<?php
require_once __DIR__ . '/connexion.php';

function trouverFournisseurParEmail($email)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx,
        "SELECT ID_Admin AS id, Nom, Prenom, Email, Mot_De_Passe AS mdp
         FROM admin WHERE Email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res);   // une ligne (tableau) ou null si introuvable
}

function modifierMotDePasseFournisseur($id, $hash)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx, "UPDATE admin SET Mot_De_Passe = ? WHERE ID_Admin = ?");
    mysqli_stmt_bind_param($stmt, "si", $hash, $id);
    return mysqli_stmt_execute($stmt);
}

function creerFournisseur($nom, $prenom, $email, $hash)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx,
        "INSERT INTO admin (Nom, Prenom, Email, Mot_De_Passe) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $nom, $prenom, $email, $hash);
    return mysqli_stmt_execute($stmt);
}