<?php
$u = $_SESSION['user'];
$initiales = mb_strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1));

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('icone')) {
    function icone($nom)
    {
        $p = [
            'accueil' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
            'saisie'  => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
            'contrat' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>',
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
}

$groupes  = $d['groupes'];
$resilies = $d['resilies'];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mes contrats — Fluxio</title>
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
  .side .tag { background:rgba(255,255,255,.08); font-size:10px; letter-spacing:.14em; font-weight:600; padding:7px 10px; border-radius:6px; margin-bottom:14px; color:#9fc0e6; }
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


  .resume { color:var(--mut); margin:0 0 20px; }
  .vide { background:var(--soft); border:1px solid #cfe9f8; color:#0b5f8a; border-radius:12px; padding:18px 20px; }
  .logement { margin-bottom:24px; }
  .logement-t { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:12px; }
  .logement-t b { font-size:15px; }
  .logement-t .adr { color:var(--mut); font-size:12px; flex-basis:100%; }
  .pill { background:var(--soft); color:var(--blue-d); border-radius:20px; padding:2px 10px; font-size:11px; font-weight:600; }
  .pill.act { background:#fff4dc; color:#8a5300; }
  .pill.fin { background:#eef1f5; color:var(--mut); }
  .cartes { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .ct { background:#fff; border:1px solid var(--line); border-radius:14px; padding:16px 18px; }
  .ct-h { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
  .ico { width:36px; height:36px; border-radius:9px; display:grid; place-items:center; background:#e8f4fd; color:var(--blue-d); flex:none; }
  .ico.elec { background:#fff4dc; color:#e09a06; }
  .ct-h b { display:block; font-size:15px; }
  .ct-h small { color:var(--mut); font-size:11px; letter-spacing:.08em; font-weight:700; }
  .ct dl { margin:0; display:grid; grid-template-columns:auto 1fr; gap:6px 14px; font-size:13px; }
  .ct dt { color:var(--mut); }
  .ct dd { margin:0; font-weight:600; text-align:right; overflow-wrap:anywhere; }
  .ct.ferme { opacity:.75; }
  details.tranches { margin-top:12px; border-top:1px dashed var(--line); padding-top:10px; font-size:13px; }
  details.tranches summary { cursor:pointer; font-weight:700; color:var(--blue-d); }
  details.tranches ul { margin:10px 0 6px; padding-left:18px; }
  details.tranches li { margin-bottom:3px; }
  details.tranches small { color:var(--mut); font-size:11px; }
  details.resilies { margin-top:8px; }
  details.resilies summary { cursor:pointer; font-weight:700; margin-bottom:14px; }
  @media (max-width:760px) { .cartes { grid-template-columns:1fr; } .app { grid-template-columns:1fr; } .side { display:none; } }
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
      <a href="index.php?page=mes_contrats" class="actif"><?= icone('contrat') ?> Mes contrats</a>
      <a href="index.php?page=factures"><?= icone('facture') ?> Mes factures</a>
      <a href="index.php?page=reclamations"><?= icone('message') ?> Mes réclamations</a>
      <a href="index.php?page=notifications"><?= icone('cloche') ?> Notifications</a>
    </nav>
    <div class="aide">
      <strong>Besoin d'aide ?</strong>
      Notre équipe vous répond du lundi au vendredi.
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
      <div class="user">
        <span class="avatar"><?= h($initiales) ?></span>
        <div><?= h($u['prenom'] . ' ' . $u['nom']) ?><small>Espace client</small></div>
      </div>
    </header>

    <main class="page">
      <div class="eyebrow">ESPACE CLIENT</div>
      <h1>Mes contrats</h1>
      <p class="sous">Chaque logement ou local, avec son quartier, son type d'activité et le tarif appliqué.</p>

      <?php if (!$groupes && !$resilies): ?>
        <div class="vide">Aucun contrat pour le moment. Votre fournisseur doit d'abord créer votre contrat et activer votre compteur.</div>
      <?php else: ?>

        <?php if ($d['nbActifs']): ?>
          <p class="resume"><?= (int) $d['nbActifs'] ?> contrat<?= $d['nbActifs'] > 1 ? 's' : '' ?> actif<?= $d['nbActifs'] > 1 ? 's' : '' ?>
            dans <?= (int) $d['nbLogements'] ?> logement<?= $d['nbLogements'] > 1 ? 's' : '' ?> / local<?= $d['nbLogements'] > 1 ? 'aux' : '' ?>.</p>
        <?php endif; ?>

        <?php foreach ($groupes as $g): ?>
          <section class="logement">
            <div class="logement-t">
              <b><?= h($g['titre']) ?></b>
              <span class="pill act"><?= h($g['activite']) ?></span>
              <?php if ($g['adresse'] !== ''): ?><span class="adr"><?= h($g['adresse']) ?></span><?php endif; ?>
            </div>
            <div class="cartes">
              <?php foreach ($g['contrats'] as $c): ?>
                <article class="ct">
                  <div class="ct-h">
                    <span class="ico <?= $c['icone'] === 'eclair' ? 'elec' : '' ?>"><?= icone($c['icone']) ?></span>
                    <div><small>CONTRAT N° <?= (int) $c['id'] ?></small><b><?= h($c['label']) ?></b></div>
                  </div>
                  <dl>
                    <dt>Compteur</dt><dd><?= h($c['compteur']) ?></dd>
                    <dt>Tarif appliqué</dt><dd><?= h($c['tarif']) ?></dd>
                    <dt>Redevance fixe</dt><dd><?= h($c['redevance']) ?></dd>
                    <dt>Seuil d'alerte</dt><dd><?= h($c['seuil']) ?></dd>
                    <dt>Début</dt><dd><?= h($c['debut']) ?></dd>
                    <dt>Index de départ</dt><dd><?= h($c['indexDebut']) ?> <?= h($c['unite']) ?></dd>
                  </dl>
                  <?php if (!empty($c['tranches'])): ?>
                    <details class="tranches">
                      <summary>Voir les tranches de prix</summary>
                      <ul><?php foreach ($c['tranches'] as $tx): ?><li><?= h($tx) ?></li><?php endforeach; ?></ul>
                      <small>Le prix de la tranche atteinte s'applique à toute la consommation du mois.</small>
                    </details>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>

        <?php if ($resilies): ?>
          <details class="resilies">
            <summary>Contrats terminés (<?= count($resilies) ?>)</summary>
            <div class="cartes">
              <?php foreach ($resilies as $c): ?>
                <article class="ct ferme">
                  <div class="ct-h">
                    <span class="ico <?= $c['icone'] === 'eclair' ? 'elec' : '' ?>"><?= icone($c['icone']) ?></span>
                    <div><small>CONTRAT N° <?= (int) $c['id'] ?></small><b><?= h($c['label']) ?></b></div>
                    <span class="pill fin" style="margin-left:auto">Terminé</span>
                  </div>
                  <dl>
                    <dt>Compteur</dt><dd><?= h($c['compteur']) ?></dd>
                    <dt>Quartier</dt><dd><?= h($c['quartier']) ?> · <?= h($c['activite']) ?></dd>
                    <dt>Période</dt><dd><?= h($c['debut']) ?> → <?= h($c['fin']) ?></dd>
                    <?php if ($c['indexFin'] !== null): ?>
                      <dt>Index de clôture</dt><dd><?= h($c['indexFin']) ?> <?= h($c['unite']) ?></dd>
                    <?php endif; ?>
                  </dl>
                </article>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endif; ?>

      <?php endif; ?>
    </main>
  </div>
</div>
</body>
</html>