<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/../BD/Tarif.php';
require_once __DIR__ . '/../BD/Quartier.php';

const TYPES_COMPTEUR = ['Eau' => 'Eau (m³)', 'Electricite' => 'Électricité (kWh)'];
const ANCRES_TARIFS  = ['quartiers', 'types', 'tranches', 'simulateur'];

function nombreTarif($s)
{
    $s = str_replace([',', ' '], ['.', ''], trim((string) $s));
    return is_numeric($s) ? round((float) $s, 4) : null;
}

function uniteCompteur($typeCompteur)
{
    return $typeCompteur === 'Eau' ? 'm³' : 'kWh';
}

function validerNouvelleTranche(array $existantes, $borne, $prix)
{
    if ($prix === null || $prix <= 0) {
        return "Le prix unitaire doit être un nombre strictement positif.";
    }
    $bornes = array_column($existantes, 'Borne_Haute');

    if ($borne === null) {
        foreach ($bornes as $b) {
            if ($b === null) {
                return "Il existe déjà une tranche illimitée : il ne peut y en avoir qu'une (la dernière).";
            }
        }
        return null;
    }
    if ($borne <= 0) {
        return "La borne doit être strictement positive.";
    }
    if ($borne > 10000000) {
        return "Borne trop grande : utilisez une tranche illimitée.";
    }
    foreach ($bornes as $b) {
        if ($b !== null && abs((float) $b - $borne) < 0.0001) {
            return "Une tranche se termine déjà à " . rtrim(rtrim(number_format($borne, 2, '.', ''), '0'), '.')
                 . " : les bornes doivent être différentes et en ordre croissant.";
        }
    }
    return null;
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

function actionAjouterTranche($p)
{
    $id      = (int) ($p['id_type'] ?? 0);
    $compteur = (string) ($p['type_compteur'] ?? '');
    if (!getTypeActiviteDetail($id) || !isset(TYPES_COMPTEUR[$compteur])) {
        return ['erreur', "Tranche non ajoutée : type d'activité ou de compteur invalide."];
    }
    $illimitee = !empty($p['illimitee']);
    $borne     = $illimitee ? null : nombreTarif($p['borne'] ?? '');
    if (!$illimitee && $borne === null) {
        return ['erreur', "Tranche non ajoutée : saisissez la borne haute (ou cochez « illimitée »)."];
    }
    $prix = nombreTarif($p['prix'] ?? '');
    if ($e = validerNouvelleTranche(getTranches($id, $compteur), $borne, $prix)) {
        return ['erreur', "Tranche non ajoutée : $e"];
    }
    try {
        ajouterTranche($id, $compteur, $borne, $prix);
    } catch (Throwable $e) {
        return ['erreur', "Tranche non ajoutée : cette borne existe déjà."];
    }
    chargerTarifs('Eau', null, true);
    return ['succes', "Tranche ajoutée (" . ($borne === null ? 'illimitée' : "jusqu'à $borne " . uniteCompteur($compteur)) . ", $prix DH)."];
}

function actionModifierTranche($p)
{
    $t = getTrancheParId((int) ($p['id_tarif'] ?? 0));
    if (!$t) {
        return ['erreur', "Tranche introuvable."];
    }
    $prix = nombreTarif($p['prix'] ?? '');
    if ($prix === null || $prix <= 0) {
        return ['erreur', "Tranche non modifiée : le prix doit être un nombre strictement positif."];
    }
    modifierTranche($t['ID_Tarif'], $prix);
    chargerTarifs('Eau', null, true);
    return ['succes', "Tranche modifiée (prix : $prix DH)."];
}

function actionSupprimerTranche($p)
{
    $t = getTrancheParId((int) ($p['id_tarif'] ?? 0));
    if (!$t) {
        return ['erreur', "Tranche introuvable."];
    }
    if ($t['Borne_Haute'] === null) {
        return ['erreur', "Suppression impossible : la tranche illimitée est obligatoire (sinon les grosses consommations ne seraient pas facturées). Modifiez son prix à la place."];
    }
    supprimerTranche($t['ID_Tarif']);
    chargerTarifs('Eau', null, true);
    return ['succes', "Tranche « jusqu'à " . (float) $t['Borne_Haute'] . " " . uniteCompteur($t['Type_Compteur']) . " » supprimée."];
}

function traiterActionTarifs()
{
    $p = $_POST;
    switch ($p['action'] ?? '') {
        case 'quartier_ajouter':   $r = actionAjouterQuartier($p);     $ancre = 'quartiers'; break;
        case 'quartier_modifier':  $r = actionModifierQuartier($p);    $ancre = 'quartiers'; break;
        case 'quartier_supprimer': $r = actionSupprimerQuartier($p);   $ancre = 'quartiers'; break;
        case 'type_modifier':      $r = actionModifierTypeActivite($p); $ancre = 'types';    break;
        case 'tranche_ajouter':    $r = actionAjouterTranche($p);      $ancre = 'tranches';  break;
        case 'tranche_modifier':   $r = actionModifierTranche($p);     $ancre = 'tranches';  break;
        case 'tranche_supprimer':  $r = actionSupprimerTranche($p);    $ancre = 'tranches';  break;
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

    $sim = ['conso' => '', 'type' => (int) ($types[0]['ID_Type_Activite'] ?? 1), 'compteur' => 'Electricite',
            'resultat' => null, 'erreur' => null, 'libelle' => null];
    if (isset($_GET['sim_conso'])) {
        $sim['conso']    = trim((string) $_GET['sim_conso']);
        $sim['type']     = (int) ($_GET['sim_type'] ?? 0);
        $sim['compteur'] = isset(TYPES_COMPTEUR[$_GET['sim_compteur'] ?? '']) ? $_GET['sim_compteur'] : 'Electricite';
        $conso = nombreTarif($sim['conso']);
        $type  = getTypeActiviteDetail($sim['type']);
        if ($conso === null || $conso < 0 || $conso > 10000000) {
            $sim['erreur'] = "Saisissez une consommation valide (nombre positif).";
        } elseif (!$type) {
            $sim['erreur'] = "Choisissez un type d'activité.";
        } else {
            $sim['resultat'] = detailMontants($conso, $sim['compteur'], $type['ID_Type_Activite']);
            $sim['libelle']  = $type['Libelle'];
            $sim['unite']    = uniteCompteur($sim['compteur']);
            $sim['seuil']    = (float) $type[$sim['compteur'] === 'Eau' ? 'Seuil_Eau' : 'Seuil_Elec'];
        }
    }

    $msg = $_SESSION['tarif_msg'] ?? null;
    unset($_SESSION['tarif_msg']);

    return [
        'quartiers' => $quartiers,
        'types'     => $types,
        'tranches'  => $tranches,
        'sim'       => $sim,
        'msg'       => $msg,
    ];
}