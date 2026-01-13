<?php
require_once __DIR__ . '/../../includes/db.php';    
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../includes/read_lyrics.php';
require_once __DIR__ . '/../../includes/encryption.php';
require_once __DIR__ . '/../../config/csp.php';

requireLogin($pdoLow, $log, $logConcern);

//csrf token injection
csrf_protection_start($logConcern);


// Validate POST only the first time
if (!isset($_POST['media_id'])) {
    logGeneralActions($log, 'no media ID in session for view_media.php', $_SESSION['user_id'], 'info');
    header("Location: user_dashboard.php");
    exit;
}

$mediaId = (int) $_POST['media_id'];
$userId  = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

/*Log request of the user*/
logMediaRequest($log,$mediaId,$userId);


// Fetch media safely
try {
    $media = dbFetchMedia($pdoLow, $mediaId);
    if (!$media) {
        logGeneralActions($log, 'no media ID in db for view_media.php', $_SESSION['user_id'], 'info');
        header("Location: user_dashboard.php");
        exit;
    }
} catch (PDOException $e) {
    logDbError($log, 'fetching media', $_SESSION['user_id'], $e);
    header("Location: user_dashboard.php");
    exit;
}

// Access control
$isOwner = ($media['user_id'] == $userId);

if (!$isOwner && $media['is_premium']  && !$_SESSION['is_premium']) {
    $_SESSION['error'] = "This content is premium. Upgrade to access it.";
    logUserForbiddenMediaRequest($log,$mediaId,$userId);
    header("Location: user_dashboard.php");
    exit;
}

//Generate listen audio token
$separator='\x1F\x1F\x1F';
$randomSalt=bin2hex(random_bytes(6));
$tokenContent=$randomSalt.$separator.$userId.$separator.$isOwner.$separator.$media['id'].$separator.$media['title'].$separator.$media['is_premium'].$separator.$media['audio_file_name'];
$audioToken=encryptData($tokenContent,ENCRYPTION_KEY);
// 3. URL Encoding 
$audioToken = urlencode($audioToken);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($media['title']) ?> - Media</title>
    <link rel="stylesheet" href="../assets/css/view_media.css">
</head>
<body>

<div class="media-container">

    <h2 class="media-title"><?= htmlspecialchars($media['title']) ?></h2>

    <div class="media-meta">
        <span class="media-author">By <?= htmlspecialchars($username) ?></span>
        <span class="<?= $media['is_premium'] ? 'premium-label' : 'free-label' ?>">
            <?= $media['is_premium'] ? 'Premium' : 'Free' ?>
        </span>
    </div>

    <!-- ===== AUDIO SECTION ===== -->
    <?php if (!empty($media['audio_file_name'])): ?>
        <div class="audio-section">
            <audio controls>
                <source src="serve_audio.php?token=<?=$audioToken?>"
                        type="<?= htmlspecialchars($media['audio_mime_type'] ?: 'audio/mpeg') ?>">
                Your browser does not support the audio tag.
            </audio>

            <div class="audio-actions">
                <form action="download_audio.php" method="post">
                    <input type="hidden" name="media_id" value="<?= htmlspecialchars($media['id']) ?>">
                    <button type="submit" class="btn-download">⬇ Download MP3</button>
                </form>
                <?php if ($isOwner): ?>
                    <form action="edit_media.php" method="post">
                        <input type="hidden" name="media_id" value="<?= htmlspecialchars($media['id']) ?>">
                        <button type="submit" class="btn-edit">✏ Edit Media</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===== LYRICS ===== -->
    <?php if (!empty($media['lyrics_file_name'])): ?>
        <h3 class="lyrics-title">Lyrics</h3>
        <pre class="lyrics-text"><?= htmlspecialchars(readLyricsFromFile($media['lyrics_file_name'])) ?></pre>
    <?php endif; ?>

    <a href="user_dashboard.php" class="back-link">← Back to Dashboard</a>

</div>

</body>
</html>