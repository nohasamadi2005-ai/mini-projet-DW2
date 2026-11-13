<?php
require_once __DIR__ . '/Consommation.php';

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

function quartierExiste($nom, $ville, $sauf = 0)
{
    $r = consoSelect(
        "SELECT ID_Quartier FROM quartier WHERE Nom = ? AND Ville = ? AND ID_Quartier <> ?",
        'ssi', [$nom, $ville, (int) $sauf]
    );
    return !empty($r);
}

function ajouterQuartier($nom, $ville, $idTypeActivite)
{
    return consoExecuter(
        "INSERT INTO quartier (Nom, Ville, ID_Type_Activite) VALUES (?, ?, ?)",
        'ssi', [$nom, $ville, (int) $idTypeActivite]
    );
}

function modifierQuartier($idQuartier, $nom, $ville, $idTypeActivite)
{
    return consoExecuter(
        "UPDATE quartier SET Nom = ?, Ville = ?, ID_Type_Activite = ? WHERE ID_Quartier = ?",
        'ssii', [$nom, $ville, (int) $idTypeActivite, (int) $idQuartier]
    );
}

function compterContratsActifsQuartier($idQuartier)
{
    $r = consoSelect(
        "SELECT COUNT(*) AS n FROM contrat WHERE ID_Quartier = ? AND Statut = 'Actif'",
        'i', [(int) $idQuartier]
    );
    return (int) ($r[0]['n'] ?? 0);
}

function compterContratsQuartier($idQuartier)
{
    $r = consoSelect("SELECT COUNT(*) AS n FROM contrat WHERE ID_Quartier = ?", 'i', [(int) $idQuartier]);
    return (int) ($r[0]['n'] ?? 0);
}

function supprimerQuartier($idQuartier)
{
    return consoExecuter("DELETE FROM quartier WHERE ID_Quartier = ?", 'i', [(int) $idQuartier]);
}