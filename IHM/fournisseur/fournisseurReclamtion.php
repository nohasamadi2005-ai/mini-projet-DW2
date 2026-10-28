<?php
require_once __DIR__ . '/../../traitement/auth.php';
exigerRole('fournisseur');
// $d est préparé dans index.php (case 'reclamations_fournisseur')
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}
$c = $d['compteurs'];
$total = array_sum($c);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fluxio - Gestion des Réclamations</title>
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
            <li><a href="index.php?page=historique_paiements"><i class="fa-solid fa-receipt"></i> Historique Paiements</a></li>
            <li><a href="index.php?page=verification_annuelle"><i class="fa-solid fa-scale-balanced"></i> Vérification Annuelle</a></li>
            <li><a href="index.php?page=traitement_anomalies"><i class="fa-solid fa-triangle-exclamation"></i> Traitement des Anomalies</a></li>
            <li class="active"><a href="index.php?page=reclamations_fournisseur"><i class="fa-solid fa-file-invoice-dollar"></i> Gestion Réclamations</a></li>
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
            <h1>Gestion des Réclamations Clients</h1>
            <div style="font-weight: 600; color: #64748B; font-size: 13px;">Espace Fournisseur</div>
        </div>

        <div class="container-fluid">

            <?php if (!empty($d['message'])): ?>
                <div class="alert alert-<?= $d['message']['type'] === 'succes' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                    <?= h($d['message']['texte']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <?php
                $cartes = [
                    ['Ouvertes',  $c['Ouverte'],  'fa-envelope-open-text', '#FEF3C7', '#D97706'],
                    ['En cours',  $c['En cours'], 'fa-spinner',            '#E0F2FE', '#1890FF'],
                    ['Traitées',  $c['Traitee'],  'fa-circle-check',       '#DCFCE7', '#16A34A'],
                    ['Rejetées',  $c['Rejetee'],  'fa-circle-xmark',       '#FEE2E2', '#DC2626'],
                ];
                foreach ($cartes as [$titre, $val, $ico, $bg, $col]): ?>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div>
                            <div class="stat-title"><?= $titre ?></div>
                            <div class="stat-value"><?= (int) $val ?></div>
                        </div>
                        <div class="stat-icon" style="background: <?= $bg ?>; color: <?= $col ?>;">
                            <i class="fa-solid <?= $ico ?>"></i>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="filtres d-flex gap-2 flex-wrap mb-3">
                <a href="index.php?page=reclamations_fournisseur" class="<?= $d['filtre'] === '' ? 'on' : '' ?>">Toutes (<?= $total ?>)</a>
                <a href="index.php?page=reclamations_fournisseur&statut=Ouverte" class="<?= $d['filtre'] === 'Ouverte' ? 'on' : '' ?>">Ouvertes</a>
                <a href="index.php?page=reclamations_fournisseur&statut=En cours" class="<?= $d['filtre'] === 'En cours' ? 'on' : '' ?>">En cours</a>
                <a href="index.php?page=reclamations_fournisseur&statut=Traitee" class="<?= $d['filtre'] === 'Traitee' ? 'on' : '' ?>">Traitées</a>
                <a href="index.php?page=reclamations_fournisseur&statut=Rejetee" class="<?= $d['filtre'] === 'Rejetee' ? 'on' : '' ?>">Rejetées</a>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>N°</th><th>Date</th><th>Client</th><th>Facture</th>
                                <th>Type</th><th>Description</th><th>Statut</th><th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($d['lignes'])): ?>
                            <tr><td colspan="8" class="text-center text-muted py-5">Aucune réclamation.</td></tr>
                        <?php else: foreach ($d['lignes'] as $r): ?>
                            <tr>
                                <td class="fw-bold"><?= h($r['numero']) ?></td>
                                <td><?= h($r['date']) ?></td>
                                <td><?= h($r['client']) ?><br><small class="text-muted"><?= h($r['email']) ?></small></td>
                                <td><?= h($r['facture']) ?><br>
                                    <span class="<?= $r['compteur'] === 'Eau' ? 'badge-eau' : 'badge-elec' ?>"><?= $r['compteur'] === 'Eau' ? 'Eau' : 'Électricité' ?></span></td>
                                <td><?= h($r['type']) ?></td>
                                <td><div class="desc-court" title="<?= h($r['description']) ?>"><?= h($r['description']) ?></div></td>
                                <td><span class="badge-st st-<?= h(str_replace(' ', '', strtolower($r['statutCode']))) ?>"><?= h($r['statut']) ?></span></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm <?= $r['cloturee'] ? 'btn-outline-secondary' : 'btn-primary' ?> btn-ouvrir"
                                        data-bs-toggle="modal" data-bs-target="#modalRec"
                                        data-id="<?= (int) $r['id'] ?>"
                                        data-numero="<?= h($r['numero']) ?>"
                                        data-client="<?= h($r['client']) ?>"
                                        data-contact="<?= h($r['email'] . ' · ' . $r['tel']) ?>"
                                        data-facture="<?= h($r['facture'] . ' — ' . $r['periode'] . ' — ' . $r['ttc'] . ' DH') ?>"
                                        data-type="<?= h($r['type']) ?>"
                                        data-description="<?= h($r['description']) ?>"
                                        data-statut="<?= h($r['statutCode']) ?>"
                                        data-reponse="<?= h($r['reponse']) ?>"
                                        data-datereponse="<?= h($r['dateReponse']) ?>"
                                        data-cloturee="<?= $r['cloturee'] ? '1' : '0' ?>">
                                        <?= $r['cloturee'] ? 'Voir' : 'Traiter' ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="modalRec" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mTitre">Réclamation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="index.php?page=reclamations_fournisseur">
                    <div class="modal-body">
                        <input type="hidden" name="id_reclamation" id="mId">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><small class="text-muted d-block">Client</small><b id="mClient"></b><br><small id="mContact" class="text-muted"></small></div>
                            <div class="col-md-6"><small class="text-muted d-block">Facture concernée</small><b id="mFacture"></b></div>
                            <div class="col-md-12"><small class="text-muted d-block">Type</small><b id="mType"></b></div>
                        </div>
                        <small class="text-muted">Description du client</small>
                        <div class="bloc-desc mb-3" id="mDesc"></div>

                        <div id="zoneTraitement">
                            <label class="form-label fw-semibold" for="mStatut">Nouveau statut</label>
                            <select name="statut" id="mStatut" class="form-select mb-3">
                                <option value="En cours">En cours de traitement</option>
                                <option value="Traitee">Traitée (clôturer)</option>
                                <option value="Rejetee">Rejetée (clôturer)</option>
                            </select>
                            <label class="form-label fw-semibold" for="mReponse">Réponse au client</label>
                            <textarea name="reponse" id="mReponse" class="form-control" rows="4" maxlength="1000"
                                      placeholder="Obligatoire pour clôturer (Traitée ou Rejetée)"></textarea>
                            <small class="text-muted">Le client sera notifié automatiquement.</small>
                        </div>

                        <div id="zoneLecture" style="display:none;">
                            <small class="text-muted">Réponse envoyée <span id="mDateRep"></span></small>
                            <div class="bloc-desc" id="mRepLue"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn btn-primary" id="mEnvoyer">Enregistrer et notifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.btn-ouvrir').forEach(btn => {
            btn.addEventListener('click', () => {
                const d = btn.dataset;
                document.getElementById('mTitre').textContent   = 'Réclamation ' + d.numero;
                document.getElementById('mId').value            = d.id;
                document.getElementById('mClient').textContent  = d.client;
                document.getElementById('mContact').textContent = d.contact;
                document.getElementById('mFacture').textContent = d.facture;
                document.getElementById('mType').textContent    = d.type;
                document.getElementById('mDesc').textContent    = d.description;

                const cloturee = d.cloturee === '1';
                document.getElementById('zoneTraitement').style.display = cloturee ? 'none' : 'block';
                document.getElementById('zoneLecture').style.display    = cloturee ? 'block' : 'none';
                document.getElementById('mEnvoyer').style.display       = cloturee ? 'none' : 'inline-block';
                document.getElementById('mReponse').value = d.reponse || '';
                document.getElementById('mStatut').value  = 'En cours';
                document.getElementById('mRepLue').textContent = d.reponse || '—';
                document.getElementById('mDateRep').textContent = d.datereponse ? '— ' + d.datereponse : '';
            });
        });
    </script>
</body>
</html>