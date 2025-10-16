<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Note: The original returned 'returnCode' => 1 for error, keeping that structure
    echo json_encode(['returnCode' => 1, 'message' => 'Invalid request method']);
    exit;
}

// Get and sanitize inputs
$username = trim($_POST['username'] ?? '');
// $email is not used by server490.php's doRegister, but we'll still accept it from the form for now.
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

/* ===================== ADDED: optional Job Alerts fields ===================== */
$alerts_email_enabled = isset($_POST['alerts_email_enabled']) ? 1 : 0;
$alerts_sms_enabled   = isset($_POST['alerts_sms_enabled'])   ? 1 : 0;
$alerts_email         = trim($_POST['alerts_email']     ?? '');
$alerts_phone         = trim($_POST['alerts_phone']     ?? '');
$alerts_keywords      = trim($_POST['alerts_keywords']  ?? '');
$alerts_location      = trim($_POST['alerts_location']  ?? '');
/* ============================================================================ */

// Basic validation
if (empty($username) || empty($email) || empty($password)) {
    echo json_encode(['returnCode' => 1, 'message' => 'All fields are required.']);
    exit;
}

// Create RabbitMQ client
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

// Prepare request: Change 'type' to 'register' and use 'password' key
$request = [
    'type' => 'register',
    'username' => $username,
    'password' => $password // server490.php expects this key
];

// Send and receive response
$response = $client->send_request($request);

// Log for debugging
error_log("Register response: " . json_encode($response));

// Return JSON response to frontend
// server490.php's doRegister returns true (successful) or false (failed, e.g., user exists)
// The $response will be true (1) or false (0)
if ($response === true || (is_array($response) && isset($response['returnCode']) && $response['returnCode'] == 0)) {

    /* ===================== ADDED: persist Job Alerts prefs ===================== 
       - Runs only on successful registration
       - Writes to ./data/alert_prefs.json (flat file). Replace with DB later if you want.
    */
    // Light normalization (won't block registration if invalid)
    if ($alerts_email_enabled && $alerts_email && !filter_var($alerts_email, FILTER_VALIDATE_EMAIL)) {
        $alerts_email_enabled = 0;
        $alerts_email = '';
    }
    if ($alerts_sms_enabled && $alerts_phone) {
        $digits = preg_replace('/\D+/', '', $alerts_phone);
        $alerts_phone = $digits ? ('+' . $digits) : '';
        if (!$alerts_phone) { $alerts_sms_enabled = 0; }
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

    // Key by username to keep it simple
    $prefs[$username] = [
        'email_enabled' => $alerts_email_enabled,
        'sms_enabled'   => $alerts_sms_enabled,
        'email'         => $alerts_email,
        'phone'         => $alerts_phone,
        'keywords'      => $alerts_keywords,
        'location'      => $alerts_location,
        'updated_at'    => date('c')
    ];

    @file_put_contents($file, json_encode($prefs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    /* =================== END ADDED: persist Job Alerts prefs =================== */

    echo json_encode(['returnCode' => 0, 'message' => 'Registration successful! You can now log in.']);
} else {
    // Note: server490.php only returns false on failure, so we assume a generic message.
    $message = 'Registration failed. The username may already be taken.';
    echo json_encode(['returnCode' => 1, 'message' => $message]);
}
?>

