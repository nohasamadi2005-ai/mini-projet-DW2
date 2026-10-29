<?php
// Variables fournies par index.php (case 'verification_annuelle') : $d = preparerPageVerificationAnnuelle()
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}
$annee = $d['annee'];
$s     = $d['resume'];
$classes = [
    ST_CONFORME => 'ok', ST_REGULARISER => 'regul', ST_A_VERIFIER => 'verif',
    ST_SANS_SAISIE => 'neutre', ST_SANS_AGENT => 'neutre',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fluxio - Vérification Annuelle</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }

        .sidebar { width: 270px; background-color: #0F2137; color: #fff; display: flex; flex-direction: column; flex: none; }
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
        .logout-btn { display: flex; align-items: center; gap: 8px; color: #F87171; text-decoration: none; font-size: 13px; font-weight: 500; padding: 5px 0; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background-color: #F8FAFC; }
        .header { background: #fff; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; }
        .header h1 { font-size: 18px; color: #1E293B; font-weight: 600; }
        .container { padding: 30px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 22px; }
        .card-kpi { background: #fff; padding: 18px; border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card-kpi .kpi-label { font-size: 12px; color: #64748B; font-weight: 500; margin-bottom: 5px; }
        .card-kpi .kpi-value { font-size: 22px; font-weight: 700; color: #0F2137; }
        .card-kpi .kpi-sub { font-size: 12px; color: #64748B; margin-top: 2px; }

        .table-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #E2E8F0; overflow-x: auto; }
        .action-bar { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-bottom: 1px solid #E2E8F0; }
        .action-bar form { display: flex; align-items: center; gap: 8px; }
        select { border: 1px solid #CBD5E1; border-radius: 8px; padding: 8px 10px; font-size: 13px; background: #fff; }
        .btn { border: none; border-radius: 8px; padding: 9px 14px; font-size: 13px; font-weight: 600; cursor: pointer; color: #fff; background: #1890FF; }
        .btn:hover { background: #0B7AE0; }
        .btn.vert { background: #16A34A; } .btn.vert:hover { background: #15803D; }
        .btn.petit { padding: 6px 10px; font-size: 12px; }
        .btn[disabled] { background: #CBD5E1; cursor: default; }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #F8FAFC; color: #475569; font-weight: 600; padding: 12px 14px; font-size: 12px; border-bottom: 1px solid #E2E8F0; white-space: nowrap; }
        td { padding: 12px 14px; color: #334155; font-size: 13px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        td small { display: block; color: #94A3B8; font-size: 11px; }
        tr.fin-client td { border-bottom: 2px solid #E2E8F0; }

        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; white-space: nowrap; }
        .badge.ok { background: #DCFCE7; color: #15803D; }
        .badge.regul { background: #FEF3C7; color: #B45309; }
        .badge.verif { background: #FEE2E2; color: #B91C1C; }
        .badge.neutre { background: #E2E8F0; color: #475569; }
        .badge-eau { background: #E0F2FE; color: #0369A1; }
        .badge-elec { background: #FEF3C7; color: #B45309; }
        .neg { color: #B91C1C; font-weight: 600; } .pos { color: #B45309; font-weight: 600; } .zero { color: #15803D; font-weight: 600; }

        .flash { padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-size: 13px; font-weight: 500; }
        .flash.succes { background: #DCFCE7; color: #15803D; } .flash.erreur { background: #FEE2E2; color: #B91C1C; }
        .regles { margin-top: 20px; background: #fff; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px 20px; font-size: 13px; color: #475569; line-height: 1.7; }
        .regles b { color: #0F2137; }
        .vide { text-align: center; padding: 50px 20px; color: #64748B; }
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
            <li class="active"><a href="index.php?page=verification_annuelle"><i class="fa-solid fa-scale-balanced"></i> Vérification Annuelle</a></li>
            <li><a href="#"><i class="fa-solid fa-triangle-exclamation"></i> Traitement Anomalies</a></li>
            <li><a href="index.php?page=reclamations_fournisseur"><i class="fa-solid fa-file-invoice-dollar"></i>Gestion des Réclamations</a></li>
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
            <h1>Vérification annuelle des consommations</h1>
            <div style="font-weight: 600; color: #64748B; font-size: 13px;">Espace Fournisseur</div>
        </div>

        <div class="container">

            <?php if ($d['flash']): ?>
                <div class="flash <?= h($d['flash']['type']) ?>"><?= h($d['flash']['texte']) ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="card-kpi">
                    <div class="kpi-label">Clients dans la vérification <?= $annee ?></div>
                    <div class="kpi-value"><?= $s['total'] ?></div>
                    <div class="kpi-sub"><?= $s[ST_CONFORME] ?> conforme(s)</div>
                </div>
                <div class="card-kpi">
                    <div class="kpi-label">À régulariser</div>
                    <div class="kpi-value" style="color:#B45309;"><?= vaNb($s['regul']) ?> DH</div>
                    <div class="kpi-sub"><?= $s[ST_REGULARISER] ?> client(s)</div>
                </div>
                <div class="card-kpi">
                    <div class="kpi-label">À vérifier (contrôle manuel)</div>
                    <div class="kpi-value" style="color:#B91C1C;"><?= $s[ST_A_VERIFIER] ?></div>
                    <div class="kpi-sub"><?= $s[ST_SANS_SAISIE] + $s[ST_SANS_AGENT] ?> donnée(s) manquante(s)</div>
                </div>
                <div class="card-kpi">
                    <div class="kpi-label">Factures <?= $annee ?> impayées</div>
                    <div class="kpi-value"><?= vaNb($s['impaye']) ?> DH</div>
                    <div class="kpi-sub"><?= $s['anotifier'] ?> client(s) à notifier</div>
                </div>
            </div>

            <div class="table-card">
                <div class="action-bar">
                    <form method="get" action="index.php">
                        <input type="hidden" name="page" value="verification_annuelle">
                        <label for="annee" style="font-size:13px;color:#64748B;font-weight:500;">Année</label>
                        <select name="annee" id="annee" onchange="this.form.submit()">
                            <?php foreach ($d['annees'] as $a): ?>
                                <option value="<?= (int) $a ?>" <?= $a === $annee ? 'selected' : '' ?>><?= (int) $a ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <div style="display:flex;gap:8px;">
                        <form method="post" action="index.php?page=verification_annuelle">
                            <input type="hidden" name="annee" value="<?= $annee ?>">
                            <button class="btn vert" name="action" value="notifier_tous" type="submit" <?= $s['anotifier'] ? '' : 'disabled' ?>>
                                <i class="fa-solid fa-bell"></i> Notifier tous (<?= $s['anotifier'] ?>)
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (!$d['entrees']): ?>
                    <div class="vide">
                        <b>Aucune donnée pour <?= $annee ?></b><br>
                        Importez d'abord le fichier Consommation_Annuelle.txt (espace agent).
                    </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Client</th><th>Type</th>
                            <th class="num">Conso client</th><th class="num">Conso agent</th><th class="num">Écart</th>
                            <th class="num">Montant client</th><th class="num">Montant agent</th><th class="num">Régularisation</th>
                            <th>Décision</th><th>Notification</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($d['entrees'] as $e):
                        $n = count($e['types']); $i = 0;
                        $impTotal = array_sum(array_column($e['impayes'], 'total'));
                        $impNb    = array_sum(array_column($e['impayes'], 'nb'));
                        foreach ($e['types'] as $type => $r): $i++; ?>
                        <tr class="<?= $i === $n ? 'fin-client' : '' ?>">
                            <?php if ($i === 1): ?>
                            <td rowspan="<?= $n ?>">
                                <b><?= h($e['nom']) ?></b>
                                <small>ID <?= (int) $e['id'] ?></small>
                            </td>
                            <?php endif; ?>
                            <td><span class="badge <?= $type === 'Eau' ? 'badge-eau' : 'badge-elec' ?>"><?= h(labelType($type)) ?></span></td>
                            <td class="num">
                                <?= $r['consoClient'] === null ? '—' : vaNb($r['consoClient']) . ' ' . uniteType($type) ?>
                                <?php if ($r['consoClient'] !== null && $r['dernierMois'] < 12): ?>
                                    <small>jusqu'à <?= h(mb_strtolower(nomMois($r['dernierMois']))) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= $r['consoAgent'] === null ? '—' : vaNb($r['consoAgent']) . ' ' . uniteType($type) ?></td>
                            <td class="num">
                                <?php if ($r['ecart'] === null): ?>—
                                <?php else: $cl = abs($r['pct']) <= TOLERANCE_ECART ? 'zero' : ($r['ecart'] < 0 ? 'neg' : 'pos'); ?>
                                    <span class="<?= $cl ?>"><?= ($r['ecart'] > 0 ? '+' : '') . vaNb($r['ecart']) ?></span>
                                    <small><?= ($r['pct'] > 0 ? '+' : '') . vaNb($r['pct'], 1) ?> %</small>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= $r['montantClient'] === null ? '—' : vaNb($r['montantClient']) . ' DH' ?></td>
                            <td class="num"><?= $r['montantAgent'] === null ? '—' : vaNb($r['montantAgent']) . ' DH' ?></td>
                            <td class="num"><?= $r['regul'] > 0 ? '<b>' . vaNb($r['regul']) . ' DH</b>' : '—' ?></td>
                            <td>
                                <span class="badge <?= $classes[$r['statut']] ?>" title="<?= h($r['raison']) ?>"><?= h(libelleStatut($r['statut'])) ?></span>
                                <small><?= h($r['raison']) ?></small>
                            </td>
                            <?php if ($i === 1): ?>
                            <td rowspan="<?= $n ?>">
                                <?php if ($impNb): ?><small style="margin-bottom:6px;color:#B91C1C;"><?= $impNb ?> facture(s) impayée(s) : <?= vaNb($impTotal) ?> DH</small><?php endif; ?>
                                <?php if (!notifiable($e)): ?>
                                    <small><?= $e['inconnu'] ? 'Client introuvable dans la base' : 'En attente du relevé agent' ?></small>
                                <?php else: ?>
                                    <?php if ($e['notifie']): ?>
                                        <small style="color:#15803D;margin-bottom:6px;">Notifié le <?= h(date('d/m/Y', strtotime($e['notifie']))) ?></small>
                                    <?php endif; ?>
                                    <form method="post" action="index.php?page=verification_annuelle">
                                        <input type="hidden" name="annee" value="<?= $annee ?>">
                                        <input type="hidden" name="id_client" value="<?= (int) $e['id'] ?>">
                                        <button class="btn petit" name="action" value="notifier" type="submit">
                                            <i class="fa-solid fa-bell"></i> <?= $e['notifie'] ? 'Renvoyer' : 'Notifier' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <div class="regles">
                <b>Règle de calcul.</b> Conso client = dernier index vérifié de <?= $annee ?> − dernier index de <?= $annee - 1 ?> ;
                écart = conso client − conso agent (fichier annuel).<br>
                <b>Décision.</b>
                Écart ≤ <?= TOLERANCE_ECART ?> % → <b>Conforme</b> : le client est invité à payer ses factures impayées.
                Client &lt; agent (jusqu'à <?= SEUIL_CONTROLE ?> %) → <b>À régulariser</b> : montant estimé de la consommation non facturée.
                Client &gt; agent, ou écart &gt; <?= SEUIL_CONTROLE ?> %, ou anomalies non traitées → <b>À vérifier</b> : contrôle manuel (nouveau relevé de l'agent, réclamation).<br>
                Les montants sont estimés avec le barème TTC (TVA 18 %), conso répartie sur 12 mois.
            </div>
        </div>
    </div>
</body>
</html>