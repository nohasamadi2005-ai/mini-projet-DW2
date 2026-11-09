<?php
require_once __DIR__ . '/../../traitement/auth.php';
exigerRole('client');
require_once __DIR__ . '/../../BD/connexion.php';

$idClient = $_SESSION['user']['id'];

// Notifications destinées au client (avec facture / réclamation liées si présentes)
$cnx = Connexion();
$sql = "SELECT ID_Notification, ID_Facture, ID_Reclamation, Message, Date_Envoi, Lu
        FROM notification
        WHERE ID_Client = ? AND (Destinataire = 'client' OR Destinataire IS NULL)
        ORDER BY Date_Envoi DESC, ID_Notification DESC";
$stmt = mysqli_prepare($cnx, $sql);
mysqli_stmt_bind_param($stmt, "i", $idClient);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$notifications = [];
while ($row = mysqli_fetch_assoc($res)) {
    $notifications[] = $row;
}

// Marquer les notifications comme lues (on garde l'état "non lu" d'avant pour l'affichage)
$sqlUpdate = "UPDATE notification SET Lu = 1 WHERE ID_Client = ? AND Lu = 0";
$stmtUpd = mysqli_prepare($cnx, $sqlUpdate);
mysqli_stmt_bind_param($stmtUpd, "i", $idClient);
mysqli_stmt_execute($stmtUpd);
mysqli_close($cnx);

// Date lisible : Aujourd'hui / Hier / date complète
function libelleDateNotif($date)
{
    $t = strtotime($date);
    $jour = date('Y-m-d', $t);
    if ($jour === date('Y-m-d')) {
        return "Aujourd’hui à " . date('H:i', $t);
    }
    if ($jour === date('Y-m-d', strtotime('-1 day'))) {
        return 'Hier à ' . date('H:i', $t);
    }
    return date('d/m/Y à H:i', $t);
}

// Catégorie, titre, couleur, icône et boutons de chaque notification
function decrireNotification($n)
{
    $m = $n['Message'];

    // 1) Réponse à une réclamation (testée en premier : ses messages peuvent contenir "surconsommation" ou "bloquée")
    if (!empty($n['ID_Reclamation'])) {
        $titre = 'Réponse à votre réclamation';
        if (preg_match('/REC-\d+/', $m, $r)) {
            $titre .= ' ' . $r[0];
        }
        return ['cat' => 'reclamation', 'classe' => 'c-reponse', 'icone' => 'message', 'titre' => $titre,
                'boutons' => [['Voir mes réclamations', 'index.php?page=reclamations', 'btn-n-prim']]];
    }
    // 2) Saisie refusée / bloquée : il faut ressaisir
    if (strpos($m, 'refusé') !== false || strpos($m, 'refusée') !== false || strpos($m, 'bloqué') !== false) {
        return ['cat' => 'alerte', 'classe' => 'c-urgent', 'icone' => 'saisie', 'titre' => 'Action requise : resaisie',
                'boutons' => [['Effectuer la resaisie maintenant', 'index.php?page=saisie', 'btn-n-danger']]];
    }
    // 3) Surconsommation : orienter vers une réclamation
    if (mb_stripos($m, 'surconsommation') !== false) {
        $lien = 'index.php?page=reclamations';
        if (!empty($n['ID_Facture'])) {
            $lien .= '&facture=' . (int) $n['ID_Facture'] . '&sous_type=Surconsommation';
        }
        return ['cat' => 'alerte', 'classe' => 'c-alerte', 'icone' => 'eclair', 'titre' => 'Surconsommation détectée',
                'boutons' => [['Déposer une réclamation', $lien, 'btn-n-warn']]];
    }
    // 4) Autre notification liée à une facture
    if (!empty($n['ID_Facture'])) {
        return ['cat' => 'facture', 'classe' => 'c-facture', 'icone' => 'facture', 'titre' => 'À propos d’une facture',
                'boutons' => [['Voir mes factures', 'index.php?page=factures', 'btn-n-prim'],
                              ['Faire une réclamation', 'index.php?page=reclamations&facture=' . (int) $n['ID_Facture'], 'btn-n-light']]];
    }
    // 5) Information simple
    return ['cat' => 'info', 'classe' => 'c-info', 'icone' => 'cloche', 'titre' => 'Information', 'boutons' => []];
}

$nbNonLues = 0;
foreach ($notifications as $i => $n) {
    $notifications[$i]['_d'] = decrireNotification($n);
    if (empty($n['Lu'])) {
        $nbNonLues++;
    }
}

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
<title>Notifications — Fluxio</title>
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
  .photo-rec { max-width:160px; border-radius:8px; margin-top:8px; display:block; }
  [hidden] { display:none !important; }
  @media (max-width:900px) { .grille { grid-template-columns:1fr; } }

  /* --- Notifications --- */
  .n-barre { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin:18px 0 14px; }
  .n-filtres { display:flex; gap:8px; flex-wrap:wrap; }
  .n-filtres button { border:1px solid var(--line); background:#fff; color:var(--mut); padding:7px 14px; border-radius:20px; font-weight:600; font-size:13px; cursor:pointer; }
  .n-filtres button.on { background:var(--blue); border-color:var(--blue); color:#fff; }
  .n-compte { color:var(--mut); font-size:13px; }
  .notif { display:flex; gap:14px; padding:16px 18px; border-top:1px solid var(--line); position:relative; background:#fff; }
  .notif:first-child { border-top:0; }
  .notif.nonlue { background:#F3F8FF; }
  .notif.nonlue::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; background:var(--blue); }
  .n-ico { flex:0 0 42px; height:42px; border-radius:12px; display:grid; place-items:center; }
  .n-ico svg { width:21px; height:21px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
  .n-corps { flex:1; min-width:0; }
  .n-tete { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; }
  .n-titre { font-weight:700; font-size:15px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .n-nouveau { background:var(--blue); color:#fff; font-size:10px; font-weight:700; letter-spacing:.5px; padding:2px 8px; border-radius:10px; }
  .n-date { color:var(--mut); font-size:12px; white-space:nowrap; }
  .n-msg { margin:6px 0 0; color:#334155; line-height:1.5; }
  .n-actions { margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; }
  .n-actions a { text-decoration:none; font-weight:600; font-size:13px; padding:8px 14px; border-radius:8px; display:inline-block; }
  .btn-n-prim { background:var(--blue); color:#fff; }
  .btn-n-prim:hover { background:var(--blue-d); }
  .btn-n-danger { background:#DC2626; color:#fff; }
  .btn-n-warn { background:#F59E0B; color:#fff; }
  .btn-n-light { background:#EEF2F6; color:#475569; }
  .c-urgent .n-ico, .n-ico.c-urgent { background:#FEE2E2; color:#DC2626; }
  .c-alerte .n-ico, .n-ico.c-alerte { background:#FEF3C7; color:#D97706; }
  .c-reponse .n-ico, .n-ico.c-reponse { background:#E0F2FE; color:#0284C7; }
  .c-facture .n-ico, .n-ico.c-facture { background:#DCFCE7; color:#16A34A; }
  .c-info .n-ico, .n-ico.c-info { background:#F1F5F9; color:#64748B; }
  .n-vide { display:none; }
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
      <a href="index.php?page=mes_contrats"><?= icone('facture') ?> Mes contrats</a>
      <a href="index.php?page=factures"><?= icone('facture') ?> Mes factures</a>
      <a href="index.php?page=reclamations"><?= icone('message') ?> Mes réclamations</a>
      <a href="index.php?page=notifications" class="actif"><?= icone('cloche') ?> Notifications</a>
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
      <h1>Notifications</h1>
      <p class="sous">Alertes sur vos consommations, actions à faire et réponses du fournisseur à vos réclamations.</p>

      <?php if (empty($notifications)): ?>
        <div class="carte" style="margin-top:18px;">
          <div class="vide">
            <?= icone('cloche') ?>
            <b>Aucune notification</b>
            Vous n’avez aucune notification pour le moment.
          </div>
        </div>
      <?php else: ?>

        <div class="n-barre">
          <div class="n-filtres" id="filtres">
            <button type="button" class="on" data-f="tout">Toutes</button>
            <button type="button" data-f="nonlue">Non lues (<?= (int) $nbNonLues ?>)</button>
            <button type="button" data-f="alerte">Alertes</button>
            <button type="button" data-f="reclamation">Réclamations</button>
          </div>
          <div class="n-compte"><?= count($notifications) ?> notification(s)</div>
        </div>

        <div class="carte" id="liste">
          <?php foreach ($notifications as $n): $d = $n['_d']; $nonLue = empty($n['Lu']); ?>
            <div class="notif <?= $nonLue ? 'nonlue' : '' ?>" data-cat="<?= h($d['cat']) ?>" data-nonlue="<?= $nonLue ? '1' : '0' ?>">
              <div class="n-ico <?= h($d['classe']) ?>"><?= icone($d['icone']) ?></div>
              <div class="n-corps">
                <div class="n-tete">
                  <div class="n-titre"><?= h($d['titre']) ?><?php if ($nonLue): ?><span class="n-nouveau">NOUVEAU</span><?php endif; ?></div>
                  <div class="n-date"><?= h(libelleDateNotif($n['Date_Envoi'])) ?></div>
                </div>
                <p class="n-msg"><?= nl2br(h($n['Message'])) ?></p>
                <?php if (!empty($d['boutons'])): ?>
                  <div class="n-actions">
                    <?php foreach ($d['boutons'] as [$texte, $lien, $classe]): ?>
                      <a href="<?= h($lien) ?>" class="<?= h($classe) ?>"><?= h($texte) ?></a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="vide n-vide" id="videFiltre">
            <?= icone('cloche') ?>
            <b>Aucune notification dans cette catégorie</b>
          </div>
        </div>

      <?php endif; ?>
    </main>
  </div>
</div>

<script>
  const boutons = document.querySelectorAll('#filtres button');
  const cartes  = document.querySelectorAll('#liste .notif');
  const vide    = document.getElementById('videFiltre');
  boutons.forEach(b => b.addEventListener('click', () => {
    boutons.forEach(x => x.classList.remove('on'));
    b.classList.add('on');
    const f = b.dataset.f;
    let visibles = 0;
    cartes.forEach(c => {
      const ok = f === 'tout' || (f === 'nonlue' && c.dataset.nonlue === '1') || c.dataset.cat === f;
      c.style.display = ok ? 'flex' : 'none';
      if (ok) visibles++;
    });
    vide.style.display = visibles ? 'none' : 'block';
  }));
</script>
</body>
</html>