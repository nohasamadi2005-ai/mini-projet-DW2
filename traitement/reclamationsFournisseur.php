<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/Reclamation.php';            // numeroFacture()
require_once __DIR__ . '/../BD/reclamationFournisseur.php';
const STATUTS_FOURNISSEUR = ['En cours', 'Traitee', 'Rejetee'];

function flashReclamationFournisseur($type, $texte)
{
    $_SESSION['recl_f_msg'] = ['type' => $type, 'texte' => $texte];
    header('Location: index.php?page=reclamations_fournisseur');
    exit;
}

function traiterReclamationFournisseur()
{
    $idAdmin = (int) $_SESSION['user']['id'];
    $id      = filter_input(INPUT_POST, 'id_reclamation', FILTER_VALIDATE_INT);
    $statut  = trim($_POST['statut'] ?? '');
    $reponse = trim($_POST['reponse'] ?? '');

    $rec = $id ? getReclamationParId($id) : null;
    if (!$rec) {
        flashReclamationFournisseur('erreur', 'Réclamation introuvable.');
    }
    if (!in_array($rec['Statut'] ?: 'Ouverte', ['Ouverte', 'En cours'], true)) {
        flashReclamationFournisseur('erreur', 'Cette réclamation est déjà clôturée.');
    }
    if (!in_array($statut, STATUTS_FOURNISSEUR, true)) {
        flashReclamationFournisseur('erreur', 'Statut invalide.');
    }
    if ($statut !== 'En cours' && mb_strlen($reponse) < 5) {
        flashReclamationFournisseur('erreur', 'Une réponse est obligatoire pour clôturer la réclamation.');
    }
    if (mb_strlen($reponse) > 1000) {
        flashReclamationFournisseur('erreur', 'La réponse est trop longue (1000 caractères maximum).');
    }

    repondreReclamation($id, $statut, $reponse !== '' ? $reponse : null);

    $num = 'REC-' . str_pad($id, 4, '0', STR_PAD_LEFT);
    if ($statut === 'En cours') {
        $msg = "Votre réclamation $num est en cours de traitement.";
    } elseif ($statut === 'Traitee') {
        $msg = "Votre réclamation $num a été traitée. Réponse du fournisseur : $reponse";
    } else {
        $msg = "Votre réclamation $num a été rejetée. Motif : $reponse";
    }
    notifierClientReclamation((int) $rec['ID_Client'], $idAdmin, $id, (int) $rec['ID_Facture'], $msg);

    flashReclamationFournisseur('succes', "Réclamation $num mise à jour et client notifié.");
}

function preparerPageReclamationsFournisseur()
{
    $filtre = $_GET['statut'] ?? '';
    if (!in_array($filtre, ['Ouverte', 'En cours', 'Traitee', 'Rejetee'], true)) {
        $filtre = '';
    }

    $libelles = ['Ouverte' => 'Ouverte', 'En cours' => 'En cours', 'Traitee' => 'Traitée', 'Rejetee' => 'Rejetée'];

    $lignes = [];
    foreach (getToutesReclamations($filtre) as $r) {
        $statut = $r['Statut'] ?: 'Ouverte';
        $lignes[] = [
            'id'          => (int) $r['ID_Reclamation'],
            'numero'      => 'REC-' . str_pad($r['ID_Reclamation'], 4, '0', STR_PAD_LEFT),
            'date'        => date('d/m/Y', strtotime($r['Date_Reclamation'])),
            'client'      => trim($r['Prenom'] . ' ' . $r['Nom']),
            'email'       => $r['Email'],
            'tel'         => $r['Telephone'],
            'facture'     => numeroFacture($r['ID_Facture'], $r['Annee'], $r['Type_Compteur']),
            'periode'     => nomMois($r['Mois']) . ' ' . $r['Annee'],
            'compteur'    => $r['Type_Compteur'],
            'ttc'         => number_format((float) $r['Montant_TTC'], 2, ',', ' '),
            'type'        => $r['Type_Reclamation'],
            'description' => $r['Description'],
            'statutCode'  => $statut,
            'statut'      => $libelles[$statut] ?? $statut,
            'reponse'     => $r['Reponse'],
            'dateReponse' => $r['Date_Reponse'] ? date('d/m/Y H:i', strtotime($r['Date_Reponse'])) : null,
            'cloturee'    => in_array($statut, ['Traitee', 'Rejetee'], true),
        ];
    }

    $msg = $_SESSION['recl_f_msg'] ?? null;
    unset($_SESSION['recl_f_msg']);

    return [
        'lignes'   => $lignes,
        'compteurs'=> compterReclamationsParStatut(),
        'filtre'   => $filtre,
        'message'  => $msg,
    ];
}