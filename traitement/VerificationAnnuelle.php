<?php
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/../BD/VerificationAnnuelle.php';
require_once __DIR__ . '/../BD/Notification.php';
require_once __DIR__ . '/../BD/Quartier.php';   // lecture seule : liste des quartiers pour le filtre

/*
 * VÉRIFICATION ANNUELLE (côté fournisseur)
 *
 *   VÉRIFICATION PAR CONTRAT : un contrat = un compteur dans un quartier (donc un seul type : Eau ou
 *   Électricité). Chaque relevé mensuel est rattaché au contrat du compteur qui couvre son mois.
 *   Le fichier de l'agent est encore PAR CLIENT et par type : si un client a plusieurs contrats du même
 *   type (ex. 2 maisons), la décision se prend sur le TOTAL de ses contrats de ce type.
 *
 *   conso contrat = dernier index vérifié de l'année - dernier index de l'année précédente
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


function typeNormal($typeCompteur) { return $typeCompteur === 'Eau' ? 'Eau' : 'Electricite'; }


// Contrat du compteur, du même client, qui couvre le mois d'un relevé.
// Le client est nécessaire : en cas de transfert dans le mois, le relevé de clôture de l'ancien client
// doit rester sur son contrat (et non sur celui du nouveau).
function contratDuReleve($contratsParCompteur, $idCompteur, $idClient, $annee, $mois)
{
    $debutMois = sprintf('%04d-%02d-01', $annee, $mois);
    $finMois   = date('Y-m-t', strtotime($debutMois));
    $choix = null;
    foreach ($contratsParCompteur[(int) $idCompteur] ?? [] as $c) {
        if ((int) $c['ID_Client'] !== (int) $idClient) {
            continue;
        }
        if ($c['Date_Debut'] <= $finMois && ($c['Date_Fin'] === null || $c['Date_Fin'] >= $debutMois)) {
            if ($choix === null || $c['Date_Debut'] > $choix['Date_Debut']
                || ($c['Date_Debut'] === $choix['Date_Debut'] && (int) $c['ID_Contrat'] > (int) $choix['ID_Contrat'])) {
                $choix = $c;
            }
        }
    }
    return $choix;
}


// [ID_Contrat] = ['conso' => float, 'dernierMois' => int]
function consoParContrat($annee, $contratsParCompteur)
{
    $parCompteur = [];
    foreach (vaIndexVerifies($annee) as $l) {
        $parCompteur[$l['ID_Compteur']][] = $l;     // déjà triés par année puis mois
    }

    $res = [];
    foreach ($parCompteur as $idCompteur => $rows) {
        $precedent = (float) $rows[0]['Index_Initial'];
        foreach ($rows as $l) {
            if ((int) $l['Annee'] < $annee) {
                $precedent = (float) $l['Index_Actuel'];   // dernier index de l'année précédente
                continue;
            }
            $ct = contratDuReleve($contratsParCompteur, $idCompteur, $l['ID_Client'], (int) $l['Annee'], (int) $l['Mois']);
            if ($ct !== null) {
                $k = (int) $ct['ID_Contrat'];
                $res[$k]['conso']       = ($res[$k]['conso'] ?? 0.0) + (float) $l['Index_Actuel'] - $precedent;
                $res[$k]['dernierMois'] = max($res[$k]['dernierMois'] ?? 0, (int) $l['Mois']);
            }
            $precedent = (float) $l['Index_Actuel'];
        }
    }
    return $res;
}


// [ID_Contrat] = nombre de relevés de l'année encore en anomalie
function anomaliesParContrat($annee, $contratsParCompteur)
{
    $res = [];
    foreach (vaReleveEnAnomalie($annee) as $l) {
        $ct = contratDuReleve($contratsParCompteur, $l['ID_Compteur'], $l['ID_Client'], $annee, (int) $l['Mois']);
        if ($ct !== null) {
            $k = (int) $ct['ID_Contrat'];
            $res[$k] = ($res[$k] ?? 0) + 1;
        }
    }
    return $res;
}


// [ID_Contrat] = ['nb' => , 'total' => ] : factures de l'année non payées
function impayesParContrat($annee, $contratsParCompteur)
{
    $res = [];
    foreach (vaFacturesImpayees($annee) as $l) {
        $ct = contratDuReleve($contratsParCompteur, $l['ID_Compteur'], $l['ID_Client'], $annee, (int) $l['Mois']);
        if ($ct !== null) {
            $k = (int) $ct['ID_Contrat'];
            $res[$k]['nb']    = ($res[$k]['nb'] ?? 0) + 1;
            $res[$k]['total'] = ($res[$k]['total'] ?? 0.0) + (float) $l['Montant_TTC'];
        }
    }
    return $res;
}


/**
 * Calcule la vérification de tous les clients pour une année (sans rien écrire en base).
 * Retourne une liste d'entrées (une par client) : id, nom, inconnu, contrats[] (une ligne par contrat),
 * statut, notifie. Chaque ligne de contrat porte : idContrat, quartier, ville, typeActivite, type,
 * consoContrat, nbGroupe/idsGroupe (contrats du même client et du même type) + le résultat d'evaluerEcart().
 */
function calculerVerificationAnnuelle($annee, $idQuartier = 0)
{
    $contrats = vaContrats($annee);
    $parCompteur = [];
    $parClient   = [];
    foreach ($contrats as $c) {
        $parCompteur[(int) $c['ID_Compteur']][] = $c;
        $parClient[(int) $c['ID_Client']][]     = $c;
    }

    $agent     = vaReleveAgent($annee);
    $conso     = consoParContrat($annee, $parCompteur);
    $anomalies = anomaliesParContrat($annee, $parCompteur);
    $impayes   = impayesParContrat($annee, $parCompteur);
    $infos     = vaClients();

    $ids = array_unique(array_merge(array_keys($agent), array_keys($parClient)));
    sort($ids);

    $out = [];
    foreach ($ids as $id) {
        $lignes = [];
        foreach (TYPES_COMPTEUR as $type) {
            $groupe = array_values(array_filter($parClient[$id] ?? [], fn($c) => typeNormal($c['Type_Compteur']) === $type));

            $a = isset($agent[$id])
                ? (float) $agent[$id][$type === 'Eau' ? 'ConsommationEau' : 'ConsommationElect']
                : null;

            $cGroupe = null; $dernierMois = 0; $nbAnom = 0;
            foreach ($groupe as $c) {
                $k = (int) $c['ID_Contrat'];
                if (isset($conso[$k])) {
                    $cGroupe     = ($cGroupe ?? 0.0) + $conso[$k]['conso'];
                    $dernierMois = max($dernierMois, $conso[$k]['dernierMois']);
                }
                $nbAnom += $anomalies[$k] ?? 0;
            }

            if ($a !== null && $cGroupe === null && $a == 0.0) {
                continue;                           // pas de contrat de ce type et rien relevé
            }
            if ($a === null && $cGroupe === null) {
                continue;
            }
            $r = evaluerEcart($cGroupe, $a, $type, $dernierMois ?: 12, $nbAnom);

            if (!$groupe) {                         // relevé agent pour un client sans contrat cette année
                $lignes[] = array_merge($r, [
                    'type' => $type, 'idContrat' => null, 'idQuartier' => null, 'quartier' => null, 'ville' => null,
                    'typeActivite' => null, 'consoContrat' => null, 'nbGroupe' => 1, 'idsGroupe' => [],
                    'impNb' => 0, 'impTotal' => 0.0,
                ]);
                continue;
            }
            $idsGroupe = array_map(fn($c) => (int) $c['ID_Contrat'], $groupe);
            foreach ($groupe as $c) {
                $k = (int) $c['ID_Contrat'];
                $lignes[] = array_merge($r, [
                    'type' => $type, 'idContrat' => $k,
                    'idQuartier' => (int) $c['ID_Quartier'], 'quartier' => $c['Quartier'], 'ville' => $c['Ville'],
                    'typeActivite' => $c['Type_Activite'], 'statutContrat' => $c['Statut'],
                    'consoContrat' => $conso[$k]['conso'] ?? null,
                    'nbGroupe' => count($groupe), 'idsGroupe' => $idsGroupe,
                    'impNb' => $impayes[$k]['nb'] ?? 0, 'impTotal' => $impayes[$k]['total'] ?? 0.0,
                ]);
            }
        }
        if (!$lignes) {
            continue;
        }

        $inconnu = !isset($infos[$id]);
        $out[] = [
            'id'       => $id,
            'nom'      => $inconnu ? 'Client inconnu' : trim($infos[$id]['Prenom'] . ' ' . $infos[$id]['Nom']),
            'inconnu'  => $inconnu,
            'contrats' => $lignes,
            'statut'   => statutGlobal($lignes),
            'notifie'  => $inconnu ? null : vaDerniereNotification($id, $annee),
        ];
    }
    return $idQuartier ? filtrerParQuartier($out, $idQuartier) : $out;
}


// Ne garde que les lignes de contrat du quartier choisi (les clients sans ligne disparaissent)
function filtrerParQuartier($entrees, $idQuartier)
{
    $out = [];
    foreach ($entrees as $e) {
        $l = array_values(array_filter($e['contrats'], fn($r) => (int) $r['idQuartier'] === (int) $idQuartier));
        if ($l) {
            $e['contrats'] = $l;
            $e['statut']   = statutGlobal($l);
            $out[] = $e;
        }
    }
    return $out;
}


function notifiable($e)
{
    return !$e['inconnu'] && $e['statut'] !== ST_SANS_AGENT;
}


// Libellé d'un groupe de contrats : « Contrat n°1040 (Al Manar, Larache) » ou « Contrats n°1040, 1041 (total) »
function libelleContrats($r)
{
    if ($r['idContrat'] === null) {
        return 'Compteur sans contrat';
    }
    if (count($r['idsGroupe']) > 1) {
        return 'Contrats n°' . implode(', ', $r['idsGroupe']) . ' (total)';
    }
    return 'Contrat n°' . $r['idContrat'] . ' (' . $r['quartier'] . ', ' . $r['ville'] . ')';
}


// Texte envoyé au client : une ligne par contrat (ou par groupe de contrats du même type)
function construireMessageClient($annee, $nom, $lignesContrat)
{
    $lignes   = ["Bonjour $nom, voici le résultat de la vérification annuelle $annee de vos consommations."];
    $nbPayer  = 0;
    $totPayer = 0.0;
    $bloque   = false;
    $vus      = [];

    foreach ($lignesContrat as $r) {
        $type = $r['type'];
        $lib  = libelleContrats($r) . ' - ' . labelType($type);
        $u    = uniteType($type);
        $premier = !isset($vus[$type]);
        $vus[$type] = true;

        switch ($r['statut']) {
            case ST_CONFORME:
                if ($premier) {
                    $lignes[] = "- $lib : vos relevés mensuels (" . vaNb($r['consoClient']) . " $u) correspondent au relevé de l'agent. Aucun écart.";
                }
                break;
            case ST_REGULARISER:
                if ($premier) {
                    $lignes[] = "- $lib : l'agent a relevé " . vaNb($r['consoAgent']) . " $u sur l'année contre "
                              . vaNb($r['consoClient']) . " $u déclarés (écart " . vaNb(abs($r['ecart'])) . " $u). "
                              . "Régularisation estimée : " . vaNb($r['regul']) . " DH TTC, qui vous sera réclamée par le fournisseur.";
                }
                break;
            case ST_A_VERIFIER:
                $bloque = true;
                if ($premier) {
                    $lignes[] = "- $lib : un écart a été détecté entre vos relevés (" . vaNb($r['consoClient']) . " $u) et ceux de l'agent ("
                              . vaNb($r['consoAgent']) . " $u). Le fournisseur vérifie votre dossier ; vous serez informé du résultat. "
                              . "Vous pouvez déposer une réclamation si vous contestez.";
                }
                continue 2;
            case ST_SANS_SAISIE:
                $bloque = true;
                if ($premier) {
                    $lignes[] = "- $lib : aucun relevé mensuel validé en $annee alors que l'agent a relevé " . vaNb($r['consoAgent'])
                              . " $u. Merci de contacter le fournisseur.";
                }
                continue 2;
            default:
                continue 2;
        }
        // factures à payer uniquement pour les contrats sans blocage
        $nbPayer  += $r['impNb'];
        $totPayer += $r['impTotal'];
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
                          construireMessageClient($annee, $e['nom'], $e['contrats']));
    } catch (Throwable $ex) {
        return "Notification impossible : " . $ex->getMessage();
    }
    return null;
}


function resumerVerification($entrees)
{
    $s = ['total' => count($entrees), 'contrats' => 0, ST_CONFORME => 0, ST_REGULARISER => 0, ST_A_VERIFIER => 0,
          ST_SANS_SAISIE => 0, ST_SANS_AGENT => 0, 'regul' => 0.0, 'impaye' => 0.0, 'anotifier' => 0];
    foreach ($entrees as $e) {
        $s[$e['statut']]++;
        $vus = [];
        foreach ($e['contrats'] as $r) {
            $s['contrats']++;
            $s['impaye'] += $r['impTotal'];
            if (!isset($vus[$r['type']])) {           // la régularisation est calculée une fois par groupe
                $vus[$r['type']] = true;
                $s['regul'] += $r['regul'];
            }
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
    $annees   = vaAnneesDisponibles();
    $annee    = (int) ($_POST['annee'] ?? $_GET['annee'] ?? ($annees[0] ?? date('Y', maintenant())));
    $quartier = (int) ($_POST['quartier'] ?? $_GET['quartier'] ?? 0);      // 0 = tous les quartiers

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action  = $_POST['action'] ?? '';
        $entrees = calculerVerificationAnnuelle($annee);                    // toujours complet : le message couvre tous les contrats
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
            $visibles = array_column(calculerVerificationAnnuelle($annee, $quartier), 'id');   // seulement les clients affichés
            $nb = 0;
            foreach ($entrees as $e) {
                if (in_array($e['id'], $visibles, true) && notifiable($e) && !$e['notifie']) {
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
        header('Location: index.php?page=verification_annuelle&annee=' . $annee . ($quartier ? '&quartier=' . $quartier : ''));
        exit;
    }

    $flash = $_SESSION['flash_va'] ?? null;
    unset($_SESSION['flash_va']);

    $entrees = calculerVerificationAnnuelle($annee, $quartier);
    return [
        'annee'     => $annee,
        'annees'    => $annees ?: [$annee],
        'quartier'  => $quartier,
        'quartiers' => getQuartiers(),
        'entrees'   => $entrees,
        'resume'    => resumerVerification($entrees),
        'flash'     => $flash,
    ];
}


// Contrôleur de la page notifications du client
function preparerPageNotifications()
{
    $idClient = (int) $_SESSION['user']['id'];
    return ['liste' => getNotificationsClient($idClient)];
}