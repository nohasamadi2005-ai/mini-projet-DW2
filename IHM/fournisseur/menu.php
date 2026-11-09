<?php
/* Menu de l'espace fournisseur — UN SEUL fichier, inclus par toutes les pages fournisseur
   à la place de leur <ul class="sidebar-menu"> :   require __DIR__ . '/menu.php';      */
// Pour ajouter une page au menu, ajoutez une ligne dans $liensMenu (rien à changer ailleurs).
$liensMenu = [
    'gestion_client'           => ['fa-users',                'Gestion Clients'],
    'contrats'                 => ['fa-file-signature',       'Contrats'],
    'historique_paiements'     => ['fa-receipt',              'Historique Paiements'],
    'verification_annuelle'    => ['fa-scale-balanced',       'Vérification Annuelle'],
    'traitement_anomalies'     => ['fa-triangle-exclamation', 'Traitement des Anomalies'],
    'reclamations_fournisseur' => ['fa-file-invoice-dollar',  'Gestion des Réclamations'],
    'agents'                   => ['fa-user-tie',             'Agents'],
];
// Pages rattachées à une entrée du menu
$pageMenu = $_GET['page'] ?? 'gestion_client';
$pageMenu = ['fournisseur' => 'gestion_client', 'historique_compteurs' => 'contrats'][$pageMenu] ?? $pageMenu;
?>
<ul class="sidebar-menu">
<?php foreach ($liensMenu as $cle => [$icone, $libelle]): ?>
    <li<?= $cle === $pageMenu ? ' class="active"' : '' ?>><a href="index.php?page=<?= $cle ?>"><i class="fa-solid <?= $icone ?>"></i> <?= $libelle ?></a></li>
<?php endforeach; ?>
</ul>
