<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../config/csp.php';

requireAdminLogin($pdo, $logAdmin, $logConcern); // Ensure only authenticated admins can access this endpoint

// Start CSRF protection and validate incoming token
csrf_protection_start($logAdmin);

// Store media ID from POST into a session variable to prevent URL tampering
if (empty($_POST['media_id'])) {
    logAdminActions($logAdmin, 'missing media ID for deletion', $_SESSION['admin_id'], 'warning', null);
    header("Location: admin_dashboard.php");
    exit;
}

// If no ID is present (invalid request or refresh), log and redirect safely


$id = (int) $_POST['media_id'];

// Resolve base upload directories (prevents path traversal attacks)
$baseAudio = realpath(__DIR__ . '/../../uploads/audio/');
$baseLyrics = realpath(__DIR__ . '/../../uploads/lyrics/');

try {
    // Fetch media info (file names, etc.)
    $media = dbFetchMedia($pdoLow, $id);
} catch (PDOException $e) {
    // Log DB failure and redirect admin safely
    logAdminActions($logAdmin, 'DB error fetching media for deletion', $_SESSION['admin_id'], 'error', ['mediaId' => $id]);
    header("Location: admin_dashboard.php");
    exit;
}
if (!$media) {
    logAdminActions($logAdmin, 'media not found for deletion', $_SESSION['admin_id'], 'warning', ['mediaId' => $id]);
    header("Location: admin_dashboard.php");
    exit;
}

// Resolve full file paths for audio and lyrics
$pathAudio = realpath($baseAudio . '/' . $media['audio_file_name']);
$pathLyrics = realpath($baseLyrics . '/' . $media['lyrics_file_name']);

try {
    // Attempt database deletion
    $deletedRows = dbDeleteMedia($pdoMedium, $id);

    if ($deletedRows > 0) {
        // Log successful DB deletion
        logAdminActions($logAdmin, 'media deleted successfully', $_SESSION['admin_id'], 'notice', ['mediaId' => $id]);

        // Delete audio file ONLY if its resolved path is valid and inside the correct directory
        if ($pathAudio !== false && strpos($pathAudio, $baseAudio) === 0 && file_exists($pathAudio)) {
            unlink($pathAudio);
        }
        else {
            // Log missing or invalid audio file path
            logAdminActions($logAdmin, 'audio file not found for deletion on server', $_SESSION['admin_id'], 'warning', ['mediaId' => $id]);
        }

        // Delete lyrics file with same validation
        if ($pathLyrics !== false && strpos($pathLyrics, $baseLyrics) === 0 && file_exists($pathLyrics)) {
            unlink($pathLyrics);
        }
        else {
            // Log missing or invalid lyrics file path
            logAdminActions($logAdmin, 'lyrics file not found for deletion on server', $_SESSION['admin_id'], 'warning', ['mediaId' => $id]);
        }
    } else {
        // If DB deletion returned 0, media didn't exist or admin lacked permissions
        logAdminActions($logAdmin, 'failed to delete media (not found or no permission)', $_SESSION['admin_id'], 'error', ['mediaId' => $id]);
    }

} catch (PDOException $e) {
    // Log database deletion failure
    logAdminActions($logAdmin, 'DB error deleting media', $_SESSION['admin_id'], 'error', ['mediaId' => $id]);
}

// Redirect admin back to dashboard
header("Location: admin_dashboard.php");
exit;
