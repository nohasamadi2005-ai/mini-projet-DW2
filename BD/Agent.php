<?php
/*
 * AGENT — accès à la base (Personne 4)
 *
 * Comptes agent (existant) :
 *   trouverAgentParEmail($email), creerAgent(...), modifierMotDePasseAgent($id, $hash)
 *
 * Agents et quartiers (nouveau) :
 *   getAgents()                            -> tous les agents + nombre de quartiers
 *   getAgentParId($idAgent)                -> un agent (ou null)
 *   getQuartiersAgent($idAgent)            -> les quartiers de la tournée d'un agent
 *   getAffectationsQuartiers()             -> [ID_Quartier => ID_Agent] (écran fournisseur « Agents »)
 *   setQuartiersAgent($idAgent, $ids)      -> remplace les quartiers d'un agent
 *
 * Tournée (nouveau) :
 *   getTourneeAgent($idAgent, $annee)      -> contrats actifs à relever + statut « relevé »
 *   getProgressionTournee($idAgent, $annee, $tournee = null) -> total / relevés / restants / %
 *
 * Règle « relevé » : un contrat est relevé pour une année si une ligne intégrée (Statut_Import = 'Integre')
 * du fichier de l'agent porte son ID_Contrat (une ligne du fichier = un contrat = un compteur).
 */
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/Consommation.php';   // consoConnexion, consoSelect, consoExecuter


// =====================================================================
//  COMPTES AGENT (existant, inchangé)
// =====================================================================

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


// =====================================================================
//  AGENTS ET QUARTIERS
// =====================================================================

// Tous les agents, avec le nombre de quartiers qui leur sont affectés
function getAgents()
{
    return consoSelect(
        "SELECT a.ID_Agent, a.Nom, a.Prenom, a.Email, a.Telephone, a.Zone, a.Actif,
                COUNT(aq.ID_Quartier) AS Nb_Quartiers
           FROM agent a
           LEFT JOIN agent_quartier aq ON aq.ID_Agent = a.ID_Agent
          GROUP BY a.ID_Agent, a.Nom, a.Prenom, a.Email, a.Telephone, a.Zone, a.Actif
          ORDER BY a.Nom, a.Prenom"
    );
}

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

// Tous les quartiers (écran fournisseur « Agents »). Requête propre à cette page : elle ne dépend pas de BD/Quartier.php
function getQuartiersPourAffectation()
{
    return consoSelect(
        "SELECT q.ID_Quartier, q.Nom, q.Ville, q.ID_Type_Activite, t.Libelle AS Type_Activite
           FROM quartier q
           JOIN type_activite t ON t.ID_Type_Activite = q.ID_Type_Activite
          ORDER BY t.ID_Type_Activite, q.Ville, q.Nom"
    );
}

// [ID_Quartier => ID_Agent] : qui s'occupe de quel quartier (un quartier n'a qu'un agent)
function getAffectationsQuartiers()
{
    $out = [];
    foreach (consoSelect("SELECT ID_Quartier, ID_Agent FROM agent_quartier") as $l) {
        $out[(int) $l['ID_Quartier']] = (int) $l['ID_Agent'];
    }
    return $out;
}

/*
 * Remplace les quartiers d'un agent par la liste donnée ($idsQuartiers = tableau d'ID).
 * - Un quartier ne peut avoir qu'un agent : cocher un quartier déjà pris par un autre agent
 *   le lui retire et le donne à cet agent.
 * - Les ID inconnus sont ignorés. Tout est fait en transaction (tout ou rien).
 * Retourne true si c'est enregistré, false sinon (agent inconnu ou erreur SQL).
 */
function setQuartiersAgent($idAgent, $idsQuartiers)
{
    $idAgent = (int) $idAgent;
    if (!getAgentParId($idAgent)) {
        return false;
    }

    // On ne garde que des quartiers qui existent, sans doublon
    $valides = array_map('intval', array_column(getQuartiersPourAffectation(), 'ID_Quartier'));
    $ids = array_values(array_unique(array_intersect(
        array_map('intval', (array) $idsQuartiers), $valides
    )));

    $cnx = consoConnexion();   // même connexion que consoExecuter -> même transaction
    try {
        mysqli_begin_transaction($cnx);

        consoExecuter("DELETE FROM agent_quartier WHERE ID_Agent = ?", 'i', [$idAgent]);

        foreach ($ids as $idQuartier) {
            // Si le quartier appartenait à un autre agent, il passe à celui-ci
            consoExecuter(
                "INSERT INTO agent_quartier (ID_Agent, ID_Quartier) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE ID_Agent = VALUES(ID_Agent)",
                'ii', [$idAgent, $idQuartier]
            );
        }

        mysqli_commit($cnx);
        return true;
    } catch (Throwable $e) {
        mysqli_rollback($cnx);
        error_log('setQuartiersAgent : ' . $e->getMessage());
        return false;
    }
}


// =====================================================================
//  TOURNÉE
// =====================================================================

/*
 * Contrats situés dans les quartiers de l'agent et EN COURS pendant l'année donnée
 * (début du contrat <= 31/12 et fin du contrat vide ou >= 01/01 : un contrat résilié en cours
 * d'année est donc relevé aussi).
 * Chaque ligne : ID_Contrat, ID_Client, Nom, Prenom, ID_Compteur, Type_Compteur, ID_Quartier,
 *   Quartier, Ville, Adresse_Branchement, Statut_Contrat, Date_Debut, Date_Fin,
 *   Releve (0/1), Date_Releve (date du dépôt ou NULL).
 * Un contrat est relevé si une ligne intégrée porte son ID_Contrat pour cette année
 * (ou, pour les anciens dépôts sans contrat, une ligne intégrée de son client).
 */
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

/*
 * Progression de la tournée : ['total' => .., 'releves' => .., 'restants' => .., 'pourcentage' => ..]
 * On peut passer le résultat de getTourneeAgent() en 3e argument pour éviter une 2e requête.
 */
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