<?php
function Connexion($serv = "localhost", $user = "root", $pass = "", $bd = "gestion_electricite_eau")
{
    $cnx = mysqli_connect($serv, $user, $pass, $bd);
    mysqli_set_charset($cnx, "utf8mb4");
    return $cnx;
}