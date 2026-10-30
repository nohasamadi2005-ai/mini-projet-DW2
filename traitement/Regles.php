<?php

date_default_timezone_set('Africa/Casablanca');


if (!defined('FENETRE_TOUJOURS_OUVERTE')) {
    define('FENETRE_TOUJOURS_OUVERTE', false);
}


if (!defined('DATE_SIMULEE')) {
    define('DATE_SIMULEE', '2028-06-27');

}

if (!function_exists('maintenant')) {
    function maintenant()
    {
        return DATE_SIMULEE ? strtotime(DATE_SIMULEE . ' 12:00:00') : time();
    }
}
 
if (!function_exists('fenetreOuverte')) {
    function fenetreOuverte()
    {
        return FENETRE_TOUJOURS_OUVERTE || (int) date('j', maintenant()) >= 18;
    }
}

if (!function_exists('joursRestants')) {
    function joursRestants()
    {
        return (int) date('t', maintenant()) - (int) date('j', maintenant()) + 1;
    }
}

if (!function_exists('seuilMax')) {

    function seuilMax($typeCompteur)
    {
        return $typeCompteur === 'Eau' ? 80 : 500;   
    }
}

if (!function_exists('detecterAnomalie')) {

    function detecterAnomalie($delta, $typeCompteur)
    {
        if ($delta < 0) {
            return 'Index negatif';
        }
        if ($delta > seuilMax($typeCompteur)) {
            return 'Surconsommation';
        }
        return null;
    }
}

if (!function_exists('nomMois')) {
    function nomMois($n)
    {
        $m = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet',
              'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        return $m[(int) $n] ?? '';
    }
}

if (!function_exists('dateFr')) {

    function dateFr($date)
    {
        $t = strtotime($date);
        return date('j', $t) . ' ' . mb_strtolower(nomMois(date('n', $t))) . ' ' . date('Y', $t);
    }
}
if (!function_exists('calculerMontants')) {
   
    function calculerMontants($consommation, $typeCompteur)
    {
        $tranches = ($typeCompteur === 'Eau')
            ? [[6, 2.37], [20, 7.39], [35, 10.98], [INF, 11.03]]   
            : [[100, 0.82], [150, 0.92], [INF, 1.10]];            

        $ht = 0.0;
        $borneBasse = 0;
        foreach ($tranches as [$borneHaute, $prix]) {
            if ($consommation <= $borneBasse) {
                break;
            }
            $quantite = min($consommation, $borneHaute) - $borneBasse;
            $ht += $quantite * $prix;
            $borneBasse = $borneHaute;
        }

        $ht  = round($ht, 2);
        $tva = round($ht * 0.18, 2);
        $ttc = round($ht + $tva, 2);
        return [$ht, $tva, $ttc];
    }
}