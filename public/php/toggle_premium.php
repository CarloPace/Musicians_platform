<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../config/csp.php';

requireAdminLogin($pdo, $logAdmin, $logConcern);
// Validate CSRF token from POST request
csrf_protection_start($logAdmin);

// Store userId from POST into session to avoid URL manipulation
if (!empty($_POST['user_id'])) {
    $_SESSION['premium_user_id'] = (int) $_POST['user_id'];
}

// If still not set, cannot proceed
if (empty($_SESSION['premium_user_id'])) {
   logAdminActions($logAdmin, 'missing user ID for premium status toggle', $_SESSION['admin_id'], 'warning', null);
    header("Location: admin_dashboard.php");
    exit;
}

$id = $_SESSION['premium_user_id'];

try {
    $updatedRows = dbSwitchPremiumStatusOfUser($pdoMedium, $id);

    if ($updatedRows > 0) {
       logAdminActions($logAdmin, 'switched premium status of user', $_SESSION['admin_id'], 'notice', $id);
    } else {
        logAdminActions($logAdmin, 'failed to switch premium status of user', $_SESSION['admin_id'], 'error', $id);
    }



} catch (PDOException $e) {
  logAdminActions($logAdmin, 'DB error while switching premium status', $_SESSION['admin_id'], 'error', $id);
}

// Prevent duplicate action on refresh
unset($_SESSION['premium_user_id']);

header("Location: admin_dashboard.php");
exit;
