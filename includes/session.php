<?php
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/queries.php';

/**
 * ---------------------------------------------------------
 * CONFIGURATION & CONSTANTS
 * ---------------------------------------------------------
 */
define('SESSION_LIFETIME', 900);        // 15 Minutes: Max duration of inactivity
define('SESSION_REGEN_GRACE', 10);      // 10 Seconds: Validity window for obsolete IDs (prevents race conditions)

/**
 * ---------------------------------------------------------
 * SESSION INITIALIZATION
 * ---------------------------------------------------------
 * Configures garbage collection and cookie parameters before starting the session.
 */
if (session_status() === PHP_SESSION_NONE) {
    // Configures the garbage collector to treat files older than the lifetime as expired
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    
    // Defines the probability (1/1) of the garbage collector running on initialization
    // with this setting, the gc will delete all the files considered "garbage" every time the server receive a request
    // This can be done in testing mode but not efficient in real scenario
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 1);

    // Sets secure cookie parameters
    session_set_cookie_params([
        'lifetime' => 0,             // Cookie expires when the browser closes
        'path' => '/',
        'domain' => '',              // Restricts to the current domain
        'secure' => true,            // Transmit only over HTTPS
        'httponly' => true,          // Restricts JavaScript access
        'samesite' => 'Strict'       // Prevents cross-site request forgery
    ]);

    session_start();
}

/**
 * ---------------------------------------------------------
 * GLOBAL SESSION MIDDLEWARE
 * ---------------------------------------------------------
 * Executes on every request to validate state, update timestamps, and enforce rotation.
 */

// 1. Validate session integrity and timeout
if (!checkSessionValidity()) {
    logout($log, $logConcern);
}

// 2. Refresh the activity timestamp for the current session
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
}

/**
 * ---------------------------------------------------------
 * CORE SESSION FUNCTIONS
 * ---------------------------------------------------------
 */

/**
 * Verifies if the current session is valid based on timestamps and obsolescence flags.
 * * @return bool True if valid, False if expired or marked as obsolete.
 */
function checkSessionValidity(): bool {
    
    // Check 1: Obsolescence flag (for sessions that have been rotated)
    if (isset($_SESSION['destroyed_at'])) {
        // Allow access only within the defined grace period
        if (time() - $_SESSION['destroyed_at'] < SESSION_REGEN_GRACE) {
            return true;
        }
        return false;
    }

    // Check 2: Inactivity timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        return false;
    }

    return true;
}

/**
 * Regenerates the session ID while preserving data.
 * Implements a "Time Bomb" mechanism to safely handle network race conditions.
 * * Usage: Call during login, privilege elevation, or periodic rotation.
 */
function rotateSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        
        // 1. Mark the current session as obsolete by setting a destruction timestamp
        $_SESSION['destroyed_at'] = time();

        // 2. Commit the current session (with the flag) and generate a new ID
        // The old session file remains on disk to satisfy requests during the grace period
        session_regenerate_id(false);

        // 3. Remove the obsolescence flag from the newly generated session ID
        unset($_SESSION['destroyed_at']);
        
        // 4. Reset the rotation timer
        $_SESSION['created_at'] = time();
    }
}

function logout($log, $logAdmin): void {

    if(isset($_SESSION['admin_id']))
        logAdminLogout($logAdmin,$_SESSION['admin_id'],$_SERVER);
    elseif(isset($_SESSION['user_id']))
        logUserLogout($log,$_SESSION['user_id'],$_SERVER);

    // Wipe session data
    $_SESSION = [];

    // Remove cookie manually
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p["path"], $p["domain"], $p["secure"], $p["httponly"]
        );
    }

    // DELETE session file on disk
    session_destroy();
}

/**
 * ---------------------------------------------------------
 *  USER AUTHENTICATION CHECK
 * ---------------------------------------------------------
 * Confirms:
 * 1. Session contains user_id + email
 * 2. User exists in DB
 * 3. User status = active
 * 4. (Optional) premium status loaded
 */
function isLoggedIn(PDO $pdoLow, $log, $logConcern): bool {

    if (!isset($_SESSION['user_id']) && !isset($_SESSION['email'])) {
        return false;
    }

    $email = $_SESSION['email'];

    try {
       $user = dbCheckUserExistence($pdoLow, $email);

       if (!$user) {
           logGeneralActions($log, 'User not found in database', $email, 'warning');
           return false;
       } else {
           
           if($user['status'] === 'active'){
            //if user is logged in and active set status of account and eventually priviledge
            $_SESSION['status']=$user['status'];
            $_SESSION['is_premium'] = ($user['is_premium']===1);
            return true;
           }
           return false;
       }

    } catch (PDOException $e) {
        logDbError($log, 'checking user status', $email, $e);
        return false;
    }
}

/**
 * ---------------------------------------------------------
 *  ADMIN AUTHENTICATION CHECK
 * ---------------------------------------------------------
 * Same logic as isLoggedIn() but for admins.
 */
function isAdminLoggedIn(PDO $pdo, $logAdmin, $logConcern): bool {

    if (!isset($_SESSION['email']) && !isset($_SESSION['admin_id'])) {
        logAdminActions($logAdmin, 'Admin not logged in', 'Unknown', 'info', null);
        return false;
    }

    $email = $_SESSION['email'];

    try {
        $admin = dbCheckAdminExistence($pdo, $email);
        if (!$admin) {
            logAdminActions($logAdmin, 'Admin not found in database', $email, 'warning', null);
            return false;
        } else {
            return $admin['status'] === 'active';
        }

    } catch (PDOException $e) {
        logAdminActions($logAdmin, 'checking admin status', $_SESSION['admin_id'] ?? 'Unknown', 'warning', null);
        return false;
    }
}

/**
 * ---------------------------------------------------------
 *  ACCESS CONTROL FOR USER PAGES
 * ---------------------------------------------------------
 * 1. Check inactivity timeout
 * 2. Check login state
 * 3. Refresh timestamp
 */
function requireLogin(PDO $pdoLow, $log, $logConcern): void {
    if (!isLoggedIn($pdoLow, $log, $logConcern)) {
        
        logSusActions($logConcern, 'Unauthorized access attempt to user page',
            $_SESSION['user_id'] ?? 'Not logged in', null);
        header("Location: login.php");
        exit;
    }
}

/**
 * ---------------------------------------------------------
 *  ACCESS CONTROL FOR ADMIN PAGES
 * ---------------------------------------------------------
 */
function requireAdminLogin(PDO $pdo, $logAdmin, $logConcern): void {

    if (!isAdminLoggedIn($pdo, $logAdmin, $logConcern)) {
       
        logSusActions($logConcern, 'Unauthorized access attempt to admin page',
            $_SESSION['admin_id'] ?? 'Not logged in', null);

        logout($logAdmin, $logConcern);
        header("Location: login.php");
        exit;
    }

}
?>
