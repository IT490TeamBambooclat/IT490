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
    echo json_encode(['returnCode' => 0, 'message' => 'Registration successful! You can now log in.']);
} else {
    // Note: server490.php only returns false on failure, so we assume a generic message.
    $message = 'Registration failed. The username may already be taken.';
    echo json_encode(['returnCode' => 1, 'message' => $message]);
}
?>
