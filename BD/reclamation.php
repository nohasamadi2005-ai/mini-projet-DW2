<?php
require_once __DIR__ . '/Consommation.php';

// Table reclamation : ID_Reclamation, ID_Client, ID_Facture (facultatif), ID_Anomalie, Date_Reclamation,
//                     Type_Reclamation, Description, Statut, Reponse, Date_Reponse,
//                     Sous_Type, Lieu_Fuite, Photo

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
// (ancienne version, conservée)
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

// Nouvelle version : fonctionne aussi pour les réclamations sans facture
// ($idFacture et $sousType peuvent être null)
function reclamationActiveMemeType($idClient, $type, $idFacture = null, $sousType = null)
{
    $sql    = "SELECT ID_Reclamation FROM reclamation
                WHERE ID_Client = ? AND Type_Reclamation = ? AND Statut IN ('Ouverte', 'En cours')";
    $types  = 'is';
    $params = [$idClient, $type];
    if ($idFacture !== null) {
        $sql .= " AND ID_Facture = ?";
        $types .= 'i';
        $params[] = $idFacture;
    }
    if ($sousType !== null) {
        $sql .= " AND Sous_Type = ?";
        $types .= 's';
        $params[] = $sousType;
    }
    $r = consoSelect($sql . " LIMIT 1", $types, $params);
    return !empty($r);
}

// Ancienne version (réclamation liée obligatoirement à une facture), conservée
function insererReclamation($idClient, $idFacture, $type, $description)
{
    return consoExecuter(
        "INSERT INTO reclamation (ID_Client, ID_Facture, Type_Reclamation, Description, Statut)
         VALUES (?, ?, ?, ?, 'Ouverte')",
        'iiss', [$idClient, $idFacture, $type, $description]
    );
}

// Nouvelle version : facture, sous-type, lieu et photo sont facultatifs selon le type
function insererReclamationDetail($idClient, $idFacture, $type, $description, $sousType = null, $lieu = null, $photo = null)
{
    return consoExecuter(
        "INSERT INTO reclamation (ID_Client, ID_Facture, Type_Reclamation, Description, Sous_Type, Lieu_Fuite, Photo, Statut)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'Ouverte')",
        'iisssss', [$idClient, $idFacture, $type, $description, $sousType, $lieu, $photo]
    );
}

function getReclamationsDuClient($idClient)
{
    return consoSelect(
        "SELECT r.ID_Reclamation, r.ID_Facture, r.Date_Reclamation, r.Type_Reclamation,
                r.Description, r.Statut, r.Reponse, r.Date_Reponse,
                r.Sous_Type, r.Lieu_Fuite, r.Photo,
                cm.Mois, cm.Annee, c.Type_Compteur
           FROM reclamation r
           LEFT JOIN facture f ON f.ID_Facture = r.ID_Facture
           LEFT JOIN consommation_mensuelle cm ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
           LEFT JOIN compteur c ON c.ID_Compteur = cm.ID_Compteur
          WHERE r.ID_Client = ?
          ORDER BY r.Date_Reclamation DESC, r.ID_Reclamation DESC",
        'i', [$idClient]
    );
}