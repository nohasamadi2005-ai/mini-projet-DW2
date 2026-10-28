<?php
const INSCRIPTION_FOURNISSEUR_TEST = true;   
require_once __DIR__ . '/traitement/auth.php';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

$page = $_GET['page'] ?? 'login';
if ($page === 'logout') {
    seDeconnecter();
    header('Location: index.php?page=login');
    exit;
}

switch ($page) {

    case 'login':
        if (estConnecte()) {       
            header('Location: index.php?page=' . pageAccueil($_SESSION['user']['role']));
            exit;
        }
        $erreur     = '';
        $emailSaisi = '';
        $roleSaisi  = 'client';
        $message    = $_SESSION['message'] ?? '';
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
    case 'gestion_client': 
        exigerRole('fournisseur');
        require __DIR__ . '/IHM/fournisseur/gestion_client.php'; 
        break;
    
    // --- NOUVEAU : Route pour l'espace Agent ---
    case 'agent':
    case 'saisie_agent':
        exigerRole('agent');
        require __DIR__ . '/IHM/agent/saisie_agent.php'; 
        break;
    case 'agent_depot':
        exigerRole('agent');
        require_once __DIR__ . '/traitement/agent.php';
        $resultatTraitement = traiterDepotReleve();
        require __DIR__ . '/IHM/agent/saisie_agent.php';
        break;

    case 'agent_historique':
        exigerRole('agent');
        require_once __DIR__ . '/BD/AgentImport.php';
        require __DIR__ . '/IHM/agent/historique.php';
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
            
            $inputRole = $_POST['role'] ?? 'client';
            if ($inputRole === 'fournisseur' && INSCRIPTION_FOURNISSEUR_TEST) {
                $roleSaisi = 'fournisseur';
            } elseif ($inputRole === 'agent') {
                $roleSaisi = 'agent';
            } else {
                $roleSaisi = 'client';
            }

            if ($roleSaisi === 'fournisseur') {
                $erreurs = inscrireFournisseur($v['nom'], $v['prenom'], $v['email'],
                                               $_POST['password'] ?? '', $_POST['password2'] ?? '');
            } elseif ($roleSaisi === 'agent') {
                $erreurs = inscrireAgent($v['nom'], $v['prenom'], $v['email'],
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
            traiterSaisie();            
        }
        $d = preparerPageSaisie();
        require __DIR__ . '/IHM/client/saisie.php';
        break; 

    case 'factures':
        exigerRole('client');
        require_once __DIR__ . '/traitement/Saisie.php';   
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
        break;

    case 'payer_facture':
        exigerRole('client');
        require_once __DIR__ . '/BD/Facture.php';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $idFacture = filter_input(INPUT_POST, 'id_facture', FILTER_VALIDATE_INT);
            $idClient  = $_SESSION['user']['id'];
            
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
        $facture   = $idFacture
            ? preparerFactureImprimable($idFacture, (int) $_SESSION['user']['id'])
            : null;

        if (!$facture) {              
            http_response_code(404);
            require __DIR__ . '/IHM/404.php';
            break;
        }
        require __DIR__ . '/IHM/client/voir_facture.php';
        break;
      
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
        require_once __DIR__ . '/traitement/clientcompteur.php';
        break;   

    case 'historique_paiements':
        exigerRole('fournisseur');
        require __DIR__ . '/IHM/fournisseur/historique_paiements.php';
        break;

    // --- NOUVEAU : Traitement des Anomalies (Fournisseur) ---
    case 'traitement_anomalies':
        exigerRole('fournisseur');
        require __DIR__ . '/IHM/fournisseur/traitement_anomalies.php';
        break;
        // --- NOUVEAU : Action de traitement de l'anomalie (POST) ---
    case 'traiter_anomalie':
        exigerRole('fournisseur');
        require __DIR__ . '/traitement/traiter_anomalie.php';
        break;

    // --- NOUVEAU : Espace Notifications (Client) ---
    case 'notifications':
        exigerRole('client');
        require __DIR__ . '/IHM/client/notifications.php';
        break;

    case 'telecharger_modele':
        exigerRole('agent');
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="modele_releves.txt"');
        echo "1, 0.0, 0.0\n";
        exit;

        case 'verification_annuelle':
        exigerRole('fournisseur');
        require_once __DIR__ . '/traitement/VerificationAnnuelle.php';
        $d = preparerPageVerificationAnnuelle();
        require __DIR__ . '/IHM/fournisseur/verification_annuelle.php';
        break;
       // --- Réclamations (Client) ---
    case 'reclamations':
        exigerRole('client');
        require_once __DIR__ . '/traitement/Reclamation.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            traiterReclamation();
        }
        $d = preparerPageReclamations();
        require __DIR__ . '/IHM/client/reclamations.php';
        break;
     // --- Réclamations (Fournisseur) ---
    case 'reclamations_fournisseur':
        exigerRole('fournisseur');
        require_once __DIR__ . '/traitement/reclamationsFournisseur.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            traiterReclamationFournisseur();
        }
        $d = preparerPageReclamationsFournisseur();
        require __DIR__ . '/IHM/fournisseur/fournisseurReclamtion.php';
        break;

    default:
        http_response_code(404);
        require __DIR__ . '/IHM/404.php';
}
