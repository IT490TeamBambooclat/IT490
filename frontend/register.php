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

// Basic validation
if (empty($username) || empty($email) || empty($password)) {
    echo json_encode(['returnCode' => 1, 'message' => 'All fields are required.']);
    exit;
}

// (Optional) Password hashing for security
// $password = password_hash($password, PASSWORD_BCRYPT);

// Create RabbitMQ client
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

// Prepare request
$request = [
    'type' => 'makeUser',
    'username' => $username,
    'email' => $email,
    'password' => $password
];

// Send and receive response
$response = $client->send_request($request);

// Log for debugging
error_log("Register response: " . json_encode($response));

// Return JSON response to frontend
if (isset($response['returnCode']) && $response['returnCode'] == 0) {
    echo json_encode(['returnCode' => 0, 'message' => 'Registration successful! You can now log in.']);
} else {
    $message = $response['message'] ?? 'Registration failed. Try a different username or email.';
    echo json_encode(['returnCode' => 1, 'message' => $message]);
}
?>

