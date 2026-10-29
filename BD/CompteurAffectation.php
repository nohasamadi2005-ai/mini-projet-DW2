<?php
// Affectation / libération des compteurs (fichier ajouté, rien n'est supprimé de BD/Client.php).
// Principe : un compteur reste attaché à sa maison. Quand le client change, le compteur
// garde son historique : l'index précédent est lu sur le compteur (voir getReferencePrecedente).
require_once __DIR__ . '/connexion.php';

// Compteur correspondant à un numéro (ou null s'il n'existe pas)
function getCompteurParNumero($numero)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx,
        "SELECT ID_Compteur, ID_Client, Type_Compteur, Index_Initial, statut
           FROM compteur WHERE ID_Compteur = ?");
    $numero = (int) $numero;
    mysqli_stmt_bind_param($stmt, "i", $numero);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_close($cnx);
    return $row ?: null;
}

// Compteurs libres (sans client) d'un type, avec leur dernier index connu
function getCompteursLibres($type)
{
    $type = ($type === 'Eau') ? 'Eau' : 'Electricite';
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx,
        "SELECT c.ID_Compteur AS numero, c.Index_Initial,
                (SELECT cm.Index_Actuel
                   FROM consommation_mensuelle cm
                  WHERE cm.ID_Compteur = c.ID_Compteur
                    AND (cm.Statut_Traitement = 'Verifie'
                         OR (cm.Statut_Traitement = 'Anomalie' AND cm.Consommation >= 0))
                  ORDER BY cm.Annee DESC, cm.Mois DESC LIMIT 1) AS dernier_index
           FROM compteur c
          WHERE c.ID_Client IS NULL AND c.Type_Compteur = ?
          ORDER BY c.ID_Compteur");
    mysqli_stmt_bind_param($stmt, "s", $type);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $liste = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $liste[] = [
            'numero' => (int) $r['numero'],
            'index'  => $r['dernier_index'] !== null ? (float) $r['dernier_index'] : (float) $r['Index_Initial'],
        ];
    }
    mysqli_close($cnx);
    return $liste;
}

// Change le compteur d'un client pour un type donné.
//  - champ vide            : le compteur actuel est libéré (statut inactif, sans client)
//  - numéro d'un compteur libre : il est affecté au client, son index actuel est conservé
//  - numéro inconnu        : nouveau compteur créé avec $indexInitial (0 s'il est neuf)
//  - numéro d'un autre client   : refusé
// Retourne null si tout va bien, sinon un message d'erreur.
function changerCompteurClient($idClient, $typeCompteur, $nouveauNumero, $indexInitial = 0)
{
    $idClient      = (int) $idClient;
    $type          = ($typeCompteur === 'Eau') ? 'Eau' : 'Electricite';
    $nouveauNumero = trim((string) $nouveauNumero);

    // Plusieurs compteurs affichés ("101, 102") : champ non modifié, on ne touche à rien
    if (strpos($nouveauNumero, ',') !== false) {
        return null;
    }
    if ($nouveauNumero !== '' && (!ctype_digit($nouveauNumero)
            || (int) $nouveauNumero < 1 || (int) $nouveauNumero > 2147483647)) {
        return "le numéro du compteur doit être un nombre.";
    }
    $indexInitial = (float) str_replace(',', '.', (string) $indexInitial);
    if ($indexInitial < 0 || $indexInitial > 9999999999.99) {
        return "l'index de départ est invalide.";
    }

    $cnx = Connexion();
    try {
        // Compteur actuel du client pour ce type
        $stmt = mysqli_prepare($cnx,
            "SELECT ID_Compteur FROM compteur WHERE ID_Client = ? AND Type_Compteur = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $idClient, $type);
        mysqli_stmt_execute($stmt);
        $ligne  = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $actuel = $ligne ? (int) $ligne['ID_Compteur'] : 0;

        // Champ vidé : on libère le compteur actuel
        if ($nouveauNumero === '') {
            if ($actuel) {
                $stmt = mysqli_prepare($cnx,
                    "UPDATE compteur SET statut = 'inactif', ID_Client = NULL WHERE ID_Compteur = ?");
                mysqli_stmt_bind_param($stmt, "i", $actuel);
                mysqli_stmt_execute($stmt);
            }
            return null;
        }

        $nouveau = (int) $nouveauNumero;
        if ($nouveau === $actuel) {
            return null;                                   // rien à changer
        }

        // Vérifications avant de toucher à quoi que ce soit
        $stmt = mysqli_prepare($cnx,
            "SELECT ID_Compteur, ID_Client, Type_Compteur FROM compteur WHERE ID_Compteur = ?");
        mysqli_stmt_bind_param($stmt, "i", $nouveau);
        mysqli_stmt_execute($stmt);
        $existant = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($existant) {
            if ($existant['Type_Compteur'] !== $type) {
                return "le compteur $nouveau est un compteur d'"
                     . ($existant['Type_Compteur'] === 'Eau' ? 'eau' : 'électricité') . '.';
            }
            if ($existant['ID_Client'] !== null && (int) $existant['ID_Client'] !== $idClient) {
                return "le compteur $nouveau est déjà affecté au client #{$existant['ID_Client']}. "
                     . "Libérez-le d'abord chez ce client.";
            }
        }

        mysqli_begin_transaction($cnx);

        if ($actuel) {                                     // l'ancien compteur reste à sa place, sans client
            $stmt = mysqli_prepare($cnx,
                "UPDATE compteur SET statut = 'inactif', ID_Client = NULL WHERE ID_Compteur = ?");
            mysqli_stmt_bind_param($stmt, "i", $actuel);
            mysqli_stmt_execute($stmt);
        }

        if ($existant) {                                   // compteur existant : historique et index conservés
            $stmt = mysqli_prepare($cnx,
                "UPDATE compteur SET ID_Client = ?, statut = 'actif' WHERE ID_Compteur = ?");
            mysqli_stmt_bind_param($stmt, "ii", $idClient, $nouveau);
        } else {                                           // nouveau compteur
            $date = date('Y-m-d');
            $stmt = mysqli_prepare($cnx,
                "INSERT INTO compteur (ID_Compteur, ID_Client, Type_Compteur, Index_Initial, Date_Installation, statut)
                 VALUES (?, ?, ?, ?, ?, 'actif')");
            mysqli_stmt_bind_param($stmt, "iisds", $nouveau, $idClient, $type, $indexInitial, $date);
        }
        mysqli_stmt_execute($stmt);

        mysqli_commit($cnx);
        return null;
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($cnx);
        return "erreur base de données (" . $e->getMessage() . ").";
    } finally {
        mysqli_close($cnx);
    }
}