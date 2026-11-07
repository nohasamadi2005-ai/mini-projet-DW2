<?php
// Quartiers (zones) et types d'activité — lecture seule.
// Personne 2 (quartiers & tarifs) complète ce fichier avec l'ajout / la modification.
require_once __DIR__ . '/Consommation.php';   // consoSelect

// Tous les quartiers avec leur type d'activité, triés par type puis ville puis nom
function getQuartiers()
{
    return consoSelect(
        "SELECT q.ID_Quartier, q.Nom, q.Ville, q.ID_Type_Activite, t.Libelle AS Type_Activite
           FROM quartier q
           JOIN type_activite t ON t.ID_Type_Activite = q.ID_Type_Activite
          ORDER BY t.ID_Type_Activite, q.Ville, q.Nom"
    );
}

function getQuartierParId($idQuartier)
{
    $r = consoSelect(
        "SELECT q.ID_Quartier, q.Nom, q.Ville, q.ID_Type_Activite, t.Libelle AS Type_Activite
           FROM quartier q
           JOIN type_activite t ON t.ID_Type_Activite = q.ID_Type_Activite
          WHERE q.ID_Quartier = ?",
        'i', [(int) $idQuartier]
    );
    return $r[0] ?? null;
}

function getTypesActivite()
{
    return consoSelect("SELECT ID_Type_Activite, Libelle FROM type_activite ORDER BY ID_Type_Activite");
}
