<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

function hash_log_data(string $data): string {

    $pepper = HASH_PEPPER;
    
    // Concatenate the pepper and the data
    $salted_data = $pepper . $data;
    
    // Use SHA-256 for fast, cryptographically secure hashing
    return hash('sha256', $salted_data);
}



// Create a single handler that will write to one file
$stream = new StreamHandler(__DIR__ . '/../logs/musicians_platform.log', Logger::DEBUG);

// Use the JsonFormatter to structure the output
$stream->setFormatter(new JsonFormatter());

// Create the logger and push the handler
$log = new Logger('MainApp');
$log->pushHandler($stream);

//creating second log file, for more concerning events
$stream2 = new StreamHandler(__DIR__ . '/../logs/concern.log', Logger::WARNING);

// Use the JsonFormatter to structure the output
$stream2->setFormatter(new JsonFormatter());

// Create the logger and push the handler
$logConcern = new Logger('Concern');
$logConcern->pushHandler($stream2);


// Create the logger for the admin activities
$streamAdmin = new StreamHandler(__DIR__ . '/../logs/musicians_platform_admins.log', Logger::INFO);

// Use the JsonFormatter to structure the output
$streamAdmin->setFormatter(new JsonFormatter());

// Create the logger and push the handler
$logAdmin = new Logger('Admin_act');
$logAdmin->pushHandler($streamAdmin);

// Examples
//$log->info('User logged in', ['username' => 'john.doe']);
//$log->warning('User password will expire in 3 days', ['username' => 'jane.doe']);
//$log->error('Failed to connect to database', ['db_host' => '10.0.0.5']);

//Possible levels
//DEBUG,INFO,NOTICE,WARNING,ERROR,CRITICAL,ALERT,EMERGENCY

function logDBError($log, $action, $id, $exception) {
    $log->error("Database error during {$action}", [
        'musicians_db' => '127.0.0.1',
        'IP' => $_SERVER['REMOTE_ADDR'] ?? '',
        'userId' => hash_log_data($id),
        'error' => $exception->getMessage()
    ]);
}
function logAdminActions($logAdmin, $action, $id, $type, $targetId) {
    $logAdmin->{$type}("{$action}", [
            'musicians_db' => '127.0.0.1',
            'IP' => $_SERVER['REMOTE_ADDR'] ?? '',
            'Admin_id'        => hash_log_data($id),
            'userId'       => $targetId
        ]);
}
function logSusActions($logConcern, $action, $id, $otp){
    $logConcern->warning("{$action}", [
        'user_id' => hash_log_data($id),
        'otp' => $otp ?? 'Not available',
        'IP_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Not available',
        'Origin' => $_SERVER['HTTP_ORIGIN'] ?? 'Not available',
        'Referer'       => $_SERVER['HTTP_REFERER'] ?? 'Not available',
        'Forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Not available',
        'Request_uri' => $_SERVER['REQUEST_URI'] ?? 'Not available'
    ]);
}
function logGeneralActions($log, $action, $id, $type){
    $log->{$type}("{$action}", [
        'id' => hash_log_data($id) ?? 'Not available'
    ]); 
}


/*Function to log user actions*/
function logFailedUserLoginAttempt($log,$userId,$server){
    $log->info("User failed attempt login", [
        'user_id' => hash_log_data($userId) ?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]); 
}

function logBlockedUserLoginAttempt($log,$userId,$server){
    $log->info("Blocked User tried to login", [
        'user_id' => hash_log_data($userId) ?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]); 
}

function logUserLogin($log,$userId,$server){
    $log->info("User login", [
        'user_id' => hash_log_data($userId) ?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]); 
}

function logUserLogout($log,$userId,$server){
    $log->info("User logout", [
        'user_id' => hash_log_data($userId) ?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]); 
}

function logUserPasswordReset($log,$userId,$server){
    $log->info("User password reset", [
        'user_id' => hash_log_data($userId) ?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]); 
}

function logUserPasswordChangeFailedAttempt($log,$userId,$server){
    $log->notice("User password change failed attempt", [
        'user_id' => hash_log_data($userId) ?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]); 
}

function logUserPasswordChange($log,$userId,$server){
    $log->notice("User password changed password", [
        'user_id' => hash_log_data($userId) ?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]); 
}

function logUserLocked($log,$userId,$server){
    $log->notice("User locked", [
        'user_id' => hash_log_data($userId) ?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]); 
}

/*Functions to log uploads
$upload type can be audio/lyrics/complete

$details structure, always pass first the type then the filename
EX. lyrics,lyricsName,audio,audioFileName
*/
function logSuccessfullUpload(Logger $log,string $userId,$audioFileName, $lyricsFileName){

    $log->info("User uploaded successfully a resource", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'audioFileName' => $audioFileName ?? 'Not available',
        'lyricsFileName' => $lyricsFileName ?? 'Not available' 
    ]); 
}
function logFailedUploadAttempt($log, $userId,$uploadType,$filename){
    $log->notice("User upload attempt failed", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'uploaded type' => $uploadType,
        'file' => $filename
    ]); 
}

/*Function to logs resource requests*/
function logMediaRequest(Logger $log,string $mediaId,string $userId){
    $log->info("User requested a media", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'mediaId' => $mediaId ?? 'Not available',
    ]);
}

function logUserForbiddenMediaRequest(Logger $log,string $mediaId,string $userId){
    $log->notice("Forbidden media request", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'mediaId' => $mediaId ?? 'Not available',
    ]);
}

/*Serve audio logs*/
function logForbiddenServeAudioRequest(Logger $log,string $mediaId,string $userId){
    $log->warning("Forbidden serve audio request", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'mediaId' => $mediaId ?? 'Not available',
    ]);
}
function logServeAudioTokenTransfer(Logger $log,string $mediaId,string $userId1,string $userId2){
    $log->alert("Serve audio token transfer detected", [
        'legitimate_user' => hash_log_data($userId1) ?? 'Not available',
        'malicious_user' => hash_log_data($userId2) ?? 'Not available',
        'mediaId' => $mediaId ?? 'Not available',
    ]);
}


/*Function to logs delete media*/

function logDeleteMediaRequestSuccessfull(Logger $log,string $mediaId,string $userId){
    $log->info("User deleted a media", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'mediaId' => $mediaId ?? 'Not available',
    ]);
}

function logDeleteMediaRequestFailed(Logger $log,string $mediaId,string $userId){
    $log->notice("Attempt of media deletion failed", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'mediaId' => $mediaId ?? 'Not available',
    ]);
}

function logDeleteAudioRequestSuccessfull(Logger $log,string $mediaId,string $userId){
    $log->info("User deleted an audio file", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'mediaId' => $mediaId ?? 'Not available',
    ]);
}

function logDeleteLyricsRequestSuccessfull(Logger $log,string $mediaId,string $userId){
    $log->info("User deleted a lyrics file", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'mediaId' => $mediaId ?? 'Not available',
    ]);
}


/*Function to logs update media*/

function logUpdateMediaRequestSuccessfull(Logger $log,string $mediaId,string $userId,array $details){
    $log->info("User updated a media", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'mediaId' => $mediaId ?? 'Not available',
        'details' => $details
    ]);
}

//Function to log the request of listening an audio

function logServeAudioRequest(Logger $log,string $audioName,string $userId){
    $log->info("User request to serve_audio", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'audio file' => $audioName ?? 'Not available'
    ]);
}

function logServeAudioFailedDecryption(Logger $log,string $token,string $userId){
    $log->info("User request to serve_audio Failed decryption", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'token' => $token
    ]);
}

function logServeAudioStreamProblem(Logger $log,string $userId,string $audioFileName){
    $log->info("request on serve_audio.php, problem during stream", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'audio file' => $audioFileName ?? 'Not available'
    ]);
}


function logServeAudioFileNotFound(Logger $log,string $userId,array $details){
    $log->warning("request on serve_audio.php, correctly decrypted, but file not found", [
        'userId' => hash_log_data($userId) ?? 'Not available',
        'details' => $details ?? 'Not available'
    ]);
}

/*Function to log download audio*/
function logDownloadAudioRequest($log,$mediaId,$userId,$server){
    $log->info("User download audio request", [
        'user_id' => hash_log_data($userId) ?? 'Not available',
        'media_id'=> $mediaId?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]); 
}
/*ADMIN LOGS*/
function logLockedAdminLoginAttempt(Logger $log,string $adminId){
    $log->warning("Locked admin tried to login", [
        'AdminId' => hash_log_data($adminId) ?? 'Not available'
    ]);
}

function logAdminLocked(Logger $log,string $adminId){
    $log->alert("Admin account locked", [
        'AdminId' => hash_log_data($adminId) ?? 'Not available'
    ]);
}

function logAdminFailedLoginAttempt(Logger $log,string $adminId){
    $log->warning("Admin failed login attempt", [
        'AdminId' => hash_log_data($adminId) ?? 'Not available'
    ]);
}

function logAdminFinalized2FA(Logger $log,string $adminId){
    $log->notice("Admin finalized 2fa", [
        'AdminId' => hash_log_data($adminId) ?? 'Not available'
    ]);
}

function logAdminLogin(Logger $log,$adminId,$server){
    $log->notice("Admin login", [
        'AdminId' => hash_log_data($adminId) ?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]);
}

function logAdminLogout(Logger $log,$adminId,$server){
    $log->notice("Admin logout", [
        'AdminId' => hash_log_data($adminId) ?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]);
}

function logAdminFailedChangePasswordAttempt(Logger $log,$adminId,$server){
    $log->warning("Admin failed change password attempt", [
        'AdminId' => hash_log_data($adminId) ?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]);
}

function logAdminPasswordChange(Logger $log,$adminId,$server){
    $log->warning("Admin changed password", [
        'AdminId' => hash_log_data($adminId) ?? 'Not available',
        'IP_addr' => $server['REMOTE_ADDR'] ?? 'Not available',
        'Forwarded_for' => $server['HTTP_X_FORWARDED_FOR'] ?? 'Not available'
    ]);
}
