<?php
$user = $_SESSION['user'] ?? ['nom' => 'Utilisateur', 'prenom' => 'Agent'];
$initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
$search = trim($_GET['q'] ?? '');
$imports = getImportsAgent($_SESSION['user']['id'], $search);
$stats = getStatistiquesImports($_SESSION['user']['id']);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Historique des dépôts — Fluxio</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; background: #f4f6f9; color: #14213d; display: flex; min-height: 100vh; }

  /* Sidebar */
  .sidebar { width: 260px; background: #0a2540; color: #fff; display: flex; flex-direction: column; justify-content: space-between; padding: 24px; position: fixed; height: 100vh; }
  .logo-area { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
  .logo-icon { background: #38bdf8; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 20px; color: #fff; box-shadow: 0 4px 12px rgba(56,189,248,0.3); }
  .logo-text h2 { margin: 0; font-size: 18px; font-weight: 700; letter-spacing: -0.01em; }
  .logo-text span { font-size: 10px; color: #7fb4e6; letter-spacing: 0.08em; font-weight: 600; text-transform: uppercase; }

  .nav-section-title { font-size: 11px; letter-spacing: 0.1em; color: #7fb4e6; margin-bottom: 12px; font-weight: 600; text-transform: uppercase; }
  .nav-menu { display: flex; flex-direction: column; gap: 8px; flex: 1; }
  .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 8px; color: #b8d3ef; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.2s; }
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

  /* Body Container */
  .content-body { padding: 32px; max-width: 1300px; }
  .page-header h1 { margin: 4px 0 8px; font-size: 24px; color: #14213d; }
  .page-header p { color: #6b7a90; margin: 0 0 24px; font-size: 14px; }
  .small-tag { font-size: 12px; font-weight: 700; color: #0284c7; letter-spacing: 0.08em; text-transform: uppercase; }

  /* Stats Cards (2 colonnes) */
  .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
  .stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px 24px; display: flex; flex-direction: column; justify-content: space-between; }
  .stat-card .top-line { display: flex; justify-content: space-between; align-items: center; color: #6b7a90; font-size: 13px; font-weight: 600; }
  .stat-card .value { font-size: 32px; font-weight: 700; color: #14213d; margin: 10px 0 4px; }
  .stat-card .desc { font-size: 12px; color: #94a3b8; }
  .stat-icon { width: 36px; height: 36px; background: #f0fdf4; color: #15803d; border-radius: 8px; display: grid; place-items: center; font-size: 16px; }

  .table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
  .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .table-header h3 { margin: 0; font-size: 18px; color: #14213d; }
  .table-header span { font-size: 13px; color: #6b7a90; }
  .search-box input { padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; width: 260px; background: #f8fafc; font-size: 13px; color: #14213d; }

  table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
  th { color: #6b7a90; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
  td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #14213d; }
  tr:hover td { background: #f8fafc; }

  .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
  .badge.integre { background: #f0fdf4; color: #15803d; }
  .download-icon-btn { color: #6b7a90; text-decoration: none; border: 1px solid #e2e8f0; padding: 6px 10px; border-radius: 6px; transition: 0.2s; display: inline-block; background: #fff; }
  .download-icon-btn:hover { background: #f1f5f9; color: #0284c7; }
  .download-icon-btn {
    color: #475569;
    background: #ffffff;
    text-decoration: none;
    border: 1px solid #cbd5e1;
    padding: 6px 10px;
    border-radius: 8px;
    transition: 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  }
  .download-icon-btn:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0284c7;
  }
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
        <span class="small-tag">Espace Agent Terrain</span>
        <h1>Historique des dépôts</h1>
        <p>Retrouvez l'ensemble des fichiers annuels transmis au fournisseur.</p>
      </div>

      <!-- Stats Cards (2 cartes épurées) -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="top-line">
            <span>Fichiers déposés</span>
          </div>
          <div class="value"><?= intval($stats['fichiers_deposes']) ?></div>
          <div class="desc">Depuis le système</div>
        </div>
        <div class="stat-card">
          <div class="top-line">
            <span>Relevés intégrés</span>
            <div class="stat-icon">✓</div>
          </div>
          <div class="value"><?= intval($stats['releves_integres']) ?></div>
          <div class="desc">Tous secteurs confondus</div>
        </div>
      </div>

      <!-- Table Section -->
      <div class="table-container">
        <div class="table-header">
          <div>
            <h3>Fichiers importés</h3>
            <span><?= count($imports) ?> fichiers au total</span>
          </div>
          <!-- Le filtre recherche par nom de fichier ou par année -->
          <form method="get" action="index.php" class="search-box">
            <input type="hidden" name="page" value="agent_historique">
            <input type="text" name="q" placeholder="Rechercher par fichier ou année..." value="<?= htmlspecialchars($search) ?>">
          </form>
        </div>

        <table>
          <thead>
            <tr>
              <th>Nom du fichier</th>
              <th>Date d'import</th>
              <th>Année</th>
              <th>Zone</th>
              <th>Nb. relevés</th>
              <th>Statut d'intégration</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($imports)): ?>
              <tr>
                <td colspan="7" style="text-align: center; color: #6b7a90; padding: 24px;">Aucun fichier importé pour le moment.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($imports as $imp): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($imp['Nom_Fichier']) ?></strong></td>
                  <td><?= date('d/m/Y H:i', strtotime($imp['Date_Import'])) ?></td>
                  <td><?= htmlspecialchars($imp['Annee']) ?></td>
                  <td><?= htmlspecialchars($imp['Zone']) ?></td>
                  <td><strong><?= intval($imp['Nb_Releves']) ?></strong></td>
                  <td>
                    <span class="badge integre">● Intégré</span>
                  </td>
                  <td>
  <a href="uploads/<?= htmlspecialchars($imp['Nom_Fichier']) ?>" class="download-icon-btn" title="Télécharger" download>
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 3v12m0 0l-4-4m4 4l4-4"></path>
      <path d="M4 21h16"></path>
    </svg>
  </a>
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