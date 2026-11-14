<?php
$liensMenu = [
    'gestion_client'           => ['fa-users',                'Gestion Clients'],
    'contrats'                 => ['fa-file-signature',       'Contrats'],
    'historique_paiements'     => ['fa-receipt',              'Historique Paiements'],
    'verification_annuelle'    => ['fa-scale-balanced',       'Vérification Annuelle'],
    'traitement_anomalies'     => ['fa-triangle-exclamation', 'Traitement des Anomalies'],
    'tableau_bord'             => ['fa-chart-line',           'Tableau de bord'],
    'reclamations_fournisseur' => ['fa-file-invoice-dollar',  'Gestion des Réclamations'],
    'quartiers_tarifs'         => ['fa-tags',                 'Quartiers & Tarifs'],
];
$pageMenu = $_GET['page'] ?? 'gestion_client';
$pageMenu = ['fournisseur' => 'gestion_client', 'historique_compteurs' => 'contrats'][$pageMenu] ?? $pageMenu;
?>
<style>
.sidebar { min-height: 0; }
.sidebar-menu { min-height: 0; overflow-y: auto; padding-top: 10px !important; padding-bottom: 10px !important; gap: 2px !important; }
.sidebar-menu li a { padding-top: 10px !important; padding-bottom: 10px !important; }
.sidebar-footer { flex-shrink: 0; padding-top: 12px !important; padding-bottom: 12px !important; gap: 8px !important; }
.sidebar-card-box { flex-shrink: 0; }
</style>
<ul class="sidebar-menu">
<?php foreach ($liensMenu as $cle => [$icone, $libelle]): ?>
    <li<?= $cle === $pageMenu ? ' class="active"' : '' ?>><a href="index.php?page=<?= $cle ?>"><i class="fa-solid <?= $icone ?>"></i> <?= $libelle ?></a></li>
<?php endforeach; ?>
</ul>