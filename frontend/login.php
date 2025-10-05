<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // The client-side form in index.html posts to this, so we use a redirect here, not JSON
    header("Location: index.html");
    exit;
}

// Get user input safely
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    // In a real application, you might redirect with an error message
    // For this setup, we'll redirect back to the login page for simplicity.
    header("Location: index.html?error=fields_required");
    exit;
}

// Create RabbitMQ client instance
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

// Prepare request for RabbitMQ
$request = [
    'type' => 'login',
    'username' => $username,
    'password' => $password
];

// Send request and get response
// $response will be the session ID (string) on success, or false on failure
$response = $client->send_request($request);

// Log response for debugging
error_log("RabbitMQ login response: " . json_encode($response));

// Handle RabbitMQ response
if ($response !== false && is_string($response) && !empty($response)) {
    // Successful login: $response contains the Session ID
    $_SESSION['username'] = $username;
    $_SESSION['session_id'] = $response; // Store the session ID from the server

    // Redirect to home.php
    header("Location: home.php");
    exit;
} else {
    // Login failed
    // Redirect back to login page with a placeholder error
    header("Location: index.html?error=login_failed");
    exit;
}
?>
