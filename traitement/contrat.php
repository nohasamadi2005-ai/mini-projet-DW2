<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/Facture.php';
require_once __DIR__ . '/../BD/Contrat.php';
require_once __DIR__ . '/../BD/Notification.php';
require_once __DIR__ . '/../BD/Quartier.php';

const CONTRATS_PAR_PAGE = 10;
const MOTIFS_FIN = ['Resiliation' => 'Résiliation', 'Demenagement' => 'Déménagement', 'Transfert' => 'Transfert'];

function aujourdhuiContrat()
{
    return date('Y-m-d', maintenant());
}

function dateValide($s)
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}

function nombreSaisi($s)
{
    $s = str_replace([',', ' '], ['.', ''], trim((string) $s));
    return is_numeric($s) ? round((float) $s, 2) : null;
}

function fr($n)
{
    return number_format((float) $n, 2, ',', ' ');
}

function dateContratFr($d)
{
    return $d ? date('d/m/Y', strtotime($d)) : '—';
}

function verifierCompteurNouveauContrat($p, $dateDebut, $idCompteurExclu = 0)
{
    $mode = ($p['mode'] ?? 'libre') === 'neuf' ? 'neuf' : 'libre';

    if ($mode === 'libre') {
        $num = (int) ($p['compteur_libre'] ?? 0);
        $cp  = $num ? getCompteurPourContrat($num) : null;
        if (!$cp) {
            return ["choisissez un compteur libre dans la liste.", null];
        }
        if ($cp['ID_Client'] !== null || getContratActifDuCompteur($num)) {
            return ["le compteur $num a déjà un contrat actif.", null];
        }
        if ($num === (int) $idCompteurExclu) {
            return ["le nouveau logement doit avoir un autre compteur que l'ancien.", null];
        }
        if ($cp['Derniere_Fin'] && $dateDebut < $cp['Derniere_Fin']) {
            return ["le compteur $num était encore sous contrat jusqu'au " . dateContratFr($cp['Derniere_Fin'])
                  . " : la date de début doit être postérieure.", null];
        }
        $quartier = $cp['Dernier_Quartier'] ? (int) $cp['Dernier_Quartier'] : (int) ($p['quartier'] ?? 0);
        return [null, [
            'idCompteur'   => $num,
            'type'         => $cp['Type_Compteur'],
            'indexDebut'   => (float) $cp['Dernier_Index'],
            'idQuartier'   => $quartier,
            'adresse'      => trim($p['adresse'] ?? '') !== '' ? trim($p['adresse']) : ($cp['Derniere_Adresse'] ?? ''),
            'neuf'         => false,
            'indexInitial' => null,
        ]];
    }

    $numero = trim((string) ($p['numero'] ?? ''));
    if (!ctype_digit($numero) || (int) $numero < 1 || (int) $numero > 2147483647) {
        return ["le numéro du nouveau compteur doit être un nombre entier positif.", null];
    }
    if (getCompteurPourContrat((int) $numero)) {
        return ["le compteur $numero existe déjà : choisissez-le dans « Compteur libre » s'il est libre.", null];
    }
    $type = ($p['type'] ?? '') === 'Eau' ? 'Eau' : (($p['type'] ?? '') === 'Electricite' ? 'Electricite' : null);
    if (!$type) {
        return ["choisissez le type du nouveau compteur (eau ou électricité).", null];
    }
    $indexInitial = nombreSaisi($p['index_initial'] ?? '0');
    if ($indexInitial === null || $indexInitial < 0) {
        return ["l'index initial du nouveau compteur doit être un nombre positif (0 pour un compteur neuf).", null];
    }
    return [null, [
        'idCompteur'   => (int) $numero,
        'type'         => $type,
        'indexDebut'   => $indexInitial,
        'idQuartier'   => (int) ($p['quartier'] ?? 0),
        'adresse'      => trim($p['adresse'] ?? ''),
        'neuf'         => true,
        'indexInitial' => $indexInitial,
    ]];
}

function verifierNouveauContrat($idClient, $dateDebut, &$infos)
{
    $client = $idClient ? getClientPourContrat($idClient) : null;
    if (!$client) {
        return "choisissez le client : tapez son nom, son CIN ou son téléphone puis cliquez sur la bonne proposition.";
    }
    if (!$dateDebut) {
        return "la date de début est invalide.";
    }
    if ($dateDebut > aujourdhuiContrat()) {
        return "la date de début ne peut pas être dans le futur (aujourd'hui : " . dateContratFr(aujourdhuiContrat()) . ").";
    }
    if (!$infos['idQuartier'] || !getQuartierParId($infos['idQuartier'])) {
        return "choisissez la zone (quartier) où se trouve le compteur.";
    }
    if ($infos['adresse'] === '') {
        $infos['adresse'] = $client['Adresse'] ?? '';
    }
    return null;
}

function verifierCloture($ct, $dateFin, $indexSaisi)
{
    if (!$ct || $ct['Statut'] !== 'Actif') {
        return ["ce contrat n'est pas actif.", null];
    }
    if (!$dateFin) {
        return ["la date de fin est invalide.", null];
    }
    if ($dateFin < $ct['Date_Debut']) {
        return ["la date de fin doit être après le début du contrat (" . dateContratFr($ct['Date_Debut']) . ").", null];
    }
    if ($dateFin > aujourdhuiContrat()) {
        return ["la date de fin ne peut pas être dans le futur (aujourd'hui : " . dateContratFr(aujourdhuiContrat()) . ").", null];
    }
    if (compterRelevesEnAttente($ct['ID_Compteur']) > 0) {
        return ["un relevé de ce compteur est en attente de traitement (anomalie ou resaisie). "
              . "Traitez-le d'abord dans « Traitement des Anomalies ».", null];
    }

    $dernierIndex = (float) $ct['Dernier_Index'];
    $indexFin     = ($indexSaisi === null || $indexSaisi === '') ? $dernierIndex : nombreSaisi($indexSaisi);
    if ($indexFin === null) {
        return ["l'index de clôture doit être un nombre.", null];
    }
    if ($indexFin < $dernierIndex) {
        return ["l'index de clôture (" . fr($indexFin) . ") ne peut pas être inférieur au dernier index relevé ("
              . fr($dernierIndex) . ").", null];
    }

    $mois  = (int) date('n', strtotime($dateFin));
    $annee = (int) date('Y', strtotime($dateFin));
    $releve = false;
    if ($indexFin > $dernierIndex) {
        $dernier = getDernierReleveCompteur($ct['ID_Compteur']);
        if ($dernier && ((int) $dernier['Annee'] > $annee
                || ((int) $dernier['Annee'] === $annee && (int) $dernier['Mois'] >= $mois))) {
            return ["un relevé existe déjà pour " . nomMois((int) $dernier['Mois']) . ' ' . $dernier['Annee']
                  . " (index " . fr($dernier['Index_Actuel']) . "). Choisissez une date de fin dans un mois suivant, "
                  . "ou gardez l'index " . fr($dernier['Index_Actuel']) . ".", null];
        }
        $releve = true;
    }

    $impayes = getImpayesContrat($ct['ID_Client'], $ct['ID_Compteur']);
    $avert = $impayes['nb'] > 0
        ? " Attention : " . $impayes['nb'] . " facture(s) impayée(s) (" . fr($impayes['montant'])
          . " DH) restent dues par " . $ct['Prenom'] . ' ' . $ct['Nom'] . '.'
        : '';

    return [null, [
        'indexFin'      => $indexFin,
        'releve'        => $releve,
        'mois'          => $mois,
        'annee'         => $annee,
        'precedent'     => $dernierIndex,
        'avertissement' => $avert,
    ]];
}

function enregistrerReleveCloture($ct, $cl)
{
    if (!$cl['releve']) {
        return '';
    }
    $idConso = insererSaisie((int) $ct['ID_Client'], (int) $ct['ID_Compteur'], $cl['mois'], $cl['annee'],
        $cl['precedent'], $cl['indexFin'], round($cl['indexFin'] - $cl['precedent'], 2), null, null, 'Verifie');
    $idFacture = $idConso ? genererFacture($idConso) : null;
    return " Relevé de clôture enregistré (" . fr($cl['indexFin'] - $cl['precedent']) . ' '
         . ($ct['Type_Compteur'] === 'Eau' ? 'm³' : 'kWh') . ')'
         . ($idFacture ? ", facture de clôture n° $idFacture générée." : '.');
}

function actionCreerContrat($p, $idAdmin)
{
    $idClient  = (int) ($p['client'] ?? 0);
    $dateDebut = dateValide($p['date_debut'] ?? '');
    [$err, $cp] = verifierCompteurNouveauContrat($p, (string) $dateDebut);
    if (!$err) {
        $err = verifierNouveauContrat($idClient, $dateDebut, $cp);
    }
    if ($err) {
        return ['erreur', "Contrat non créé : $err"];
    }

    $cnx = Connexion();
    try {
        mysqli_begin_transaction($cnx);
        if ($cp['neuf']) {
            creerCompteurNeuf($cnx, $cp['idCompteur'], $cp['type'], $cp['indexInitial'], $dateDebut);
        }
        $id = insererContrat($cnx, $idClient, $cp['idCompteur'], $cp['idQuartier'], $cp['adresse'],
                             $dateDebut, $cp['indexDebut'], $idAdmin);
        definirTitulaireCompteur($cnx, $cp['idCompteur'], $idClient);
        mysqli_commit($cnx);
        return ['succes', "Contrat n° $id créé : compteur " . ($cp['type'] === 'Eau' ? "d'eau" : "d'électricité")
                        . " n° {$cp['idCompteur']}, index de départ " . fr($cp['indexDebut']) . '.'];
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($cnx);
        return ['erreur', "Contrat non créé : erreur base de données (" . $e->getMessage() . ")."];
    } finally {
        mysqli_close($cnx);
    }
}

function bloquerSiImpayes($ct, $action)
{
    $impayes = getImpayesClient($ct['ID_Client']);
    if ($impayes['nb'] === 0) {
        return null;
    }
    $montant = fr($impayes['montant']);
    $notifie = '';
    try {
        creerNotificationFacture(
            (int) $ct['ID_Client'],
            ((int) ($_SESSION['user']['id'] ?? 0)) ?: null,
            $impayes['premiere'],
            "Votre demande de $action du contrat n° {$ct['ID_Contrat']} n'a pas pu être traitée : "
            . "vous avez {$impayes['nb']} facture(s) impayée(s) pour un montant total de $montant DH. "
            . "Merci de régler vos factures ; l'opération pourra ensuite être effectuée."
        );
        $notifie = " Le client a été notifié.";
    } catch (Throwable $e) {
        $notifie = " (Notification au client impossible.)";
    }
    return $ct['Prenom'] . ' ' . $ct['Nom'] . " a encore " . $impayes['nb'] . " facture(s) impayée(s) ("
         . $montant . " DH). Elles doivent être réglées avant le/la $action." . $notifie;
}

function actionResilierContrat($p)
{
    $ct = getContratParId((int) ($p['id_contrat'] ?? 0));
    [$err, $cl] = verifierCloture($ct, dateValide($p['date_fin'] ?? ''), $p['index_fin'] ?? '');
    if ($err) {
        return ['erreur', "Résiliation impossible : $err"];
    }
    if ($err = bloquerSiImpayes($ct, 'résiliation')) {
        return ['erreur', "Résiliation impossible : $err"];
    }
    $cnx = Connexion();
    try {
        mysqli_begin_transaction($cnx);
        cloturerContrat($cnx, $ct['ID_Contrat'], $p['date_fin'], $cl['indexFin'], 'Resiliation');
        definirTitulaireCompteur($cnx, $ct['ID_Compteur'], null);
        mysqli_commit($cnx);
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($cnx);
        return ['erreur', "Résiliation impossible : erreur base de données (" . $e->getMessage() . ")."];
    } finally {
        mysqli_close($cnx);
    }
    return ['succes', "Contrat n° {$ct['ID_Contrat']} résilié au " . dateContratFr($p['date_fin'])
                    . " (index de fin " . fr($cl['indexFin']) . "). Le compteur n° {$ct['ID_Compteur']} est maintenant libre."
                    . enregistrerReleveCloture($ct, $cl) . $cl['avertissement']];
}

function actionTransfererContrat($p, $idAdmin)
{
    $ct       = getContratParId((int) ($p['id_contrat'] ?? 0));
    $date     = dateValide($p['date_fin'] ?? '');
    $nouveau  = (int) ($p['nouveau_client'] ?? 0);
    [$err, $cl] = verifierCloture($ct, $date, $p['index_fin'] ?? '');
    if (!$err && $nouveau === (int) $ct['ID_Client']) {
        $err = "le nouveau titulaire doit être un autre client.";
    }
    $infos = ['idQuartier' => $ct ? (int) $ct['ID_Quartier'] : 0, 'adresse' => $ct['Adresse_Branchement'] ?? ''];
    if (!$err) {
        $err = verifierNouveauContrat($nouveau, $date, $infos);
    }
    if ($err) {
        return ['erreur', "Transfert impossible : $err"];
    }
    if ($err = bloquerSiImpayes($ct, 'transfert')) {
        return ['erreur', "Transfert impossible : $err"];
    }

    $cnx = Connexion();
    try {
        mysqli_begin_transaction($cnx);
        cloturerContrat($cnx, $ct['ID_Contrat'], $date, $cl['indexFin'], 'Transfert');
        $id = insererContrat($cnx, $nouveau, $ct['ID_Compteur'], $ct['ID_Quartier'], $infos['adresse'],
                             $date, $cl['indexFin'], $idAdmin);
        definirTitulaireCompteur($cnx, $ct['ID_Compteur'], $nouveau);
        mysqli_commit($cnx);
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($cnx);
        return ['erreur', "Transfert impossible : erreur base de données (" . $e->getMessage() . ")."];
    } finally {
        mysqli_close($cnx);
    }
    return ['succes', "Compteur n° {$ct['ID_Compteur']} transféré : contrat n° {$ct['ID_Contrat']} clôturé, "
                    . "nouveau contrat n° $id (index de départ " . fr($cl['indexFin']) . ")."
                    . enregistrerReleveCloture($ct, $cl) . $cl['avertissement']];
}

function actionDemenagerClient($p, $idAdmin)
{
    $ct   = getContratParId((int) ($p['id_contrat'] ?? 0));
    $date = dateValide($p['date_fin'] ?? '');
    [$err, $cl] = verifierCloture($ct, $date, $p['index_fin'] ?? '');
    $cp = null;
    if (!$err) {
        [$err, $cp] = verifierCompteurNouveauContrat($p, (string) $date, (int) $ct['ID_Compteur']);
    }
    if (!$err && $cp['type'] !== $ct['Type_Compteur']) {
        $err = "le nouveau compteur doit être du même type (" . ($ct['Type_Compteur'] === 'Eau' ? 'eau' : 'électricité') . ").";
    }
    if (!$err) {
        $err = verifierNouveauContrat((int) $ct['ID_Client'], $date, $cp);
    }
    if ($err) {
        return ['erreur', "Déménagement impossible : $err"];
    }
    if ($err = bloquerSiImpayes($ct, 'déménagement')) {
        return ['erreur', "Déménagement impossible : $err"];
    }

    $cnx = Connexion();
    try {
        mysqli_begin_transaction($cnx);
        cloturerContrat($cnx, $ct['ID_Contrat'], $date, $cl['indexFin'], 'Demenagement');
        definirTitulaireCompteur($cnx, $ct['ID_Compteur'], null);
        if ($cp['neuf']) {
            creerCompteurNeuf($cnx, $cp['idCompteur'], $cp['type'], $cp['indexInitial'], $date);
        }
        $id = insererContrat($cnx, (int) $ct['ID_Client'], $cp['idCompteur'], $cp['idQuartier'], $cp['adresse'],
                             $date, $cp['indexDebut'], $idAdmin);
        definirTitulaireCompteur($cnx, $cp['idCompteur'], (int) $ct['ID_Client']);
        mysqli_commit($cnx);
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($cnx);
        return ['erreur', "Déménagement impossible : erreur base de données (" . $e->getMessage() . ")."];
    } finally {
        mysqli_close($cnx);
    }
    return ['succes', "Déménagement enregistré : contrat n° {$ct['ID_Contrat']} clôturé (compteur n° {$ct['ID_Compteur']} libéré), "
                    . "nouveau contrat n° $id sur le compteur n° {$cp['idCompteur']}."
                    . enregistrerReleveCloture($ct, $cl) . $cl['avertissement']];
}

function actionChangerZone($p)
{
    $idCompteur = (int) ($p['id_compteur'] ?? 0);
    $quartier   = getQuartierParId((int) ($p['quartier'] ?? 0));
    if (!$idCompteur || !getContratsDuCompteur($idCompteur)) {
        return ['erreur', "Zone non modifiée : compteur introuvable."];
    }
    if (!$quartier) {
        return ['erreur', "Zone non modifiée : choisissez une zone."];
    }
    changerZoneCompteur($idCompteur, $quartier['ID_Quartier']);
    return ['succes', "Compteur n° $idCompteur placé dans la zone {$quartier['Nom']} ({$quartier['Ville']}, "
                    . "{$quartier['Type_Activite']}) pour tous ses contrats."];
}

function traiterActionContrat()
{
    $idAdmin = (int) ($_SESSION['user']['id'] ?? 0);
    $p       = $_POST;
    switch ($p['action'] ?? '') {
        case 'creer':      $r = actionCreerContrat($p, $idAdmin);     break;
        case 'resilier':   $r = actionResilierContrat($p);            break;
        case 'transferer': $r = actionTransfererContrat($p, $idAdmin); break;
        case 'demenager':  $r = actionDemenagerClient($p, $idAdmin);  break;
        case 'zone':       $r = actionChangerZone($p);                break;
        default:           $r = ['erreur', 'Action inconnue.'];
    }
    $_SESSION['contrat_msg'] = ['type' => $r[0], 'texte' => $r[1]];

    $retour = [];
    parse_str((string) ($p['retour'] ?? ''), $retour);
    $retour = array_intersect_key($retour, array_flip(['client', 'quartier', 'activite', 'statut', 'type', 'q', 'p']));
    header('Location: index.php?' . http_build_query(['page' => 'contrats'] + $retour));
    exit;
}

function preparerPageContrats()
{
    $f = [
        'client'   => max(0, (int) ($_GET['client'] ?? 0)),
        'quartier' => max(0, (int) ($_GET['quartier'] ?? 0)),
        'activite' => max(0, (int) ($_GET['activite'] ?? 0)),
        'statut'   => in_array($_GET['statut'] ?? '', ['Actif', 'Resilie'], true) ? $_GET['statut'] : '',
        'type'     => in_array($_GET['type'] ?? '', ['Eau', 'Electricite'], true) ? $_GET['type'] : '',
        'q'        => trim((string) ($_GET['q'] ?? '')),
    ];
    $total = compterContrats($f);
    $pages = max(1, (int) ceil($total / CONTRATS_PAR_PAGE));
    $page  = min($pages, max(1, (int) ($_GET['p'] ?? 1)));

    $lignes = [];
    foreach (getContrats($f, CONTRATS_PAR_PAGE, ($page - 1) * CONTRATS_PAR_PAGE) as $c) {
        $c['unite']        = $c['Type_Compteur'] === 'Eau' ? 'm³' : 'kWh';
        $c['client']       = trim($c['Prenom'] . ' ' . $c['Nom']);
        $c['zone']         = $c['Quartier'] . ' — ' . $c['Ville'];
        $c['actif']        = $c['Statut'] === 'Actif';
        $c['motif']        = MOTIFS_FIN[$c['Motif_Fin']] ?? '';
        $lignes[] = $c;
    }

    $clientFiltre = $f['client'] ? getClientPourContrat($f['client']) : null;

    $msg = $_SESSION['contrat_msg'] ?? null;
    unset($_SESSION['contrat_msg']);

    $filtresUrl = array_filter($f + ['p' => $page]);
    return [
        'lignes'       => $lignes,
        'total'        => $total,
        'page'         => $page,
        'pages'        => $pages,
        'filtres'      => $f,
        'retour'       => http_build_query($filtresUrl),
        'stats'        => statistiquesContrats(),
        'quartiers'    => getQuartiers(),
        'activites'    => getTypesActivite(),
        'libres'       => getCompteursLibresPourContrat(),
        'clientFiltre' => $clientFiltre,
        'ouvrirCreation' => isset($_GET['nouveau']),
        'aujourdhui'   => aujourdhuiContrat(),
        'msg'          => $msg,
    ];
}

function clientPourJs($c)
{
    return [
        'id'        => (int) $c['ID_Client'],
        'nom'       => trim($c['Prenom'] . ' ' . $c['Nom']),
        'cin'       => (string) ($c['CIN'] ?? ''),
        'telephone' => (string) ($c['Telephone'] ?? ''),
        'email'     => (string) ($c['Email'] ?? ''),
        'adresse'   => (string) ($c['Adresse'] ?? ''),
        'actifs'    => (int) ($c['Nb_Actifs'] ?? 0),
    ];
}

function envoyerSuggestionsClients()
{
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
    $liste = mb_strlen($q) >= 2 || ctype_digit($q) ? rechercherClientsPourContrat($q, 10) : [];
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array_map('clientPourJs', $liste), JSON_UNESCAPED_UNICODE);
    exit;
}