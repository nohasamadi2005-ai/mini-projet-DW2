<?php
require_once __DIR__ . '/Traitement/Auth.php';

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
        exigerRole('fournisseur');
        require __DIR__ . '/IHM/fournisseur/index.php';
        break;
    
    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            seDeconnecter();
        }
        header('Location: index.php?page=login');
        exit;
    
        case 'register':
        $erreurs = [];
        $v = ['nom' => '', 'prenom' => '', 'adresse' => '', 'telephone' => '', 'email' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($v as $cle => $_) {
                $v[$cle] = trim($_POST[$cle] ?? '');
            }
            $erreurs = inscrireClient($v['nom'], $v['prenom'], $v['adresse'], $v['telephone'],
                                      $v['email'], $_POST['password'] ?? '', $_POST['password2'] ?? '');
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
        require __DIR__ . '/IHM/client/saisie.php';
        break;
        
    default:
        http_response_code(404);
        require __DIR__ . '/IHM/404.php';
}