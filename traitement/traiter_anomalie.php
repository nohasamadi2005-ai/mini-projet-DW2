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
        } elseif ($action === 'corriger_saisie') {
            // NOUVEAU : Traitement de la correction d'index effectuée par le fournisseur
            $indexCorrige = filter_input(INPUT_POST, 'index_corrige', FILTER_VALIDATE_FLOAT);
            if ($indexCorrige !== false && $indexCorrige !== null) {
                corrigerEtFacturerParFournisseur($idConsommation, $idClient, $indexCorrige);
                $_SESSION['message_succes'] = "L'index a été corrigé par vos soins, la facture a été générée et transmise au client.";
            } else {
                $_SESSION['message_erreur'] = "Veuillez saisir un index valide.";
            }
        }
    }
}

// Redirection vers le tableau de bord
header('Location: index.php?page=traitement_anomalies');
exit;