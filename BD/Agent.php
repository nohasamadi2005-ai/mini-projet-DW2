<?php

require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/Consommation.php';   // consoConnexion, consoSelect, consoExecuter


//  COMPTES AGENT (existant, inchangé)

function trouverAgentParEmail($email)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx,
        "SELECT ID_Agent AS id, Nom, Prenom, Email, Password AS mdp
         FROM agent WHERE Email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res);
}

function creerAgent($nom, $prenom, $email, $hash)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx,
        "INSERT INTO agent (Nom, Prenom, Email, Password)
         VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $nom, $prenom, $email, $hash);
    if (mysqli_stmt_execute($stmt)) {
        return mysqli_insert_id($cnx);
    }
    return false;
}

function modifierMotDePasseAgent($id, $hash)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx, "UPDATE agent SET Password = ? WHERE ID_Agent = ?");
    mysqli_stmt_bind_param($stmt, "si", $hash, $id);
    return mysqli_stmt_execute($stmt);
}


//  AGENTS ET QUARTIERS



function getAgentParId($idAgent)
{
    $r = consoSelect(
        "SELECT ID_Agent, Nom, Prenom, Email, Telephone, Zone, Actif FROM agent WHERE ID_Agent = ?",
        'i', [(int) $idAgent]
    );
    return $r[0] ?? null;
}

// Quartiers de la tournée d'un agent (avec leur type d'activité)
function getQuartiersAgent($idAgent)
{
    return consoSelect(
        "SELECT q.ID_Quartier, q.Nom, q.Ville, q.ID_Type_Activite, t.Libelle AS Type_Activite
           FROM agent_quartier aq
           JOIN quartier q      ON q.ID_Quartier = aq.ID_Quartier
           JOIN type_activite t ON t.ID_Type_Activite = q.ID_Type_Activite
          WHERE aq.ID_Agent = ?
          ORDER BY q.Ville, q.Nom",
        'i', [(int) $idAgent]
    );
}



//  TOURNÉE


function getTourneeAgent($idAgent, $annee)
{
    $annee   = (int) $annee;
    $idAgent = (int) $idAgent;
    return consoSelect(
        "SELECT ct.ID_Contrat, ct.ID_Client, cl.Nom, cl.Prenom,
                ct.ID_Compteur, cp.Type_Compteur,
                ct.ID_Quartier, q.Nom AS Quartier, q.Ville, ct.Adresse_Branchement,
                ct.Statut AS Statut_Contrat, ct.Date_Debut, ct.Date_Fin,
                EXISTS (SELECT 1 FROM consommation_annuelle ca
                         WHERE ca.Annee = ? AND ca.Statut_Import = 'Integre'
                           AND (ca.ID_Contrat = ct.ID_Contrat
                                OR (ca.ID_Contrat IS NULL AND ca.ID_Client = ct.ID_Client))) AS Releve,
                (SELECT MAX(ca.Date_saisie) FROM consommation_annuelle ca
                  WHERE ca.Annee = ? AND ca.Statut_Import = 'Integre'
                    AND (ca.ID_Contrat = ct.ID_Contrat
                         OR (ca.ID_Contrat IS NULL AND ca.ID_Client = ct.ID_Client))) AS Date_Releve
           FROM contrat ct
           JOIN agent_quartier aq ON aq.ID_Quartier = ct.ID_Quartier AND aq.ID_Agent = ?
           JOIN client cl         ON cl.ID_Client   = ct.ID_Client
           JOIN compteur cp       ON cp.ID_Compteur = ct.ID_Compteur
           JOIN quartier q        ON q.ID_Quartier  = ct.ID_Quartier
          WHERE ct.Date_Debut <= ? AND (ct.Date_Fin IS NULL OR ct.Date_Fin >= ?)
          ORDER BY q.Ville, q.Nom, cl.Nom, cl.Prenom, ct.ID_Contrat",
        'iiiss', [$annee, $annee, $idAgent, $annee . '-12-31', $annee . '-01-01']
    );
}


function getProgressionTournee($idAgent, $annee, $tournee = null)
{
    if ($tournee === null) {
        $tournee = getTourneeAgent($idAgent, $annee);
    }
    $total   = count($tournee);
    $releves = 0;
    foreach ($tournee as $l) {
        if (!empty($l['Releve'])) {
            $releves++;
        }
    }
    return [
        'total'       => $total,
        'releves'     => $releves,
        'restants'    => $total - $releves,
        'pourcentage' => $total > 0 ? (int) round($releves * 100 / $total) : 0,
    ];
}