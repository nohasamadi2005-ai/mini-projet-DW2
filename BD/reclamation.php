<?php
require_once __DIR__ . '/Consommation.php';

// Table reclamation : ID_Reclamation, ID_Client, ID_Facture, ID_Anomalie, Date_Reclamation,
//                     Type_Reclamation, Description, Statut, Reponse, Date_Reponse

function clientPossedeFacture($idFacture, $idClient)
{
    $r = consoSelect(
        "SELECT f.ID_Facture
           FROM facture f
           JOIN consommation_mensuelle cm ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
          WHERE f.ID_Facture = ? AND cm.ID_Client = ?",
        'ii', [$idFacture, $idClient]
    );
    return !empty($r);
}

// Réclamation encore en cours de traitement pour la même facture et le même type
function reclamationActiveExiste($idClient, $idFacture, $type)
{
    $r = consoSelect(
        "SELECT ID_Reclamation FROM reclamation
          WHERE ID_Client = ? AND ID_Facture = ? AND Type_Reclamation = ?
            AND Statut IN ('Ouverte', 'En cours')
          LIMIT 1",
        'iis', [$idClient, $idFacture, $type]
    );
    return !empty($r);
}

function insererReclamation($idClient, $idFacture, $type, $description)
{
    return consoExecuter(
        "INSERT INTO reclamation (ID_Client, ID_Facture, Type_Reclamation, Description, Statut)
         VALUES (?, ?, ?, ?, 'Ouverte')",
        'iiss', [$idClient, $idFacture, $type, $description]
    );
}

function getReclamationsDuClient($idClient)
{
    return consoSelect(
        "SELECT r.ID_Reclamation, r.ID_Facture, r.Date_Reclamation, r.Type_Reclamation,
                r.Description, r.Statut, r.Reponse, r.Date_Reponse,
                cm.Mois, cm.Annee, c.Type_Compteur
           FROM reclamation r
           JOIN facture f ON f.ID_Facture = r.ID_Facture
           JOIN consommation_mensuelle cm ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
           JOIN compteur c ON c.ID_Compteur = cm.ID_Compteur
          WHERE r.ID_Client = ?
          ORDER BY r.Date_Reclamation DESC, r.ID_Reclamation DESC",
        'i', [$idClient]
    );
}