<?php
require_once __DIR__ . '/connexion.php';

function getImportsAgent($idAgent = null, $search = '') {
    $cnx = Connexion();
    $search = mysqli_real_escape_string($cnx, $search);
    $sql = "SELECT Fichier_Source AS Nom_Fichier, 
                   MIN(Date_saisie) AS Date_Import, 
                   Annee, 
                   'Secteur Nord — Zone 04' AS Zone, 
                   COUNT(*) AS Nb_Releves, 
                   'Intégré' AS Statut 
            FROM consommation_annuelle 
            WHERE Fichier_Source IS NOT NULL AND Fichier_Source != ''";
    if ($idAgent) {
        $idAgent = intval($idAgent);
        $sql .= " AND ID_Agent = $idAgent";
    }
    if (!empty($search)) {
        $sql .= " AND (Fichier_Source LIKE '%$search%' OR Annee LIKE '%$search%')";
    }
    $sql .= " GROUP BY Fichier_Source, Annee ORDER BY Date_Import DESC";
    $result = mysqli_query($cnx, $sql);
    $imports = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $imports[] = $row;
        }
    }
    mysqli_close($cnx);
    return $imports;
}

function getStatistiquesImports($idAgent = null) {
    $cnx = Connexion();
    $where = $idAgent ? "WHERE ID_Agent = " . intval($idAgent) : "";
    
    $res = mysqli_query($cnx, "SELECT COUNT(DISTINCT Fichier_Source) as fichiers_deposes, COUNT(*) as releves_integres FROM consommation_annuelle $where");
    $data = mysqli_fetch_assoc($res);

    mysqli_close($cnx);
    return [
        'fichiers_deposes' => $data['fichiers_deposes'] ?? 0,
        'releves_integres' => $data['releves_integres'] ?? 0
    ];
}

function insererConsommationAnnuelle($idClient, $idAgent, $annee, $consoEau, $consoElect, $fichierSource) {
    $cnx = Connexion();
    $stmt = mysqli_prepare($cnx, "INSERT INTO consommation_annuelle (ID_Client, ID_Agent, Annee, ConsommationEau, ConsommationElect, Date_saisie, Fichier_Source) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iiidds", $idClient, $idAgent, $annee, $consoEau, $consoElect, $fichierSource);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($cnx);
        return $res;
    }
    mysqli_close($cnx);
    return false;
}
?>