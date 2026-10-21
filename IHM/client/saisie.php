<?php

$u = $_SESSION['user'];
$initiales = mb_strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1));

$compteurs = $d['compteurs'];
$choisi    = $d['choisi'];

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
        'alerte'  => '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
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
  [hidden] { display:none !important; }

  
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
  .banniere.ferme { background:#fff4e5; border-color:#f6d9a8; }
  .banniere.ferme .i { color:#c77700; }
  .pastille { margin-left:auto; background:#fff; border:1px solid #cfe9f8; border-radius:20px; padding:3px 10px; font-size:11px; font-weight:600; white-space:nowrap; }
  .banniere.ferme .pastille { border-color:#f6d9a8; }

 
  .alerte { display:flex; gap:12px; align-items:flex-start; border-radius:12px; padding:14px 18px; margin-bottom:18px; border:1px solid; }
  .alerte b { display:block; margin-bottom:2px; }
  .alerte span, .alerte div { font-size:13px; }
  .alerte.succes   { background:#eafaf1; border-color:#b7e8cb; color:#14653a; }
  .alerte.anomalie { background:#fff4e5; border-color:#f6d9a8; color:#8a5300; }
  .alerte.erreur   { background:#fdecec; border-color:#f5b9b9; color:#9b1c1c; }
  .alerte.info     { background:var(--soft); border-color:#cfe9f8; color:#0b5f8a; }
  .alerte.interne  { margin:0 0 16px; }

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
  .miniature { width:84px; height:58px; border-radius:8px; background:#3a3f45; display:grid; place-items:center; overflow:hidden; border:3px solid #cfd6de; flex:none; }
  .miniature img { width:100%; height:100%; object-fit:cover; }
  .miniature .chiffres { background:#c5cf9a; color:#2b3a20; font:700 10px monospace; padding:3px 6px; border-radius:3px; }
  .photo-prec small { display:block; color:var(--mut); font-size:11px; }
  .photo-prec a { color:var(--blue-d); font-size:11px; font-weight:600; text-decoration:none; cursor:pointer; }

  fieldset { border:0; padding:0; margin:0; min-width:0; }
  fieldset:disabled { opacity:.55; }
  fieldset:disabled .zone { cursor:not-allowed; }
  label.champ { display:block; font-size:12px; font-weight:700; margin-bottom:6px; }
  .saisie { display:flex; }
  .saisie input { flex:1; min-width:0; padding:12px 14px; border:1px solid var(--line); border-radius:8px 0 0 8px; font:inherit; background:#fff; }
  .saisie input:focus { outline:2px solid var(--blue); }
  .saisie .u { padding:0 14px; display:grid; place-items:center; border:1px solid var(--line); border-left:0; border-radius:0 8px 8px 0; background:#f3f6fb; color:var(--mut); font-size:12px; }
  .aide-champ { color:var(--mut); font-size:11px; margin:8px 0 16px; }
  .avert { color:#8a5300; background:#fff4e5; border-radius:8px; padding:8px 10px; font-size:11px; margin:-8px 0 16px; }

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
  .valider:disabled { background:#9fb3c8; cursor:not-allowed; }

  .facture-zone { margin-top:14px; border-top:1px solid var(--line); padding-top:14px; }
  .btn-sec { width:100%; background:#fff; color:var(--blue-d); border:1px solid var(--blue); border-radius:8px; padding:11px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px; }
  .btn-sec:disabled { color:#9aa8ba; border-color:var(--line); background:#f6f8fb; cursor:not-allowed; }
  .note-facture { color:var(--mut); font-size:11px; margin:8px 0 0; text-align:center; }

  .conseil { margin-top:16px; color:var(--mut); font-size:11px; display:flex; gap:8px; align-items:center; }
  .conseil i { width:16px; height:16px; border-radius:50%; background:#dbeefa; color:var(--blue-d); font-style:normal; font-size:10px; font-weight:700; display:grid; place-items:center; }


  .modale { position:fixed; inset:0; background:rgba(11,35,64,.7); display:grid; place-items:center; z-index:50; padding:20px; }
  .modale .boite { background:#fff; border-radius:14px; padding:26px; width:100%; max-width:560px; max-height:92vh; overflow:auto; position:relative; }
  .modale .x { position:absolute; top:12px; right:14px; border:0; background:none; font-size:24px; color:var(--mut); line-height:1; }
  .modale h2 { margin:4px 0 14px; font-size:20px; }
  .grille { display:grid; grid-template-columns:1fr 1fr; gap:10px 20px; margin-bottom:16px; font-size:12px; }
  .grille small { display:block; color:var(--mut); font-size:10px; letter-spacing:.08em; font-weight:700; }
  .lignes { width:100%; border-collapse:collapse; font-size:13px; margin-bottom:14px; }
  .lignes td { padding:8px 0; border-bottom:1px solid var(--line); }
  .lignes td:last-child { text-align:right; font-weight:600; }
  .lignes tr.total td { border-bottom:0; font-size:16px; font-weight:800; }
  .photos { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .photos figure { margin:0; }
  .photos img { width:100%; height:110px; object-fit:cover; border-radius:8px; background:#e8edf4; }
  .photos figcaption { font-size:10px; color:var(--mut); margin-top:4px; }
  .modale-photo img { max-width:92vw; max-height:90vh; border-radius:10px; }


  .boite.facture { max-width:720px; }
  .f-entete { display:flex; align-items:center; gap:10px; margin-bottom:12px; padding-right:30px; flex-wrap:wrap; }
  .f-badge { font-size:10px; font-weight:700; border-radius:6px; padding:4px 9px; }
  .f-badge.elec { background:#fff4dc; color:#c77700; }
  .f-badge.eau { background:#e8f4fd; color:var(--blue-d); }
  .f-gen { color:var(--mut); font-size:11px; }
  .f-statut { margin-left:auto; font-size:11px; font-weight:700; border-radius:20px; padding:3px 10px; }
  .f-statut.impaye { background:#fdecec; color:#9b1c1c; }
  .f-statut.paye { background:#eafaf1; color:#14653a; }
  .f-sous { display:block; color:var(--mut); font-size:11px; margin-top:2px; }
  .f-bloc b { display:block; font-size:14px; margin-top:2px; }
  .f-bloc { border-top:1px solid var(--line); border-bottom:1px solid var(--line); padding:14px 0; margin-bottom:18px; }
  .f-table { width:100%; border-collapse:collapse; border:1px solid var(--line); border-radius:10px; overflow:hidden; font-size:13px; margin-bottom:16px; }
  .f-table th { background:#f6f8fb; color:var(--mut); font-size:9px; letter-spacing:.1em; text-align:left; padding:10px 14px; }
  .f-table th:last-child, .f-table td:last-child { text-align:right; }
  .f-table td { padding:12px 14px; vertical-align:top; }
  .f-table td:last-child { font-weight:700; }
  .f-totaux { margin-left:auto; width:min(280px,100%); font-size:13px; margin-bottom:18px; }
  .f-totaux div { display:flex; justify-content:space-between; padding:5px 0; }
  .f-totaux span { color:var(--mut); }
  .f-totaux .ttc { border-top:1px solid var(--line); margin-top:6px; padding-top:10px; font-size:16px; }
  .f-totaux .ttc span { color:var(--txt); font-weight:800; }
  .f-titre { font-size:11px; color:var(--mut); margin-bottom:8px; }
  .photos figcaption b { display:block; font-size:12px; color:var(--txt); }
  .f-pied { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:18px; padding-top:14px; border-top:1px solid var(--line); font-size:10px; color:var(--mut); }
  .f-logo { display:flex; align-items:center; gap:8px; font-weight:700; font-size:14px; color:var(--txt); }
  .f-logo-i { width:28px; height:28px; border-radius:8px; background:var(--blue); color:#fff; display:grid; place-items:center; }

  @media (max-width:900px) {
    .app { grid-template-columns:1fr; } .side { position:static; height:auto; }
    .choix, .deux, .grille, .photos { grid-template-columns:1fr; } .aide { display:none; }
  }
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
      <a href="index.php?page=saisie" class="actif"><?= icone('saisie') ?> Saisie mensuelle</a>
     <a href="index.php?page=factures"><?= icone('facture') ?> Mes factures</a>
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

      <!-- Message après envoi (succès / anomalie / erreur) -->
      <?php if ($d['flash']): ?>
        <div class="alerte <?= h($d['flash']['type']) ?>" role="alert">
          <?= icone($d['flash']['type'] === 'succes' ? 'valider' : 'alerte') ?>
          <div><?= h($d['flash']['texte']) ?></div>
        </div>
      <?php endif; ?>


      <div class="banniere <?= $d['ouverte'] ? '' : 'ferme' ?>">
        <?= icone('agenda') ?>
        <div><b><?= $d['ouverte'] ? 'Fenêtre de saisie ouverte' : 'Saisie fermée' ?></b>
          <span><?= h($d['fenetreTexte']) ?></span></div>
        <span class="pastille"><?= h($d['pastille']) ?></span>
      </div>

      <?php if (!$compteurs): ?>
        <div class="alerte info"><?= icone('alerte') ?>
          <div>Aucun compteur n'est associé à votre compte. Veuillez contacter le fournisseur.</div></div>
      <?php else: ?>

      <form method="post" action="index.php?page=saisie" enctype="multipart/form-data" id="formSaisie">

        
        <section class="carte">
          <div class="titre"><span class="num">1</span>
            <div><b>Choisissez votre compteur</b><span>Sélectionnez le type de consommation à relever</span></div></div>
          <div class="choix">
            <?php foreach ($compteurs as $c): ?>
              <label class="compteur">
                <span class="ico <?= $c['icone'] === 'eclair' ? 'elec' : '' ?>"><?= icone($c['icone']) ?></span>
                <span><b><?= h($c['label']) ?></b><small>Compteur n° <?= h($c['numero']) ?></small></span>
                <input type="radio" name="compteur" value="<?= (int) $c['id'] ?>"
                       <?= $c['id'] === $choisi ? 'checked' : '' ?> required>
              </label>
            <?php endforeach; ?>
          </div>
        </section>

        <div class="deux">
      
          <section class="carte gris">
            <div class="mois">
              <div style="display:flex;gap:12px;align-items:center">
                <span class="num">2</span>
                <div><small>MOIS PRÉCÉDENT</small><b id="moisPrec"></b></div>
              </div>
            </div>
            <div class="index-prec">
              <span>Index relevé</span>
              <strong><span id="indexPrec"></span><em data-unite-texte></em></strong>
            </div>
            <div class="photo-prec">
              <div class="miniature" id="miniature"></div>
              <div>
                <b style="font-size:12px">Photo du compteur</b>
                <small id="photoDate"></small>
                <a id="agrandir" role="button" tabindex="0">Agrandir l'aperçu</a>
              </div>
            </div>
          </section>

  
          <section class="carte">
            <div class="mois">
              <div style="display:flex;gap:12px;align-items:center">
                <span class="num">3</span>
                <div><small>MOIS ACTUEL</small><b id="moisActuel"></b></div>
              </div>
            </div>

       
            <div class="alerte interne" id="etat" hidden>
              <span id="etatIcOk"><?= icone('valider') ?></span><span id="etatIcAl"><?= icone('alerte') ?></span>
              <div><b id="etatTitre"></b><span id="etatTexte"></span></div>
            </div>

            <fieldset id="champs">
              <label class="champ" for="nouvel_index">Nouvel index <span style="color:#ef4444">*</span></label>
              <div class="saisie">
                <input type="number" id="nouvel_index" name="nouvel_index" placeholder="Ex. 4932"
                       min="0" step="0.01" inputmode="decimal" required>
                <span class="u" data-unite-texte></span>
              </div>
              <p class="aide-champ">L'index doit être supérieur à celui du mois précédent.</p>
              <p class="avert" id="avert" hidden>Cet index est inférieur au précédent. Vérifiez votre relevé (vous pouvez tout de même envoyer).</p>

              <div class="zone" id="zone">
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png" required>
                <div class="rond"><?= icone('upload') ?></div>
                <b>Déposez la photo du compteur ici</b>
                <small>ou cliquez pour parcourir · JPG, PNG · 5 Mo max</small>
              </div>
              <div class="apercu" id="apercu"><img id="apercuImg" alt=""><span id="apercuNom"></span></div>

              <button type="submit" class="valider" id="btnValider"><?= icone('valider') ?> Valider ma saisie</button>
            </fieldset>

        
            <div class="facture-zone">
              <button type="button" class="btn-sec" id="btnFacture" disabled><?= icone('facture') ?> Voir ma facture</button>
              <p class="note-facture" id="noteFacture"></p>
            </div>
          </section>
        </div>
      </form>

      <p class="conseil"><i>i</i> Conseil : assurez-vous que les chiffres du compteur sont nets et entièrement visibles sur la photo.</p>
      <?php endif; ?>
    </main>
  </div>
</div>


<div class="modale modale-photo" id="modalePhoto" hidden><img id="modalePhotoImg" alt="Photo du compteur"></div>


<div class="modale" id="modaleFacture" hidden>
  <div class="boite facture">
    <button type="button" class="x" data-fermer aria-label="Fermer">&times;</button>

    <div class="f-entete">
      <span class="f-badge" id="fBadge"></span>
      <span class="f-gen" id="fGen"></span>
      <span class="f-statut" id="fStatut"></span>
    </div>
    <div class="eyebrow">FACTURE MENSUELLE</div>
    <h2 id="fNumero"></h2>

    <div class="grille f-bloc">
      <div><small>CLIENT</small><b id="fClient"></b>
        <span class="f-sous" id="fAdresse"></span><span class="f-sous" id="fContact"></span></div>
      <div><small>PÉRIODE DE CONSOMMATION</small><b id="fPeriode"></b>
        <span class="f-sous" id="fCompteur"></span></div>
    </div>

    <table class="f-table">
      <thead><tr><th>DÉTAIL</th><th>CONSOMMATION</th><th>MONTANT HT</th></tr></thead>
      <tbody><tr>
        <td><b id="fDetail"></b><span class="f-sous" id="fIndex"></span></td>
        <td id="fConso"></td>
        <td id="fLigneHT"></td>
      </tr></tbody>
    </table>

    <div class="f-totaux">
      <div><span>Consommation totale</span><b id="fConso2"></b></div>
      <div><span>Montant HT</span><b id="fHT"></b></div>
      <div><span>TVA (18 %)</span><b id="fTVA"></b></div>
      <div class="ttc"><span>Montant TTC</span><b id="fTTC"></b></div>
    </div>

    <div class="f-titre">Justificatifs compteur</div>
    <div class="photos">
      <figure><img id="fPhotoAvant" alt="Photo du compteur du mois précédent">
        <figcaption><b>Mois précédent</b><span id="fIdxAvant"></span></figcaption></figure>
      <figure><img id="fPhotoApres" alt="Photo du compteur du mois actuel">
        <figcaption><b>Mois actuel</b><span id="fIdxApres"></span></figcaption></figure>
    </div>

    <div class="f-pied">
      <span class="f-logo"><span class="f-logo-i"><?= icone('goutte') ?></span> Fluxio</span>
      <span>Facture générée automatiquement après validation du relevé mensuel.</span>
    </div>
  </div>
</div>

<script>
  const DONNEES = <?= json_encode($d['payload'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
  const $ = id => document.getElementById(id);

  const ETATS = {
    verifie:  ['succes',   'Saisie enregistrée et vérifiée', 'Votre relevé de ce mois a bien été enregistré.'],
    anomalie: ['anomalie', 'Anomalie détectée', 'Votre saisie est en cours de traitement par le fournisseur. La facture est bloquée jusqu\'à la fin de ce traitement.'],
    resaisie: ['info',     'Nouvelle saisie demandée', 'Le fournisseur vous demande de saisir à nouveau votre index. Vérifiez votre compteur puis renvoyez votre relevé.']
  };
  let courant = null;

  function afficher(id) {
    const c = DONNEES[id];
    if (!c) return;
    courant = c;

    document.querySelectorAll('[data-unite-texte]').forEach(e => e.textContent = c.unite);
    $('moisActuel').textContent = c.moisActuel;
    $('moisPrec').textContent   = c.moisPrecedent;
    $('indexPrec').textContent  = c.indexPrecedent;

    // Photo du mois précédent
    const m = $('miniature');
    m.textContent = '';
    if (c.photo) {
      const img = document.createElement('img');
      img.src = c.photo; img.alt = 'Photo du compteur du mois précédent';
      m.appendChild(img);
    } else {
      const s = document.createElement('span');
      s.className = 'chiffres'; s.textContent = 'AUCUNE';
      m.appendChild(s);
    }
    $('photoDate').textContent = c.photo ? 'Ajoutée le ' + c.datePhoto : 'Aucune photo précédente';
    $('agrandir').hidden = !c.photo;

    // État de la saisie
    const e = ETATS[c.etat];
    $('etat').hidden = !e;
    if (e) {
      $('etat').className = 'alerte interne ' + e[0];
      $('etatTitre').textContent = e[1];
      $('etatTexte').textContent = e[2];
      $('etatIcOk').hidden = e[0] !== 'succes';
      $('etatIcAl').hidden = e[0] === 'succes';
    }
    $('champs').disabled = ['ferme', 'verifie', 'anomalie'].includes(c.etat);

    
    $('btnFacture').disabled = !c.facture;
    let note = 'Votre facture sera disponible après une saisie valide.';
    if (c.etat === 'anomalie') note = 'Facture bloquée tant que l\'anomalie n\'est pas traitée.';
    else if (c.etat === 'verifie') note = c.facture ? '' : 'Votre facture est en cours de génération.';
    $('noteFacture').textContent = note;

    $('nouvel_index').value = '';
    $('avert').hidden = true;
  }

  document.querySelectorAll('input[name="compteur"]').forEach(r =>
    r.addEventListener('change', () => afficher(r.value)));
  const coche = document.querySelector('input[name="compteur"]:checked');
  if (coche) afficher(coche.value);

  
  $('nouvel_index')?.addEventListener('input', ev => {
    const v = parseFloat(ev.target.value);
    $('avert').hidden = !(courant && !isNaN(v) && v < courant.indexPrecedentNum);
  });


  const fichier = $('photo'), zone = $('zone'), apercu = $('apercu');
  fichier?.addEventListener('change', () => {
    const f = fichier.files[0];
    if (!f) { apercu.style.display = 'none'; return; }
    if (!['image/jpeg', 'image/png'].includes(f.type) || f.size > 5 * 1024 * 1024) {
      alert('Image JPG ou PNG de 5 Mo maximum, svp.');
      fichier.value = ''; apercu.style.display = 'none'; return;
    }
    $('apercuImg').src = URL.createObjectURL(f);
    $('apercuNom').textContent = f.name;
    apercu.style.display = 'flex';
  });
  ['dragenter', 'dragover'].forEach(e => zone?.addEventListener(e, () => zone.classList.add('survol')));
  ['dragleave', 'drop'].forEach(e => zone?.addEventListener(e, () => zone.classList.remove('survol')));


  const formSaisie = $('formSaisie');
  if (formSaisie) formSaisie.addEventListener('submit', () => { $('btnValider').disabled = true; });


  function ouvrirPhoto(src) {
    if (!src) return;
    $('modalePhotoImg').src = src;
    $('modalePhoto').hidden = false;
  }
  $('agrandir')?.addEventListener('click', () => ouvrirPhoto(courant && courant.photo));
  $('agrandir')?.addEventListener('keydown', e => { if (e.key === 'Enter') ouvrirPhoto(courant && courant.photo); });


  $('btnFacture')?.addEventListener('click', () => {
    const f = courant && courant.facture;
    if (!f) return;
    const u = courant.unite;
    const txt = {
      fGen: 'Générée le ' + f.date, fStatut: f.paiement, fNumero: 'Facture N° ' + f.numero,
      fClient: f.client.nom, fAdresse: f.client.adresse || '',
      fContact: [f.client.tel, f.client.email].filter(Boolean).join(' · '),
      fPeriode: f.periode, fCompteur: 'Compteur : ' + f.compteur,
      fDetail: 'Consommation ' + f.typeLabel.toLowerCase(),
      fIndex: 'Index ' + f.indexPrec + ' → ' + f.indexAct + ' ' + u,
      fConso: f.conso + ' ' + u, fConso2: f.conso + ' ' + u,
      fLigneHT: f.ht + ' DH', fHT: f.ht + ' DH', fTVA: f.tva + ' DH', fTTC: f.ttc + ' DH',
      fIdxAvant: 'Index : ' + f.indexPrec + ' ' + u, fIdxApres: 'Index : ' + f.indexAct + ' ' + u
    };
    Object.keys(txt).forEach(k => $(k).textContent = txt[k]);
    $('fBadge').textContent = f.typeLabel;
    $('fBadge').className = 'f-badge ' + (f.type === 'Eau' ? 'eau' : 'elec');
    $('fStatut').className = 'f-statut ' + (f.paye ? 'paye' : 'impaye');
    [['fPhotoAvant', f.photoAvant], ['fPhotoApres', f.photoApres]].forEach(([id, src]) => {
      $(id).src = src || ''; $(id).hidden = !src;
    });
    $('modaleFacture').hidden = false;
  });


  document.querySelectorAll('.modale').forEach(mo => mo.addEventListener('click', e => {
    if (e.target === mo || e.target.hasAttribute('data-fermer')) mo.hidden = true;
  }));
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modale').forEach(mo => mo.hidden = true);
  });
</script>
</body>
</html>