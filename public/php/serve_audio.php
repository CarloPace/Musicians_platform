<?php
require_once __DIR__ . '/../../includes/db.php';    
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/queries.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/encryption.php';
require_once __DIR__ . '/../../config/csp.php';


requireLogin($pdoLow, $log, $logConcern);

if (empty($_GET['token'])) {
    http_response_code(400); // Bad Request
    exit;
}

$token=(string) $_GET['token']; //url decryption automatically done

$decryptedToken=decryptData($token,ENCRYPTION_KEY);

if($decryptedToken===false){
    logServeAudioFailedDecryption($logConcern,$decryptedToken,$_SESSION['user_id']);
    http_response_code(400); // Bad Request
    exit;
}
//Token processing
$SEPARATOR='\x1F\x1F\x1F';
$parts = explode($SEPARATOR, $decryptedToken, 7);

//Parts[0] is the random salt

$userId = filter_var($parts[1] ?? -1, FILTER_VALIDATE_INT);
$isOwner = filter_var($parts[2] ?? false, FILTER_VALIDATE_BOOLEAN);
$mediaId = filter_var($parts[3] ?? -1, FILTER_VALIDATE_INT);
$title = $parts[4] ?? '';
$isPremiumContent = filter_var($parts[5] ?? -1, FILTER_VALIDATE_INT);
$audioFileName = $parts[6] ?? '';



$sessionUserId = (int)($_SESSION['user_id'] ?? -1);

$legitimateUser = ($userId === $sessionUserId);

//Cheking user requests is a legitimate user
if (!$legitimateUser) {
    $_SESSION['error'] = "You are not allowed to submit this request.";
    logServeAudioTokenTransfer($logConcern,$mediaId,$userId,$_SESSION['user_id']);
    header("Location: user_dashboard.php");
    exit;
}

//Checking permissions
if (!$isOwner && $isPremiumContent  && !$_SESSION['is_premium']) {
    $_SESSION['error'] = "This content is premium. Upgrade to access it.";
    logForbiddenServeAudioRequest($logConcern,$mediaId,$_SESSION['user_id']);
    header("Location: user_dashboard.php");
    exit;
}

logServeAudioRequest($log,$audioFileName,$_SESSION['user_id']);

//Check the base path to your private files
$base = realpath(__DIR__ . '/../../uploads/audio/');

//Construct the full, absolute file path
$filename = basename($audioFileName); // Clean filename for security
$filepath = realpath($base .DIRECTORY_SEPARATOR. $filename);


//Validate the path and check file existence
// This check is crucial to prevent "Directory Traversal" attacks.
if ($filepath === false || strpos($filepath, $base) !== 0) {
    $details=[
        'IP_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Not available',
        'Origin' => $_SERVER['HTTP_ORIGIN'] ?? 'Not available',
        'Referer'       => $_SERVER['HTTP_REFERER'] ?? 'Not available',
        'Forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Not available',
        'Request_uri' => $_SERVER['REQUEST_URI'] ?? 'Not available',
        'filename' => $filename,
        'filepath' => $filepath         
    ];
    logServeAudioFileNotFound($logConcern,$_SESSION['user_id'],$details);
    http_response_code(404); // Not Found
    exit;
}

//Set the necessary headers for streaming
$mime_type = mime_content_type($filepath);

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($filepath));
header('Content-Disposition: inline; filename="' . basename($title) . '"');
header('Accept-Ranges: bytes');

//Stream the file content
if (readfile($filepath) === false) {
    // Handle error if streaming fails
    logServeAudioStreamProblem($log,$_SESSION['user_id'],$filename);
    http_response_code(400); 
}


exit;

?>



