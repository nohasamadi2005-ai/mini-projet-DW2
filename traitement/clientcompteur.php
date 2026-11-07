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
        
        // Les compteurs se gèrent par contrat : on crée le client, puis on ouvre l'écran Contrats
        if (!empty($nom) && !empty($prenom) && !empty($email) && !empty($password)) {
            try {
                $idClient = ajouterClientAvecCompteur($nom, $prenom, $adresse, $telephone, $email, $password, '', '');
            } catch (mysqli_sql_exception $e) {
                $idClient = false;
            }
            if ($idClient) {
                $_SESSION['contrat_msg'] = ['type' => 'succes',
                    'texte' => "Client #$idClient créé. Créez maintenant son contrat (compteur + zone)."];
                header('Location: index.php?page=contrats&client=' . (int) $idClient . '&nouveau=1');
                exit;
            }
            $_SESSION['client_msg'] = ['type' => 'erreur', 'texte' => "Client non créé : cet email est peut-être déjà utilisé."];
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

         
            // Les compteurs ne se modifient plus ici : écran Contrats (index.php?page=contrats)
            $_SESSION['client_msg'] = ['type' => 'succes', 'texte' => 'Client mis à jour.'];
        }

        
        header('Location: index.php?page=gestion_client');
        exit;
    }
}