<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../BD/HistoriqueCompteur.php';

function preparerPageHistoriqueCompteurs()
{
    $idClient   = max(0, (int) ($_GET['client'] ?? 0));
    $idCompteur = max(0, (int) ($_GET['compteur'] ?? 0));

    $lignes = [];
    foreach (getHistoriqueCompteurs($idClient, $idCompteur) as $h) {
        $enCours  = $h['Date_Fin'] === null;
        $lignes[] = [
            'compteur'  => (int) $h['ID_Compteur'],
            'type'      => $h['Type_Compteur'],
            'unite'     => $h['Type_Compteur'] === 'Eau' ? 'm³' : 'kWh',
            'idClient'  => $h['ID_Client'] !== null ? (int) $h['ID_Client'] : null,
            'client'    => $h['ID_Client'] !== null ? trim($h['Prenom'] . ' ' . $h['Nom']) : 'Client supprimé',
            'debut'     => date('d/m/Y', strtotime($h['Date_Debut'])),
            'fin'       => $enCours ? null : date('d/m/Y', strtotime($h['Date_Fin'])),
            'enCours'   => $enCours,
            'indexDeb'  => $h['Index_Debut'] !== null ? number_format((float) $h['Index_Debut'], 2, ',', ' ') : '—',
            'indexFin'  => $h['Index_Fin'] !== null ? number_format((float) $h['Index_Fin'], 2, ',', ' ') : '—',
            'saisies'   => (int) $h['nb_saisies'],
            'conso'     => number_format((float) $h['conso_totale'], 2, ',', ' '),
            'contrat'   => (int) $h['ID_Contrat'],
            'zone'      => $h['Quartier'] . ' — ' . $h['Ville'],
            'activite'  => $h['Type_Activite'],
            'motif'     => ['Resiliation' => 'Résiliation', 'Demenagement' => 'Déménagement', 'Transfert' => 'Transfert'][$h['Motif_Fin']] ?? '',
        ];
    }

    $client = $idClient ? getClientPourHistorique($idClient) : null;

    return [
        'lignes'     => $lignes,
        'idClient'   => $idClient,
        'idCompteur' => $idCompteur,
        'client'     => $client ? trim($client['Prenom'] . ' ' . $client['Nom']) : null,
    ];
}