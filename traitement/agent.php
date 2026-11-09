<?php
require_once __DIR__ . '/../BD/AgentImport.php';
require_once __DIR__ . '/../BD/Agent.php';


const DEPOT_TAILLE_MAX = 10 * 1024 * 1024;   // 10 Mo (affiché dans le formulaire)
const DEPOT_LIGNES_MAX = 5000;
const DEPOT_VALEUR_MAX = 9999999999.99;      // limite de decimal(12,2)

// =====================================================================
//  PARTIE HTTP
// =====================================================================

function traiterDepotReleve()
{
    $res = ['erreurs' => [], 'succes' => '', 'fichier' => null, 'nb_integrees' => 0, 'nb_rejetees' => 0];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $res;
    }

    $idAgent = (int) ($_SESSION['user']['id'] ?? 0);
    if ($idAgent <= 0) {
        $res['erreurs'][] = "Session expirée : reconnectez-vous.";
        return $res;
    }

    $annee = (int) ($_POST['annee'] ?? date('Y'));
    if ($annee < 2000 || $annee > 2100) {
        $res['erreurs'][] = "Année de référence invalide.";
        return $res;
    }

    // --- Le fichier envoyé ---
    $f = $_FILES['fichier_txt'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        $codes = [
            UPLOAD_ERR_INI_SIZE   => "Le fichier dépasse la taille maximale autorisée par le serveur.",
            UPLOAD_ERR_FORM_SIZE  => "Le fichier dépasse la taille maximale autorisée.",
            UPLOAD_ERR_PARTIAL    => "Le fichier n'a été que partiellement envoyé. Réessayez.",
            UPLOAD_ERR_NO_FILE    => "Veuillez sélectionner un fichier .TXT valide.",
        ];
        $res['erreurs'][] = $codes[$f['error'] ?? UPLOAD_ERR_NO_FILE] ?? "Erreur lors de l'envoi du fichier.";
        return $res;
    }
    if ($f['size'] > DEPOT_TAILLE_MAX) {
        $res['erreurs'][] = "Le fichier dépasse 10 Mo.";
        return $res;
    }

    $nomOrigine = basename($f['name']);
    if (strtolower(pathinfo($nomOrigine, PATHINFO_EXTENSION)) !== 'txt') {
        $res['erreurs'][] = "Le format du fichier doit être obligatoirement .TXT.";
        return $res;
    }

    // --- Sauvegarde sous un nom unique (un 2e dépôt du même nom n'écrase plus le 1er) ---
    $slug = trim(substr(preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($nomOrigine, PATHINFO_FILENAME)), 0, 40), '_');
    if ($slug === '') {
        $slug = 'releve';
    }
    $nomFichier = $slug . '_a' . $idAgent . '_' . $annee . '_' . bin2hex(random_bytes(4)) . '.txt';

    $dossier = __DIR__ . '/../uploads/';
    if (!is_dir($dossier)) {
        mkdir($dossier, 0777, true);
    }
    $chemin = $dossier . $nomFichier;
    if (!move_uploaded_file($f['tmp_name'], $chemin)) {
        $res['erreurs'][] = "Impossible d'enregistrer le fichier sur le serveur.";
        return $res;
    }

    // --- Import ---
    $r = importerFichierReleve($chemin, $nomFichier, $idAgent, $annee);
    if ($r['erreur'] !== null) {
        @unlink($chemin);                       // rien n'a été gardé en base : on ne garde pas le fichier
        $res['erreurs'][] = $r['erreur'];
        return $res;
    }

    $res['fichier']      = $nomFichier;
    $res['nb_integrees'] = $r['integrees'];
    $res['nb_rejetees']  = $r['rejetees'];

    if ($r['integrees'] === 0) {
        $res['erreurs'][] = "Aucune ligne valide : les {$r['rejetees']} ligne(s) du fichier ont été rejetées. "
                          . "Consultez le détail du dépôt dans l'historique.";
    } else {
        $res['succes'] = "Le fichier a été importé : {$r['integrees']} relevé(s) intégré(s)";
        $res['succes'] .= $r['rejetees'] > 0
            ? ", {$r['rejetees']} ligne(s) rejetée(s) (voir le détail dans l'historique)."
            : " avec succès.";
    }
    return $res;
}

// =====================================================================
//  LOGIQUE D'IMPORT
// =====================================================================
 
/*
 * Lit le fichier, contrôle chaque ligne et l'enregistre (intégrée ou rejetée).
 * $nomFichier = valeur stockée dans Fichier_Source.
 * Retourne ['integrees' => n, 'rejetees' => n, 'erreur' => null | message].
 * En cas d'erreur, rien n'est enregistré en base.
 */
function importerFichierReleve($chemin, $nomFichier, $idAgent, $annee)
{
    $echec = fn($msg) => ['integrees' => 0, 'rejetees' => 0, 'erreur' => $msg];
 
    $contenu = @file_get_contents($chemin);
    if ($contenu === false) {
        return $echec("Impossible de lire le fichier.");
    }
    if (strpos($contenu, "\0") !== false) {
        return $echec("Ce fichier n'est pas un fichier texte.");
    }
    $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu);          // BOM UTF-8 éventuel
 
    $lignes = preg_split('/\r\n|\r|\n/', $contenu);
    if (count($lignes) > DEPOT_LIGNES_MAX) {
        return $echec("Le fichier contient plus de " . DEPOT_LIGNES_MAX . " lignes.");
    }
 
    $etat = getEtatClientsPourAgent($idAgent);   // [ID_Client => contrats actifs / dans la tournée]
    $vus  = [];                                   // clients déjà intégrés dans ce fichier : [ID_Client => n° de ligne]
    $nbInt = 0;
    $nbRej = 0;
 
    $cnx = consoConnexion();
    mysqli_begin_transaction($cnx);
 
    foreach ($lignes as $i => $brut) {
        $numero = $i + 1;
        $ligne  = trim(versUtf8($brut));
        if ($ligne === '' || $ligne[0] === '#') {
            continue;                              // ligne vide ou commentaire : ignorée
        }
 
        $a = analyserLigneReleve($ligne, $etat, $vus);
 
        $statut = $a['motif'] === null ? 'Integre' : 'Rejete';
        $motif  = $a['motif'];
        if ($motif !== null && $a['doublon_de'] !== null) {
            $motif .= " (déjà en ligne {$a['doublon_de']})";
        }
 
        if (!insererLigneImport($a['client'], $idAgent, $annee, $a['eau'], $a['elec'],
                                $nomFichier, $numero, $ligne, $statut, $motif)) {
            mysqli_rollback($cnx);
            return $echec("Erreur d'enregistrement à la ligne $numero : le dépôt a été annulé, rien n'a été enregistré.");
        }
 
        if ($statut === 'Integre') {
            $vus[(int) $a['client']] = $numero;
            $nbInt++;
        } else {
            $nbRej++;
        }
    }
 
    if ($nbInt + $nbRej === 0) {
        mysqli_rollback($cnx);
        return $echec("Aucune ligne de relevé trouvée dans le fichier.");
    }
 
    mysqli_commit($cnx);
    return ['integrees' => $nbInt, 'rejetees' => $nbRej, 'erreur' => null];
}
 
/*
 * Contrôle UNE ligne (déjà nettoyée). Retourne :
 *   'client' => ID_Client à enregistrer (NULL si inconnu ou illisible)
 *   'eau', 'elec' => valeurs lues (NULL si illisibles)
 *   'motif' => NULL si la ligne est valide, sinon le motif du rejet
 *   'doublon_de' => n° de la ligne déjà intégrée pour ce client (motif « doublon »), sinon NULL
 */
function analyserLigneReleve($ligne, $etat, $vus)
{
    $r = ['client' => null, 'eau' => null, 'elec' => null, 'motif' => null, 'doublon_de' => null];
 
    $p = array_map('trim', preg_split('/[,;\t]+/', $ligne));
 
    // 1. Exactement 3 colonnes (sinon une virgule décimale décalerait silencieusement les valeurs)
    if (count($p) !== 3) {
        $r['motif'] = "Format invalide : 3 colonnes attendues (ID client ; eau ; électricité), "
                    . count($p) . " trouvée(s). Utilisez le point comme séparateur décimal.";
        return $r;
    }
 
    // 2. ID client
    if (!ctype_digit($p[0]) || (int) $p[0] <= 0 || strlen($p[0]) > 9) {
        $r['motif'] = "ID client invalide";
        return $r;
    }
    $id = (int) $p[0];
    $r['client'] = isset($etat[$id]) ? $id : null;     // un client inconnu ne peut pas être référencé en base
 
    // 3. Consommations
    $okEau  = estConsommationValide($p[1]);
    $okElec = estConsommationValide($p[2]);
    $r['eau']  = $okEau  ? (float) $p[1] : null;
    $r['elec'] = $okElec ? (float) $p[2] : null;
    if (!$okEau || !$okElec) {
        $r['motif'] = "Consommation invalide (" . (!$okEau ? "eau" : "électricité")
                    . " : nombre positif attendu)";
        return $r;
    }
 
    // 4. Client existant ?
    if (!isset($etat[$id])) {
        $r['motif'] = "Client introuvable (ID $id)";
        return $r;
    }
    // 5. Contrat actif ?
    if ($etat[$id]['contrats_actifs'] === 0) {
        $r['motif'] = "Aucun contrat actif";
        return $r;
    }
    // 6. Dans la tournée de l'agent ?
    if ($etat[$id]['dans_tournee'] === 0) {
        $r['motif'] = "Hors de votre tournée";
        return $r;
    }
    // 7. Déjà dans ce fichier ?
    if (isset($vus[$id])) {
        $r['motif'] = "Doublon dans le fichier";
        $r['doublon_de'] = $vus[$id];
        return $r;
    }
    return $r;
}
 
function estConsommationValide($texte)
{
    return is_numeric($texte) && (float) $texte >= 0 && (float) $texte <= DEPOT_VALEUR_MAX;
}
 
// Texte non UTF-8 (fichier .txt enregistré en ANSI / Windows-1252) -> UTF-8
function versUtf8($texte)
{
    if (preg_match('//u', $texte)) {
        return $texte;
    }
    $conv = @iconv('Windows-1252', 'UTF-8//IGNORE', $texte);
    return $conv === false ? '' : $conv;
}

// Années proposées dans les listes : les 2 dernières, l'année courante, la suivante, + celles qui ont des dépôts
function anneesAgent()
{
    $courante = (int) date('Y');
    $annees   = range($courante + 1, $courante - 2);
    foreach (consoSelect("SELECT DISTINCT Annee FROM consommation_annuelle") as $r) {
        $annees[] = (int) $r['Annee'];
    }
    $annees = array_values(array_unique($annees));
    rsort($annees);
    return $annees;
}

// Année demandée dans l'URL (?annee=...), sinon l'année courante
function anneeDemandee($annees)
{
    $annee = (int) ($_GET['annee'] ?? date('Y'));
    return in_array($annee, $annees, true) ? $annee : (int) date('Y');
}


// =====================================================================
//  « MA TOURNÉE »
// =====================================================================

/*
 * Données de la page « Ma tournée » (?page=agent_tournee&annee=2027&statut=tous|a_releve|releve)
 *   $d['agent'], $d['annee'], $d['annees'], $d['filtre'], $d['quartiers'],
 *   $d['progression'] = [total, releves, restants, pourcentage]   (toujours sur toute la tournée)
 *   $d['lignes']      = contrats affichés (selon le filtre)
 */
function preparerPageTournee()
{
    $idAgent = (int) ($_SESSION['user']['id'] ?? 0);
    $annees  = anneesAgent();
    $annee   = anneeDemandee($annees);

    $filtre = $_GET['statut'] ?? 'tous';
    if (!in_array($filtre, ['tous', 'a_releve', 'releve'], true)) {
        $filtre = 'tous';
    }

    $tournee = getTourneeAgent($idAgent, $annee);
    $lignes  = array_values(array_filter($tournee, function ($l) use ($filtre) {
        return $filtre === 'tous' || ($filtre === 'releve') === (bool) $l['Releve'];
    }));

    return [
        'agent'       => getAgentParId($idAgent),
        'annee'       => $annee,
        'annees'      => $annees,
        'filtre'      => $filtre,
        'quartiers'   => getQuartiersAgent($idAgent),
        'progression' => getProgressionTournee($idAgent, $annee, $tournee),
        'lignes'      => $lignes,
    ];
}

/*
 * Texte de la feuille de tournée : une ligne par CLIENT (ses contrats eau + électricité sont
 * regroupés), au format du dépôt « ID_Client ; eau ; électricité » avec les valeurs à remplir.
 * Par défaut seulement les clients encore à relever ; $tous = true -> toute la tournée.
 * Les lignes # (quartier, nom, adresse, compteurs) sont des commentaires ignorés au dépôt.
 */
function genererFeuilleTournee($idAgent, $annee, $tous = false)
{
    $agent   = getAgentParId($idAgent);
    $tournee = getTourneeAgent($idAgent, $annee);
    $libelle = ['Eau' => 'Eau', 'Electricite' => 'Électricité'];

    $clients = [];
    foreach ($tournee as $l) {
        if (!$tous && $l['Releve']) {
            continue;
        }
        $c = (int) $l['ID_Client'];
        if (!isset($clients[$c])) {
            $clients[$c] = [
                'nom'      => trim($l['Prenom'] . ' ' . $l['Nom']),
                'quartier' => $l['Quartier'] . ' (' . $l['Ville'] . ')',
                'adresses' => [],
                'types'    => [],
            ];
        }
        if (!empty($l['Adresse_Branchement'])) {
            $clients[$c]['adresses'][$l['Adresse_Branchement']] = true;
        }
        $clients[$c]['types'][$libelle[$l['Type_Compteur']] ?? $l['Type_Compteur']] = true;
    }

    $nomAgent = $agent ? trim($agent['Prenom'] . ' ' . $agent['Nom']) : 'Agent';
    $out = [
        "# FLUXIO - Feuille de tournée $annee",
        "# Agent : $nomAgent   |   Générée le " . date('d/m/Y H:i'),
        "# " . ($tous ? "Tous les clients de la tournée" : "Clients encore à relever") . " : " . count($clients),
        "#",
        "# À REMPLIR : une ligne par client    ID_Client ; eau (m3) ; électricité (kWh)",
        "# Exemple rempli : 12;157.8;2555      (point décimal, pas de virgule)",
        "# Supprimez les lignes des clients que vous n'avez pas relevés. Les lignes # sont ignorées.",
        "# Puis déposez ce fichier dans « Déposer un relevé annuel », année $annee.",
        "#",
    ];
    if (!$clients) {
        $out[] = "# Tournée complète : tous les clients sont déjà relevés pour $annee.";
    }

    $quartierCourant = null;
    foreach ($clients as $id => $c) {
        if ($c['quartier'] !== $quartierCourant) {
            $quartierCourant = $c['quartier'];
            $out[] = "";
            $out[] = "# ===== Quartier : $quartierCourant =====";
        }
        $out[] = "# " . $c['nom'] . " | " . (implode(' / ', array_keys($c['adresses'])) ?: 'adresse non renseignée')
               . " | compteurs : " . implode(', ', array_keys($c['types']));
        $out[] = "$id;;";
    }
    return implode("\r\n", $out) . "\r\n";
}

// Télécharge la feuille de tournée (?page=agent_tournee_export&annee=2027[&tous=1]) puis termine
function envoyerFeuilleTournee()
{
    $idAgent = (int) ($_SESSION['user']['id'] ?? 0);
    $annee   = anneeDemandee(anneesAgent());
    $tous    = !empty($_GET['tous']);

    $contenu = genererFeuilleTournee($idAgent, $annee, $tous);

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="feuille_tournee_' . $annee . '_agent' . $idAgent . '.txt"');
    header('Content-Length: ' . (strlen($contenu) + 3));
    echo "\xEF\xBB\xBF" . $contenu;      // BOM : Notepad affiche bien les accents
    exit;
}


// =====================================================================
//  DÉTAIL D'UN DÉPÔT
// =====================================================================

/*
 * Données de la page « Détail du dépôt » (?page=agent_historique_detail&fichier=...).
 * Retourne null si le fichier n'existe pas pour cet agent (index.php affiche alors la 404).
 *   $d['fichier'], $d['annee'], $d['date_import'], $d['integrees'], $d['rejetees'],
 *   $d['nb_integrees'], $d['nb_rejetees'], $d['statut'] ('Integre' | 'Partiel' | 'Rejete')
 */
function preparerPageDetailDepot()
{
    $idAgent = (int) ($_SESSION['user']['id'] ?? 0);
    $fichier = basename((string) ($_GET['fichier'] ?? ''));
    if ($fichier === '') {
        return null;
    }
    $detail = getDetailDepot($idAgent, $fichier);
    if ($detail === null) {
        return null;
    }
    $detail['nb_integrees'] = count($detail['integrees']);
    $detail['nb_rejetees']  = count($detail['rejetees']);
    $detail['statut'] = $detail['nb_rejetees'] === 0 ? 'Integre'
                      : ($detail['nb_integrees'] === 0 ? 'Rejete' : 'Partiel');
    return $detail;
}


// =====================================================================
//  ÉCRAN FOURNISSEUR « AGENTS »
// =====================================================================

/*
 * Données de la page « Agents » (?page=agents).
 *   $d['agents']       = agents (avec Nb_Quartiers)
 *   $d['quartiers']    = tous les quartiers (ID_Quartier, Nom, Ville, Type_Activite)
 *   $d['affectations'] = [ID_Quartier => ID_Agent]  (pour pré-cocher les cases)
 *   $d['flash']        = ['type' => 'ok'|'erreur', 'texte' => ...] ou null (message après enregistrement)
 */
function preparerPageAgents()
{
    $flash = $_SESSION['flash_agents'] ?? null;
    unset($_SESSION['flash_agents']);

    return [
        'agents'       => getAgents(),
        'quartiers'    => getQuartiersPourAffectation(),
        'affectations' => getAffectationsQuartiers(),
        'flash'        => $flash,
    ];
}

// POST ?page=agents_action : champs id_agent et quartiers[] (cases cochées). Ne redirige pas : c'est index.php qui le fait.
function traiterActionAgents()
{
    $idAgent = (int) ($_POST['id_agent'] ?? 0);
    $ids     = $_POST['quartiers'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }

    $agent = getAgentParId($idAgent);
    if (!$agent) {
        $_SESSION['flash_agents'] = ['type' => 'erreur', 'texte' => "Agent introuvable."];
    } elseif (setQuartiersAgent($idAgent, $ids)) {
        $nb = count(getQuartiersAgent($idAgent));
        $_SESSION['flash_agents'] = ['type' => 'ok',
            'texte' => "Tournée de {$agent['Prenom']} {$agent['Nom']} enregistrée : $nb quartier(s)."];
    } else {
        $_SESSION['flash_agents'] = ['type' => 'erreur',
            'texte' => "Impossible d'enregistrer les quartiers de {$agent['Prenom']} {$agent['Nom']}."];
    }
}
 
