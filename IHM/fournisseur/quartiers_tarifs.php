<?php
require_once __DIR__ . '/../../traitement/auth.php';
exigerRole('fournisseur');
// $d est préparé dans index.php (case 'quartiers_tarifs') par preparerPageQuartiersTarifs()
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}
function badgeActiviteTarif($libelle)
{
    $cls = ['Résidentiel' => 'act-res', 'Commercial' => 'act-com', 'Industriel' => 'act-ind'][$libelle] ?? 'act-res';
    return '<span class="badge-act ' . $cls . '">' . h($libelle) . '</span>';
}
function dh($n)
{
    return number_format((float) $n, 2, ',', ' ');
}
function qte($n)
{
    return rtrim(rtrim(number_format((float) $n, 2, ',', ' '), '0'), ',');
}
function optionsTypes($types, $selection = 0)
{
    $html = '';
    foreach ($types as $t) {
        $html .= '<option value="' . (int) $t['ID_Type_Activite'] . '"'
               . ((int) $t['ID_Type_Activite'] === (int) $selection ? ' selected' : '') . '>' . h($t['Libelle']) . '</option>';
    }
    return $html;
}
$sim = $d['sim'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fluxio - Quartiers &amp; Tarifs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }

        .sidebar { width: 270px; min-width: 270px; background-color: #0F2137; color: #fff; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 25px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .logo-icon { width: 38px; height: 38px; background: #1890FF; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; }
        .sidebar-brand .brand-text { font-size: 16px; font-weight: bold; color: #ffffff; line-height: 1.2; }
        .sidebar-brand .brand-sub { font-size: 12px; font-weight: normal; color: #94A3B8; }
        .sidebar-menu { list-style: none; padding: 20px 15px; flex: 1; display: flex; flex-direction: column; gap: 5px; }
        .sidebar-menu li a { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: #A0AEC0; text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 8px; transition: 0.2s; }
        .sidebar-menu li.active a, .sidebar-menu li a:hover { background-color: #1A3353; color: #fff; border-left: 4px solid #1890FF; font-weight: 600; }
        .sidebar-card-box { margin: 15px; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; display: flex; align-items: center; gap: 10px; }
        .sidebar-card-box i { color: #1890FF; font-size: 18px; }
        .sidebar-card-box .box-title { font-size: 13px; font-weight: 600; color: #ffffff; }
        .sidebar-card-box .box-sub { font-size: 11px; color: #94A3B8; }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; gap: 15px; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 36px; height: 36px; background: #1A3353; color: #38BDF8; font-weight: 600; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .user-info .user-name { font-size: 13px; font-weight: 600; color: #ffffff; }
        .user-info .user-role { font-size: 11px; color: #94A3B8; }
        .logout-btn { display: flex; align-items: center; gap: 8px; color: #F87171; text-decoration: none; font-size: 13px; font-weight: 500; padding: 5px 0; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background-color: #F8FAFC; scroll-behavior: smooth; }
        .header { background: #fff; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 5; }
        .header h1 { font-size: 18px; color: #1E293B; font-weight: 600; }
        .container-fluid { padding: 30px; }
        .nav-ancres a { font-size: 13px; font-weight: 600; color: #475569; text-decoration: none; margin-left: 18px; }
        .nav-ancres a:hover { color: #1890FF; }

        section.bloc { margin-bottom: 34px; scroll-margin-top: 80px; }
        section.bloc > h2 { font-size: 16px; font-weight: 700; color: #0F2137; margin-bottom: 4px; }
        .table-card { background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; overflow: hidden; }
        .table th { background: #F8FAFC; color: #475569; font-weight: 600; font-size: 13px; padding: 12px 14px; white-space: nowrap; }
        .table td { padding: 10px 14px; font-size: 13.5px; color: #334155; vertical-align: middle; }
        .carte { background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 18px; height: 100%; }
        .carte h3 { font-size: 15px; font-weight: 700; color: #0F2137; margin-bottom: 12px; }

        .badge-eau  { background-color: #E0F2FE; color: #0284C7; font-weight: 600; padding: 3px 9px; border-radius: 12px; font-size: 12px; }
        .badge-elec { background-color: #FEF3C7; color: #D97706; font-weight: 600; padding: 3px 9px; border-radius: 12px; font-size: 12px; }
        .badge-act  { font-weight: 600; padding: 3px 9px; border-radius: 12px; font-size: 11.5px; white-space: nowrap; }
        .act-res { background: #DCFCE7; color: #15803D; }
        .act-com { background: #EDE9FE; color: #6D28D9; }
        .act-ind { background: #FFE4E6; color: #BE123C; }
        .st-actif { background: #DCFCE7; color: #15803D; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .st-vide  { background: #F1F5F9; color: #475569; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .tag-sel  { background: #FEF3C7; color: #B45309; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .btn-xs { padding: 3px 8px; font-size: 12px; }
        .aide { font-size: 12px; color: #64748B; }
        .prix-input { width: 90px; }

        .sim-total td { font-weight: 700; background: #F8FAFC; }
        .sim-ttc td { font-weight: 700; background: #0F2137; color: #fff; font-size: 15px; }
        .alerte-seuil { font-size: 12.5px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-icon"><i class="fa-solid fa-droplet"></i></div>
        <div>
            <div class="brand-text">Gestion des Factures</div>
            <div class="brand-sub">Eau & Électricité</div>
        </div>
    </div>
    <div class="sidebar-card-box">
        <i class="fa-solid fa-shield-halved"></i>
        <div>
            <div class="box-title">Espace Fournisseur</div>
            <div class="box-sub">Accès administration</div>
        </div>
    </div>
    <?php require __DIR__ . '/menu.php'; ?>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">SF</div>
            <div class="user-info">
                <div class="user-name">Service Fournisseur</div>
                <div class="user-role">Administrateur</div>
            </div>
        </div>
        <a href="index.php?page=logout" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Se déconnecter</a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <h1><i class="fa-solid fa-tags text-primary"></i> Quartiers &amp; Tarifs</h1>
        <div class="nav-ancres">
            <a href="#quartiers">Quartiers</a>
            <a href="#types">Seuils &amp; redevances</a>
            <a href="#tranches">Tranches</a>
            <a href="#simulateur">Simulateur</a>
        </div>
    </div>

    <div class="container-fluid">

        <?php if ($d['msg']): ?>
            <div class="alert alert-<?= $d['msg']['type'] === 'succes' ? 'success' : 'danger' ?> alert-dismissible fade show">
                <?= h($d['msg']['texte']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ============ 1. QUARTIERS ============ -->
        <section class="bloc" id="quartiers">
            <h2><i class="fa-solid fa-location-dot"></i> Quartiers</h2>
            <p class="aide mb-3">Chaque quartier a un seul type d'activité, qui détermine le tarif de ses contrats.
                Un quartier utilisé par un contrat actif ne peut pas être supprimé.</p>

            <form method="post" action="index.php?page=tarifs_action" class="carte mb-3">
                <input type="hidden" name="action" value="quartier_ajouter">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4"><label class="form-label aide mb-1">Nom du quartier</label>
                        <input type="text" name="nom" class="form-control" maxlength="100" required></div>
                    <div class="col-md-3"><label class="form-label aide mb-1">Ville</label>
                        <input type="text" name="ville" class="form-control" maxlength="100" required></div>
                    <div class="col-md-3"><label class="form-label aide mb-1">Type d'activité</label>
                        <select name="type_activite" class="form-select" required><?= optionsTypes($d['types']) ?></select></div>
                    <div class="col-md-2 d-grid"><button class="btn btn-success"><i class="fa-solid fa-plus"></i> Ajouter</button></div>
                </div>
            </form>

            <div class="table-card">
                <table class="table mb-0">
                    <thead><tr><th>#</th><th>Quartier</th><th>Ville</th><th>Type d'activité</th><th>Contrats actifs</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($d['quartiers'] as $q): ?>
                        <tr>
                            <td><?= (int) $q['ID_Quartier'] ?></td>
                            <td><strong><?= h($q['Nom']) ?></strong></td>
                            <td><?= h($q['Ville']) ?></td>
                            <td><?= badgeActiviteTarif($q['Type_Activite']) ?></td>
                            <td><?= $q['nb_actifs'] > 0
                                    ? '<span class="st-actif">' . (int) $q['nb_actifs'] . ' actif(s)</span>'
                                    : '<span class="st-vide">aucun</span>' ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-primary btn-xs btn-edit-quartier"
                                        data-bs-toggle="modal" data-bs-target="#mQuartier"
                                        data-id="<?= (int) $q['ID_Quartier'] ?>"
                                        data-nom="<?= h($q['Nom']) ?>" data-ville="<?= h($q['Ville']) ?>"
                                        data-type="<?= (int) $q['ID_Type_Activite'] ?>"
                                        data-actifs="<?= (int) $q['nb_actifs'] ?>">
                                    <i class="fa-solid fa-pen"></i> Modifier</button>
                                <?php if ($q['supprimable']): ?>
                                    <form method="post" action="index.php?page=tarifs_action" class="d-inline"
                                          onsubmit="return confirm('Supprimer le quartier « <?= h(addslashes($q['Nom'])) ?> » ?');">
                                        <input type="hidden" name="action" value="quartier_supprimer">
                                        <input type="hidden" name="id_quartier" value="<?= (int) $q['ID_Quartier'] ?>">
                                        <button class="btn btn-outline-danger btn-xs"><i class="fa-solid fa-trash"></i> Supprimer</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary btn-xs" disabled
                                            title="Utilisé par <?= (int) $q['nb_actifs'] ?> contrat(s) actif(s)">
                                        <i class="fa-solid fa-lock"></i> Supprimer</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$d['quartiers']): ?><tr><td colspan="6" class="text-center aide py-4">Aucun quartier.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ============ 2. TYPES D'ACTIVITE : SEUILS + REDEVANCES ============ -->
        <section class="bloc" id="types">
            <h2><i class="fa-solid fa-gauge-high"></i> Seuils d'alerte &amp; redevances fixes</h2>
            <p class="aide mb-3">Le seuil mensuel déclenche une alerte « Surconsommation » à la saisie.
                La redevance fixe mensuelle (DH HT) s'ajoute à chaque facture, avant la TVA.</p>
            <div class="row g-3">
                <?php foreach ($d['types'] as $t): ?>
                    <div class="col-lg-4">
                        <form method="post" action="index.php?page=tarifs_action" class="carte">
                            <input type="hidden" name="action" value="type_modifier">
                            <input type="hidden" name="id_type" value="<?= (int) $t['ID_Type_Activite'] ?>">
                            <h3><?= badgeActiviteTarif($t['Libelle']) ?></h3>
                            <p class="aide mb-3"><?= h($t['Description']) ?></p>
                            <div class="row g-2">
                                <div class="col-6"><label class="form-label aide mb-1"><span class="badge-eau">Eau</span> seuil (m³)</label>
                                    <input type="text" name="seuil_eau" class="form-control" value="<?= h(qte($t['Seuil_Eau'])) ?>" required></div>
                                <div class="col-6"><label class="form-label aide mb-1"><span class="badge-elec">Élec</span> seuil (kWh)</label>
                                    <input type="text" name="seuil_elec" class="form-control" value="<?= h(qte($t['Seuil_Elec'])) ?>" required></div>
                                <div class="col-6"><label class="form-label aide mb-1">Redevance eau (DH/mois)</label>
                                    <input type="text" name="redevance_eau" class="form-control" value="<?= h(qte($t['Redevance_Eau'])) ?>" required></div>
                                <div class="col-6"><label class="form-label aide mb-1">Redevance élec (DH/mois)</label>
                                    <input type="text" name="redevance_elec" class="form-control" value="<?= h(qte($t['Redevance_Elec'])) ?>" required></div>
                            </div>
                            <button class="btn btn-primary btn-sm mt-3"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ============ 3. TRANCHES ============ -->
        <section class="bloc" id="tranches">
            <h2><i class="fa-solid fa-layer-group"></i> Tranches de prix</h2>
            <p class="aide mb-3">Les tranches sont affichées par bornes croissantes ; deux tranches ne peuvent pas avoir la même borne.
                La tranche illimitée est obligatoire (on modifie son prix, on ne la supprime pas).
                Facturation normale : le prix de la tranche où tombe la consommation s'applique à <em>toute</em> la consommation.</p>

            <ul class="nav nav-pills mb-3" role="tablist">
                <?php foreach ($d['types'] as $i => $t): ?>
                    <li class="nav-item"><button class="nav-link <?= $i === 0 ? 'active' : '' ?>" data-bs-toggle="pill"
                            data-bs-target="#tr<?= (int) $t['ID_Type_Activite'] ?>" type="button"><?= h($t['Libelle']) ?></button></li>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content">
            <?php foreach ($d['types'] as $i => $t): $idT = (int) $t['ID_Type_Activite']; ?>
                <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="tr<?= $idT ?>">
                    <div class="row g-3">
                    <?php foreach (TYPES_COMPTEUR as $cle => $libC): $u = uniteCompteur($cle); ?>
                        <div class="col-xl-6">
                            <div class="table-card">
                                <div class="px-3 pt-3 pb-1"><span class="<?= $cle === 'Eau' ? 'badge-eau' : 'badge-elec' ?>"><?= h($libC) ?></span>
                                    <span class="aide ms-2">prix en DH HT par <?= $u ?></span></div>
                                <table class="table mb-0">
                                    <thead><tr><th>Tranche (<?= $u ?>)</th><th>Prix</th><th class="text-end"></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($d['tranches'][$idT][$cle] as $l): $illim = $l['Borne_Haute'] === null; ?>
                                        <tr>
                                            <td><?= $illim ? ((float) $l['Borne_Basse'] === 0.0 ? 'toute consommation' : 'au-delà de ' . qte($l['Borne_Basse'])) . ' <span class="aide">(illimitée)</span>'
                                                           : 'de ' . qte($l['Borne_Basse']) . ' à ' . qte($l['Borne_Haute']) ?></td>
                                            <td colspan="2">
                                                <form method="post" action="index.php?page=tarifs_action" class="d-flex align-items-center gap-2">
                                                    <input type="hidden" name="action" value="tranche_modifier">
                                                    <input type="hidden" name="id_tarif" value="<?= (int) $l['ID_Tarif'] ?>">
                                                    <input type="text" name="prix" class="form-control form-control-sm prix-input" value="<?= h(rtrim(rtrim(number_format((float) $l['Prix_Unitaire'], 4, '.', ''), '0'), '.')) ?>">
                                                    <button class="btn btn-outline-primary btn-xs ms-auto" title="Enregistrer"><i class="fa-solid fa-check"></i></button>
                                                    <?php if (!$illim): ?>
                                                        <button class="btn btn-outline-danger btn-xs" title="Supprimer cette tranche"
                                                                formaction="index.php?page=tarifs_action"
                                                                name="action" value="tranche_supprimer"
                                                                onclick="return confirm('Supprimer cette tranche ?');"><i class="fa-solid fa-trash"></i></button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <form method="post" action="index.php?page=tarifs_action" class="p-3 border-top d-flex flex-wrap align-items-end gap-2">
                                    <input type="hidden" name="action" value="tranche_ajouter">
                                    <input type="hidden" name="id_type" value="<?= $idT ?>">
                                    <input type="hidden" name="type_compteur" value="<?= h($cle) ?>">
                                    <div><label class="aide d-block mb-1">Borne haute (<?= $u ?>)</label>
                                        <input type="text" name="borne" class="form-control form-control-sm" style="width:110px"></div>
                                    <div><label class="aide d-block mb-1">Prix (DH)</label>
                                        <input type="text" name="prix" class="form-control form-control-sm" style="width:90px" required></div>
                                    <label class="aide d-flex align-items-center gap-1 mb-1"><input type="checkbox" name="illimitee" value="1" class="form-check-input mt-0"> illimitée</label>
                                    <button class="btn btn-success btn-sm ms-auto"><i class="fa-solid fa-plus"></i> Ajouter</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </section>

        <!-- ============ 4. SIMULATEUR ============ -->
        <section class="bloc" id="simulateur">
            <h2><i class="fa-solid fa-calculator"></i> Simulateur de facture</h2>
            <p class="aide mb-3">Combien paie un abonné pour une consommation mensuelle donnée ? (calcul avec les tarifs enregistrés ci-dessus)</p>

            <form method="get" action="index.php#simulateur" class="carte mb-3">
                <input type="hidden" name="page" value="quartiers_tarifs">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3"><label class="form-label aide mb-1">Type d'activité</label>
                        <select name="sim_type" class="form-select"><?= optionsTypes($d['types'], $sim['type']) ?></select></div>
                    <div class="col-md-3"><label class="form-label aide mb-1">Compteur</label>
                        <select name="sim_compteur" class="form-select">
                            <?php foreach (TYPES_COMPTEUR as $cle => $lib): ?>
                                <option value="<?= h($cle) ?>" <?= $sim['compteur'] === $cle ? 'selected' : '' ?>><?= h($lib) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-md-3"><label class="form-label aide mb-1">Consommation du mois</label>
                        <input type="text" name="sim_conso" class="form-control" value="<?= h($sim['conso']) ?>" placeholder="ex. 600" required></div>
                    <div class="col-md-3 d-grid"><button class="btn btn-primary"><i class="fa-solid fa-calculator"></i> Calculer</button></div>
                </div>
            </form>

            <?php if ($sim['erreur']): ?>
                <div class="alert alert-danger"><?= h($sim['erreur']) ?></div>
            <?php elseif ($sim['resultat']): $r = $sim['resultat']; ?>
                <div class="table-card">
                    <div class="p-3 border-bottom">
                        <strong><?= qte($r['consommation']) ?> <?= h($sim['unite']) ?></strong> —
                        <?= badgeActiviteTarif($sim['libelle']) ?>
                        <span class="<?= $sim['compteur'] === 'Eau' ? 'badge-eau' : 'badge-elec' ?> ms-1"><?= $sim['compteur'] === 'Eau' ? 'Eau' : 'Électricité' ?></span>
                        <?php if ($r['consommation'] > $sim['seuil']): ?>
                            <span class="text-danger alerte-seuil ms-2"><i class="fa-solid fa-triangle-exclamation"></i>
                                dépasse le seuil d'alerte (<?= qte($sim['seuil']) ?> <?= h($sim['unite']) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <table class="table mb-0">
                        <thead><tr><th>Tranche</th><th class="text-end">Quantité</th><th class="text-end">Prix unitaire</th><th class="text-end">Montant (DH)</th></tr></thead>
                        <tbody>
                        <?php foreach ($r['lignes'] as $l): ?>
                            <tr>
                                <td><?= $l['a'] === null ? 'au-delà de ' . qte($l['de']) : 'de ' . qte($l['de']) . ' à ' . qte($l['a']) ?></td>
                                <td class="text-end"><?= qte($l['quantite']) ?> <?= h($sim['unite']) ?></td>
                                <td class="text-end"><?= dh($l['prix']) ?></td>
                                <td class="text-end"><?= dh($l['montant']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$r['lignes']): ?><tr><td colspan="4" class="aide">Aucune consommation.</td></tr><?php endif; ?>
                        <tr><td colspan="3" class="text-end">Redevance fixe mensuelle</td><td class="text-end"><?= dh($r['redevance']) ?></td></tr>
                        <tr class="sim-total"><td colspan="3" class="text-end">Total HT</td><td class="text-end"><?= dh($r['ht']) ?></td></tr>
                        <tr><td colspan="3" class="text-end">TVA (18 %)</td><td class="text-end"><?= dh($r['tva']) ?></td></tr>
                        <tr class="sim-ttc"><td colspan="3" class="text-end">Total TTC à payer</td><td class="text-end"><?= dh($r['ttc']) ?> DH</td></tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    </div>
</div>

<!-- Modale : modifier un quartier -->
<div class="modal fade" id="mQuartier" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="index.php?page=tarifs_action" class="modal-content">
            <input type="hidden" name="action" value="quartier_modifier">
            <input type="hidden" name="id_quartier" id="mq-id">
            <div class="modal-header"><h5 class="modal-title">Modifier le quartier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label aide mb-1">Nom</label>
                    <input type="text" name="nom" id="mq-nom" class="form-control" maxlength="100" required></div>
                <div class="mb-2"><label class="form-label aide mb-1">Ville</label>
                    <input type="text" name="ville" id="mq-ville" class="form-control" maxlength="100" required></div>
                <div class="mb-2"><label class="form-label aide mb-1">Type d'activité</label>
                    <select name="type_activite" id="mq-type" class="form-select"><?= optionsTypes($d['types']) ?></select></div>
                <div class="alert alert-warning py-2 aide d-none" id="mq-alerte">
                    <i class="fa-solid fa-triangle-exclamation"></i> Ce quartier a <strong id="mq-nb"></strong> contrat(s) actif(s) :
                    changer le type d'activité modifie leur tarif.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                <button class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.btn-edit-quartier').forEach(btn => btn.addEventListener('click', () => {
    document.getElementById('mq-id').value    = btn.dataset.id;
    document.getElementById('mq-nom').value   = btn.dataset.nom;
    document.getElementById('mq-ville').value = btn.dataset.ville;
    document.getElementById('mq-type').value = btn.dataset.type;
    const nb = parseInt(btn.dataset.actifs, 10);
    document.getElementById('mq-nb').textContent = nb;
    document.getElementById('mq-alerte').classList.toggle('d-none', nb === 0);
}));
</script>
</body>
</html>