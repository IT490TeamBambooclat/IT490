<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['returnCode' => 1, 'message' => 'Invalid request method']);
    exit;
}

// Get and sanitize inputs
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$role = trim($_POST['role'] ?? '');

// Alerts
$alerts_email_enabled = isset($_POST['alerts_email_enabled']) ? 1 : 0;
$alerts_email = trim($_POST['alerts_email'] ?? '');

// Basic validation
if (empty($username) || empty($email) || empty($password) || empty($role)) {
    echo json_encode(['returnCode' => 1, 'message' => 'All fields (including role) are required.']);
    exit;
}

// Create RabbitMQ client
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

// Prepare request with full payload (username, password, email, role, alerts)
$request = [
    'type' => 'register',
    'username' => $username,
    'password' => $password,
    'email' => $email,
    'role' => $role,
    'alerts_email_enabled' => $alerts_email_enabled,
    'alerts_email' => $alerts_email
];

// Send and receive response from backend (worker)
$response = $client->send_request($request);

// Log for debugging
error_log("Register response: " . json_encode($response));

// Return JSON response to frontend and persist alert prefs locally as well
if ($response === true || (is_array($response) && isset($response['returnCode']) && $response['returnCode'] == 0)) {

    // Validate alert email if provided
    if ($alerts_email_enabled && $alerts_email && !filter_var($alerts_email, FILTER_VALIDATE_EMAIL)) {
        $alerts_email_enabled = 0;
        $alerts_email = '';
    }

    $dir  = __DIR__ . '/data';
    $file = $dir . '/alert_prefs.json';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

    $prefs = [];
    if (file_exists($file)) {
        $json  = file_get_contents($file);
        $prefs = $json ? json_decode($json, true) : [];
        if (!is_array($prefs)) { $prefs = []; }
    }

    // Save alerts (local copy)
    $prefs[$username] = [
        'email_enabled' => $alerts_email_enabled,
        'email'         => $alerts_email,
        'role'          => $role,
        'updated_at'    => date('c')
    ];

    @file_put_contents($file, json_encode($prefs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    echo json_encode(['returnCode' => 0, 'message' => 'Registration successful! You can now log in.']);
} else {
    $message = 'Registration failed. The username may already be taken.';
    // If backend returned message, use that
    if (is_array($response) && isset($response['message'])) { $message = $response['message']; }
    echo json_encode(['returnCode' => 1, 'message' => $message]);
}
?>
