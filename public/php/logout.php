<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/log.php';
logout($log, $logAdmin);

// Always redirect to main login
header("Location: login.php");
exit;
