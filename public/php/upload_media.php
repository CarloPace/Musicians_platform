<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../includes/csrf_protection.php';
require_once __DIR__ . '/../../config/csp.php';
require_once __DIR__ . '/../../includes/utility.php';

requireLogin($pdoLow, $log, $logConcern);

//csrf token injection
csrf_protection_start($logConcern);

$title = sanitizeGeneralText($_POST['title'] ?? '');
$isPremium = isset($_POST['is_premium']) ? 1 : 0;
$userId = $_SESSION['user_id'];

// Validate title
if (strlen($title) < 1 || strlen($title) > 255) {
    $_SESSION['error'] = "Invalid title length";
    header("Location: user_dashboard.php");
    exit;
}

// === Prepare uploads directories ===
$uploadDirLyr = __DIR__ . '/../../uploads/lyrics/';
$uploadDirAudio = __DIR__ . '/../../uploads/audio/';
/*if (!is_dir($uploadDirLyr)) {
    mkdir($uploadDirLyr, 0755, true);
}

if (!is_dir($uploadDirAudio)) {
    mkdir($uploadDirAudio, 0755, true);
}*/

// Initialize variables for optional data
$lyricsFileName = $lyricsOriginalName = $lyricsMimeType = $lyricsText = null;
$lyricsFileSize = null;
$audioFileName = $audioOriginalName = $audioMimeType = null;
$audioFileSize = null;

// ------------------------------------
// Handle Lyrics Upload 
// ------------------------------------
if (!empty($_FILES['lyrics_file']['tmp_name']) && $_FILES['lyrics_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmp = $_FILES['lyrics_file']['tmp_name'];
    $lyricsOriginalName = $_FILES['lyrics_file']['name'];
    $lyricsFileSize = $_FILES['lyrics_file']['size'];
    
    // Validate file extension
    $lyricsExt = strtolower(pathinfo($lyricsOriginalName, PATHINFO_EXTENSION));
    if ($lyricsExt !== 'txt') {
        $_SESSION['error'] = " Invalid lyrics file type. Only .txt files are allowed.";
        header("Location: user_dashboard.php");
        exit;
    }
    
    $lyricsMimeType = mime_content_type($fileTmp);
    if ($lyricsMimeType !== 'text/plain') {
        $_SESSION['error'] = " Invalid lyrics file format. Only .txt files are allowed.";
        header("Location: user_dashboard.php");
        exit;
    }
    
    // Check file size (10 KB max)
    $maxLyricsSize = 10 * 1024;
    if ($lyricsFileSize > $maxLyricsSize) {
        $fileSizeKB = round($lyricsFileSize / 1024, 2);
        $_SESSION['error'] = " Lyrics file is too large ({$fileSizeKB} KB). Maximum allowed: 10 KB";
        header("Location: user_dashboard.php");
        exit;
    }

    $lyricsText = file_get_contents($fileTmp);
    if (empty(trim($lyricsText))) {
        $_SESSION['error'] = " Lyrics file cannot be empty";
        header("Location: user_dashboard.php");
        exit;
    }

    $lyricsFileName = bin2hex(random_bytes(16)) . ".txt";
} elseif (isset($_FILES['lyrics_file']) && $_FILES['lyrics_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Handle upload errors
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Lyrics file exceeds maximum upload size',
        UPLOAD_ERR_FORM_SIZE => 'Lyrics file exceeds form maximum size',
        UPLOAD_ERR_PARTIAL => 'Lyrics file was only partially uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write lyrics file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the lyrics file upload'
    ];
    $error = $_FILES['lyrics_file']['error'];
    $_SESSION['error'] = $errorMessages[$error] ?? 'Unknown error uploading lyrics file';
    header("Location: user_dashboard.php");
    exit;
}

// ------------------------------------
// Handle Audio Upload 
// ------------------------------------
if (!empty($_FILES['audio_file']['tmp_name']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmp = $_FILES['audio_file']['tmp_name'];
    $audioOriginalName = $_FILES['audio_file']['name'];
    $audioFileSize = $_FILES['audio_file']['size'];
    
    // Validate file extension
    $audioExt = strtolower(pathinfo($audioOriginalName, PATHINFO_EXTENSION));
    if ($audioExt !== 'mp3') {
        $_SESSION['error'] = " Invalid audio file type. Only .mp3 files are allowed.";
        header("Location: user_dashboard.php");
        exit;
    }
    
    $audioMimeType = mime_content_type($fileTmp);
    $allowedAudioTypes = ['audio/mpeg', 'audio/mp3', 'audio/mpeg3'];
    if (!in_array($audioMimeType, $allowedAudioTypes)) {
        $_SESSION['error'] = " Invalid audio file format. Only .mp3 files are allowed.";
        header("Location: user_dashboard.php");
        exit;
    }
    
    // Check file size (10 MB max)
    $maxAudioSize = 10 * 1024 * 1024;
    if ($audioFileSize > $maxAudioSize) {
        $fileSizeMB = round($audioFileSize / (1024 * 1024), 2);
        $_SESSION['error'] = " Audio file is too large ({$fileSizeMB} MB). Maximum allowed: 10 MB";
        header("Location: user_dashboard.php");
        exit;
    }

    $audioFileName = bin2hex(random_bytes(16)) . ".mp3";
} elseif (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Handle upload errors
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Audio file exceeds maximum upload size',
        UPLOAD_ERR_FORM_SIZE => 'Audio file exceeds form maximum size',
        UPLOAD_ERR_PARTIAL => 'Audio file was only partially uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write audio file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the audio file upload'
    ];
    $error = $_FILES['audio_file']['error'];
    $_SESSION['error'] = $errorMessages[$error] ?? 'Unknown error uploading audio file';
    header("Location: user_dashboard.php");
    exit;
}

// If nothing uploaded:
if (!$lyricsFileName && !$audioFileName) {
    $_SESSION['error'] = " Please upload at least one file (lyrics or audio)";
    header("Location: user_dashboard.php");
    exit;
}

try {
    
    //IF variable are set define the path to in which store them
    if($lyricsFileName)
        $lyricsPath = $uploadDirLyr . $lyricsFileName;
    if($audioFileName)
        $audioPath = $uploadDirAudio . $audioFileName;

    //if the path to store the lyrics is set and the storage fails upload fails
    if (isset($lyricsPath)&&!move_uploaded_file($_FILES['lyrics_file']['tmp_name'], $lyricsPath)) {
        $_SESSION['error'] = "Upload of lyrics file failed, retry";
        logFailedUploadAttempt($log,$_SESSION['user_id'],'lyrics',$lyricsOriginalName);
        header("Location: user_dashboard.php");
        exit;
    }

    //if the path to store the audio is set and the storage fails upload failed
    if (isset($audioPath)&&!move_uploaded_file($_FILES['audio_file']['tmp_name'], $audioPath)) {
        $_SESSION['error'] = "Upload of audio file failed, retry";
        logFailedUploadAttempt($log,$_SESSION['user_id'],'audio',$audioOriginalName);
        header("Location: user_dashboard.php");
        exit;
    }
    
    
    //File successfully saved on the server
    //Adding files to the DB

    $result = dbAddMedia($pdoMedium,$userId, $title,
    $audioFileName,$audioFileSize,$audioMimeType,
    $lyricsFileName, $lyricsFileSize, $lyricsMimeType,
    $isPremium);
     
    if($result){
        $_SESSION['success'] = " Media uploaded successfully!";
        logSuccessfullUpload($log,$_SESSION['user_id'],$audioFileName,$lyricsFileName);
    } else {
        $_SESSION['error'] = "Database error: Failed to save media";
    }

    header("Location: user_dashboard.php");
    exit;

} catch (PDOException $e) {
    logDbError($log, 'adding media', $_SESSION['user_id'], $e);
    $_SESSION['error'] = "Database error: Failed to upload media"; 
    header("Location: user_dashboard.php"); 
    exit;
}
?>