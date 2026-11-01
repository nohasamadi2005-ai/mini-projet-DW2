<?php
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/../traitement/Regles.php'; 

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

            // RÈGLE MÉTIER STRICTE : La correction fournisseur n'est active qu'à partir de 2 refus
            if ($statutTraitement === 'saisie_fournisseur' || $nbRefus >= 2) {
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

    // 1. Insérer l'anomalie dans la table `anomalie` pour incrémenter l'historique des refus
    $stmtAnomalie = mysqli_prepare($cnx, "INSERT INTO anomalie (ID_Consommation_Mensuelle, Type_Anomalie, Description, Statut) VALUES (?, 'Index negatif', ?, 'En cours')");
    mysqli_stmt_bind_param($stmtAnomalie, 'is', $idConso, $motif);
    mysqli_stmt_execute($stmtAnomalie);
    $idAnomalie = mysqli_insert_id($cnx);
    mysqli_stmt_close($stmtAnomalie);

    // 2. Compter le nombre de refus enregistrés
    $r = mysqli_query($cnx, "SELECT COUNT(*) AS n FROM anomalie WHERE ID_Consommation_Mensuelle = $idConso");
    $row = mysqli_fetch_assoc($r);
    $tentatives = intval($row['n'] ?? 1);

        $statut = ($tentatives >= 2) ? 'Saisie_Fournisseur' : 'Resaisie demandee';

    if ($tentatives >= 2) {
        $message = "Vos 2 tentatives de resaisie n'ont pas été validées. Le fournisseur va analyser la photo de votre compteur et corriger lui-même l'index.";
    } else {
        $nbRestant = 2 - $tentatives;
        $message = "Votre index a été refusé (motif : $motif). Merci de procéder à une nouvelle resaisie (il vous reste $nbRestant tentative(s)).";
    }

    // 4. Mettre à jour le Statut_Traitement
    mysqli_query($cnx, "UPDATE consommation_mensuelle
                        SET Statut_Traitement = '$statut'
                        WHERE ID_Consommation_Mensuelle = $idConso");

    // 5. Notification au client
    try {
        $stmtNotif = mysqli_prepare($cnx, "INSERT INTO notification (ID_Client, ID_Anomalie, Message, Date_Envoi) VALUES (?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmtNotif, 'iis', $idClient, $idAnomalie, $message);
        mysqli_stmt_execute($stmtNotif);
        mysqli_stmt_close($stmtNotif);
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
    }

    mysqli_close($cnx);
}

/**
 * Correction manuelle par le fournisseur et génération de la facture
 */
function corrigerEtFacturerParFournisseur($idConsommation, $idClient, $nouvelIndexCorrige) {
    require_once __DIR__ . '/Facture.php';

    $cnx = Connexion();
    $idConso = intval($idConsommation);
    $idClient = intval($idClient);
    $indexCorrige = floatval($nouvelIndexCorrige);

    // Récupérer l'ancien index et le type de compteur
    $sqlData = "SELECT cm.Index_Precedent, cm.Mois, cm.Annee, cp.Type_Compteur 
                FROM consommation_mensuelle cm
                JOIN compteur cp ON cm.ID_Compteur = cp.ID_Compteur
                WHERE cm.ID_Consommation_Mensuelle = $idConso";
    $resData = mysqli_query($cnx, $sqlData);
    $data = mysqli_fetch_assoc($resData);

    $indexPrecedent = floatval($data['Index_Precedent']);
    $consommationCalcul = max(0, $indexCorrige - $indexPrecedent);

    // 1. Mise à jour de l'index, de la consommation et passage au statut 'Verifie'
    $sqlUpdate = "UPDATE consommation_mensuelle 
                  SET Index_Actuel = $indexCorrige,
                      Consommation = $consommationCalcul,
                      Statut_Traitement = 'Verifie' 
                  WHERE ID_Consommation_Mensuelle = $idConso";
    mysqli_query($cnx, $sqlUpdate);
    mysqli_close($cnx);

    // 2. Calcul financier (Tarif Eau = 6.00 DH/m³ | Électricité = 1.10 DH/kWh, TVA = 14%)
    $prixUnitaire = (strtolower($data['Type_Compteur']) === 'eau') ? 6.00 : 1.10;
    $montantHT = $consommationCalcul * $prixUnitaire;
    $tva = round($montantHT * 0.14, 2);
    $montantTTC = $montantHT + $tva;

    $dateAujourdhui = date('Y-m-d');
    $debutMois = date('Y-m-01');
    $finMois = date('Y-m-t');

    // 3. Insertion de la facture
    insererFacture($idConso, $dateAujourdhui, $debutMois, $finMois, $montantHT, $tva, $montantTTC);

    // 4. Notification au client
    $cnx = Connexion();
    $msg = "Votre index a été corrigé par le fournisseur après vérification de la photo de votre compteur. Index retenu : {$indexCorrige}. Votre facture s'élève à " . number_format($montantTTC, 2) . " DH.";
    
    try {
        $stmt = mysqli_prepare($cnx, "INSERT INTO notification (ID_Client, Message, Date_Envoi) VALUES (?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, 'is', $idClient, $msg);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
    }

    mysqli_close($cnx);
}