<?php
require_once __DIR__ . '/auth.php';
exigerRole('fournisseur');
require_once __DIR__ . '/../BD/Client.php';
require_once __DIR__ . '/../BD/CompteurAffectation.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'ajouter') {
        $nom            = trim($_POST['nom'] ?? '');
        $prenom         = trim($_POST['prenom'] ?? '');
        $adresse        = trim($_POST['adresse'] ?? '');
        $telephone      = trim($_POST['telephone'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $password       = $_POST['password'] ?? '';
        
        $numeroCompteur = trim($_POST['numero_compteur'] ?? '');
        $typeCompteur   = $_POST['type_compteur'] ?? 'Electricite';

        if (!empty($nom) && !empty($prenom) && !empty($email) && !empty($password)) {
            ajouterClientAvecCompteur($nom, $prenom, $adresse, $telephone, $email, $password, $numeroCompteur, $typeCompteur);
        }


        header('Location: index.php?page=gestion_client');
        exit;
    }
    
    elseif ($action === 'modifier') {
        $idClient     = $_POST['id_client'] ?? null;
        $nom          = trim($_POST['nom'] ?? '');
        $prenom       = trim($_POST['prenom'] ?? '');
        $adresse      = trim($_POST['adresse'] ?? '');
        $telephone    = trim($_POST['telephone'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        
        $compteurEau  = trim($_POST['compteur_eau'] ?? '');
        $compteurElec = trim($_POST['compteur_elec'] ?? '');

        if ($idClient) {
  
            modifierClient($idClient, $nom, $prenom, $adresse, $telephone, $email);

         
            // Nouvelle gestion des compteurs (affectation avec conservation de l'index)
            $erreurs = [];
            $e = changerCompteurClient($idClient, 'Eau', $compteurEau, $_POST['index_eau'] ?? 0);
            if ($e) { $erreurs[] = "Compteur eau : $e"; }
            $e = changerCompteurClient($idClient, 'Electricite', $compteurElec, $_POST['index_elec'] ?? 0);
            if ($e) { $erreurs[] = "Compteur électricité : $e"; }

            $_SESSION['client_msg'] = $erreurs
                ? ['type' => 'erreur', 'texte' => implode(' ', $erreurs)]
                : ['type' => 'succes', 'texte' => 'Client et compteurs mis à jour.'];
        }

        
        header('Location: index.php?page=gestion_client');
        exit;
    }
}