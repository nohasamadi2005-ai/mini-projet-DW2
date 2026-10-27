<?php

require_once __DIR__ . '/connexion.php';




function consoConnexion()
{
    static $cnx = null;
    if ($cnx === null || !mysqli_ping($cnx)) {
        $cnx = Connexion();
    }
    return $cnx;
}


function consoSelect($sql, $types = null, $params = [])
{
    $cnx = consoConnexion();
    $stmt = mysqli_prepare($cnx, $sql);

    if (!$stmt) {
        error_log("Erreur SQL dans consoSelect: " . mysqli_error($cnx) . " | Requête: " . $sql);
        return [];
    }

    if ($types && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $resultat = mysqli_stmt_get_result($stmt);

    $donnees = [];
    if ($resultat) {
        while ($ligne = mysqli_fetch_assoc($resultat)) {
            $donnees[] = $ligne;
        }
    }

    mysqli_stmt_close($stmt);

    return $donnees;
}


function consoExecuter($sql, $types, $params)
{
    $cnx = consoConnexion();
    $stmt = mysqli_prepare($cnx, $sql);

    // Vérification de la préparation de la requête
    if (!$stmt) {
        error_log("Erreur SQL dans consoExecuter: " . mysqli_error($cnx) . " | Requête: " . $sql);
        die("Erreur de préparation SQL : " . mysqli_error($cnx));
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);

    $retour = mysqli_stmt_insert_id($stmt) ?: mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return $retour;
}




function getCompteursDuClient($idClient)
{
    return consoSelect(
        "SELECT ID_Compteur, Type_Compteur, Index_Initial
           FROM compteur WHERE ID_Client = ? ORDER BY Type_Compteur",
        'i', [$idClient]
    );
}


function getCompteurDuClient($idCompteur, $idClient)
{
    $r = consoSelect(
        "SELECT ID_Compteur, Type_Compteur, Index_Initial
           FROM compteur WHERE ID_Compteur = ? AND ID_Client = ?",
        'ii', [$idCompteur, $idClient]
    );
    return $r[0] ?? null;
}



function getReferencePrecedente($compteur, $mois, $annee)
{
    $r = consoSelect(
        "SELECT Index_Actuel, Photo_Actuelle, Date_saisie, Mois, Annee
           FROM consommation_mensuelle
          WHERE ID_Compteur = ?
  AND (Statut_Traitement = 'Verifie'
       OR (Statut_Traitement = 'Anomalie' AND Consommation >= 0))
  AND (Annee < ? OR (Annee = ? AND Mois < ?))
          ORDER BY Annee DESC, Mois DESC LIMIT 1",
        'iiii', [$compteur['ID_Compteur'], $annee, $annee, $mois]
    );
    if ($r) {
        return [
            'premiere' => false,
            'index'    => (float) $r[0]['Index_Actuel'],
            'photo'    => $r[0]['Photo_Actuelle'],
            'date'     => $r[0]['Date_saisie'],
            'mois'     => (int) $r[0]['Mois'],
            'annee'    => (int) $r[0]['Annee'],
        ];
    }
    return [
        'premiere' => true,
        'index'    => (float) $compteur['Index_Initial'],
        'photo'    => null, 
        'date'     => null,
        'mois'     => null, 
        'annee'    => null,
    ];
}



function getSaisie($idCompteur, $mois, $annee)
{
    $r = consoSelect(
        "SELECT * FROM consommation_mensuelle
          WHERE ID_Compteur = ? AND Mois = ? AND Annee = ?",
        'iii', [$idCompteur, $mois, $annee]
    );
    return $r[0] ?? null;
}


function getSaisieEnResaisie($idCompteur)
{
    $r = consoSelect(
        "SELECT * FROM consommation_mensuelle 
          WHERE ID_Compteur = ? 
            AND Statut_Traitement = 'Resaisie demandee' 
          LIMIT 1",
        'i',
        [$idCompteur]
    );
    return $r[0] ?? null;
}

function insererSaisie($idClient, $idCompteur, $mois, $annee, $indexPrec, $indexActuel,
                       $consommation, $anciennePhoto, $photoActuelle, $statut)
{
    return consoExecuter(
        "INSERT INTO consommation_mensuelle
           (ID_Client, ID_Compteur, Mois, Annee, Index_Precedent, Index_Actuel,
            Consommation, Ancien_photo, Photo_Actuelle, Statut_Traitement)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        'iiiidddsss',
        [$idClient, $idCompteur, $mois, $annee, $indexPrec, $indexActuel,
         $consommation, $anciennePhoto, $photoActuelle, $statut]
    );
}


function modifierSaisie($idConso, $indexPrec, $indexActuel, $consommation,
                        $anciennePhoto, $photoActuelle, $statut)
{
    return consoExecuter(
        "UPDATE consommation_mensuelle
            SET Index_Precedent = ?, Index_Actuel = ?, Consommation = ?,
                Ancien_photo = ?, Photo_Actuelle = ?, Statut_Traitement = ?,
                Date_saisie = NOW()
          WHERE ID_Consommation_Mensuelle = ?",
        'dddsssi',
        [$indexPrec, $indexActuel, $consommation, $anciennePhoto, $photoActuelle, $statut, $idConso]
    );
}

function creerAnomalie($idConso, $type, $description)
{
    return consoExecuter(
        "INSERT INTO anomalie (ID_Consommation_Mensuelle, Type_Anomalie, Description)
         VALUES (?, ?, ?)",
        'iss', [$idConso, $type, $description]
    );
}


function getFactureDeSaisie($idConso)
{
    $r = consoSelect(
        "SELECT f.ID_Facture, f.Date_Facture, f.Periode_Debut, f.Periode_Fin,
                f.Montant_HT, f.TVA, f.Montant_TTC, f.Statut_Paiement,
                cm.ID_Compteur, cm.Mois, cm.Annee, cm.Index_Precedent, cm.Index_Actuel,
                cm.Consommation, cm.Ancien_photo, cm.Photo_Actuelle,
                cl.Nom, cl.Prenom, cl.Adresse, cl.Telephone, cl.Email,
                c.Type_Compteur
           FROM facture f
           JOIN consommation_mensuelle cm
             ON cm.ID_Consommation_Mensuelle = f.ID_Consommation_Mensuelle
           JOIN client cl ON cl.ID_Client = cm.ID_Client
           JOIN compteur c ON c.ID_Compteur = cm.ID_Compteur
          WHERE f.ID_Consommation_Mensuelle = ? LIMIT 1",
        'i', [$idConso]
    );
    return $r[0] ?? null;
}