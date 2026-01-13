<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../../config/csp.php';

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
// ================================
// INITIALIZATION
// ================================
$step = 'login';
$message = '';
$success = false;

if(isset($_SESSION['user_id']))
    header('Location: user_dashboard.php');

csrf_protection_start($logConcern);

// Cancel 2FA
if (isset($_GET['cancel'])) {
   logout($log, $logConcern);
   header('Location: login.php');
   exit;
}

// ================================
// UTILITY FUNCTIONS
// ================================

function handleFailedAttempt(PDO $pdo, $log, $account, $isAdmin) {
    $increaseFn = $isAdmin ? 'dbIncreaseAdminFailedLoginAttempts' : 'dbIncreaseNumberFailedLoginAttempts';
    try { $increaseFn($pdo, $account['id']); } 
    catch (\PDOException $e) { logDBError($log, 'increasing failed attempts', $account['id'], $e); }
}

function lockAccount(PDO $pdo,$log,$logAdmin,$account,$isAdmin) {
    $lockFn = $isAdmin ? 'dbLockAdminAccount' : 'dbLockUserAccount';
    try { 
        $lockFn($pdo, $account['id']);
        if($isAdmin){
            logAdminLocked($logAdmin,$account['id']);
        }
        else
        {
            logUserLocked($log,$account['id'],$_SERVER);
        }

    } catch (\PDOException $e) { 
        logDBError($log, 'locking account', $account['id'], $e); 
    }
}

function resetFailedAttempts(PDO $pdo, $log, $account, $isAdmin) {
    $resetFn = $isAdmin ? 'dbResetAdminFailedLoginAttempts' : 'dbResetFailedLoginAttempts';
    try { $resetFn($pdo, $account['id']); } 
    catch (\PDOException $e) { logDBError($log, 'resetting failed attempts', $account['id'], $e); }
}

function createQRCodeForAdmin2FA() {
   
    $tfa = new TwoFactorAuth(new EndroidQrCodeProvider(), 'Musicians Platform Admins', 6,30);
    $secret = $tfa->createSecret();
    
    $_SESSION['tfa_new_secret'] = $secret;

    return $tfa->getQRCodeImageAsDataUri('Admin', $secret);
}

// ================================
// STEP FUNCTIONS
// ================================
function attemptAdminLogin($pdo, $log,$logConcern, $logAdmin, $email, $password, string &$step, string &$message) {
    try { 
        $admin = dbCheckAdminExistence($pdo, $email); 
    } catch (\PDOException $e) { 
        logDBError($log,'checking admin existence',$email, $e);
        $message = "Login error. Try again.";
        return;
    }

    if (!$admin) {
        $_SESSION['notAdmin'] = true;
        return;
    }
    
    if ($admin['status'] === 'locked') {
        $message = "Invalid email or password.";
        logLockedAdminLoginAttempt($logAdmin,$admin['id']);
        //send email to admin
        $notification="Your account is blocked currently, contact support";
        sendUserWarningMail($email,$notification, $logAdmin);
        return;
    }
    
    if (!password_verify($password, $admin['password_hash'])) {
        handleFailedAttempt($pdo, $log, $admin, true);
        $attempts = $admin['failed_login_attempts'] + 1;
        if($attempts >= 3){
            lockAccount($pdo, $log,$logAdmin,$admin, true);
            $message = "Invalid email or password.";
            $notification="Your account has been blocked due too many failed logins attempt, contact support";
            sendUserWarningMail($email,$notification, $logAdmin);
            logout($log, $logConcern);
            return;
        }
        $message = "Invalid email or password.";
        logAdminFailedLoginAttempt($logAdmin,$admin['id']);
        //$step = 'login';
        return;
    }

    /*if ($admin['status'] !== 'active') {
        $message = "Admin account disabled. Contact support.";
        logLockedAdminLoginAttempt($logAdmin,$admin['id']);
        return;
    }*/
    
    resetFailedAttempts($pdo, $log, $admin, true);
    rotateSession();
    $has_secret = !empty($admin['2fa_secret']);
    if (!$has_secret) {
        $_SESSION['admin_pending'] = $admin['email'];
        $_SESSION['secret_setup'] = true;
        
    } else {
        $_SESSION['admin_pending'] = $admin['email'];
    }
}

function attemptUserLogin($pdoMedium, $log,$logConcern,$logAdmin, $email, $password, string &$step, string &$message) {
    try { 
        $user = dbCheckUserExistence($pdoMedium, $email); 
    } catch (\PDOException $e) { 
        logDbError($log,'checking user existence',$email, $e);
        $message = "Login error. Try again.";
        return;
    }

    //User not found
    if (!$user) { 
        logGeneralActions($log, 'Unknown login attempt', $email, 'info'); 
        $message = "Invalid email or password.";
        return;
    }
    
    //Found user check password
    if (!password_verify($password,$user['password_hash'])) {
        handleFailedAttempt($pdoMedium,$log,$user,false);
        $attempts = $user['failed_login_attempts']+1;
        if($attempts >= 3){
            lockAccount($pdoMedium,$log,$logAdmin,$user,false);
            $message = "Invalid email or password.";
            $notification="Your accound has been blocked due to many failed login attempts, contact support";
            sendUserWarningMail($email,$notification, $log);
            logout($log, $logConcern);
            //exit;
            return;
        }
        $message = "Invalid email or password.";
        logFailedUserLoginAttempt($log,$user['id'],$_SERVER);
        return;
    }

    if ($user['status']!=='active') {
        $message = "Invalid email or password.";
        logBlockedUserLoginAttempt($log,$user['id'],$_SERVER);
        return;
    }

    resetFailedAttempts($pdoMedium,$log,$user,false);
    rotateSession();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['is_admin'] = false;
    $_SESSION['is_premium'] = (bool)$user['is_premium'];
    $_SESSION['userOK'] = true;
   
    logUserLogin($log,$_SESSION['user_id'],$_SERVER);
    
    header('Location: user_dashboard.php');
    exit;
}

function finalizeAdminSecret($pdo, $log,$logAdmin, $email, $inputCode, string &$step, string &$message) {
    

    if (!isset($_SESSION['tfa_new_secret'])) {
        $message = "Error generating the QR code, try again";
        unset($_SESSION['secret_setup']);
        unset($_SESSION['admin_pending']);
        return;
    }

    $secret = $_SESSION['tfa_new_secret'];
    $tfa = new TwoFactorAuth(new EndroidQrCodeProvider(), 'Musicians Platform Admins', 6, 30);

    if (!$tfa->verifyCode($secret, $inputCode)) {
        $message = "Invalid authentication code. Scan the QR code again and enter the correct code.";
        return;
    }
    // Save secret permanently
    try {
        $admin = dbCheckAdminExistence($pdo, $email);
        dbAddAdminSecret($pdo, $admin['id'], $secret);
    } catch (\PDOException $e) {
        logDBError($log, 'storing admin 2FA secret', $email, $e);
        $message = "Error storing secret.";
        return;
    }
    unset($_SESSION['secret_setup']);
    unset($_SESSION['admin_pending']);

    logAdminFinalized2FA($logAdmin,$admin['id']);

    rotateSession();
     
    $success = true;
    header("Location: login.php");
    
}

function verifyAdmin2FA($pdo, $log, $logAdmin, $inputCode, string &$step, string &$message) {

    if (!isset($_SESSION['admin_pending'])) {
        $message = "Session expired. Login again.";
        header("Location: login.php");
        return;
    }
    
    $email = $_SESSION['admin_pending'];
    try { 
        $admin = dbCheckAdminExistence($pdo, $email); 
    } catch (\PDOException $e) {
        logDBError($log, 'checking admin existence', $email, $e);
        $message = "Error. Try again.";
        logout($log, $logAdmin);
        exit;
    }
    
    // Initialize attempts if not set
    if (!isset($_SESSION['numberOfOtpAttempts'])) {
        $_SESSION['numberOfOtpAttempts'] = 0;
    }
    
   
    $tfa = new TwoFactorAuth(new EndroidQrCodeProvider(), 'Musicians Platform Admins', 6, 30);
    $encryptedSecret = base64_decode($admin['2fa_secret']);
    $nonce = base64_decode($admin['nonce']);
    $encryptionKey = base64_decode(file_get_contents('../../cert/secret.key'));
    $decryptedSecret = sodium_crypto_secretbox_open($encryptedSecret, $nonce, $encryptionKey);

    if ($tfa->verifyCode($decryptedSecret, $inputCode) === false) {
        $_SESSION['numberOfOtpAttempts']++;
        logSusActions($logAdmin, 'Invalid TOTP attempt', $email, $inputCode);
        $message = "Invalid authentication code.";
        
        
        if ($_SESSION['numberOfOtpAttempts'] >= 3) {
            logSusActions($logAdmin, 'Too many invalid TOTP attempts', $email, $inputCode);
            $_SESSION['numberOfOtpAttempts'] = 0;
            $warningMessage = "Someone is trying to login to your admin account but failed the 2FA multiple times. If this wasn't you, please change your password immediately.";
            sendUserWarningMail($email, $warningMessage, $log);
            dbLockAdminAccount($pdo, $admin['id']);
            $message = "Too many invalid TOTP attempts. Please try again later.";
            logout($log, $logAdmin);
            
        }
        return;
    }
    
    $message = "2FA successful.";

    $_SESSION['numberOfOtpAttempts'] = 0;

    rotateSession();

    unset($_SESSION['admin_pending']);

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['username'] = $admin['username'];
    $_SESSION['email'] = $admin['email'];
    $_SESSION['is_admin'] = true;
    $_SESSION['adminOK'] = true;
    
    logAdminLogin($logAdmin,$admin['id'],$_SERVER);

    header('Location: admin_dashboard.php');
    exit;
}

// ================================
// HANDLE POST
// ================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // -------------------------
    // 1 — Admin / User login
    // -------------------------
    if (isset($_POST['email'], $_POST['password']) && !isset($_SESSION['admin_pending'])) {

        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // Attempt admin login
        attemptAdminLogin($pdo, $log,$logConcern, $logAdmin, $email, $password, $step, $message);

        // If not admin, try user login
        if (!empty($_SESSION['notAdmin'])) {
            attemptUserLogin($pdoMedium, $log,$logAdmin,$logConcern, $email, $password, $step, $message);
        }
    }

    // -------------------------
    // 2 — First-time 2FA setup
    // -------------------------
    elseif (isset($_POST['first_totp']) && !empty($_SESSION['admin_pending']) && !empty($_SESSION['tfa_new_secret'])) {

        $code = trim($_POST['first_totp']);
        finalizeAdminSecret($pdo, $log,$logAdmin, $_SESSION['admin_pending'], $code, $step, $message);
    }

    // -------------------------
    // 3 — Admin 2FA verification
    // -------------------------
    elseif (isset($_POST['totp']) && !empty($_SESSION['admin_pending'])) {

        verifyAdmin2FA($pdo, $log, $logAdmin, $_POST['totp'], $step, $message);
    }
}

// ================================
// INTERCEPT ALL REQUESTS TO SHOW ALWAYS THE CORRECT STEP
// ================================

    if (!empty($_SESSION['secret_setup'])) {
        $step = 'secret';
        $QrCode = createQRCodeForAdmin2FA();
    } elseif (!empty($_SESSION['admin_pending'])) {
        $step = '2fa';
    } else {
        $step = 'login';
    }



?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
<link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
<div class="container">

<?php if ($step==='login'): ?>
    <h2>Login</h2>
    <?php if (!empty($message)): ?><p class="error message"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <form method="post">
      <label>Email:</label>
      <input type="email" name="email" required>
      <label>Password:</label>
      <input type="password" name="password" required>
      <button type="submit">Login</button>
    </form>
    <div class="auth-links">
        <a href="forgot_password.php">Forgot Password?</a>
    </div>
    <div class="auth-links">
        <a href="register.php">Don't you have an account? Create an Account</a>
    </div>

<?php elseif ($step==='secret'): ?>
    <h2>Set up Two-Factor Authentication</h2>
    <?php if (!empty($message)): ?><p class="error message"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<div class="qr">
    <p>Scan the QR code with Google Authenticator: </p>
    <img src="<?= htmlspecialchars($QrCode) ?>" alt="QR Code for 2FA setup" >
    <p>Then enter the first code generated:</p>
    <?php if ($success): ?><div class="success-message">2FA setup complete. Please login again.</div><?php endif; ?>
</div>
    

    <form method="post">
        <input type="text" name="first_totp" maxlength="6" required>
        <button type="submit">Verify</button>
    </form>

    <p class="auth-links"><a href="login.php?cancel=1">Cancel</a></p>

<?php elseif ($step==='2fa'): ?>
    <h2>Admin Authentication</h2>

    <?php if (!empty($message)): ?>
        <p class="error message"><?= htmlspecialchars($message) ?></p>
    <?php else: ?>
        <p class="info">Enter your authentication code.</p>
    <?php endif; ?>

    <!-- MAIN VERIFY FORM -->
    <form method="post" id="verify-form">
        <label>2FA Code:</label>
        <input type="text" name="totp" id="totp-input" maxlength="6" pattern="\d{6}" required>
        <button type="submit">Verify</button>
    </form>
    <p class="auth-links"><a href="login.php?cancel=1">Cancel</a></p>

<?php endif; ?>
</div>
</body>
</html>
