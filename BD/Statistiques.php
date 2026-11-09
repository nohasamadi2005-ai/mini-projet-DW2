<?php
/*  Statistiques du tableau de bord fournisseur (Personne 5).
    Aucune vue SQL : la jointure  relevé -> contrat -> quartier -> type d'activité
    est écrite UNE seule fois, dans statFromReleves(), et réutilisée par toutes les requêtes.
    (consommation_mensuelle n'a pas d'ID_Contrat : on rattache le relevé au contrat du MÊME
    compteur qui couvre le mois du relevé ; en cas de transfert dans le mois, le contrat
    le plus récent est retenu.)                                                              */
require_once __DIR__ . '/Consommation.php';


// Début / fin du mois d'un relevé, en SQL
const STAT_DEBUT_MOIS = "STR_TO_DATE(CONCAT(cm.Annee, '-', cm.Mois, '-01'), '%Y-%m-%d')";

function statFromReleves($jointure = 'JOIN')
{
    return "FROM consommation_mensuelle cm
            " . $jointure . " contrat ct ON ct.ID_Contrat = (
                   SELECT c2.ID_Contrat FROM contrat c2
                    WHERE c2.ID_Compteur = cm.ID_Compteur
                      AND c2.Date_Debut <= LAST_DAY(" . STAT_DEBUT_MOIS . ")
                      AND (c2.Date_Fin IS NULL OR c2.Date_Fin >= " . STAT_DEBUT_MOIS . ")
                    ORDER BY c2.Date_Debut DESC, c2.ID_Contrat DESC
                    LIMIT 1)
            " . $jointure . " quartier q       ON q.ID_Quartier = ct.ID_Quartier
            " . $jointure . " type_activite ta ON ta.ID_Type_Activite = q.ID_Type_Activite
            JOIN compteur cp      ON cp.ID_Compteur = cm.ID_Compteur";
}

// $annee = 0 -> toutes les années
function statFiltreAnnee()
{
    return " (? = 0 OR cm.Annee = ?) ";
}


function statAnnees()
{
    return array_map('intval', array_column(
        consoSelect("SELECT DISTINCT Annee FROM consommation_mensuelle ORDER BY Annee DESC"), 'Annee'
    ));
}


// Chiffre d'affaires par type d'activité : facturé, encaissé, impayé
function statCAParTypeActivite($annee)
{
    return consoSelect(
        "SELECT ta.Libelle AS Type_Activite,
                COUNT(f.ID_Facture)                                                   AS Nb_Factures,
                COALESCE(SUM(f.Montant_TTC), 0)                                       AS Facture,
                COALESCE(SUM(CASE WHEN f.Statut_Paiement = 'Payee' THEN f.Montant_TTC END), 0)  AS Encaisse,
                COALESCE(SUM(CASE WHEN f.Statut_Paiement <> 'Payee' THEN f.Montant_TTC END), 0) AS Impaye
           " . statFromReleves() . "
           JOIN facture f ON f.ID_Consommation_Mensuelle = cm.ID_Consommation_Mensuelle
          WHERE " . statFiltreAnnee() . "
          GROUP BY ta.ID_Type_Activite, ta.Libelle
          ORDER BY Encaisse DESC",
        'ii', [$annee, $annee]
    );
}


// Consommation par quartier (relevés vérifiés), eau en m³ et électricité en kWh séparées
function statConsoParQuartier($annee)
{
    return consoSelect(
        "SELECT q.ID_Quartier, q.Nom AS Quartier, q.Ville, ta.Libelle AS Type_Activite,
                COALESCE(SUM(CASE WHEN cp.Type_Compteur = 'Eau' THEN cm.Consommation END), 0) AS Conso_Eau,
                COALESCE(SUM(CASE WHEN cp.Type_Compteur <> 'Eau' THEN cm.Consommation END), 0) AS Conso_Elec
           " . statFromReleves() . "
          WHERE cm.Statut_Traitement = 'Verifie' AND " . statFiltreAnnee() . "
          GROUP BY q.ID_Quartier, q.Nom, q.Ville, ta.Libelle
          ORDER BY q.Ville, q.Nom",
        'ii', [$annee, $annee]
    );
}


// Impayés par quartier
function statImpayesParQuartier($annee)
{
    return consoSelect(
        "SELECT q.ID_Quartier, q.Nom AS Quartier, q.Ville, ta.Libelle AS Type_Activite,
                COUNT(f.ID_Facture)  AS Nb_Factures,
                SUM(f.Montant_TTC)   AS Total
           " . statFromReleves() . "
           JOIN facture f ON f.ID_Consommation_Mensuelle = cm.ID_Consommation_Mensuelle
          WHERE f.Statut_Paiement <> 'Payee' AND " . statFiltreAnnee() . "
          GROUP BY q.ID_Quartier, q.Nom, q.Ville, ta.Libelle
          ORDER BY Total DESC",
        'ii', [$annee, $annee]
    );
}


function statContratsActifs()
{
    $r = consoSelect("SELECT COUNT(*) AS n FROM contrat WHERE Statut = 'Actif'");
    return (int) ($r[0]['n'] ?? 0);
}