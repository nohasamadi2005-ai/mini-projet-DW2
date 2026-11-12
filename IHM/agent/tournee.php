<?php
/** @var array $d données préparées par preparerPageTournee() (traitement/agent.php) */
// Page « Ma tournée » — données préparées par preparerPageTournee() (traitement/agent.php) dans $d
$user     = $_SESSION['user'] ?? ['nom' => 'Utilisateur', 'prenom' => 'Agent'];
$initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

$annee       = $d['annee'];
$progression = $d['progression'];
$lignes      = $d['lignes'];
$quartiers   = $d['quartiers'];
$pct         = (int) $progression['pourcentage'];
$urlFeuille  = 'index.php?page=agent_tournee_export&annee=' . urlencode($annee);

$libelleType = ['Eau' => 'Eau', 'Electricite' => 'Électricité'];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ma tournée — Fluxio</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; background: #f4f6f9; color: #14213d; display: flex; min-height: 100vh; }

  /* Sidebar */
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

  /* Main Content */
  .main-content { margin-left: 260px; flex: 1; display: flex; flex-direction: column; }
  .topbar { background: #fff; padding: 16px 32px; display: flex; justify-content: flex-end; align-items: center; border-bottom: 1px solid #e2e8f0; }
  .user-profile { display: flex; align-items: center; gap: 12px; }
  .notification-icon { width: 36px; height: 36px; border-radius: 50%; border: 1px solid #e2e8f0; display: grid; place-items: center; cursor: pointer; color: #6b7a90; background: #fff; }
  .avatar { width: 38px; height: 38px; background: #0284c7; color: #fff; border-radius: 8px; display: grid; place-items: center; font-weight: 600; font-size: 14px; }
  .user-info h4 { margin: 0; font-size: 14px; color: #14213d; }
  .user-info span { font-size: 12px; color: #6b7a90; }

  .content-body { padding: 32px; max-width: 1300px; }
  .page-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap; margin-bottom: 24px; }
  .page-header h1 { margin: 4px 0 8px; font-size: 24px; color: #14213d; }
  .page-header p { color: #6b7a90; margin: 0; font-size: 14px; }
  .small-tag { font-size: 12px; font-weight: 700; color: #0284c7; letter-spacing: 0.08em; text-transform: uppercase; }

  .btn-feuille { display: inline-flex; align-items: center; gap: 8px; background: #0284c7; color: #fff; text-decoration: none; padding: 11px 18px; border-radius: 10px; font-size: 14px; font-weight: 600; box-shadow: 0 2px 6px rgba(2,132,199,0.3); }
  .btn-feuille:hover { background: #0369a1; }
  .lien-tous { display: block; margin-top: 6px; font-size: 12px; color: #6b7a90; text-align: right; }
  .lien-tous a { color: #0284c7; text-decoration: none; }

  /* Progression */
  .progress-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px 24px; margin-bottom: 20px; }
  .progress-top { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
  .progress-top strong { font-size: 15px; }
  .progress-top .ratio { font-size: 28px; font-weight: 700; color: #14213d; }
  .progress-top .ratio small { font-size: 15px; font-weight: 600; color: #6b7a90; }
  .bar { height: 12px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
  .bar > div { height: 100%; background: linear-gradient(90deg, #38bdf8, #0284c7); border-radius: 99px; }
  .bar.complete > div { background: linear-gradient(90deg, #4ade80, #15803d); }
  .progress-note { margin: 10px 0 0; font-size: 13px; color: #6b7a90; }

  .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
  .stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 24px; }
  .stat-card .label { color: #6b7a90; font-size: 13px; font-weight: 600; }
  .stat-card .value { font-size: 30px; font-weight: 700; color: #14213d; margin: 8px 0 2px; }
  .stat-card .desc { font-size: 12px; color: #94a3b8; }

  .quartiers-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 24px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
  .quartiers-box .titre { font-size: 12px; font-weight: 700; color: #6b7a90; text-transform: uppercase; letter-spacing: 0.06em; margin-right: 6px; }
  .chip { background: #eff6ff; color: #1d4ed8; border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 600; }

  .table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
  .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 16px; flex-wrap: wrap; }
  .table-header h3 { margin: 0; font-size: 18px; color: #14213d; }
  .table-header span { font-size: 13px; color: #6b7a90; }
  .filtres { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
  .filtres select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; font-size: 13px; background: #f8fafc; color: #14213d; }
  .filtres button { padding: 8px 16px; border: 0; border-radius: 8px; background: #14213d; color: #fff; font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }

  table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
  th { color: #6b7a90; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
  td { padding: 13px 16px; border-bottom: 1px solid #f1f5f9; color: #14213d; }
  tr:hover td { background: #f8fafc; }
  tr.groupe td { background: #f1f5f9; font-weight: 700; font-size: 13px; color: #0a2540; padding: 10px 16px; }
  .muted { color: #6b7a90; font-size: 12px; }

  .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap; }
  .badge.integre { background: #f0fdf4; color: #15803d; }
  .badge.attente { background: #fffbeb; color: #b45309; }
  .vide { text-align: center; color: #6b7a90; padding: 28px; }
</style>
</head>
<body>

  <!-- Sidebar -->
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
        <a href="index.php?page=agent_tournee" class="nav-item active">Ma tournée</a>
        <a href="index.php?page=agent_historique" class="nav-item">Historique des dépôts</a>
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

  <!-- Main Content -->
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
      <div class="page-header">
        <div>
          <span class="small-tag">Espace Agent Terrain</span>
          <h1>Ma tournée <?= (int) $annee ?></h1>
          <p>Les contrats à relever dans vos quartiers et l'avancement de votre relevé annuel.</p>
        </div>
        <div>
          <a href="<?= htmlspecialchars($urlFeuille) ?>" class="btn-feuille">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4"></path><path d="M4 21h16"></path></svg>
            Télécharger la feuille de tournée
          </a>
          <span class="lien-tous">Clients encore à relever · <a href="<?= htmlspecialchars($urlFeuille) ?>&tous=1">toute la tournée</a></span>
        </div>
      </div>

      

      <!-- Progression -->
      <div class="progress-card">
        <div class="progress-top">
          <strong>Progression du relevé <?= (int) $annee ?></strong>
          <span class="ratio"><?= (int) $progression['releves'] ?> <small>/ <?= (int) $progression['total'] ?> contrats relevés (<?= $pct ?> %)</small></span>
        </div>
        <div class="bar<?= $pct >= 100 ? ' complete' : '' ?>"><div style="width: <?= $pct ?>%"></div></div>
        <p class="progress-note">
          <?php if ($progression['total'] === 0): ?>
            Aucun contrat en cours dans vos quartiers pour cette année.
          <?php elseif ($progression['restants'] === 0): ?>
            Tournée terminée : tous les contrats sont relevés pour <?= (int) $annee ?>.
          <?php else: ?>
            Il reste <strong><?= (int) $progression['restants'] ?></strong> contrat(s) à relever.
          <?php endif; ?>
        </p>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="label">Contrats dans ma tournée</div>
          <div class="value"><?= (int) $progression['total'] ?></div>
          <div class="desc">Contrats en cours sur l'année</div>
        </div>
        <div class="stat-card">
          <div class="label">Déjà relevés</div>
          <div class="value"><?= (int) $progression['releves'] ?></div>
          <div class="desc">Ligne intégrée pour <?= (int) $annee ?></div>
        </div>
        <div class="stat-card">
          <div class="label">Restant à relever</div>
          <div class="value"><?= (int) $progression['restants'] ?></div>
          <div class="desc">À déposer dans un fichier</div>
        </div>
      </div>

      <div class="quartiers-box">
        <span class="titre">Mes quartiers</span>
        <?php foreach ($quartiers as $q): ?>
          <span class="chip"><?= htmlspecialchars($q['Nom']) ?> · <?= htmlspecialchars($q['Ville']) ?></span>
        <?php endforeach; ?>
      </div>

      <!-- Liste des contrats -->
      <div class="table-container">
        <div class="table-header">
          <div>
            <h3>Contrats à relever</h3>
            <span><?= count($lignes) ?> contrat(s) affiché(s)</span>
          </div>
          <form method="get" action="index.php" class="filtres">
            <input type="hidden" name="page" value="agent_tournee">
            <select name="annee">
              <?php foreach ($d['annees'] as $a): ?>
                <option value="<?= (int) $a ?>"<?= (int) $a === (int) $annee ? ' selected' : '' ?>><?= (int) $a ?></option>
              <?php endforeach; ?>
            </select>
            <select name="statut">
              <option value="tous"<?= $d['filtre'] === 'tous' ? ' selected' : '' ?>>Tous les contrats</option>
              <option value="a_releve"<?= $d['filtre'] === 'a_releve' ? ' selected' : '' ?>>À relever</option>
              <option value="releve"<?= $d['filtre'] === 'releve' ? ' selected' : '' ?>>Déjà relevés</option>
            </select>
            <button type="submit">Filtrer</button>
          </form>
        </div>

        <table>
          <thead>
            <tr>
              <th>Client</th>
              <th>Contrat</th>
              <th>Compteur</th>
              <th>Adresse de branchement</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($lignes)): ?>
              <tr><td colspan="5" class="vide">Aucun contrat ne correspond à ce filtre.</td></tr>
            <?php else: ?>
              <?php $quartierCourant = null; ?>
              <?php foreach ($lignes as $l): ?>
                <?php $cle = $l['Quartier'] . ' — ' . $l['Ville']; ?>
                <?php if ($cle !== $quartierCourant): $quartierCourant = $cle; ?>
                  <tr class="groupe"><td colspan="5"><?= htmlspecialchars($cle) ?></td></tr>
                <?php endif; ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($l['Prenom'] . ' ' . $l['Nom']) ?></strong><br>
                    <span class="muted">Client n° <?= (int) $l['ID_Client'] ?></span>
                  </td>
                  <td>n° <?= (int) $l['ID_Contrat'] ?><?php if ($l['Statut_Contrat'] === 'Resilie' && !empty($l['Date_Fin'])): ?><br><span class="muted">résilié le <?= date('d/m/Y', strtotime($l['Date_Fin'])) ?></span><?php endif; ?></td>
                  <td><?= htmlspecialchars($libelleType[$l['Type_Compteur']] ?? $l['Type_Compteur']) ?><br><span class="muted">Compteur n° <?= (int) $l['ID_Compteur'] ?></span></td>
                  <td><?= htmlspecialchars($l['Adresse_Branchement'] ?? '—') ?></td>
                  <td>
                    <?php if ($l['Releve']): ?>
                      <span class="badge integre">● Relevé</span>
                      <?php if (!empty($l['Date_Releve'])): ?>
                        <br><span class="muted"><?= date('d/m/Y', strtotime($l['Date_Releve'])) ?></span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="badge attente">● À relever</span>
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

</body>
</html>