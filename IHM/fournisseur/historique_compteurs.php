<?php
require_once __DIR__ . '/../../traitement/auth.php';
exigerRole('fournisseur');
// $d est préparé dans index.php (case 'historique_compteurs')
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fluxio - Historique des compteurs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background-color: #F8FAFC; }
        .header { background: #fff; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; }
        .header h1 { font-size: 18px; color: #1E293B; font-weight: 600; }
        .container-fluid { padding: 30px; }

        .stat-card { background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .stat-card .stat-title { font-size: 13px; color: #64748B; font-weight: 500; margin-bottom: 5px; }
        .stat-card .stat-value { font-size: 22px; color: #0F2137; font-weight: 700; }
        .stat-card .stat-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }

        .table-card { background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; overflow: hidden; }
        .table th { background: #F8FAFC; color: #475569; font-weight: 600; font-size: 13px; padding: 14px 20px; }
        .table td { padding: 14px 20px; font-size: 14px; color: #334155; vertical-align: middle; }
        
        .badge-eau { background-color: #E0F2FE; color: #0284C7; font-weight: 600; padding: 4px 10px; border-radius: 12px; font-size: 12px; }
        .badge-elec { background-color: #FEF3C7; color: #D97706; font-weight: 600; padding: 4px 10px; border-radius: 12px; font-size: 12px; }
        .badge-anomalie-neg { background-color: #FEE2E2; color: #DC2626; font-weight: 600; padding: 4px 10px; border-radius: 12px; font-size: 12px; }
        .badge-anomalie-sur { background-color: #FEF3C7; color: #B45309; font-weight: 600; padding: 4px 10px; border-radius: 12px; font-size: 12px; }

        .badge-st { font-weight: 600; padding: 4px 10px; border-radius: 12px; font-size: 12px; white-space: nowrap; }
        .st-ouverte { background:#FEF3C7; color:#B45309; }
        .st-encours { background:#E0F2FE; color:#0284C7; }
        .st-traitee { background:#DCFCE7; color:#15803D; }
        .st-rejetee { background:#FEE2E2; color:#DC2626; }
        .filtres a { text-decoration:none; padding:7px 14px; border-radius:20px; font-size:13px; font-weight:600; color:#475569; background:#fff; border:1px solid #E2E8F0; }
        .filtres a.on { background:#1890FF; color:#fff; border-color:#1890FF; }
        .desc-court { max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .bloc-desc { background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:12px; white-space:pre-line; }
        .enc { background:#DCFCE7; color:#15803D; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; }
        .fin { background:#F1F5F9; color:#475569; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; }
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

        <ul class="sidebar-menu">
            <li class="active"><a href="index.php?page=gestion_client"><i class="fa-solid fa-users"></i> Gestion Clients</a></li>
            <li><a href="index.php?page=historique_paiements"><i class="fa-solid fa-receipt"></i> Historique Paiements</a></li>
            <li><a href="index.php?page=verification_annuelle"><i class="fa-solid fa-scale-balanced"></i> Vérification Annuelle</a></li>
            <li><a href="index.php?page=traitement_anomalies"><i class="fa-solid fa-triangle-exclamation"></i> Traitement des Anomalies</a></li>
            <li><a href="index.php?page=reclamations_fournisseur"><i class="fa-solid fa-file-invoice-dollar"></i> Gestion Réclamations</a></li>
        </ul>

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
            <h1>Historique des compteurs</h1>
            <div style="font-weight: 600; color: #64748B; font-size: 13px;">Espace Fournisseur</div>
        </div>

        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <?php if ($d['client']): ?>
                        <h5 class="mb-0">Compteurs de <?= h($d['client']) ?> <small class="text-muted">(client #<?= (int) $d['idClient'] ?>)</small></h5>
                    <?php elseif ($d['idCompteur']): ?>
                        <h5 class="mb-0">Propriétaires successifs du compteur n° <?= (int) $d['idCompteur'] ?></h5>
                    <?php else: ?>
                        <h5 class="mb-0">Toutes les affectations de compteurs</h5>
                    <?php endif; ?>
                    <small class="text-muted">Chaque compteur reste attaché à sa maison : son historique et son index suivent le compteur, pas le client.</small>
                </div>
                <a href="index.php?page=gestion_client" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Gestion Clients</a>
            </div>

            <form method="GET" action="index.php" class="row g-2 mb-3">
                <input type="hidden" name="page" value="historique_compteurs">
                <div class="col-md-3"><input type="number" min="1" name="client" class="form-control" placeholder="ID client" value="<?= $d['idClient'] ?: '' ?>"></div>
                <div class="col-md-3"><input type="number" min="1" name="compteur" class="form-control" placeholder="N° compteur" value="<?= $d['idCompteur'] ?: '' ?>"></div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Filtrer</button>
                    <a class="btn btn-light" href="index.php?page=historique_compteurs">Réinitialiser</a>
                </div>
            </form>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Compteur</th><th>Client</th><th>Du</th><th>Au</th>
                                <th>Index début</th><th>Index fin</th><th>Saisies</th><th>Consommation</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($d['lignes'])): ?>
                            <tr><td colspan="8" class="text-center text-muted py-5">Aucun historique pour ce filtre.</td></tr>
                        <?php else: foreach ($d['lignes'] as $l): ?>
                            <tr>
                                <td class="fw-bold">n° <?= (int) $l['compteur'] ?><br>
                                    <span class="<?= $l['type'] === 'Eau' ? 'badge-eau' : 'badge-elec' ?>"><?= $l['type'] === 'Eau' ? 'Eau' : 'Électricité' ?></span></td>
                                <td><?php if ($l['idClient']): ?>
                                        <a href="index.php?page=historique_compteurs&client=<?= (int) $l['idClient'] ?>" style="text-decoration:none;"><?= h($l['client']) ?></a>
                                        <br><small class="text-muted">#<?= (int) $l['idClient'] ?></small>
                                    <?php else: ?><span class="text-muted"><?= h($l['client']) ?></span><?php endif; ?></td>
                                <td><?= h($l['debut']) ?></td>
                                <td><?= $l['enCours'] ? '<span class="enc">En cours</span>' : h($l['fin']) ?></td>
                                <td><?= h($l['indexDeb']) ?></td>
                                <td><?= $l['enCours'] ? '<span class="fin">—</span>' : h($l['indexFin']) ?></td>
                                <td><?= (int) $l['saisies'] ?></td>
                                <td><?= h($l['conso']) ?> <?= h($l['unite']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>