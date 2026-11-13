<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/../BD/Tarif.php';
require_once __DIR__ . '/../BD/Quartier.php';

const TYPES_COMPTEUR = ['Eau' => 'Eau (m³)', 'Electricite' => 'Électricité (kWh)'];
const ANCRES_TARIFS  = ['quartiers', 'types'];

function nombreTarif($s)
{
    $s = str_replace([',', ' '], ['.', ''], trim((string) $s));
    return is_numeric($s) ? round((float) $s, 4) : null;
}

function uniteCompteur($typeCompteur)
{
    return $typeCompteur === 'Eau' ? 'm³' : 'kWh';
}

function validerTypeActivite($seuilEau, $seuilElec, $redEau, $redElec)
{
    if ($seuilEau === null || $seuilElec === null || $seuilEau <= 0 || $seuilElec <= 0) {
        return "Les seuils d'alerte doivent être des nombres strictement positifs.";
    }
    if ($redEau === null || $redElec === null || $redEau < 0 || $redElec < 0) {
        return "Les redevances fixes doivent être des nombres positifs ou nuls.";
    }
    if ($seuilEau > 1000000 || $seuilElec > 1000000 || $redEau > 100000 || $redElec > 100000) {
        return "Valeur trop grande.";
    }
    return null;
}

function validerNomQuartier($nom, $ville)
{
    if ($nom === '' || $ville === '') {
        return "Le nom et la ville du quartier sont obligatoires.";
    }
    if (mb_strlen($nom) > 100 || mb_strlen($ville) > 100) {
        return "Le nom et la ville sont limités à 100 caractères.";
    }
    return null;
}

function actionAjouterQuartier($p)
{
    $nom   = trim((string) ($p['nom'] ?? ''));
    $ville = trim((string) ($p['ville'] ?? ''));
    $type  = (int) ($p['type_activite'] ?? 0);
    if ($e = validerNomQuartier($nom, $ville)) {
        return ['erreur', "Quartier non ajouté : $e"];
    }
    if (!getTypeActiviteDetail($type)) {
        return ['erreur', "Quartier non ajouté : choisissez un type d'activité."];
    }
    if (quartierExiste($nom, $ville)) {
        return ['erreur', "Quartier non ajouté : « $nom » existe déjà à $ville."];
    }
    ajouterQuartier($nom, $ville, $type);
    return ['succes', "Quartier « $nom » ($ville) ajouté."];
}

function actionModifierQuartier($p)
{
    $id    = (int) ($p['id_quartier'] ?? 0);
    $nom   = trim((string) ($p['nom'] ?? ''));
    $ville = trim((string) ($p['ville'] ?? ''));
    $type  = (int) ($p['type_activite'] ?? 0);
    $q     = getQuartierParId($id);
    if (!$q) {
        return ['erreur', "Quartier non modifié : introuvable."];
    }
    if ($e = validerNomQuartier($nom, $ville)) {
        return ['erreur', "Quartier non modifié : $e"];
    }
    $nouveauType = getTypeActiviteDetail($type);
    if (!$nouveauType) {
        return ['erreur', "Quartier non modifié : choisissez un type d'activité."];
    }
    if (quartierExiste($nom, $ville, $id)) {
        return ['erreur', "Quartier non modifié : « $nom » existe déjà à $ville."];
    }
    modifierQuartier($id, $nom, $ville, $type);

    $msg = "Quartier « $nom » ($ville) modifié.";
    $nb  = compterContratsActifsQuartier($id);
    if ($type !== (int) $q['ID_Type_Activite'] && $nb > 0) {
        $msg .= " Attention : $nb contrat(s) actif(s) sont maintenant facturés au tarif « {$nouveauType['Libelle']} ».";
    }
    return ['succes', $msg];
}

function actionSupprimerQuartier($p)
{
    $id = (int) ($p['id_quartier'] ?? 0);
    $q  = getQuartierParId($id);
    if (!$q) {
        return ['erreur', "Suppression impossible : quartier introuvable."];
    }
    $actifs = compterContratsActifsQuartier($id);
    if ($actifs > 0) {
        return ['erreur', "Suppression impossible : le quartier « {$q['Nom']} » ({$q['Ville']}) est utilisé par $actifs contrat(s) actif(s)."];
    }
    $total = compterContratsQuartier($id);
    if ($total > 0) {
        return ['erreur', "Suppression impossible : $total contrat(s) résilié(s) sont rattachés au quartier « {$q['Nom']} » (historique à conserver)."];
    }
    try {
        supprimerQuartier($id);
    } catch (Throwable $e) {
        return ['erreur', "Suppression impossible : quartier encore référencé ailleurs."];
    }
    return ['succes', "Quartier « {$q['Nom']} » ({$q['Ville']}) supprimé."];
}

function actionModifierTypeActivite($p)
{
    $id   = (int) ($p['id_type'] ?? 0);
    $type = getTypeActiviteDetail($id);
    if (!$type) {
        return ['erreur', "Type d'activité introuvable."];
    }
    $v = [nombreTarif($p['seuil_eau'] ?? ''), nombreTarif($p['seuil_elec'] ?? ''),
          nombreTarif($p['redevance_eau'] ?? ''), nombreTarif($p['redevance_elec'] ?? '')];
    if ($e = validerTypeActivite(...$v)) {
        return ['erreur', "« {$type['Libelle']} » non modifié : $e"];
    }
    modifierTypeActivite($id, ...$v);
    chargerTarifs('Eau', null, true);
    return ['succes', "Seuils et redevances du type « {$type['Libelle']} » enregistrés."];
}

function traiterActionTarifs()
{
    $p = $_POST;
    switch ($p['action'] ?? '') {
        case 'quartier_ajouter':   $r = actionAjouterQuartier($p);     $ancre = 'quartiers'; break;
        case 'quartier_modifier':  $r = actionModifierQuartier($p);    $ancre = 'quartiers'; break;
        case 'quartier_supprimer': $r = actionSupprimerQuartier($p);   $ancre = 'quartiers'; break;
        case 'type_modifier':      $r = actionModifierTypeActivite($p); $ancre = 'types';    break;
        default:                   $r = ['erreur', 'Action inconnue.']; $ancre = 'quartiers';
    }
    $_SESSION['tarif_msg'] = ['type' => $r[0], 'texte' => $r[1]];
    header('Location: index.php?page=quartiers_tarifs#' . (in_array($ancre, ANCRES_TARIFS, true) ? $ancre : 'quartiers'));
    exit;
}

function preparerPageQuartiersTarifs()
{
    $quartiers = getQuartiers();
    foreach ($quartiers as &$q) {
        $q['nb_actifs'] = compterContratsActifsQuartier($q['ID_Quartier']);
        $q['supprimable'] = $q['nb_actifs'] === 0;
    }
    unset($q);

    $types    = getTypesActiviteDetail();
    $tranches = [];
    foreach ($types as $t) {
        foreach (array_keys(TYPES_COMPTEUR) as $c) {
            $basse = 0.0;
            $lignes = getTranches($t['ID_Type_Activite'], $c);
            foreach ($lignes as &$l) {
                $l['Borne_Basse'] = $basse;
                $basse = $l['Borne_Haute'] === null ? $basse : (float) $l['Borne_Haute'];
            }
            unset($l);
            $tranches[$t['ID_Type_Activite']][$c] = $lignes;
        }
    }

    $msg = $_SESSION['tarif_msg'] ?? null;
    unset($_SESSION['tarif_msg']);

    return [
        'quartiers' => $quartiers,
        'types'     => $types,
        'tranches'  => $tranches,
        'msg'       => $msg,
    ];
}