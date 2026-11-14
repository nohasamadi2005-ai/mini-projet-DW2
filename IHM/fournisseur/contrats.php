<?php
require_once __DIR__ . '/../../traitement/auth.php';
exigerRole('fournisseur');
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}
$f = $d['filtres'];

$zonesParType = [];
foreach ($d['quartiers'] as $q) {
    $zonesParType[$q['Type_Activite']][] = $q;
}
function optionsZones($zonesParType, $selection = 0)
{
    $html = '<option value="">— Choisir la zone —</option>';
    foreach ($zonesParType as $type => $zones) {
        $html .= '<optgroup label="Zone ' . h($type) . '">';
        foreach ($zones as $q) {
            $html .= '<option value="' . (int) $q['ID_Quartier'] . '"' . ((int) $q['ID_Quartier'] === (int) $selection ? ' selected' : '')
                   . '>' . h($q['Nom'] . ' — ' . $q['Ville']) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
}
function champClient($name, $client = null)
{
    $json = $client ? h(json_encode(clientPourJs($client), JSON_UNESCAPED_UNICODE)) : '';
    ob_start(); ?>
    <div class="client-picker" data-initial="<?= $json ?>">
        <input type="hidden" name="<?= h($name) ?>" class="cp-id">
        <div class="cp-recherche">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                <input type="text" class="form-control cp-texte" autocomplete="off" spellcheck="false"
                       placeholder="Nom, prénom, CIN, téléphone ou n° client…"
                       role="combobox" aria-autocomplete="list" aria-expanded="false">
            </div>
            <div class="cp-liste" role="listbox"></div>
            <div class="invalid-feedback cp-erreur">Choisissez le client dans les propositions.</div>
        </div>
        <div class="cp-choisi" style="display:none"></div>
    </div>
    <?php return ob_get_clean();
}
function badgeActivite($libelle)
{
    $cls = ['Résidentiel' => 'act-res', 'Commercial' => 'act-com', 'Industriel' => 'act-ind'][$libelle] ?? 'act-res';
    return '<span class="badge-act ' . $cls . '">' . h($libelle) . '</span>';
}
function lienPage($f, $p)
{
    return 'index.php?' . http_build_query(['page' => 'contrats'] + array_filter($f) + ['p' => $p]);
}
$zonesJs = [];
foreach ($d['quartiers'] as $q) {
    $zonesJs[(int) $q['ID_Quartier']] = $q['Nom'] . ' — ' . $q['Ville'] . ' (' . $q['Type_Activite'] . ')';
}
$libresJs = array_map(fn($c) => [
    'id'       => (int) $c['ID_Compteur'],
    'type'     => $c['Type_Compteur'],
    'index'    => (float) $c['Dernier_Index'],
    'quartier' => $c['Dernier_Quartier'] ? (int) $c['Dernier_Quartier'] : null,
    'adresse'  => $c['Derniere_Adresse'] ?? '',
], $d['libres']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fluxio - Contrats</title>
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

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background-color: #F8FAFC; }
        .header { background: #fff; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; }
        .header h1 { font-size: 18px; color: #1E293B; font-weight: 600; }
        .container-fluid { padding: 30px; }

        .stat-card { background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 16px 18px; height: 100%; }
        .stat-card .stat-title { font-size: 12px; color: #64748B; font-weight: 500; margin-bottom: 4px; }
        .stat-card .stat-value { font-size: 22px; color: #0F2137; font-weight: 700; }
        .table-card { background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; overflow: hidden; }
        .table th { background: #F8FAFC; color: #475569; font-weight: 600; font-size: 13px; padding: 12px 14px; white-space: nowrap; }
        .table td { padding: 12px 14px; font-size: 13.5px; color: #334155; vertical-align: middle; }

        .badge-eau  { background-color: #E0F2FE; color: #0284C7; font-weight: 600; padding: 3px 9px; border-radius: 12px; font-size: 12px; }
        .badge-elec { background-color: #FEF3C7; color: #D97706; font-weight: 600; padding: 3px 9px; border-radius: 12px; font-size: 12px; }
        .badge-act  { font-weight: 600; padding: 3px 9px; border-radius: 12px; font-size: 11.5px; white-space: nowrap; }
        .act-res { background: #DCFCE7; color: #15803D; }
        .act-com { background: #EDE9FE; color: #6D28D9; }
        .act-ind { background: #FFE4E6; color: #BE123C; }
        .st-actif   { background: #DCFCE7; color: #15803D; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .st-resilie { background: #F1F5F9; color: #475569; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .btn-xs { padding: 3px 8px; font-size: 12px; }
        .aide { font-size: 12px; color: #64748B; }
        .zone-imposee { background: #F1F5F9; pointer-events: none; }

        .cp-recherche { position: relative; }
        .cp-liste { display: none; position: absolute; z-index: 1080; left: 0; right: 0; top: calc(100% + 4px);
                    background: #fff; border: 1px solid #E2E8F0; border-radius: 10px; box-shadow: 0 12px 28px rgba(15,33,55,.14);
                    max-height: 320px; overflow-y: auto; }
        .cp-liste.ouverte { display: block; }
        .cp-item { padding: 9px 12px; cursor: pointer; border-bottom: 1px solid #F1F5F9; }
        .cp-item:last-child { border-bottom: 0; }
        .cp-item.actif, .cp-item:hover { background: #EFF6FF; }
        .cp-item .cp-nom { font-weight: 600; color: #0F2137; font-size: 14px; }
        .cp-item .cp-nom mark { padding: 0; background: #FEF08A; border-radius: 2px; }
        .cp-item .cp-num { color: #64748B; font-weight: 500; font-size: 12px; margin-left: 4px; }
        .cp-details { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 12px; color: #475569; margin-top: 2px; }
        .cp-details i { color: #94A3B8; width: 12px; }
        .cp-homonyme { background: #FEF3C7; color: #B45309; font-size: 10.5px; font-weight: 600; padding: 1px 7px; border-radius: 10px; margin-left: 6px; }
        .cp-vide { padding: 12px; font-size: 13px; color: #64748B; }
        .cp-choisi { border: 1px solid #BFDBFE; background: #EFF6FF; border-radius: 10px; padding: 10px 12px;
                     display: flex; align-items: flex-start; gap: 10px; }
        .cp-choisi .cp-avatar { width: 34px; height: 34px; border-radius: 50%; background: #1890FF; color: #fff; font-weight: 700;
                                font-size: 13px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .client-picker.is-invalid .cp-texte { border-color: #DC3545; }
        .client-picker.is-invalid .cp-erreur { display: block; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-icon"><i class="fa-solid fa-droplet"></i></div>
        <div>
            <div class="brand-text">Fluxio</div>
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
        <h1><i class="fa-solid fa-file-signature text-primary"></i> Gestion des contrats</h1>
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mCreer"><i class="fa-solid fa-plus"></i> Nouveau contrat</button>
        </div>
    </div>

    <div class="container-fluid">

        <?php if ($d['msg']): ?>
            <div class="alert alert-<?= $d['msg']['type'] === 'succes' ? 'success' : 'danger' ?> alert-dismissible fade show">
                <?= h($d['msg']['texte']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($d['clientFiltre']): ?>
            <div class="alert alert-primary d-flex justify-content-between align-items-center">
                <div><i class="fa-solid fa-user"></i> Contrats de <strong><?= h($d['clientFiltre']['Prenom'] . ' ' . $d['clientFiltre']['Nom']) ?></strong>
                    (client #<?= (int) $d['clientFiltre']['ID_Client'] ?>)</div>
                <div class="d-flex gap-2">
                    <a href="index.php?page=gestion_client" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-arrow-left"></i> Gestion Clients</a>
                    <a href="index.php?page=contrats" class="btn btn-sm btn-outline-secondary">Tous les contrats</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <?php foreach ([
                ['Contrats actifs', $d['stats']['actifs'] ?? 0, 'text-success'],
                ['Zone résidentielle', $d['stats']['residentiel'] ?? 0, ''],
                ['Zone commerciale', $d['stats']['commercial'] ?? 0, ''],
                ['Zone industrielle', $d['stats']['industriel'] ?? 0, ''],
                ['Contrats résiliés', $d['stats']['resilies'] ?? 0, 'text-secondary'],
                ['Compteurs libres', $d['stats']['libres'] ?? 0, 'text-primary'],
            ] as [$titre, $val, $cls]): ?>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="stat-card"><div class="stat-title"><?= h($titre) ?></div><div class="stat-value <?= $cls ?>"><?= (int) $val ?></div></div>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="GET" action="index.php" class="row g-2 mb-3 align-items-end">
            <input type="hidden" name="page" value="contrats">
            <?php if ($f['client']): ?><input type="hidden" name="client" value="<?= (int) $f['client'] ?>"><?php endif; ?>
            <div class="col-lg"><input type="text" name="q" class="form-control" placeholder="Client, n° contrat, n° compteur, adresse…" value="<?= h($f['q']) ?>"></div>
            <div class="col-lg-auto"><select name="quartier" class="form-select"><?= str_replace('— Choisir la zone —', 'Toutes les zones', optionsZones($zonesParType, $f['quartier'])) ?></select></div>
            <div class="col-lg-auto"><select name="activite" class="form-select">
                <option value="">Tous les types</option>
                <?php foreach ($d['activites'] as $a): ?>
                    <option value="<?= (int) $a['ID_Type_Activite'] ?>" <?= (int) $a['ID_Type_Activite'] === $f['activite'] ? 'selected' : '' ?>><?= h($a['Libelle']) ?></option>
                <?php endforeach; ?>
            </select></div>
            <div class="col-lg-auto"><select name="type" class="form-select">
                <option value="">Eau et électricité</option>
                <option value="Eau" <?= $f['type'] === 'Eau' ? 'selected' : '' ?>>Eau</option>
                <option value="Electricite" <?= $f['type'] === 'Electricite' ? 'selected' : '' ?>>Électricité</option>
            </select></div>
            <div class="col-lg-auto"><select name="statut" class="form-select">
                <option value="">Tous les statuts</option>
                <option value="Actif" <?= $f['statut'] === 'Actif' ? 'selected' : '' ?>>Actifs</option>
                <option value="Resilie" <?= $f['statut'] === 'Resilie' ? 'selected' : '' ?>>Résiliés</option>
            </select></div>
            <div class="col-lg-auto d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrer</button>
                <a class="btn btn-light" href="index.php?page=contrats<?= $f['client'] ? '&client=' . (int) $f['client'] : '' ?>">Effacer</a>
            </div>
        </form>

        <div class="table-card">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr><th>Contrat</th><th>Client</th><th>Compteur</th><th>Zone</th><th>Adresse</th>
                            <th>Période</th><th>Index</th><th>Statut</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$d['lignes']): ?>
                        <tr><td colspan="9" class="text-center text-muted py-5">Aucun contrat pour ces filtres.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($d['lignes'] as $c):
                        $json = h(json_encode([
                            'id' => (int) $c['ID_Contrat'], 'client' => $c['client'], 'idClient' => (int) $c['ID_Client'],
                            'compteur' => (int) $c['ID_Compteur'], 'type' => $c['Type_Compteur'], 'unite' => $c['unite'],
                            'dernierIndex' => (float) $c['Dernier_Index'], 'debut' => $c['Date_Debut'],
                            'quartier' => (int) $c['ID_Quartier'], 'zone' => $c['zone'] . ' (' . $c['Type_Activite'] . ')',
                        ], JSON_UNESCAPED_UNICODE)); ?>
                        <tr>
                            <td class="fw-bold">n° <?= (int) $c['ID_Contrat'] ?></td>
                            <td><a href="index.php?page=contrats&client=<?= (int) $c['ID_Client'] ?>" class="text-decoration-none"><?= h($c['client']) ?></a>
                                <br><small class="text-muted">#<?= (int) $c['ID_Client'] ?></small></td>
                            <td>n° <?= (int) $c['ID_Compteur'] ?><br>
                                <span class="<?= $c['Type_Compteur'] === 'Eau' ? 'badge-eau' : 'badge-elec' ?>"><?= $c['Type_Compteur'] === 'Eau' ? 'Eau' : 'Électricité' ?></span></td>
                            <td><?= h($c['zone']) ?><br><?= badgeActivite($c['Type_Activite']) ?></td>
                            <td style="max-width:200px"><small><?= h($c['Adresse_Branchement'] ?: '—') ?></small></td>
                            <td><small>du <?= h(dateContratFr($c['Date_Debut'])) ?><br>
                                <?= $c['actif'] ? '<span class="text-success">en cours</span>' : 'au ' . h(dateContratFr($c['Date_Fin'])) ?></small></td>
                            <td><small>début <?= h(fr($c['Index_Debut'])) ?><br>
                                <?= $c['actif'] ? 'actuel ' . h(fr($c['Dernier_Index'])) : 'fin ' . h(fr($c['Index_Fin'])) ?> <?= h($c['unite']) ?></small></td>
                            <td><?php if ($c['actif']): ?><span class="st-actif">Actif</span>
                                <?php else: ?><span class="st-resilie">Résilié</span><br><small class="text-muted"><?= h($c['motif']) ?></small><?php endif; ?></td>
                            <td class="text-end" style="white-space:nowrap">
                                <?php if ($c['actif']): ?>
                                    <button class="btn btn-outline-danger btn-xs" data-bs-toggle="modal" data-bs-target="#mResilier" data-ct="<?= $json ?>" title="Résilier"><i class="fa-solid fa-ban"></i> Résilier</button>
                                    <button class="btn btn-outline-primary btn-xs" data-bs-toggle="modal" data-bs-target="#mTransferer" data-ct="<?= $json ?>" title="Nouvel occupant du même logement"><i class="fa-solid fa-right-left"></i> Transférer</button>
                                    <button class="btn btn-outline-secondary btn-xs" data-bs-toggle="modal" data-bs-target="#mDemenager" data-ct="<?= $json ?>" title="Le client change de logement"><i class="fa-solid fa-truck"></i> Déménager</button>
                                <?php endif; ?>
                                <button class="btn btn-outline-secondary btn-xs" data-bs-toggle="modal" data-bs-target="#mZone" data-ct="<?= $json ?>" title="Corriger la zone du compteur"><i class="fa-solid fa-map-location-dot"></i></button>
                                <a class="btn btn-outline-secondary btn-xs" href="index.php?page=historique_compteurs&compteur=<?= (int) $c['ID_Compteur'] ?>" title="Historique du compteur"><i class="fa-solid fa-clock-rotate-left"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center p-3 border-top">
                <small class="text-muted"><?= (int) $d['total'] ?> contrat(s) — page <?= (int) $d['page'] ?> / <?= (int) $d['pages'] ?></small>
                <div class="d-flex gap-2">
                    <?php if ($d['page'] > 1): ?><a class="btn btn-sm btn-light border" href="<?= h(lienPage($f, $d['page'] - 1)) ?>">Précédent</a><?php endif; ?>
                    <?php if ($d['page'] < $d['pages']): ?><a class="btn btn-sm btn-light border" href="<?= h(lienPage($f, $d['page'] + 1)) ?>">Suivant</a><?php endif; ?>
                </div>
            </div>
        </div>
        <p class="aide mt-3"><i class="fa-solid fa-circle-info"></i>
            Un compteur reste dans son logement : tous ses contrats sont dans la même zone. Une zone n'a qu'un type d'activité
            (résidentielle, commerciale ou industrielle), c'est elle qui fixe le type du contrat.</p>
    </div>
</div>

<?php
function blocCompteur($prefixe, $zonesParType, $typeImpose = null)
{
    ob_start(); ?>
    <div class="mb-2">
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="mode" value="libre" id="<?= $prefixe ?>_libre" checked>
            <label class="form-check-label" for="<?= $prefixe ?>_libre">Compteur libre existant</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="mode" value="neuf" id="<?= $prefixe ?>_neuf">
            <label class="form-check-label" for="<?= $prefixe ?>_neuf">Nouveau compteur</label>
        </div>
    </div>
    <div class="bloc-libre mb-2">
        <select name="compteur_libre" class="form-select sel-libre" data-type="<?= h($typeImpose ?? '') ?>"></select>
        <div class="aide info-libre mt-1"></div>
    </div>
    <div class="bloc-neuf row g-2 mb-2" style="display:none">
        <div class="col-5"><label class="form-label aide mb-0">N° du compteur</label><input type="number" min="1" name="numero" class="form-control"></div>
        <div class="col-4"><label class="form-label aide mb-0">Type</label>
            <?php if ($typeImpose): ?>
                <input type="hidden" name="type" value="<?= h($typeImpose) ?>" class="type-impose">
                <input type="text" class="form-control type-impose-label" disabled>
            <?php else: ?>
                <select name="type" class="form-select"><option value="Eau">Eau</option><option value="Electricite">Électricité</option></select>
            <?php endif; ?>
        </div>
        <div class="col-3"><label class="form-label aide mb-0">Index initial</label><input type="text" name="index_initial" value="0" class="form-control"></div>
    </div>
    <div class="mb-2">
        <label class="form-label mb-0">Zone (quartier) du compteur</label>
        <select name="quartier" class="form-select sel-zone"><?= optionsZones($zonesParType) ?></select>
        <div class="aide info-zone mt-1"></div>
    </div>
    <?php return ob_get_clean();
}
?>

<div class="modal fade" id="mCreer" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="index.php?page=contrat_action">
        <input type="hidden" name="action" value="creer"><input type="hidden" name="retour" value="<?= h($d['retour']) ?>">
        <div class="modal-header"><h5 class="modal-title"><i class="fa-solid fa-plus"></i> Nouveau contrat</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label mb-0">Client</label>
                <?= champClient('client', $d['clientFiltre']) ?></div>
            <?= blocCompteur('cr', $zonesParType) ?>
            <div class="mb-2"><label class="form-label mb-0">Date de début</label>
                <input type="date" name="date_debut" class="form-control" value="<?= h($d['aujourdhui']) ?>" max="<?= h($d['aujourdhui']) ?>" required></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-success">Créer le contrat</button></div>
    </form>
</div></div></div>

<div class="modal fade" id="mResilier" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="index.php?page=contrat_action">
        <input type="hidden" name="action" value="resilier"><input type="hidden" name="retour" value="<?= h($d['retour']) ?>">
        <input type="hidden" name="id_contrat" class="in-id">
        <div class="modal-header"><h5 class="modal-title"><i class="fa-solid fa-ban"></i> Résilier le contrat <span class="txt-id"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="txt-resume mb-3"></p>
            <?= blocCloture($d) ?>
            <p class="aide mb-0">Le compteur devient libre et reste dans le logement, avec son index.</p>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger">Résilier</button></div>
    </form>
</div></div></div>

<div class="modal fade" id="mTransferer" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="index.php?page=contrat_action">
        <input type="hidden" name="action" value="transferer"><input type="hidden" name="retour" value="<?= h($d['retour']) ?>">
        <input type="hidden" name="id_contrat" class="in-id">
        <div class="modal-header"><h5 class="modal-title"><i class="fa-solid fa-right-left"></i> Transférer le contrat <span class="txt-id"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="txt-resume mb-2"></p>
            <p class="aide">Le logement change d'occupant : le contrat actuel est clôturé et un nouveau contrat est ouvert
                sur <strong>le même compteur</strong> (même zone, même adresse) pour le nouveau client.</p>
            <div class="mb-2"><label class="form-label mb-0">Nouveau titulaire</label>
                <?= champClient('nouveau_client') ?></div>
            <?= blocCloture($d, 'Date du transfert', 'Index relevé le jour du transfert') ?>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Transférer</button></div>
    </form>
</div></div></div>

<div class="modal fade" id="mDemenager" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" action="index.php?page=contrat_action">
        <input type="hidden" name="action" value="demenager"><input type="hidden" name="retour" value="<?= h($d['retour']) ?>">
        <input type="hidden" name="id_contrat" class="in-id">
        <div class="modal-header"><h5 class="modal-title"><i class="fa-solid fa-truck"></i> Déménagement — contrat <span class="txt-id"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="txt-resume mb-3"></p>
            <div class="row">
                <div class="col-md-6 border-end">
                    <h6 class="text-danger"><i class="fa-solid fa-house-circle-xmark"></i> Ancien logement</h6>
                    <?= blocCloture($d, 'Date du déménagement', 'Index de départ relevé') ?>
                </div>
                <div class="col-md-6">
                    <h6 class="text-success"><i class="fa-solid fa-house-circle-check"></i> Nouveau logement</h6>
                    <div class="bloc-demenager"><?= blocCompteur('dm', $zonesParType, 'Eau') ?></div>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer le déménagement</button></div>
    </form>
</div></div></div>

<div class="modal fade" id="mZone" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="index.php?page=contrat_action">
        <input type="hidden" name="action" value="zone"><input type="hidden" name="retour" value="<?= h($d['retour']) ?>">
        <input type="hidden" name="id_compteur" class="in-compteur">
        <div class="modal-header"><h5 class="modal-title"><i class="fa-solid fa-map-location-dot"></i> Zone du compteur <span class="txt-compteur"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="aide">Zone actuelle : <strong class="txt-zone"></strong>. Le compteur ne bouge pas : la correction s'applique à
                <strong>tous ses contrats</strong>. À utiliser pour corriger une erreur de saisie (par exemple un contrat repris
                automatiquement lors de la migration), pas pour un déménagement.</p>
            <select name="quartier" class="form-select sel-zone-corr" required><?= optionsZones($zonesParType) ?></select>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
    </form>
</div></div></div>

<?php
function blocCloture($d, $libDate = 'Date de fin', $libIndex = 'Index de clôture')
{
    ob_start(); ?>
    <div class="mb-2"><label class="form-label mb-0"><?= h($libDate) ?></label>
        <input type="date" name="date_fin" class="form-control" value="<?= h($d['aujourdhui']) ?>" max="<?= h($d['aujourdhui']) ?>" required></div>
    <div class="mb-2"><label class="form-label mb-0"><?= h($libIndex) ?></label>
        <input type="text" name="index_fin" class="form-control in-index">
        <div class="aide mt-1">Dernier index relevé : <strong class="txt-dernier"></strong>. S'il est plus élevé, un relevé de
            clôture et sa facture sont générés pour le client sortant.</div></div>
    <?php return ob_get_clean();
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const LIBRES = <?= json_encode($libresJs, JSON_UNESCAPED_UNICODE) ?>;
const ZONES  = <?= json_encode($zonesJs, JSON_UNESCAPED_UNICODE) ?>;
const fmt = n => Number(n).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

function initBlocCompteur(modal, typeImpose) {
    const sel = modal.querySelector('.sel-libre');
    if (!sel) return;
    const zone = modal.querySelector('.sel-zone'), infoZone = modal.querySelector('.info-zone');
    const infoLibre = modal.querySelector('.info-libre');
    const libres = LIBRES.filter(c => !typeImpose || c.type === typeImpose);
    sel.innerHTML = '<option value="">— Choisir un compteur libre —</option>' + libres.map(c =>
        `<option value="${c.id}">n° ${c.id} — ${c.type === 'Eau' ? 'Eau' : 'Électricité'} — index ${fmt(c.index)}`
        + `${c.quartier ? ' — ' + ZONES[c.quartier] : ' — jamais utilisé'}</option>`).join('');
    if (typeImpose) {
        modal.querySelector('.type-impose').value = typeImpose;
        modal.querySelector('.type-impose-label').value = typeImpose === 'Eau' ? 'Eau' : 'Électricité';
    }
    const majMode = () => {
        const neuf = modal.querySelector('input[name="mode"]:checked').value === 'neuf';
        modal.querySelector('.bloc-libre').style.display = neuf ? 'none' : '';
        modal.querySelector('.bloc-neuf').style.display  = neuf ? '' : 'none';
        majLibre();
    };
    const majLibre = () => {
        const neuf = modal.querySelector('input[name="mode"]:checked').value === 'neuf';
        const c = neuf ? null : libres.find(x => String(x.id) === sel.value);
        zone.classList.remove('zone-imposee'); infoZone.textContent = ''; infoLibre.textContent = '';
        if (c) {
            infoLibre.textContent = `Index de départ du contrat : ${fmt(c.index)} ${c.type === 'Eau' ? 'm³' : 'kWh'}.`;
            if (c.quartier) {
                zone.value = c.quartier; zone.classList.add('zone-imposee');
                infoZone.textContent = 'Zone imposée : ce compteur est déjà installé dans cette zone.';
            }
        }
    };
    modal.querySelectorAll('input[name="mode"]').forEach(r => r.onchange = majMode);
    sel.onchange = majLibre;
    majMode();
}
initBlocCompteur(document.getElementById('mCreer'), null);

const esc = s => String(s ?? '').replace(/[&<>"']/g, ch => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch]));
const sansAccent = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

function surligner(texte, saisie) {
    const mots = saisie.trim().split(/\s+/).filter(m => m.length > 0).map(sansAccent);
    const base = sansAccent(texte);
    const marque = new Array(texte.length).fill(false);
    mots.forEach(m => { let i = base.indexOf(m); while (m && i >= 0) { for (let k = i; k < i + m.length; k++) marque[k] = true; i = base.indexOf(m, i + m.length); } });
    let html = '', ouvert = false;
    [...texte].forEach((ch, i) => {
        if (marque[i] && !ouvert) { html += '<mark>'; ouvert = true; }
        if (!marque[i] && ouvert) { html += '</mark>'; ouvert = false; }
        html += esc(ch);
    });
    return html + (ouvert ? '</mark>' : '');
}

function detailsClient(c) {
    const d = [];
    d.push(`<span><i class="fa-solid fa-id-card"></i> ${c.cin ? esc(c.cin) : '<em>CIN non renseigné</em>'}</span>`);
    if (c.telephone) d.push(`<span><i class="fa-solid fa-phone"></i> ${esc(c.telephone)}</span>`);
    if (c.adresse)   d.push(`<span><i class="fa-solid fa-location-dot"></i> ${esc(c.adresse)}</span>`);
    else if (c.email) d.push(`<span><i class="fa-solid fa-envelope"></i> ${esc(c.email)}</span>`);
    d.push(`<span><i class="fa-solid fa-file-signature"></i> ${c.actifs} contrat${c.actifs > 1 ? 's' : ''} actif${c.actifs > 1 ? 's' : ''}</span>`);
    return `<div class="cp-details">${d.join('')}</div>`;
}

class ClientPicker {
    constructor(racine) {
        this.racine = racine;
        this.id     = racine.querySelector('.cp-id');
        this.texte  = racine.querySelector('.cp-texte');
        this.liste  = racine.querySelector('.cp-liste');
        this.bloc   = racine.querySelector('.cp-recherche');
        this.choisi = racine.querySelector('.cp-choisi');
        this.exclu = 0; this.resultats = []; this.actif = -1; this.minuteur = null; this.requete = null;
        racine.picker = this;

        this.texte.addEventListener('input', () => this.planifier());
        this.texte.addEventListener('keydown', e => this.clavier(e));
        this.texte.addEventListener('focus', () => { if (this.resultats.length) this.ouvrir(); });
        document.addEventListener('click', e => { if (!racine.contains(e.target)) this.fermer(); });
        this.liste.addEventListener('mousedown', e => {
            const item = e.target.closest('.cp-item');
            if (item) { e.preventDefault(); this.choisir(this.resultats[+item.dataset.i]); }
        });
        racine.closest('form').addEventListener('submit', e => {
            if (!this.id.value) { e.preventDefault(); racine.classList.add('is-invalid'); this.texte.focus(); }
        });
        if (racine.dataset.initial) this.choisir(JSON.parse(racine.dataset.initial));
    }
    planifier() {
        this.id.value = ''; this.racine.classList.remove('is-invalid');
        clearTimeout(this.minuteur);
        const q = this.texte.value.trim();
        if (q.length < 2 && !/^\d+$/.test(q)) { this.resultats = []; this.fermer(); return; }
        this.minuteur = setTimeout(() => this.chercher(q), 200);
    }
    async chercher(q) {
        if (this.requete) this.requete.abort();
        this.requete = new AbortController();
        this.liste.innerHTML = '<div class="cp-vide"><span class="spinner-border spinner-border-sm me-2"></span>Recherche…</div>';
        this.ouvrir();
        try {
            const r = await fetch('index.php?page=contrat_clients&q=' + encodeURIComponent(q),
                                  {signal: this.requete.signal, headers: {'Accept': 'application/json'}});
            if (!r.ok || !(r.headers.get('content-type') || '').includes('json')) throw new Error('session');
            const liste = (await r.json()).filter(c => c.id !== this.exclu);
            if (q !== this.texte.value.trim()) return;
            this.afficher(liste, q);
        } catch (e) {
            if (e.name === 'AbortError') return;
            this.liste.innerHTML = '<div class="cp-vide text-danger">Recherche impossible. Rechargez la page (session expirée ?).</div>';
        }
    }
    afficher(liste, q) {
        this.resultats = liste; this.actif = liste.length ? 0 : -1;
        if (!liste.length) {
            this.liste.innerHTML = `<div class="cp-vide">Aucun client ne correspond à « ${esc(q)} ».<br>
                <a href="index.php?page=gestion_client">Créer le client dans Gestion Clients</a></div>`;
            return;
        }
        const parNom = {};
        liste.forEach(c => { const k = sansAccent(c.nom); parNom[k] = (parNom[k] || 0) + 1; });
        this.liste.innerHTML = liste.map((c, i) => `
            <div class="cp-item${i === 0 ? ' actif' : ''}" data-i="${i}" role="option">
                <div><span class="cp-nom">${surligner(c.nom, q)}</span><span class="cp-num">#${c.id}</span>
                    ${parNom[sansAccent(c.nom)] > 1 ? '<span class="cp-homonyme" title="Plusieurs clients portent ce nom : vérifiez le CIN">Homonyme</span>' : ''}</div>
                ${detailsClient(c)}
            </div>`).join('');
        this.ouvrir();
    }
    clavier(e) {
        const ouverte = this.liste.classList.contains('ouverte');
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            if (!this.resultats.length) return;
            e.preventDefault();
            if (!ouverte) this.ouvrir();
            this.actif = (this.actif + (e.key === 'ArrowDown' ? 1 : -1) + this.resultats.length) % this.resultats.length;
            this.liste.querySelectorAll('.cp-item').forEach((el, i) => el.classList.toggle('actif', i === this.actif));
            this.liste.querySelector('.cp-item.actif')?.scrollIntoView({block: 'nearest'});
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (ouverte && this.actif >= 0) this.choisir(this.resultats[this.actif]);
        } else if (e.key === 'Escape' && ouverte) {
            e.preventDefault(); e.stopPropagation(); this.fermer();
        }
    }
    choisir(c) {
        if (!c) return;
        this.id.value = c.id;
        this.racine.classList.remove('is-invalid');
        this.fermer();
        const initiales = c.nom.split(/\s+/).map(m => m[0] || '').join('').slice(0, 2).toUpperCase();
        this.choisi.innerHTML = `
            <div class="cp-avatar">${esc(initiales)}</div>
            <div class="flex-grow-1"><div><span class="cp-nom fw-semibold">${esc(c.nom)}</span>
                <span class="text-muted small">client #${c.id}</span></div>${detailsClient(c)}</div>
            <button type="button" class="btn btn-sm btn-outline-primary cp-changer">Changer</button>`;
        this.choisi.querySelector('.cp-changer').onclick = () => this.vider(true);
        this.bloc.style.display = 'none'; this.choisi.style.display = '';
    }
    vider(focus) {
        this.id.value = ''; this.texte.value = ''; this.resultats = []; this.fermer();
        this.racine.classList.remove('is-invalid');
        this.choisi.style.display = 'none'; this.bloc.style.display = '';
        if (focus) this.texte.focus();
    }
    reinitialiser(exclu) { this.exclu = exclu || 0; this.vider(false); }
    ouvrir() { this.liste.classList.add('ouverte'); this.texte.setAttribute('aria-expanded', 'true'); }
    fermer() { this.liste.classList.remove('ouverte'); this.texte.setAttribute('aria-expanded', 'false'); }
}
document.querySelectorAll('.client-picker').forEach(p => new ClientPicker(p));

['mCreer', 'mTransferer'].forEach(id => document.getElementById(id).addEventListener('shown.bs.modal', e => {
    const t = e.target.querySelector('.cp-texte');
    if (t && t.offsetParent) t.focus();
}));

document.querySelectorAll('[data-ct]').forEach(btn => btn.addEventListener('click', () => {
    const ct = JSON.parse(btn.dataset.ct), modal = document.querySelector(btn.dataset.bsTarget);
    modal.querySelectorAll('.in-id').forEach(e => e.value = ct.id);
    modal.querySelectorAll('.txt-id').forEach(e => e.textContent = 'n° ' + ct.id);
    modal.querySelectorAll('.in-index').forEach(e => e.value = ct.dernierIndex);
    modal.querySelectorAll('.txt-dernier').forEach(e => e.textContent = fmt(ct.dernierIndex) + ' ' + ct.unite);
    modal.querySelectorAll('.txt-resume').forEach(e => e.innerHTML =
        `<strong>${ct.client}</strong> — compteur ${ct.type === 'Eau' ? "d'eau" : "d'électricité"} n° ${ct.compteur} — ${ct.zone}`);
    modal.querySelectorAll('.in-compteur').forEach(e => e.value = ct.compteur);
    modal.querySelectorAll('.txt-compteur').forEach(e => e.textContent = 'n° ' + ct.compteur);
    modal.querySelectorAll('.txt-zone').forEach(e => e.textContent = ct.zone);
    modal.querySelectorAll('.sel-zone-corr').forEach(e => e.value = ct.quartier);
    modal.querySelectorAll('.client-picker').forEach(p => p.picker.reinitialiser(ct.idClient));
    if (modal.id === 'mDemenager') {
        modal.querySelector('.sel-zone').value = '';
        initBlocCompteur(modal, ct.type);
    }
}));

<?php if ($d['ouvrirCreation']): ?>
new bootstrap.Modal(document.getElementById('mCreer')).show();
<?php endif; ?>
</script>
</body>
</html>
