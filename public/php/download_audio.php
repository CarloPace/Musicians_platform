<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../config/csp.php';

requireLogin($pdoLow, $log, $logConcern); // Ensure only authenticated users can access this file download route

// Start CSRF protection for the request
read_only_requests_csrf_protection_start($logConcern);

// Save media ID from POST only when the user clicks the "Download" button
// Prevents ID tampering through URL modification
if (!isset($_POST['media_id'])) {
     logGeneralActions($log, 'missing media ID for download', $_SESSION['user_id'], 'warning');
    header("Location: user_dashboard.php");
    exit;
}

$mediaId = (int) $_POST['media_id'];

logDownloadAudioRequest($log,$mediaId,$_SESSION['user_id'],$_SERVER);

// Fetch media details from DB
try {
    $media = dbFetchMedia($pdoLow, $mediaId);
} catch (PDOException $e) {
    logDbError($log, 'fetching media for download', $_SESSION['user_id'], $e);
    header("Location: user_dashboard.php");
    exit;
}

// If DB returns nothing, the audio doesn't exist anymore
if (!$media) {
    die("Audio not found");
}

// Enforce access control:
// - If the media is premium, user must be premium OR the uploader
if ($media['is_premium']) {
    if (!$_SESSION['is_premium'] && $media['user_id'] != $_SESSION['user_id']) {
        logGeneralActions($logConcern, "Unauthorized download attempt", $_SESSION['user_id'], "warning");
        die("You do not have access to this premium content.");
    }
}

// Resolve base upload directory and final file path securely
$base = realpath(__DIR__ . '/../../uploads/audio/');
$path = realpath($base . '/' . $media['audio_file_name']);

// Validate file path using realpath() + directory boundary check
// Prevents directory traversal attacks
if ($path === false || strpos($path, $base) !== 0) {
    die("Invalid file path");
}

// Clear any active output buffers to avoid corrupting the audio stream
if (ob_get_level()) {
    ob_end_clean();
}

$title = basename($media['title']); // Use basename just in case the title contains directory separators
$extension = '.mp3';

// Concatenate the title and the extension
$download_filename = $title . $extension;
// Send headers to force browser download of the MP3 file
header('Content-Description: File Transfer');
header('Content-Type: audio/mpeg'); // Correct MIME type for MP3 files
header('Content-Disposition: attachment; filename="' . $download_filename .'"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Output the file content directly to the client
readfile($path);
exit;
