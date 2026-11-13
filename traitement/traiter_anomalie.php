<?php
require_once __DIR__ . '/auth.php';
exigerRole('fournisseur');

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
            if (demanderResaisie($idConsommation, $idClient, $motif)) {
                $_SESSION['message_alerte'] = "La demande de resaisie a été transmise au client.";
            } else {
                $_SESSION['message_erreur'] = "Le client a déjà utilisé sa resaisie : corrigez l'index vous-même.";
            }
        } elseif ($action === 'corriger_saisie') {
            $indexCorrige = filter_input(INPUT_POST, 'index_corrige', FILTER_VALIDATE_FLOAT);
            if ($indexCorrige !== false && $indexCorrige !== null) {
                $r = corrigerEtFacturerParFournisseur($idConsommation, $idClient, $indexCorrige);
                if ($r === true) {
                    $_SESSION['message_succes'] = "L'index a été corrigé par vos soins, la facture a été générée et transmise au client.";
                } else {
                    $_SESSION['message_erreur'] = $r;
                }
            } else {
                $_SESSION['message_erreur'] = "Veuillez saisir un index valide.";
            }
        }
    }
}

header('Location: index.php?page=traitement_anomalies');
exit;