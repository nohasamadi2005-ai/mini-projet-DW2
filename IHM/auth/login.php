<?php
$erreur     = $erreur ?? '';
$emailSaisi = $emailSaisi ?? '';
$message    = $message ?? '';
$roleSaisi  = $roleSaisi ?? 'client';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion — Fluxio</title>
<?php require __DIR__ . '/style.php'; ?>
</head>
<body>
<div class="shell">
  <section class="left">
    <small>VOTRE ESPACE SÉCURISÉ</small>
    <h2>Vos consommations, simplement maîtrisées.</h2>
    <p>Relevés, factures et suivi : tout ce dont vous avez besoin réuni dans un espace clair et sécurisé.</p>
  </section>

  <section class="right">
    <form method="post" action="index.php?page=login">
      <small class="muted">BIENVENUE</small>
      <h1>Connectez-vous à Fluxio</h1>
      <p class="muted">Renseignez vos identifiants pour accéder à votre espace.</p>

      <?php if ($erreur): ?>
        <div class="erreur"><?= htmlspecialchars($erreur) ?></div>
      <?php endif; ?>

      <?php if ($message): ?>
    <div class="ok"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

      <label for="email">Adresse e-mail</label>
      <input type="email" id="email" name="email" value="<?= htmlspecialchars($emailSaisi) ?>" required>

      <label for="password">Mot de passe</label>
      <input type="password" id="password" name="password" required>

      <label for="role">Espace de connexion</label>
      <select id="role" name="role">
        <option value="client"      <?= $roleSaisi === 'client' ? 'selected' : '' ?>>Particulier</option>
        <option value="fournisseur" <?= $roleSaisi === 'fournisseur' ? 'selected' : '' ?>>Fournisseur</option>
      </select>

      <button type="submit">Se connecter</button>
      <p class="muted" style="text-align:center;margin-top:16px">
    Pas de compte ? <a href="index.php?page=register">Créer un compte</a>
</p>
    </form>
  </section>
</div>
</body>
</html>