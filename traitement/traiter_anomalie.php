<?php
require_once __DIR__ . '/auth.php';
exigerRole('fournisseur');

// INCLUSION OBLIGATOIRE DU FICHIER MODEL CONTENANT LES FONCTIONS
require_once __DIR__ . '/../BD/Anomalie.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $idConsommation = filter_input(INPUT_POST, 'id_consommation', FILTER_VALIDATE_INT);
    $idClient = filter_input(INPUT_POST, 'id_client', FILTER_VALIDATE_INT);

    if ($idConsommation && $idClient) {
        if ($action === 'traiter_surconsommation') {
            traiterSurconsommation($idConsommation, $idClient);
            $_SESSION['message_succes'] = "L'intervention a été programmée et la surconsommation validée.";
        } elseif ($action === 'demander_resaisie') {
            $motif = trim($_POST['motif'] ?? 'Index non conforme');
            demanderResaisie($idConsommation, $idClient, $motif);
            $_SESSION['message_alerte'] = "La demande de resaisie a été transmise au client.";
        }
    }
}

// Redirection vers le tableau de bord
header('Location: index.php?page=traitement_anomalies');
exit;