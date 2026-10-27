<?php
require_once __DIR__ . '/Consommation.php';

// Table notification : ID_Notification, ID_Client, ID_Admin, ID_Anomalie, ID_Reclamation, ID_Facture, Message, Date_Envoi

function creerNotification($idClient, $idAdmin, $message)
{
    return consoExecuter(
        "INSERT INTO notification (ID_Client, ID_Admin, Message, Date_Envoi,lu) VALUES (?, ?, ?, NOW(),0)",
        'iis', [$idClient, $idAdmin, $message]
    );
}

function getNotificationsClient($idClient)
{
    return consoSelect(
        "SELECT Message, Date_Envoi FROM notification WHERE ID_Client = ?
         ORDER BY ID_Notification DESC",
        'i', [$idClient]
    );
}