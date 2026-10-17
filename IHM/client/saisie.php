<?php
/* ============================================================
   FRONT-END de la saisie mensuelle (maquette Fluxio)
   Les valeurs ci-dessous sont des EXEMPLES : à remplacer plus
   tard par les vraies données venant de la base.
   ============================================================ */
$u = $_SESSION['user'];
$initiales = mb_strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1));

$compteurs = [
    ['id' => 1, 'label' => 'Eau',         'numero' => 'EAU-04821',  'unite' => 'm³',  'icone' => 'goutte'],
    ['id' => 2, 'label' => 'Électricité', 'numero' => 'ELEC-29460', 'unite' => 'kWh', 'icone' => 'eclair'],
];
$compteurChoisi = 2;

$moisPrecedent       = 'Juin 2025';
$indexPrecedent      = '4 620';
$datePhotoPrecedente = '24 juin 2025';
$photoPrecedente     = null;        // chemin de l'image, ou null s'il n'y en a pas
$moisActuel          = 'Juillet 2025';
$fenetre             = 'du 18 au 31 juillet 2025';
$joursRestants       = 12;

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

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
        'agenda'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'upload'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>',
        'valider' => '<path d="M20 6L9 17l-5-5"/>',
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
<title>Saisie mensuelle — Fluxio</title>
<style>
  :root { --navy:#0b2340; --blue:#1aa7ec; --blue-d:#0b87c4; --bg:#f3f6fb; --line:#e3e9f2;
          --txt:#12233f; --mut:#6f7f95; --soft:#eaf6fd; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:"Segoe UI",system-ui,sans-serif; background:var(--bg); color:var(--txt); font-size:14px; }
  .i { width:18px; height:18px; flex:none; }
  button { font:inherit; cursor:pointer; }

  /* ---------- Menu latéral ---------- */
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
  .aide { margin-top:auto; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:10px; padding:14px; font-size:12px; }
  .aide strong { color:#fff; display:block; margin:6px 0 4px; }
  .aide a { color:var(--blue); text-decoration:none; font-weight:600; }
  .quit { margin-top:14px; border-top:1px solid rgba(255,255,255,.1); padding-top:12px; }
  .quit button { background:none; border:0; color:#c9d8ec; display:flex; align-items:center; gap:10px; padding:8px 12px; width:100%; }

  /* ---------- Contenu ---------- */
  .top { background:#fff; border-bottom:1px solid var(--line); height:58px; display:flex; align-items:center; justify-content:flex-end; gap:18px; padding:0 28px; }
  .cloche { position:relative; border:1px solid var(--line); background:#fff; border-radius:8px; width:34px; height:34px; display:grid; place-items:center; color:var(--mut); }
  .cloche::after { content:""; position:absolute; top:6px; right:7px; width:7px; height:7px; background:#ef4444; border-radius:50%; }
  .user { display:flex; align-items:center; gap:10px; padding-left:18px; border-left:1px solid var(--line); font-weight:600; font-size:12px; }
  .user small { display:block; color:var(--mut); font-weight:400; font-size:10px; }
  .avatar { width:34px; height:34px; border-radius:8px; background:var(--blue); color:#fff; display:grid; place-items:center; font-size:12px; font-weight:700; }

  .page { max-width:820px; margin:0 auto; padding:34px 24px 50px; }
  .eyebrow { font-size:10px; letter-spacing:.14em; font-weight:700; color:var(--blue-d); }
  h1 { font-size:28px; margin:6px 0 4px; }
  .sous { color:var(--mut); margin:0 0 22px; }

  .banniere { background:var(--soft); border:1px solid #cfe9f8; border-radius:12px; padding:14px 18px; display:flex; align-items:center; gap:14px; margin-bottom:18px; }
  .banniere .i { color:var(--blue-d); }
  .banniere b { display:block; }
  .banniere span { color:var(--mut); font-size:12px; }
  .pastille { margin-left:auto; background:#fff; border:1px solid #cfe9f8; border-radius:20px; padding:3px 10px; font-size:11px; font-weight:600; white-space:nowrap; }

  .carte { background:#fff; border:1px solid var(--line); border-radius:14px; padding:20px 22px; }
  .titre { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
  .num { width:24px; height:24px; border-radius:7px; background:var(--navy); color:#fff; font-size:12px; font-weight:700; display:grid; place-items:center; flex:none; }
  .titre b { font-size:15px; display:block; }
  .titre span { color:var(--mut); font-size:12px; }

  .choix { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .compteur { position:relative; display:flex; align-items:center; gap:12px; border:1px solid var(--line); border-radius:10px; padding:14px 16px; cursor:pointer; background:#fff; }
  .compteur input { position:absolute; right:16px; top:50%; transform:translateY(-50%); accent-color:var(--blue); width:16px; height:16px; }
  .compteur:has(input:checked) { border-color:var(--blue); box-shadow:0 0 0 3px rgba(26,167,236,.15); }
  .ico { width:34px; height:34px; border-radius:9px; display:grid; place-items:center; background:#e8f4fd; color:var(--blue-d); }
  .ico.elec { background:#fff4dc; color:#e09a06; }
  .compteur b { display:block; }
  .compteur small { color:var(--mut); font-size:11px; }

  .deux { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:18px; }
  .gris { background:#f8fafd; }
  .mois { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
  .mois small { font-size:9px; letter-spacing:.14em; color:var(--mut); font-weight:700; display:block; }
  .mois b { font-size:15px; }
  .index-prec { background:#fff; border:1px solid var(--line); border-radius:10px; padding:16px; display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
  .index-prec span { color:var(--mut); font-size:12px; }
  .index-prec strong { font-size:24px; }
  .index-prec em { font-style:normal; color:var(--mut); font-size:11px; margin-left:4px; }

  .photo-prec { display:flex; gap:14px; align-items:center; }
  .miniature { width:84px; height:58px; border-radius:8px; background:#3a3f45; display:grid; place-items:center; overflow:hidden; border:3px solid #cfd6de; }
  .miniature img { width:100%; height:100%; object-fit:cover; }
  .miniature .chiffres { background:#c5cf9a; color:#2b3a20; font:700 11px monospace; padding:3px 6px; border-radius:3px; }
  .photo-prec small { display:block; color:var(--mut); font-size:11px; }
  .photo-prec a { color:var(--blue-d); font-size:11px; font-weight:600; text-decoration:none; }

  label.champ { display:block; font-size:12px; font-weight:700; margin-bottom:6px; }
  .saisie { display:flex; }
  .saisie input { flex:1; min-width:0; padding:12px 14px; border:1px solid var(--line); border-radius:8px 0 0 8px; font:inherit; background:#fff; }
  .saisie input:focus { outline:2px solid var(--blue); }
  .saisie .u { padding:0 14px; display:grid; place-items:center; border:1px solid var(--line); border-left:0; border-radius:0 8px 8px 0; background:#f3f6fb; color:var(--mut); font-size:12px; }
  .aide-champ { color:var(--mut); font-size:11px; margin:8px 0 16px; }

  .zone { position:relative; border:2px dashed #c9d5e4; border-radius:10px; text-align:center; padding:22px 14px; background:#fbfcfe; margin-bottom:16px; transition:.15s; }
  .zone:hover, .zone.survol { border-color:var(--blue); background:var(--soft); }
  .zone input { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
  .zone .rond { width:34px; height:34px; border-radius:50%; background:#e8f4fd; color:var(--blue-d); display:grid; place-items:center; margin:0 auto 8px; }
  .zone b { display:block; font-size:12px; }
  .zone small { color:var(--mut); font-size:10px; }
  .apercu { display:none; margin-bottom:16px; align-items:center; gap:12px; background:var(--soft); border-radius:10px; padding:10px; }
  .apercu img { width:64px; height:48px; object-fit:cover; border-radius:6px; }
  .apercu span { font-size:12px; word-break:break-all; }

  .valider { width:100%; background:var(--blue); color:#fff; border:0; border-radius:8px; padding:13px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px; }
  .valider:hover { background:var(--blue-d); }
  .conseil { margin-top:16px; color:var(--mut); font-size:11px; display:flex; gap:8px; align-items:center; }
  .conseil i { width:16px; height:16px; border-radius:50%; background:#dbeefa; color:var(--blue-d); font-style:normal; font-size:10px; font-weight:700; display:grid; place-items:center; }

  @media (max-width:900px) {
    .app { grid-template-columns:1fr; } .side { position:static; height:auto; }
    .choix, .deux { grid-template-columns:1fr; } .aide { display:none; }
  }
</style>
</head>
<body>
<div class="app">

  <!-- ============ MENU LATÉRAL ============ -->
  <aside class="side">
    <div class="brand"><span class="logo"><?= icone('goutte') ?></span>
      <div>Fluxio<small>ÉNERGIE &amp; EAU</small></div></div>
    <div class="tag">ESPACE CLIENT</div>
    <nav>
      <a href="index.php?page=client"><?= icone('accueil') ?> Accueil</a>
      <a href="index.php?page=saisie" class="actif"><?= icone('saisie') ?> Saisie mensuelle</a>
      <a href="#"><?= icone('facture') ?> Mes factures</a>
      <a href="#"><?= icone('message') ?> Mes réclamations</a>
      <a href="#"><?= icone('cloche') ?> Notifications <span class="badge">3</span></a>
    </nav>
    <div class="aide">
      <span class="num" style="width:20px;height:20px">?</span>
      <strong>Besoin d'aide ?</strong>
      Notre équipe vous répond du lundi au vendredi.
      <br><br><a href="#">Centre d'aide</a>
    </div>
        <nav>
      <a href="index.php?page=motdepasse"><?= icone('cle') ?> Mon mot de passe</a>
    </nav>
    <form class="quit" method="post" action="index.php?page=logout">
      <button type="submit"><?= icone('sortie') ?> Se déconnecter</button>
    </form>
  </aside>

  <!-- ============ CONTENU ============ -->
  <div>
    <header class="top">
      <span class="cloche"><?= icone('cloche') ?></span>
      <div class="user">
        <span class="avatar"><?= h($initiales) ?></span>
        <div><?= h($u['prenom'] . ' ' . $u['nom']) ?><small>Espace client</small></div>
      </div>
    </header>

    <main class="page">
      <div class="eyebrow">ESPACE CLIENT</div>
      <h1>Saisie mensuelle</h1>
      <p class="sous">Transmettez votre relevé de compteur pour le mois en cours.</p>

      <div class="banniere">
        <?= icone('agenda') ?>
        <div><b>Fenêtre de saisie ouverte</b>
          <span>Vous pouvez saisir votre relevé <?= h($fenetre) ?>.</span></div>
        <span class="pastille"><?= (int) $joursRestants ?> jours restants</span>
      </div>

      <form method="post" action="index.php?page=saisie" enctype="multipart/form-data">

        <!-- 1. Choix du compteur -->
        <section class="carte">
          <div class="titre"><span class="num">1</span>
            <div><b>Choisissez votre compteur</b><span>Sélectionnez le type de consommation à relever</span></div></div>
          <div class="choix">
            <?php foreach ($compteurs as $c): ?>
              <label class="compteur">
                <span class="ico <?= $c['icone'] === 'eclair' ? 'elec' : '' ?>"><?= icone($c['icone']) ?></span>
                <span><b><?= h($c['label']) ?></b><small>Compteur n° <?= h($c['numero']) ?></small></span>
                <input type="radio" name="compteur" value="<?= (int) $c['id'] ?>"
                       data-unite="<?= h($c['unite']) ?>" <?= $c['id'] === $compteurChoisi ? 'checked' : '' ?> required>
              </label>
            <?php endforeach; ?>
          </div>
        </section>

        <div class="deux">
          <!-- 2. Mois précédent -->
          <section class="carte gris">
            <div class="mois">
              <div style="display:flex;gap:12px;align-items:center">
                <span class="num">2</span>
                <div><small>MOIS PRÉCÉDENT</small><b><?= h($moisPrecedent) ?></b></div>
              </div>
            </div>
            <div class="index-prec">
              <span>Index relevé</span>
              <strong><?= h($indexPrecedent) ?><em data-unite-texte>kWh</em></strong>
            </div>
            <div class="photo-prec">
              <div class="miniature">
                <?php if ($photoPrecedente): ?>
                  <img src="<?= h($photoPrecedente) ?>" alt="Photo du compteur du mois précédent">
                <?php else: ?>
                  <span class="chiffres">004620</span>
                <?php endif; ?>
              </div>
              <div>
                <b style="font-size:12px">Photo du compteur</b>
                <small>Ajoutée le <?= h($datePhotoPrecedente) ?></small>
                <a href="#">Agrandir l'aperçu</a>
              </div>
            </div>
          </section>

          <!-- 3. Mois actuel -->
          <section class="carte">
            <div class="mois">
              <div style="display:flex;gap:12px;align-items:center">
                <span class="num">3</span>
                <div><small>MOIS ACTUEL</small><b><?= h($moisActuel) ?></b></div>
              </div>
            </div>

            <label class="champ" for="nouvel_index">Nouvel index <span style="color:#ef4444">*</span></label>
            <div class="saisie">
              <input type="number" id="nouvel_index" name="nouvel_index" placeholder="Ex. 4932"
                     min="0" step="0.01" inputmode="decimal" required>
              <span class="u" data-unite-texte>kWh</span>
            </div>
            <p class="aide-champ">L'index doit être supérieur à celui du mois précédent.</p>

            <div class="zone" id="zone">
              <input type="file" id="photo" name="photo" accept="image/jpeg,image/png" required>
              <div class="rond"><?= icone('upload') ?></div>
              <b>Déposez la photo du compteur ici</b>
              <small>ou cliquez pour parcourir · JPG, PNG · 5 Mo max</small>
            </div>
            <div class="apercu" id="apercu"><img id="apercuImg" alt=""><span id="apercuNom"></span></div>

            <button type="submit" class="valider"><?= icone('valider') ?> Valider ma saisie</button>
          </section>
        </div>
      </form>

      <p class="conseil"><i>i</i> Conseil : assurez-vous que les chiffres du compteur sont nets et entièrement visibles sur la photo.</p>
    </main>
  </div>
</div>

<script>
  // 1) L'unité (kWh / m³) suit le compteur choisi
  const radios = document.querySelectorAll('input[name="compteur"]');
  function majUnite() {
    const choisi = document.querySelector('input[name="compteur"]:checked');
    if (!choisi) return;
    document.querySelectorAll('[data-unite-texte]').forEach(el => el.textContent = choisi.dataset.unite);
  }
  radios.forEach(r => r.addEventListener('change', majUnite));
  majUnite();

  // 2) Aperçu de la photo choisie + contrôle simple (le vrai contrôle se fait côté serveur)
  const fichier = document.getElementById('photo');
  const zone = document.getElementById('zone');
  const apercu = document.getElementById('apercu');
  fichier.addEventListener('change', () => {
    const f = fichier.files[0];
    if (!f) { apercu.style.display = 'none'; return; }
    if (!['image/jpeg', 'image/png'].includes(f.type) || f.size > 5 * 1024 * 1024) {
      alert('Image JPG ou PNG de 5 Mo maximum, svp.');
      fichier.value = '';
      apercu.style.display = 'none';
      return;
    }
    document.getElementById('apercuImg').src = URL.createObjectURL(f);
    document.getElementById('apercuNom').textContent = f.name;
    apercu.style.display = 'flex';
  });
  ['dragenter', 'dragover'].forEach(e => zone.addEventListener(e, () => zone.classList.add('survol')));
  ['dragleave', 'drop'].forEach(e => zone.addEventListener(e, () => zone.classList.remove('survol')));
</script>
</body>
</html>