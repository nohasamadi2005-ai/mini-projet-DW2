<?php
function Connexion($serv = "localhost", $user = "root", $pass = "", $bd = "gestion eau et électricité")
{
    $cnx = mysqli_connect($serv, $user, $pass, $bd);
    mysqli_set_charset($cnx, "utf8mb4");
    return $cnx;
}