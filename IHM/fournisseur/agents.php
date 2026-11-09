<?php
/** @var array $d données préparées par preparerPageAgents() (traitement/agent.php) */
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}

$agents       = $d['agents'];
$quartiers    = $d['quartiers'];
$affectations = $d['affectations'];          // [ID_Quartier => ID_Agent]

// Première lettre d'un texte (gère les accents, sans dépendre de l'extension mbstring)
$premiereLettre = function ($texte) {
    return preg_match('/^./us', (string) $texte, $m) ? $m[0] : '';
};

// Nom complet de chaque agent, pour indiquer qui a déjà un quartier
$nomAgent = [];
foreach ($agents as $a) {
    $nomAgent[(int) $a['ID_Agent']] = trim($a['Prenom'] . ' ' . $a['Nom']);
}

// Quartiers qui n'ont encore aucun agent
$sansAgent = array_values(array_filter($quartiers, function ($q) use ($affectations) {
    return !isset($affectations[(int) $q['ID_Quartier']]);
}));
$nbAffectes = count($quartiers) - count($sansAgent);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fluxio - Gestion des Agents</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }

        .sidebar { width: 270px; background-color: #0F2137; color: #fff; display: flex; flex-direction: column; flex: none; }
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
        .container { padding: 30px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 22px; }
        .card-kpi { background: #fff; padding: 18px; border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card-kpi .kpi-label { font-size: 12px; color: #64748B; font-weight: 500; margin-bottom: 5px; }
        .card-kpi .kpi-value { font-size: 22px; font-weight: 700; color: #0F2137; }
        .card-kpi .kpi-value.alerte { color: #B45309; }
        .card-kpi .kpi-sub { font-size: 12px; color: #64748B; margin-top: 2px; }

        .flash { padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-size: 13px; font-weight: 500; }
        .flash.succes { background: #DCFCE7; color: #15803D; } .flash.erreur { background: #FEE2E2; color: #B91C1C; }
        .info { background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E40AF; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; line-height: 1.6; }

        .agent-card { background: #fff; border: 1px solid #E2E8F0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 18px; overflow: hidden; }
        .agent-head { padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; border-bottom: 1px solid #E2E8F0; background: #F8FAFC; }
        .agent-id { display: flex; align-items: center; gap: 12px; }
        .agent-avatar { width: 40px; height: 40px; background: #1A3353; color: #38BDF8; font-weight: 600; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .agent-nom { font-size: 15px; font-weight: 700; color: #0F2137; }
        .agent-sub { font-size: 12px; color: #64748B; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; white-space: nowrap; }
        .badge.ok { background: #DCFCE7; color: #15803D; }
        .badge.neutre { background: #E2E8F0; color: #475569; }
        .badge.nb { background: #E0F2FE; color: #0369A1; }

        .quartiers-grid { padding: 18px 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
        .quartier { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid #E2E8F0; border-radius: 10px; cursor: pointer; background: #fff; transition: 0.15s; }
        .quartier:hover { border-color: #93C5FD; background: #F8FAFC; }
        .quartier input { margin-top: 3px; width: 16px; height: 16px; accent-color: #1890FF; flex: none; }
        .quartier.coche { border-color: #1890FF; background: #EFF6FF; }
        .q-nom { font-size: 13px; font-weight: 600; color: #0F2137; }
        .q-sub { font-size: 11px; color: #64748B; margin-top: 2px; }
        .q-autre { font-size: 11px; color: #B45309; margin-top: 2px; font-weight: 500; }
        .agent-foot { padding: 12px 20px 16px; display: flex; justify-content: flex-end; }
        .btn { border: none; border-radius: 8px; padding: 9px 16px; font-size: 13px; font-weight: 600; cursor: pointer; color: #fff; background: #1890FF; }
        .btn:hover { background: #0B7AE0; }
        .vide { text-align: center; padding: 50px 20px; color: #64748B; background: #fff; border: 1px solid #E2E8F0; border-radius: 12px; }
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
            <h1>Gestion des agents et de leurs tournées</h1>
            <div style="font-weight: 600; color: #64748B; font-size: 13px;">Espace Fournisseur</div>
        </div>

        <div class="container">

            <?php if (!empty($d['flash'])): ?>
                <div class="flash <?= $d['flash']['type'] === 'ok' ? 'succes' : 'erreur' ?>"><?= h($d['flash']['texte']) ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="card-kpi">
                    <div class="kpi-label">Agents</div>
                    <div class="kpi-value"><?= count($agents) ?></div>
                    <div class="kpi-sub">Agents terrain</div>
                </div>
                <div class="card-kpi">
                    <div class="kpi-label">Quartiers affectés</div>
                    <div class="kpi-value"><?= $nbAffectes ?> / <?= count($quartiers) ?></div>
                    <div class="kpi-sub">Quartiers qui ont un agent</div>
                </div>
                <div class="card-kpi">
                    <div class="kpi-label">Quartiers sans agent</div>
                    <div class="kpi-value<?= $sansAgent ? ' alerte' : '' ?>"><?= count($sansAgent) ?></div>
                    <div class="kpi-sub">
                        <?= $sansAgent
                            ? h(implode(', ', array_map(fn($q) => $q['Nom'] . ' (' . $q['Ville'] . ')', $sansAgent)))
                            : 'Tous les quartiers sont couverts' ?>
                    </div>
                </div>
            </div>

            <div class="info">
                <i class="fa-solid fa-circle-info"></i>
                Cochez les quartiers dont l'agent a la charge, puis cliquez sur « Enregistrer ». Un quartier n'a qu'un seul agent :
                cocher un quartier déjà attribué à un autre agent le lui retire. Les quartiers cochés forment la tournée
                de l'agent (page « Ma tournée »).
            </div>

            <?php if (empty($agents)): ?>
                <div class="vide">Aucun agent enregistré.</div>
            <?php endif; ?>

            <?php foreach ($agents as $a): ?>
                <?php
                    $idAgent  = (int) $a['ID_Agent'];
                    $initiales = strtoupper($premiereLettre($a['Prenom']) . $premiereLettre($a['Nom']));
                ?>
                <form method="post" action="index.php?page=agents_action" class="agent-card">
                    <input type="hidden" name="id_agent" value="<?= $idAgent ?>">

                    <div class="agent-head">
                        <div class="agent-id">
                            <div class="agent-avatar"><?= h($initiales) ?></div>
                            <div>
                                <div class="agent-nom"><?= h($a['Prenom'] . ' ' . $a['Nom']) ?></div>
                                <div class="agent-sub"><?= h($a['Email']) ?><?= !empty($a['Telephone']) ? ' · ' . h($a['Telephone']) : '' ?></div>
                            </div>
                        </div>
                        <div>
                            <span class="badge nb"><?= (int) $a['Nb_Quartiers'] ?> quartier(s)</span>
                            <span class="badge <?= !empty($a['Actif']) ? 'ok' : 'neutre' ?>"><?= !empty($a['Actif']) ? 'Actif' : 'Inactif' ?></span>
                        </div>
                    </div>

                    <div class="quartiers-grid">
                        <?php foreach ($quartiers as $q): ?>
                            <?php
                                $idQ        = (int) $q['ID_Quartier'];
                                $proprio    = $affectations[$idQ] ?? null;
                                $estCoche   = $proprio === $idAgent;
                                $chezAutre  = $proprio !== null && !$estCoche;
                            ?>
                            <label class="quartier<?= $estCoche ? ' coche' : '' ?>">
                                <input type="checkbox" name="quartiers[]" value="<?= $idQ ?>"<?= $estCoche ? ' checked' : '' ?>>
                                <span>
                                    <span class="q-nom"><?= h($q['Nom']) ?></span><br>
                                    <span class="q-sub"><?= h($q['Ville']) ?> · <?= h($q['Type_Activite']) ?></span>
                                    <?php if ($chezAutre): ?>
                                        <br><span class="q-autre">Actuellement : <?= h($nomAgent[$proprio] ?? 'autre agent') ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="agent-foot">
                        <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Enregistrer la tournée</button>
                    </div>
                </form>
            <?php endforeach; ?>

        </div>
    </div>

</body>
</html>