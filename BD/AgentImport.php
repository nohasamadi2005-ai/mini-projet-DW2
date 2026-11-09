<?php
/*
 * IMPORT DES RELEVÉS DE L'AGENT — accès à la base (Personne 4)
 *
 * Chaque ligne d'un fichier .txt déposé par un agent devient une ligne de
 * consommation_annuelle :
 *   - Statut_Import = 'Integre' : ligne valide, utilisée par la Vérification annuelle ;
 *   - Statut_Import = 'Rejete'  : ligne refusée, gardée avec son motif (Motif_Rejet),
 *                                 son numéro de ligne et son texte brut (Ligne_Brute).
 *
 * Fonctions :
 *   insererLigneImport(...)                    -> enregistre UNE ligne (intégrée ou rejetée)
 *   insererConsommationAnnuelle(...)           -> ancienne fonction, conservée (= ligne intégrée)
 *   getEtatClientsPourAgent($idAgent)          -> pour contrôler les lignes : [ID_Client => état]
 *   getImportsAgent($idAgent, $search)         -> un dépôt (fichier) par ligne, avec nb intégrées / rejetées
 *   getStatistiquesImports($idAgent)           -> chiffres des cartes de l'historique
 *   getDetailDepot($idAgent, $fichier)         -> détail d'un dépôt : lignes intégrées et lignes rejetées
 */
require_once __DIR__ . '/Consommation.php';   // consoSelect, consoExecuter


// =====================================================================
//  ÉCRITURE
// =====================================================================

/*
 * Enregistre une ligne du fichier.
 *   $idClient   : NULL si la ligne n'a pas de client valide (rejet « client introuvable », etc.)
 *   $consoEau / $consoElect : NULL si les valeurs n'ont pas pu être lues
 *   $numeroLigne : numéro de la ligne dans le fichier ; $ligneBrute : son texte tel quel
 *   $statut     : 'Integre' ou 'Rejete' ; $motif : obligatoire pour un rejet
 * Retourne true si la ligne est enregistrée.
 */
function insererLigneImport($idClient, $idAgent, $annee, $consoEau, $consoElect, $fichierSource,
                            $numeroLigne = null, $ligneBrute = null, $statut = 'Integre', $motif = null)
{
    if (!in_array($statut, ['Integre', 'Rejete'], true)) {
        return false;
    }
    if ($statut === 'Integre') {
        $motif = null;
    } elseif ($motif === null || $motif === '') {
        $motif = 'Motif non précisé';
    }

    $idClient    = ($idClient === null || $idClient === '') ? null : (int) $idClient;
    $consoEau    = ($consoEau === null)   ? null : (float) $consoEau;
    $consoElect  = ($consoElect === null) ? null : (float) $consoElect;
    $numeroLigne = ($numeroLigne === null) ? null : (int) $numeroLigne;
    $ligneBrute  = ($ligneBrute === null)  ? null : tronquerTexte($ligneBrute, 255);
    $motif       = ($motif === null)       ? null : tronquerTexte($motif, 255);

    try {
        return (bool) consoExecuter(
            "INSERT INTO consommation_annuelle
                    (ID_Client, ID_Agent, Annee, ConsommationEau, ConsommationElect, Date_saisie,
                     Fichier_Source, Numero_Ligne, Ligne_Brute, Statut_Import, Motif_Rejet)
             VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)",
            'iiiddsisss',
            [$idClient, (int) $idAgent, (int) $annee, $consoEau, $consoElect,
             $fichierSource, $numeroLigne, $ligneBrute, $statut, $motif]
        );
    } catch (Throwable $e) {
        error_log('insererLigneImport : ' . $e->getMessage());
        return false;
    }
}

// Coupe un texte à $max caractères (sans casser les accents, même sans l'extension mbstring)
function tronquerTexte($texte, $max)
{
    $texte = (string) $texte;
    return function_exists('mb_substr') ? mb_substr($texte, 0, $max) : substr($texte, 0, $max);
}

// Ancienne fonction (gardée pour ne rien casser) : une ligne intégrée sans détail
function insererConsommationAnnuelle($idClient, $idAgent, $annee, $consoEau, $consoElect, $fichierSource)
{
    return insererLigneImport($idClient, $idAgent, $annee, $consoEau, $consoElect, $fichierSource);
}


// =====================================================================
//  CONTRÔLE DES LIGNES (utilisé par traitement/agent.php)
// =====================================================================

/*
 * Pour chaque client : combien de contrats actifs il a, et combien sont dans les quartiers de l'agent.
 *   [ID_Client => ['contrats_actifs' => n, 'dans_tournee' => m]]
 * Un client absent du tableau n'existe pas. Une seule requête pour tout le fichier.
 *   contrats_actifs = 0            -> « Aucun contrat actif »
 *   contrats_actifs > 0, dans_tournee = 0 -> « Hors de votre tournée »
 */
function getEtatClientsPourAgent($idAgent)
{
    $rows = consoSelect(
        "SELECT cl.ID_Client,
                COUNT(ct.ID_Contrat)           AS contrats_actifs,
                COUNT(aq.ID_Quartier)          AS dans_tournee
           FROM client cl
           LEFT JOIN contrat ct       ON ct.ID_Client = cl.ID_Client AND ct.Statut = 'Actif'
           LEFT JOIN agent_quartier aq ON aq.ID_Quartier = ct.ID_Quartier AND aq.ID_Agent = ?
          GROUP BY cl.ID_Client",
        'i', [(int) $idAgent]
    );
    $etat = [];
    foreach ($rows as $r) {
        $etat[(int) $r['ID_Client']] = [
            'contrats_actifs' => (int) $r['contrats_actifs'],
            'dans_tournee'    => (int) $r['dans_tournee'],
        ];
    }
    return $etat;
}


// =====================================================================
//  LECTURE (historique)
// =====================================================================

/*
 * Un dépôt (= un fichier + une année) par ligne, du plus récent au plus ancien.
 * Colonnes : Nom_Fichier, Date_Import, Annee, ID_Agent, Zone, Nb_Integres, Nb_Rejetes,
 *            Nb_Lignes, Nb_Releves (= Nb_Integres, ancien nom), Statut ('Integre' | 'Partiel' | 'Rejete').
 * $idAgent = null -> tous les agents. $search filtre sur le nom du fichier ou l'année.
 */
function getImportsAgent($idAgent = null, $search = '')
{
    $where  = ["Fichier_Source IS NOT NULL", "Fichier_Source <> ''"];
    $types  = '';
    $params = [];

    if ($idAgent) {
        $where[]  = 'ID_Agent = ?';
        $types   .= 'i';
        $params[] = (int) $idAgent;
    }
    $search = trim((string) $search);
    if ($search !== '') {
        $like     = '%' . addcslashes($search, '%_\\') . '%';
        $where[]  = '(Fichier_Source LIKE ? OR Annee LIKE ?)';
        $types   .= 'ss';
        array_push($params, $like, $like);
    }

    $imports = consoSelect(
        "SELECT Fichier_Source AS Nom_Fichier,
                MIN(Date_saisie) AS Date_Import,
                Annee,
                ID_Agent,
                SUM(Statut_Import = 'Integre') AS Nb_Integres,
                SUM(Statut_Import = 'Rejete')  AS Nb_Rejetes,
                COUNT(*)                       AS Nb_Lignes
           FROM consommation_annuelle
          WHERE " . implode(' AND ', $where) . "
          GROUP BY Fichier_Source, Annee, ID_Agent
          ORDER BY Date_Import DESC",
        $types ?: null, $params
    );

    $zones = getZonesDesFichiers($idAgent);
    foreach ($imports as &$imp) {
        $imp['Nb_Integres'] = (int) $imp['Nb_Integres'];
        $imp['Nb_Rejetes']  = (int) $imp['Nb_Rejetes'];
        $imp['Nb_Lignes']   = (int) $imp['Nb_Lignes'];
        $imp['Nb_Releves']  = $imp['Nb_Integres'];
        $imp['Zone']        = $zones[$imp['Nom_Fichier']] ?? '—';
        $imp['Statut']      = $imp['Nb_Rejetes'] === 0 ? 'Integre'
                            : ($imp['Nb_Integres'] === 0 ? 'Rejete' : 'Partiel');
    }
    unset($imp);

    return $imports;
}

// [Fichier_Source => 'Al Manar, Hay Jadid'] : quartiers des clients des lignes intégrées du fichier
function getZonesDesFichiers($idAgent = null)
{
    $types  = '';
    $params = [];
    $filtre = '';
    if ($idAgent) {
        $filtre = ' AND ca.ID_Agent = ?';
        $types  = 'i';
        $params = [(int) $idAgent];
    }
    $out = [];
    foreach (consoSelect(
        "SELECT ca.Fichier_Source,
                GROUP_CONCAT(DISTINCT q.Nom ORDER BY q.Nom SEPARATOR ', ') AS Zone
           FROM consommation_annuelle ca
           JOIN contrat ct  ON ct.ID_Client = ca.ID_Client AND ct.Statut = 'Actif'
           JOIN quartier q  ON q.ID_Quartier = ct.ID_Quartier
          WHERE ca.Statut_Import = 'Integre' AND ca.Fichier_Source IS NOT NULL" . $filtre . "
          GROUP BY ca.Fichier_Source",
        $types ?: null, $params
    ) as $r) {
        $out[$r['Fichier_Source']] = $r['Zone'];
    }
    return $out;
}

// Chiffres de l'historique : fichiers déposés, relevés intégrés, lignes rejetées
function getStatistiquesImports($idAgent = null)
{
    $where  = '';
    $types  = null;
    $params = [];
    if ($idAgent) {
        $where  = 'WHERE ID_Agent = ?';
        $types  = 'i';
        $params = [(int) $idAgent];
    }
    $r = consoSelect(
        "SELECT COUNT(DISTINCT Fichier_Source)  AS fichiers_deposes,
                COALESCE(SUM(Statut_Import = 'Integre'), 0) AS releves_integres,
                COALESCE(SUM(Statut_Import = 'Rejete'), 0)  AS releves_rejetes
           FROM consommation_annuelle $where",
        $types, $params
    );
    $d = $r[0] ?? [];
    return [
        'fichiers_deposes' => (int) ($d['fichiers_deposes'] ?? 0),
        'releves_integres' => (int) ($d['releves_integres'] ?? 0),
        'releves_rejetes'  => (int) ($d['releves_rejetes'] ?? 0),
    ];
}

/*
 * Détail d'un dépôt de l'agent : null si ce fichier n'existe pas pour cet agent.
 * Sinon : ['fichier', 'annee', 'date_import', 'integrees' => [...], 'rejetees' => [...]]
 * Chaque ligne : Numero_Ligne, ID_Client, Nom, Prenom, ConsommationEau, ConsommationElect,
 *                Statut_Import, Motif_Rejet, Ligne_Brute, Date_saisie.
 * (Nom et Prenom sont NULL quand le client est introuvable.)
 */
function getDetailDepot($idAgent, $fichier)
{
    $lignes = consoSelect(
        "SELECT ca.Numero_Ligne, ca.ID_Client, cl.Nom, cl.Prenom,
                ca.ConsommationEau, ca.ConsommationElect,
                ca.Statut_Import, ca.Motif_Rejet, ca.Ligne_Brute, ca.Annee, ca.Date_saisie
           FROM consommation_annuelle ca
           LEFT JOIN client cl ON cl.ID_Client = ca.ID_Client
          WHERE ca.ID_Agent = ? AND ca.Fichier_Source = ?
          ORDER BY (ca.Numero_Ligne IS NULL), ca.Numero_Ligne, ca.ID_Consommation_Annuelle",
        'is', [(int) $idAgent, (string) $fichier]
    );
    if (!$lignes) {
        return null;
    }

    $detail = [
        'fichier'     => (string) $fichier,
        'annee'       => (int) $lignes[0]['Annee'],
        'date_import' => min(array_column($lignes, 'Date_saisie')),
        'integrees'   => [],
        'rejetees'    => [],
    ];
    foreach ($lignes as $l) {
        if ($l['Statut_Import'] === 'Rejete') {
            $detail['rejetees'][] = $l;
        } else {
            $detail['integrees'][] = $l;
        }
    }
    return $detail;
}