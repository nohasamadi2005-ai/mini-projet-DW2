<?php
require_once __DIR__ . '/../BD/AgentImport.php';
require_once __DIR__ . '/../BD/Agent.php';

const DEPOT_TAILLE_MAX = 10 * 1024 * 1024;
const DEPOT_LIGNES_MAX = 5000;
const DEPOT_VALEUR_MAX = 9999999999.99;

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

    $r = importerFichierReleve($chemin, $nomFichier, $idAgent, $annee);
    if ($r['erreur'] !== null) {
        @unlink($chemin);
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
    $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu);

    $lignes = preg_split('/\r\n|\r|\n/', $contenu);
    if (count($lignes) > DEPOT_LIGNES_MAX) {
        return $echec("Le fichier contient plus de " . DEPOT_LIGNES_MAX . " lignes.");
    }

    $etat = getEtatContratsPourAgent($idAgent, $annee);
    $vus  = [];
    $nbInt = 0;
    $nbRej = 0;

    $cnx = consoConnexion();
    mysqli_begin_transaction($cnx);

    foreach ($lignes as $i => $brut) {
        $numero = $i + 1;
        $ligne  = trim(versUtf8($brut));
               if ($ligne === '' || $ligne[0] === '#' || preg_match('/^\d+\s*[,;\t]\s*$/', $ligne)) {
            continue;
        }
        $a = analyserLigneReleve($ligne, $etat, $vus, $annee);

        $statut = $a['motif'] === null ? 'Integre' : 'Rejete';
        $motif  = $a['motif'];
        if ($motif !== null && $a['doublon_de'] !== null) {
            $motif .= " (déjà en ligne {$a['doublon_de']})";
        }

        if (!insererLigneImport($a['client'], $idAgent, $annee, $a['eau'], $a['elec'],
                                $nomFichier, $numero, $ligne, $statut, $motif, $a['contrat'])) {
            mysqli_rollback($cnx);
            return $echec("Erreur d'enregistrement à la ligne $numero : le dépôt a été annulé, rien n'a été enregistré.");
        }

        if ($statut === 'Integre') {
            $vus[(int) $a['contrat']] = $numero;
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

function analyserLigneReleve($ligne, $etat, $vus, $annee)
{
    $r = ['contrat' => null, 'client' => null, 'eau' => null, 'elec' => null, 'motif' => null, 'doublon_de' => null];

    $p = array_map('trim', preg_split('/[,;\t]/', $ligne));

    if (count($p) !== 2) {
        $r['motif'] = "Format invalide : 2 colonnes attendues (ID contrat ; consommation), "
                    . count($p) . " trouvée(s). Utilisez le point comme séparateur décimal.";
        return $r;
    }

    if (!ctype_digit($p[0]) || (int) $p[0] <= 0 || strlen($p[0]) > 9) {
        $r['motif'] = "ID contrat invalide";
        return $r;
    }
    $id = (int) $p[0];
    $c  = $etat[$id] ?? null;
       if ($c !== null && !$c['dans_tournee']) {
         $c = null;
       }
      if ($c !== null) {
         $r['contrat'] = $id;
         $r['client']  = $c['client'];
       }

    if (!estConsommationValide($p[1])) {
        $r['motif'] = "Consommation invalide (nombre positif attendu)";
        return $r;
    }
    if ($c !== null) {
        $r[$c['type'] === 'Eau' ? 'eau' : 'elec'] = (float) $p[1];
    }

    if ($c === null) {
      $r['motif'] = "Contrat introuvable dans votre tournée (ID $id)";
        return $r;
    }
    if (!$c['valide_annee']) {
        $r['motif'] = "Contrat non en cours en $annee";
        return $r;
    }
    
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

function versUtf8($texte)
{
    if (preg_match('//u', $texte)) {
        return $texte;
    }
    $conv = @iconv('Windows-1252', 'UTF-8//IGNORE', $texte);
    return $conv === false ? '' : $conv;
}

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

function anneeDemandee($annees)
{
    $annee = (int) ($_GET['annee'] ?? date('Y'));
    return in_array($annee, $annees, true) ? $annee : (int) date('Y');
}

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

function genererFeuilleTournee($idAgent, $annee, $tous = false)
{
    $agent   = getAgentParId($idAgent);
    $tournee = getTourneeAgent($idAgent, $annee);
    $unite   = ['Eau' => 'eau, m3', 'Electricite' => 'électricité, kWh'];

    $contrats = array_values(array_filter($tournee, function ($l) use ($tous) {
        return $tous || !$l['Releve'];
    }));

    $nomAgent = $agent ? trim($agent['Prenom'] . ' ' . $agent['Nom']) : 'Agent';
    $out = [
        "# FLUXIO - Feuille de tournée $annee",
        "# Agent : $nomAgent   |   Générée le " . date('d/m/Y H:i'),
        "# " . ($tous ? "Tous les contrats de la tournée" : "Contrats encore à relever") . " : " . count($contrats),
        "#",
        "# À REMPLIR : une ligne par contrat    ID_Contrat ; consommation de l'année",
        "#             (m3 pour un compteur d'eau, kWh pour un compteur d'électricité)",
        "# Exemple rempli : 1001;157.8      (point décimal, pas de virgule)",
        "# Supprimez les lignes des contrats que vous n'avez pas relevés. Les lignes # sont ignorées.",
        "# Puis déposez ce fichier dans « Déposer un relevé annuel », année $annee.",
        "#",
    ];
    if (!$contrats) {
        $out[] = "# Tournée complète : tous les contrats sont déjà relevés pour $annee.";
    }

    $quartierCourant = null;
    foreach ($contrats as $l) {
        $quartier = $l['Quartier'] . ' (' . $l['Ville'] . ')';
        if ($quartier !== $quartierCourant) {
            $quartierCourant = $quartier;
            $out[] = "";
            $out[] = "# ===== Quartier : $quartier =====";
        }
        $note = '';
        if ($l['Statut_Contrat'] === 'Resilie' && !empty($l['Date_Fin'])) {
            $note = " | résilié le " . date('d/m/Y', strtotime($l['Date_Fin']));
        }
        $out[] = "# " . trim($l['Prenom'] . ' ' . $l['Nom']) . " | " . ($unite[$l['Type_Compteur']] ?? $l['Type_Compteur'])
               . " | " . ($l['Adresse_Branchement'] ?: 'adresse non renseignée') . $note;
        $out[] = (int) $l['ID_Contrat'] . ";";
    }
    return implode("\r\n", $out) . "\r\n";
}

function envoyerFeuilleTournee()
{
    $idAgent = (int) ($_SESSION['user']['id'] ?? 0);
    $annee   = anneeDemandee(anneesAgent());
    $tous    = !empty($_GET['tous']);

    $contenu = genererFeuilleTournee($idAgent, $annee, $tous);

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="feuille_tournee_' . $annee . '_agent' . $idAgent . '.txt"');
    header('Content-Length: ' . (strlen($contenu) + 3));
    echo "\xEF\xBB\xBF" . $contenu;
    exit;
}

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