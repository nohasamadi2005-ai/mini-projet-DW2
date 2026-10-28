<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/../BD/Facture.php';
require_once __DIR__ . '/../BD/Reclamation.php';

const TYPES_RECLAMATION = ['Fuite externe', 'Fuite interne', 'Facture', 'Autre'];

function numeroFacture($idFacture, $annee, $typeCompteur)
{
    return 'FAC-' . $annee . '-' . ($typeCompteur === 'Eau' ? 'EAU' : 'ELEC')
         . '-' . str_pad((int) $idFacture, 4, '0', STR_PAD_LEFT);
}

function messageReclamation($type, $texte)
{
    $_SESSION['reclamation_msg'] = ['type' => $type, 'texte' => $texte];
    header('Location: index.php?page=reclamations');
    exit;
}

function traiterReclamation()
{
    $idClient  = (int) $_SESSION['user']['id'];
    $idFacture = filter_input(INPUT_POST, 'id_facture', FILTER_VALIDATE_INT);
    $type      = trim($_POST['type'] ?? '');
    $precision = trim($_POST['precision'] ?? '');
    $desc      = trim($_POST['description'] ?? '');

    if (!$idFacture || !clientPossedeFacture($idFacture, $idClient)) {
        messageReclamation('erreur', 'Veuillez choisir une de vos factures.');
    }
    if (!in_array($type, TYPES_RECLAMATION, true)) {
        messageReclamation('erreur', 'Veuillez choisir un type de réclamation valide.');
    }
    if ($type === 'Autre') {
        if ($precision === '') {
            messageReclamation('erreur', 'Veuillez préciser le type de votre réclamation.');
        }
        $type = 'Autre : ' . mb_substr($precision, 0, 80);
    }
    if (mb_strlen($desc) < 10) {
        messageReclamation('erreur', 'Veuillez décrire votre réclamation (10 caractères minimum).');
    }
    if (mb_strlen($desc) > 1000) {
        messageReclamation('erreur', 'La description est trop longue (1000 caractères maximum).');
    }
    if (reclamationActiveExiste($idClient, $idFacture, $type)) {
        messageReclamation('erreur', 'Une réclamation de ce type est déjà en cours de traitement pour cette facture.');
    }

    if (insererReclamation($idClient, $idFacture, $type, $desc)) {
        messageReclamation('succes', 'Votre réclamation a été envoyée. Le fournisseur vous répondra prochainement.');
    }
    messageReclamation('erreur', "Une erreur est survenue, veuillez réessayer.");
}

function preparerPageReclamations()
{
    $idClient = (int) $_SESSION['user']['id'];

    $factures = [];
    foreach (getFacturesDuClient($idClient) as $f) {
        $eau = $f['Type_Compteur'] === 'Eau';
        $factures[] = [
            'id'    => (int) $f['ID_Facture'],
            'label' => numeroFacture($f['ID_Facture'], $f['Annee'], $f['Type_Compteur'])
                     . ' — ' . ($eau ? 'Eau' : 'Électricité') . ' — '
                     . nomMois($f['Mois']) . ' ' . $f['Annee']
                     . ' — ' . number_format((float) $f['Montant_TTC'], 2, ',', ' ') . ' DH',
        ];
    }

    $classes = ['Ouverte' => 'ouverte', 'En cours' => 'encours', 'Traitee' => 'traitee', 'Rejetee' => 'rejetee'];
    $libelles = ['Ouverte' => 'Ouverte', 'En cours' => 'En cours', 'Traitee' => 'Traitée', 'Rejetee' => 'Rejetée'];

    $reclamations = [];
    foreach (getReclamationsDuClient($idClient) as $r) {
        $statut = $r['Statut'] ?: 'Ouverte';
        $reclamations[] = [
            'numero'      => 'REC-' . str_pad($r['ID_Reclamation'], 4, '0', STR_PAD_LEFT),
            'date'        => date('d/m/Y', strtotime($r['Date_Reclamation'])),
            'facture'     => numeroFacture($r['ID_Facture'], $r['Annee'], $r['Type_Compteur']),
            'type'        => $r['Type_Reclamation'],
            'description' => $r['Description'],
            'statut'      => $libelles[$statut] ?? $statut,
            'classe'      => $classes[$statut] ?? 'ouverte',
            'reponse'     => $r['Reponse'],
            'dateReponse' => $r['Date_Reponse'] ? date('d/m/Y H:i', strtotime($r['Date_Reponse'])) : null,
        ];
    }

    $msg = $_SESSION['reclamation_msg'] ?? null;
    unset($_SESSION['reclamation_msg']);

    return [
        'factures'     => $factures,
        'reclamations' => $reclamations,
        'message'      => $msg,
        'preselection' => (int) ($_GET['facture'] ?? 0),
        'types'        => TYPES_RECLAMATION,
    ];
}