<?php
/*
 * CONTRATS — accès à la base (Personne 1)
 *
 * Un contrat = un client + un compteur + un quartier (zone), du Date_Debut au Date_Fin.
 * Règles tenues par ce fichier et par traitement/contrat.php :
 *   - au plus UN contrat actif par compteur (garanti aussi par la base) ;
 *   - compteur.ID_Client = titulaire du contrat actif (NULL si le compteur est libre) ;
 *   - un compteur ne bouge pas : tous ses contrats sont dans le même quartier.
 *
 * Fonctions utilisées par les autres membres de l'équipe :
 *   getContratsDuClient($idClient, $actifsSeulement = false)  -> liste des contrats du client
 *   getContratParId($idContrat)                               -> un contrat (ou null)
 *   getContratActifDuCompteur($idCompteur)                    -> contrat actif du compteur (ou null)
 *   getContratsDuCompteur($idCompteur)                        -> tous les contrats du compteur (historique)
 * Chaque contrat renvoyé contient : ID_Contrat, ID_Client, Nom, Prenom, ID_Compteur, Type_Compteur,
 *   ID_Quartier, Quartier, Ville, ID_Type_Activite, Type_Activite, Adresse_Branchement,
 *   Date_Debut, Date_Fin, Index_Debut, Index_Fin, Statut, Motif_Fin, Dernier_Index.
 */
require_once __DIR__ . '/Consommation.php';   // Connexion, consoSelect, consoExecuter

// Dernier index valable d'un compteur (même règle que getReferencePrecedente)
const SQL_DERNIER_INDEX = "COALESCE((SELECT cm.Index_Actuel FROM consommation_mensuelle cm
                                      WHERE cm.ID_Compteur = cp.ID_Compteur
                                        AND (cm.Statut_Traitement = 'Verifie'
                                             OR (cm.Statut_Traitement = 'Anomalie' AND cm.Consommation >= 0))
                                      ORDER BY cm.Annee DESC, cm.Mois DESC LIMIT 1), cp.Index_Initial)";

const SQL_CONTRAT = "SELECT ct.ID_Contrat, ct.ID_Client, ct.ID_Compteur, ct.ID_Quartier, ct.Adresse_Branchement,
                            ct.Date_Debut, ct.Date_Fin, ct.Index_Debut, ct.Index_Fin, ct.Statut, ct.Motif_Fin,
                            ct.Date_Creation,
                            cl.Nom, cl.Prenom, cl.Email, cl.Telephone,
                            cp.Type_Compteur, cp.Index_Initial,
                            q.Nom AS Quartier, q.Ville, q.ID_Type_Activite, t.Libelle AS Type_Activite,
                            " . SQL_DERNIER_INDEX . " AS Dernier_Index
                       FROM contrat ct
                       JOIN client cl        ON cl.ID_Client = ct.ID_Client
                       JOIN compteur cp      ON cp.ID_Compteur = ct.ID_Compteur
                       JOIN quartier q       ON q.ID_Quartier = ct.ID_Quartier
                       JOIN type_activite t  ON t.ID_Type_Activite = q.ID_Type_Activite";


// =====================================================================
//  LECTURE
// =====================================================================

// Filtres possibles : client, quartier, activite (type d'activité), statut, type (compteur), q (recherche)
function filtreContratsSql($f)
{
    $where = [];
    $types = '';
    $params = [];
    if (!empty($f['client']))   { $where[] = 'ct.ID_Client = ?';         $types .= 'i'; $params[] = (int) $f['client']; }
    if (!empty($f['quartier'])) { $where[] = 'ct.ID_Quartier = ?';       $types .= 'i'; $params[] = (int) $f['quartier']; }
    if (!empty($f['activite'])) { $where[] = 'q.ID_Type_Activite = ?';   $types .= 'i'; $params[] = (int) $f['activite']; }
    if (!empty($f['statut']))   { $where[] = 'ct.Statut = ?';            $types .= 's'; $params[] = $f['statut']; }
    if (!empty($f['type']))     { $where[] = 'cp.Type_Compteur = ?';     $types .= 's'; $params[] = $f['type']; }
    if (isset($f['q']) && $f['q'] !== '') {
        $like = '%' . $f['q'] . '%';
        $sql  = "(cl.Nom LIKE ? OR cl.Prenom LIKE ? OR CONCAT(cl.Prenom, ' ', cl.Nom) LIKE ? OR ct.Adresse_Branchement LIKE ?";
        $types .= 'ssss';
        array_push($params, $like, $like, $like, $like);
        if (ctype_digit($f['q'])) {
            $sql .= " OR ct.ID_Contrat = ? OR ct.ID_Compteur = ? OR ct.ID_Client = ?";
            $types .= 'iii';
            array_push($params, (int) $f['q'], (int) $f['q'], (int) $f['q']);
        }
        $where[] = $sql . ')';
    }
    return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $types, $params];
}

function getContrats($f = [], $limite = 10, $decalage = 0)
{
    [$where, $types, $params] = filtreContratsSql($f);
    $types   .= 'ii';
    $params[] = (int) $limite;
    $params[] = (int) $decalage;
    return consoSelect(SQL_CONTRAT . $where
        . " ORDER BY (ct.Statut = 'Actif') DESC, ct.ID_Contrat DESC LIMIT ? OFFSET ?", $types, $params);
}

function compterContrats($f = [])
{
    [$where, $types, $params] = filtreContratsSql($f);
    $r = consoSelect("SELECT COUNT(*) AS n
                        FROM contrat ct
                        JOIN client cl   ON cl.ID_Client = ct.ID_Client
                        JOIN compteur cp ON cp.ID_Compteur = ct.ID_Compteur
                        JOIN quartier q  ON q.ID_Quartier = ct.ID_Quartier" . $where, $types ?: null, $params);
    return (int) ($r[0]['n'] ?? 0);
}

function getContratParId($idContrat)
{
    $r = consoSelect(SQL_CONTRAT . " WHERE ct.ID_Contrat = ?", 'i', [(int) $idContrat]);
    return $r[0] ?? null;
}

function getContratsDuClient($idClient, $actifsSeulement = false)
{
    return consoSelect(SQL_CONTRAT . " WHERE ct.ID_Client = ?" . ($actifsSeulement ? " AND ct.Statut = 'Actif'" : '')
        . " ORDER BY (ct.Statut = 'Actif') DESC, cp.Type_Compteur, ct.Date_Debut DESC", 'i', [(int) $idClient]);
}

function getContratActifDuCompteur($idCompteur)
{
    $r = consoSelect(SQL_CONTRAT . " WHERE ct.ID_Compteur = ? AND ct.Statut = 'Actif'", 'i', [(int) $idCompteur]);
    return $r[0] ?? null;
}

function getContratsDuCompteur($idCompteur)
{
    return consoSelect(SQL_CONTRAT . " WHERE ct.ID_Compteur = ? ORDER BY ct.Date_Debut, ct.ID_Contrat",
        'i', [(int) $idCompteur]);
}

// Chiffres pour les cartes en haut de l'écran
function statistiquesContrats()
{
    $r = consoSelect(
        "SELECT SUM(ct.Statut = 'Actif')                                AS actifs,
                SUM(ct.Statut = 'Resilie')                              AS resilies,
                SUM(ct.Statut = 'Actif' AND q.ID_Type_Activite = 1)     AS residentiel,
                SUM(ct.Statut = 'Actif' AND q.ID_Type_Activite = 2)     AS commercial,
                SUM(ct.Statut = 'Actif' AND q.ID_Type_Activite = 3)     AS industriel,
                (SELECT COUNT(*) FROM compteur WHERE ID_Client IS NULL) AS libres
           FROM contrat ct JOIN quartier q ON q.ID_Quartier = ct.ID_Quartier"
    );
    return array_map('intval', $r[0] ?? []);
}

// Clients pour les listes déroulantes
function getClientsPourContrat()
{
    return consoSelect("SELECT ID_Client, Nom, Prenom, Adresse FROM client ORDER BY Nom, Prenom");
}

// Un compteur avec ce qu'il faut savoir pour lui créer un contrat :
// titulaire actuel, dernier index, zone et adresse de son dernier contrat, fin du dernier contrat.
function getCompteurPourContrat($idCompteur)
{
    $r = consoSelect(
        "SELECT cp.ID_Compteur, cp.ID_Client, cp.Type_Compteur, cp.Index_Initial,
                " . SQL_DERNIER_INDEX . " AS Dernier_Index,
                d.ID_Quartier AS Dernier_Quartier, d.Adresse_Branchement AS Derniere_Adresse,
                (SELECT MAX(x.Date_Fin) FROM contrat x WHERE x.ID_Compteur = cp.ID_Compteur) AS Derniere_Fin
           FROM compteur cp
           LEFT JOIN contrat d ON d.ID_Contrat = (SELECT MAX(y.ID_Contrat) FROM contrat y WHERE y.ID_Compteur = cp.ID_Compteur)
          WHERE cp.ID_Compteur = ?",
        'i', [(int) $idCompteur]
    );
    return $r[0] ?? null;
}

// Compteurs libres (sans contrat actif), avec la même information
function getCompteursLibresPourContrat()
{
    return consoSelect(
        "SELECT cp.ID_Compteur, cp.Type_Compteur,
                " . SQL_DERNIER_INDEX . " AS Dernier_Index,
                d.ID_Quartier AS Dernier_Quartier, d.Adresse_Branchement AS Derniere_Adresse
           FROM compteur cp
           LEFT JOIN contrat d ON d.ID_Contrat = (SELECT MAX(y.ID_Contrat) FROM contrat y WHERE y.ID_Compteur = cp.ID_Compteur)
          WHERE cp.ID_Client IS NULL
          ORDER BY cp.Type_Compteur, cp.ID_Compteur"
    );
}

// Dernier relevé valable d'un compteur (ou null)
function getDernierReleveCompteur($idCompteur)
{
    $r = consoSelect(
        "SELECT Mois, Annee, Index_Actuel FROM consommation_mensuelle
          WHERE ID_Compteur = ?
            AND (Statut_Traitement = 'Verifie' OR (Statut_Traitement = 'Anomalie' AND Consommation >= 0))
          ORDER BY Annee DESC, Mois DESC LIMIT 1",
        'i', [(int) $idCompteur]
    );
    return $r[0] ?? null;
}

// Relevés du compteur encore en attente de traitement (bloquent une résiliation)
function compterRelevesEnAttente($idCompteur)
{
    $r = consoSelect(
        "SELECT COUNT(*) AS n FROM consommation_mensuelle
          WHERE ID_Compteur = ?
            AND (Statut_Traitement IN ('En attente', 'Resaisie demandee', 'Saisie_Fournisseur')
                 OR (Statut_Traitement = 'Anomalie' AND Consommation < 0))",
        'i', [(int) $idCompteur]
    );
    return (int) ($r[0]['n'] ?? 0);
}

// Factures impayées d'un client sur un compteur : ['nb' => , 'montant' => ]
function getImpayesContrat($idClient, $idCompteur)
{
    $r = consoSelect(
        "SELECT COUNT(*) AS nb, COALESCE(SUM(f.Montant_TTC), 0) AS montant
           FROM facture f
           JOIN consommation_mensuelle cm ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
          WHERE cm.ID_Client = ? AND cm.ID_Compteur = ? AND f.Statut_Paiement <> 'Payee'",
        'ii', [(int) $idClient, (int) $idCompteur]
    );
    return ['nb' => (int) ($r[0]['nb'] ?? 0), 'montant' => (float) ($r[0]['montant'] ?? 0)];
}


// =====================================================================
//  ÉCRITURE — chaque fonction reçoit la connexion $cnx de la transaction
// =====================================================================

function executerContrat($cnx, $sql, $types, $params)
{
    $stmt = mysqli_prepare($cnx, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($cnx);
    mysqli_stmt_close($stmt);
    return $id;
}

function creerCompteurNeuf($cnx, $numero, $type, $indexInitial, $dateInstallation)
{
    executerContrat($cnx,
        "INSERT INTO compteur (ID_Compteur, ID_Client, Type_Compteur, Index_Initial, Date_Installation, statut)
         VALUES (?, NULL, ?, ?, ?, 'inactif')",
        'isds', [(int) $numero, $type, (float) $indexInitial, $dateInstallation]);
}

function insererContrat($cnx, $idClient, $idCompteur, $idQuartier, $adresse, $dateDebut, $indexDebut, $idAdmin)
{
    return executerContrat($cnx,
        "INSERT INTO contrat (ID_Client, ID_Compteur, ID_Quartier, Adresse_Branchement, Date_Debut, Index_Debut, Statut, ID_Admin)
         VALUES (?, ?, ?, ?, ?, ?, 'Actif', ?)",
        'iiissdi', [(int) $idClient, (int) $idCompteur, (int) $idQuartier, $adresse, $dateDebut, (float) $indexDebut,
                    $idAdmin ? (int) $idAdmin : null]);
}

function cloturerContrat($cnx, $idContrat, $dateFin, $indexFin, $motif)
{
    executerContrat($cnx,
        "UPDATE contrat SET Statut = 'Resilie', Date_Fin = ?, Index_Fin = ?, Motif_Fin = ?
          WHERE ID_Contrat = ? AND Statut = 'Actif'",
        'sdsi', [$dateFin, (float) $indexFin, $motif, (int) $idContrat]);
}

// Le compteur prend le titulaire du contrat actif (NULL = compteur libre)
function definirTitulaireCompteur($cnx, $idCompteur, $idClient)
{
    if ($idClient) {
        executerContrat($cnx, "UPDATE compteur SET ID_Client = ?, statut = 'actif' WHERE ID_Compteur = ?",
            'ii', [(int) $idClient, (int) $idCompteur]);
    } else {
        executerContrat($cnx, "UPDATE compteur SET ID_Client = NULL, statut = 'inactif' WHERE ID_Compteur = ?",
            'i', [(int) $idCompteur]);
    }
}

// Correction de la zone d'un compteur : s'applique à tous ses contrats (le compteur ne bouge pas)
function changerZoneCompteur($idCompteur, $idQuartier)
{
    return consoExecuter("UPDATE contrat SET ID_Quartier = ? WHERE ID_Compteur = ?",
        'ii', [(int) $idQuartier, (int) $idCompteur]);
}
