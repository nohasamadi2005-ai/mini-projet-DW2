<?php
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/../BD/VerificationAnnuelle.php';
require_once __DIR__ . '/../BD/Notification.php';

/*
 * VÉRIFICATION ANNUELLE (côté fournisseur)
 *
 *   conso client = dernier index vérifié de l'année - dernier index de l'année précédente
 *                  (ou Index_Initial du compteur s'il n'y en a pas)
 *   conso agent  = valeur du fichier Consommation_Annuelle.txt
 *   écart        = conso client - conso agent
 *
 *   |écart| <= TOLERANCE_ECART %  -> Conforme        : le client doit payer ses factures impayées
 *   client < agent (jusqu'à SEUIL_CONTROLE %) -> Régularisation : consommation non facturée, montant estimé
 *   client > agent (jusqu'à SEUIL_CONTROLE %) -> À vérifier : possible trop-perçu / erreur de saisie ou de relevé
 *   |écart| > SEUIL_CONTROLE %     -> À vérifier : contrôle manuel (nouvelle visite de l'agent, réclamation)
 */
const TOLERANCE_ECART = 5.0;    // en %
const SEUIL_CONTROLE  = 20.0;   // en %

const ST_CONFORME     = 'Conforme';
const ST_REGULARISER  = 'Regularisation';
const ST_A_VERIFIER   = 'A_verifier';
const ST_SANS_SAISIE  = 'Sans_saisie';
const ST_SANS_AGENT   = 'Sans_releve_agent';

const TYPES_COMPTEUR = ['Eau', 'Electricite'];


function labelType($type)  { return $type === 'Eau' ? 'Eau' : 'Électricité'; }
function uniteType($type)  { return $type === 'Eau' ? 'm³' : 'kWh'; }
function vaNb($v, $d = 2)  { return number_format((float) $v, $d, ',', ' '); }

function libelleStatut($statut)
{
    return [
        ST_CONFORME    => 'Conforme',
        ST_REGULARISER => 'À régulariser',
        ST_A_VERIFIER  => 'À vérifier',
        ST_SANS_SAISIE => 'Sans saisie client',
        ST_SANS_AGENT  => 'Sans relevé agent',
    ][$statut] ?? $statut;
}


// Montant TTC annuel estimé. Les tranches sont mensuelles : on répartit la conso sur 12 mois.
// Le client et l'agent sont estimés de la même façon, donc leur différence est comparable.
function estimerMontantAnnuel($conso, $type)
{
    return round(12 * calculerMontants(max(0.0, $conso) / 12, $type)[2], 2);
}


/**
 * Compare la conso du client et celle de l'agent pour UN type de compteur.
 * $consoClient / $consoAgent : float ou null (donnée absente).
 */
function evaluerEcart($consoClient, $consoAgent, $type, $dernierMois = 12, $anomalies = 0)
{
    $r = [
        'consoClient' => $consoClient, 'consoAgent' => $consoAgent,
        'ecart' => null, 'pct' => null,
        'montantClient' => null, 'montantAgent' => null, 'regul' => 0.0,
        'dernierMois' => $dernierMois, 'statut' => ST_CONFORME, 'raison' => '',
    ];

    if ($consoAgent === null) {
        $r['statut'] = ST_SANS_AGENT;
        $r['raison'] = "Le fichier de l'agent ne contient pas ce client.";
        return $r;
    }
    if ($consoClient === null) {
        $r['statut'] = ST_SANS_SAISIE;
        $r['raison'] = "Aucun relevé mensuel validé pour ce compteur cette année.";
        return $r;
    }

    $ecart = round($consoClient - $consoAgent, 2);
    $pct   = $consoAgent > 0 ? round($ecart / $consoAgent * 100, 2) : ($ecart == 0 ? 0.0 : 100.0);
    $r['ecart'] = $ecart;
    $r['pct']   = $pct;
    $r['montantClient'] = estimerMontantAnnuel($consoClient, $type);
    $r['montantAgent']  = estimerMontantAnnuel($consoAgent, $type);

    if ($anomalies > 0) {
        $r['statut'] = ST_A_VERIFIER;
        $r['raison'] = $anomalies . " saisie(s) de l'année encore en anomalie : à traiter avant de conclure.";
    } elseif (abs($pct) <= TOLERANCE_ECART) {
        $r['statut'] = ST_CONFORME;
        $r['raison'] = "Écart dans la tolérance de " . TOLERANCE_ECART . " %.";
    } elseif (abs($pct) > SEUIL_CONTROLE) {
        $r['statut'] = ST_A_VERIFIER;
        $r['raison'] = "Écart de " . vaNb(abs($pct), 1) . " % (> " . SEUIL_CONTROLE . " %) : nouveau relevé ou réclamation à prévoir.";
    } elseif ($ecart < 0) {
        $r['statut'] = ST_REGULARISER;
        $r['regul']  = max(0.0, round($r['montantAgent'] - $r['montantClient'], 2));
        $r['raison'] = "Le client a déclaré moins que l'agent : consommation non facturée."
                     . ($dernierMois < 12 ? " Dernier relevé client : " . nomMois($dernierMois) . "." : '');
    } else {
        $r['statut'] = ST_A_VERIFIER;
        $r['raison'] = "Le client a déclaré plus que l'agent : possible trop-perçu ou erreur de saisie/relevé.";
    }
    return $r;
}


// Statut global d'un client = le plus grave de ses statuts
function statutGlobal($types)
{
    foreach ([ST_A_VERIFIER, ST_REGULARISER, ST_SANS_SAISIE, ST_SANS_AGENT] as $s) {
        foreach ($types as $r) {
            if ($r['statut'] === $s) {
                return $s;
            }
        }
    }
    return ST_CONFORME;
}


// [ID_Client][type] = ['conso' => float, 'dernierMois' => int]
function consoClientParType($annee)
{
    $parCompteur = [];
    foreach (vaIndexVerifies($annee) as $l) {
        $parCompteur[$l['ID_Compteur']][] = $l;     // déjà triés par année puis mois
    }

    $res = [];
    foreach ($parCompteur as $rows) {
        $ref  = (float) $rows[0]['Index_Initial'];
        $last = null;
        foreach ($rows as $l) {
            if ((int) $l['Annee'] < $annee) {
                $ref = (float) $l['Index_Actuel'];  // dernier index de l'année précédente
            } else {
                $last = $l;                          // dernier mois saisi de l'année
            }
        }
        if (!$last) {
            continue;
        }
        $id   = (int) $rows[0]['ID_Client'];
        $type = $rows[0]['Type_Compteur'] === 'Eau' ? 'Eau' : 'Electricite';
        $res[$id][$type]['conso']       = ($res[$id][$type]['conso'] ?? 0.0) + (float) $last['Index_Actuel'] - $ref;
        $res[$id][$type]['dernierMois'] = max($res[$id][$type]['dernierMois'] ?? 0, (int) $last['Mois']);
    }
    return $res;
}


/**
 * Calcule la vérification de tous les clients pour une année (sans rien écrire en base).
 * Retourne une liste d'entrées : id, nom, inconnu, types[type] => résultat, statut, impayes, notifie.
 */
function calculerVerificationAnnuelle($annee)
{
    $agent     = vaReleveAgent($annee);
    $client    = consoClientParType($annee);
    $anomalies = vaAnomaliesEnAttente($annee);
    $impayes   = vaImpayes($annee);
    $infos     = vaClients();

    $ids = array_unique(array_merge(array_keys($agent), array_keys($client)));
    sort($ids);

    $out = [];
    foreach ($ids as $id) {
        $types = [];
        foreach (TYPES_COMPTEUR as $type) {
            $a = isset($agent[$id])
                ? (float) $agent[$id][$type === 'Eau' ? 'ConsommationEau' : 'ConsommationElect']
                : null;
            $c = $client[$id][$type]['conso'] ?? null;

            if ($a !== null && $c === null && $a == 0.0) {
                continue;                           // pas de compteur de ce type et rien relevé
            }
            if ($a === null && $c === null) {
                continue;
            }
            $types[$type] = evaluerEcart($c, $a, $type, $client[$id][$type]['dernierMois'] ?? 12, $anomalies[$id] ?? 0);
        }
        if (!$types) {
            continue;
        }

        $statut = statutGlobal($types);
        $inconnu = !isset($infos[$id]);

        $out[] = [
            'id'      => $id,
            'nom'     => $inconnu ? 'Client inconnu' : trim($infos[$id]['Prenom'] . ' ' . $infos[$id]['Nom']),
            'inconnu' => $inconnu,
            'types'   => $types,
            'statut'  => $statut,
            'impayes' => $impayes[$id] ?? [],
            'notifie' => $inconnu ? null : vaDerniereNotification($id, $annee),
        ];
    }
    return $out;
}


function notifiable($e)
{
    return !$e['inconnu'] && $e['statut'] !== ST_SANS_AGENT;
}


// Texte envoyé au client
function construireMessageClient($annee, $nom, $types, $impayes)
{
    $lignes   = ["Bonjour $nom, voici le résultat de la vérification annuelle $annee de vos consommations."];
    $nbPayer  = 0;
    $totPayer = 0.0;
    $bloque   = false;

    foreach ($types as $type => $r) {
        $lib = labelType($type);
        $u   = uniteType($type);

        switch ($r['statut']) {
            case ST_CONFORME:
                $lignes[] = "- $lib : vos relevés mensuels (" . vaNb($r['consoClient']) . " $u) correspondent au relevé de l'agent. Aucun écart.";
                break;
            case ST_REGULARISER:
                $lignes[] = "- $lib : l'agent a relevé " . vaNb($r['consoAgent']) . " $u sur l'année contre "
                          . vaNb($r['consoClient']) . " $u déclarés (écart " . vaNb(abs($r['ecart'])) . " $u). "
                          . "Régularisation estimée : " . vaNb($r['regul']) . " DH TTC, qui vous sera réclamée par le fournisseur.";
                break;
            case ST_A_VERIFIER:
                $bloque   = true;
                $lignes[] = "- $lib : un écart a été détecté entre vos relevés (" . vaNb($r['consoClient']) . " $u) et ceux de l'agent ("
                          . vaNb($r['consoAgent']) . " $u). Le fournisseur vérifie votre dossier ; vous serez informé du résultat. "
                          . "Vous pouvez déposer une réclamation si vous contestez.";
                continue 2;
            case ST_SANS_SAISIE:
                $bloque   = true;
                $lignes[] = "- $lib : aucun relevé mensuel validé en $annee alors que l'agent a relevé " . vaNb($r['consoAgent'])
                          . " $u. Merci de contacter le fournisseur.";
                continue 2;
            default:
                continue 2;
        }
        // factures à payer uniquement pour les compteurs sans blocage
        $nbPayer  += $impayes[$type]['nb'] ?? 0;
        $totPayer += $impayes[$type]['total'] ?? 0.0;
    }

    if ($nbPayer > 0) {
        $lignes[] = "Merci de régler vos $nbPayer facture(s) impayée(s) de $annee, soit " . vaNb($totPayer)
                  . " DH TTC, depuis « Mes factures ».";
    } elseif (!$bloque) {
        $lignes[] = "Toutes vos factures $annee sont réglées. Merci.";
    }
    return implode("\n", $lignes);
}


// Envoie la notification d'un client. Retourne null si OK, sinon un message d'erreur.
function notifierClientAnnuel($e, $annee)
{
    if (!notifiable($e)) {
        return "Ce client ne peut pas être notifié.";
    }
    try {
        creerNotification($e['id'], (int) $_SESSION['user']['id'],
                          construireMessageClient($annee, $e['nom'], $e['types'], $e['impayes']));
    } catch (Throwable $ex) {
        return "Notification impossible : " . $ex->getMessage();
    }
    return null;
}


function resumerVerification($entrees)
{
    $s = ['total' => count($entrees), ST_CONFORME => 0, ST_REGULARISER => 0, ST_A_VERIFIER => 0,
          ST_SANS_SAISIE => 0, ST_SANS_AGENT => 0, 'regul' => 0.0, 'impaye' => 0.0, 'anotifier' => 0];
    foreach ($entrees as $e) {
        $s[$e['statut']]++;
        foreach ($e['types'] as $r) {
            $s['regul'] += $r['regul'];
        }
        foreach ($e['impayes'] as $i) {
            $s['impaye'] += $i['total'];
        }
        if (notifiable($e) && !$e['notifie']) {
            $s['anotifier']++;
        }
    }
    return $s;
}


// Contrôleur de la page fournisseur (gère aussi les POST, puis redirige)
function preparerPageVerificationAnnuelle()
{
    $annees = vaAnneesDisponibles();
    $annee  = (int) ($_POST['annee'] ?? $_GET['annee'] ?? ($annees[0] ?? date('Y', maintenant())));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action  = $_POST['action'] ?? '';
        $entrees = calculerVerificationAnnuelle($annee);
        $flash   = ['type' => 'succes', 'texte' => ''];

        if ($action === 'notifier') {
            $id = (int) ($_POST['id_client'] ?? 0);
            foreach ($entrees as $e) {
                if ($e['id'] === $id) {
                    $err = notifierClientAnnuel($e, $annee);
                    $flash = $err ? ['type' => 'erreur', 'texte' => $err]
                                  : ['type' => 'succes', 'texte' => "Notification envoyée à " . $e['nom'] . "."];
                }
            }
        } elseif ($action === 'notifier_tous') {
            $nb = 0;
            foreach ($entrees as $e) {
                if (notifiable($e) && !$e['notifie']) {
                    $err = notifierClientAnnuel($e, $annee);
                    if ($err) {
                        $flash = ['type' => 'erreur', 'texte' => $err];
                        break;
                    }
                    $nb++;
                }
            }
            if ($flash['type'] === 'succes') {
                $flash['texte'] = "$nb notification(s) envoyée(s).";
            }
        }
        $_SESSION['flash_va'] = $flash;
        header('Location: index.php?page=verification_annuelle&annee=' . $annee);
        exit;
    }

    $flash = $_SESSION['flash_va'] ?? null;
    unset($_SESSION['flash_va']);

    $entrees = calculerVerificationAnnuelle($annee);
    return [
        'annee'   => $annee,
        'annees'  => $annees ?: [$annee],
        'entrees' => $entrees,
        'resume'  => resumerVerification($entrees),
        'flash'   => $flash,
    ];
}


// Contrôleur de la page notifications du client
function preparerPageNotifications()
{
    $idClient = (int) $_SESSION['user']['id'];
    return ['liste' => getNotificationsClient($idClient)];
}