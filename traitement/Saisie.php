<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/../BD/Consommation.php';
require_once __DIR__ . '/Facture.php';

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



function traiterSaisie()
{

    $idClient   = (int) $_SESSION['user']['id'];
    $idCompteur = (int) ($_POST['compteur'] ?? 0);

  
    $compteur = getCompteurDuClient($idCompteur, $idClient);
    if (!$compteur) {
        retourSaisie('erreur', 'Compteur invalide.');
    }

    $existante = getSaisieEnResaisie($idCompteur);
    $resaisie  = $existante !== null;
    $texte = 'Votre saisie a été enregistrée et vérifiée, aucun problème détecté.';
    if ($resaisie) {
        $mois  = (int) $existante['Mois'];
        $annee = (int) $existante['Annee'];
     $texte = 'Votre nouvelle saisie de ' . nomMois($mois) . ' ' . $annee
           . ' a été vérifiée.';
    } else {
        $mois      = (int) date('n', maintenant());
        $annee     = (int) date('Y', maintenant());
        $existante = getSaisie($idCompteur, $mois, $annee);
    }

  
    if (!$resaisie && !fenetreOuverte()) {
        retourSaisie('erreur', 'La saisie est fermée. Elle est ouverte du 18 à la fin de chaque mois.', $idCompteur);
    }

    if ($existante && !$resaisie) {
        retourSaisie('erreur', 'Vous avez déjà saisi ce compteur pour ce mois.', $idCompteur);
    }

 
    $brut = str_replace(',', '.', trim($_POST['nouvel_index'] ?? ''));
    if ($brut === '' || !is_numeric($brut) || (float) $brut < 0 || (float) $brut > 9999999999) {
        retourSaisie('erreur', 'Index invalide : saisissez un nombre positif.', $idCompteur);
    }
    $indexActuel = round((float) $brut, 2);

    
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


    $ref   = getReferencePrecedente($compteur, $mois, $annee);
    $delta = round($indexActuel - $ref['index'], 2);


    $typeAnomalie = detecterAnomalie($delta, $compteur['Type_Compteur']);
    $statut       = $typeAnomalie ? 'Anomalie' : 'Verifie';

   
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
        if ((int) $e->getCode() === 1062) {   
            retourSaisie('erreur', 'Vous avez déjà saisi ce compteur pour ce mois.', $idCompteur);
        }
        retourSaisie('erreur', 'Une erreur est survenue. Veuillez réessayer.', $idCompteur);
    }
  if ($typeAnomalie) {
        try {
            creerAnomalie($idConso, $typeAnomalie, 'Détectée à la saisie');
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
    }
   
if ($typeAnomalie === 'Surconsommation') {
    if (function_exists('genererFacture') && !getFactureDeSaisie($idConso)) {
        try {
            genererFacture($idConso);
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
    }

    retourSaisie(
        'anomalie',
        'Surconsommation détectée : le fournisseur va vérifier d’éventuelles fuites. '
        . 'Votre facture a été générée et est disponible dans « Mes factures ».',
        $idCompteur
    );
}
if ($typeAnomalie) {   // Index négatif
    retourSaisie(
        'anomalie',
        'Anomalie détectée : votre index est inférieur à celui du mois précédent. Le fournisseur va vérifier votre saisie.',
        $idCompteur
    );
}


   
    $texte = 'Votre saisie a été enregistrée et vérifiée, aucun problème détecté.';
    if (function_exists('genererFacture')) {
        try {
            genererFacture($idConso);
        } catch (Throwable $e) {
            
        }
        if (getFactureDeSaisie($idConso)) {
            $texte .= $resaisie
        ? ' Votre facture est disponible dans « Mes factures ».'
        : ' Votre facture a été générée.';
        }
    }
    retourSaisie('succes', $texte, $idCompteur);
}



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
        $ref = getReferencePrecedente($c, $mc, $ac);   

        if ($resaisie) {
            $etat = 'resaisie';
       } elseif ($saisie) {
    $s = $saisie['Statut_Traitement'];
    $etat = $s === 'Verifie' ? 'verifie' : ($s === 'Rejete' ? 'rejete' : 'anomalie');
      }else {
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