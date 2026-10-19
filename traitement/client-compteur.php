<?php
// Traitement/ClientTraitement.php
require_once __DIR__ . '/auth.php';
exigerRole('fournisseur');
require_once __DIR__ . '/../BD/Client.php';

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

        header('Location: ../index.php?page=gestion_client');
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
            // 1. Modifier les infos du client
            modifierClient($idClient, $nom, $prenom, $adresse, $telephone, $email);

            // 2. Mettre à jour ou insérer le compteur d'eau
            mettreAJourCompteurClient($idClient, 'Eau', $compteurEau);

            // 3. Mettre à jour ou insérer le compteur d'électricité
            mettreAJourCompteurClient($idClient, 'Electricite', $compteurElec);
        }

        header('Location: ../index.php?page=gestion_client');
        exit;
    }
}