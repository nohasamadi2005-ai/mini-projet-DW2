<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/Reclamation.php';            // numeroFacture()
require_once __DIR__ . '/../BD/reclamationFournisseur.php';

function flashReclamationFournisseur($type, $texte)
{
    $_SESSION['recl_f_msg'] = ['type' => $type, 'texte' => $texte];
    header('Location: index.php?page=reclamations_fournisseur');
    exit;
}

// Catégorie de la réclamation : Fuite externe, Fuite interne, Facture ou Autre
function categorieReclamation($type)
{
    if (in_array($type, ['Fuite externe', 'Fuite interne', 'Facture'], true)) {
        return $type;
    }
    return 'Autre';
}

// Décisions possibles selon le type + notification envoyée au client
// 'statut' = statut de la réclamation après la décision
// 'note'   = true si un message complémentaire est obligatoire
function actionsReclamation()
{
    $encours = [
        'libelle' => 'En cours de traitement',
        'statut'  => 'En cours',
        'msg'     => 'Votre réclamation est en cours de traitement par nos équipes.',
    ];

    return [
        'Fuite externe' => [
            'encours'    => $encours,
            'technicien' => [
                'libelle' => 'Technicien envoyé',
                'statut'  => 'En cours',
                'msg'     => "Un technicien a été envoyé pour réparer la fuite externe que vous avez signalée.",
            ],
            'resolu'     => [
                'libelle' => 'Problème résolu',
                'statut'  => 'Traitee',
                'msg'     => "La fuite externe que vous avez signalée a été réparée. Le problème est résolu.",
            ],
        ],
        'Fuite interne' => [
            'encours'    => $encours,
            'technicien' => [
                'libelle' => 'Technicien envoyé (vérification)',
                'statut'  => 'En cours',
                'msg'     => "Un technicien passera vérifier votre installation suite à la fuite interne que vous avez signalée.",
            ],
            'conseil'    => [
                'libelle' => 'Fuite côté client (conseil plombier)',
                'statut'  => 'Traitee',
                'msg'     => "Après vérification, la fuite se situe dans votre installation intérieure. Nous vous conseillons de faire appel à un plombier.",
            ],
        ],
        'Facture' => [
            'encours'  => $encours,
            'corrigee' => [
                'libelle' => 'Facture corrigée',
                'statut'  => 'Traitee',
                'msg'     => "Après vérification, votre facture a été corrigée. Vous pouvez la consulter dans « Mes factures ».",
            ],
            'conforme' => [
                'libelle' => 'Facture conforme (aucune erreur)',
                'statut'  => 'Rejetee',
                'msg'     => "Après vérification, votre facture est conforme : aucune erreur n'a été constatée.",
            ],
        ],
        'Autre' => [
            'encours' => $encours,
            'traitee' => [
                'libelle' => 'Traitée',
                'statut'  => 'Traitee',
                'msg'     => 'Votre réclamation a été traitée.',
                'note'    => true,
            ],
            'rejetee' => [
                'libelle' => 'Rejetée',
                'statut'  => 'Rejetee',
                'msg'     => 'Votre réclamation a été rejetée.',
                'note'    => true,
            ],
        ],
    ];
}

function traiterReclamationFournisseur()
{
    $idAdmin = (int) $_SESSION['user']['id'];
    $id      = filter_input(INPUT_POST, 'id_reclamation', FILTER_VALIDATE_INT);
    $code    = trim($_POST['action'] ?? '');
    $note    = trim($_POST['reponse'] ?? '');

    $rec = $id ? getReclamationParId($id) : null;
    if (!$rec) {
        flashReclamationFournisseur('erreur', 'Réclamation introuvable.');
    }
    if (!in_array($rec['Statut'] ?: 'Ouverte', ['Ouverte', 'En cours'], true)) {
        flashReclamationFournisseur('erreur', 'Cette réclamation est déjà clôturée.');
    }

    $actions = actionsReclamation()[categorieReclamation($rec['Type_Reclamation'])];
    if (!isset($actions[$code])) {
        flashReclamationFournisseur('erreur', 'Décision invalide pour ce type de réclamation.');
    }
    $a = $actions[$code];

    if (!empty($a['note']) && mb_strlen($note) < 5) {
        flashReclamationFournisseur('erreur', 'Un message est obligatoire pour ce type de réclamation.');
    }
    if (mb_strlen($note) > 500) {
        flashReclamationFournisseur('erreur', 'Le message complémentaire est trop long (500 caractères maximum).');
    }

    $num   = 'REC-' . str_pad($id, 4, '0', STR_PAD_LEFT);
    $texte = $a['msg'] . ($note !== '' ? ' Précision : ' . $note : '');

    repondreReclamation($id, $a['statut'], $texte);
    notifierClientReclamation((int) $rec['ID_Client'], $idAdmin, $id, (int) $rec['ID_Facture'],
                              "Réclamation $num — " . $texte);

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

    // Décisions par catégorie, pour remplir la liste déroulante du formulaire
    $actionsJs = [];
    foreach (actionsReclamation() as $cat => $liste) {
        foreach ($liste as $code => $a) {
            $actionsJs[$cat][] = ['code' => $code, 'libelle' => $a['libelle'], 'note' => !empty($a['note'])];
        }
    }

    $msg = $_SESSION['recl_f_msg'] ?? null;
    unset($_SESSION['recl_f_msg']);

    return [
        'lignes'    => $lignes,
        'compteurs' => compterReclamationsParStatut(),
        'filtre'    => $filtre,
        'message'   => $msg,
        'actionsJs' => $actionsJs,
    ];
}