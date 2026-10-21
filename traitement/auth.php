<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../BD/Client.php';
require_once __DIR__ . '/../BD/Fournisseur.php';

function seConnecter($role, $email, $motDePasse) {
    $email = trim($email);

    if ($role === 'client') {
        $user = trouverClientParEmail($email);
    } elseif ($role === 'fournisseur') {
        $user = trouverFournisseurParEmail($email);
    } else {
        return false;
    }

    if ($user && password_verify($motDePasse, $user['mdp'])) {
        session_regenerate_id(true);

       
        $idUser = $user['id'] ?? $user['ID_Client'] ?? $user['ID_Admin'] ?? $user['ID_Fournisseur'] ?? null;

        $_SESSION['user'] = [
            'id'     => $idUser,
            'role'   => $role,
            'nom'    => $user['Nom'],
            'prenom' => $user['Prenom'],
            'email'  => $user['Email'],
        ];
        return true;
    }
    return false;
}

function estConnecte()
{
    return isset($_SESSION['user']);
}

function exigerRole($role)
{
    if (!estConnecte()) {
        header('Location: index.php?page=login');
        exit;
    }
    if ($_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        echo '<h1>403 - Accès refusé</h1>'
           . '<p>Vous n\'avez pas le droit d\'accéder à cette page.</p>'
           . '<a href="index.php?page=' . pageAccueil($_SESSION['user']['role']) . '">Retour à mon espace</a>';
        exit;
    }
}

function seDeconnecter()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function erreurMotDePasse($mdp)
{
    if (strlen($mdp) < 8 || !preg_match('/[A-Za-z]/', $mdp) || !preg_match('/\d/', $mdp)) {
        return 'Mot de passe : 8 caractères minimum, avec au moins une lettre et un chiffre.';
    }
    return null;
}

function inscrireClient($nom, $prenom, $adresse, $telephone, $email, $mdp, $mdp2)
{
    $erreurs = [];

    if ($nom === '' || $prenom === '') {
        $erreurs[] = 'Le nom et le prénom sont obligatoires.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = 'Adresse e-mail invalide.';
    }
    if ($e = erreurMotDePasse($mdp)) {
        $erreurs[] = $e;
    }
    if ($mdp !== $mdp2) {
        $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
    }
    if (!$erreurs && trouverClientParEmail($email)) {
        $erreurs[] = 'Cette adresse e-mail est déjà utilisée.';
    }

    if (!$erreurs) {
     
        creerClient($nom, $prenom, $adresse, $telephone, $email, password_hash($mdp, PASSWORD_DEFAULT));

     
        $user = trouverClientParEmail($email);
        if ($user) {
            session_regenerate_id(true);
            $idUser = $user['id'] ?? $user['ID_Client'] ?? null;

            $_SESSION['user'] = [
                'id'     => $idUser,
                'role'   => 'client', 
                'nom'    => $user['Nom'],
                'prenom' => $user['Prenom'],
                'email'  => $user['Email'],
            ];
        }
    }
    return $erreurs; 
}

function inscrireFournisseur($nom, $prenom, $email, $mdp, $mdp2)
{
    $erreurs = [];
    if ($nom === '' || $prenom === '') {
        $erreurs[] = 'Le nom et le prénom sont obligatoires.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = 'Adresse e-mail invalide.';
    }
    if ($e = erreurMotDePasse($mdp)) {
        $erreurs[] = $e;
    }
    if ($mdp !== $mdp2) {
        $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
    }
    if (!$erreurs && trouverFournisseurParEmail($email)) {
        $erreurs[] = 'Cette adresse e-mail est déjà utilisée.';
    }
    if (!$erreurs) {
        creerFournisseur($nom, $prenom, $email, password_hash($mdp, PASSWORD_DEFAULT));

      
        $user = trouverFournisseurParEmail($email);
        if ($user) {
            session_regenerate_id(true);
            $idUser = $user['id'] ?? $user['ID_Admin'] ?? $user['ID_Fournisseur'] ?? null;

            $_SESSION['user'] = [
                'id'     => $idUser,
                'role'   => 'fournisseur',
                'nom'    => $user['Nom'],
                'prenom' => $user['Prenom'],
                'email'  => $user['Email'],
            ];
        }
    }
    return $erreurs;
}

function changerMotDePasse($ancien, $nouveau, $nouveau2)
{
    $u       = $_SESSION['user'];
    $erreurs = [];

    $compte = ($u['role'] === 'client')
        ? trouverClientParEmail($u['email'])
        : trouverFournisseurParEmail($u['email']);

    if (!$compte || !password_verify($ancien, $compte['mdp'])) {
        $erreurs[] = 'Le mot de passe actuel est incorrect.';
    }
    if ($e = erreurMotDePasse($nouveau)) {
        $erreurs[] = $e;
    }
    if ($nouveau !== $nouveau2) {
        $erreurs[] = 'Les deux nouveaux mots de passe ne correspondent pas.';
    }
    if ($nouveau === $ancien) {
        $erreurs[] = 'Le nouveau mot de passe doit être différent de l\'ancien.';
    }

    if (!$erreurs) {
        $hash = password_hash($nouveau, PASSWORD_DEFAULT);
        if ($u['role'] === 'client') {
            modifierMotDePasseClient($u['id'], $hash);
        } else {
            modifierMotDePasseFournisseur($u['id'], $hash);
        }
        session_regenerate_id(true);
    }
    return $erreurs;
}

function pageAccueil($role)
{
    return $role === 'fournisseur' ? 'gestion_client' : 'saisie';
}
?>