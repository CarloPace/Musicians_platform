<?php

require_once __DIR__ . '/../config/config.php';

function encryptData(string $data, string $key): string|false {
    
    // 1. Generate IV (12 bytes for AES-GCM)
    $iv_length = openssl_cipher_iv_length(CIPHER_METHOD);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $tag = ''; 

    // 2. Encrypt with IV as AAD
    $encrypted = openssl_encrypt(
        $data,
        CIPHER_METHOD,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        $iv  
    );

    if ($encrypted === false) {
        return false;
    }
    
    // 3. Combine parts: IV + Ciphertext + Tag
    $combined_data = $iv . $encrypted . $tag;
    
    return base64_encode($combined_data);
}

function decryptData(string $data, string $key): string|false {
    
    // 1. Decode
    $decoded = base64_decode($data);
    if ($decoded === false) {
        return false;
    }
    
    // 2. Define lengths and separate
    $iv_length = openssl_cipher_iv_length(CIPHER_METHOD);
    $tag_length = 16; 
    $total_length = strlen($decoded);

    if ($total_length < $iv_length + $tag_length) {
        return false;
    }
    
    $iv = substr($decoded, 0, $iv_length);
    $tag = substr($decoded, -$tag_length);
    $ciphertext_length = $total_length - $iv_length - $tag_length;
    $ciphertext = substr($decoded, $iv_length, $ciphertext_length);

    // 3. Decrypt with IV as AAD
    $decrypted = openssl_decrypt(
        $ciphertext,
        CIPHER_METHOD,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        $iv
    );
    
    return $decrypted;
}
?>