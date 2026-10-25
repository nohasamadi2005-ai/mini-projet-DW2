<?php
$user = $_SESSION['user'] ?? ['nom' => 'Utilisateur', 'prenom' => 'Agent'];
$initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
$resultatTraitement = $resultatTraitement ?? ['erreurs' => [], 'succes' => ''];

// Récupérer le dernier fichier importé par cet agent pour le bouton de téléchargement
require_once __DIR__ . '/../../BD/AgentImport.php';
$agentId = $user['id'] ?? 1;
$fichiersImportes = getImportsAgent($agentId);
$dernierFichier = !empty($fichiersImportes) ? $fichiersImportes[0]['Nom_Fichier'] : null;
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Déposer un relevé annuel — Fluxio</title>
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

  /* Body Container Centered */
  .content-body { padding: 32px; max-width: 900px; width: 100%; margin: 0 auto; }
  .page-header h1 { margin: 4px 0 8px; font-size: 24px; color: #14213d; }
  .page-header p { color: #6b7a90; margin: 0 0 24px; font-size: 14px; }
  .small-tag { font-size: 12px; font-weight: 700; color: #0284c7; letter-spacing: 0.08em; text-transform: uppercase; }

  /* Card Layout */
  .card { background: #fff; border-radius: 12px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 20px; }

  .upload-box { border: 2px dashed #93c5fd; border-radius: 12px; padding: 40px; text-align: center; background: #f8fafc; cursor: pointer; margin-bottom: 20px; transition: 0.2s; }
  .upload-box:hover { background: #f1f5f9; border-color: #3b82f6; }
  .upload-icon-circle { width: 50px; height: 50px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 12px; font-size: 20px; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
  label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #334155; }
  select, input[type="file"] { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; background: #f8fafc; color: #14213d; }
  .submit-btn { width: 100%; background: #38bdf8; color: #fff; border: 0; padding: 14px; border-radius: 8px; font: inherit; font-weight: 600; cursor: pointer; transition: 0.2s; }
  .submit-btn:hover { background: #0284c7; }

  /* Green Download Button */
  .download-model-btn { display: block; text-align: center; background: #10b981; color: #fff; border: 0; padding: 14px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; transition: 0.2s; width: 100%; }
  .download-model-btn:hover { background: #059669; }

  /* Alerts */
  .erreur { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
  .ok { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
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
        <a href="index.php?page=agent_depot" class="nav-item active">Déposer un relevé annuel</a>
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
        <span class="small-tag">Espace Agent Terrain</span>
        <h1>Déposer un relevé annuel</h1>
        <p>Importez le fichier consolidé des consommations relevées sur le terrain.</p>
      </div>

      <?php if (!empty($resultatTraitement['erreurs'])): ?>
        <?php foreach ($resultatTraitement['erreurs'] as $err): ?>
          <div class="erreur"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!empty($resultatTraitement['succes'])): ?>
        <div class="ok"><?= htmlspecialchars($resultatTraitement['succes']) ?></div>
      <?php endif; ?>

      <form method="post" action="index.php?page=agent_depot" enctype="multipart/form-data">
        <!-- Main Card: Upload -->
        <div class="card">
          <h3>Importer un nouveau fichier</h3>
          <p style="color: #6b7a90; font-size: 13px; margin-bottom: 20px;">Le fichier sera contrôlé avant son intégration.</p>

      <div class="upload-box" onclick="document.getElementById('fileInput').click();">
            <div class="upload-icon-circle">📁</div>
            <strong>Glissez votre fichier de consommation ici</strong>
            <p style="color: #6b7a90; font-size: 13px; margin: 6px 0 0;">ou cliquez pour sélectionner un fichier</p>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 10px;">Format accepté : .TXT - Taille maximale : 10 Mo</div>
            <input type="file" id="fileInput" name="fichier_txt" accept=".txt" style="display: none;" required>
          </div>

          <div class="form-row">
            <div>
              <label>Année de référence</label>
              <select name="annee">
                <option value="2026" selected>2026</option>
                <option value="2025">2025</option>
                <option value="2024">2024</option>
              </select>
            </div>
            <div>
              <label>Zone d'intervention</label>
              <select name="zone">
                <option value="Secteur Nord — Zone 04" selected>Secteur Nord — Zone 04</option>
                <option value="Secteur Sud — Zone 01">Secteur Sud — Zone 01</option>
                <option value="Secteur Est — Zone 02">Secteur Est — Zone 02</option>
              </select>
            </div>
          </div>

          <button type="submit" class="submit-btn">Importer le fichier</button>
        </div>

        <!-- Green Button: Télécharger le dernier fichier importé -->
        <?php if ($dernierFichier): ?>
        <div class="card" style="padding: 16px;">
          <a href="uploads/<?= htmlspecialchars($dernierFichier) ?>" class="download-model-btn" download>
            Télécharger (<?= htmlspecialchars($dernierFichier) ?>)
          </a>
        </div>
        <?php endif; ?>
      </form>
    </div>
  </div>

</body>
</html>