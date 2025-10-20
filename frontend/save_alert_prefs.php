<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

session_start();

// Make sure the user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

$username = $_SESSION['username'];

// Get posted data
$alerts_email_enabled = isset($_POST['alerts_email_enabled']) ? 1 : 0;
$alerts_email = trim($_POST['alerts_email'] ?? '');

// Validate email format if enabled
if ($alerts_email_enabled && $alerts_email && !filter_var($alerts_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

// Directory and file for storage
$dir  = __DIR__ . '/data';
$file = $dir . '/alert_prefs.json';

// Make sure directory exists
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

// Load existing data
$prefs = [];
if (file_exists($file)) {
    $json  = file_get_contents($file);
    $prefs = $json ? json_decode($json, true) : [];
    if (!is_array($prefs)) { $prefs = []; }
}

// Save only email alert preference
$prefs[$username] = [
    'email_enabled' => $alerts_email_enabled,
    'email'         => $alerts_email,
    'updated_at'    => date('c')
];

// Write back to JSON file
@file_put_contents($file, json_encode($prefs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Respond to frontend
echo json_encode(['success' => true, 'message' => 'Email alert preference saved successfully.']);
?>

