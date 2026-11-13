<?php
require_once __DIR__ . '/Consommation.php';

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

function creerNotificationFacture($idClient, $idAdmin, $idFacture, $message)
{
    return consoExecuter(
        "INSERT INTO notification (ID_Client, ID_Admin, ID_Facture, Message, Date_Envoi, Lu)
         VALUES (?, ?, ?, ?, NOW(), 0)",
        'iiis', [(int) $idClient, $idAdmin, $idFacture ?: null, $message]
    );
}