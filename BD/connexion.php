<?php
function Connexion($serv = "localhost", $user = "root", $pass = "", $bd = "gestion_eau_electricite")
{
    $cnx = mysqli_connect($serv, $user, $pass, $bd);
    mysqli_set_charset($cnx, "utf8mb4");
    return $cnx;
}