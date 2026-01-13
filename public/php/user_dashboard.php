<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../config/csp.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';

requireLogin($pdoLow, $log, $logConcern);

//csrf token injection
csrf_protection_start($logConcern);

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['username'] ?? $_SESSION['email'];


// === Fetch Current User's Uploads ===
try{
  $myUploads = dbFetchUserUploads($pdoLow,$userId);
} catch (PDOException $e) {
  logDbError($logConcern, 'fetching user uploads', $userName, $e);
}

// === Fetch Other Users' Free Uploads ===
try{
  $freeUploads = dbFetchOthersUsersFreeUploads($pdoLow,$userId);
} catch (PDOException $e) {
  logDbError($logConcern, 'fetching free uploads', $userName, $e);
}

// === Fetch Premium Uploads (only if current user is premium) ===
$premiumUploads = [];
if ($_SESSION['is_premium']) {
    try{
        $premiumUploads = dbFetchOthersUsersPremiumUploads($pdoLow,$userId);
    } catch (PDOException $e){
      logDbError($logConcern, 'fetching premium uploads', $userName, $e);
    }
}


if(isset($_POST['delete'], $_POST['media_id'])){
  $mediaId = (int) $_POST['media_id'];
   try {
        // Optional: fetch media first to ensure it belongs to this user
        $media = dbFetchUserMedia($pdoLow, $mediaId, $userId);

        if (!$media) {
            $_SESSION['error'] = "Media not found or you don't have permission.";
            logDeleteMediaRequestFailed($log,$mediaId,$userId);
        } else {
            // Delete audio file if exists
            if (!empty($media['audio_file_name'])) {
                $baseAudio = realpath(__DIR__ . '/../../uploads/audio/');
                $audioPath = realpath($baseAudio . '/' . $media['audio_file_name']);
                if ($audioPath && strpos($audioPath, $baseAudio) === 0 && file_exists($audioPath)) {
                    unlink($audioPath);
                }
            }

            // Delete lyrics file if exists
            if (!empty($media['lyrics_file_name'])) {
                $baseLyrics = realpath(__DIR__ . '/../../uploads/lyrics/');
                $lyricsPath = realpath($baseLyrics . '/' . $media['lyrics_file_name']);
                if ($lyricsPath && strpos($lyricsPath, $baseLyrics) === 0 && file_exists($lyricsPath)) {
                    unlink($lyricsPath);
                }
            }

            // Delete DB record
            dbDeleteMedia($pdoMedium, $mediaId);

            $_SESSION['success'] = "Media deleted successfully.";
            logDeleteMediaRequestSuccessfull($log,$mediaId,$userId);
        }

    } catch (PDOException $e) {
        logDbError($logConcern, "deleting media", $userId, $e);
        $_SESSION['error'] = "Failed to delete media.";
    }

    // Redirect to avoid resubmission
    
    header("Location: user_dashboard.php");
    exit;
}

// Check for success or error messages
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';

// Clear the messages after reading them
unset($_SESSION['success']);
unset($_SESSION['error']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Dashboard</title>
  <link rel="stylesheet" href="../assets/css/user_dashboard.css">
  <script src="../assets/js/user_dashboard.js"></script>
</head>
<body>

<!-- === HEADER AREA === -->
<div class="dashboard-header">
  <h2>
    Welcome, <?= htmlspecialchars($userName) ?>
    <?php if (!empty($_SESSION['is_premium'])): ?>
      <span class="premium-badge">Premium</span>
    <?php endif; ?>
  </h2>
  <div class="user-info">
    <a href="change_password.php" class="btn-change">Change Password</a>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</div>

<!-- === MAIN CONTAINER === -->
<div class="container">
  <div class="tabs-nav">
    <button class="tab-btn active" data-tab="tab-upload">Upload Media</button>
    <button class="tab-btn" data-tab="tab-myuploads">My Uploads</button>
    <button class="tab-btn" data-tab="tab-others">Explore</button>
  </div>

  <div class="tabs-content">

    <!-- === UPLOAD TAB === -->
    <div id="tab-upload" class="tab-panel active">
      <h2>Upload Media</h2>
      <!-- Error Message -->
        <?php if ($errorMessage): ?>
            <div class="message warning"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <!-- Success Message -->
        <?php if ($successMessage): ?>
            <div class="message info-text"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
      <form action="upload_media.php" method="post" enctype="multipart/form-data">
        
        <label>Title:</label>
        <input type="text" name="title" required>

        <label>Lyrics File (.txt):</label>
        <input type="file" name="lyrics_file" accept=".txt">

        <label>Audio File (.mp3):</label>
        <input type="file" name="audio_file" accept=".mp3,audio/mpeg">

        <label class="checkbox-inline">
          <input type="checkbox" name="is_premium" value="1"> Mark as Premium
        </label>

        <button type="submit" class="btn">Upload</button>
        
      </form>
       <div id="uploadError" class="message warning"></div>
    </div>

    <!-- === MY UPLOADS TAB === -->
    <div id="tab-myuploads" class="tab-panel">
      <h2>My Uploads</h2>
      <?php if ($myUploads): ?>
      <table class="styled-table">
        <thead>
          <tr><th>Title</th><th>Lyrics</th><th>Audio</th><th>Tier</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($myUploads as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= !empty($row['lyrics_file_name']) ? '✔️' : '–' ?></td>
            <td><?= !empty($row['audio_file_name']) ? '🎵' : '–' ?></td>
            <td><?= $row['is_premium'] ? '<span class="badge premium">Premium</span>' : '<span class="badge free">Free</span>' ?></td>
            <td>
            <div class ="form-container"> 
              <form action="view_media.php" method="post">
                <input type="hidden" name="media_id" value="<?= $row['id'] ?>">
                <button type="submit" class="action-link action-link-view">View</button>
              </form>
              <form action="edit_media.php" method="post">
                <input type="hidden" name="media_id" value="<?= $row['id'] ?>">
                <button type="submit" class="action-link action-link-edit">Edit</button>
              </form>
              <form action="user_dashboard.php" method="post" class ="delete-form">
                <input type="hidden" name="media_id" value="<?= $row['id'] ?>">
                <input type="hidden" name="delete" value="1">
                <button type="submit" class="action-link action-link-delete">Delete</button>
              </form>
            </div> 
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><p class="info-text">You have no uploads yet.</p><?php endif; ?>
    </div>

    <!-- === EXPLORE TAB === -->
    <div id="tab-others" class="tab-panel">
      <h2>Explore Uploads</h2>

      <h3>Free Content</h3>
      <?php if ($freeUploads): ?>
      <table class="styled-table">
        <thead><tr><th>Title</th><th>By</th><th>Lyrics</th><th>Audio</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($freeUploads as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
            <td><?= !empty($row['lyrics_file_name']) ? '✔️' : '–' ?></td>
            <td><?= !empty($row['audio_file_name']) ? '🎵' : '–' ?></td>
            <td><form action="view_media.php" method="post">
                <input type="hidden" name="media_id" value="<?= $row['id'] ?>">
                <button type="submit" class="action-link action-link-view">View</button>
              </form>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><p class="info-text">No free uploads available.</p><?php endif; ?>

      <h3>Premium Content</h3>
      <?php if (empty($_SESSION['is_premium'])): ?>
        <p class="info-text warning">Upgrade to Premium to see this section.</p>
      <?php elseif ($premiumUploads): ?>
      <table class="styled-table">
        <thead><tr><th>Title</th><th>By</th><th>Lyrics</th><th>Audio</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($premiumUploads as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
            <td><?= !empty($row['lyrics_file_name']) ? '✔️' : '–' ?></td>
            <td><?= !empty($row['audio_file_name']) ? '🎵' : '–' ?></td>
            <td><form action="view_media.php" method="post">
                <input type="hidden" name="media_id" value="<?= $row['id'] ?>">
                <button type="submit" class="action-link">View</button>
              </form>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><p class="info-text">No premium uploads available.</p><?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>
