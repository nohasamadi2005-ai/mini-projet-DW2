<?php

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/../BD/Consommation.php';

const PHOTOS_DOSSIER   = __DIR__ . '/../uploads/compteurs/';
const PHOTOS_URL       = 'uploads/compteurs/';
const PHOTO_TAILLE_MAX = 5 * 1024 * 1024;   // 5 Mo


// type : 'succes' (vert) | 'anomalie' (orange) | 'erreur' (rouge)
function retourSaisie($type, $texte, $idCompteur = 0)
{
    $_SESSION['flash'] = ['type' => $type, 'texte' => $texte];
    header('Location: index.php?page=saisie' . ($idCompteur ? '&c=' . (int) $idCompteur : ''));
    exit;
}

function formaterIndex($v)
{
    $v = (float) $v;
    return number_format($v, fmod($v, 1) == 0.0 ? 0 : 2, ',', ' ');
}


function periodeFr($debut, $fin, $mois, $annee)
{
    $valide = $debut && $fin && strpos($debut, '0000') !== 0 && strpos($fin, '0000') !== 0
              && strtotime($debut) && strtotime($fin);
    if (!$valide) {
        return nomMois($mois) . ' ' . $annee;
    }
    $d = strtotime($debut);
    $f = strtotime($fin);
    if (date('Y-n', $d) === date('Y-n', $f)) {
        return date('d', $d) . ' au ' . date('d', $f) . ' '
             . mb_strtolower(nomMois(date('n', $f))) . ' ' . date('Y', $f);
    }
    return dateFr($debut) . ' au ' . dateFr($fin);
}

function formaterMontant($v)
{
    return number_format((float) $v, 2, ',', ' ');
}

/* ------------------------------------------------------------------ */
/*  Réception du formulaire                                            */
/* ------------------------------------------------------------------ */

function traiterSaisie()
{
    // L'identité vient TOUJOURS de la session, jamais du formulaire.
    $idClient   = (int) $_SESSION['user']['id'];
    $idCompteur = (int) ($_POST['compteur'] ?? 0);

    // 1. Le compteur appartient bien à ce client
    $compteur = getCompteurDuClient($idCompteur, $idClient);
    if (!$compteur) {
        retourSaisie('erreur', 'Compteur invalide.');
    }

    // 2. Quelle saisie vise-t-on ? Resaisie demandée (peut concerner un mois passé) ou mois courant
    $existante = getSaisieEnResaisie($idCompteur);
    $resaisie  = $existante !== null;
    if ($resaisie) {
        $mois  = (int) $existante['Mois'];
        $annee = (int) $existante['Annee'];
    } else {
        $mois      = (int) date('n', maintenant());
        $annee     = (int) date('Y', maintenant());
        $existante = getSaisie($idCompteur, $mois, $annee);
    }

    // 3. Fenêtre 18 -> fin du mois (sauf resaisie demandée par le fournisseur)
    if (!$resaisie && !fenetreOuverte()) {
        retourSaisie('erreur', 'La saisie est fermée. Elle est ouverte du 18 à la fin de chaque mois.', $idCompteur);
    }

    // 4. Doublon
    if ($existante && !$resaisie) {
        retourSaisie('erreur', 'Vous avez déjà saisi ce compteur pour ce mois.', $idCompteur);
    }

    // 5. Index
    $brut = str_replace(',', '.', trim($_POST['nouvel_index'] ?? ''));
    if ($brut === '' || !is_numeric($brut) || (float) $brut < 0 || (float) $brut > 9999999999) {
        retourSaisie('erreur', 'Index invalide : saisissez un nombre positif.', $idCompteur);
    }
    $indexActuel = round((float) $brut, 2);

    // 6. Photo (obligatoire, JPEG/PNG, 5 Mo max, type réel vérifié)
    $f = $_FILES['photo'] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
        retourSaisie('erreur', 'La photo du compteur est obligatoire.', $idCompteur);
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > PHOTO_TAILLE_MAX) {
        retourSaisie('erreur', 'La photo est trop lourde (5 Mo maximum).', $idCompteur);
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        retourSaisie('erreur', 'Échec du téléversement de la photo. Réessayez.', $idCompteur);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
    if (!$ext || !@getimagesize($f['tmp_name'])) {
        retourSaisie('erreur', 'La photo doit être une image JPG ou PNG valide.', $idCompteur);
    }

    // 7. Référence relue EN BASE (jamais depuis le formulaire) + calcul
    $ref   = getReferencePrecedente($compteur, $mois, $annee);
    $delta = round($indexActuel - $ref['index'], 2);

    // 8. Valide ou anomalie ? (une anomalie est ENREGISTRÉE, jamais refusée)
    $typeAnomalie = detecterAnomalie($delta, $compteur['Type_Compteur']);
    $statut       = $typeAnomalie ? 'Anomalie' : 'Verifie';

    // 9. Stockage de la photo (nom aléatoire) puis enregistrement
    if (!is_dir(PHOTOS_DOSSIER)) {
        mkdir(PHOTOS_DOSSIER, 0775, true);
    }
    $nom    = sprintf('c%d_%d%02d_%s.%s', $idCompteur, $annee, $mois, bin2hex(random_bytes(6)), $ext);
    $chemin = PHOTOS_DOSSIER . $nom;
    if (!move_uploaded_file($f['tmp_name'], $chemin)) {
        retourSaisie('erreur', 'Impossible d\'enregistrer la photo.', $idCompteur);
    }
    $photoBD = PHOTOS_URL . $nom;

    try {
        if ($resaisie) {
            $idConso = (int) $existante['ID_Consommation_Mensuelle'];
            modifierSaisie($idConso, $ref['index'], $indexActuel, $delta, $ref['photo'], $photoBD, $statut);
        } else {
            $idConso = (int) insererSaisie($idClient, $idCompteur, $mois, $annee, $ref['index'],
                                           $indexActuel, $delta, $ref['photo'], $photoBD, $statut);
        }
    } catch (mysqli_sql_exception $e) {
        @unlink($chemin);
        if ((int) $e->getCode() === 1062) {   // contrainte UNIQUE : double clic, etc.
            retourSaisie('erreur', 'Vous avez déjà saisi ce compteur pour ce mois.', $idCompteur);
        }
        retourSaisie('erreur', 'Une erreur est survenue. Veuillez réessayer.', $idCompteur);
    }

    // 10. Suite selon le résultat
    if ($typeAnomalie) {
        creerAnomalie($idConso, $typeAnomalie,
            'Index précédent ' . $ref['index'] . ', index saisi ' . $indexActuel . ', écart ' . $delta);
        // Aucune génération de facture : elle reste bloquée jusqu'au traitement par le fournisseur.
        retourSaisie('anomalie',
            'Votre saisie a bien été enregistrée et est actuellement en cours de traitement. '
            . 'Une anomalie a été détectée : le fournisseur va la vérifier. '
            . 'Votre facture sera disponible après cette vérification.', $idCompteur);
    }

   /*genere facture appel si elle existe */
    $texte = 'Votre saisie a été enregistrée et vérifiée, aucun problème détecté.';
    if (function_exists('genererFacture')) {
        try {
            genererFacture($idConso);
        } catch (Throwable $e) {
            
        }
        if (getFactureDeSaisie($idConso)) {
            $texte .= ' Votre facture a été générée.';
        }
    }
    retourSaisie('succes', $texte, $idCompteur);
}

/* ------------------------------------------------------------------ */
/*  Données pour la vue                                                */
/* ------------------------------------------------------------------ */

function preparerPageSaisie()
{
    $idClient = (int) $_SESSION['user']['id'];
    $moisNow  = (int) date('n', maintenant());
    $anneeNow = (int) date('Y', maintenant());
    $ouverte  = fenetreOuverte();

    $compteurs = [];
    $payload   = [];

    foreach (getCompteursDuClient($idClient) as $c) {
        $id   = (int) $c['ID_Compteur'];
        $eau  = $c['Type_Compteur'] === 'Eau';
        $prefixe = $eau ? 'EAU-' : 'ELEC-';

        $resaisie = getSaisieEnResaisie($id);
        $saisie   = $resaisie ?: getSaisie($id, $moisNow, $anneeNow);
        $mc = $resaisie ? (int) $resaisie['Mois']  : $moisNow;
        $ac = $resaisie ? (int) $resaisie['Annee'] : $anneeNow;
        $ref = getReferencePrecedente($c, $mc, $ac);   // période strictement antérieure

        if ($resaisie) {
            $etat = 'resaisie';
        } elseif ($saisie) {
            $etat = $saisie['Statut_Traitement'] === 'Verifie' ? 'verifie' : 'anomalie';
        } else {
            $etat = $ouverte ? 'libre' : 'ferme';
        }

        $facture = null;
        if ($saisie) {
            $fa = getFactureDeSaisie($saisie['ID_Consommation_Mensuelle']);
            if ($fa) {
                $payee    = $fa['Statut_Paiement'] === 'Payee';
                $facture = [
                    'numero'     => 'FAC-' . $fa['Annee'] . '-' . ($eau ? 'EAU' : 'ELEC')
                                  . '-' . str_pad($fa['ID_Facture'], 4, '0', STR_PAD_LEFT),
                    'type'       => $fa['Type_Compteur'],
                    'typeLabel'  => $eau ? 'Eau' : 'Électricité',
                    'date'       => dateFr($fa['Date_Facture']),
                    'periode'    => periodeFr($fa['Periode_Debut'], $fa['Periode_Fin'], $fa['Mois'], $fa['Annee']),
                    'compteur'   => $prefixe . str_pad($fa['ID_Compteur'], 5, '0', STR_PAD_LEFT),
                    'client'     => [
                        'nom'     => trim($fa['Prenom'] . ' ' . $fa['Nom']),
                        'adresse' => $fa['Adresse'],
                        'tel'     => $fa['Telephone'],
                        'email'   => $fa['Email'],
                    ],
                    'indexPrec'  => formaterIndex($fa['Index_Precedent']),
                    'indexAct'   => formaterIndex($fa['Index_Actuel']),
                    'conso'      => formaterIndex($fa['Consommation']),
                    'ht'         => formaterMontant($fa['Montant_HT']),
                    'tva'        => formaterMontant($fa['TVA']),
                    'ttc'        => formaterMontant($fa['Montant_TTC']),
                    'paye'       => $payee,
                    'paiement'   => $payee ? 'Payée' : 'Impayée',
                    'photoAvant' => $fa['Ancien_photo'],
                    'photoApres' => $fa['Photo_Actuelle'],
                ];
            }
        }

        $compteurs[] = [
            'id'     => $id,
            'label'  => $eau ? 'Eau' : 'Électricité',
            'numero' => $prefixe . str_pad($id, 5, '0', STR_PAD_LEFT),
            'unite'  => $eau ? 'm³' : 'kWh',
            'icone'  => $eau ? 'goutte' : 'eclair',
        ];
        $payload[$id] = [
            'unite'             => $eau ? 'm³' : 'kWh',
            'etat'              => $etat,
            'moisActuel'        => nomMois($mc) . ' ' . $ac,
            'moisPrecedent'     => $ref['premiere']
                                    ? nomMois($mc == 1 ? 12 : $mc - 1) . ' ' . ($mc == 1 ? $ac - 1 : $ac)
                                    : nomMois($ref['mois']) . ' ' . $ref['annee'],
            'premiere'          => $ref['premiere'],
            'indexPrecedent'    => formaterIndex($ref['index']),
            'indexPrecedentNum' => $ref['index'],
            'photo'             => $ref['photo'],
            'datePhoto'         => $ref['date'] ? dateFr($ref['date']) : null,
            'facture'           => $facture,
        ];
    }

    // Compteur affiché : celui du retour de formulaire (?c=), sinon le premier
    $choisi = (int) ($_GET['c'] ?? 0);
    if (!isset($payload[$choisi])) {
        $choisi = $compteurs ? $compteurs[0]['id'] : 0;
    }

    if ($ouverte) {
        $fenetreTexte = 'Vous pouvez saisir votre relevé du 18 au ' . date('t', maintenant()) . ' '
                      . mb_strtolower(nomMois($moisNow)) . ' ' . $anneeNow . '.';
        $pastille     = joursRestants() . ' jour' . (joursRestants() > 1 ? 's' : '') . ' restant' . (joursRestants() > 1 ? 's' : '');
    } else {
        $fenetreTexte = 'La saisie ouvrira le 18 ' . mb_strtolower(nomMois($moisNow)) . ' ' . $anneeNow . '.';
        $pastille     = 'Ouverture le 18';
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return compact('compteurs', 'payload', 'choisi', 'ouverte', 'fenetreTexte', 'pastille', 'flash');
}