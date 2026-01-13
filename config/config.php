<?php

// Load environment variables from .env file
$envFile = __DIR__ . '/../.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // skip comments
        list($name, $value) = array_map('trim', explode('=', $line, 2));
        $_ENV[$name] = $value;
    }
}

// Define constants only once
if (!defined('DB_HOST')) define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', $_ENV['DB_NAME'] ?? 'musicians_db');
if (!defined('DB_USER')) define('DB_USER', $_ENV['DB_USER'] ?? 'root');
if (!defined('DB_PASS')) define('DB_PASS', $_ENV['DB_PASS'] ?? '');

if (!defined('DB_USER_LOW')) define('DB_USER_LOW', $_ENV['DB_USER_LOW'] ?? '');
if (!defined('DB_USER_MEDIUM')) define('DB_USER_MEDIUM', $_ENV['DB_USER_MEDIUM'] ?? '');
if (!defined('DB_PASS_LOW')) define('DB_PASS_LOW', $_ENV['DB_PASS_LOW'] ?? '');
if (!defined('DB_PASS_MEDIUM')) define('DB_PASS_MEDIUM', $_ENV['DB_PASS_MEDIUM'] ?? '');

if (!defined('HASH_PEPPER')) define('HASH_PEPPER', $_ENV['HASH_PEPPER'] ?? '');

if (!defined('CIPHER_METHOD')) define('CIPHER_METHOD', $_ENV['CIPHER_METHOD'] ?? '');
if (!defined('ENCRYPTION_KEY')) define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? '');

?>