<?php
require_once __DIR__ . '/Connexion.php';

function trouverClientParEmail($email)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx,
        "SELECT ID_Client AS id, Nom, Prenom, Email, Password AS mdp
         FROM client WHERE Email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res);   // une ligne (tableau) ou null si introuvable
}

function creerClient($nom, $prenom, $adresse, $telephone, $email, $hash)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx,
        "INSERT INTO client (Nom, Prenom, Adresse, Telephone, Email, Password)
         VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssssss", $nom, $prenom, $adresse, $telephone, $email, $hash);
    return mysqli_stmt_execute($stmt);
}

function modifierMotDePasseClient($id, $hash)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx, "UPDATE client SET Password = ? WHERE ID_Client = ?");
    mysqli_stmt_bind_param($stmt, "si", $hash, $id);
    return mysqli_stmt_execute($stmt);
}