<?php
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/../BD/Facture.php';
require_once __DIR__ . '/../BD/Contrat.php';   

/**
 * Génère une facture si le relevé est validé ou corrigé
 */
function genererFacture($idConso)
{
    $s = getSaisiePourFacture($idConso);
    
    // Accepter les statuts 'Verifie', 'Anomalie' et 'Saisie_Fournisseur'
    if (!$s || !in_array($s['Statut_Traitement'], ['Verifie', 'Anomalie', 'Saisie_Fournisseur'])) {
        return null;
    }
    
    if ($s['Statut_Traitement'] === 'Anomalie' && (float) $s['Consommation'] < 0) {
        return null;
    }

    if (getFactureDeSaisie($idConso)) {
        return null; // Déjà facturée
    }

    [$ht, $tva, $ttc] = calculerMontants((float) $s['Consommation'], $s['Type_Compteur']);

    $debut = sprintf('%04d-%02d-01', (int) $s['Annee'], (int) $s['Mois']);
    $fin   = date('Y-m-t', strtotime($debut));
    $date  = date('Y-m-d', maintenant());  

    return insererFacture($idConso, $date, $debut, $fin, $ht, $tva, $ttc);
}

/**
 * Prépare la liste des factures pour l'IHM client
 */
function preparerPageFactures()
{
    $idClient = (int) $_SESSION['user']['id'];
    $factures = [];

    // [P3] Contrats du client (pour le filtre) ; on n'accepte qu'un contrat qui lui appartient
    $contrats = [];
    $idsValides = [];
    foreach (getContratsDuClient($idClient) as $ct) {
        $eauCt = $ct['Type_Compteur'] === 'Eau';
        $idsValides[] = (int) $ct['ID_Contrat'];
        $contrats[] = [
            'id'    => (int) $ct['ID_Contrat'],
            'label' => 'Contrat n° ' . $ct['ID_Contrat'] . ' — ' . ($eauCt ? 'Eau' : 'Électricité')
                     . ' · ' . $ct['Quartier'] . ' (' . $ct['Ville'] . ') · ' . $ct['Type_Activite']
                     . ($ct['Statut'] === 'Actif' ? '' : ' (terminé)'),
        ];
    }
    $filtre = (int) ($_GET['contrat'] ?? 0);
    if (!in_array($filtre, $idsValides, true)) {
        $filtre = 0;
    }

    $moisFr = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
    ];

    foreach (getFacturesDuClient($idClient, $filtre) as $f) {
        $eau = (strtolower($f['Type_Compteur']) === 'eau');
        $statuts = [
            'Payee'     => ['Payée',     'paye'],
            'Partielle' => ['Partielle', 'partiel'],
            'Impayee'   => ['Impayée',   'impaye'],
        ];
        [$statutLabel, $statutClasse] = $statuts[$f['Statut_Paiement']] ?? $statuts['Impayee'];

        // Sécurité si les fonctions utilitaires manquent
        $dateFormatted = function_exists('dateFr') ? dateFr($f['Date_Facture']) : date('d/m/Y', strtotime($f['Date_Facture']));
        $periodeFormatted = function_exists('periodeFr') 
            ? periodeFr($f['Periode_Debut'], $f['Periode_Fin'], $f['Mois'], $f['Annee']) 
            : ($moisFr[(int)$f['Mois']] ?? 'Mois ' . $f['Mois']) . ' ' . $f['Annee'];
        $consoFormatted = function_exists('formaterIndex') 
            ? formaterIndex($f['Consommation']) 
            : number_format((float)$f['Consommation'], 2, ',', ' ');
        $ttcFormatted = function_exists('formaterMontant') 
            ? formaterMontant($f['Montant_TTC']) 
            : number_format((float)$f['Montant_TTC'], 2, ',', ' ');

        $factures[] = [
            'id'        => (int) $f['ID_Facture'],
            'contrat'   => $f['ID_Contrat'] ? (int) $f['ID_Contrat'] : null,
            'quartier'  => $f['Quartier'] ? $f['Quartier'] . ' (' . $f['Ville'] . ')' : null,
            'activite'  => $f['Type_Activite'],
            'numero'    => 'FAC-' . $f['Annee'] . '-' . ($eau ? 'EAU' : 'ELEC')
                         . '-' . str_pad($f['ID_Facture'], 4, '0', STR_PAD_LEFT),
            'date'      => $dateFormatted,
            'periode'   => $periodeFormatted,
            'type'      => $eau ? 'eau' : 'elec',
            'typeLabel' => $eau ? 'Eau' : 'Électricité',
            'conso'     => $consoFormatted . ' ' . ($eau ? 'm³' : 'kWh'),
            'ttc'       => $ttcFormatted,
            'statut'    => $statutLabel,
            'classe'    => $statutClasse,
            'paye'      => (strtolower($f['Statut_Paiement']) === 'payee' || strtolower($f['Statut_Paiement']) === 'paye'),
        ];
    }
    return compact('factures', 'contrats', 'filtre');
}

/**
 * Prépare les données détaillées d'une facture imprimable / visualisable
 */
function preparerFactureImprimable($idFacture, $idClient)
{
    $f = getFactureDuClient($idFacture, $idClient);
    if (!$f) {
        return null;
    }
    $eau   = (strtolower($f['Type_Compteur']) === 'eau');
    $unite = $eau ? 'm³' : 'kWh';
    $paye  = (strtolower($f['Statut_Paiement']) === 'payee' || strtolower($f['Statut_Paiement']) === 'paye');

    $moisFr = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
    ];

    $dateFormatted = function_exists('dateFr') ? dateFr($f['Date_Facture']) : date('d/m/Y', strtotime($f['Date_Facture']));
    $periodeFormatted = function_exists('periodeFr') 
        ? periodeFr($f['Periode_Debut'], $f['Periode_Fin'], $f['Mois'], $f['Annee']) 
        : ($moisFr[(int)$f['Mois']] ?? 'Mois ' . $f['Mois']) . ' ' . $f['Annee'];
    $consoFormatted = function_exists('formaterIndex') ? formaterIndex($f['Consommation']) : number_format((float)$f['Consommation'], 2, ',', ' ');
    $indexPrecFormatted = function_exists('formaterIndex') ? formaterIndex($f['Index_Precedent']) : number_format((float)$f['Index_Precedent'], 2, ',', ' ');
    $indexActFormatted = function_exists('formaterIndex') ? formaterIndex($f['Index_Actuel']) : number_format((float)$f['Index_Actuel'], 2, ',', ' ');

    // [P3] Détail par tranche + redevance : fourni par la Personne 2 (traitement/Regles.php).
    // Tant que detailMontants() n'existe pas, la facture garde l'affichage actuel (une seule ligne).
    $detail = null;
    if (function_exists('detailMontants') && !empty($f['Type_Activite'])) {
        try {
            $detail = detailMontants((float) $f['Consommation'], $f['Type_Compteur'], $f['Type_Activite']);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $detail = null;
        }
    }

    return [
        'contrat'      => $f['ID_Contrat'] ? (int) $f['ID_Contrat'] : null,
        'quartier'     => $f['Quartier'] ? $f['Quartier'] . ' (' . $f['Ville'] . ')' : null,
        'activite'     => $f['Type_Activite'],
        'detail'       => $detail,
        'unite'        => $unite,
        'numero'       => 'FAC-' . $f['Annee'] . '-' . ($eau ? 'EAU' : 'ELEC')
                        . '-' . str_pad($f['ID_Facture'], 4, '0', STR_PAD_LEFT),
        'date'         => $dateFormatted,
        'periode'      => $periodeFormatted,
        'client'       => $f['Prenom'] . ' ' . $f['Nom'],
        'adresse'      => $f['Adresse'],
        'compteur'     => $f['ID_Compteur'],
        'typeLabel'    => $eau ? 'Eau' : 'Électricité',
        'indexPrec'    => $indexPrecFormatted . ' ' . $unite,
        'indexAct'     => $indexActFormatted . ' ' . $unite,
        'conso'        => $consoFormatted . ' ' . $unite,
        'ht'           => function_exists('formaterMontant') ? formaterMontant($f['Montant_HT']) : number_format((float)$f['Montant_HT'], 2, ',', ' '),
        'tva'          => function_exists('formaterMontant') ? formaterMontant($f['TVA']) : number_format((float)$f['TVA'], 2, ',', ' '),
        'ttc'          => function_exists('formaterMontant') ? formaterMontant($f['Montant_TTC']) : number_format((float)$f['Montant_TTC'], 2, ',', ' '),
        'paye'         => $paye,
        'statut'       => $paye ? 'Payée' : 'Impayée',
        'datePaiement' => $f['Date_paiement'] ? ($dateFormatted) : null,
        'type'         => $eau ? 'eau' : 'elec',
        'contact'      => $f['Email'] . ' · ' . $f['Telephone'],
        'photoAvant'   => $f['Ancien_photo'],
        'photoApres'   => $f['Photo_Actuelle'],
    ];
}