<?php
require_once __DIR__ . '/Consommation.php';

// Historique des affectations de compteurs (filtrable par client et/ou par compteur)
function getHistoriqueCompteurs($idClient = 0, $idCompteur = 0)
{
    $sql = "SELECT h.ID_Historique, h.ID_Compteur, h.ID_Client, h.Date_Debut, h.Date_Fin,
                   h.Index_Debut, h.Index_Fin,
                   c.Type_Compteur, c.ID_Client AS client_actuel,
                   cl.Nom, cl.Prenom,
                   (SELECT COUNT(*) FROM consommation_mensuelle cm
                     WHERE cm.ID_Compteur = h.ID_Compteur AND cm.ID_Client = h.ID_Client) AS nb_saisies,
                   (SELECT COALESCE(SUM(cm.Consommation), 0) FROM consommation_mensuelle cm
                     WHERE cm.ID_Compteur = h.ID_Compteur AND cm.ID_Client = h.ID_Client) AS conso_totale
              FROM historique_compteur h
              JOIN compteur c ON c.ID_Compteur = h.ID_Compteur
              LEFT JOIN client cl ON cl.ID_Client = h.ID_Client
             WHERE 1 = 1";
    $types  = '';
    $params = [];
    if ($idClient > 0) {
        $sql .= " AND h.ID_Client = ?";
        $types .= 'i';
        $params[] = $idClient;
    }
    if ($idCompteur > 0) {
        $sql .= " AND h.ID_Compteur = ?";
        $types .= 'i';
        $params[] = $idCompteur;
    }
    $sql .= " ORDER BY h.ID_Compteur, h.Date_Debut DESC, h.ID_Historique DESC";
    return consoSelect($sql, $types, $params);
}

function getClientPourHistorique($idClient)
{
    $r = consoSelect("SELECT ID_Client, Nom, Prenom FROM client WHERE ID_Client = ?", 'i', [$idClient]);
    return $r[0] ?? null;
}