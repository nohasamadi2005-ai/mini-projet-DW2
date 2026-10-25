<?php
require_once __DIR__ . '/../BD/AgentImport.php';

function traiterDepotReleve() {
    $erreurs = [];
    $succes = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $annee = intval($_POST['annee'] ?? date('Y'));
        $zone = trim($_POST['zone'] ?? 'Secteur Nord — Zone 04');
        $idAgent = $_SESSION['user']['id'] ?? 1;
        
        if (isset($_FILES['fichier_txt']) && $_FILES['fichier_txt']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['fichier_txt']['tmp_name'];
            $fileName = basename($_FILES['fichier_txt']['name']);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($fileExtension !== 'txt') {
                $erreurs[] = "Le format du fichier doit être obligatoirement .TXT.";
            } else {
                // 1. Sauvegarder physiquement le fichier dans le dossier uploads/ pour qu'il soit téléchargeable
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $destPath = $uploadDir . $fileName;
                move_uploaded_file($fileTmpPath, $destPath);

                // 2. Lire le fichier et insérer les lignes dans consommation_annuelle
                $lignes = file($destPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $nbReleves = 0;

                foreach ($lignes as $ligne) {
                    $ligne = trim($ligne);
                    if (empty($ligne) || str_starts_with($ligne, '#')) continue;

                    $parties = preg_split('/[,;\t]+/', $ligne);
                    if (count($parties) >= 3) {
                        $idClient = intval(trim($parties[0]));
                        $consoEau = floatval(trim($parties[1]));
                        $consoElect = floatval(trim($parties[2]));

                        if (insererConsommationAnnuelle($idClient, $idAgent, $annee, $consoEau, $consoElect, $fileName)) {
                            $nbReleves++;
                        }
                    }
                }

                if ($nbReleves > 0) {
                    $succes = "Le fichier a été importé et $nbReleves relevés ont été intégrés avec succès dans la base de données.";
                } else {
                    $erreurs[] = "Aucun relevé valide n'a pu être lu dans le fichier.";
                }
            }
        } else {
            $erreurs[] = "Veuillez sélectionner un fichier .TXT valide.";
        }
    }
    return ['erreurs' => $erreurs, 'succes' => $succes];
}
?>