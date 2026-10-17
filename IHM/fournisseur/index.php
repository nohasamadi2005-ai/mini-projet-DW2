<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Espace fournisseur— Fluxio</title></head>
<body>
  <h1>Bonjour <?= htmlspecialchars($_SESSION['user']['prenom'] ?? '') ?></h1>
  <p>Bienvenue dans votre espace fournisseur.</p>
  <form method="post" action="index.php?page=logout">
  <button type="submit">Se déconnecter</button>
  <p><a href="index.php?page=motdepasse">Changer mon mot de passe</a></p>
</form>
</body>
</html>