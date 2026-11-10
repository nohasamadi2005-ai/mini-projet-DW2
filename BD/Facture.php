<?php
require_once __DIR__ . '/Consommation.php';
require_once __DIR__ . '/Statistiques.php';

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

/**
 * Factures d'un client, avec le contrat / quartier / type d'activité de chaque relevé.
 * [P3] $idContrat > 0 : filtre sur un seul contrat.
 * La jointure relevé -> contrat vient de statFromReleves() (Personne 5) ; LEFT JOIN pour ne pas
 * perdre les anciennes factures dont le mois n'est couvert par aucun contrat.
 */
function getFacturesDuClient($idClient, $idContrat = 0)
{
    $types  = 'i';
    $params = [(int) $idClient];
    $filtre = '';
    if ((int) $idContrat > 0) {
        $filtre   = ' AND ct.ID_Contrat = ?';
        $types   .= 'i';
        $params[] = (int) $idContrat;
    }
    return consoSelect(
        "SELECT f.ID_Facture, f.Date_Facture, f.Periode_Debut, f.Periode_Fin,
                f.Montant_HT, f.TVA, f.Montant_TTC, f.Statut_Paiement,
                cm.Mois, cm.Annee, cm.Consommation, cp.Type_Compteur,
                ct.ID_Contrat, q.Nom AS Quartier, q.Ville, ta.Libelle AS Type_Activite
           " . statFromReleves('LEFT JOIN') . "
           JOIN facture f ON f.ID_Consommation_Mensuelle = cm.ID_Consommation_Mensuelle
          WHERE cm.ID_Client = ?" . $filtre . "
          ORDER BY f.Date_Facture DESC, f.ID_Facture DESC",
        $types, $params
    );
}

function getFactureDuClient($idFacture, $idClient)
{
    $r = consoSelect(
        "SELECT f.ID_Facture, f.Date_Facture, f.Periode_Debut, f.Periode_Fin,
                f.Montant_HT, f.TVA, f.Montant_TTC, f.Statut_Paiement, f.Date_paiement,
                cm.ID_Compteur, cm.Mois, cm.Annee,
                cm.Index_Precedent, cm.Index_Actuel, cm.Consommation,
                cp.Type_Compteur,
                cl.Nom, cl.Prenom, cl.Adresse, cl.Email, cl.Telephone,
                cm.Ancien_photo, cm.Photo_Actuelle,
                ct.ID_Contrat, ct.Adresse_Branchement, q.Nom AS Quartier, q.Ville,
                ta.Libelle AS Type_Activite
           " . statFromReleves('LEFT JOIN') . "
           JOIN facture f ON f.ID_Consommation_Mensuelle = cm.ID_Consommation_Mensuelle
           JOIN client cl ON cl.ID_Client = cm.ID_Client
          WHERE f.ID_Facture = ? AND cm.ID_Client = ?
          LIMIT 1",
        'ii', [$idFacture, $idClient]
    );
    return $r[0] ?? null;
}

function obtenirHistoriquePaiementsFournisseur($f = [])
{
    $where  = ["f.Statut_Paiement LIKE 'Paye%'"];
    $types  = '';
    $params = [];
 
    if (!empty($f['quartier'])) { $where[] = 'q.ID_Quartier = ?';       $types .= 'i'; $params[] = (int) $f['quartier']; }
    if (!empty($f['type']))     { $where[] = 'ta.ID_Type_Activite = ?'; $types .= 'i'; $params[] = (int) $f['type']; }
    if (!empty($f['ville']))    { $where[] = 'q.Ville = ?';             $types .= 's'; $params[] = (string) $f['ville']; }
 
    return consoSelect(
        "SELECT f.ID_Facture, f.Date_Facture, f.Date_paiement,
                f.Montant_HT, f.TVA, f.Montant_TTC, f.Statut_Paiement,
                cm.Mois, cm.Annee, cp.Type_Compteur,
                ct.ID_Contrat, q.Nom AS Quartier, q.Ville, ta.Libelle AS Type_Activite,
                cl.Nom, cl.Prenom, cl.Email, cl.Telephone
           " . statFromReleves() . "
           JOIN facture f ON f.ID_Consommation_Mensuelle = cm.ID_Consommation_Mensuelle
           JOIN client cl ON cl.ID_Client = cm.ID_Client
          WHERE " . implode(' AND ', $where) . "
          ORDER BY f.Date_Facture DESC, f.ID_Facture DESC",
        $types ?: null, $params
    );
}
 
// Villes qui ont au moins un quartier (pour le filtre)
function getVillesQuartiers()
{
    return array_column(consoSelect("SELECT DISTINCT Ville FROM quartier ORDER BY Ville"), 'Ville');
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