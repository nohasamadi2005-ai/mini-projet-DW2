<?php
require_once __DIR__ . '/Consommation.php';


// Années disponibles dans les fichiers déposés par les agents
function vaAnneesDisponibles()
{
    return array_map('intval', array_column(
        consoSelect("SELECT DISTINCT Annee FROM consommation_annuelle WHERE Statut_Import = 'Integre' ORDER BY Annee DESC"), 'Annee'
    ));
}


// Valeurs du fichier de l'agent (lignes intégrées seulement) : [ID_Client => ligne]. Le dépôt de l'agent est
// par CLIENT (eau + électricité). Si un fichier a été importé deux fois, la ligne la plus récente écrase la précédente.
function vaReleveAgent($annee)
{
    $lignes = consoSelect(
        "SELECT ID_Client, ConsommationEau, ConsommationElect, Fichier_Source
           FROM consommation_annuelle
          WHERE Annee = ? AND Statut_Import = 'Integre' AND ID_Client IS NOT NULL   -- les lignes rejetées (Personne 4) ne comptent pas
          ORDER BY Date_saisie ASC, ID_Consommation_Annuelle ASC",
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


// Contrats qui chevauchent l'année (actifs ou résiliés en cours d'année), avec quartier et type d'activité.
// [ID_Contrat => ligne]
function vaContrats($annee)
{
    $r = consoSelect(
        "SELECT ct.ID_Contrat, ct.ID_Client, ct.ID_Compteur, ct.Date_Debut, ct.Date_Fin, ct.Statut,
                q.ID_Quartier, q.Nom AS Quartier, q.Ville, ta.Libelle AS Type_Activite, cp.Type_Compteur
           FROM contrat ct
           JOIN quartier q       ON q.ID_Quartier = ct.ID_Quartier
           JOIN type_activite ta ON ta.ID_Type_Activite = q.ID_Type_Activite
           JOIN compteur cp      ON cp.ID_Compteur = ct.ID_Compteur
          WHERE ct.Date_Debut <= ? AND (ct.Date_Fin IS NULL OR ct.Date_Fin >= ?)
          ORDER BY ct.ID_Contrat",
        'ss', [(int) $annee . '-12-31', (int) $annee . '-01-01']
    );
    $out = [];
    foreach ($r as $l) {
        $out[(int) $l['ID_Contrat']] = $l;
    }
    return $out;
}


// Relevés de l'année encore en anomalie / ressaisie (le contrat est retrouvé côté PHP : compteur + mois)
function vaReleveEnAnomalie($annee)
{
    return consoSelect(
        "SELECT ID_Compteur, Mois FROM consommation_mensuelle
          WHERE Annee = ? AND Statut_Traitement <> 'Verifie'",
        'i', [$annee]
    );
}


// Factures de l'année non payées (le contrat est retrouvé côté PHP : compteur + mois)
function vaFacturesImpayees($annee)
{
    return consoSelect(
        "SELECT cm.ID_Compteur, cm.Mois, f.Montant_TTC
           FROM facture f
           JOIN consommation_mensuelle cm ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
          WHERE cm.Annee = ? AND f.Statut_Paiement <> 'Payee'",
        'i', [$annee]
    );
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