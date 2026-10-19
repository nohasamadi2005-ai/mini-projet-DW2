<?php

date_default_timezone_set('Africa/Casablanca');

// Mettre true UNIQUEMENT pour tester le formulaire avant le 18. Remettre false pour la démo.
if (!defined('FENETRE_TOUJOURS_OUVERTE')) {
    define('FENETRE_TOUJOURS_OUVERTE', false);
}

// MODE DÉMO : mettre une date (ex. '2026-11-18') 
// null = vraie date du serveur (à remettre pour l'usage normal).
if (!defined('DATE_SIMULEE')) {
    define('DATE_SIMULEE', '2026-11-18');
    /*define('DATE_SIMULEE', null); pour la date de serveur*/
}

if (!function_exists('maintenant')) {
    function maintenant()
    {
        return DATE_SIMULEE ? strtotime(DATE_SIMULEE . ' 12:00:00') : time();
    }
}

if (!function_exists('fenetreOuverte')) {
    // Ouverte du 18 au dernier jour du mois (le 1er, date('j') vaut 1 : elle se referme seule)
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
    // PROVISOIRE : seuils fixes par mois. À rendre modifiables par le fournisseur plus tard.
    function seuilMax($typeCompteur)
    {
        return $typeCompteur === 'Eau' ? 80 : 500;   // m³ / kWh
    }
}

if (!function_exists('detecterAnomalie')) {
    // Retourne null si la saisie est valide, sinon le type d'anomalie.
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
    // "2026-06-24 10:12:00" -> "24 juin 2026"
    function dateFr($date)
    {
        $t = strtotime($date);
        return date('j', $t) . ' ' . mb_strtolower(nomMois(date('n', $t))) . ' ' . date('Y', $t);
    }
}