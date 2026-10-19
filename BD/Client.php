<?php
require_once __DIR__ . '/Connexion.php';

function trouverClientParEmail($email)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx,
        "SELECT ID_Client AS id, Nom, Prenom, Email, Password AS mdp
         FROM client WHERE Email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res);   // une ligne (tableau) ou null si introuvable
}

function creerClient($nom, $prenom, $adresse, $telephone, $email, $hash)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx,
        "INSERT INTO client (Nom, Prenom, Adresse, Telephone, Email, Password)
         VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssssss", $nom, $prenom, $adresse, $telephone, $email, $hash);
    if (mysqli_stmt_execute($stmt)) {
        return mysqli_insert_id($cnx); // Retourne l'ID du client fraîchement créé
    }
    return false;
}

function modifierMotDePasseClient($id, $hash)
{
    $cnx  = Connexion();
    $stmt = mysqli_prepare($cnx, "UPDATE client SET Password = ? WHERE ID_Client = ?");
    mysqli_stmt_bind_param($stmt, "si", $hash, $id);
    return mysqli_stmt_execute($stmt);
}

function getAllClientsWithCompteurs($search = '') {
    $cnx = Connexion();
    $search = mysqli_real_escape_string($cnx, $search);
    
    $sql = "SELECT c.*, 
            GROUP_CONCAT(DISTINCT CASE WHEN cp.Type_Compteur = 'Eau' THEN cp.ID_Compteur END SEPARATOR ', ') as compteur_eau_ids,
            GROUP_CONCAT(DISTINCT CASE WHEN cp.Type_Compteur = 'Electricite' THEN cp.ID_Compteur END) as compteur_elec_ids
            FROM client c
            LEFT JOIN compteur cp ON c.ID_Client = cp.ID_Client";
            
    if (!empty($search)) {
        $sql .= " WHERE c.ID_Client LIKE '%$search%' 
                  OR c.Nom LIKE '%$search%' 
                  OR c.Prenom LIKE '%$search%' 
                  OR cp.ID_Compteur LIKE '%$search%'";
    }
    
    $sql .= " GROUP BY c.ID_Client ORDER BY c.ID_Client DESC";
    
    $result = mysqli_query($cnx, $sql);
    $clients = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $clients[] = $row;
    }
    mysqli_close($cnx);
    return $clients;
}


// Mettre à jour les informations d'un client
function modifierClient($idClient, $nom, $prenom, $adresse, $telephone, $email) {
    $cnx = Connexion();
    $sql = "UPDATE client SET Nom='$nom', Prenom='$prenom', Adresse='$adresse', Telephone='$telephone', Email='$email' WHERE ID_Client=$idClient";
    $res = mysqli_query($cnx, $sql);
    mysqli_close($cnx);
    return $res;
}



// BD/Client.php

// Ajouter un client et lui associer son compteur initial (Eau ou Électricité)
function ajouterClientAvecCompteur($nom, $prenom, $adresse, $telephone, $email, $password, $numeroCompteur, $typeCompteur) {
    $cnx = Connexion();
    $nom = mysqli_real_escape_string($cnx, $nom);
    $prenom = mysqli_real_escape_string($cnx, $prenom);
    $adresse = mysqli_real_escape_string($cnx, $adresse);
    $telephone = mysqli_real_escape_string($cnx, $telephone);
    $email = mysqli_real_escape_string($cnx, $email);
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // 1. Création du client
    $sqlClient = "INSERT INTO client (Nom, Prenom, Adresse, Telephone, Email, Password) 
                  VALUES ('$nom', '$prenom', '$adresse', '$telephone', '$email', '$passwordHash')";
    
    if (mysqli_query($cnx, $sqlClient)) {
        $idClient = mysqli_insert_id($cnx);

        // 2. Association du compteur s'il a été renseigné
        if (!empty($numeroCompteur)) {
            $numeroCompteur = mysqli_real_escape_string($cnx, $numeroCompteur);
            // S'assurer que le type est uniquement Eau ou Electricite
            $typeCompteur = ($typeCompteur === 'Eau') ? 'Eau' : 'Electricite';
            $dateInstall = date('Y-m-d');
            
            // On conserve la colonne 'statut' ('actif' / 'inactif') pour gérer les changements de logement/réclamations
            $sqlCompteur = "INSERT INTO compteur (ID_Compteur, ID_Client, Type_Compteur, Index_Initial, Date_Installation, statut) 
                            VALUES ('$numeroCompteur', $idClient, '$typeCompteur', 0.00, '$dateInstall', 'actif')";
            mysqli_query($cnx, $sqlCompteur);
        }
        
        mysqli_close($cnx);
        return true;
    }
    mysqli_close($cnx);
    return false;
}

// Fonction complémentaire : Dissocier/Désactiver un compteur (changement de logement)
function desactiverCompteur($idCompteur) {
    $cnx = Connexion();
    $idCompteur = mysqli_real_escape_string($cnx, $idCompteur);
    $sql = "UPDATE compteur SET statut='inactif', ID_Client=NULL WHERE ID_Compteur='$idCompteur'";
    $res = mysqli_query($cnx, $sql);
    mysqli_close($cnx);
    return $res;
}

// Fonction complémentaire : Attribuer un compteur existant/nouveau à un client
function associerCompteurAClient($idClient, $idCompteur, $typeCompteur) {
    $cnx = Connexion();
    $idCompteur = mysqli_real_escape_string($cnx, $idCompteur);
    $typeCompteur = ($typeCompteur === 'Eau') ? 'Eau' : 'Electricite';
    
    $sql = "UPDATE compteur 
            SET ID_Client = $idClient, Type_Compteur = '$typeCompteur', statut = 'actif' 
            WHERE ID_Compteur = '$idCompteur'";
    $res = mysqli_query($cnx, $sql);
    mysqli_close($cnx);
    return $res;
}
function mettreAJourCompteurClient($idClient, $typeCompteur, $nouveauNumero) {
    $cnx = Connexion();
    $idClient = intval($idClient);
    $typeCompteur = ($typeCompteur === 'Eau') ? 'Eau' : 'Electricite';
    
    // Vérifier si un compteur de ce type existe déjà pour ce client
    $res = mysqli_query($cnx, "SELECT ID_Compteur FROM compteur WHERE ID_Client = $idClient AND Type_Compteur = '$typeCompteur'");
    $existe = mysqli_fetch_assoc($res);

    if (!empty($nouveauNumero)) {
        $nouveauNumero = mysqli_real_escape_string($cnx, $nouveauNumero);
        if ($existe) {
            // On met à jour l'ancien ID du compteur
            $oldId = $existe['ID_Compteur'];
            mysqli_query($cnx, "UPDATE compteur SET ID_Compteur = '$nouveauNumero' WHERE ID_Compteur = '$oldId'");
        } else {
            // S'il n'en avait pas, on l'ajoute
            $dateInstall = date('Y-m-d');
            mysqli_query($cnx, "INSERT INTO compteur (ID_Compteur, ID_Client, Type_Compteur, Index_Initial, Date_Installation, statut) 
                                VALUES ('$nouveauNumero', $idClient, '$typeCompteur', 0.00, '$dateInstall', 'actif')");
        }
    } else {
        // Si le champ a été vidé, on peut désactiver ou supprimer le compteur
        if ($existe) {
            $oldId = $existe['ID_Compteur'];
            mysqli_query($cnx, "UPDATE compteur SET statut = 'inactif', ID_Client = NULL WHERE ID_Compteur = '$oldId'");
        }
    }
    mysqli_close($cnx);
}
?>