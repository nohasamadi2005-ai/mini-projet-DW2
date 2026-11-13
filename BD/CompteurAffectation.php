<?php
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/../traitement/Regles.php';

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

function dernierIndexCompteur($cnx, $idCompteur)
{
    $stmt = mysqli_prepare($cnx,
        "SELECT COALESCE(
                  (SELECT cm.Index_Actuel
                     FROM consommation_mensuelle cm
                    WHERE cm.ID_Compteur = c.ID_Compteur
                      AND (cm.Statut_Traitement = 'Verifie'
                           OR (cm.Statut_Traitement = 'Anomalie' AND cm.Consommation >= 0))
                    ORDER BY cm.Annee DESC, cm.Mois DESC LIMIT 1),
                  c.Index_Initial) AS idx
           FROM compteur c WHERE c.ID_Compteur = ?");
    mysqli_stmt_bind_param($stmt, "i", $idCompteur);
    mysqli_stmt_execute($stmt);
    $ligne = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return $ligne ? (float) $ligne['idx'] : 0.0;
}

function libererCompteurAvecHistorique($cnx, $idCompteur, $idClient)
{
    $indexFin = dernierIndexCompteur($cnx, $idCompteur);
    $dateFin  = date('Y-m-d', function_exists('maintenant') ? maintenant() : time());

    $stmt = mysqli_prepare($cnx,
        "UPDATE contrat SET Statut = 'Resilie', Date_Fin = GREATEST(Date_Debut, ?), Index_Fin = ?, Motif_Fin = 'Resiliation'
          WHERE ID_Compteur = ? AND Statut = 'Actif'");
    mysqli_stmt_bind_param($stmt, "sdi", $dateFin, $indexFin, $idCompteur);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($cnx,
        "UPDATE compteur SET statut = 'inactif', ID_Client = NULL WHERE ID_Compteur = ?");
    mysqli_stmt_bind_param($stmt, "i", $idCompteur);
    mysqli_stmt_execute($stmt);
}

function ouvrirHistoriqueCompteur($cnx, $idCompteur, $idClient, $indexDebut)
{
    $date = date('Y-m-d', function_exists('maintenant') ? maintenant() : time());
    $stmt = mysqli_prepare($cnx,
        "INSERT INTO contrat (ID_Client, ID_Compteur, ID_Quartier, Adresse_Branchement, Date_Debut, Index_Debut, Statut)
         SELECT cl.ID_Client, ?,
                COALESCE((SELECT x.ID_Quartier FROM contrat x WHERE x.ID_Compteur = ? ORDER BY x.ID_Contrat DESC LIMIT 1),
                         IF(LOWER(cl.Adresse) LIKE '%t_touan%', 5, 1)),
                cl.Adresse, ?, ?, 'Actif'
           FROM client cl WHERE cl.ID_Client = ?");
    mysqli_stmt_bind_param($stmt, "iisdi", $idCompteur, $idCompteur, $date, $indexDebut, $idClient);
    mysqli_stmt_execute($stmt);
}

function changerCompteurClient($idClient, $typeCompteur, $nouveauNumero, $indexInitial = 0)
{
    $idClient      = (int) $idClient;
    $type          = ($typeCompteur === 'Eau') ? 'Eau' : 'Electricite';
    $nouveauNumero = trim((string) $nouveauNumero);

    if (strpos($nouveauNumero, ',') !== false) {
        return null;
    }
    if ($nouveauNumero !== '' && (!ctype_digit($nouveauNumero)
            || (int) $nouveauNumero < 1 || (int) $nouveauNumero > 2147483647)) {
        return "le numéro du compteur doit être un nombre.";
    }
    $indexInitial = random_int(0, 5);

    $cnx = Connexion();
    try {
        $stmt = mysqli_prepare($cnx,
            "SELECT ID_Compteur FROM compteur WHERE ID_Client = ? AND Type_Compteur = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $idClient, $type);
        mysqli_stmt_execute($stmt);
        $ligne  = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $actuel = $ligne ? (int) $ligne['ID_Compteur'] : 0;

        if ($nouveauNumero === '') {
            if ($actuel) {
                mysqli_begin_transaction($cnx);
                libererCompteurAvecHistorique($cnx, $actuel, $idClient);
                mysqli_commit($cnx);
            }
            return null;
        }

        $nouveau = (int) $nouveauNumero;
        if ($nouveau === $actuel) {
            return null;
        }

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

        if ($actuel) {
            libererCompteurAvecHistorique($cnx, $actuel, $idClient);
        }

        if ($existant) {
            $stmt = mysqli_prepare($cnx,
                "UPDATE compteur SET ID_Client = ?, statut = 'actif' WHERE ID_Compteur = ?");
            mysqli_stmt_bind_param($stmt, "ii", $idClient, $nouveau);
        } else {
            $date = date('Y-m-d');
            $stmt = mysqli_prepare($cnx,
                "INSERT INTO compteur (ID_Compteur, ID_Client, Type_Compteur, Index_Initial, Date_Installation, statut)
                 VALUES (?, ?, ?, ?, ?, 'actif')");
            mysqli_stmt_bind_param($stmt, "iisds", $nouveau, $idClient, $type, $indexInitial, $date);
        }
        mysqli_stmt_execute($stmt);

        ouvrirHistoriqueCompteur($cnx, $nouveau, $idClient,
            $existant ? dernierIndexCompteur($cnx, $nouveau) : (float) $indexInitial);

        mysqli_commit($cnx);
        return null;
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($cnx);
        return "erreur base de données (" . $e->getMessage() . ").";
    } finally {
        mysqli_close($cnx);
    }
}