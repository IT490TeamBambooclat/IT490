<?php
// save_alert_prefs.php — handles saving Job Alert preferences (PHP only)

session_start();

// 1. Verify user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['session_id'])) {
    header('Location: index.html');
    exit;
}

$username = $_SESSION['username'];

// 2. Get form values (from home.php form)
$email_enabled = isset($_POST['email_enabled']) ? 1 : 0;
$sms_enabled   = isset($_POST['sms_enabled'])   ? 1 : 0;
$email         = trim($_POST['email'] ?? '');
$phone         = trim($_POST['phone'] ?? '');
$keywords      = trim($_POST['keywords'] ?? '');
$location      = trim($_POST['location'] ?? '');

// 3. Simple flat-file storage (saves to data/alert_prefs.json)
// You can replace this later with MySQL if you want.
$dir = __DIR__ . '/data';
$file = $dir . '/alert_prefs.json';

if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$prefs = [];
if (file_exists($file)) {
    $json = file_get_contents($file);
    $prefs = $json ? json_decode($json, true) : [];
    if (!is_array($prefs)) $prefs = [];
}

// 4. Save this user’s preferences
$prefs[$username] = [
    'email_enabled' => $email_enabled,
    'sms_enabled'   => $sms_enabled,
    'email'         => $email,
    'phone'         => $phone,
    'keywords'      => $keywords,
    'location'      => $location,
    'updated_at'    => date('c')
];

file_put_contents($file, json_encode($prefs, JSON_PRETTY_PRINT));

// 5. Redirect back to home.php with success message
header('Location: home.php?prefs_saved=1');
exit;

