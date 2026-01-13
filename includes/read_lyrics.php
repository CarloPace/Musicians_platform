<?php

function readLyricsFromFile(string $filename){

    $location = '../../uploads/lyrics/';
    $file_path = $location.$filename;
    $file_content = '';

    if (file_exists($file_path)) {
    // Read the entire file content into the $file_content variable
    $file_content = file_get_contents($file_path);

    // Check for error
    if ($file_content === false) return 'Error';
    
    return $file_content;

    } else {
        return "File specified does not exist.";
    }
}

?>