<?php

$u = $_SESSION['user'];
$initiales = mb_strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1));

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}

function icone($nom)
{
    $p = [
        'accueil' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'saisie'  => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'facture' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'cloche'  => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'goutte'  => '<path d="M12 2.7l5.7 5.7a8 8 0 1 1-11.4 0z"/>',
        'eclair'  => '<path d="M13 2L3 14h9l-1 8 10-12h-9z"/>',
        'sortie'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'cle'     => '<path d="M21 2l-2 2m-7.6 7.6a5.5 5.5 0 1 1-7.8 7.8 5.5 5.5 0 0 1 7.8-7.8zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
    ];
    return '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
         . 'stroke-linecap="round" stroke-linejoin="round">' . $p[$nom] . '</svg>';
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mes réclamations — Fluxio</title>
<style>
  :root { --navy:#0b2340; --blue:#1aa7ec; --blue-d:#0b87c4; --bg:#f3f6fb; --line:#e3e9f2;
          --txt:#12233f; --mut:#6f7f95; --soft:#eaf6fd; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:"Segoe UI",system-ui,sans-serif; background:var(--bg); color:var(--txt); font-size:14px; }
  .i { width:18px; height:18px; flex:none; }
  button { font:inherit; cursor:pointer; }

  .app { display:grid; grid-template-columns:230px 1fr; min-height:100vh; }
  .side { background:var(--navy); color:#c9d8ec; padding:20px 14px; display:flex; flex-direction:column; position:sticky; top:0; height:100vh; }
  .brand { display:flex; align-items:center; gap:10px; color:#fff; font-weight:700; font-size:18px; margin:0 6px 20px; }
  .brand .logo { width:34px; height:34px; border-radius:9px; background:var(--blue); display:grid; place-items:center; }
  .brand small { display:block; font-size:9px; letter-spacing:.14em; color:#8fb0d6; font-weight:600; }
  .tag { background:rgba(255,255,255,.08); font-size:10px; letter-spacing:.14em; font-weight:600; padding:7px 10px; border-radius:6px; margin-bottom:14px; color:#9fc0e6; }
  .side nav a { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; color:#c9d8ec; text-decoration:none; margin-bottom:4px; }
  .side nav a:hover { background:rgba(255,255,255,.07); }
  .side nav a.actif { background:var(--blue); color:#fff; font-weight:600; }
  .badge { margin-left:auto; background:#ef4444; color:#fff; font-size:10px; font-weight:700; border-radius:10px; padding:1px 6px; }
  .side .bas { margin-top:auto; }
  .quit { margin-top:14px; border-top:1px solid rgba(255,255,255,.1); padding-top:12px; }
  .quit button { background:none; border:0; color:#c9d8ec; display:flex; align-items:center; gap:10px; padding:8px 12px; width:100%; }

  .top { background:#fff; border-bottom:1px solid var(--line); height:58px; display:flex; align-items:center; justify-content:flex-end; gap:18px; padding:0 28px; }
  .user { display:flex; align-items:center; gap:10px; padding-left:18px; border-left:1px solid var(--line); font-weight:600; font-size:12px; }
  .user small { display:block; color:var(--mut); font-weight:400; font-size:10px; }
  .avatar { width:34px; height:34px; border-radius:8px; background:var(--blue); color:#fff; display:grid; place-items:center; font-size:12px; font-weight:700; }

  .page { max-width:1050px; margin:0 auto; padding:34px 24px 50px; }
  .eyebrow { font-size:10px; letter-spacing:.14em; font-weight:700; color:var(--blue-d); }
  h1 { font-size:28px; margin:6px 0 4px; }
  .sous { color:var(--mut); margin:0 0 22px; }

  .carte { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; }
  table.hist { width:100%; border-collapse:collapse; }
  .hist th { background:#f6f8fb; color:var(--mut); font-size:10px; letter-spacing:.1em; text-align:left; padding:12px 16px; }
  .hist td { padding:14px 16px; border-top:1px solid var(--line); vertical-align:middle; }
  .hist td.num { font-weight:700; }
  .hist td.ttc { font-weight:800; white-space:nowrap; }
  .hist th.r, .hist td.r { text-align:right; }

  .f-badge { font-size:10px; font-weight:700; border-radius:6px; padding:4px 9px; display:inline-flex; align-items:center; gap:5px; }
  .f-badge .i { width:12px; height:12px; }
  .f-badge.elec { background:#fff4dc; color:#c77700; }
  .f-badge.eau { background:#e8f4fd; color:var(--blue-d); }
  .statut { font-size:11px; font-weight:700; border-radius:20px; padding:3px 10px; white-space:nowrap; }
  .statut.impaye { background:#fdecec; color:#9b1c1c; }
  .statut.paye { background:#eafaf1; color:#14653a; }
  .statut.partiel { background:#fff4e5; color:#8a5300; }
  
  .btn-voir { display:inline-block; color:var(--blue-d); border:1px solid var(--blue); border-radius:8px; padding:6px 10px; font-weight:700; font-size:12px; text-decoration:none; white-space:nowrap; }
  .btn-voir:hover { background:var(--soft); }
  
  .btn-payer { background:#16a34a; color:#fff; border:none; border-radius:8px; padding:7px 12px; font-weight:700; font-size:12px; transition:0.2s; white-space:nowrap; }
  .btn-payer:hover { background:#15803d; }

  .vide { text-align:center; padding:50px 20px; color:var(--mut); }
  .vide .i { width:34px; height:34px; margin-bottom:10px; color:#b6c3d4; }
  .vide b { display:block; color:var(--txt); font-size:15px; margin-bottom:4px; }

  .actions-cell { display:flex; align-items:center; gap:8px; justify-content:flex-end; }

  @media (max-width:900px) {
    .app { grid-template-columns:1fr; } .side { position:static; height:auto; }
    .carte { overflow-x:auto; } table.hist { min-width:780px; }
  }

  /* --- Réclamations --- */
  .alerte { padding:12px 16px; border-radius:10px; margin-bottom:18px; font-weight:600; }
  .alerte.succes { background:#eafaf1; color:#14653a; border:1px solid #b7e8cd; }
  .alerte.erreur { background:#fdecec; color:#9b1c1c; border:1px solid #f4c2c2; }
  .grille { display:grid; grid-template-columns:380px 1fr; gap:22px; align-items:start; }
  .form-carte { padding:22px; }
  .form-carte h2, .liste-titre { font-size:16px; margin:0 0 14px; }
  label { display:block; font-weight:600; font-size:12px; margin:14px 0 6px; }
  select, input[type=text], textarea { width:100%; border:1px solid var(--line); border-radius:8px; padding:10px 12px; font:inherit; background:#fff; color:var(--txt); }
  textarea { min-height:110px; resize:vertical; }
  select:focus, input:focus, textarea:focus { outline:2px solid var(--blue); border-color:var(--blue); }
  .btn-envoyer { margin-top:18px; width:100%; background:var(--blue); color:#fff; border:none; border-radius:8px; padding:11px; font-weight:700; }
  .btn-envoyer:hover { background:var(--blue-d); }
  .btn-envoyer[disabled] { background:#b6c3d4; cursor:not-allowed; }
  .compteur { text-align:right; font-size:11px; color:var(--mut); margin-top:4px; }
  .rec { padding:16px 18px; border-top:1px solid var(--line); }
  .rec:first-child { border-top:0; }
  .rec-tete { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
  .rec-tete b { font-size:14px; }
  .rec-meta { color:var(--mut); font-size:12px; margin:4px 0 8px; }
  .rec p { margin:0; white-space:pre-line; }
  .reponse { margin-top:12px; background:var(--soft); border-left:3px solid var(--blue); border-radius:6px; padding:10px 12px; }
  .reponse small { display:block; color:var(--mut); margin-bottom:4px; }
  .statut.ouverte { background:#fff4e5; color:#8a5300; }
  .statut.encours { background:#e8f4fd; color:var(--blue-d); }
  .statut.traitee { background:#eafaf1; color:#14653a; }
  .statut.rejetee { background:#fdecec; color:#9b1c1c; }
  @media (max-width:900px) { .grille { grid-template-columns:1fr; } }
</style>
</head>
<body>
<div class="app">

  <aside class="side">
    <div class="brand"><span class="logo"><?= icone('goutte') ?></span>
      <div>Fluxio<small>ÉNERGIE &amp; EAU</small></div></div>
    <div class="tag">ESPACE CLIENT</div>
    <nav>
      <a href="index.php?page=client"><?= icone('accueil') ?> Accueil</a>
      <a href="index.php?page=saisie"><?= icone('saisie') ?> Saisie mensuelle</a>
      <a href="index.php?page=factures"><?= icone('facture') ?> Mes factures</a>
      <a href="index.php?page=reclamations" class="actif"><?= icone('message') ?> Mes réclamations</a>
      <a href="index.php?page=notifications"><?= icone('cloche') ?> Notifications</a>
    </nav>
    <div class="bas">
      <nav><a href="index.php?page=motdepasse"><?= icone('cle') ?> Mon mot de passe</a></nav>
      <form class="quit" method="post" action="index.php?page=logout">
        <button type="submit"><?= icone('sortie') ?> Se déconnecter</button>
      </form>
    </div>
  </aside>

  <div>
    <header class="top">
      <div class="user">
        <span class="avatar"><?= h($initiales) ?></span>
        <div><?= h($u['prenom'] . ' ' . $u['nom']) ?><small>Espace client</small></div>
      </div>
    </header>

    <main class="page">
      <div class="eyebrow">ESPACE CLIENT</div>
      <h1>Mes réclamations</h1>
      <p class="sous">Signalez un problème sur une facture (fuite, montant, autre) et suivez la réponse du fournisseur.</p>

      <?php if (!empty($d['message'])): ?>
        <div class="alerte <?= h($d['message']['type']) ?>"><?= h($d['message']['texte']) ?></div>
      <?php endif; ?>

      <div class="grille">

        <div class="carte form-carte">
          <h2>Nouvelle réclamation</h2>
          <?php if (empty($d['factures'])): ?>
            <div class="vide" style="padding:20px 0;">
              <?= icone('facture') ?>
              <b>Aucune facture</b>
              Vous pourrez faire une réclamation dès que vous aurez une facture.
            </div>
          <?php else: ?>
          <form method="POST" action="index.php?page=reclamations" id="formRec">
            <label for="id_facture">Facture concernée</label>
            <select name="id_facture" id="id_facture" required>
              <option value="">— Choisir une facture —</option>
              <?php foreach ($d['factures'] as $f): ?>
                <option value="<?= (int) $f['id'] ?>" <?= $f['id'] === $d['preselection'] ? 'selected' : '' ?>>
                  <?= h($f['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <label for="type">Type de réclamation</label>
            <select name="type" id="type" required>
              <option value="">— Choisir un type —</option>
              <?php foreach ($d['types'] as $t): ?>
                <option value="<?= h($t) ?>"><?= h($t) ?></option>
              <?php endforeach; ?>
            </select>

            <div id="blocPrecision" style="display:none;">
              <label for="precision">Précisez le type</label>
              <input type="text" name="precision" id="precision" maxlength="80" placeholder="Ex : erreur de relevé, coupure...">
            </div>

            <label for="description">Description</label>
            <textarea name="description" id="description" maxlength="1000" required
                      placeholder="Décrivez votre problème le plus précisément possible..."></textarea>
            <div class="compteur"><span id="nbCar">0</span> / 1000</div>

            <button type="submit" class="btn-envoyer">Envoyer la réclamation</button>
          </form>
          <?php endif; ?>
        </div>

        <div>
          <h2 class="liste-titre">Historique de mes réclamations</h2>
          <div class="carte">
          <?php if (empty($d['reclamations'])): ?>
            <div class="vide">
              <?= icone('message') ?>
              <b>Aucune réclamation</b>
              Vos réclamations apparaîtront ici.
            </div>
          <?php else: ?>
            <?php foreach ($d['reclamations'] as $r): ?>
              <div class="rec">
                <div class="rec-tete">
                  <b><?= h($r['numero']) ?> — <?= h($r['type']) ?></b>
                  <span class="statut <?= h($r['classe']) ?>"><?= h($r['statut']) ?></span>
                </div>
                <div class="rec-meta">Le <?= h($r['date']) ?> · Facture <?= h($r['facture']) ?></div>
                <p><?= h($r['description']) ?></p>
                <?php if (!empty($r['reponse'])): ?>
                  <div class="reponse">
                    <small>Réponse du fournisseur<?= $r['dateReponse'] ? ' — ' . h($r['dateReponse']) : '' ?></small>
                    <?= nl2br(h($r['reponse'])) ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>

<script>
  const type = document.getElementById('type');
  const bloc = document.getElementById('blocPrecision');
  const prec = document.getElementById('precision');
  const desc = document.getElementById('description');
  const nb   = document.getElementById('nbCar');
  if (type) {
    type.addEventListener('change', () => {
      const autre = type.value === 'Autre';
      bloc.style.display = autre ? 'block' : 'none';
      prec.required = autre;
      if (!autre) prec.value = '';
    });
    desc.addEventListener('input', () => { nb.textContent = desc.value.length; });
    document.getElementById('formRec').addEventListener('submit', e => {
      e.target.querySelector('button[type=submit]').disabled = true;
    });
  }
</script>
</body>
</html>