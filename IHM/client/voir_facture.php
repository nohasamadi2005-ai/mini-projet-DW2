<?php
/** @var array $facture Données préparées par preparerFactureImprimable() */
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture <?= h($facture['numero']) ?> — Fluxio</title>
    <style>
        body { font-family: "Segoe UI", system-ui, sans-serif; background: #f3f6fb; padding: 30px; }
        .btns { max-width:720px; margin:0 auto 20px; display:flex; gap:10px; justify-content:center; }
        .btn-imprimer { background:#1aa7ec; color:#fff; border:0; border-radius:8px; padding:12px 24px; font-weight:700; cursor:pointer; }
        .btn-imprimer:hover { background:#0b87c4; }
        .btn-retour { background:#fff; color:#0b87c4; border:1px solid #1aa7ec; border-radius:8px; padding:12px 24px; font-weight:700; text-decoration:none; }
        @media print {
            .btns { display:none; }
            body { background:#fff; padding:0; }
            .boite.facture { box-shadow:none; padding:0; }
        }

        .boite.facture { max-width:720px; background:#fff; border-radius:14px; padding:26px; margin:auto; box-shadow:0 4px 15px rgba(0,0,0,.05); }
        .eyebrow { font-size:10px; letter-spacing:.14em; font-weight:700; color:#0b87c4; }
        h2 { margin:6px 0 16px; }
        .f-entete { display:flex; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap; }
        .f-badge { font-size:10px; font-weight:700; border-radius:6px; padding:4px 9px; }
        .f-badge.elec { background:#fff4dc; color:#c77700; }
        .f-badge.eau { background:#e8f4fd; color:#0b87c4; }
        .f-gen { color:#6f7f95; font-size:11px; }
        .f-statut { margin-left:auto; font-size:11px; font-weight:700; border-radius:20px; padding:3px 10px; }
        .f-statut.impaye { background:#fdecec; color:#9b1c1c; }
        .f-statut.paye { background:#eafaf1; color:#14653a; }
        .f-sous { display:block; color:#6f7f95; font-size:11px; margin-top:2px; }
        .f-bloc b { display:block; font-size:14px; margin-top:2px; }
        .f-bloc { border-top:1px solid #e3e9f2; border-bottom:1px solid #e3e9f2; padding:14px 0; margin-bottom:18px; }
        .grille { display:grid; grid-template-columns:1fr 1fr; gap:10px 20px; font-size:12px; }
        .grille small { display:block; color:#6f7f95; font-size:10px; letter-spacing:.08em; font-weight:700; }
        .f-table { width:100%; border-collapse:collapse; border:1px solid #e3e9f2; font-size:13px; margin-bottom:16px; }
        .f-table th { background:#f6f8fb; color:#6f7f95; font-size:9px; letter-spacing:.1em; text-align:left; padding:10px 14px; }
        .f-table th:last-child, .f-table td:last-child { text-align:right; }
        .f-table td { padding:12px 14px; vertical-align:top; }
        .f-table td:last-child { font-weight:700; }
        .f-totaux { margin-left:auto; width:min(280px,100%); font-size:13px; margin-bottom:18px; }
        .f-totaux div { display:flex; justify-content:space-between; padding:5px 0; }
        .f-totaux span { color:#6f7f95; }
        .f-totaux .ttc { border-top:1px solid #e3e9f2; margin-top:6px; padding-top:10px; font-size:16px; }
        .f-totaux .ttc span { color:#12233f; font-weight:800; }
        .f-titre { font-size:11px; color:#6f7f95; margin-bottom:8px; }
        .photos { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .photos figure { margin:0; }
        .photos img { width:100%; height:110px; object-fit:cover; border-radius:8px; background:#e8edf4; }
        .photos .vide { height:110px; border-radius:8px; background:#e8edf4; display:grid; place-items:center; color:#6f7f95; font-size:11px; }
        .photos figcaption { font-size:10px; color:#6f7f95; margin-top:4px; }
        .photos figcaption b { display:block; font-size:12px; color:#12233f; }
        .f-pied { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:18px; padding-top:14px; border-top:1px solid #e3e9f2; font-size:10px; color:#6f7f95; }
        .f-logo { display:flex; align-items:center; gap:8px; font-weight:700; font-size:14px; color:#12233f; }
        .f-logo-i { width:28px; height:28px; border-radius:8px; background:#1aa7ec; color:#fff; display:grid; place-items:center; }
    </style>
</head>
<body>

<div class="btns">
    <button onclick="window.print()" class="btn-imprimer">🖨️ Imprimer / Télécharger en PDF</button>
    <a href="index.php?page=factures" class="btn-retour">Retour</a>
</div>

<div class="boite facture">
    <div class="f-entete">
      <span class="f-badge <?= h($facture['type']) ?>"><?= h($facture['typeLabel']) ?></span>
      <span class="f-gen">Émise le <?= h($facture['date']) ?></span>
      <span class="f-statut <?= $facture['paye'] ? 'paye' : 'impaye' ?>">
        <?= h($facture['statut']) ?><?= $facture['datePaiement'] ? ' le ' . h($facture['datePaiement']) : '' ?>
      </span>
    </div>
    <div class="eyebrow">FACTURE MENSUELLE</div>
    <h2><?= h($facture['numero']) ?></h2>

    <div class="grille f-bloc">
      <div><small>CLIENT</small><b><?= h($facture['client']) ?></b>
        <span class="f-sous"><?= h($facture['adresse']) ?></span>
        <span class="f-sous"><?= h($facture['contact']) ?></span></div>
      <div><small>PÉRIODE DE CONSOMMATION</small><b><?= h($facture['periode']) ?></b>
        <span class="f-sous">Compteur n° <?= h($facture['compteur']) ?></span></div>
    </div>

    <table class="f-table">
      <thead><tr><th>DÉTAIL</th><th>CONSOMMATION</th><th>MONTANT HT</th></tr></thead>
      <tbody><tr>
        <td><b><?= h($facture['typeLabel']) ?></b>
            <span class="f-sous">Index : <?= h($facture['indexPrec']) ?> → <?= h($facture['indexAct']) ?></span></td>
        <td><?= h($facture['conso']) ?></td>
        <td><?= h($facture['ht']) ?> DH</td>
      </tr></tbody>
    </table>

    <div class="f-totaux">
      <div><span>Consommation totale</span><b><?= h($facture['conso']) ?></b></div>
      <div><span>Montant HT</span><b><?= h($facture['ht']) ?> DH</b></div>
      <div><span>TVA (18 %)</span><b><?= h($facture['tva']) ?> DH</b></div>
      <div class="ttc"><span>Montant TTC</span><b><?= h($facture['ttc']) ?> DH</b></div>
    </div>

    <div class="f-titre">Justificatifs compteur</div>
    <div class="photos">
      <figure>
        <?php if ($facture['photoAvant']): ?>
          <img src="<?= h($facture['photoAvant']) ?>" alt="Photo du compteur du mois précédent">
        <?php else: ?>
          <div class="vide">Aucune photo</div>
        <?php endif; ?>
        <figcaption><b>Mois précédent</b><?= h($facture['indexPrec']) ?></figcaption>
      </figure>
      <figure>
        <?php if ($facture['photoApres']): ?>
          <img src="<?= h($facture['photoApres']) ?>" alt="Photo du compteur du mois actuel">
        <?php else: ?>
          <div class="vide">Aucune photo</div>
        <?php endif; ?>
        <figcaption><b>Mois actuel</b><?= h($facture['indexAct']) ?></figcaption>
      </figure>
    </div>

    <div class="f-pied">
      <span class="f-logo"><span class="f-logo-i">💧</span> Fluxio</span>
      <span>Facture générée automatiquement après validation du relevé mensuel.</span>
    </div>
</div>

</body>
</html>