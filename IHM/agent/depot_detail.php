<?php
$user     = $_SESSION['user'] ?? ['nom' => 'Utilisateur', 'prenom' => 'Agent'];
$initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

$nf = function ($v) {
    return $v === null || $v === '' ? '—' : number_format((float) $v, 2, ',', ' ');
};
$typeCompteur = ['Eau' => 'Eau', 'Electricite' => 'Électricité'];
$badges = [
    'Integre' => ['integre', 'Intégré'],
    'Partiel' => ['partiel', 'Partiel'],
    'Rejete'  => ['rejete',  'Rejeté'],
];
[$classeBadge, $texteBadge] = $badges[$d['statut']] ?? $badges['Integre'];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Détail du dépôt — Fluxio</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; background: #f4f6f9; color: #14213d; display: flex; min-height: 100vh; }

  .sidebar { width: 260px; background: #0a2540; color: #fff; display: flex; flex-direction: column; justify-content: space-between; padding: 24px; position: fixed; height: 100vh; }
  .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
  .logo-icon { background: #38bdf8; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 20px; color: #fff; box-shadow: 0 4px 12px rgba(56,189,248,0.4); }
  .logo-text h2 { margin: 0; font-size: 18px; font-weight: 700; letter-spacing: -0.01em; }
  .logo-text span { font-size: 10px; color: #7fb4e6; letter-spacing: 0.08em; font-weight: 600; text-transform: uppercase; }

  .nav-section-title { font-size: 11px; letter-spacing: 0.1em; color: #7fb4e6; margin-bottom: 12px; font-weight: 600; text-transform: uppercase; }
  .nav-menu { display: flex; flex-direction: column; gap: 8px; flex: 1; }
  .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 8px; color: #b8d3ef; text-decoration: none; font-size: 14px; font-weight: 500; }
  .nav-item:hover, .nav-item.active { background: #38bdf8; color: #fff; }

  .help-box { background: rgba(255,255,255,0.05); padding: 16px; border-radius: 12px; margin-bottom: 20px; font-size: 13px; border: 1px solid rgba(255,255,255,0.08); }
  .help-box p { margin: 6px 0 12px; color: #b8d3ef; font-size: 12px; line-height: 1.4; }
  .help-box a { color: #38bdf8; text-decoration: none; font-weight: 600; font-size: 12px; }
  .logout-btn { display: flex; align-items: center; gap: 10px; color: #ef4444; text-decoration: none; font-size: 14px; font-weight: 600; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); }

  .main-content { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-width: 0; }
  .topbar { background: #fff; padding: 16px 32px; display: flex; justify-content: flex-end; align-items: center; border-bottom: 1px solid #e2e8f0; }
  .user-profile { display: flex; align-items: center; gap: 12px; }
  .notification-icon { width: 36px; height: 36px; border-radius: 50%; border: 1px solid #e2e8f0; display: grid; place-items: center; cursor: pointer; color: #6b7a90; background: #fff; }
  .avatar { width: 38px; height: 38px; background: #0284c7; color: #fff; border-radius: 8px; display: grid; place-items: center; font-weight: 600; font-size: 14px; }
  .user-info h4 { margin: 0; font-size: 14px; color: #14213d; }
  .user-info span { font-size: 12px; color: #6b7a90; }

  .content-body { padding: 32px; max-width: 1300px; }
  .retour { display: inline-block; margin-bottom: 14px; color: #0284c7; text-decoration: none; font-size: 13px; font-weight: 600; }
  .page-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap; margin-bottom: 24px; }
  .page-header h1 { margin: 4px 0 8px; font-size: 24px; color: #14213d; word-break: break-all; }
  .page-header p { color: #6b7a90; margin: 0; font-size: 14px; }
  .small-tag { font-size: 12px; font-weight: 700; color: #0284c7; letter-spacing: 0.08em; text-transform: uppercase; }
  .btn-fichier { display: inline-flex; align-items: center; gap: 8px; background: #fff; color: #14213d; text-decoration: none; padding: 10px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; border: 1px solid #cbd5e1; }
  .btn-fichier:hover { background: #f8fafc; color: #0284c7; }

  .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
  .stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 24px; }
  .stat-card .label { color: #6b7a90; font-size: 13px; font-weight: 600; }
  .stat-card .value { font-size: 28px; font-weight: 700; color: #14213d; margin: 8px 0 2px; }
  .stat-card .value.ok { color: #15803d; }
  .stat-card .value.ko { color: #b91c1c; }
  .stat-card .desc { font-size: 12px; color: #94a3b8; }

  .table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; overflow-x: auto; }
  .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 16px; flex-wrap: wrap; }
  .table-header h3 { margin: 0; font-size: 18px; color: #14213d; }
  .table-header span { font-size: 13px; color: #6b7a90; }

  table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
  th { color: #6b7a90; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
  td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; color: #14213d; vertical-align: top; }
  tr:hover td { background: #f8fafc; }
  td.num, th.num { text-align: right; white-space: nowrap; }
  .muted { color: #6b7a90; font-size: 12px; }
  code.brut { font-family: Consolas, "Courier New", monospace; font-size: 13px; background: #f1f5f9; padding: 3px 8px; border-radius: 6px; word-break: break-all; }
  .motif { color: #b91c1c; font-weight: 600; }
  .vide { text-align: center; color: #6b7a90; padding: 24px; }

  .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap; }
  .badge.integre { background: #f0fdf4; color: #15803d; }
  .badge.partiel { background: #fffbeb; color: #b45309; }
  .badge.rejete  { background: #fef2f2; color: #b91c1c; }
</style>
</head>
<body>

  <div class="sidebar">
    <div>
      <div class="logo-area">
        <div class="logo-icon">💧</div>
        <div class="logo-text">
          <h2>Fluxio</h2>
          <span>ÉNERGIE & EAU</span>
        </div>
      </div>
      <div class="nav-section-title">Agent terrain</div>
      <div class="nav-menu">
        <a href="index.php?page=agent_depot" class="nav-item">Déposer un relevé annuel</a>
        <a href="index.php?page=agent_tournee" class="nav-item">Ma tournée</a>
        <a href="index.php?page=agent_historique" class="nav-item active">Historique des dépôts</a>
      </div>
    </div>
    <div>
      <div class="help-box">
        <strong>Besoin d'aide ?</strong>
        <p>Notre équipe vous répond du lundi au vendredi.</p>
        <a href="#">Centre d'aide</a>
      </div>
      <a href="index.php?page=logout" class="logout-btn">Se déconnecter</a>
    </div>
  </div>

  <div class="main-content">
    <div class="topbar">
      <div class="user-profile">
        <div class="notification-icon">🔔</div>
        <div class="avatar"><?= htmlspecialchars($initials) ?></div>
        <div class="user-info">
          <h4><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h4>
          <span>Agent terrain</span>
        </div>
      </div>
    </div>

    <div class="content-body">
      <a href="index.php?page=agent_historique" class="retour">← Retour à l'historique</a>

      <div class="page-header">
        <div>
          <span class="small-tag">Détail du dépôt</span>
          <h1><?= htmlspecialchars($d['fichier']) ?></h1>
          <p>Déposé le <?= date('d/m/Y à H:i', strtotime($d['date_import'])) ?> pour l'année <?= (int) $d['annee'] ?>. <span class="badge <?= $classeBadge ?>">● <?= $texteBadge ?></span></p>
        </div>
        <a href="uploads/<?= rawurlencode($d['fichier']) ?>" class="btn-fichier" download>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4"></path><path d="M4 21h16"></path></svg>
          Télécharger le fichier d'origine
        </a>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="label">Lignes traitées</div>
          <div class="value"><?= (int) ($d['nb_integrees'] + $d['nb_rejetees']) ?></div>
          <div class="desc">Hors lignes vides et commentaires</div>
        </div>
        <div class="stat-card">
          <div class="label">Lignes intégrées</div>
          <div class="value ok"><?= (int) $d['nb_integrees'] ?></div>
          <div class="desc">Prises en compte</div>
        </div>
        <div class="stat-card">
          <div class="label">Lignes rejetées</div>
          <div class="value ko"><?= (int) $d['nb_rejetees'] ?></div>
          <div class="desc">Non prises en compte</div>
        </div>
        <div class="stat-card">
          <div class="label">Année de référence</div>
          <div class="value"><?= (int) $d['annee'] ?></div>
          <div class="desc">Relevé annuel</div>
        </div>
      </div>

      <div class="table-container">
        <div class="table-header">
          <div>
            <h3>Lignes rejetées</h3>
            <span>Corrigez ces lignes puis redéposez-les dans un nouveau fichier.</span>
          </div>
          <span><?= (int) $d['nb_rejetees'] ?> ligne(s)</span>
        </div>
        <table>
          <thead>
            <tr>
              <th class="num">Ligne</th>
              <th>Contenu de la ligne</th>
              <th>Contrat</th>
              <th>Client</th>
              <th>Motif du rejet</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($d['rejetees'])): ?>
              <tr><td colspan="5" class="vide">Aucune ligne rejetée : tout le fichier a été intégré.</td></tr>
            <?php else: ?>
              <?php foreach ($d['rejetees'] as $l): ?>
                <tr>
                  <td class="num"><?= $l['Numero_Ligne'] !== null ? (int) $l['Numero_Ligne'] : '—' ?></td>
                  <td><code class="brut"><?= htmlspecialchars($l['Ligne_Brute'] ?? '—') ?></code></td>
                  <td>
                    <?php if ($l['ID_Contrat'] !== null): ?>
                      n° <?= (int) $l['ID_Contrat'] ?><br>
                      <span class="muted"><?= htmlspecialchars($typeCompteur[$l['Type_Compteur']] ?? '') ?></span>
                    <?php else: ?>
                      <span class="muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($l['ID_Client'] !== null): ?>
                      <?= htmlspecialchars(trim($l['Prenom'] . ' ' . $l['Nom'])) ?><br>
                      <span class="muted">Client n° <?= (int) $l['ID_Client'] ?></span>
                    <?php else: ?>
                      <span class="muted">Client non identifié</span>
                    <?php endif; ?>
                  </td>
                  <td class="motif"><?= htmlspecialchars($l['Motif_Rejet'] ?? 'Motif non précisé') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="table-container">
        <div class="table-header">
          <div>
            <h3>Lignes intégrées</h3>
            <span>Relevés enregistrés et utilisés par la vérification annuelle.</span>
          </div>
          <span><?= (int) $d['nb_integrees'] ?> ligne(s)</span>
        </div>
        <table>
          <thead>
            <tr>
              <th class="num">Ligne</th>
              <th>Contrat</th>
              <th>Client</th>
              <th class="num">Eau (m³)</th>
              <th class="num">Électricité (kWh)</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($d['integrees'])): ?>
              <tr><td colspan="5" class="vide">Aucune ligne intégrée dans ce dépôt.</td></tr>
            <?php else: ?>
              <?php foreach ($d['integrees'] as $l): ?>
                <tr>
                  <td class="num"><?= $l['Numero_Ligne'] !== null ? (int) $l['Numero_Ligne'] : '—' ?></td>
                  <td>
                    <?php if ($l['ID_Contrat'] !== null): ?>
                      n° <?= (int) $l['ID_Contrat'] ?><br>
                      <span class="muted"><?= htmlspecialchars($typeCompteur[$l['Type_Compteur']] ?? '') ?></span>
                    <?php else: ?>
                      <span class="muted">ancien dépôt</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <strong><?= htmlspecialchars(trim($l['Prenom'] . ' ' . $l['Nom'])) ?></strong><br>
                    <span class="muted">Client n° <?= (int) $l['ID_Client'] ?></span>
                  </td>
                  <td class="num"><?= $nf($l['ConsommationEau']) ?></td>
                  <td class="num"><?= $nf($l['ConsommationElect']) ?></td>
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