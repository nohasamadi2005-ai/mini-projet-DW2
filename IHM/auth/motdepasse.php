<?php
$erreurs = $erreurs ?? [];
$succes  = $succes ?? false;
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Changer mon mot de passe — Fluxio</title>
<?php require __DIR__ . '/style.php'; ?>
</head>
<body>
<section class="right" style="min-height:100vh">
  <form method="post" action="index.php?page=motdepasse">
    <h1>Changer mon mot de passe</h1>

    <?php foreach ($erreurs as $e): ?>
      <div class="erreur"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <?php if ($succes): ?>
      <div class="ok">Mot de passe modifié avec succès.</div>
    <?php endif; ?>

    <label>Mot de passe actuel</label>
    <input type="password" name="ancien" required>

    <label>Nouveau mot de passe</label>
    <input type="password" name="nouveau" required>

    <label>Confirmer le nouveau mot de passe</label>
    <input type="password" name="nouveau2" required>
    <p class="muted">8 caractères minimum, avec au moins une lettre et un chiffre.</p>

    <button type="submit">Enregistrer</button>
    <p class="muted" style="text-align:center;margin-top:16px">
      <a href="index.php?page=<?= pageAccueil($_SESSION['user']['role']) ?>">← Retour à mon espace</a>
    </p>
  </form>
</section>
</body>
</html>