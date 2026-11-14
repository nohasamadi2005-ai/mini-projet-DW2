<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../BD/Client.php';
require_once __DIR__ . '/../BD/Fournisseur.php';
require_once __DIR__ . '/../BD/Agent.php';

function seConnecter($role, $email, $motDePasse) {
    $email = trim($email);

    if ($role === 'client') {
        $user = trouverClientParEmail($email);
    } elseif ($role === 'fournisseur') {
        $user = trouverFournisseurParEmail($email);
    } elseif ($role === 'agent') {
        $user = trouverAgentParEmail($email);
    } else {
        return false;
    }

    if ($user && password_verify($motDePasse, $user['mdp'])) {
        session_regenerate_id(true);

        $idUser = $user['id'] ?? $user['ID_Client'] ?? $user['ID_Admin'] ?? $user['ID_Fournisseur'] ?? $user['ID_Agent'] ?? null;

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

function formatCinValide($cin)
{
    return (bool) preg_match('/^[A-Z]{1,2}[0-9]{5,6}$/', $cin);
}

function formatIceValide($ice)
{
    return (bool) preg_match('/^[0-9]{15}$/', $ice);
}

function inscrireClient($nom, $prenom, $adresse, $telephone, $email, $mdp, $mdp2,
                        $cin = '', $typeClient = 'particulier', $raisonSociale = '', $ice = '')
{
    $erreurs = [];

    $cin           = strtoupper(preg_replace('/\s+/', '', $cin));
    $ice           = preg_replace('/\s+/', '', $ice);
    $raisonSociale = trim($raisonSociale);
    if ($typeClient !== 'entreprise') {
        $typeClient = 'particulier';
    }

    if ($nom === '' || $prenom === '') {
        $erreurs[] = 'Le nom et le prénom sont obligatoires.';
    }
    if ($cin === '') {
        $erreurs[] = 'Le CIN est obligatoire.';
    } elseif (!formatCinValide($cin)) {
        $erreurs[] = 'CIN invalide : 1 ou 2 lettres suivies de 5 ou 6 chiffres (ex. AB123456).';
    }
    if ($typeClient === 'entreprise') {
        if ($raisonSociale === '') {
            $erreurs[] = 'La raison sociale est obligatoire pour une entreprise.';
        }
        if ($ice === '') {
            $erreurs[] = 'L\'ICE est obligatoire pour une entreprise.';
        } elseif (!formatIceValide($ice)) {
            $erreurs[] = 'ICE invalide : il doit contenir exactement 15 chiffres.';
        }
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
    if (!$erreurs && trouverClientParCin($cin)) {
        $erreurs[] = 'Ce CIN est déjà enregistré.';
    }
    if (!$erreurs && $typeClient === 'entreprise' && trouverClientParIce($ice)) {
        $erreurs[] = 'Cet ICE est déjà enregistré.';
    }

    if (!$erreurs) {
        $rs  = $typeClient === 'entreprise' ? $raisonSociale : null;
        $iceBd = $typeClient === 'entreprise' ? $ice : null;

        if (!creerClient($nom, $prenom, $adresse, $telephone, $email,
                         password_hash($mdp, PASSWORD_DEFAULT), $cin, $typeClient, $rs, $iceBd)) {
            $erreurs[] = 'Impossible de créer le compte (CIN, ICE ou e-mail déjà utilisé).';
            return $erreurs;
        }

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

function inscrireAgent($nom, $prenom, $email, $mdp, $mdp2)
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
    if (!$erreurs && trouverAgentParEmail($email)) {
        $erreurs[] = 'Cette adresse e-mail est déjà utilisée.';
    }
    if (!$erreurs) {
        creerAgent($nom, $prenom, $email, password_hash($mdp, PASSWORD_DEFAULT));

        $user = trouverAgentParEmail($email);
        if ($user) {
            session_regenerate_id(true);
            $idUser = $user['id'] ?? $user['ID_Agent'] ?? null;

            $_SESSION['user'] = [
                'id'     => $idUser,
                'role'   => 'agent',
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

    if ($u['role'] === 'client') {
        $compte = trouverClientParEmail($u['email']);
    } elseif ($u['role'] === 'fournisseur') {
        $compte = trouverFournisseurParEmail($u['email']);
    } else {
        $compte = trouverAgentParEmail($u['email']);
    }

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
        } elseif ($u['role'] === 'fournisseur') {
            modifierMotDePasseFournisseur($u['id'], $hash);
        } else {
            modifierMotDePasseAgent($u['id'], $hash);
        }
        session_regenerate_id(true);
    }
    return $erreurs;
}

function pageAccueil($role)
{
    if ($role === 'fournisseur') return 'gestion_client';
    if ($role === 'agent') return 'saisie_agent';
    return 'saisie';
}

?>