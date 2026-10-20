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
        "SELECT f.ID_Facture, f.Date_Facture, f.Periode_Debut, f.Periode_Fin,
                f.Montant_HT, f.TVA, f.Montant_TTC, f.Statut_Paiement, f.Date_paiement,
                cm.ID_Compteur, cm.Mois, cm.Annee,
                cm.Index_Precedent, cm.Index_Actuel, cm.Consommation,
                c.Type_Compteur,
                cl.Nom, cl.Prenom, cl.Adresse, cl.Email, cl.Telephone,
                cm.Ancien_photo, cm.Photo_Actuelle
           FROM facture f
           JOIN consommation_mensuelle cm
             ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
           JOIN compteur c ON c.ID_Compteur = cm.ID_Compteur
           JOIN client cl ON cl.ID_Client = cm.ID_Client
          WHERE f.ID_Facture = ? AND cm.ID_Client = ?
          LIMIT 1",
        'ii', [$idFacture, $idClient]
    );
    return $r[0] ?? null; // null si la facture n'appartient pas au client
}
/**
 * Récupère l'historique complet des factures payées pour l'espace Fournisseur
 */
/**
 * Récupère l'historique complet des factures payées pour l'espace Fournisseur
 */
function obtenirHistoriquePaiementsFournisseur()
{
    $cnx = Connexion();
    
    $sql = "SELECT f.ID_Facture,
                   f.Date_Facture,
                   f.Date_paiement,
                   f.Montant_HT,
                   f.TVA,
                   f.Montant_TTC,
                   f.Statut_Paiement,
                   cm.Mois,
                   cm.Annee,
                   c.Type_Compteur,
                   cl.Nom,
                   cl.Prenom,
                   cl.Email,
                   cl.Telephone
              FROM facture f
              JOIN consommation_mensuelle cm 
                ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
              JOIN client cl 
                ON cl.ID_Client = cm.ID_Client
              JOIN compteur c 
                ON c.ID_Compteur = cm.ID_Compteur
             WHERE f.Statut_Paiement LIKE 'Paye%'
             ORDER BY f.Date_Facture DESC, f.ID_Facture DESC";

    $resultat = mysqli_query($cnx, $sql);
    $historique = [];

    if ($resultat) {
        while ($ligne = mysqli_fetch_assoc($resultat)) {
            $historique[] = $ligne;
        }
    }

    mysqli_close($cnx);
    return $historique;
}

function payerFactureClient($idFacture, $idClient)
{
    return consoExecuter(
        "UPDATE facture f
           JOIN consommation_mensuelle cm
             ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
            SET f.Statut_Paiement = 'Payee', f.Date_paiement = CURDATE()
          WHERE f.ID_Facture = ? AND cm.ID_Client = ? AND f.Statut_Paiement <> 'Payee'",
        'ii', [$idFacture, $idClient]
    );
}
?>