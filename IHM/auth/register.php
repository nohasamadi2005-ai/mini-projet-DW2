<?php
$erreurs = $erreurs ?? [];
$v = array_merge(['nom' => '', 'prenom' => '', 'adresse' => '', 'telephone' => '', 'email' => '',
                  'cin' => '', 'type_client' => 'particulier', 'raison_sociale' => '', 'ice' => ''], $v ?? []);
$roleSaisi = $roleSaisi ?? 'client';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Créer un compte — Fluxio</title>
<?php require __DIR__ . '/style.php'; ?>
</head>
<body>
<div class="shell">
  <section class="left">
    <small>NOUVEAU CLIENT</small>
    <h2>Rejoignez Fluxio en quelques instants.</h2>
    <p>Saisissez vos relevés chaque mois et consultez vos factures d'eau et d'électricité en ligne.</p>
  </section>

  <section class="right">
    <form method="post" action="index.php?page=register">
      <h1>Créer un compte</h1>
             <?php if (INSCRIPTION_FOURNISSEUR_TEST): ?>
        <label>Je suis</label>
        <select name="role" id="role">
          <option value="client"      <?= $roleSaisi === 'client' ? 'selected' : '' ?>>Client</option>
          <option value="fournisseur" <?= $roleSaisi === 'fournisseur' ? 'selected' : '' ?>>Fournisseur (TEST)</option>
          <option value="agent"       <?= $roleSaisi === 'agent' ? 'selected' : '' ?>>Agent</option>
        </select>
      <?php endif; ?>

      <?php foreach ($erreurs as $e): ?>
        <div class="erreur"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>

      <div class="row2">
        <div><label>Nom</label>
          <input name="nom" value="<?= htmlspecialchars($v['nom']) ?>" required></div>
        <div><label>Prénom</label>
          <input name="prenom" value="<?= htmlspecialchars($v['prenom']) ?>" required></div>
      </div>

      <label>Adresse</label>
      <input name="adresse" value="<?= htmlspecialchars($v['adresse']) ?>">

      <label>Téléphone</label>
      <input name="telephone" value="<?= htmlspecialchars($v['telephone']) ?>">

      <label>Adresse e-mail</label>
      <input type="email" name="email" value="<?= htmlspecialchars($v['email']) ?>" required>
      <div id="champsClient">
        <label>CIN</label>
        <input name="cin" value="<?= htmlspecialchars($v['cin']) ?>" maxlength="10"
               pattern="[A-Za-z]{1,2}[0-9]{5,6}" placeholder="Ex. AB123456"
               style="text-transform:uppercase" title="1 ou 2 lettres suivies de 5 ou 6 chiffres">

        <label>Type de client</label>
        <select name="type_client" id="type_client">
          <option value="particulier" <?= $v['type_client'] !== 'entreprise' ? 'selected' : '' ?>>Particulier</option>
          <option value="entreprise"  <?= $v['type_client'] === 'entreprise' ? 'selected' : '' ?>>Entreprise</option>
        </select>

        <div id="champsEntreprise" style="display:none">
          <label>Raison sociale</label>
          <input name="raison_sociale" value="<?= htmlspecialchars($v['raison_sociale']) ?>">
          <label>ICE (15 chiffres)</label>
          <input name="ice" value="<?= htmlspecialchars($v['ice']) ?>" maxlength="15"
                 pattern="[0-9]{15}" inputmode="numeric" title="Exactement 15 chiffres">
        </div>
      </div>
      <div class="row2">
        <div><label>Mot de passe</label>
          <input type="password" name="password" required></div>
        <div><label>Confirmation</label>
          <input type="password" name="password2" required></div>
      </div>
      <p class="muted">8 caractères minimum, avec au moins une lettre et un chiffre.</p>

      <button type="submit">Créer mon compte</button>
      <p class="muted" style="text-align:center;margin-top:16px">
        Déjà inscrit ? <a href="index.php?page=login">Se connecter</a>
      </p>
    </form>
  </section>
</div>
</body>

<script>
  const role = document.getElementById('role');
  const champs = document.getElementById('champsClient');
  function maj() {
    const autre = role && (role.value === 'fournisseur' || role.value === 'agent');
    champs.style.display = autre ? 'none' : 'block';
    const cin = champs.querySelector('[name=cin]'); if (cin) cin.required = !autre;
  }
  if (role) { role.addEventListener('change', maj); maj(); }

  // Champs entreprise : visibles seulement si « Entreprise », et alors obligatoires.
  const typeClient = document.getElementById('type_client');
  const blocEntreprise = document.getElementById('champsEntreprise');
  function majType() {
    const ent = typeClient.value === 'entreprise';
    blocEntreprise.style.display = ent ? 'block' : 'none';
    blocEntreprise.querySelectorAll('input').forEach(i => i.required = ent);
  }
  typeClient.addEventListener('change', majType); majType();
</script>
</html>