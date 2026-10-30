<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Regles.php';
require_once __DIR__ . '/../BD/Facture.php';
require_once __DIR__ . '/../BD/Reclamation.php';

const TYPES_RECLAMATION     = ['Fuite externe', 'Facture', 'Déménagement', 'Saisie bloquée', 'Autre'];
const SOUS_TYPES_FACTURE    = ['Fuite interne', 'Prix dans la facture', 'Autre'];
const RECL_PHOTO_MAX        = 5 * 1024 * 1024;                       // 5 Mo
const RECL_PHOTOS_DOSSIER   = __DIR__ . '/../uploads/reclamations/';
const RECL_PHOTOS_URL       = 'uploads/reclamations/';

if (!function_exists('numeroFacture')) {
    function numeroFacture($idFacture, $annee, $typeCompteur)
    {
        return 'FAC-' . $annee . '-' . ($typeCompteur === 'Eau' ? 'EAU' : 'ELEC')
             . '-' . str_pad((int) $idFacture, 4, '0', STR_PAD_LEFT);
    }
}

function messageReclamation($type, $texte)
{
    $_SESSION['reclamation_msg'] = ['type' => $type, 'texte' => $texte];
    header('Location: index.php?page=reclamations');
    exit;
}

// Enregistre la photo (JPG/PNG, 5 Mo max) et retourne son chemin, null si aucune photo,
// ou termine par un message d'erreur.
function enregistrerPhotoReclamation($idClient)
{
    $f = $_FILES['photo'] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > RECL_PHOTO_MAX) {
        messageReclamation('erreur', 'La photo est trop lourde (5 Mo maximum).');
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        messageReclamation('erreur', 'Échec du téléversement de la photo. Réessayez.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
    if (!$ext || !@getimagesize($f['tmp_name'])) {
        messageReclamation('erreur', 'La photo doit être une image JPG ou PNG valide.');
    }
    if (!is_dir(RECL_PHOTOS_DOSSIER)) {
        mkdir(RECL_PHOTOS_DOSSIER, 0775, true);
    }
    $nom = sprintf('r%d_%s_%s.%s', $idClient, date('Ymd'), bin2hex(random_bytes(6)), $ext);
    if (!move_uploaded_file($f['tmp_name'], RECL_PHOTOS_DOSSIER . $nom)) {
        messageReclamation('erreur', "Impossible d'enregistrer la photo.");
    }
    return RECL_PHOTOS_URL . $nom;
}

function traiterReclamation()
{
    // Fichier plus gros que post_max_size : PHP vide alors $_POST
    if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
        messageReclamation('erreur', 'Le fichier envoyé est trop lourd (5 Mo maximum).');
    }

    $idClient = (int) $_SESSION['user']['id'];
    $type     = trim($_POST['type'] ?? '');
    $desc     = trim($_POST['description'] ?? '');

    if (!in_array($type, TYPES_RECLAMATION, true)) {
        messageReclamation('erreur', 'Veuillez choisir un type de réclamation valide.');
    }
    if (mb_strlen($desc) < 10) {
        messageReclamation('erreur', 'Veuillez décrire votre réclamation (10 caractères minimum).');
    }
    if (mb_strlen($desc) > 1000) {
        messageReclamation('erreur', 'La description est trop longue (1000 caractères maximum).');
    }

    $idFacture = null;
    $sousType  = null;
    $lieu      = null;
    $photo     = null;
    $typeBD    = $type;

    switch ($type) {
        case 'Fuite externe':
            $lieu = trim($_POST['lieu'] ?? '');
            if (mb_strlen($lieu) < 3) {
                messageReclamation('erreur', 'Veuillez indiquer le lieu de la fuite.');
            }
            if (mb_strlen($lieu) > 255) {
                messageReclamation('erreur', 'Le lieu de la fuite est trop long (255 caractères maximum).');
            }
            break;

        case 'Facture':
            $idFacture = filter_var($_POST['id_facture'] ?? null, FILTER_VALIDATE_INT);
            if (!$idFacture || !clientPossedeFacture($idFacture, $idClient)) {
                messageReclamation('erreur', 'Veuillez choisir une de vos factures.');
            }
            $sousType = trim($_POST['sous_type'] ?? '');
            if (!in_array($sousType, SOUS_TYPES_FACTURE, true)) {
                messageReclamation('erreur', 'Veuillez choisir le type de problème de la facture.');
            }
            if (reclamationActiveMemeType($idClient, 'Facture', $idFacture, $sousType)) {
                messageReclamation('erreur', 'Une réclamation de ce type est déjà en cours de traitement pour cette facture.');
            }
            break;

        case 'Déménagement':
        case 'Saisie bloquée':
            if (reclamationActiveMemeType($idClient, $type)) {
                messageReclamation('erreur', 'Une réclamation de ce type est déjà en cours de traitement.');
            }
            break;

        case 'Autre':
            $precision = trim($_POST['precision'] ?? '');
            if ($precision === '') {
                messageReclamation('erreur', 'Veuillez préciser le type de votre réclamation.');
            }
            $typeBD = 'Autre : ' . mb_substr($precision, 0, 80);
            break;
    }

    if ($type === 'Fuite externe') {
        $photo = enregistrerPhotoReclamation($idClient);     // facultative
    }

    if (insererReclamationDetail($idClient, $idFacture ?: null, $typeBD, $desc, $sousType, $lieu, $photo)) {
        messageReclamation('succes', 'Votre réclamation a été envoyée. Le fournisseur vous répondra prochainement.');
    }
    if ($photo) {
        @unlink(__DIR__ . '/../' . $photo);
    }
    messageReclamation('erreur', 'Une erreur est survenue, veuillez réessayer.');
}

function preparerPageReclamations()
{
    $idClient = (int) $_SESSION['user']['id'];

    $factures = [];
    foreach (getFacturesDuClient($idClient) as $f) {
        $eau = $f['Type_Compteur'] === 'Eau';
        $factures[] = [
            'id'    => (int) $f['ID_Facture'],
            'label' => numeroFacture($f['ID_Facture'], $f['Annee'], $f['Type_Compteur'])
                     . ' — ' . ($eau ? 'Eau' : 'Électricité') . ' — '
                     . nomMois($f['Mois']) . ' ' . $f['Annee']
                     . ' — ' . number_format((float) $f['Montant_TTC'], 2, ',', ' ') . ' DH',
        ];
    }

    $classes  = ['Ouverte' => 'ouverte', 'En cours' => 'encours', 'Traitee' => 'traitee', 'Rejetee' => 'rejetee'];
    $libelles = ['Ouverte' => 'Ouverte', 'En cours' => 'En cours', 'Traitee' => 'Traitée', 'Rejetee' => 'Rejetée'];

    $reclamations = [];
    foreach (getReclamationsDuClient($idClient) as $r) {
        $statut = $r['Statut'] ?: 'Ouverte';
        $reclamations[] = [
            'numero'      => 'REC-' . str_pad($r['ID_Reclamation'], 4, '0', STR_PAD_LEFT),
            'date'        => date('d/m/Y', strtotime($r['Date_Reclamation'])),
            'facture'     => $r['ID_Facture'] ? numeroFacture($r['ID_Facture'], $r['Annee'], $r['Type_Compteur']) : null,
            'type'        => $r['Type_Reclamation'],
            'sousType'    => $r['Sous_Type'],
            'lieu'        => $r['Lieu_Fuite'],
            'photo'       => $r['Photo'],
            'description' => $r['Description'],
            'statut'      => $libelles[$statut] ?? $statut,
            'classe'      => $classes[$statut] ?? 'ouverte',
            'reponse'     => $r['Reponse'],
            'dateReponse' => $r['Date_Reponse'] ? date('d/m/Y H:i', strtotime($r['Date_Reponse'])) : null,
        ];
    }

    $msg = $_SESSION['reclamation_msg'] ?? null;
    unset($_SESSION['reclamation_msg']);

    return [
        'factures'     => $factures,
        'reclamations' => $reclamations,
        'message'      => $msg,
        'preselection' => (int) ($_GET['facture'] ?? 0),
        'types'        => TYPES_RECLAMATION,
        'sousTypes'    => SOUS_TYPES_FACTURE,
    ];
}