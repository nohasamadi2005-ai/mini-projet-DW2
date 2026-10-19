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
        ];
    }
    return compact('factures');
}