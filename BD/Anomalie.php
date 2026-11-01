<?php
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/../traitement/Regles.php'; 

// Nombre de resaisies que le fournisseur peut demander (client = 1re saisie + 1 resaisie)
if (!defined('MAX_RESAISIES')) { define('MAX_RESAISIES', 1); }

/**
 * Récupère les anomalies en attente de traitement par le fournisseur.
 * Exclut les relevés en statut 'Resaisie demandee' (car en attente d'action client).
 */
function getAnomaliesEnAttente() {
    $cnx = Connexion();
    
    // Sélectionne uniquement les relevés nécessitant une action du fournisseur :
    // 1. Relevés non traités (Statut NULL, vide ou 'Anomalie')
    // 2. Relevés en statut 'Saisie_Fournisseur' (suite à 2 échecs du client)
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
                   (SELECT COUNT(*) FROM anomalie a WHERE a.ID_Consommation_Mensuelle = cm.ID_Consommation_Mensuelle) AS Nb_Refus
            FROM consommation_mensuelle cm
            JOIN client cl ON cm.ID_Client = cl.ID_Client
            JOIN compteur cp ON cm.ID_Compteur = cp.ID_Compteur
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
            $seuil = (strtolower($row['Type_Compteur']) === 'eau') ? 80 : 500;
            $nbRefus = intval($row['Nb_Refus'] ?? 0);
            $statutTraitement = strtolower($row['Statut_Traitement'] ?? '');

            // RÈGLE MÉTIER : le client a droit à 2 saisies (1ère + 1 resaisie).
            // Dès que la 2e saisie est encore négative, Saisie.php passe le statut à
            // 'Saisie_Fournisseur' : le fournisseur corrige alors lui-même l'index.
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

/**
 * Traitement d'une surconsommation : validation et alerte du client avec lien vers réclamation
 */
function traiterSurconsommation($idConsommation, $idClient) {
    $cnx = Connexion();
    $idConso = intval($idConsommation);
    $idClient = intval($idClient);

    // 1. Validation du statut dans consommation_mensuelle
    $sql = "UPDATE consommation_mensuelle 
            SET Statut_Traitement = 'Verifie' 
            WHERE ID_Consommation_Mensuelle = $idConso";
    mysqli_query($cnx, $sql);

    // 2. Récupération de l'ID_Facture associée
    $sqlFacture = "SELECT ID_Facture FROM facture WHERE ID_Consommation_Mensuelle = $idConso LIMIT 1";
    $resFacture = mysqli_query($cnx, $sqlFacture);
    $idFacture = null;
    if ($resFacture && $rowF = mysqli_fetch_assoc($resFacture)) {
        $idFacture = intval($rowF['ID_Facture']);
    }

    // 3. Message explicatif incitant à faire une réclamation
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

/**
 * Gestion des demandes de resaisie basée sur la table `anomalie`
 */
function demanderResaisie($idConsommation, $idClient, $motif) {
    $cnx = Connexion();
    $idConso = intval($idConsommation);
    $idClient = intval($idClient);

    // Garde-fou : le client n'a droit qu'à UNE resaisie (2 saisies au total).
    // Si un refus existe déjà, on bascule directement en correction fournisseur.
    $r = mysqli_query($cnx, "SELECT COUNT(*) AS n FROM anomalie WHERE ID_Consommation_Mensuelle = $idConso");
    $row = mysqli_fetch_assoc($r);
    if (intval($row['n'] ?? 0) >= MAX_RESAISIES) {
        mysqli_query($cnx, "UPDATE consommation_mensuelle
                            SET Statut_Traitement = 'Saisie_Fournisseur'
                            WHERE ID_Consommation_Mensuelle = $idConso");
        mysqli_close($cnx);
        return false;
    }

    // 1. Historiser le refus
    $stmtAnomalie = mysqli_prepare($cnx, "INSERT INTO anomalie (ID_Consommation_Mensuelle, Type_Anomalie, Description, Statut) VALUES (?, 'Index negatif', ?, 'En cours')");
    mysqli_stmt_bind_param($stmtAnomalie, 'is', $idConso, $motif);
    mysqli_stmt_execute($stmtAnomalie);
    $idAnomalie = mysqli_insert_id($cnx);
    mysqli_stmt_close($stmtAnomalie);

    // 2. Le client peut ressaisir une (dernière) fois
    mysqli_query($cnx, "UPDATE consommation_mensuelle
                        SET Statut_Traitement = 'Resaisie demandee'
                        WHERE ID_Consommation_Mensuelle = $idConso");

    $message = "Votre index a été refusé (motif : $motif). Merci de procéder à une nouvelle saisie. "
             . "Attention : c'est votre dernière tentative ; si l'index est de nouveau incorrect, "
             . "le fournisseur corrigera lui-même l'index d'après la photo de votre compteur.";

    // 3. Notification au client
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

/**
 * Correction manuelle par le fournisseur et génération de la facture
 */
function corrigerEtFacturerParFournisseur($idConsommation, $idClient, $nouvelIndexCorrige) {
    require_once __DIR__ . '/../traitement/Facture.php';   // genererFacture()
    require_once __DIR__ . '/Consommation.php';            // getFactureDeSaisie()

    $cnx = Connexion();
    $idConso = intval($idConsommation);
    $idClient = intval($idClient);
    $indexCorrige = round(floatval($nouvelIndexCorrige), 2);

    $res = mysqli_query($cnx, "SELECT Index_Precedent, Statut_Traitement
                               FROM consommation_mensuelle
                               WHERE ID_Consommation_Mensuelle = $idConso");
    $data = mysqli_fetch_assoc($res);

    // Sécurité : uniquement si le relevé attend bien la correction fournisseur
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

    // 1. Mise à jour de l'index + passage en 'Verifie'
    mysqli_query($cnx, "UPDATE consommation_mensuelle
                        SET Index_Actuel = $indexCorrige,
                            Consommation = $consommation,
                            Statut_Traitement = 'Verifie'
                        WHERE ID_Consommation_Mensuelle = $idConso");
    mysqli_query($cnx, "UPDATE anomalie SET Statut = 'Resolue'
                        WHERE ID_Consommation_Mensuelle = $idConso");
    mysqli_close($cnx);

    // 2. Facture : MÊME calcul que pour une saisie normale (tranches + TVA 18 %, mois de la saisie)
    genererFacture($idConso);
    $fa  = getFactureDeSaisie($idConso);
    $ttc = $fa ? number_format((float) $fa['Montant_TTC'], 2, ',', ' ') : '—';

    // 3. Notification au client
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
