<?php

function sanitizeGeneralText(string $data): string {
    $data = trim($data);
    $data = strip_tags($data); 
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function sanitizeEmailInput(string $data): string {
    $data = trim($data);
    $data = strtolower($data);
    return $data;
}
