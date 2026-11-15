<?php
require_once __DIR__ . '/../../traitement/auth.php';
require_once __DIR__ . '/../../BD/Facture.php';
require_once __DIR__ . '/../../BD/Quartier.php';   // lecture seule : liste des quartiers / types


exigerRole('fournisseur');


$filtres = [
    'quartier' => (int) ($_GET['quartier'] ?? 0),
    'type'     => (int) ($_GET['type'] ?? 0),
    'ville'    => trim($_GET['ville'] ?? ''),
];
$paiements = obtenirHistoriquePaiementsFournisseur($filtres);

$listeQuartiers = getQuartiers();
$listeTypes     = getTypesActivite();
$listeVilles    = getVillesQuartiers();

$totalEncaisse = 0;
$totalHT = 0;
$totalTVA = 0;
$nbTransactions = count($paiements);

foreach ($paiements as $p) {
    $totalEncaisse += $p['Montant_TTC'];
    $totalHT  += $p['Montant_HT'];
    $totalTVA += $p['TVA'];
}
$filtreActif = $filtres['quartier'] || $filtres['type'] || $filtres['ville'] !== '';

// ---- Pagination (affichage seulement : les totaux ci-dessus restent calculés sur TOUS les résultats filtrés) ----
$parPage       = 10;
$totalPages    = max(1, (int) ceil($nbTransactions / $parPage));
$pageCourante  = min(max(1, (int) ($_GET['p'] ?? 1)), $totalPages);
$paiementsPage = array_slice($paiements, ($pageCourante - 1) * $parPage, $parPage);
$debutAff      = $nbTransactions ? ($pageCourante - 1) * $parPage + 1 : 0;
$finAff        = min($pageCourante * $parPage, $nbTransactions);
// Lien de page : conserve les filtres actifs
$urlPage = function (int $n) use ($filtres) {
    return 'index.php?' . http_build_query(array_filter([
        'page'     => 'historique_paiements',
        'ville'    => $filtres['ville'],
        'quartier' => $filtres['quartier'] ?: null,
        'type'     => $filtres['type'] ?: null,
        'p'        => $n > 1 ? $n : null,
    ], fn($v) => $v !== null && $v !== ''));
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fluxio - Historique des Paiements</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        .sidebar { width: 270px; background-color: #0F2137; color: #fff; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 25px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .logo-icon { width: 38px; height: 38px; background: #1890FF; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; }
        .sidebar-brand .brand-text { font-size: 16px; font-weight: bold; color: #ffffff; line-height: 1.2; }
        .sidebar-brand .brand-sub { font-size: 12px; font-weight: normal; color: #94A3B8; }

        .sidebar-menu { list-style: none; padding: 20px 15px; flex: 1; display: flex; flex-direction: column; gap: 5px; }
        .sidebar-menu li a { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: #A0AEC0; text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 8px; transition: 0.2s; }
        .sidebar-menu li.active a, .sidebar-menu li a:hover { background-color: #1A3353; color: #fff; border-left: 4px solid #1890FF; font-weight: 600; }
        
        .sidebar-card-box { margin: 15px; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; display: flex; align-items: center; gap: 10px; }
        .sidebar-card-box i { color: #1890FF; font-size: 18px; }
        .sidebar-card-box .box-title { font-size: 13px; font-weight: 600; color: #ffffff; }
        .sidebar-card-box .box-sub { font-size: 11px; color: #94A3B8; }

        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; gap: 15px; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 36px; height: 36px; background: #1A3353; color: #38BDF8; font-weight: 600; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .user-info .user-name { font-size: 13px; font-weight: 600; color: #ffffff; }
        .user-info .user-role { font-size: 11px; color: #94A3B8; }
        .logout-btn { display: flex; align-items: center; gap: 8px; color: #F87171; text-decoration: none; font-size: 13px; font-weight: 500; padding: 5px 0; transition: 0.2s; }
        .logout-btn:hover { color: #EF4444; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background-color: #F8FAFC; }
        .header { background: #fff; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; }
        .header h1 { font-size: 18px; color: #1E293B; font-weight: 600; }
        
        .container { padding: 30px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .card-kpi { background: #ffffff; padding: 20px; border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .card-kpi .kpi-label { font-size: 13px; color: #64748B; font-weight: 500; margin-bottom: 5px; }
        .card-kpi .kpi-value { font-size: 22px; font-weight: 700; color: #0F2137; }
        .card-kpi .kpi-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .icon-blue { background: #E0F2FE; color: #0284C7; }
        .icon-green { background: #DCFCE7; color: #16A34A; }

        .table-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #E2E8F0; overflow: hidden; }
        .action-bar { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; }
        .search-box { display: flex; background: #fff; border: 1px solid #CBD5E1; border-radius: 8px; overflow: hidden; width: 350px; }
        .search-box input { border: none; padding: 9px 12px; outline: none; width: 100%; font-size: 13px; }
        .search-box button { background: #fff; border: none; padding: 0 12px; color: #64748B; cursor: pointer; }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #F8FAFC; color: #475569; font-weight: 600; padding: 14px 20px; font-size: 13px; border-bottom: 1px solid #E2E8F0; }
        td { padding: 14px 20px; color: #334155; font-size: 14px; border-bottom: 1px solid #F1F5F9; }
        tr:hover { background-color: #F8FAFC; }

        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-paye { background: #DCFCE7; color: #15803D; }
        .badge-eau { background: #E0F2FE; color: #0369A1; }
        .badge-elec { background: #FEF3C7; color: #B45309; }
            .filtres { display:flex; flex-wrap:wrap; gap:12px; align-items:end; margin-bottom:20px; }
        .filtres label { display:block; font-size:12px; color:#64748B; margin-bottom:4px; font-weight:500; }
        .filtres select { padding:8px 12px; border:1px solid #CBD5E1; border-radius:8px; background:#fff; font-size:13px; min-width:170px; }
        .filtres button, .filtres a.reset { padding:9px 16px; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:13px; text-decoration:none; }
        .filtres button { background:#1890FF; color:#fff; }
        .filtres a.reset { background:#E2E8F0; color:#475569; }
        .badge-type { background:#EDE9FE; color:#6D28D9; }
.pagination { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding:14px 20px; border-top:1px solid #E2E8F0; background:#fff; }
        .pagination .info { font-size:13px; color:#64748B; }
        .pagination .pages { display:flex; gap:6px; flex-wrap:wrap; }
        .pagination .pages a, .pagination .pages span { min-width:34px; height:34px; padding:0 10px; display:flex; align-items:center; justify-content:center; border:1px solid #CBD5E1; border-radius:8px; font-size:13px; font-weight:500; color:#475569; text-decoration:none; background:#fff; }
        .pagination .pages a:hover { background:#F1F5F9; }
        .pagination .pages .current { background:#1890FF; border-color:#1890FF; color:#fff; font-weight:600; }
        .pagination .pages .disabled { color:#CBD5E1; background:#F8FAFC; }
                tfoot td { font-weight:700; background:#F8FAFC; border-top:2px solid #E2E8F0; }
    </style>
</head>
<body>


    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="logo-icon"><i class="fa-solid fa-droplet"></i></div>
            <div>
                <div class="brand-text">Gestion des Factures</div>
                <div class="brand-sub">Eau & Électricité</div>
            </div>
        </div>

        <div class="sidebar-card-box">
            <i class="fa-solid fa-shield-halved"></i>
            <div>
                <div class="box-title">Espace Fournisseur</div>
                <div class="box-sub">Accès administration</div>
            </div>
        </div>

        <?php require __DIR__ . '/menu.php'; ?>

        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar">SF</div>
                <div class="user-info">
                    <div class="user-name">Service Fournisseur</div>
                    <div class="user-role">Administrateur</div>
                </div>
            </div>
            <a href="index.php?page=logout" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Se déconnecter</a>
        </div>
    </div>

    
    <div class="main-content">
        <div class="header">
            <h1>Historique et Journal des Règlements Encaissés</h1>
            <div style="font-weight: 600; color: #64748B; font-size: 13px;">Espace Fournisseur</div>
        </div>

        <div class="container">
        
            <div class="stats-grid">
                <div class="card-kpi">
                    <div>
                        <div class="kpi-label">Nombre de règlements</div>
                        <div class="kpi-value"><?php echo $nbTransactions; ?> transaction(s)</div>
                    </div>
                    <div class="kpi-icon icon-blue">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                </div>

                <div class="card-kpi">
                    <div>
                        <div class="kpi-label">Total Général Encaissé (TTC)</div>
                        <div class="kpi-value" style="color: #16A34A;"><?php echo number_format($totalEncaisse, 2, ',', ' '); ?> DH</div>
                    </div>
                    <div class="kpi-icon icon-green">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>
            </div>

          
            <form method="get" action="index.php" class="filtres">
                <input type="hidden" name="page" value="historique_paiements">
                <div>
                    <label for="ville">Ville</label>
                    <select name="ville" id="ville">
                        <option value="">Toutes</option>
                        <?php foreach ($listeVilles as $v): ?>
                            <option value="<?= htmlspecialchars($v) ?>"<?= $v === $filtres['ville'] ? ' selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="quartier">Quartier</label>
                    <select name="quartier" id="quartier">
                        <option value="0">Tous</option>
                        <?php foreach ($listeQuartiers as $q): ?>
                            <option value="<?= (int) $q['ID_Quartier'] ?>"<?= (int) $q['ID_Quartier'] === $filtres['quartier'] ? ' selected' : '' ?>>
                                <?= htmlspecialchars($q['Nom'] . ' (' . $q['Ville'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="type">Type d'activité</label>
                    <select name="type" id="type">
                        <option value="0">Tous</option>
                        <?php foreach ($listeTypes as $t): ?>
                            <option value="<?= (int) $t['ID_Type_Activite'] ?>"<?= (int) $t['ID_Type_Activite'] === $filtres['type'] ? ' selected' : '' ?>>
                                <?= htmlspecialchars($t['Libelle']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit"><i class="fa-solid fa-filter"></i> Filtrer</button>
                <?php if ($filtreActif): ?><a class="reset" href="index.php?page=historique_paiements">Réinitialiser</a><?php endif; ?>
            </form>

            <div class="table-card">
                <div class="action-bar">
                    <div style="font-size:13px;color:#64748B;font-weight:500;"><?= $filtreActif ? 'Résultats filtrés' : 'Tous les règlements' ?></div>
                    <div style="font-size: 13px; color: #64748B; font-weight: 500;">
                        Statut : <span class="badge badge-paye">Factures réglées</span>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>N° Facture</th>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Contrat / Quartier</th>
                            <th>Type / Période</th>
                            <th>Montant HT</th>
                            <th>TVA (18%)</th>
                            <th>Montant Réglé (TTC)</th>
                            <th>Date Règlement</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paiements)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; color: #94A3B8; padding: 40px;">
                                    <i class="fa-solid fa-inbox" style="font-size: 28px; margin-bottom: 10px; display: block;"></i>
                                    Aucun règlement ne correspond à ces critères.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paiementsPage as $p): ?>
                                <tr>
                                    <td><strong>#FAC-<?php echo htmlspecialchars($p['ID_Facture']); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($p['Nom'] . ' ' . $p['Prenom']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($p['Email']); ?><br>
                                        <small style="color: #64748B;"><?php echo htmlspecialchars($p['Telephone'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <strong>#<?= (int) $p['ID_Contrat'] ?></strong>
                                        <small style="color:#64748B;display:block;"><?= htmlspecialchars($p['Quartier'] . ' · ' . $p['Ville']) ?></small>
                                        <span class="badge badge-type"><?= htmlspecialchars($p['Type_Activite']) ?></span>
                                    </td>
                                    <td>
                                        <?php $isEau = (strtolower($p['Type_Compteur']) === 'eau'); ?>
                                        <span class="badge <?php echo $isEau ? 'badge-eau' : 'badge-elec'; ?>">
                                            <?php echo htmlspecialchars($p['Type_Compteur']); ?>
                                        </span>
                                        <small style="color: #64748B; margin-left: 5px;"><?php echo $p['Mois'] . '/' . $p['Annee']; ?></small>
                                    </td>
                                    <td><?php echo number_format($p['Montant_HT'], 2, ',', ' '); ?> DH</td>
                                    <td><?php echo number_format($p['TVA'], 2, ',', ' '); ?> DH</td>
                                    <td><strong style="color: #16A34A;"><?php echo number_format($p['Montant_TTC'], 2, ',', ' '); ?> DH</strong></td>
                                    <td>
                                        <?php 
                                            echo !empty($p['Date_paiement']) 
                                                ? date('d/m/Y H:i', strtotime($p['Date_paiement'])) 
                                                : '<span style="color: #64748B;">Validé</span>'; 
                                        ?>
                                    </td>
                                    <td><span class="badge badge-paye">Payée</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($paiements)): ?>
                    <tfoot>
                        <tr>
                            <td colspan="5">TOTAUX — toutes pages (<?= $nbTransactions ?> règlement(s))</td>
                            <td><?= number_format($totalHT, 2, ',', ' ') ?> DH</td>
                            <td><?= number_format($totalTVA, 2, ',', ' ') ?> DH</td>
                            <td style="color:#16A34A;"><?= number_format($totalEncaisse, 2, ',', ' ') ?> DH</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div class="info">Affichage de <?= $debutAff ?> à <?= $finAff ?> sur <?= $nbTransactions ?> règlement(s)</div>
                    <div class="pages">
                        <?php if ($pageCourante > 1): ?>
                            <a href="<?= htmlspecialchars($urlPage($pageCourante - 1)) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="fa-solid fa-chevron-left"></i></span>
                        <?php endif; ?>

                        <?php
                        $debutP = max(1, $pageCourante - 2);
                        $finP   = min($totalPages, $pageCourante + 2);
                        if ($debutP > 1): ?>
                            <a href="<?= htmlspecialchars($urlPage(1)) ?>">1</a>
                            <?php if ($debutP > 2): ?><span class="disabled">…</span><?php endif; ?>
                        <?php endif;
                        for ($i = $debutP; $i <= $finP; $i++): ?>
                            <?php if ($i === $pageCourante): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($urlPage($i)) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor;
                        if ($finP < $totalPages): ?>
                            <?php if ($finP < $totalPages - 1): ?><span class="disabled">…</span><?php endif; ?>
                            <a href="<?= htmlspecialchars($urlPage($totalPages)) ?>"><?= $totalPages ?></a>
                        <?php endif; ?>

                        <?php if ($pageCourante < $totalPages): ?>
                            <a href="<?= htmlspecialchars($urlPage($pageCourante + 1)) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="fa-solid fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>