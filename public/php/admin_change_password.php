<?php
// Core dependencies: database layer, logging system, session security, CSRF protection,
// reusable queries, Composer libraries, password-strength library, and CSP headers.
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/zxcvbn.php';
require_once __DIR__ . '/../../config/csp.php';

// Only allow logged-in admins to access the page.
// If not authenticated, the middleware redirects and terminates.
requireAdminLogin($pdo, $logAdmin, $logConcern);

// INITIALIZATION of PARAMETERS
$step = 'change';
$error = '';
$success = false;
$adminId = $_SESSION['admin_id'];
$email   = $_SESSION['email'];


// Start CSRF token validation and regeneration logic
csrf_protection_start($logAdmin);

// Track incorrect password attempts in the session
if (!isset($_SESSION['failedPasswordChangeAttempts']))
    $_SESSION['failedPasswordChangeAttempts'] = 0;

/**
 * Handles the entire password-change workflow for administrators.
 *
 * Responsibilities:
 * - Validate new password strength using zxcvbn
 * - Ensure user re-types the password correctly
 * - Verify the current password matches the DB
 * - Increment lockout counter on failures and lock account if needed
 * - Block overly similar passwords to mitigate password reuse
 * - Update password securely in the database
 * - Log security-relevant actions
 */
function handleChangePassword(PDO $pdo,PDO $pdoMedium, $log, $logConcern, $logAdmin, $zxcvbn, int $adminId, string &$email, string &$step, string &$error, bool &$success) {

    // Extract submitted form fields
    $oldPassword     = $_POST['old_password'] ?? '';  
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Evaluate password strength to enforce secure credentials
    $strength = $zxcvbn->passwordStrength($newPassword);

    if ($strength['score'] < 3) {
        // Weak password: return user-facing suggestions
        $error = "Password too weak. " .
                 ($strength['feedback']['warning'] ?? '') .
                 implode(' ', $strength['feedback']['suggestions'] ?? []);
        return;
    }

    // Ensure the admin typed both copies of the new password correctly
    if ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match.";
        return;
    }

    // Fetch current password hash from the database
    try {
        $admin = dbGetAdminPassword($pdo, $adminId);
    } catch (PDOException $e) {
        logAdminActions($logAdmin, 'fetching admin password for change', $email, 'error', null);
        $error = "Database error while fetching user.";
        return;
    }

    // Validate old password against stored hash
    if (!$admin || !password_verify($oldPassword, $admin['password_hash'])) {

        // Increment failed attempts locally
        $_SESSION['failedPasswordChangeAttempts'] += 1;
        dbIncreaseAdminFailedLoginAttempts($pdo, $adminId);
        logAdminFailedChangePasswordAttempt($logAdmin,$adminId,$_SERVER);

        // Lockout triggered after 3 failed attempts during password change
        if ($_SESSION['failedPasswordChangeAttempts'] >= 3) {
            $error = "Your account has been locked due to too many failed attempts.";

            try {
                // Lock the admin account at DB level
                dbLockAdminAccount($pdoMedium, $adminId);
                logAdminActions($logAdmin,
                    'account locked due to too many failed change password attempts',
                    $email, 'notice', null
                );
            } catch (PDOException $e) {
                logAdminActions($logAdmin, 'failed to lock admin account', $email, 'error', null);
            }
                // Force logout and redirect to login
                logout($log, $logConcern);
                header("Location: login.php");
                exit;
        } else {
            // Provide remaining attempts count
            $error = "The current password is wrong : attempt {$_SESSION['failedPasswordChangeAttempts']} of 3 before locking.";
            return;
        }
    }

    // Successful password verification resets failure counters
    $_SESSION['failedPasswordChangeAttempts'] = 0;
    dbResetAdminFailedLoginAttempts($pdo, $adminId);

    // Prevent nearly identical passwords (discourages predictable evolution)
    similar_text($oldPassword, $newPassword, $percent);
    if ($percent > 70) {
        $error = "New password is too similar to the old one.";
        return;
    }

    // Update password securely in the database
    try {
        $updatedRows = dbUpdateAdminPassword($pdo, $newPassword, $adminId);

        if ($updatedRows > 0) {
            $success = true;
            $_SESSION['done'] = true; // Marker to trigger logout and redirect
            logAdminPasswordChange($logAdmin,$adminId,$_SERVER);
        }

    } catch (PDOException $e) {
        logAdminActions($logAdmin, 'updating admin password', $email, 'error', null);
        $error = "Database error while updating password.";
    }
}

// ================== MAIN REQUEST CONTROLLER ==================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only proceed if all required fields are present
    if (isset($_POST['old_password'], $_POST['new_password'], $_POST['confirm_password'])) {
        handleChangePassword($pdo, $pdoMedium, $log, $logConcern, $logAdmin, $zxcvbn, $adminId, $email, $step, $error, $success);
    }
}

// After a successful update, force logout to ensure fresh authentication with new credentials
if (isset($_SESSION['done'])) {
    logout($log, $logConcern);
    $step = 'done';
} else {
    $step = 'change';
}

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Change Password</title>
<link rel="stylesheet" href="../assets/css/admin_change_password.css">

<!-- zxcvbn library used on the client side for live password strength feedback -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js"></script>
<script src="../assets/js/admin_change_password.js" defer></script>

<!-- Auto-redirect to login page after successful password change -->
<?php if ($step === 'done'): ?>
  <meta http-equiv="refresh" content="5;url=login.php">
<?php endif; ?>
</head>

<body>
<div class="container">

  <?php if ($step === 'change'): ?>
    <h2>Change Your Password</h2>

    <!-- Display validation or security errors -->
    <?php if ($error): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- Password change form -->
    <form method="post" class="change-form" novalidate>

      <label>Old Password:</label>
      <input type="password" name="old_password" required>

      <label>New Password:</label>
      <input type="password" name="new_password" id="password" required>

      <!-- Live strength meter -->
      <div class="meter"><span id="password-strength-bar"></span></div>

      <!-- Client-side rules for user guidance -->
      <div id="password-suggestions" class="password-suggestions">
        <ul>
          <li id="length-rule">Minimum 8 characters</li>
          <li id="uppercase-rule">At least 1 uppercase letter</li>
          <li id="number-rule">At least 1 number</li>
          <li id="special-rule">At least 1 special character (@#$%&*! etc.)</li>
        </ul>
      </div>

      <div class="feedback" id="password-feedback"></div>

      <label>Confirm New Password:</label>
      <input type="password" name="confirm_password" id="confirm_password" required>

      <button type="submit" id="btn">Change Password</button>
    </form>

    <div class="change-links">
      <p><a href="admin_dashboard.php">← Back to Dashboard</a></p>
    </div>

  <?php elseif ($step === 'done'): ?>

    <!-- Success confirmation screen -->
    <h2>Password Changed </h2>

    <?php if ($success): ?>
      <p class="success-message">Your password was updated successfully.</p>
    <?php endif; ?>

    <p class="redirect-info">Redirecting to login in 5 seconds...</p>
    <p><a href="login.php" class="login-now">Go to Login</a></p>

  <?php endif; ?>

</div>
</body>
</html>
