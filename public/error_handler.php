<?php
function shutDownFunction() {
    $error = error_get_last();
    // Check if it's a fatal error
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('HTTP/1.1 500 Internal Server Error');
        // Hide all output and show your html
        ob_end_clean(); 
        include(__DIR__ . '/errors/error.html');
        die();
    }
}
register_shutdown_function('shutDownFunction');
ob_start(); // Buffer output so we can clear it if a crash happens