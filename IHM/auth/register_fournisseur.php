<?php
$erreurs = $erreurs ?? [];
$v = $v ?? ['nom' => '', 'prenom' => '', 'email' => ''];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inscription fournisseur (TEST) — Fluxio</title>
<?php require __DIR__ . '/style.php'; ?>
</head>
<body>
<div class="shell">
  <section class="left">
    <small>MODE TEST</small>
    <h2>Création d'un compte fournisseur.</h2>
    <p>Page temporaire, réservée aux tests. Elle sera supprimée dans la version finale.</p>
  </section>

  <section class="right">
    <form method="post" action="index.php?page=register_fournisseur">
      <h1>Créer un fournisseur</h1>

      <?php foreach ($erreurs as $e): ?>
        <div class="erreur"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>

      <div class="row2">
        <div><label>Nom</label>
          <input name="nom" value="<?= htmlspecialchars($v['nom']) ?>" required></div>
        <div><label>Prénom</label>
          <input name="prenom" value="<?= htmlspecialchars($v['prenom']) ?>" required></div>
      </div>

      <label>Adresse e-mail</label>
      <input type="email" name="email" value="<?= htmlspecialchars($v['email']) ?>" required>

      <div class="row2">
        <div><label>Mot de passe</label>
          <input type="password" name="password" required></div>
        <div><label>Confirmation</label>
          <input type="password" name="password2" required></div>
      </div>
      <p class="muted">8 caractères minimum, avec au moins une lettre et un chiffre.</p>

      <button type="submit">Créer le fournisseur</button>
      <p class="muted" style="text-align:center;margin-top:16px">
        <a href="index.php?page=login">Se connecter</a>
      </p>
    </form>
  </section>
</div>
</body>
</html>