<?php
require_once __DIR__ . '/Consommation.php';

function insererLigneImport($idClient, $idAgent, $annee, $consoEau, $consoElect, $fichierSource,
                            $numeroLigne = null, $ligneBrute = null, $statut = 'Integre', $motif = null, $idContrat = null)
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
    $idContrat   = ($idContrat === null || $idContrat === '') ? null : (int) $idContrat;
    $consoEau    = ($consoEau === null)   ? null : (float) $consoEau;
    $consoElect  = ($consoElect === null) ? null : (float) $consoElect;
    $numeroLigne = ($numeroLigne === null) ? null : (int) $numeroLigne;
    $ligneBrute  = ($ligneBrute === null)  ? null : tronquerTexte($ligneBrute, 255);
    $motif       = ($motif === null)       ? null : tronquerTexte($motif, 255);

    try {
        return (bool) consoExecuter(
            "INSERT INTO consommation_annuelle
                    (ID_Client, ID_Agent, Annee, ConsommationEau, ConsommationElect, Date_saisie,
                     Fichier_Source, Numero_Ligne, Ligne_Brute, Statut_Import, Motif_Rejet, ID_Contrat)
             VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)",
            'iiiddsisssi',
            [$idClient, (int) $idAgent, (int) $annee, $consoEau, $consoElect,
             $fichierSource, $numeroLigne, $ligneBrute, $statut, $motif, $idContrat]
        );
    } catch (Throwable $e) {
        error_log('insererLigneImport : ' . $e->getMessage());
        return false;
    }
}

function tronquerTexte($texte, $max)
{
    $texte = (string) $texte;
    return function_exists('mb_substr') ? mb_substr($texte, 0, $max) : substr($texte, 0, $max);
}

function insererConsommationAnnuelle($idClient, $idAgent, $annee, $consoEau, $consoElect, $fichierSource)
{
    return insererLigneImport($idClient, $idAgent, $annee, $consoEau, $consoElect, $fichierSource);
}

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

function getEtatContratsPourAgent($idAgent, $annee)
{
    $annee = (int) $annee;
    $rows = consoSelect(
        "SELECT ct.ID_Contrat, ct.ID_Client, cp.Type_Compteur,
                (ct.Date_Debut <= ? AND (ct.Date_Fin IS NULL OR ct.Date_Fin >= ?)) AS valide_annee,
                (aq.ID_Quartier IS NOT NULL) AS dans_tournee
           FROM contrat ct
           JOIN compteur cp            ON cp.ID_Compteur = ct.ID_Compteur
           LEFT JOIN agent_quartier aq ON aq.ID_Quartier = ct.ID_Quartier AND aq.ID_Agent = ?",
        'ssi', [$annee . '-12-31', $annee . '-01-01', (int) $idAgent]
    );
    $etat = [];
    foreach ($rows as $r) {
        $etat[(int) $r['ID_Contrat']] = [
            'client'       => (int) $r['ID_Client'],
            'type'         => $r['Type_Compteur'],
            'valide_annee' => (bool) $r['valide_annee'],
            'dans_tournee' => (bool) $r['dans_tournee'],
        ];
    }
    return $etat;
}

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

function getZonesDesFichiers($idAgent = null)
{
    $filtre = '';
    $types  = '';
    $params = [];
    if ($idAgent) {
        $filtre = ' AND ca.ID_Agent = ?';
        $types  = 'ii';
        $params = [(int) $idAgent, (int) $idAgent];
    }
    $rows = consoSelect(
        "SELECT DISTINCT ca.Fichier_Source, q.Nom
           FROM consommation_annuelle ca
           JOIN contrat ct ON ct.ID_Contrat = ca.ID_Contrat
           JOIN quartier q ON q.ID_Quartier = ct.ID_Quartier
          WHERE ca.Statut_Import = 'Integre' AND ca.Fichier_Source IS NOT NULL" . $filtre . "
         UNION
         SELECT DISTINCT ca.Fichier_Source, q.Nom
           FROM consommation_annuelle ca
           JOIN contrat ct ON ct.ID_Client = ca.ID_Client AND ct.Statut = 'Actif'
           JOIN quartier q ON q.ID_Quartier = ct.ID_Quartier
          WHERE ca.ID_Contrat IS NULL AND ca.Statut_Import = 'Integre' AND ca.Fichier_Source IS NOT NULL" . $filtre,
        $types ?: null, $params
    );
    $noms = [];
    foreach ($rows as $r) {
        $noms[$r['Fichier_Source']][] = $r['Nom'];
    }
    $out = [];
    foreach ($noms as $fichier => $liste) {
        sort($liste);
        $out[$fichier] = implode(', ', $liste);
    }
    return $out;
}

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

function getDetailDepot($idAgent, $fichier)
{
    $lignes = consoSelect(
        "SELECT ca.Numero_Ligne, ca.ID_Contrat, cp.Type_Compteur, ca.ID_Client, cl.Nom, cl.Prenom,
                ca.ConsommationEau, ca.ConsommationElect,
                ca.Statut_Import, ca.Motif_Rejet, ca.Ligne_Brute, ca.Annee, ca.Date_saisie
           FROM consommation_annuelle ca
           LEFT JOIN client cl   ON cl.ID_Client   = ca.ID_Client
           LEFT JOIN contrat ct  ON ct.ID_Contrat  = ca.ID_Contrat
           LEFT JOIN compteur cp ON cp.ID_Compteur = ct.ID_Compteur
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