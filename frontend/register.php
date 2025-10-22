<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') 
{
    echo json_encode(['returnCode' => 1, 'message' => 'Invalid request method']);
    exit;
}
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$role = trim($_POST['role'] ?? '');
$alerts_enabled = (int)($_POST['alerts_email_enabled'] ?? 0); //sending as int for testing.
if (empty($username) || empty($email) || empty($password) || empty($role)) {
    echo json_encode(['returnCode' => 1, 'message' => 'All fields are required.']);
    exit;
}
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

// Send everything thru rabbitmq
$request = [
    'type' => 'register',
    'username' => $username,
    'password' => $password,
    'email' => $email,
    'role' => $role,
    'alerts_enabled' => $alerts_enabled // This now correctly sends 0 or 1
];

$response = $client->send_request($request);
error_log("Register response: " . json_encode($response));

if ($response === true || (is_array($response) && isset($response['returnCode']) && $response['returnCode'] == 0)) {
    echo json_encode(['returnCode' => 0, 'message' => 'Registration successful! You can now log in.']);
} else {
    $message = 'Registration failed. The username or email may already be taken.';
    echo json_encode(['returnCode' => 1, 'message' => $message]);
}
?>
