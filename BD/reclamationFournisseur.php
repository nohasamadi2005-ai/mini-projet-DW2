<?php
require_once __DIR__ . '/Consommation.php';

// Liste des réclamations (toutes, ou filtrées par statut)
function getToutesReclamations($statut = '')
{
    $sql = "SELECT r.ID_Reclamation, r.ID_Client, r.ID_Facture, r.Date_Reclamation, r.Type_Reclamation,
                   r.Description, r.Statut, r.Reponse, r.Date_Reponse,
                   r.Sous_Type, r.Lieu_Fuite, r.Photo,
                   cl.Nom, cl.Prenom, cl.Email, cl.Telephone,
                   cm.Mois, cm.Annee, cm.Consommation, c.Type_Compteur,
                   f.Montant_TTC
              FROM reclamation r
              JOIN client cl ON cl.ID_Client = r.ID_Client
              LEFT JOIN facture f ON f.ID_Facture = r.ID_Facture
              LEFT JOIN consommation_mensuelle cm ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
              LEFT JOIN compteur c ON c.ID_Compteur = cm.ID_Compteur";
    if ($statut !== '') {
        return consoSelect($sql . " WHERE r.Statut = ? ORDER BY r.Date_Reclamation DESC, r.ID_Reclamation DESC",
                           's', [$statut]);
    }
    return consoSelect($sql . " ORDER BY r.Date_Reclamation DESC, r.ID_Reclamation DESC");
}

function getReclamationParId($id)
{
    $r = consoSelect(
        "SELECT ID_Reclamation, ID_Client, ID_Facture, Type_Reclamation, Sous_Type, Statut
           FROM reclamation WHERE ID_Reclamation = ?",
        'i', [$id]
    );
    return $r[0] ?? null;
}

// Nombre de réclamations par statut : ['Ouverte' => n, 'En cours' => n, ...]
function compterReclamationsParStatut()
{
    $out = ['Ouverte' => 0, 'En cours' => 0, 'Traitee' => 0, 'Rejetee' => 0];
    foreach (consoSelect("SELECT Statut, COUNT(*) AS n FROM reclamation GROUP BY Statut") as $l) {
        $out[$l['Statut'] ?: 'Ouverte'] = (int) $l['n'];
    }
    return $out;
}

function repondreReclamation($id, $statut, $reponse)
{
    return consoExecuter(
        "UPDATE reclamation
            SET Statut = ?, Reponse = ?, Date_Reponse = NOW()
          WHERE ID_Reclamation = ?",
        'ssi', [$statut, $reponse, $id]
    );
}

function notifierClientReclamation($idClient, $idAdmin, $idReclamation, $idFacture, $message)
{
    return consoExecuter(
        "INSERT INTO notification (ID_Client, ID_Admin, ID_Reclamation, ID_Facture, Message)
         VALUES (?, ?, ?, ?, ?)",
        'iiiis', [$idClient, $idAdmin, $idReclamation, $idFacture, $message]
    );
}