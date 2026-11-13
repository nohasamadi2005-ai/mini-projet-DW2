<?php
require_once __DIR__ . '/Consommation.php';


// Années disponibles dans les fichiers déposés par les agents
function vaAnneesDisponibles()
{
    return array_map('intval', array_column(
        consoSelect("SELECT DISTINCT Annee FROM consommation_annuelle WHERE Statut_Import = 'Integre' ORDER BY Annee DESC"), 'Annee'
    ));
}

function vaReleveAgent($annee)
{
    $lignes = consoSelect(
        "SELECT ID_Client, ID_Contrat, ConsommationEau, ConsommationElect, Fichier_Source
           FROM consommation_annuelle
          WHERE Annee = ? AND Statut_Import = 'Integre'
          ORDER BY Date_saisie ASC, ID_Consommation_Annuelle ASC",
        'i', [$annee]
    );

    $parContrat = [];     // dernière ligne de chaque contrat
    $parClient  = [];     // dernière ligne d'un ancien dépôt par client
    foreach ($lignes as $l) {
        if ($l['ID_Contrat'] !== null) {
            $parContrat[(int) $l['ID_Contrat']] = $l;
        } else {
            $parClient[(int) $l['ID_Client']] = $l;
        }
    }

    $par = [];
    foreach ($parClient as $id => $l) {
        $par[$id] = ['ID_Client' => $id, 'ConsommationEau' => $l['ConsommationEau'],
                     'ConsommationElect' => $l['ConsommationElect'], 'Fichier_Source' => $l['Fichier_Source']];
    }
    $remplace = [];       // [client][colonne] : l'ancienne valeur par client est remplacée par les contrats
    foreach ($parContrat as $l) {
        $id = (int) $l['ID_Client'];
        foreach (['ConsommationEau', 'ConsommationElect'] as $col) {
            if ($l[$col] === null) {
                continue;
            }
            if (!isset($par[$id])) {
                $par[$id] = ['ID_Client' => $id, 'ConsommationEau' => null, 'ConsommationElect' => null, 'Fichier_Source' => $l['Fichier_Source']];
            }
            if (empty($remplace[$id][$col])) {
                $par[$id][$col] = 0.0;
                $remplace[$id][$col] = true;
            }
            $par[$id][$col] += (float) $l[$col];
        }
        $par[$id]['Fichier_Source'] = $l['Fichier_Source'];
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
// Contrats en cours pendant l'année
function vaContrats($annee)
{
    return consoSelect(
        "SELECT ct.ID_Contrat, ct.ID_Client, ct.ID_Compteur, ct.ID_Quartier,
                q.Nom AS Quartier, q.Ville, t.Libelle AS Type_Activite,
                ct.Statut, ct.Date_Debut, ct.Date_Fin, cp.Type_Compteur
           FROM contrat ct
           JOIN compteur cp       ON cp.ID_Compteur = ct.ID_Compteur
           JOIN quartier q        ON q.ID_Quartier = ct.ID_Quartier
           JOIN type_activite t   ON t.ID_Type_Activite = q.ID_Type_Activite
          WHERE ct.Date_Debut <= ? AND (ct.Date_Fin IS NULL OR ct.Date_Fin >= ?)",
        'ss', [$annee . '-12-31', $annee . '-01-01']
    );
}

// Saisies de l'année encore en anomalie
function vaReleveEnAnomalie($annee)
{
    return consoSelect(
        "SELECT ID_Client, ID_Compteur, Mois FROM consommation_mensuelle
          WHERE Annee = ? AND Statut_Traitement <> 'Verifie'",
        'i', [$annee]
    );
}

// Factures de l'année non payées
function vaFacturesImpayees($annee)
{
    return consoSelect(
        "SELECT cm.ID_Client, cm.ID_Compteur, cm.Mois, f.Montant_TTC
           FROM facture f
           JOIN consommation_mensuelle cm ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
          WHERE cm.Annee = ? AND f.Statut_Paiement <> 'Payee'",
        'i', [$annee]
    );
}