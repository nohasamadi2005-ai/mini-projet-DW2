<?php
// Règles métier — Personne 2 (quartiers & tarifs).
// Les tranches, seuils et redevances viennent de la base (tables tarif / type_activite).
require_once __DIR__ . '/../BD/Tarif.php';

date_default_timezone_set('Africa/Casablanca');


if (!defined('FENETRE_TOUJOURS_OUVERTE')) {
    define('FENETRE_TOUJOURS_OUVERTE', false);
}


if (!defined('DATE_SIMULEE')) {
    define('DATE_SIMULEE', '2026-12-19');

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


// ---------------------------------------------------------------------
//  Tarifs : lecture (base de données, avec repli si la base est indisponible)
// ---------------------------------------------------------------------

if (!defined('TVA_TAUX')) {
    define('TVA_TAUX', 0.18);
}
if (!defined('TYPE_ACTIVITE_DEFAUT')) {
    define('TYPE_ACTIVITE_DEFAUT', 1);   // Résidentiel : utilisé quand aucun type d'activité n'est donné
}

if (!function_exists('estEau')) {
    function estEau($typeCompteur)
    {
        return strtolower((string) $typeCompteur) === 'eau';
    }
}

if (!function_exists('tarifsDeRepli')) {
    // Résidentiel (tarifs du sujet du prof) : utilisé seulement si la base est inaccessible ou vide
    function tarifsDeRepli($typeCompteur)
    {
        if (estEau($typeCompteur)) {
            return [
                'tranches'  => [
                    ['borne' => 6,    'prix' => 2.37],
                    ['borne' => 20,   'prix' => 7.39],
                    ['borne' => 35,   'prix' => 10.98],
                    ['borne' => null, 'prix' => 11.03],
                ],
                'redevance' => 0.0,
                'seuil'     => 80.0,
            ];
        }
        return [
            'tranches'  => [
                ['borne' => 100,  'prix' => 0.82],
                ['borne' => 150,  'prix' => 0.92],
                ['borne' => null, 'prix' => 1.10],
            ],
            'redevance' => 0.0,
            'seuil'     => 500.0,
        ];
    }
}

if (!function_exists('chargerTarifs')) {
    /**
     * Tarifs d'un type d'activité pour un type de compteur.
     * $typeActivite : null (= Résidentiel), un ID, ou un libellé ('Commercial'...).
     * Renvoie ['tranches' => [[borne, prix], ...], 'redevance' => x, 'seuil' => y].
     * $vider = true : vide le cache (à appeler après une modification de tarif).
     */
    function chargerTarifs($typeCompteur, $typeActivite = null, $vider = false)
    {
        static $cache = [];
        if ($vider) {
            $cache = [];
            return null;
        }
        $eau = estEau($typeCompteur);
        $cle = ($eau ? 'E' : 'L') . '|' . ($typeActivite === null ? '' : $typeActivite);
        if (isset($cache[$cle])) {
            return $cache[$cle];
        }

        $res = tarifsDeRepli($typeCompteur);
        try {
            if ($typeActivite === null || $typeActivite === '') {
                $type = getTypeActiviteDetail(TYPE_ACTIVITE_DEFAUT);
            } elseif (is_numeric($typeActivite)) {
                $type = getTypeActiviteDetail((int) $typeActivite);
            } else {
                $type = getTypeActiviteParLibelle($typeActivite);
            }

            if ($type) {
                $lignes = getTranches((int) $type['ID_Type_Activite'], $eau ? 'Eau' : 'Electricite');
                if ($lignes) {
                    $res['tranches'] = [];
                    foreach ($lignes as $l) {
                        $res['tranches'][] = [
                            'borne'    => $l['Borne_Haute'] === null ? null : (float) $l['Borne_Haute'],
                            'prix'     => (float) $l['Prix_Unitaire'],
                        ];
                    }
                }
                $res['redevance'] = (float) $type[$eau ? 'Redevance_Eau' : 'Redevance_Elec'];
                $res['seuil']     = (float) $type[$eau ? 'Seuil_Eau'    : 'Seuil_Elec'];
            }
        } catch (Throwable $e) {
            error_log('chargerTarifs : repli sur les tarifs par défaut (' . $e->getMessage() . ')');
        }
        return $cache[$cle] = $res;
    }
}

// ---------------------------------------------------------------------
//  Seuils d'alerte
// ---------------------------------------------------------------------

if (!function_exists('seuilMax')) {
    // $typeActivite facultatif : null / ID / libellé (voir chargerTarifs)
    function seuilMax($typeCompteur, $typeActivite = null)
    {
        return chargerTarifs($typeCompteur, $typeActivite)['seuil'];
    }
}

if (!function_exists('detecterAnomalie')) {

    function detecterAnomalie($delta, $typeCompteur, $typeActivite = null)
    {
        if ($delta < 0) {
            return 'Index negatif';
        }
        if ($delta > seuilMax($typeCompteur, $typeActivite)) {
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

// ---------------------------------------------------------------------
//  Calcul des montants
// ---------------------------------------------------------------------

if (!function_exists('calculerDetail')) {
    /**
     * Calcul pur (sans base de données), testable seul. FACTURATION NORMALE :
     * on cherche la tranche où tombe la consommation (]borneBasse ; borne], une conso égale
     * à la borne reste dans cette tranche) et SON PRIX s'applique à TOUTE la consommation.
     * La redevance fixe mensuelle s'ajoute au HT ; la TVA porte sur le total.
     */
    function calculerDetail($consommation, array $tranches, $redevance = 0.0)
    {
        $conso  = max(0.0, (float) $consommation);
        $lignes = [];

        if ($conso > 0 && $tranches) {
            // Tranche atteinte (par défaut la dernière, normalement la tranche illimitée)
            $k = count($tranches) - 1;
            foreach ($tranches as $i => $t) {
                if ($t['borne'] === null || $conso <= $t['borne']) {
                    $k = $i;
                    break;
                }
            }
            $lignes[] = [
                'de'       => $k === 0 ? 0.0 : (float) $tranches[$k - 1]['borne'],
                'a'        => $tranches[$k]['borne'],
                'quantite' => $conso,
                'prix'     => $tranches[$k]['prix'],
                'montant'  => round($conso * $tranches[$k]['prix'], 2),
            ];
        }

        $consoHt = round(array_sum(array_column($lignes, 'montant')), 2);
        $red     = round((float) $redevance, 2);
        $ht      = round($consoHt + $red, 2);
        $tva     = round($ht * TVA_TAUX, 2);
        $ttc     = round($ht + $tva, 2);

        return [
            'lignes'      => $lignes,
            'consommation'=> $conso,
            'ht_conso'    => $consoHt,
            'redevance'   => $red,
            'ht'          => $ht,
            'tva'         => $tva,
            'ttc'         => $ttc,
        ];
    }
}

if (!function_exists('detailMontants')) {
    /**
     * Détail pour afficher une facture : la ligne de la tranche appliquée + redevance + HT / TVA / TTC.
     * $typeActivite : null (= Résidentiel), un ID ou un libellé.
     */
    function detailMontants($consommation, $typeCompteur, $typeActivite = null)
    {
        $t = chargerTarifs($typeCompteur, $typeActivite);
        return calculerDetail($consommation, $t['tranches'], $t['redevance']);
    }
}

if (!function_exists('calculerMontants')) {
    // Même signature qu'avant (+ $typeActivite facultatif) : renvoie [HT, TVA, TTC]
    function calculerMontants($consommation, $typeCompteur, $typeActivite = null)
    {
        $d = detailMontants($consommation, $typeCompteur, $typeActivite);
        return [$d['ht'], $d['tva'], $d['ttc']];
    }
}