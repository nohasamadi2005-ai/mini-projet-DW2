<?php
require_once __DIR__ . '/Consommation.php';

// La saisie + le type de compteur, pour calculer la facture
function getSaisiePourFacture($idConso)
{
    $r = consoSelect(
        "SELECT cm.ID_Consommation_Mensuelle, cm.Mois, cm.Annee, cm.Consommation,
                cm.Statut_Traitement, c.Type_Compteur
           FROM consommation_mensuelle cm
           JOIN compteur c ON c.ID_Compteur = cm.ID_Compteur
          WHERE cm.ID_Consommation_Mensuelle = ?",
        'i', [$idConso]
    );
    return $r[0] ?? null;
}

function insererFacture($idConso, $dateFacture, $debut, $fin, $ht, $tva, $ttc)
{
    return consoExecuter(
        "INSERT INTO facture
           (ID_Consommation_Mensuelle, Date_Facture, Periode_Debut, Periode_Fin,
            Montant_HT, TVA, Montant_TTC, Statut_Paiement)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'Impayee')",
        'isssddd', [$idConso, $dateFacture, $debut, $fin, $ht, $tva, $ttc]
    );
}
// Toutes les factures d'un client (le filtre est sur le propriétaire de la saisie)
function getFacturesDuClient($idClient)
{
    return consoSelect(
        "SELECT f.ID_Facture, f.Date_Facture, f.Periode_Debut, f.Periode_Fin,
                f.Montant_HT, f.TVA, f.Montant_TTC, f.Statut_Paiement,
                cm.Mois, cm.Annee, cm.Consommation, c.Type_Compteur
           FROM facture f
           JOIN consommation_mensuelle cm
             ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
           JOIN compteur c ON c.ID_Compteur = cm.ID_Compteur
          WHERE cm.ID_Client = ?
          ORDER BY f.Date_Facture DESC, f.ID_Facture DESC",
        'i', [$idClient]
    );
}

function getFactureDuClient($idFacture, $idClient)
{
    $r = consoSelect(
        "SELECT f.ID_Facture,
                f.Date_Facture,
                f.Periode_Debut,
                f.Periode_Fin,
                f.Montant_HT,
                f.TVA,
                f.Montant_TTC,
                f.Statut_Paiement,
                cm.Mois,
                cm.Annee,
                cm.Consommation,
                c.Type_Compteur
           FROM facture f
           JOIN consommation_mensuelle cm
             ON cm.ID_Consommation_Mensuelle =
                f.ID_Consommation_Mensuelle
           JOIN compteur c
             ON c.ID_Compteur = cm.ID_Compteur
          WHERE f.ID_Facture = ?
            AND cm.ID_Client = ?
          LIMIT 1",
        'ii',
        [$idFacture, $idClient]
    );

    return $r[0] ?? null;
}
