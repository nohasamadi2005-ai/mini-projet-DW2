<?php
require_once __DIR__ . '/Consommation.php';


// Années disponibles dans les fichiers déposés par les agents
function vaAnneesDisponibles()
{
    return array_map('intval', array_column(
        consoSelect("SELECT DISTINCT Annee FROM consommation_annuelle ORDER BY Annee DESC"), 'Annee'
    ));
}


// Valeurs du fichier de l'agent : [ID_Client => ligne]. Si un fichier a été importé deux fois,
// la ligne la plus récente écrase la précédente.
function vaReleveAgent($annee)
{
    $lignes = consoSelect(
        "SELECT ID_Client, ConsommationEau, ConsommationElect, Fichier_Source
           FROM consommation_annuelle WHERE Annee = ? ORDER BY Date_saisie ASC",
        'i', [$annee]
    );
    $par = [];
    foreach ($lignes as $l) {
        $par[(int) $l['ID_Client']] = $l;
    }
    return $par;
}


// Tous les relevés vérifiés (années <= $annee), triés pour calculer index final - index de départ
function vaIndexVerifies($annee)
{
    return consoSelect(
        "SELECT cm.ID_Client, cm.ID_Compteur, c.Type_Compteur, c.Index_Initial,
                cm.Annee, cm.Mois, cm.Index_Actuel
           FROM consommation_mensuelle cm
           JOIN compteur c ON c.ID_Compteur = cm.ID_Compteur
          WHERE cm.Statut_Traitement = 'Verifie' AND cm.Annee <= ?
          ORDER BY cm.ID_Compteur, cm.Annee, cm.Mois",
        'i', [$annee]
    );
}


// Saisies de l'année encore en anomalie / ressaisie : [ID_Client => nombre]
function vaAnomaliesEnAttente($annee)
{
    $r = consoSelect(
        "SELECT ID_Client, COUNT(*) AS nb FROM consommation_mensuelle
          WHERE Annee = ? AND Statut_Traitement <> 'Verifie' GROUP BY ID_Client",
        'i', [$annee]
    );
    $out = [];
    foreach ($r as $l) {
        $out[(int) $l['ID_Client']] = (int) $l['nb'];
    }
    return $out;
}


// Factures de l'année non payées : [ID_Client][type] = ['nb' => , 'total' => ]
function vaImpayes($annee)
{
    $r = consoSelect(
        "SELECT cm.ID_Client, c.Type_Compteur, COUNT(*) AS nb, SUM(f.Montant_TTC) AS total
           FROM facture f
           JOIN consommation_mensuelle cm ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
           JOIN compteur c ON c.ID_Compteur = cm.ID_Compteur
          WHERE cm.Annee = ? AND f.Statut_Paiement <> 'Payee'
          GROUP BY cm.ID_Client, c.Type_Compteur",
        'i', [$annee]
    );
    $out = [];
    foreach ($r as $l) {
        $type = $l['Type_Compteur'] === 'Eau' ? 'Eau' : 'Electricite';
        $out[(int) $l['ID_Client']][$type] = ['nb' => (int) $l['nb'], 'total' => (float) $l['total']];
    }
    return $out;
}


function vaClients()
{
    $out = [];
    foreach (consoSelect("SELECT ID_Client, Nom, Prenom, Email FROM client") as $c) {
        $out[(int) $c['ID_Client']] = $c;
    }
    return $out;
}


// Date de la dernière notification « vérification annuelle » envoyée à ce client (ou null)
function vaDerniereNotification($idClient, $annee)
{
    $r = consoSelect(
        "SELECT MAX(Date_Envoi) AS d FROM notification WHERE ID_Client = ? AND Message LIKE ?",
        'is', [$idClient, '%vérification annuelle ' . (int) $annee . '%']
    );
    return $r[0]['d'] ?? null;
}