<?php
/**
 * -----------------------------------------------------------------------------
 * DATABASE QUERIES FILE
 * Handles CRUD operations for:
 *  - USERS (auth, profile, account)
 *  - ADMINS (auth, account)
 *  - MEDIA (uploads, edits, fetch)
 *  - OTP (reset password, email verification)
 * -----------------------------------------------------------------------------
 */
require_once __DIR__ . '/db.php';


// ============================================================================
//  USER AUTH & ACCOUNT MANAGEMENT
// ============================================================================

/**
 * Fetch full user row by email
 * @return array|false
 */
function dbCheckUserExistence($pdo, $email) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Check if a username already exists in the database.
 * @return array|false
 */
function dbCheckUsernameExistence(PDO $pdo, string $username) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Check user status (active, locked, pending, inactive)
 * @return string|false
 */
function dbCheckUserStatus($pdo, $email) {
    $stmt = $pdo->prepare("SELECT status FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
       $status = $stmt->fetchColumn();
    if ($status === '1') return 'pending';
    if ($status === '2') return 'active';
    if ($status === '3') return 'locked';
    return $status;
}
/**
 * Check if user has premium status
 * @return bool|false
 */
function dbCheckUserPremiumStatus($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT is_premium FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    return $stmt->fetchColumn();
}

/**
 * Reset failed login attempts after successful login
 * @return int affected rows
 */
function dbResetFailedLoginAttempts($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0 WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount();
}

/**
 * Increase failed login attempts after failed login
 * @return int affected rows
 */
function dbIncreaseNumberFailedLoginAttempts($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount();
}

/**
 * Lock user account after reaching limit
 * @return int affected rows
 */
function dbLockUserAccount($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE users SET status = 'locked' WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount();
}

/**
 * Register a new user (default status = pending until email verified)
 * @return int affected rows
 */
function dbRegisterNewUser($pdo, $email, $username, $password) {
    $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash, status, created_at)
        VALUES (:username, :email, :password_hash, 'pending', NOW())
    ");
    $stmt->execute([
        ':username'     => $username,
        ':email'        => $email,
        ':password_hash'=> $passwordHash
    ]);
    return $stmt->rowCount();
}

/**
 * Get user's current password hash
 */
function dbGetUserPassword($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    return $stmt->fetch();
}

/**
 * Update user password
 * @return int affected rows
 */
function dbUpdateUserPassword($pdo, $newPassword, $userId) {
    $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
    $stmt->execute([':hash' => $newHash, ':id' => $userId]);
    return $stmt->rowCount();
}

/**
 * Set user account active (after OTP validation)
 */
function dbSetUserStatusActive($pdo, $email) {
    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE email = :email");
    $stmt->execute([':email' => $email]);
    return $stmt->rowCount();
}

/**
 * Toggle premium for a user
 */
function dbSwitchPremiumStatusOfUser($pdo, $uid) {
    $stmt = $pdo->prepare("UPDATE users SET is_premium = NOT is_premium WHERE id = :uid");
    $stmt->execute([':uid' => $uid]);
    return $stmt->rowCount();
}

/**
 * Enable/Disable account and reset login attempts
 */
function dbSwitchAccountStatusOfUser($pdo, $uid) {
    $stmt = $pdo->prepare("
        UPDATE users
        SET status = IF(status = 'active', 'locked', 'active'),
            failed_login_attempts = 0
        WHERE id = :uid
    ");
    $stmt->execute([':uid' => $uid]);
    return $stmt->rowCount();
}

/**
 * Permanently delete user account
 */
function dbDeleteUser($pdo, $email) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
    return $stmt->execute([$email]);
}


// ============================================================================
//  ADMIN AUTH & ACCOUNT MANAGEMENT
// ============================================================================

function dbCheckAdminExistence($pdo, $email) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function dbCheckAdminStatus($pdo, $email) {
    $stmt = $pdo->prepare("SELECT status FROM admins WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $status = $stmt->fetchColumn();

    // Return as lowercase string for consistency
    return strtolower($status ?: '');
}

function dbGetAdminPassword($pdo, $adminId) {
    $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = :id");
    $stmt->execute([':id' => $adminId]);
    return $stmt->fetch();
}
function dbUpdateAdminPassword($pdo, $newPassword, $adminId) {
    $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare("UPDATE admins SET password_hash = :hash WHERE id = :id");
    $stmt->execute([':hash' => $newHash, ':id' => $adminId]);
    return $stmt->rowCount();
}
function dbRegisterNewAdmin($pdo, $email, $username, $password) {
    $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare("
        INSERT INTO admins (username, email, password_hash, status, created_at)
        VALUES (:username, :email, :password_hash, 'active', NOW())
    ");
    $stmt->execute([
        ':username'     => $username,
        ':email'        => $email,
        ':password_hash'=> $passwordHash
    ]);
    return $stmt->rowCount();
}
function dbLockAdminAccount($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE admins SET status = 'locked' WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount();
}

function dbResetAdminFailedLoginAttempts($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE admins SET failed_login_attempts = 0 WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount();
}

function dbIncreaseAdminFailedLoginAttempts($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE admins SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount();
}
function dbCheckAdminSecret($pdo, $secret) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE 2fa_secret = :secret LIMIT 1");
    $stmt->execute([':secret' => $secret]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function dbAddAdminSecret($pdo, $adminId, $secret) {
    $encryptionKey = base64_decode(file_get_contents('../../cert/secret.key'));
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $encrypted = sodium_crypto_secretbox($secret, $nonce, $encryptionKey);
    $encBase64 = base64_encode($encrypted);
    $nonceBase64 = base64_encode($nonce);
    $stmt = $pdo->prepare("UPDATE admins SET 2fa_secret = :secret, nonce = :nonce WHERE id = :id");
    $stmt->execute([':secret' => $encBase64, ':nonce' => $nonceBase64, ':id' => $adminId]);
    return $stmt->rowCount();
}

// ============================================================================
//  MEDIA QUERIES (Uploads & Fetch)
// ============================================================================

function dbFetchUserUploads($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM media WHERE user_id = :userId ORDER BY created_at DESC");
    $stmt->execute([':userId' => $userId]);
    return $stmt->fetchAll();
}

function dbFetchOthersUsersFreeUploads($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.username 
        FROM media m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.user_id != :userId AND m.is_premium = 0 
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([':userId' => $userId]);
    return $stmt->fetchAll();
}

function dbFetchOthersUsersPremiumUploads($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.username 
        FROM media m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.user_id != :userId AND m.is_premium = 1 
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([':userId' => $userId]);
    return $stmt->fetchAll();
}

function dbFetchMedia($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM media WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}


function dbFetchUserMedia($pdo, $mediaId, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM media WHERE id = :mediaId AND user_id = :userId");
    $stmt->execute([':mediaId' => $mediaId, ':userId' => $userId]);
    return $stmt->fetch();
}



/**
 * Add FULL MEDIA (Lyrics + Audio)
 */
function dbAddMedia(
    $pdo, $userId, $title, $audioFileName, $audioFileSize, $audioMimeType,
     $lyricsFileName, $lyricsFileSize,$lyricsMimeType,$isPremium
) {
    $stmt = $pdo->prepare("
        INSERT INTO media (
            user_id, title, audio_file_name, audio_file_size, audio_mime_type, lyrics_file_name,
            lyrics_file_size,lyrics_mime_type,is_premium,created_at,uploaded_at
        ) VALUES (
            :userId, :title, :audioFileName, :audioFileSize, :audioMimeType, :lyricsFileName,
            :lyricsFileSize, :lyricsMimeType,:isPremium, NOW(), NOW()
        )
    ");
    
    return $stmt->execute([
        ':userId' => $userId,
        ':title' => $title,
        ':audioFileName' => $audioFileName,
        ':audioFileSize' => $audioFileSize,
        ':audioMimeType' => $audioMimeType,
        ':lyricsFileName' => $lyricsFileName,
        ':lyricsFileSize' => $lyricsFileSize,
        ':lyricsMimeType' => $lyricsMimeType,
        ':isPremium' => $isPremium
    ]);
}

/**
 * Update FULL media content
 */
function dbUpdateUserMedia(
    $pdo, $userId,$mediaId,$title, $audioFileName, $audioFileSize, $audioMimeType,
     $lyricsFileName, $lyricsFileSize,$lyricsMimeType,$isPremium
) {
    $stmt = $pdo->prepare("
        UPDATE media 
        SET title = :title, audio_file_name = :audioFileName, audio_file_size = :audioFileSize,
            audio_mime_type = :audioMimeType, lyrics_file_name = :lyricsFileName,
            lyrics_file_size = :lyricsFileSize, lyrics_mime_type = :lyricsMimeType, is_premium = :isPremium, uploaded_at = NOW()
        WHERE id = :mediaId AND user_id = :userId
    ");
    $stmt->execute([
        ':title' => $title,
        ':audioFileName' => $audioFileName,
        ':audioFileSize' => $audioFileSize,
        ':audioMimeType' => $audioMimeType,
        ':lyricsFileName' => $lyricsFileName,
        ':lyricsFileSize' => $lyricsFileSize,
        ':lyricsMimeType' => $lyricsMimeType,
        ':isPremium' => $isPremium,
        ':mediaId' => $mediaId,
        ':userId' => $userId
    ]);
    return $stmt->rowCount();
}

/**
 * Delete any media
 */
function dbDeleteMedia($pdo, $mediaId) {
    $stmt = $pdo->prepare("DELETE FROM media WHERE id = :id");
    $stmt->execute([':id' => $mediaId]);
    return $stmt->rowCount();
}

/**
 * Admin: Fetch all users
 */
function dbFetchAllUsers($pdo) {
    return $pdo->query("SELECT id, username, email, is_premium, status FROM users ORDER BY id ASC")->fetchAll();
}

/**
 * Admin: Fetch all media + user info
 */
function dbFetchAllMediasAndInfo($pdo) {
    return $pdo->query("
        SELECT m.id, m.title, m.is_premium, m.created_at, u.username, u.email 
        FROM media m 
        JOIN users u ON m.user_id = u.id 
        ORDER BY m.created_at DESC
    ")->fetchAll();
}


// ============================================================================
//  OTP HANDLING (Reset Password / Email Verification)
// ============================================================================

/**
 * Generate a secure OTP with cooldown
 * - 1 OTP / 60 seconds limit
 * - Stores hashed OTP (bcrypt)
 * - Returns plaintext OTP to send by email
 */
function generateOtp($email, $purpose, $pdo, $expiresAt) {
    try {
        // 1. SPAM CHECK (Throttle)
        // We still check the TIME of the last OTP to prevent email spamming.
        // If the last one was sent < 60 seconds ago, we block the request.
        $stmt = $pdo->prepare("
            SELECT created_at 
            FROM otps 
            WHERE email = ? AND purpose = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$email, $purpose]);
        $lastCreatedAt = $stmt->fetchColumn();

        if ($lastCreatedAt && (time() - strtotime($lastCreatedAt)) < 59) {
            return null; // Too fast!
        }

        // 2. INVALIDATE OLD OTPs
        // Instead of checking if they are expired, we FORCE them to be consumed.
        // This ensures that as soon as a new code is requested, the old one dies.
        $stmt = $pdo->prepare("
            UPDATE otps 
            SET consumed = 1 
            WHERE email = ? AND purpose = ? AND consumed = 0
        ");
        $stmt->execute([$email, $purpose]);

        // 3. GENERATE & INSERT NEW OTP
        $otp = sodium_bin2hex(random_bytes(4));
        $otpHash = password_hash($otp, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO otps (email, otp_hash, purpose, created_at, expires_at, consumed)
            VALUES (?, ?, ?, NOW(), ?, 0)
        ");
        $stmt->execute([$email, $otpHash, $purpose, $expiresAt]);

        return $otp;

    } catch (Exception $e) {
        error_log('OTP generation error: ' . $email . ' :: ' . $e->getMessage());
        return null;
    }
}

/**
 * Verify OTP and mark as consumed
 */
function verifyOtp($email, $purpose, $inputOtp, $pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM otps 
        WHERE email = ? AND purpose = ? AND consumed = 0 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$email, $purpose]);
    $otpRow = $stmt->fetch();
    //if fetched and not expired
    if ($otpRow && strtotime($otpRow['expires_at']) > time()) {
        if (password_verify($inputOtp, $otpRow['otp_hash'])) {
            $pdo->prepare("UPDATE otps SET consumed = 1 WHERE id = ?")->execute([$otpRow['id']]);
            return true;
        }
        else return false;
    }
    return false;
}

/**
 * Mark OTP as consumed manually
 */
function dbSetConsumedOtp($pdo, $email, $purpose) {
    $stmt = $pdo->prepare("UPDATE otps 
                           SET consumed = 1 
                           WHERE email = :email 
                           AND purpose = :purpose 
                           AND expires_at < NOW()
                           AND consumed = 0");
                           
    $stmt->execute([':email' => $email, ':purpose' => $purpose]);
    
    return $stmt->rowCount(); // Returns the number of expired OTPs that were closed
}

/**
 * Delete OTP after password reset
 */
function dbDeleteOtp($pdo, $email, $purpose) {
    $stmt = $pdo->prepare("DELETE FROM otps WHERE email = :email AND purpose = :purpose");
    $stmt->execute([':email' => $email, ':purpose' => $purpose]);
    return $stmt->rowCount();
}

/**
 * Reset user password using email lookup (OTP success)
 */
function dbResetUserPassword($pdo, $email, $newPassword) {
    $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE email = :email");
    $stmt->execute([
        ':hash' => password_hash($newPassword, PASSWORD_ARGON2ID),
        ':email' => $email
    ]);
    return $stmt->rowCount();
}
?>
