<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/zxcvbn.php';
require_once __DIR__ . '/../../config/csp.php';

requireLogin($pdoLow, $log, $logConcern); // Ensures only authenticated users can access this page

// INITIALIZATION of PARAMETERS
$step = 'change';            // Controls which page state to display
$error = '';                 // Holds validation or process errors
$success = false;            // Indicates whether password update succeeded
$userId = $_SESSION['user_id'];   // Currently logged-in user ID
$email = $_SESSION['email'];       // User email for logging
csrf_protection_start($logConcern); // Begins CSRF protection and logs issues if any

// Initialize failed attempt counter if not set
if (!isset($_SESSION['failedPasswordChangeAttempts']))
    $_SESSION['failedPasswordChangeAttempts'] = 0;

// === STEP HANDLER FUNCTION ===
// Handles everything related to processing a password change request
function handleChangePassword(PDO $pdoLow, PDO $pdoMedium, $log,$logConcern, $zxcvbn, int $userId, string &$email, string &$step, string &$error, bool &$success) {

    // Fetch input fields safely
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Evaluate password strength using zxcvbn
    $strength = $zxcvbn->passwordStrength($newPassword);

    // Reject weak passwords before proceeding further
    if ($strength['score'] < 3) {
        $error = "Password too weak. " .
                 ($strength['feedback']['warning'] ?? '') .
                 implode(' ', $strength['feedback']['suggestions'] ?? []);
        return;
    }

    // Ensure new password matches confirmation
    if ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match.";
        return;
    }

    // Retrieve user’s existing password from database
    try {
        $user = dbGetUserPassword($pdoLow, $userId);
    } catch (PDOException $e) {
        logDbError($log, 'fetching user password for change', $email, $e); // Logs DB failure
        $error = "Database error while fetching user.";
        return;
    }

    // Validate old password to prevent unauthorized changes
    if (!$user || !password_verify($oldPassword, $user['password_hash'])) {

        // Increment failed attempts and notify user
        $_SESSION['failedPasswordChangeAttempts'] += 1;
        $error = "Incorrect old password , {$_SESSION['failedPasswordChangeAttempts']} attempts remaining.";

        // Update failed login attempt counter in DB
        dbIncreaseNumberFailedLoginAttempts($pdoMedium, $userId);

        // Auto-lock account after 3 bad attempts
        if ($_SESSION['failedPasswordChangeAttempts'] >= 3) {
            $error = "Too many failed attempts. Your account has been locked.";
            try {
                dbLockUserAccount($pdoMedium, $userId); // Locks account in DB
                logGeneralActions($logConcern, 'Account locked due too many failed change password attempts', $email, 'notice');
                logout($log, $logConcern); // Logs user out for safety
                header("Location: login.php"); // Redirects to login
                exit;
            } catch (PDOException $e) {
                logDbError($log, 'locking user account after failed change password attempts', $email, $e);
                $error = "Something went wrong";
                return;
            }
        } else {
            // Still below lock threshold
            $error = "The current password is wrong : attempt {$_SESSION['failedPasswordChangeAttempts']} of 3 before locking.";
            logUserPasswordChangeFailedAttempt($log,$_SESSION['user_id'],$_SERVER);
            return;
        }
    }

    // Reset failed attempts on correct old password
    $_SESSION['failedPasswordChangeAttempts'] = 0;
    dbResetFailedLoginAttempts($pdoMedium, $userId);

    // Ensure new password is not too similar to the old one
    similar_text($oldPassword, $newPassword, $percent);
    if ($percent > 70) {
        $error = "New password is too similar to the old one.";
        return;
    }

    // Update password in the database
    try {
        $updatedRows = dbUpdateUserPassword($pdoMedium, $newPassword, $userId);
        if ($updatedRows > 0) {
            $_SESSION['done'] = true;  // Mark process completed
            $success = true;
            $step = 'done';
            logUserPasswordChange($log,$_SESSION['user_id'],$_SERVER);
        }
    } catch (PDOException $e) {
       logDbError($log, 'updating password', $email, $e);
        $error = "Database error while updating password.";
    }
}

// === MAIN CONTROLLER ===
// Trigger password change handler on POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['old_password'], $_POST['new_password'], $_POST['confirm_password'])) {
        handleChangePassword($pdoLow, $pdoMedium, $log, $logConcern, $zxcvbn, $userId, $email, $step, $error, $success);
    } 
}

// If password change succeeded, force logout to re-authenticate
if (isset($_SESSION['done'])) {
    logout($log, $logConcern);
} else {
    $step = 'change'; // Otherwise, remain in password change form
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Change Password</title>
<link rel="stylesheet" href="../assets/css/change_password.css"> <!-- Page styling -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js"></script> <!-- Password strength library -->
<script src="../assets/js/change_password.js" defer></script> <!-- Front-end validation -->
<?php if ($step === 'done'): ?>
  <meta http-equiv="refresh" content="5;url=login.php"> <!-- Auto-redirect after completion -->
<?php endif; ?>
</head>
<body>
<div class="container">

  <?php if ($step === 'change'): ?>
    <h2>Change Your Password</h2>

    <!-- Display any errors safely -->
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <!-- Password change form -->
    <form method="post" class="change-form" novalidate>
      <label>Old Password:</label>
      <input type="password" name="old_password" required>

      <label>New Password:</label>
      <input type="password" name="new_password" id="password" required>

      <!-- Password strength visual feedback -->
      <div class="meter"><span id="password-strength-bar"></span></div>

      <!-- Real-time password requirement indicators -->
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
      <p><a href="user_dashboard.php">← Back to Dashboard</a></p>
    </div>

  <?php elseif ($step === 'done'): ?>
    <!-- Success state after password change -->
    <h2>Password Changed</h2>
    <?php if ($success): ?><p class="success-message">Your password was updated successfully.</p><?php endif; ?>
    <p class="redirect-info">Redirecting to login in 5 seconds...</p>
    <p><a href="login.php" class="login-now">Go to Login</a></p>
  <?php endif; ?>

</div>
</body>
</html>
