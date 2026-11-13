<?php
require_once __DIR__ . '/../../traitement/auth.php';
exigerRole('fournisseur');
require_once __DIR__ . '/../../BD/Anomalie.php';

$anomalies = getAnomaliesEnAttente();

$fmt = fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');

$totalAnomalies = count($anomalies);
$nbIndexNegatifs = 0;
$nbSurconso = 0;

foreach ($anomalies as $a) {
    if (($a['Type_Anomalie'] ?? '') === 'Surconsommation') {
        $nbSurconso++;
    } else {
        $nbIndexNegatifs++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fluxio - Traitement des Anomalies</title>
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
        .badge-saisie-fournisseur { background-color: #E0F2FE; color: #0369A1; font-weight: 600; padding: 4px 10px; border-radius: 12px; font-size: 12px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="logo-icon"><i class="fa-solid fa-droplet"></i></div>
            <div>
                <div class="brand-text">Fluxio</div>
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
            <h1>Traitement et Validation des Anomalies de Consommation</h1>
            <div style="font-weight: 600; color: #64748B; font-size: 13px;">Espace Fournisseur</div>
        </div>

        <div class="container-fluid">
            
            <?php if (!empty($_SESSION['message_succes'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['message_succes']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message_succes']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['message_erreur'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['message_erreur']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message_erreur']); ?>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="stat-card">
                        <div>
                            <div class="stat-title">Anomalies en Attente</div>
                            <div class="stat-value text-primary"><?= $totalAnomalies ?> dossier(s)</div>
                        </div>
                        <div class="stat-icon" style="background: #E0F2FE; color: #1890FF;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stat-card">
                        <div>
                            <div class="stat-title">Répartition par Type</div>
                            <div class="stat-value fs-6 mt-1">
                                <span class="text-danger font-weight-bold me-3"><i class="fa-solid fa-arrow-down"></i> <?= $nbIndexNegatifs ?> Index Négatifs</span>
                                <span class="text-warning font-weight-bold"><i class="fa-solid fa-bolt"></i> <?= $nbSurconso ?> Surconsommations</span>
                            </div>
                        </div>
                        <div class="stat-icon" style="background: #FEF3C7; color: #D97706;">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0 fs-6 font-weight-bold text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i> File d'attente des vérifications</h5>
                    <span class="badge bg-light text-dark border"><?= $totalAnomalies ?> à traiter</span>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Contact</th>
                                <th>Type / Compteur</th>
                                <th>Quartier / Activité</th>
                                <th>Ancien Index</th>
                                <th>Nouveau Saisi</th>
                                <th>Consommation</th>
                                <th>Nature d'Anomalie</th>
                                <th>Photo</th>
                                <th class="text-end pe-4">Action Fournisseur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($anomalies)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-circle-check fa-3x mb-3 text-success"></i>
                                        <p class="fs-6 mb-0">Aucune anomalie en attente de traitement.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($anomalies as $a): ?>
                                    <?php 
                                        $nbRefus = intval($a['Nb_Refus'] ?? 0);
                                        $peutCorrigerDirectement = (($a['Statut_Traitement'] ?? '') === 'Saisie_Fournisseur');
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars(($a['Nom'] ?? '') . ' ' . ($a['Prenom'] ?? '')) ?></strong></td>
                                        <td><small><?= htmlspecialchars($a['Email'] ?? '') ?></small></td>
                                        <td>
                                            <?php if (strtolower($a['Type_Compteur'] ?? '') === 'eau'): ?>
                                                <span class="badge-eau">Eau</span>
                                            <?php else: ?>
                                                <span class="badge-elec">Électricité</span>
                                            <?php endif; ?>
                                            <br><small class="text-muted">#<?= htmlspecialchars($a['ID_Compteur'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($a['Quartier'])): ?>
                                                <strong><?= htmlspecialchars($a['Quartier']) ?></strong>
                                                <small class="text-muted d-block"><?= htmlspecialchars($a['Ville'] . ' · contrat #' . $a['ID_Contrat']) ?></small>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($a['Type_Activite']) ?></span>
                                            <?php else: ?>
                                                <small class="text-muted">Aucun contrat trouvé</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($a['Index_Precedent'] ?? '0') ?></td>
                                        <td><strong class="text-primary"><?= htmlspecialchars($a['Index_Actuel'] ?? '0') ?></strong></td>
                                        <td>
                                            <strong><?= $fmt($a['Consommation'] ?? 0) ?> <?= htmlspecialchars($a['Unite'] ?? '') ?></strong>
                                            <small class="text-muted d-block">seuil <?= htmlspecialchars($a['Type_Activite'] ?? '') ?> <?= $fmt($a['Seuil'] ?? 0) ?> <?= htmlspecialchars($a['Unite'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <?php if ($peutCorrigerDirectement): ?>
                                                <span class="badge-saisie-fournisseur"><i class="fa-solid fa-user-pen me-1"></i> Correction Fournisseur</span>
                                            <?php elseif (($a['Type_Anomalie'] ?? '') === 'Surconsommation'): ?>
                                                <span class="badge-anomalie-sur"><i class="fa-solid fa-bolt me-1"></i> Surconsommation</span>
                                            <?php else: ?>
                                                <span class="badge-anomalie-neg"><i class="fa-solid fa-arrow-down me-1"></i> Index Négatif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($a['Photo_Actuelle'])): ?>
                                                <a href="<?= htmlspecialchars($a['Photo_Actuelle']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fa-solid fa-image"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted"><em>Aucune</em></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ($peutCorrigerDirectement): ?>
                                                <button type="button" class="btn btn-sm btn-primary font-weight-bold" data-bs-toggle="modal" data-bs-target="#modalCorrection<?= $a['ID_Consommation_Mensuelle'] ?>">
                                                    <i class="fa-solid fa-pen-to-square me-1"></i> Corriger & Facturer
                                                </button>

                                                <div class="modal fade text-start" id="modalCorrection<?= $a['ID_Consommation_Mensuelle'] ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <form method="POST" action="index.php?page=traiter_anomalie">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title fs-6 font-weight-bold"><i class="fa-solid fa-magnifying-glass me-2 text-primary"></i>Correction d'index par le fournisseur</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body row">
                                                                    <input type="hidden" name="id_consommation" value="<?= $a['ID_Consommation_Mensuelle'] ?>">
                                                                    <input type="hidden" name="id_client" value="<?= $a['ID_Client'] ?>">
                                                                    <input type="hidden" name="action" value="corriger_saisie">

                                                                    <div class="col-md-6 text-center border-end">
                                                                        <label class="form-label font-weight-bold d-block text-start mb-2">Photo de compteur transmise :</label>
                                                                        <?php if (!empty($a['Photo_Actuelle'])): ?>
                                                                            <img src="<?= htmlspecialchars($a['Photo_Actuelle']) ?>" class="img-fluid rounded border shadow-sm" style="max-height: 240px; object-fit: contain;">
                                                                            <div class="mt-2">
                                                                                <a href="<?= htmlspecialchars($a['Photo_Actuelle']) ?>" target="_blank" class="btn btn-sm btn-link text-decoration-none"><i class="fa-solid fa-up-right-from-square me-1"></i>Ouvrir en grand</a>
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <div class="p-4 bg-light rounded text-muted"><em>Aucune photo disponible</em></div>
                                                                        <?php endif; ?>
                                                                    </div>

                                                                    <div class="col-md-6 ps-4">
                                                                        <div class="mb-3">
                                                                            <span class="text-muted small d-block">Client :</span>
                                                                            <strong><?= htmlspecialchars(($a['Nom'] ?? '') . ' ' . ($a['Prenom'] ?? '')) ?></strong>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <span class="text-muted small d-block">Ancien Index relevé :</span>
                                                                            <span class="badge bg-secondary"><?= htmlspecialchars($a['Index_Precedent'] ?? '0') ?></span>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <span class="text-muted small d-block">Dernier Index erroné saisi par le client :</span>
                                                                            <span class="text-danger font-weight-bold"><?= htmlspecialchars($a['Index_Actuel'] ?? '0') ?></span>
                                                                        </div>
                                                                        
                                                                        <div class="mb-3 mt-4">
                                                                            <label for="index_corrige" class="form-label font-weight-bold text-dark">Nouvel Index lisible sur la photo :</label>
                                                                            <input type="number" step="0.01" name="index_corrige" class="form-control form-control-lg border-primary" required placeholder="Ex: 120.00">
                                                                            <small class="text-muted">Saisissez la valeur exacte lue sur la photo.</small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                    <button type="submit" class="btn btn-sm btn-success font-weight-bold"><i class="fa-solid fa-check me-1"></i> Valider & Générer Facture</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                            <?php elseif (($a['Type_Anomalie'] ?? '') === 'Surconsommation'): ?>
                                                <form method="POST" action="index.php?page=traiter_anomalie" class="d-inline">
                                                    <input type="hidden" name="id_consommation" value="<?= $a['ID_Consommation_Mensuelle'] ?>">
                                                    <input type="hidden" name="id_client" value="<?= $a['ID_Client'] ?>">
                                                    <input type="hidden" name="action" value="traiter_surconsommation">
                                                    <button type="submit" class="btn btn-sm btn-warning font-weight-bold">
                                                        <i class="fa-solid fa-bell me-1"></i> Alerter le Client
                                                    </button>
                                                </form>

                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-danger font-weight-bold" data-bs-toggle="modal" data-bs-target="#modalResaisie<?= $a['ID_Consommation_Mensuelle'] ?>">
                                                    <i class="fa-solid fa-rotate-left me-1"></i> Demander Resaisie
                                                </button>

                                                <div class="modal fade text-start" id="modalResaisie<?= $a['ID_Consommation_Mensuelle'] ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form method="POST" action="index.php?page=traiter_anomalie">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title fs-6 font-weight-bold">Demande de resaisie - Client #<?= $a['ID_Client'] ?></h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="id_consommation" value="<?= $a['ID_Consommation_Mensuelle'] ?>">
                                                                    <input type="hidden" name="id_client" value="<?= $a['ID_Client'] ?>">
                                                                    <input type="hidden" name="action" value="demander_resaisie">
                                                                    
                                                                    <p class="mb-2">Client : <strong><?= htmlspecialchars(($a['Nom'] ?? '') . ' ' . ($a['Prenom'] ?? '')) ?></strong></p>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="motif" class="form-label font-weight-bold">Motif du refus :</label>
                                                                        <select name="motif" class="form-select">
                                                                            <option value="Index inférieur à l'ancien index">Index inférieur à l'ancien index</option>
                                                                            <option value="Photo du compteur floue ou illisible">Photo du compteur floue ou illisible</option>
                                                                            <option value="Incohérence des chiffres">Incohérence des chiffres</option>
                                                                        </select>
                                                                    </div>
                                                                    <p class="small text-muted mb-0">
                                                                        Le client dispose d'une seule resaisie : en cas de nouvel échec, vous corrigerez l'index vous-même.
                                                                    </p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                    <button type="submit" class="btn btn-sm btn-danger">Envoyer la notification</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>