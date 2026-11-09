<?php
require_once __DIR__ . '/../../traitement/auth.php';
require_once __DIR__ . '/../../BD/Statistiques.php';

exigerRole('fournisseur');

$annees = statAnnees();
$annee  = isset($_GET['annee']) ? (int) $_GET['annee'] : ($annees[0] ?? 0);   // 0 = toutes les années

$caTypes   = statCAParTypeActivite($annee);
$consoQuar = statConsoParQuartier($annee);
$impQuar   = statImpayesParQuartier($annee);

$totFacture = $totEncaisse = $totImpaye = 0.0;
foreach ($caTypes as $t) {
    $totFacture  += (float) $t['Facture'];
    $totEncaisse += (float) $t['Encaisse'];
    $totImpaye   += (float) $t['Impaye'];
}
$maxCA  = max(array_map(fn($t) => (float) $t['Encaisse'], $caTypes) ?: [0]);
$maxImp = max(array_map(fn($t) => (float) $t['Total'], $impQuar) ?: [0]);
$maxEau = max(array_map(fn($t) => (float) $t['Conso_Eau'], $consoQuar) ?: [0]);
$maxEl  = max(array_map(fn($t) => (float) $t['Conso_Elec'], $consoQuar) ?: [0]);

if (!function_exists('h'))   { function h($s)   { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('dh'))  { function dh($v)  { return number_format((float) $v, 2, ',', ' ') . ' DH'; } }
if (!function_exists('nb'))  { function nb($v)  { return number_format((float) $v, 1, ',', ' '); } }
if (!function_exists('pct')) { function pct($v, $max) { return $max > 0 ? round(100 * (float) $v / $max) : 0; } }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fluxio - Tableau de bord</title>
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
    
        .filtre { display:flex; gap:10px; align-items:center; margin-bottom:20px; font-size:13px; color:#475569; }
        .filtre select { padding:8px 12px; border:1px solid #CBD5E1; border-radius:8px; background:#fff; font-size:13px; }
        .filtre button { padding:8px 16px; border:none; border-radius:8px; background:#1890FF; color:#fff; font-weight:600; cursor:pointer; font-size:13px; }
        .grid2 { display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:25px; }
        @media (max-width:1100px){ .grid2 { grid-template-columns:1fr; } }
        .card-title { padding:15px 20px; font-weight:600; color:#1E293B; border-bottom:1px solid #E2E8F0; font-size:14px; }
        .bar { background:#F1F5F9; border-radius:6px; height:8px; margin-top:6px; overflow:hidden; }
        .bar > span { display:block; height:100%; background:#1890FF; }
        .bar.rouge > span { background:#EF4444; }
        .badge-type { background:#EDE9FE; color:#6D28D9; }
        .num { text-align:right; white-space:nowrap; }
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
            <h1>Tableau de bord : chiffre d'affaires, consommation et impayés</h1>
            <div style="font-weight: 600; color: #64748B; font-size: 13px;">Espace Fournisseur</div>
        </div>

        <div class="container">

            <form method="get" action="index.php" class="filtre">
                <input type="hidden" name="page" value="tableau_bord">
                <label for="annee">Année :</label>
                <select name="annee" id="annee">
                    <option value="0"<?= $annee === 0 ? ' selected' : '' ?>>Toutes</option>
                    <?php foreach ($annees as $a): ?>
                        <option value="<?= $a ?>"<?= $a === $annee ? ' selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Afficher</button>
            </form>

            <div class="stats-grid">
                <div class="card-kpi">
                    <div><div class="kpi-label">Total facturé (TTC)</div><div class="kpi-value"><?= dh($totFacture) ?></div></div>
                    <div class="kpi-icon icon-blue"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                </div>
                <div class="card-kpi">
                    <div><div class="kpi-label">Encaissé</div><div class="kpi-value" style="color:#16A34A;"><?= dh($totEncaisse) ?></div></div>
                    <div class="kpi-icon icon-green"><i class="fa-solid fa-wallet"></i></div>
                </div>
                <div class="card-kpi">
                    <div><div class="kpi-label">Impayés</div><div class="kpi-value" style="color:#DC2626;"><?= dh($totImpaye) ?></div></div>
                    <div class="kpi-icon" style="background:#FEE2E2;color:#DC2626;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                </div>
                <div class="card-kpi">
                    <div><div class="kpi-label">Contrats actifs</div><div class="kpi-value"><?= statContratsActifs() ?></div></div>
                    <div class="kpi-icon icon-blue"><i class="fa-solid fa-file-signature"></i></div>
                </div>
            </div>

            <div class="grid2">
                <div class="table-card">
                    <div class="card-title">Chiffre d'affaires par type d'activité</div>
                    <table>
                        <thead><tr><th>Type</th><th class="num">Factures</th><th class="num">Facturé</th><th class="num">Encaissé</th></tr></thead>
                        <tbody>
                        <?php if (!$caTypes): ?>
                            <tr><td colspan="4" style="text-align:center;color:#94A3B8;padding:30px;">Aucune facture.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($caTypes as $t): ?>
                            <tr>
                                <td><span class="badge badge-type"><?= h($t['Type_Activite']) ?></span>
                                    <div class="bar"><span style="width:<?= pct($t['Encaisse'], $maxCA) ?>%"></span></div></td>
                                <td class="num"><?= (int) $t['Nb_Factures'] ?></td>
                                <td class="num"><?= dh($t['Facture']) ?></td>
                                <td class="num"><strong style="color:#16A34A;"><?= dh($t['Encaisse']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-card">
                    <div class="card-title">Impayés par quartier</div>
                    <table>
                        <thead><tr><th>Quartier</th><th class="num">Factures</th><th class="num">Total impayé</th></tr></thead>
                        <tbody>
                        <?php if (!$impQuar): ?>
                            <tr><td colspan="3" style="text-align:center;color:#94A3B8;padding:30px;">Aucun impayé.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($impQuar as $q): ?>
                            <tr>
                                <td><strong><?= h($q['Quartier']) ?></strong> <small style="color:#64748B;"><?= h($q['Ville']) ?> · <?= h($q['Type_Activite']) ?></small>
                                    <div class="bar rouge"><span style="width:<?= pct($q['Total'], $maxImp) ?>%"></span></div></td>
                                <td class="num"><?= (int) $q['Nb_Factures'] ?></td>
                                <td class="num"><strong style="color:#DC2626;"><?= dh($q['Total']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="table-card">
                <div class="card-title">Consommation par quartier (relevés vérifiés)</div>
                <table>
                    <thead><tr><th>Quartier</th><th>Ville</th><th>Type</th><th class="num">Eau (m³)</th><th class="num">Électricité (kWh)</th></tr></thead>
                    <tbody>
                    <?php if (!$consoQuar): ?>
                        <tr><td colspan="5" style="text-align:center;color:#94A3B8;padding:30px;">Aucune consommation.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($consoQuar as $q): ?>
                        <tr>
                            <td><strong><?= h($q['Quartier']) ?></strong></td>
                            <td><?= h($q['Ville']) ?></td>
                            <td><span class="badge badge-type"><?= h($q['Type_Activite']) ?></span></td>
                            <td class="num"><?= nb($q['Conso_Eau']) ?>
                                <div class="bar"><span style="width:<?= pct($q['Conso_Eau'], $maxEau) ?>%"></span></div></td>
                            <td class="num"><?= nb($q['Conso_Elec']) ?>
                                <div class="bar"><span style="width:<?= pct($q['Conso_Elec'], $maxEl) ?>%"></span></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</body>
</html>