<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['returnCode' => 'failure', 'message' => 'Invalid request method']);
    exit;
}

// Get user input safely
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    echo json_encode(['returnCode' => 'failure', 'message' => 'Username and password are required']);
    exit;
}

// Create RabbitMQ client instance
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

// Prepare request for RabbitMQ
$request = [
    'type' => 'login',
    'username' => $username,
    'password' => $password // If you hash passwords before sending, adjust this line
];

// Send request and get response
$response = $client->send_request($request);

// Log response for debugging
error_log("RabbitMQ response: " . json_encode($response));

// Handle RabbitMQ response
if (isset($response['returnCode']) && $response['returnCode'] == 0) {
    // Successful login
    $_SESSION['username'] = $username;

    // Redirect to home.php
    header("Location: home.php");
    exit;
} else {
    // Login failed
    $message = $response['message'] ?? 'Login failed. Please try again.';
    echo json_encode(['returnCode' => 1, 'message' => $message]);
    exit;
}
?>

