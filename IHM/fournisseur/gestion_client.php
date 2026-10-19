<?php
require_once __DIR__ . '/../../traitement/auth.php';
exigerRole('fournisseur');
require_once __DIR__ . '/../../BD/Client.php';

// Gestion de la recherche et de la pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$pageActuelle = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$parPage = 5; 
$debut = ($pageActuelle - 1) * $parPage;

$cnx = Connexion();
$searchEsc = mysqli_real_escape_string($cnx, $search);

// 1. Compter le nombre total de clients pour la pagination
$sqlCount = "SELECT COUNT(DISTINCT c.ID_Client) as total FROM client c LEFT JOIN compteur cp ON c.ID_Client = cp.ID_Client";
if (!empty($search)) {
    $sqlCount .= " WHERE c.ID_Client LIKE '%$searchEsc%' OR c.Nom LIKE '%$searchEsc%' OR c.Prenom LIKE '%$searchEsc%' OR cp.ID_Compteur LIKE '%$searchEsc%'";
}
$resCount = mysqli_query($cnx, $sqlCount);
$totalClients = mysqli_fetch_assoc($resCount)['total'] ?? 0;
$totalPages = ceil($totalClients / $parPage);
if ($totalPages < 1) $totalPages = 1;
if ($pageActuelle > $totalPages) $pageActuelle = $totalPages;
if ($pageActuelle < 1) $pageActuelle = 1;
$debut = ($pageActuelle - 1) * $parPage;

// 2. Récupérer les clients de la page courante avec leurs compteurs
$sql = "SELECT c.*, 
        MAX(CASE WHEN cp.Type_Compteur = 'Eau' THEN cp.ID_Compteur END) as compteur_eau_id,
        MAX(CASE WHEN cp.Type_Compteur = 'Electricite' THEN cp.ID_Compteur END) as compteur_elec_id
        FROM client c
        LEFT JOIN compteur cp ON c.ID_Client = cp.ID_Client";
        
if (!empty($search)) {
    $sql .= " WHERE c.ID_Client LIKE '%$searchEsc%' 
              OR c.Nom LIKE '%$searchEsc%' 
              OR c.Prenom LIKE '%$searchEsc%' 
              OR cp.ID_Compteur LIKE '%$searchEsc%'";
}

$sql .= " GROUP BY c.ID_Client ORDER BY c.ID_Client DESC LIMIT $debut, $parPage";

$result = mysqli_query($cnx, $sql);
$clients = [];
while ($row = mysqli_fetch_assoc($result)) {
    $clients[] = $row;
}
mysqli_close($cnx);

$debutAffichage = ($totalClients > 0) ? $debut + 1 : 0;
$finAffichage = min($debut + $parPage, $totalClients);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fluxio - Gestion des Clients & Compteurs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Fluxio style d'origine (Bleu) */
        .sidebar { width: 270px; background-color: #0F2137; color: #fff; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 25px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .logo-icon { width: 38px; height: 38px; background: #1890FF; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; }
        .sidebar-brand .brand-text { font-size: 16px; font-weight: bold; color: #ffffff; line-height: 1.2; }
        .sidebar-brand .brand-sub { font-size: 12px; font-weight: normal; color: #94A3B8; }

        .sidebar-menu { list-style: none; padding: 20px 15px; flex: 1; display: flex; flex-direction: column; gap: 5px; }
        .sidebar-menu li a { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: #A0AEC0; text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 8px; transition: 0.2s; }
        .sidebar-menu li.active a, .sidebar-menu li a:hover { background-color: #1A3353; color: #fff; border-left: 4px solid #1890FF; font-weight: 600; }
        
        /* Espace Fournisseur badge style box dans le menu */
        .sidebar-card-box { margin: 15px; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; display: flex; align-items: center; gap: 10px; }
        .sidebar-card-box i { color: #1890FF; font-size: 18px; }
        .sidebar-card-box .box-title { font-size: 13px; font-weight: 600; color: #ffffff; }
        .sidebar-card-box .box-sub { font-size: 11px; color: #94A3B8; }

        /* Sidebar Footer (Profil & Déconnexion) */
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; gap: 15px; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 36px; height: 36px; background: #1A3353; color: #38BDF8; font-weight: 600; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .user-info .user-name { font-size: 13px; font-weight: 600; color: #ffffff; }
        .user-info .user-role { font-size: 11px; color: #94A3B8; }
        .logout-btn { display: flex; align-items: center; gap: 8px; color: #F87171; text-decoration: none; font-size: 13px; font-weight: 500; padding: 5px 0; transition: 0.2s; }
        .logout-btn:hover { color: #EF4444; }

        /* Main Content */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background-color: #F8FAFC; }
        .header { background: #fff; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; }
        .header h1 { font-size: 18px; color: #1E293B; font-weight: 600; }
        
        .container { padding: 30px; }
        
        /* Actions Bar */
        .action-bar { display: flex; justify-content: space-between; margin-bottom: 20px; gap: 15px; }
        .search-box { display: flex; background: #fff; border: 1px solid #CBD5E1; border-radius: 8px; overflow: hidden; width: 350px; }
        .search-box input { border: none; padding: 10px 15px; outline: none; width: 100%; font-size: 14px; }
        .search-box button { background: #fff; border: none; padding: 0 15px; color: #64748B; cursor: pointer; }
        
        .btn-primary { background-color: #1890FF; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-primary:hover { background-color: #0284C7; }

        /* Table Card */
        .table-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #E2E8F0; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; min-height: 380px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #F8FAFC; color: #475569; font-weight: 600; padding: 14px 20px; font-size: 13px; border-bottom: 1px solid #E2E8F0; }
        td { padding: 14px 20px; color: #334155; font-size: 14px; border-bottom: 1px solid #F1F5F9; }
        tr:hover { background-color: #F8FAFC; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-actif { background: #DCFCE7; color: #15803D; }

        .btn-action { background: #EEF2F6; border: none; padding: 6px 12px; border-radius: 6px; color: #475569; cursor: pointer; font-weight: 500; transition: 0.2s; }
        .btn-action:hover { background: #1890FF; color: #fff; }

        /* Pagination Footer (Boutons actifs) */
        .pagination-footer { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-top: 1px solid #E2E8F0; background: #fff; font-size: 13px; color: #64748B; }
        .pagination-buttons { display: flex; gap: 10px; }
        .page-btn { background: #1890FF; border: 1px solid #1890FF; padding: 6px 14px; border-radius: 6px; color: #ffffff; font-size: 13px; font-weight: 500; text-decoration: none; transition: 0.2s; cursor: pointer; display: inline-block; }
        .page-btn:hover:not(.disabled) { background: #0284C7; border-color: #0284C7; }
        .page-btn.disabled { opacity: 0.4; cursor: not-allowed; background: #94A3B8; border-color: #94A3B8; }

        /* Modal style */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: #fff; padding: 30px; border-radius: 12px; width: 450px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .modal-content h2 { margin-bottom: 20px; font-size: 18px; color: #0F2137; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #CBD5E1; border-radius: 6px; outline: none; font-size: 14px; background: #fff; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-secondary { background: #E2E8F0; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500; color: #475569; }
    </style>
</head>
<body>

    <!-- Sidebar Bleue d'origine -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="logo-icon"><i class="fa-solid fa-droplet"></i></div>
            <div>
                <div class="brand-text">Gestion des Factures</div>
                <div class="brand-sub">Eau & Électricité</div>
            </div>
        </div>

        <div class="sidebar-card-box">
            <i class="fa-solid fa-shield-halved"></i>
            <div>
                <div class="box-title">Espace Fournisseur</div>
                <div class="box-sub">Accès défini par votre compte</div>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li class="active"><a href="index.php?page=gestion_client"><i class="fa-solid fa-users"></i> Gestion Clients</a></li>
            <li><a href="#"><i class="fa-solid fa-triangle-exclamation"></i> Traitement des Anomalies</a></li>
            <li><a href="#"><i class="fa-solid fa-file-invoice-dollar"></i> Gestion des Réclamations</a></li>
        </ul>

        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar">SF</div>
                <div class="user-info">
                    <div class="user-name">Service Fournisseur</div>
                    <div class="user-role">Administrateur</div>
                </div>
            </div>
            <a href="index.php?page=logout" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Se déconnecter</a>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="header">
            <h1>Administration des Clients et Compteurs</h1>
            <div style="font-weight: 600; color: #64748B; font-size: 13px;">Espace Fournisseur</div>
        </div>

        <div class="container">
            <!-- Barre d'action et recherche rapide -->
            <div class="action-bar">
                <form method="GET" action="index.php" class="search-box">
                    <input type="hidden" name="page" value="gestion_client">
                    <input type="text" name="search" placeholder="Filtrer par ID, Nom, Prénom ou Compteur..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fa-solid fa-search"></i></button>
                </form>
                <button class="btn-primary" onclick="openModal('addModal')"><i class="fa-solid fa-user-plus"></i> Ajouter un client</button>
            </div>

            <!-- Table des clients avec Pagination -->
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>ID Client</th>
                            <th>Nom & Prénom</th>
                            <th>Adresse</th>
                            <th>Téléphone / Email</th>
                            <th>Compteur Eau</th>
                            <th>Compteur Électricité</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clients)): ?>
                            <tr><td colspan="8" style="text-align: center; color: #94A3B8; padding: 30px;">Aucun client trouvé.</td></tr>
                        <?php else: ?>
                            <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><strong>#<?php echo $client['ID_Client']; ?></strong></td>
                                <td><?php echo htmlspecialchars($client['Nom'] . ' ' . $client['Prenom']); ?></td>
                                <td><?php echo htmlspecialchars($client['Adresse']); ?></td>
                                <td><?php echo htmlspecialchars($client['Telephone']); ?><br><small style="color: #64748B;"><?php echo htmlspecialchars($client['Email']); ?></small></td>
                                <!-- Colonne Compteur Eau -->
<td>
    <span style="font-size: 12px; font-weight: 600; color: #0284C7;">
        <?php echo !empty($client['compteur_eau_ids']) ? htmlspecialchars($client['compteur_eau_ids']) : 'Aucun'; ?>
    </span>
</td>

<!-- Colonne Compteur Électricité -->
<td>
    <span style="font-size: 12px; font-weight: 600; color: #D97706;">
        <?php echo !empty($client['compteur_elec_ids']) ? htmlspecialchars($client['compteur_elec_ids']) : 'Aucun'; ?>
    </span>
</td>
                                <td>
                                    <span class="badge badge-actif">Actif</span>
                                </td>
                                <td>
                                    <button class="btn-action" onclick="openEditModal(
                                      '<?php echo $client['ID_Client']; ?>',
    '<?php echo addslashes($client['Nom']); ?>',
    '<?php echo addslashes($client['Prenom']); ?>',
    '<?php echo addslashes($client['Adresse']); ?>',
    '<?php echo addslashes($client['Telephone']); ?>',
    '<?php echo addslashes($client['Email']); ?>',
    '<?php echo addslashes($client['compteur_eau_ids'] ?? ''); ?>',
    '<?php echo addslashes($client['compteur_elec_ids'] ?? ''); ?>'
                                    )"><i class="fa-solid fa-pen"></i> Modifier</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Footer Pagination -->
                <div class="pagination-footer">
                    <div>
                        Affichage de <?php echo $debutAffichage; ?> à <?php echo $finAffichage; ?> sur <?php echo $totalClients; ?> clients
                    </div>
                    <div class="pagination-buttons">
                        <?php if ($pageActuelle > 1): ?>
                            <a href="index.php?page=gestion_client&search=<?php echo urlencode($search); ?>&p=<?php echo $pageActuelle - 1; ?>" class="page-btn">Précédent</a>
                        <?php else: ?>
                            <span class="page-btn disabled">Précédent</span>
                        <?php endif; ?>

                        <?php if ($pageActuelle < $totalPages): ?>
                            <a href="index.php?page=gestion_client&search=<?php echo urlencode($search); ?>&p=<?php echo $pageActuelle + 1; ?>" class="page-btn">Suivant</a>
                        <?php else: ?>
                            <span class="page-btn disabled">Suivant</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

   <!-- Modale d'Ajout -->
    <div id="addModal" class="modal">
        <div class="modal-content" style="max-height: 90vh; overflow-y: auto;">
            <h2>Ajouter un nouveau client</h2>
            <form action="/mini-projet-DW2/traitement/client-compteur.php" method="POST">
                <input type="hidden" name="action" value="ajouter">
                <div class="form-group"><label>Nom</label><input type="text" name="nom" required></div>
                <div class="form-group"><label>Prénom</label><input type="text" name="prenom" required></div>
                <div class="form-group"><label>Adresse</label><input type="text" name="adresse"></div>
                <div class="form-group"><label>Téléphone</label><input type="text" name="telephone"></div>
                <div class="form-group"><label>Email (Identifiant)</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Mot de passe provisoire</label><input type="password" name="password" required></div>
                
                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #E2E8F0;">
                <h3 style="font-size: 14px; color: #1890FF; margin-bottom: 12px;"><i class="fa-solid fa-gauge"></i> Compteur initial</h3>
                <div class="form-group"><label>Numéro / Référence du compteur</label><input type="text" name="numero_compteur" placeholder="Ex: CPT-9988"></div>
                <div class="form-group">
                    <label>Type de compteur</label>
                    <select name="type_compteur">
                        <option value="Eau">Eau</option>
                        <option value="Electricite">Électricité</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('addModal')">Annuler</button>
                    <button type="submit" class="btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

   <!-- Modale de Modification -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-height: 90vh; overflow-y: auto;">
            <h2>Modifier le client et ses compteurs</h2>
            <form action="/mini-projet-DW2/traitement/client-compteur.php" method="POST">
                <input type="hidden" name="action" value="modifier">
                <input type="hidden" name="id_client" id="edit_id">
                
                <div class="form-group"><label>Nom</label><input type="text" name="nom" id="edit_nom" required></div>
                <div class="form-group"><label>Prénom</label><input type="text" name="prenom" id="edit_prenom" required></div>
                <div class="form-group"><label>Adresse</label><input type="text" name="adresse" id="edit_adresse"></div>
                <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" id="edit_telephone"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" required></div>

                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #E2E8F0;">
                <h3 style="font-size: 14px; color: #1890FF; margin-bottom: 12px;"><i class="fa-solid fa-gauge"></i> Gestion des Compteurs</h3>
                <div class="form-group"><label>Numéro Compteur Eau</label><input type="text" name="compteur_eau" id="edit_compteur_eau"></div>
                <div class="form-group"><label>Numéro Compteur Électricité</label><input type="text" name="compteur_elec" id="edit_compteur_elec"></div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Annuler</button>
                    <button type="submit" class="btn-primary">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openEditModal(id, nom, prenom, adresse, telephone, email, compteurEau, compteurElec) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nom').value = nom;
            document.getElementById('edit_prenom').value = prenom;
            document.getElementById('edit_adresse').value = adresse;
            document.getElementById('edit_telephone').value = telephone;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_compteur_eau').value = compteurEau;
            document.getElementById('edit_compteur_elec').value = compteurElec;
            openModal('editModal');
        }
    </script>
</body>
</html>