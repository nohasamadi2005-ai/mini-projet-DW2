<?php
require_once __DIR__ . '/Connexion.php';

function getAnomaliesEnAttente() {
    $cnx = Connexion();
    
    $sql = "SELECT cm.ID_Consommation_Mensuelle,
                   cm.Index_Precedent,
                   cm.Index_Actuel,
                   cm.Consommation,
                   cm.Photo_Actuelle,
                   cm.Statut_Traitement,
                   cm.Date_saisie,
                   cl.ID_Client,
                   cl.Nom,
                   cl.Prenom,
                   cl.Email,
                   cp.ID_Compteur,
                   cp.Type_Compteur
            FROM consommation_mensuelle cm
            JOIN client cl ON cm.ID_Client = cl.ID_Client
            JOIN compteur cp ON cm.ID_Compteur = cp.ID_Compteur
            WHERE cm.Statut_Traitement = 'En attente' OR cm.Statut_Traitement = '' OR cm.Statut_Traitement IS NULL
            ORDER BY cm.ID_Consommation_Mensuelle DESC";

    $res = mysqli_query($cnx, $sql);
    $anomalies = [];

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $seuil = (strtolower($row['Type_Compteur']) === 'eau') ? 80 : 500;
            
            if ($row['Consommation'] < 0 || $row['Index_Actuel'] < $row['Index_Precedent']) {
                $row['Type_Anomalie'] = 'Index negatif';
            } elseif ($row['Consommation'] > $seuil) {
                $row['Type_Anomalie'] = 'Surconsommation';
            } else {
                $row['Type_Anomalie'] = 'Saisie suspecte';
            }

            $anomalies[] = $row;
        }
    }
    
    mysqli_close($cnx);
    return $anomalies;
}

function traiterSurconsommation($idConsommation, $idClient) {
    $cnx = Connexion();
    
    // 1. Validation du statut dans consommation_mensuelle
    $sql = "UPDATE consommation_mensuelle 
            SET Statut_Traitement = 'Verifie' 
            WHERE ID_Consommation_Mensuelle = " . intval($idConsommation);
    mysqli_query($cnx, $sql);

    // 2. Notification optionnelle
    $message = mysqli_real_escape_string($cnx, "Votre surconsommation a été enregistrée. Un technicien va procéder à la vérification d'éventuelles fuites.");
    $sqlNotif = "INSERT INTO notification (ID_Client, Message, Date_Notification, Lu) 
                 VALUES (" . intval($idClient) . ", '$message', NOW(), 0)";
    @mysqli_query($cnx, $sqlNotif);

    mysqli_close($cnx);
}

function demanderResaisie($idConsommation, $idClient, $motif) {
    $cnx = Connexion();
    
    // 1. Passage du statut en Rejete
    $sql = "UPDATE consommation_mensuelle 
            SET Statut_Traitement = 'Rejete' 
            WHERE ID_Consommation_Mensuelle = " . intval($idConsommation);
    mysqli_query($cnx, $sql);

    // 2. Notification du motif
    $message = mysqli_real_escape_string($cnx, "Votre index a été refusé pour le motif : " . $motif . ". Merci de procéder à une nouvelle resaisie.");
    $sqlNotif = "INSERT INTO notification (ID_Client, Message, Date_Notification, Lu) 
                 VALUES (" . intval($idClient) . ", '$message', NOW(), 0)";
    @mysqli_query($cnx, $sqlNotif);

    mysqli_close($cnx);
}