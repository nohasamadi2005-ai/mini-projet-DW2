<?php
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/../BD/Facture.php';

// Génère la facture d'une saisie vérifiée. Retourne l'id de la facture, ou null si rien à faire.
function genererFacture($idConso)
{
    $s = getSaisiePourFacture($idConso);
    if (!$s || $s['Statut_Traitement'] !== 'Verifie') {
        return null;                       // pas de facture pour une anomalie
    }
    if (getFactureDeSaisie($idConso)) {
        return null;                       // déjà facturée
    }

    [$ht, $tva, $ttc] = calculerMontants((float) $s['Consommation'], $s['Type_Compteur']);

    $debut = sprintf('%04d-%02d-01', (int) $s['Annee'], (int) $s['Mois']);
    $fin   = date('Y-m-t', strtotime($debut));
    $date  = date('Y-m-d', maintenant());  // respecte DATE_SIMULEE

    return insererFacture($idConso, $date, $debut, $fin, $ht, $tva, $ttc);
}

// Données pour la page « Mes factures »
// (utilise periodeFr(), formaterIndex(), formaterMontant() de Saisie.php et dateFr() de Regles.php)
function preparerPageFactures()
{
    $idClient = (int) $_SESSION['user']['id'];
    $factures = [];

    foreach (getFacturesDuClient($idClient) as $f) {
        $eau = $f['Type_Compteur'] === 'Eau';
        $statuts = [
            'Payee'     => ['Payée',     'paye'],
            'Partielle' => ['Partielle', 'partiel'],
            'Impayee'   => ['Impayée',   'impaye'],
        ];
        [$statutLabel, $statutClasse] = $statuts[$f['Statut_Paiement']] ?? $statuts['Impayee'];

        $factures[] = [
            'id'        => (int) $f['ID_Facture'],
            'numero'    => 'FAC-' . $f['Annee'] . '-' . ($eau ? 'EAU' : 'ELEC')
                         . '-' . str_pad($f['ID_Facture'], 4, '0', STR_PAD_LEFT),
            'date'      => dateFr($f['Date_Facture']),
            'periode'   => periodeFr($f['Periode_Debut'], $f['Periode_Fin'], $f['Mois'], $f['Annee']),
            'type'      => $eau ? 'eau' : 'elec',
            'typeLabel' => $eau ? 'Eau' : 'Électricité',
            'conso'     => formaterIndex($f['Consommation']) . ' ' . ($eau ? 'm³' : 'kWh'),
            'ttc'       => formaterMontant($f['Montant_TTC']),
            'statut'    => $statutLabel,
            'classe'    => $statutClasse,
            'paye'      => $f['Statut_Paiement'] === 'Payee',   // <-- AJOUTE CETTE LIGNE

        ];
    }
    return compact('factures');
}
// Données pour la page « Voir / imprimer ». Retourne null si la facture n'appartient pas au client.
function preparerFactureImprimable($idFacture, $idClient)
{
    $f = getFactureDuClient($idFacture, $idClient);
    if (!$f) {
        return null;
    }
    $eau   = $f['Type_Compteur'] === 'Eau';
    $unite = $eau ? 'm³' : 'kWh';
    $paye  = $f['Statut_Paiement'] === 'Payee';

    return [
        'numero'       => 'FAC-' . $f['Annee'] . '-' . ($eau ? 'EAU' : 'ELEC')
                        . '-' . str_pad($f['ID_Facture'], 4, '0', STR_PAD_LEFT),
        'date'         => dateFr($f['Date_Facture']),
        'periode'      => periodeFr($f['Periode_Debut'], $f['Periode_Fin'], $f['Mois'], $f['Annee']),
        'client'       => $f['Prenom'] . ' ' . $f['Nom'],
        'adresse'      => $f['Adresse'],
        'compteur'     => $f['ID_Compteur'],
        'typeLabel'    => $eau ? 'Eau' : 'Électricité',
        'indexPrec'    => formaterIndex($f['Index_Precedent']) . ' ' . $unite,
        'indexAct'     => formaterIndex($f['Index_Actuel']) . ' ' . $unite,
        'conso'        => formaterIndex($f['Consommation']) . ' ' . $unite,
        'ht'           => formaterMontant($f['Montant_HT']),
        'tva'          => formaterMontant($f['TVA']),
        'ttc'          => formaterMontant($f['Montant_TTC']),
        'paye'         => $paye,
        'statut'       => $paye ? 'Payée' : 'Impayée',
        'datePaiement' => $f['Date_paiement'] ? dateFr($f['Date_paiement']) : null,
        'type'         => $eau ? 'eau' : 'elec',
        'contact'      => $f['Email'] . ' · ' . $f['Telephone'],
        'photoAvant'   => $f['Ancien_photo'],
        'photoApres'   => $f['Photo_Actuelle'],
    ];
}