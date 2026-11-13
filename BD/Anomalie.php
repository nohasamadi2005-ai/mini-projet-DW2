<?php
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/../traitement/Regles.php';
require_once __DIR__ . '/Statistiques.php';

if (!defined('MAX_RESAISIES')) { define('MAX_RESAISIES', 1); }

function getAnomaliesEnAttente() {
    $cnx = Connexion();
    
    $sql = "SELECT cm.ID_Consommation_Mensuelle,
                   cm.Index_Precedent,
                   cm.Index_Actuel,
                   cm.Consommation,
                   cm.Photo_Actuelle,
                   cm.Statut_Traitement,
                   cm.Date_saisie,
                   cl.ID_Client,
                   cl.Nom,
                   cl.Prenom,
                   cl.Email,
                   cp.ID_Compteur,
                   cp.Type_Compteur,
                   ct.ID_Contrat,
                   q.Nom AS Quartier,
                   q.Ville,
                   ta.Libelle AS Type_Activite,
                   (SELECT COUNT(*) FROM anomalie a WHERE a.ID_Consommation_Mensuelle = cm.ID_Consommation_Mensuelle) AS Nb_Refus
            " . statFromReleves('LEFT JOIN') . "
            JOIN client cl ON cm.ID_Client = cl.ID_Client
            WHERE (cm.Statut_Traitement IS NULL 
               OR cm.Statut_Traitement = '' 
               OR cm.Statut_Traitement = 'Anomalie'
               OR cm.Statut_Traitement = 'Saisie_Fournisseur')
               AND (cm.Statut_Traitement IS NULL OR cm.Statut_Traitement != 'Resaisie demandee')
               AND LOWER(cm.Statut_Traitement) != 'verifie'
            ORDER BY cm.ID_Consommation_Mensuelle DESC";

    $res = mysqli_query($cnx, $sql);
    $anomalies = [];

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $estEau = (strtolower($row['Type_Compteur']) === 'eau');
            $seuil  = seuilMax($row['Type_Compteur'], $row['Type_Activite'] ?? null);
            $row['Seuil'] = $seuil;
            $row['Unite'] = $estEau ? 'm³' : 'kWh';
            $nbRefus = intval($row['Nb_Refus'] ?? 0);
            $statutTraitement = strtolower($row['Statut_Traitement'] ?? '');

            if ($statutTraitement === 'saisie_fournisseur') {
                $row['Statut_Traitement'] = 'Saisie_Fournisseur';
                $row['Type_Anomalie'] = 'Saisie_Fournisseur';
            } elseif ($row['Consommation'] < 0 || $row['Index_Actuel'] < $row['Index_Precedent']) {
                $row['Type_Anomalie'] = 'Index negatif';
            } elseif ($row['Consommation'] > $seuil) {
                $row['Type_Anomalie'] = 'Surconsommation';
            } else {
                $row['Type_Anomalie'] = 'Saisie suspecte';
            }

            $anomalies[] = $row;
        }
    }
    
    mysqli_close($cnx);
    return $anomalies;
}

function traiterSurconsommation($idConsommation, $idClient) {
    $cnx = Connexion();
    $idConso = intval($idConsommation);
    $idClient = intval($idClient);

    $sql = "UPDATE consommation_mensuelle 
            SET Statut_Traitement = 'Verifie' 
            WHERE ID_Consommation_Mensuelle = $idConso";
    mysqli_query($cnx, $sql);

    $sqlFacture = "SELECT ID_Facture FROM facture WHERE ID_Consommation_Mensuelle = $idConso LIMIT 1";
    $resFacture = mysqli_query($cnx, $sqlFacture);
    $idFacture = null;
    if ($resFacture && $rowF = mysqli_fetch_assoc($resFacture)) {
        $idFacture = intval($rowF['ID_Facture']);
    }

    $message = "Une surconsommation a été détectée sur votre relevé ce mois-ci et votre facture a été générée. Si cette hausse est inhabituelle ou si vous suspectez une anomalie/fuite, vous pouvez déposer une réclamation pour justifier votre consommation.";

    try {
        $stmt = mysqli_prepare($cnx, "INSERT INTO notification (ID_Client, ID_Facture, Message, Date_Envoi) VALUES (?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, 'iis', $idClient, $idFacture, $message);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
    }

    mysqli_close($cnx);
}

function demanderResaisie($idConsommation, $idClient, $motif) {
    $cnx = Connexion();
    $idConso = intval($idConsommation);
    $idClient = intval($idClient);

    $r = mysqli_query($cnx, "SELECT COUNT(*) AS n FROM anomalie WHERE ID_Consommation_Mensuelle = $idConso");
    $row = mysqli_fetch_assoc($r);
    if (intval($row['n'] ?? 0) >= MAX_RESAISIES) {
        mysqli_query($cnx, "UPDATE consommation_mensuelle
                            SET Statut_Traitement = 'Saisie_Fournisseur'
                            WHERE ID_Consommation_Mensuelle = $idConso");
        mysqli_close($cnx);
        return false;
    }

    $stmtAnomalie = mysqli_prepare($cnx, "INSERT INTO anomalie (ID_Consommation_Mensuelle, Type_Anomalie, Description, Statut) VALUES (?, 'Index negatif', ?, 'En cours')");
    mysqli_stmt_bind_param($stmtAnomalie, 'is', $idConso, $motif);
    mysqli_stmt_execute($stmtAnomalie);
    $idAnomalie = mysqli_insert_id($cnx);
    mysqli_stmt_close($stmtAnomalie);

    mysqli_query($cnx, "UPDATE consommation_mensuelle
                        SET Statut_Traitement = 'Resaisie demandee'
                        WHERE ID_Consommation_Mensuelle = $idConso");

    $message = "Votre index a été refusé (motif : $motif). Merci de procéder à une nouvelle saisie. "
             . "Attention : c'est votre dernière tentative ; si l'index est de nouveau incorrect, "
             . "le fournisseur corrigera lui-même l'index d'après la photo de votre compteur.";

    try {
        $stmtNotif = mysqli_prepare($cnx, "INSERT INTO notification (ID_Client, ID_Anomalie, Message, Date_Envoi) VALUES (?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmtNotif, 'iis', $idClient, $idAnomalie, $message);
        mysqli_stmt_execute($stmtNotif);
        mysqli_stmt_close($stmtNotif);
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
    }

    mysqli_close($cnx);
    return true;
}

function corrigerEtFacturerParFournisseur($idConsommation, $idClient, $nouvelIndexCorrige) {
    require_once __DIR__ . '/../traitement/Facture.php';
    require_once __DIR__ . '/Consommation.php';

    $cnx = Connexion();
    $idConso = intval($idConsommation);
    $idClient = intval($idClient);
    $indexCorrige = round(floatval($nouvelIndexCorrige), 2);

    $res = mysqli_query($cnx, "SELECT Index_Precedent, Statut_Traitement
                               FROM consommation_mensuelle
                               WHERE ID_Consommation_Mensuelle = $idConso");
    $data = mysqli_fetch_assoc($res);

    if (!$data || $data['Statut_Traitement'] !== 'Saisie_Fournisseur') {
        mysqli_close($cnx);
        return "Ce relevé n'est pas en attente de correction fournisseur.";
    }

    $indexPrecedent = floatval($data['Index_Precedent']);
    if ($indexCorrige < $indexPrecedent) {
        mysqli_close($cnx);
        return "L'index corrigé ($indexCorrige) ne peut pas être inférieur à l'ancien index ($indexPrecedent).";
    }
    $consommation = round($indexCorrige - $indexPrecedent, 2);

    mysqli_query($cnx, "UPDATE consommation_mensuelle
                        SET Index_Actuel = $indexCorrige,
                            Consommation = $consommation,
                            Statut_Traitement = 'Verifie'
                        WHERE ID_Consommation_Mensuelle = $idConso");
    mysqli_query($cnx, "UPDATE anomalie SET Statut = 'Resolue'
                        WHERE ID_Consommation_Mensuelle = $idConso");
    mysqli_close($cnx);

    genererFacture($idConso);
    $fa  = getFactureDeSaisie($idConso);
    $ttc = $fa ? number_format((float) $fa['Montant_TTC'], 2, ',', ' ') : '—';

    $cnx = Connexion();
    $msg = "Votre index a été corrigé par le fournisseur après vérification de la photo de votre compteur. "
         . "Index retenu : {$indexCorrige}. Votre facture s'élève à {$ttc} DH et est disponible dans « Mes factures ».";
    try {
        $stmt = mysqli_prepare($cnx, "INSERT INTO notification (ID_Client, Message, Date_Envoi) VALUES (?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, 'is', $idClient, $msg);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
    }
    mysqli_close($cnx);
    return true;
}