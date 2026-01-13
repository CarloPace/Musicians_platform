<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/zxcvbn.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../config/csp.php';
require_once __DIR__ . '/../../includes/utility.php';

$step = 'register';
$error = '';
$success = false;
//csrf token injection
csrf_protection_start($logConcern);

//handling multi-step procedure, forcing browser to always request the page, and not use the cached one
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");


$email = $_SESSION['email'] ?? '';
$username = $_SESSION['username'] ?? '';

// === STEP HANDLER FUNCTIONS ===

//This function after the submission of the form for registering set the session variable email_pending
function handleRegisterUser(PDO $pdoLow, PDO $pdoMedium,  $zxcvbn,  $log, $logConcern,string &$step, string &$error, bool &$success) {
    $email = sanitizeEmailInput($_POST['email'] ?? '');
    $username = sanitizeGeneralText($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    //Uniqueness Check for username
    try {
        if (dbCheckUsernameExistence($pdoLow, $username)) {
            $error = "The username '{$username}' is already taken. Please choose another one.";
            return;
        }
    } catch (PDOException $e) {

        logDBError($log, 'checking username existence', $email, $e);
        $error = "A system error occurred. Please try again later.";
        return;
    }
    
    if ($zxcvbn->passwordStrength($password)['score'] < 3) {
        $error = "Password too weak. ";
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
        return;
    }

    if ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
        return;
    }

    //checking if the email in the registration request is alredy 
    //used by a user
    if (dbCheckUserExistence($pdoLow, $email)) {

        $_SESSION['pending_email'] = $email;
        $success = true;

        if (dbCheckUserStatus($pdoLow, $email) === 'pending'){
            rotateSession();
            $_SESSION['pending_email'] = $email;
            $success = true;
            return;
        }

        //Case in which a user is trying to register with an alredy used email
        //redirecting to otp verify step, without sending email
        //but logging in concern and noticing the owner of the email
        $_SESSION['fake_resend_otp']=true;

        $warningMessage="Someone is trying to register, inside Musician Platform using your email";
        sendUserWarningMail($email,$warningMessage, $log);
        logSusActions($logConcern, $warningMessage, $email, null);
        return;
    }

    //case the email has not been used by anyone
    try {

        $result = dbRegisterNewUser($pdoMedium, $email, $username, $password);
        logGeneralActions($log, 'New pending user in the db', $email, 'info');
        if (!$result) {
            $error = "Something went wrong. Please try again.";
            return;
        }

        // Generate OTP and send email
        $expireTime = date('Y-m-d H:i:s', time() + 300);
        $otp = generateOtp($email, 'registration', $pdoMedium, $expireTime);
        sendOtpEmail($email, $otp, $log);
        logGeneralActions($log, 'Registration email sent', $email, 'info');
        rotateSession();
        $_SESSION['pending_email'] = $email;
        $success = true;
        
    } catch (PDOException $e) {
        logDBError($log, 'Database error during registration', $email, $e);
        $error = "Database error. Please try again later.";
    }
}

function handleResendOtp(PDO $pdoMedium, $log,string &$step, string &$error, bool &$success) {
   
    $email = $_SESSION['pending_email'] ?? '';
    $fakeStep = $_SESSION['fake_resend_otp'] ?? false;

    if (empty($email)) {      
        logGeneralActions($log, 'Session expired during OTP resend', $email, 'warning');
        $error = "Session expired. Please restart registration.";
        unset($_SESSION['pending_email']);
        return;
    }

    if(!$fakeStep){
        try {
            dbDeleteOtp($pdoMedium, 'registration', $email); // deleting old OTP
            $expireTime = date('Y-m-d H:i:s', time() + 300);
            $otp = generateOtp($email, 'registration', $pdoMedium, $expireTime);
            sendOtpEmail($email, $otp, $log);
            logGeneralActions($log, 'Otp resended', $email, 'notice');
            $success = true;
        } catch (Exception $e) {
            logDBError($log, 'Error resending OTP', $email, $e);
            $error = "Failed to resend OTP. Please try again.";
        }
        return;
    }
    //Fake steps
    $string1=sodium_bin2hex(random_bytes(6));
    $string2=sodium_bin2hex(random_bytes(6));
    hash_equals($string1,$string2);
}
//this function after veryfing the otp, set the session variable done
function handleVerifyOtp(PDO $pdoMedium, $log, $logConcern, string &$step, string &$error) {
    $otp = trim($_POST['otp'] ?? '');
    $email = $_SESSION['pending_email'] ?? '';
    
    if (empty($email)) {
        logGeneralActions($log, 'Session expired during OTP verification', $email, 'warning');
        $error = "Session expired. Please restart registration.";
        return;
    }
        // Initialize attempts if not set
    if (!isset($_SESSION['numberOfOtpAttempts'])) {
        $_SESSION['numberOfOtpAttempts'] = 0;
    }
    try {
        if (verifyOtp($email, 'registration', $otp, $pdoMedium)) {
            $_SESSION['numberOfOtpAttempts'] = 0;
            dbSetUserStatusActive($pdoMedium, $email);
            logGeneralActions($log, 'Pending user verified, now active', $email, 'notice');
            dbDeleteOtp($pdoMedium, $email, 'registration');
            unset($_SESSION['pending_email']);
            rotateSession();
            $_SESSION['done']=true;
        } else {
            $_SESSION['numberOfOtpAttempts']++;
            $error = "Invalid OTP. Please try again.";
            logSusActions($logConcern, 'Invalid OTP attempt during registration verification', $email, $otp);
            
            if ($_SESSION['numberOfOtpAttempts'] >= 3) {
                logSusActions($logConcern, 'Too many invalid OTP attempts during registration verification', $email, $otp);
                dbDeleteUser($pdoMedium, $email);
                 $error = "Too many invalid attempts. Session ended.";
                logout($log, $logConcern);
                header("Location: register.php"); //after 3 wrong otp, navigate back to register page again
                exit;
            }
        }
    } catch (PDOException $e) {
        logDBError($log, 'DB error verifying registration OTP, deleted pending user', $email, $e);
        dbDeleteUser($pdoMedium, $email);
        dbDeleteOtp($pdoMedium, $email, 'registration');
        $error = "A system error occurred. Please try again.";
        unset($_SESSION['pending_email']);
    }
}

// === MAIN CONTROLLER ===

//imposing consistency on the steps using the session variables

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['pending_email'],$_SESSION['done']) &&
        isset($_POST['email'], $_POST['username'], $_POST['password'], $_POST['confirm_password'])) {

        handleRegisterUser($pdoLow, $pdoMedium, $zxcvbn, $log,$logConcern, $step, $error, $success);

    } elseif (!isset($_SESSION['done'])&&isset($_SESSION['pending_email'])&&isset($_POST['resend_otp'])) {
        
        handleResendOtp($pdoMedium, $log,$step, $error, $success);

    } elseif (!isset($_SESSION['done'])&&isset($_SESSION['pending_email'])&&isset($_POST['otp'])) {

        handleVerifyOtp($pdoMedium, $log, $logConcern, $step, $error);

    } elseif (isset($_SESSION['done'])){
        $step = 'done';
    } else {
        $step = 'register';
    }

}

//intercept all the request to show always the correct step
if(isset($_SESSION['done'])){
    $step='done';
    logout($log, $logConcern);
}
elseif(isset($_SESSION['pending_email']))
    $step='verify';
else
    $step='register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register</title>
<link rel="stylesheet" href="../assets/css/register.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js"></script>
<script src="../assets/js/register.js" defer></script>
</head>
<body>
<div class="container">

  <?php if ($step === 'register'): ?>
    <h2>Create Your Account</h2>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <form method="post" name="signup_data" class="register-form">
    <p id="error_message" class="error hidden"></p>
      <label>Email:</label>
      <input type="email" name="email" placeholder="Enter your email" required value="<?= htmlspecialchars($email) ?>">

      <label>Username:</label>
      <input type="text" name="username" placeholder="Choose a username" required value="<?= htmlspecialchars($username) ?>">

      <label>Password:</label>
      <input type="password" name="password" id="password" placeholder="Enter your password" required>
      <div class="meter"><span id="password-strength-bar"></span></div>

      <div id="password-suggestions" class="password-suggestions">
        <ul>
          <li id="length-rule">Minimum 8 characters</li>
          <li id="uppercase-rule">At least 1 uppercase letter</li>
          <li id="number-rule">At least 1 number</li>
          <li id="special-rule">At least 1 special character (@#$%&*! etc.)</li>
        </ul>
      </div>

      <div class="feedback" id="password-feedback"></div>

      <label>Confirm Password:</label>
      <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter password" required>

      <button type="submit">Register</button>
      <p>Go back to <a href="login.php">Login</a></p>
    </form>

<?php elseif ($step === 'verify'): ?>
<div class="verify-container">
  <h2>Verify Your Email</h2>

  <?php if ($success): ?>
    <p class="otp-info">
      An OTP has been sent to <strong><?= htmlspecialchars($_SESSION['pending_email']) ?></strong>.
    </p>
  <?php endif; ?>

  <?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <form method="post" class="otp-form">
    <input type="text" name="otp" maxlength="8" class="otp-input" placeholder="••••••••" required>
    <button type="submit" class="verify-btn">Verify OTP</button>
  </form>
</div>

  <p class="resend-wrapper">
    E-mail not received?

    <form method="post" class="resend-wrapper-form">
      <input type="hidden" name="resend_otp" value="1">
      <button type="submit" class="resend-btn">Resend OTP</button>
    </form>

    <span id="resend-timer" class="cooldown-timer hidden"></span>
  </p>


  <?php elseif ($step === 'done'): ?>
     
    <?php
    // Redirect after 5 seconds
    header("refresh:5; url=login.php");
    ?>

    <h2>Registration Successful</h2>
    <p>Your account has been activated. You can now <a href="login.php">log in or simply wait a moment for autoredirection</a>.</p>
  <?php endif; ?>

</div>
</body>
</html>
