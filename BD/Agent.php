<?php
require_once __DIR__ . '/connexion.php';

function trouverAgentParEmail($email)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx,
        "SELECT ID_Agent AS id, Nom, Prenom, Email, Password AS mdp
         FROM agent WHERE Email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res);
}

function creerAgent($nom, $prenom, $email, $hash)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx,
        "INSERT INTO agent (Nom, Prenom, Email, Password)
         VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $nom, $prenom, $email, $hash);
    if (mysqli_stmt_execute($stmt)) {
        return mysqli_insert_id($cnx);
    }
    return false;
}

function modifierMotDePasseAgent($id, $hash)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx, "UPDATE agent SET Password = ? WHERE ID_Agent = ?");
    mysqli_stmt_bind_param($stmt, "si", $hash, $id);
    return mysqli_stmt_execute($stmt);
}
?>