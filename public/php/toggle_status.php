<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
requireAdminLogin($pdo, $logAdmin, $logConcern);
csrf_protection_start($logAdmin);

// Make sure ID is coming from POST and stored into session (not URL)
if (!empty($_POST['user_id'])) {
    $_SESSION['action_user_id'] = (int) $_POST['user_id'];
}

// If still missing, nothing to do
if (empty($_SESSION['action_user_id'])) {
    logAdminActions($logAdmin, 'missing user ID on toggle request', $_SESSION['admin_id'], 'warning', null);
    header("Location: admin_dashboard.php");
    exit;
}

$id = $_SESSION['action_user_id'];

try {
    $updatedRows = dbSwitchAccountStatusOfUser($pdoMedium, $id);

    if ($updatedRows > 0) {
       logAdminActions($logAdmin, 'switched user account status', $_SESSION['admin_id'], 'notice', $id);
    } else {
       logAdminActions($logAdmin, 'failed to switch user account status', $_SESSION['admin_id'], 'error', $id);
    }

} catch (PDOException $e) {
    logAdminActions($logAdmin, 'DB error while switching user account status', $_SESSION['admin_id'], 'error', $id);
}

// Clear ID from session to avoid re-triggering the action on refresh
unset($_SESSION['action_user_id']);

header("Location: admin_dashboard.php");
exit;
