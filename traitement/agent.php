<?php

require_once __DIR__ . '/../BD/AgentImport.php';
require_once __DIR__ . '/../BD/Agent.php';

const DEPOT_TAILLE_MAX = 10 * 1024 * 1024;   // 10 Mo (affiché dans le formulaire)
const DEPOT_LIGNES_MAX = 5000;
const DEPOT_VALEUR_MAX = 9999999999.99;      // limite de decimal(12,2)

//  PARTIE HTTP

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
//  LOGIQUE D'IMPORT

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

    $etat = getEtatContratsPourAgent($idAgent, $annee);   // [ID_Contrat => client, type, en cours, dans la tournée]
    $vus  = [];                                            // contrats déjà intégrés dans ce fichier : [ID_Contrat => n° de ligne]
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

    $p = array_map('trim', preg_split('/[,;\t]+/', $ligne));

    // 1. Exactement 2 colonnes (sinon une virgule décimale décalerait silencieusement les valeurs)
    if (count($p) !== 2) {
        $r['motif'] = "Format invalide : 2 colonnes attendues (ID contrat ; consommation), "
                    . count($p) . " trouvée(s). Utilisez le point comme séparateur décimal.";
        return $r;
    }

    // 2. ID contrat
    if (!ctype_digit($p[0]) || (int) $p[0] <= 0 || strlen($p[0]) > 9) {
        $r['motif'] = "ID contrat invalide";
        return $r;
    }
    $id = (int) $p[0];
    $c  = $etat[$id] ?? null;
    if ($c !== null) {
        $r['contrat'] = $id;                    // un contrat inconnu ne peut pas être référencé en base
        $r['client']  = $c['client'];
    }

    // 3. Consommation
    if (!estConsommationValide($p[1])) {
        $r['motif'] = "Consommation invalide (nombre positif attendu)";
        return $r;
    }
    if ($c !== null) {
        $r[$c['type'] === 'Eau' ? 'eau' : 'elec'] = (float) $p[1];
    }

    // 4. Contrat existant ?
    if ($c === null) {
        $r['motif'] = "Contrat introuvable (ID $id)";
        return $r;
    }
    // 5. Contrat en cours pendant l'année du dépôt ?
    if (!$c['valide_annee']) {
        $r['motif'] = "Contrat non en cours en $annee";
        return $r;
    }
    // 6. Dans la tournée de l'agent ?
    if (!$c['dans_tournee']) {
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


//  PAGINATION (espace agent)

const PAGINATION_PAR_PAGE = 10;

function paginer($items, $parPage = PAGINATION_PAR_PAGE, $param = 'p')
{
    $items   = array_values((array) $items);
    $total   = count($items);
    $parPage = max(1, (int) $parPage);
    $pages   = max(1, (int) ceil($total / $parPage));
    $page    = min(max((int) ($_GET[$param] ?? 1), 1), $pages);     // numéro invalide -> page 1 ou dernière page
    $debut   = ($page - 1) * $parPage;

    return [
        'items'    => array_slice($items, $debut, $parPage),
        'page'     => $page,
        'pages'    => $pages,
        'total'    => $total,
        'par_page' => $parPage,
        'debut'    => $total ? $debut + 1 : 0,
        'fin'      => min($debut + $parPage, $total),
    ];
}

function afficherPagination($pg, $param = 'p', $ancre = '')
{
    if ($pg['pages'] <= 1) {
        return;                                   // une seule page : rien à afficher
    }

    static $cssAffiche = false;
    if (!$cssAffiche) {
        $cssAffiche = true;
        echo '<style>
  .pagination { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
  .pagination .info { font-size: 13px; color: #6b7a90; }
  .pagination .pages { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
  .pagination .pages a, .pagination .pages span { min-width: 34px; height: 34px; padding: 0 10px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid #e2e8f0; background: #fff; color: #14213d; }
  .pagination .pages a:hover { background: #eff6ff; border-color: #bfdbfe; color: #0284c7; }
  .pagination .pages .cur { background: #0284c7; border-color: #0284c7; color: #fff; }
  .pagination .pages .off { color: #cbd5e1; background: #f8fafc; }
  .pagination .pages .dots { border: 0; background: transparent; color: #94a3b8; min-width: 20px; padding: 0; }
</style>';
    }

    $q = $_GET;
    unset($q[$param]);
    $lien = function ($n) use ($q, $param, $ancre) {
        $q2 = $q;
        if ($n > 1) {
            $q2[$param] = $n;
        }
        return 'index.php?' . htmlspecialchars(http_build_query($q2)) . ($ancre !== '' ? '#' . $ancre : '');
    };

    // pages affichées : la première, la dernière et celles autour de la page courante
    $affichees = [1, $pg['pages']];
    for ($i = $pg['page'] - 1; $i <= $pg['page'] + 1; $i++) {
        if ($i > 1 && $i < $pg['pages']) {
            $affichees[] = $i;
        }
    }
    $affichees = array_unique($affichees);
    sort($affichees);

    echo '<div class="pagination">';
    echo '<div class="info">Affichage ' . (int) $pg['debut'] . '–' . (int) $pg['fin'] . ' sur ' . (int) $pg['total'] . '</div>';
    echo '<div class="pages">';

    echo $pg['page'] > 1
        ? '<a href="' . $lien($pg['page'] - 1) . '">‹ Précédent</a>'
        : '<span class="off">‹ Précédent</span>';

    $precedente = 0;
    foreach ($affichees as $n) {
        if ($n - $precedente === 2) {
            echo '<a href="' . $lien($precedente + 1) . '">' . ($precedente + 1) . '</a>';     // une seule page cachée : on l'affiche
        } elseif ($n - $precedente > 2) {
            echo '<span class="dots">…</span>';
        }
        echo $n === $pg['page']
            ? '<span class="cur">' . $n . '</span>'
            : '<a href="' . $lien($n) . '">' . $n . '</a>';
        $precedente = $n;
    }

    echo $pg['page'] < $pg['pages']
        ? '<a href="' . $lien($pg['page'] + 1) . '">Suivant ›</a>'
        : '<span class="off">Suivant ›</span>';

    echo '</div></div>';
}


// =====================================================================
//  OUTILS COMMUNS DE L'ESPACE AGENT
// =====================================================================

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


//  « MA TOURNÉE »

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

    $pg = paginer($lignes, PAGINATION_PAR_PAGE, 'p');      // 10 contrats par page (?p=2)

    return [
        'agent'       => getAgentParId($idAgent),
        'annee'       => $annee,
        'annees'      => $annees,
        'filtre'      => $filtre,
        'quartiers'   => getQuartiersAgent($idAgent),
        'progression' => getProgressionTournee($idAgent, $annee, $tournee),
        'lignes'      => $pg['items'],
        'pagination'  => $pg,
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


//  DÉTAIL D'UN DÉPÔT

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

    // 10 lignes par page et par tableau (?pr=2 pour les rejetées, ?pi=2 pour les intégrées)
    $pr = paginer($detail['rejetees'], PAGINATION_PAR_PAGE, 'pr');
    $pi = paginer($detail['integrees'], PAGINATION_PAR_PAGE, 'pi');
    $detail['rejetees']    = $pr['items'];
    $detail['integrees']   = $pi['items'];
    $detail['pg_rejetees']  = $pr;
    $detail['pg_integrees'] = $pi;
    return $detail;
}

