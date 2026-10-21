<?php
require_once __DIR__ . '/../../traitement/auth.php';
require_once __DIR__ . '/../../BD/Facture.php';


exigerRole('fournisseur');


$paiements = obtenirHistoriquePaiementsFournisseur();


$totalEncaisse = 0;
$nbTransactions = count($paiements);

foreach ($paiements as $p) {
    $totalEncaisse += $p['Montant_TTC'];
}
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
            <li><a href="index.php?page=gestion_client"><i class="fa-solid fa-users"></i> Gestion Clients</a></li>
            <li class="active"><a href="index.php?page=historique_paiements"><i class="fa-solid fa-receipt"></i> Historique Paiements</a></li>
            <li><a href="#"><i class="fa-solid fa-triangle-exclamation"></i> Traitement Anomalies</a></li>
            <li><a href="#"><i class="fa-solid fa-file-invoice-dollar"></i> Gestion Réclamations</a></li>
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

          
            <div class="table-card">
                <div class="action-bar">
                    <div class="search-box">
                        <input type="text" placeholder="Rechercher une facture, un client...">
                        <button><i class="fa-solid fa-search"></i></button>
                    </div>
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
                                <td colspan="9" style="text-align: center; color: #94A3B8; padding: 40px;">
                                    <i class="fa-solid fa-inbox" style="font-size: 28px; margin-bottom: 10px; display: block;"></i>
                                    Aucun règlement enregistré dans la base de données.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paiements as $p): ?>
                                <tr>
                                    <td><strong>#FAC-<?php echo htmlspecialchars($p['ID_Facture']); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($p['Nom'] . ' ' . $p['Prenom']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($p['Email']); ?><br>
                                        <small style="color: #64748B;"><?php echo htmlspecialchars($p['Telephone'] ?? 'N/A'); ?></small>
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
                </table>
            </div>
        </div>
    </div>

</body>
</html>