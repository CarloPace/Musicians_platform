<?php
// Required system includes (DB, logging, queries, session, CSRF, security policy)
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../includes/read_lyrics.php';
require_once __DIR__ . '/../../config/csp.php';

requireLogin($pdoLow, $log, $logConcern); // Ensure user is authenticated

// Start CSRF protection for this request

csrf_protection_start($logConcern);

// Save media ID ONLY when the user clicks "Edit" on dashboard.
// Prevents tampering through direct URL editing.
if (!isset($_POST['media_id']) && !isset($_POST['update_media'])) {
    logGeneralActions($log, 'missing media ID for edit_media.php', $_SESSION['user_id'], 'info');
    header("Location: user_dashboard.php");
    exit;
}

$mediaId = (int) $_POST['media_id'];
$userId  = $_SESSION['user_id'];

// Load any feedback messages from previous request
$hasError     = false;
$errorMessage = $_SESSION['error']  ?? '';
$successMsg   = $_SESSION['success'] ?? '';

unset($_SESSION['error'], $_SESSION['success']); // Clear flash messages

// Fetch media belonging to the logged-in user
try {

    $media = dbFetchUserMedia($pdoLow, $mediaId, $userId);

    // If the media does not exist or doesn't belong to this user, redirect
    if (!$media) {
        logGeneralActions($log, 'missing media ID for edit_media.php in db', $_SESSION['user_id'], 'info');
        header("Location: user_dashboard.php");
        exit;
    }
} catch (PDOException $e) {
    logDbError($log, 'fetching user media for edit', $_SESSION['user_id'], $e);
    exit;
}

// --------------- DELETE OPERATIONS (Lyrics or Audio) ---------------- //

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Delete Lyrics
    if (isset($_POST['delete_lyrics'])) {

        // Secure path resolution – prevents path traversal attacks
        $baseLyrics = realpath(__DIR__ . '/../../uploads/lyrics/');
        $pathLyrics = realpath($baseLyrics . '/' . $media['lyrics_file_name']);

        // If file exists within the expected directory, delete it
        if ($pathLyrics && strpos($pathLyrics, $baseLyrics) === 0 && file_exists($pathLyrics)) {
            unlink($pathLyrics);
        } else {
            logGeneralActions($log, 'lyrics file not found for deletion on server', $userId, 'info');
            return;
        }
        // Remove lyrics information from database
        try {
            dbUpdateUserMedia(
                $pdoMedium,
                $userId,
                $media['id'],
                $media['title'],
                $media['audio_file_name'],
                $media['audio_file_size'],
                $media['audio_mime_type'],
                null,null,null,
                $media['is_premium']
            );
        } catch (PDOException $e) {
            logDbError($log, 'updating user media to remove lyrics', $_SESSION['user_id'], $e);
            $_SESSION['error'] = ' Failed to delete lyrics.';
            header("Location: edit_media.php");
            exit;
        }

        $_SESSION['success'] = ' Lyrics deleted successfully.';
        logDeleteLyricsRequestSuccessfull($log,$mediaId,$userId);
        header("Location: edit_media.php");
        exit;
    }

    // Delete Audio
    if (isset($_POST['delete_audio'])) {

        $baseAudio = realpath(__DIR__ . '/../../uploads/audio/');
        $pathAudio = realpath($baseAudio . '/' . $media['audio_file_name']);

        // Secure deletion with directory boundary check
        if ($pathAudio && strpos($pathAudio, $baseAudio) === 0) {
            unlink($pathAudio);
        } else {
            logGeneralActions($log, 'audio file not found for deletion on server', $userId, 'info');
        }

        // Remove audio metadata but keep lyrics info unchanged
        try {
            dbUpdateUserMedia(
                $pdoMedium,
                $userId,
                $media['id'],
                $media['title'],
                null,null,null, 
                $media['lyrics_file_name'],
                $media['lyrics_file_size'],
                $media['lyrics_mime_type'],
                $media['is_premium']
            );
        } catch (PDOException $e) {
            logDbError($logConcern, 'updating user media to remove audio', $_SESSION['user_id'], $e);
            $_SESSION['error'] = ' Failed to delete audio.';
            header("Location: edit_media.php");
            exit;
        }

        $_SESSION['success'] = ' Audio deleted successfully.';
        logDeleteAudioRequestSuccessfull($log,$mediaId,$userId);
        header("Location: edit_media.php");
        exit;
    }
}

// --------------- UPDATE MEDIA (Lyrics, Audio, Metadata) ---------------- //

if (isset($_POST['update_media'])) {

    $title      = trim($_POST['title'] ?? '');
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;

    // Start with existing media values — overwritten only if new uploads succeed
    $lyrics_file_name           = $media['lyrics_file_name'];
    $lyrics_file_size           = $media['lyrics_file_size'];
    $lyrics_mime_type           = $media['lyrics_mime_type'];
    $audio_file_name     = $media['audio_file_name'];
    $audio_mime_type     = $media['audio_mime_type'];
    $audio_file_size     = $media['audio_file_size'];

    // ---------- LYRICS UPLOAD VALIDATION & PROCESSING ---------- //

    if (!empty($_FILES['lyrics_file']['name'])) {

        $lyricsFile = $_FILES['lyrics_file'];

        // Standard PHP file upload check
        if ($lyricsFile['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = ' Error uploading lyrics file.';
            $hasError = true;
            logGeneralActions($log, 'lyrics upload error', $userId, 'info');

        } else {

            // Validate extension
            $lyricsExt = strtolower(pathinfo($lyricsFile['name'], PATHINFO_EXTENSION));

            if ($lyricsExt !== 'txt') {
                $_SESSION['error'] = ' Invalid lyrics file type. Only .txt files are allowed.';
                $hasError = true;
                logGeneralActions($log, 'rejected lyrics extension', $userId, 'warning');

            } else {

                // Check mime type for safety
                $mime = mime_content_type($lyricsFile['tmp_name']);

                if ($mime !== 'text/plain') {
                    $_SESSION['error'] = ' Invalid lyrics file format. Only .txt files are allowed.';
                    $hasError = true;
                    logGeneralActions($logConcern, 'rejected lyrics file type', $userId, 'warning');

                } else {

                    // Enforce size limit (10 KB)
                    $maxLyricsSize = 10 * 1024;

                    if ($lyricsFile['size'] > $maxLyricsSize) {
                        $fileSizeKB = round($lyricsFile['size'] / 1024, 2);
                        $_SESSION['error'] = " Lyrics file is too large ({$fileSizeKB} KB). Maximum allowed: 100 KB";
                        $hasError = true;
                        logGeneralActions($logConcern, 'lyrics file too large', $userId, 'info');

                    } else {
                        // Generate a safe filename for the new lyrics
                        $newName = bin2hex(random_bytes(16)) . '.txt';

                        $dir = __DIR__ . '/../../uploads/lyrics/';
                        //if (!is_dir($dir)) mkdir($dir, 0777, true);

                        $target = $dir . $newName;

                        // Save file securely
                        if (move_uploaded_file($lyricsFile['tmp_name'], $target)) {
                            $lyrics_file_name     = $newName;
                            $lyrics_mime_type     = $mime;
                            $lyrics_file_size     = filesize($target);
                        } else {
                            $_SESSION['error'] = ' Failed to save uploaded lyrics file.';
                            $hasError = true;
                            logGeneralActions($log, 'failed to move lyrics file', $userId, 'error');
                        }
                    }
                }
            }
        }
    }

    // ---------- AUDIO UPLOAD VALIDATION & PROCESSING ---------- //

    if (!$hasError && !empty($_FILES['audio_file']['name'])) {

        $audioFile = $_FILES['audio_file'];

        if ($audioFile['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = ' Error uploading audio file.';
            $hasError = true;
            logGeneralActions($log, 'audio upload error', $userId, 'info');

        } else {

            $audioExt = strtolower(pathinfo($audioFile['name'], PATHINFO_EXTENSION));

            if ($audioExt !== 'mp3') {
                $_SESSION['error'] = ' Invalid audio file type. Only .mp3 files are allowed.';
                $hasError = true;
                logGeneralActions($logConcern, 'rejected audio extension', $userId, 'warning');

            } else {
                $audioMime = mime_content_type($audioFile['tmp_name']);

                // Accept only legitimate MP3 MIME types
                if ($audioMime !== 'audio/mpeg' && $audioMime !== 'audio/mp3') {
                    $_SESSION['error'] = ' Invalid audio file format. Only .mp3 files are allowed.';
                    $hasError = true;
                    logGeneralActions($logConcern, 'rejected audio MIME type', $userId, 'warning');

                } else {

                    // Enforce 10 MB limit
                    $maxAudioSize = 10 * 1024 * 1024;

                    if ($audioFile['size'] > $maxAudioSize) {
                        $fileSizeMB = round($audioFile['size'] / (1024 * 1024), 2);
                        $_SESSION['error'] = " Audio file is too large ({$fileSizeMB} MB). Maximum allowed: 10 MB";
                        $hasError = true;
                        logGeneralActions($logConcern, 'audio file too large', $userId, 'warning');

                    } else {
                        // Generate secure filename for audio
                        $newAudio = bin2hex(random_bytes(16)) . '.mp3';

                        $dir = __DIR__ . '/../../uploads/audio/';
                        if (!is_dir($dir)) mkdir($dir, 0777, true);

                        $target = $dir . $newAudio;

                        if (move_uploaded_file($audioFile['tmp_name'], $target)) {
                            $audio_file_name     = $newAudio;
                            $audio_mime_type     = $audioMime;
                            $audio_file_size     = filesize($target);
                        } else {
                            $_SESSION['error'] = ' Failed to save uploaded audio file.';
                            $hasError = true;
                            logGeneralActions($log, 'failed to move audio file', $userId, 'error');
                        }
                    }
                }
            }
        }
    }

    // ---------- DATABASE UPDATE ---------- //

    if (!$hasError) {

        try {
            // Update the DB with the final values (existing or replaced)
            $updated = dbUpdateUserMedia(
                $pdoMedium,
                $userId,
                $media['id'],
                $title,
                $audio_file_name,
                $audio_file_size,
                $audio_mime_type,
                $lyrics_file_name,
                $lyrics_file_size,
                $lyrics_mime_type,
                $is_premium
            );

            // Update returns false only on actual DB-level error, not on "no changes"
            if ($updated === false) {
                $_SESSION['error'] = "No changes made to media.";
                logGeneralActions($log, 'DB update returned false', $userId, 'warning');
            } else {
                $_SESSION['success'] = " Media updated successfully!";
                unset($_SESSION['current_media']);
                $details=[$media['id'],
                $title,
                $audio_file_name,
                $audio_file_size,
                $audio_mime_type,
                $lyrics_file_name,
                $lyrics_file_size,
                $lyrics_mime_type,
                $is_premium];
                logUpdateMediaRequestSuccessfull($log,$mediaId,$userId,$details);
                header("Location: user_dashboard.php");
                exit;
            }

        } catch (PDOException $e) {
            $_SESSION['error'] = "Unexpected error updating media.";
            logDbError($logConcern, 'updating media', $userId, $e);
        }
    }
}
?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Edit Media</title>
        <link rel="stylesheet" href="../assets/css/edit_media.css">
    </head>
    <body>
        <div class="container">
            <h2>Edit Media</h2>
            <!-- Show Current Lyrics -->
    <?php if (!empty($media['lyrics_file_name'])): ?>
        <div class="current-media">
            <h3>Current Lyrics</h3>
            <div class="file-with-delete">
                <span class="file-name"><?= htmlspecialchars(readLyricsFromFile($media['lyrics_file_name'])); ?></span>
                <form method="post">
                    <input type="hidden" name="media_id" value="<?= htmlspecialchars($mediaId); ?>">
                    <input type="hidden" name="delete_lyrics" value="1">
                    <button type="submit" class="btn-delete">×</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Show Current Audio -->
    <?php if (!empty($media['audio_file_name'])): ?>
        <div class="current-media">
            <h3>Current Audio</h3>
            <div class="file-with-delete">
                <span class="file-name"><?= htmlspecialchars($media['title'].'.mp3'); ?></span>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="media_id" value="<?= htmlspecialchars($mediaId); ?>">
                    <input type="hidden" name="delete_audio" value="1">
                    <button type="submit" class="btn-delete">×</button>
                </form>
            </div>
        </div>
    <?php endif; ?>


            <!-- Error Message -->
            <?php if ($errorMessage): ?>
                <div class="message warning"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if ($successMsg): ?>
                <div class="message info-text"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="media_id" value="<?php echo htmlspecialchars($mediaId); ?>">

                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($media['title']); ?>" required>

                <label for="lyrics_file">Replace Lyrics (.txt)</label>
                <input type="file" id="lyrics_file" name="lyrics_file" accept=".txt">

                <label for="audio_file">Replace Audio (.mp3)</label>
                <input type="file" id="audio_file" name="audio_file" accept=".mp3,audio/mpeg">

                <label class="checkbox-inline">
                    <input type="checkbox" name="is_premium" value="1"
                        <?php if (htmlspecialchars($media['is_premium'])) echo "checked"; ?>>
                    Mark as Premium
                </label>

                <button type="submit" name="update_media" value="1">Save Changes</button>
                <a href="user_dashboard.php" class="btn-cancel">Cancel</a>
            </form>

            <div id="uploadError" class="message"></div>
        </div>

        <script src="../assets/js/edit_media.js"></script>
    </body>
    </html>
