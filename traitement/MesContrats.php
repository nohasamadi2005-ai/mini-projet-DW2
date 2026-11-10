<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/../BD/Contrat.php';   // getContratsDuClient()

function preparerPageMesContrats()
{
    $idClient = (int) $_SESSION['user']['id'];

    $groupesActifs = [];
    $resilies      = [];
    $nbActifs      = 0;

    foreach (getContratsDuClient($idClient) as $ct) {
        $eau    = $ct['Type_Compteur'] === 'Eau';
        $actif  = $ct['Statut'] === 'Actif';
        $adr    = trim((string) $ct['Adresse_Branchement']);

        $carte = [
            'id'        => (int) $ct['ID_Contrat'],
            'label'     => $eau ? 'Eau' : 'Électricité',
            'icone'     => $eau ? 'goutte' : 'eclair',
            'compteur'  => ($eau ? 'EAU-' : 'ELEC-') . str_pad($ct['ID_Compteur'], 5, '0', STR_PAD_LEFT),
            'quartier'  => $ct['Quartier'] . ' (' . $ct['Ville'] . ')',
            'activite'  => $ct['Type_Activite'],
            'adresse'   => $adr,
            // [P2] Le tarif dépend du type d'activité. Quand la personne 2 aura publié ses tarifs,
            // on pourra afficher ici le détail des tranches via detailMontants().
            'tarif'     => 'Tarif ' . $ct['Type_Activite'] . ' — ' . ($eau ? 'Eau' : 'Électricité'),
            'seuil'     => seuilMax($ct['Type_Compteur'], $ct['Type_Activite']) . ' ' . ($eau ? 'm³' : 'kWh') . ' / mois',
            'debut'     => dateFr($ct['Date_Debut']),
            'fin'       => $ct['Date_Fin'] ? dateFr($ct['Date_Fin']) : null,
            'motif'     => $ct['Motif_Fin'],
            'indexDebut' => formaterIndexContrat($ct['Index_Debut']),
            'indexFin'   => $ct['Index_Fin'] !== null ? formaterIndexContrat($ct['Index_Fin']) : null,
            'unite'     => $eau ? 'm³' : 'kWh',
        ];

        if (!$actif) {
            $resilies[] = $carte;
            continue;
        }
        $nbActifs++;

        // Un logement / local = même quartier + même adresse de branchement
        $cle = $ct['ID_Quartier'] . '|' . mb_strtolower($adr);
        if (!isset($groupesActifs[$cle])) {
            $groupesActifs[$cle] = [
                'titre'    => $carte['quartier'],
                'activite' => $carte['activite'],
                'adresse'  => $adr,
                'contrats' => [],
            ];
        }
        $groupesActifs[$cle]['contrats'][] = $carte;
    }

    return [
        'groupes'   => array_values($groupesActifs),
        'resilies'  => $resilies,
        'nbActifs'  => $nbActifs,
        'nbLogements' => count($groupesActifs),
    ];
}

function formaterIndexContrat($v)
{
    $v = (float) $v;
    return number_format($v, fmod($v, 1) == 0.0 ? 0 : 2, ',', ' ');
}
