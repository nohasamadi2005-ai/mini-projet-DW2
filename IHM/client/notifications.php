<?php
require_once __DIR__ . '/../../traitement/auth.php';
exigerRole('client');
require_once __DIR__ . '/../../BD/connexion.php';

$idClient = $_SESSION['user']['id'];

// Récupération des notifications destinées au client
$cnx = Connexion();
$sql = "SELECT ID_Notification, Message, Date_Envoi, Lu 
        FROM notification 
        WHERE ID_Client = ? AND (Destinataire = 'client' OR Destinataire IS NULL)
        ORDER BY Date_Envoi DESC";
$stmt = mysqli_prepare($cnx, $sql);
mysqli_stmt_bind_param($stmt, "i", $idClient);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$notifications = [];
while ($row = mysqli_fetch_assoc($res)) {
    $notifications[] = $row;
}

// Marquer les notifications comme lues
$sqlUpdate = "UPDATE notification SET Lu = 1 WHERE ID_Client = ? AND Lu = 0";
$stmtUpd = mysqli_prepare($cnx, $sqlUpdate);
mysqli_stmt_bind_param($stmtUpd, "i", $idClient);
mysqli_stmt_execute($stmtUpd);

mysqli_close($cnx);
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fa-solid fa-bell"></i> Mes Notifications & Alertes</h4>
            <span class="badge bg-primary fs-6"><?= count($notifications) ?> message(s)</span>
        </div>
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-envelope-open fa-3x mb-3"></i>
                    <p class="fs-5">Vous n'avez aucune notification pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($notifications as $n): ?>
                        <div class="list-group-item list-group-item-action flex-column align-items-start p-3 mb-2 rounded border">
                            <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                                <h6 class="mb-1 text-primary">
                                    <?php if (strpos($n['Message'], 'refusée') !== false || strpos($n['Message'], 'bloqué') !== false): ?>
                                        <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i><strong>Action requise : Resaisie</strong>
                                    <?php elseif (strpos($n['Message'], 'surconsommation') !== false): ?>
                                        <i class="fa-solid fa-circle-info text-warning me-2"></i><strong>Information : Intervention Technicien</strong>
                                    <?php else: ?>
                                        <i class="fa-solid fa-bell text-info me-2"></i><strong>Notification</strong>
                                    <?php endif; ?>
                                </h6>
                                <small class="text-muted"><i class="fa-solid fa-clock"></i> <?= date('d/m/Y H:i', strtotime($n['Date_Envoi'])) ?></small>
                            </div>

                            <p class="mb-2 text-dark fs-6"><?= htmlspecialchars($n['Message']) ?></p>

                            <!-- Bouton d'action si la notification concerne une resaisie -->
                            <?php if (strpos($n['Message'], 'refusée') !== false): ?>
                                <div class="mt-2">
                                    <a href="index.php?page=saisie" class="btn btn-sm btn-danger">
                                        <i class="fa-solid fa-rotate-right"></i> Effectuer la resaisie maintenant
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>