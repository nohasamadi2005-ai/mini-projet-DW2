<?php
const INSCRIPTION_FOURNISSEUR_TEST = true;   // TEST : mettre false (ou supprimer) à la fin
require_once __DIR__ . '/traitement/auth.php';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

$page = $_GET['page'] ?? 'login';

switch ($page) {

    case 'login':
        if (estConnecte()) {       
            header('Location: index.php?page=' . pageAccueil($_SESSION['user']['role']));
            exit;
        }
        $erreur     = '';
        $emailSaisi = '';
        $roleSaisi  = 'client';
        $message = $_SESSION['message'] ?? '';
        unset($_SESSION['message']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $emailSaisi = trim($_POST['email'] ?? '');
            $roleSaisi  = $_POST['role'] ?? 'client';

            if (seConnecter($roleSaisi, $emailSaisi, $_POST['password'] ?? '')) {
                header('Location: index.php?page=' . pageAccueil($roleSaisi));
                exit;
            }
            $erreur = 'Identifiants incorrects.';
        }
        require __DIR__ . '/IHM/auth/login.php';
        break;

       case 'client':
        header('Location: index.php?page=saisie');
        exit;

   case 'fournisseur':
    case 'gestion_client': // On peut regrouper les deux ou créer le cas dédié
        exigerRole('fournisseur');
        // On charge ton fichier de gestion des clients et compteurs à la place de l'index de test
        require __DIR__ . '/IHM/fournisseur/gestion_client.php'; 
        break;
    
    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            seDeconnecter();
        }
        header('Location: index.php?page=login');
        exit;
    
                case 'register':
        $erreurs   = [];
        $roleSaisi = 'client';
        $v = ['nom' => '', 'prenom' => '', 'adresse' => '', 'telephone' => '', 'email' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($v as $cle => $_) {
                $v[$cle] = trim($_POST[$cle] ?? '');
            }
            // Le serveur décide : "fournisseur" n'est accepté que si le mode TEST est actif
            $roleSaisi = (($_POST['role'] ?? '') === 'fournisseur' && INSCRIPTION_FOURNISSEUR_TEST)
                         ? 'fournisseur' : 'client';

            if ($roleSaisi === 'fournisseur') {
                $erreurs = inscrireFournisseur($v['nom'], $v['prenom'], $v['email'],
                                               $_POST['password'] ?? '', $_POST['password2'] ?? '');
            } else {
                $erreurs = inscrireClient($v['nom'], $v['prenom'], $v['adresse'], $v['telephone'],
                                          $v['email'], $_POST['password'] ?? '', $_POST['password2'] ?? '');
            }

            if (!$erreurs) {
                $_SESSION['message'] = 'Compte créé. Vous pouvez maintenant vous connecter.';
                header('Location: index.php?page=login');
                exit;
            }
        }
        require __DIR__ . '/IHM/auth/register.php';
        break;
        
        case 'motdepasse':
        if (!estConnecte()) {
            header('Location: index.php?page=login');
            exit;
        }
        $erreurs = [];
        $succes  = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $erreurs = changerMotDePasse($_POST['ancien'] ?? '', $_POST['nouveau'] ?? '', $_POST['nouveau2'] ?? '');
            $succes  = !$erreurs;
        }
        require __DIR__ . '/IHM/auth/motdepasse.php';
        break;

       case 'saisie':
    exigerRole('client');
    require_once __DIR__ . '/traitement/Saisie.php';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        traiterSaisie();            // bouton « Valider ma saisie »
    }
    $d = preparerPageSaisie();
    require __DIR__ . '/IHM/client/saisie.php';
    break; 

        case 'factures':
        exigerRole('client');
        require_once __DIR__ . '/traitement/Saisie.php';   // pour periodeFr(), formaterMontant()...
        $d = preparerPageFactures();
        require __DIR__ . '/IHM/client/factures.php';
        break;
        case 'facture':
    exigerRole('client');

    $idFacture = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$idFacture || $idFacture <= 0) {
        http_response_code(404);
        require __DIR__ . '/IHM/404.php';
        break;
    }

    // La récupération et la vérification de la facture
    // seront ajoutées à l'étape suivante.

    break;
    case 'payer_facture':
        exigerRole('client');
        require_once __DIR__ . '/BD/Facture.php';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $idFacture = filter_input(INPUT_POST, 'id_facture', FILTER_VALIDATE_INT);
            $idClient = $_SESSION['user']['id'];
            
            if ($idFacture && $idFacture > 0) {
                payerFactureClient($idFacture, $idClient);
            }
        }
        header('Location: index.php?page=factures');
        exit;
            case 'voir_facture':
        exigerRole('client');
        require_once __DIR__ . '/traitement/Saisie.php';
        require_once __DIR__ . '/traitement/Facture.php';

        $idFacture = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $facture = $idFacture
            ? preparerFactureImprimable($idFacture, (int) $_SESSION['user']['id'])
            : null;

        if (!$facture) {               // id invalide, inexistant ou facture d'un autre client
            http_response_code(404);
            require __DIR__ . '/IHM/404.php';
            break;
        }
        require __DIR__ . '/IHM/client/voir_facture.php';
        break;
         // TEST : inscription fournisseur, à supprimer à la fin
    case 'register_fournisseur':
        if (!INSCRIPTION_FOURNISSEUR_TEST) {
            http_response_code(404);
            require __DIR__ . '/IHM/404.php';
            break;
        }
        $erreurs = [];
        $v = ['nom' => '', 'prenom' => '', 'email' => ''];
        if ($post) {
            foreach ($v as $cle => $_) {
                $v[$cle] = trim($_POST[$cle] ?? '');
            }
            $erreurs = inscrireFournisseur($v['nom'], $v['prenom'], $v['email'],
                                           $_POST['password'] ?? '', $_POST['password2'] ?? '');
            if (!$erreurs) {
                $_SESSION['message'] = 'Fournisseur créé. Vous pouvez vous connecter.';
                header('Location: index.php?page=login');
                exit;
            }
        }
        require __DIR__ . '/IHM/auth/register_fournisseur.php';
        break;
        case 'clientcompteur':
        exigerRole('fournisseur');
        // On inclut le fichier de traitement
        require_once __DIR__ . '/traitement/clientcompteur.php';
        break;   
        case 'historique_paiements':
        exigerRole('fournisseur');
        require __DIR__ . '/IHM/fournisseur/historique_paiements.php';
        break;
        case 'logout':
        // On détruit la session à chaque appel de la déconnexion
        seDeconnecter();
        // Redirection vers la page d'inscription / création de fournisseur
        header('Location: index.php?page=register_fournisseur');
        exit;
    default:
        http_response_code(404);
        require __DIR__ . '/IHM/404.php';
}