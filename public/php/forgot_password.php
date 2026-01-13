<?php
// ----------------------
// REQUIRED INCLUDES
// ----------------------
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/zxcvbn.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../config/csp.php';
require_once __DIR__ . '/../../includes/utility.php';

// ----------------------
// INITIALIZATION
// ----------------------
$step = 'request'; // UI step: request -> verify -> reset -> done
$error = '';
$success = false;

// Start CSRF protection
csrf_protection_start($logConcern);

// ----------------------
// STEP HANDLER FUNCTIONS
// ----------------------

/**
 * Handle the initial request to send an OTP.
 */
function handleRequestOtp(PDO $pdoLow, PDO $pdoMedium, $log, $logConcern, string &$step, string &$error, bool &$success) {
    $email = sanitizeEmailInput($_POST['email'] ?? '');
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
        $step = 'request';
        return;
    }

    // Initialize OTP attempt counter
    $_SESSION['numberOfOtpAttempts'] = 0;

    // Check if user exists
    $user = dbCheckUserExistence($pdoLow, $email);

    if ($user) {
        $status = dbCheckUserStatus($pdoLow, $email);
        
        if ($status === 'locked') {
            logGeneralActions($logConcern, 'Password reset attempt for locked account', $email, 'warning');
            $error = "Your account is locked due to multiple failed login attempts.";
            return;
        }

        try {
            $purpose = 'password_reset';
            $expiresAt = date('Y-m-d H:i:s', time() + 600); // OTP valid for 10 mins
            $otp = generateOtp($email, $purpose, $pdoMedium, $expiresAt);

            if (!$otp) {
                $error = "Failed to generate OTP. Please try again.";
            }

            sendResetLinkEmail($email, $otp, $log); // send OTP email
            rotateSession(); // regenerate session ID
            $success = true;
            logGeneralActions($logConcern, 'Sent password reset OTP', $email, 'info');
            $_SESSION['email'] = $email;

        } catch (Exception $e) {
            logDbError($log, 'sending password reset OTP', $email, $e);
            $error = "Failed to send OTP. Please try again.";
        }

    } else {
        // No info leak: still show success to prevent account enumeration
        rotateSession();
        $success = true;
        $_SESSION['email'] = $email;
    }
}

/**
 * Verify the OTP entered by the user.
 */
function handleVerifyOtp(PDO $pdoLow, PDO $pdoMedium, $log,$logConcern, string &$step, string &$error, bool &$success) {
    $otp = trim($_POST['otp'] ?? '');
    $email = $_SESSION['email'] ?? '';
    $purpose = 'password_reset';

    if (empty($email)) {
        logGeneralActions($logConcern, 'Session expired during OTP verification', $email, 'warning');
        $error = "Session expired. Please restart.";
        unset($_SESSION['email']);
        header("Location: login.php");
        exit;
    }

    try {
        $user = dbCheckUserExistence($pdoMedium, $email);
    } catch (PDOException $e) {
        logDbError($log, 'checking user existence during OTP verification', $email, $e);
        $error = "A system error occurred. Please try again later.";
        return;
    }

    $id = $user['id'] ?? null;

    // Expire old OTPs
    try {
        dbSetConsumedOtp($pdoMedium, $email, $purpose);
    } catch(PDOException $e) {
        logDbError($log, 'removing expired OTPs', $email, $e);
        return;
    }

    try {
        $record = verifyOtp($email, $purpose, $otp, $pdoMedium);

        if (!$record) {


            $_SESSION['numberOfOtpAttempts'] = (int)($_SESSION['numberOfOtpAttempts'] ?? 0) + 1;
            logSusActions($logConcern, 'Invalid OTP attempt', $email, $otp);        

            $warningMessage = "Someone is trying to reset the password for this account with invalid OTPs.";
            sendUserWarningMail($email, $warningMessage, $log);

                
            dbDeleteOtp($pdoMedium, $email, $purpose);
            logout($log, $logConcern);
            header("Location: login.php");
            exit;

        } else {
            dbDeleteOtp($pdoMedium, $email, $purpose);
            rotateSession(); // regenerate session ID
            $success = true;
            $_SESSION['otpVerified'] = true;
            $_SESSION['numberOfOtpAttempts'] = 0;
        }

    } catch (PDOException $e) {
        logDbError($log, 'verifying OTP during password reset', $email, $e);
        $error = "A system error occurred. Please try again later.";
    }
}

/**
 * Handle password reset after OTP verification.
 */
function handleResetPassword(PDO $pdoLow, PDO $pdoMedium, $log, $logConcern, $zxcvbn, string &$step, string &$error, bool &$success) {
    $email = $_SESSION['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $purpose = 'password_reset';

    if (empty($email)) {
        logGeneralActions($logConcern, 'Session expired during password reset', $email, 'warning');
        $error = "Session expired. Please restart the password reset process.";
        unset($_SESSION['email'], $_SESSION['otpVerified']);
        header("Location: login.php");
        exit;
    }

    try {
        if ($password !== $confirm) {
            $error = "Passwords do not match.";
        } elseif ($zxcvbn->passwordStrength($password)['score'] < 3) {
            $error = "Password too weak. Please use a stronger one.";
        } else {
            dbResetUserPassword($pdoMedium, $email, $password); // securely hash password
            
            logUserPasswordReset($log,$email,$_SERVER);

            unset($_SESSION['email'], $_SESSION['otpVerified'], $_SESSION['numberOfOtpAttempts']);

            // Rotate session after sensitive action
            rotateSession();
            $_SESSION['done'] = true;
            $success = true;
        }
    } catch (PDOException $e) {
        logDbError($log, 'resetting password', $email, $e);
        $error = "A system error occurred. Please try again later.";
    }
}

// ----------------------
// MAIN CONTROLLER
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email']) && !isset($_POST['otp']) && !isset($_POST['password'])) {
        handleRequestOtp($pdoLow, $pdoMedium, $log, $logConcern, $step, $error, $success);
    } elseif (isset($_POST['otp'])) {
        handleVerifyOtp($pdoLow, $pdoMedium, $log, $logConcern, $step, $error, $success);
    } elseif (isset($_POST['password'], $_POST['confirm_password'])) {
        handleResetPassword($pdoLow, $pdoMedium, $log, $logConcern, $zxcvbn, $step, $error, $success);
    }
}

// ----------------------
// DETERMINE CURRENT STEP
// ----------------------
if (isset($_SESSION['email']) && !isset($_SESSION['otpVerified'])) {
    $step = 'verify';
} elseif (isset($_SESSION['otpVerified'])) {
    $step = 'reset';
} elseif (isset($_SESSION['done'])) {
    $step = 'done';
    logout($log, $logConcern);
} else {
    $step = 'request';
}
?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Password Reset</title>
    <link rel="stylesheet" href="../assets/css/forgot_password.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js"></script>
    <script src="../assets/js/forgot_password.js" defer></script>
    </head>
    <body>
    <div class="container">

        <?php if ($step === 'request'): ?>
        <h2>Forgot Password</h2>
        <p>Enter your email to receive a one-time password (OTP).</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="email" name="email" placeholder="Enter your email" required>
            <button type="submit">Send OTP</button>
        </form>

        <?php elseif ($step === 'verify'): ?>
        <h2>Verify OTP</h2>
        <p>Check your email and enter the OTP you received.</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success-message">If the email exists in our system, you will receive an OTP shortly</div><?php endif; ?>
        <form method="post">
            <input type="text" name="otp" placeholder="Enter OTP" maxlength="8" required>
            <button type="submit">Verify OTP</button>
        </form>

    <?php elseif ($step === 'reset'): ?>
    <h2>Reset Password</h2>
    <p>Enter your new password below.</p>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
        <label for="password">New Password:</label>
        <input type="password" name="password" id="password" placeholder="New password" required>

        <div class="meter"><span id="password-strength-bar"></span></div>

        <!-- Suggestions / Rule checklist -->
        <div id="password-suggestions" class="password-suggestions">
        <ul>
            <li id="length-rule">Minimum 8 characters</li>
            <li id="uppercase-rule">At least 1 uppercase letter</li>
            <li id="number-rule">At least 1 number</li>
            <li id="special-rule">At least 1 special character (@#$%&*! etc.)</li>
        </ul>
        </div>

        <div class="feedback" id="password-feedback"></div>

        <label for="confirm_password">Confirm Password:</label>
        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" required>

        <button type="submit">Change Password</button>
    </form>
    <?php elseif ($step === 'done'): ?>

        <h2>Password Reset Successful</h2>
        <p>Your password has been updated. You can now <a href="login.php">log in</a>.</p>
        <?php endif; ?>

    </div>
    </body>
    </html>
