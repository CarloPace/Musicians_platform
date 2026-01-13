<?php
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Loads PHPMailer and dependencies via Composer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;



/**
 * Sends a short-lived OTP code to the user’s email.
 * - Configures SMTP using environment variables
 * - Builds a simple HTML email containing the OTP
 * - Returns true on success, false on failure
 */
function sendOtpEmail($toEmail, $otp, $log) {
    $mail = new PHPMailer(true);

    try {
        // SMTP server setup
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com'; // Default to Gmail if not configured
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Secure connection
        $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;

        // Sender and recipient information
        $mail->setFrom($_ENV['SMTP_USER'], 'Musicians Portal');
        $mail->addAddress($toEmail);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = "Your OTP Code";
        $mail->Body    = "<p>Your OTP is <b>{$otp}</b>. It will expire in 5 minutes.</p>";

        // Attempt to send
        $mail->send();
        return true;

    } catch (Exception $e) {
        // Log the error without exposing sensitive details
        logDbError($log, 'error generating e-mail for otp', $mail, $e);
        return false;
    }
}

/**
 * Sends a password-reset OTP to the user.
 * - Uses same SMTP configuration pattern
 * - Sanitizes the OTP to prevent HTML injection
 * - Intended for account recovery workflows
 */
function sendResetLinkEmail(string $email, string $otp, $log): bool {
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;

        // Recipients
        $mail->setFrom($_ENV['SMTP_USER'], 'Musicians Portal');
        $mail->addAddress($email);

        // Prevent output injection in the email body
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Email content
        $mail->isHTML(true);
        $mail->Subject = "Password Reset Request";
        $mail->Body    = "<p>Your OTP is <b>{$safeOtp}</b>. It will expire in 10 minutes.</p>";

        // Send email
        $mail->send();
        return true;

    } catch (Exception $e) {
        logDbError($log, 'error generating resend e-mail for otp', $mail, $e);
        return false;
    }
}

/**
 * Sends a custom warning message to a user.
 * - Used when an admin needs to notify a user about rule violations or concerns
 * - Sends a simple HTML message without OTP logic
 */
function sendUserWarningMail(string $email, string $Warning, $log): bool {
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;

        // Sender/recipient
        $mail->setFrom($_ENV['SMTP_USER'], 'Musicians Portal');
        $mail->addAddress($email);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = "Warning";
        $mail->Body    = "<p>{$Warning}</p>";

        // Send email
        $mail->send();
        return true;

    } catch (Exception $e) {
        logDbError($log, 'error generating warning e-mail', $mail, $e);
        return false;
    }
}

