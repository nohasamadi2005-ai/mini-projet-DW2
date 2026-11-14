<?php
require_once __DIR__ . '/Consommation.php';

function getTranches($idTypeActivite, $typeCompteur)
{
    return consoSelect(
        "SELECT ID_Tarif, ID_Type_Activite, Type_Compteur, Borne_Haute, Prix_Unitaire
           FROM tarif
          WHERE ID_Type_Activite = ? AND Type_Compteur = ?
          ORDER BY (Borne_Haute IS NULL), Borne_Haute",
        'is', [(int) $idTypeActivite, $typeCompteur]
    );
}

function getTrancheParId($idTarif)
{
    $r = consoSelect(
        "SELECT ID_Tarif, ID_Type_Activite, Type_Compteur, Borne_Haute, Prix_Unitaire
           FROM tarif WHERE ID_Tarif = ?",
        'i', [(int) $idTarif]
    );
    return $r[0] ?? null;
}

function ajouterTranche($idTypeActivite, $typeCompteur, $borneHaute, $prix)
{
    return consoExecuter(
        "INSERT INTO tarif (ID_Type_Activite, Type_Compteur, Borne_Haute, Prix_Unitaire)
         VALUES (?, ?, ?, ?)",
        'isdd',
        [(int) $idTypeActivite, $typeCompteur,
         $borneHaute === null ? null : (float) $borneHaute,
         (float) $prix]
    );
}

function supprimerTranche($idTarif)
{
    return consoExecuter("DELETE FROM tarif WHERE ID_Tarif = ?", 'i', [(int) $idTarif]);
}

function getTypesActiviteDetail()
{
    return consoSelect(
        "SELECT ID_Type_Activite, Libelle, Description, Seuil_Eau, Seuil_Elec, Redevance_Eau, Redevance_Elec
           FROM type_activite ORDER BY ID_Type_Activite"
    );
}

function getTypeActiviteDetail($idTypeActivite)
{
    $r = consoSelect(
        "SELECT ID_Type_Activite, Libelle, Description, Seuil_Eau, Seuil_Elec, Redevance_Eau, Redevance_Elec
           FROM type_activite WHERE ID_Type_Activite = ?",
        'i', [(int) $idTypeActivite]
    );
    return $r[0] ?? null;
}

function getTypeActiviteParLibelle($libelle)
{
    $r = consoSelect(
        "SELECT ID_Type_Activite, Libelle, Description, Seuil_Eau, Seuil_Elec, Redevance_Eau, Redevance_Elec
           FROM type_activite WHERE Libelle = ?",
        's', [(string) $libelle]
    );
    return $r[0] ?? null;
}

function modifierTypeActivite($idTypeActivite, $seuilEau, $seuilElec, $redevanceEau, $redevanceElec)
{
    return consoExecuter(
        "UPDATE type_activite
            SET Seuil_Eau = ?, Seuil_Elec = ?, Redevance_Eau = ?, Redevance_Elec = ?
          WHERE ID_Type_Activite = ?",
        'ddddi',
        [(float) $seuilEau, (float) $seuilElec, (float) $redevanceEau, (float) $redevanceElec, (int) $idTypeActivite]
    );
}

function getTypeActiviteDuCompteur($idCompteur)
{
    $r = consoSelect(
        "SELECT q.ID_Type_Activite
           FROM contrat c
           JOIN quartier q ON q.ID_Quartier = c.ID_Quartier
          WHERE c.ID_Compteur = ? AND c.Statut = 'Actif'
          LIMIT 1",
        'i', [(int) $idCompteur]
    );
    return isset($r[0]) ? (int) $r[0]['ID_Type_Activite'] : null;
}

function modifierTranche($idTarif, $prix)
{
    return consoExecuter(
        "UPDATE tarif SET Prix_Unitaire = ? WHERE ID_Tarif = ?",
        'di', [(float) $prix, (int) $idTarif]
    );
}