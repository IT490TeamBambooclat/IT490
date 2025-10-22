<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.html");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    header("Location: index.html?error=fields_required");
    exit;
}

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

$request = [
    'type' => 'login',
    'username' => $username,
    'password' => $password
];

$response = $client->send_request($request);

error_log("RabbitMQ login response: " . json_encode($response));

if (is_array($response) && isset($response['session_id']) && isset($response['role'])) {
    
    $_SESSION['username'] = $username;
    $_SESSION['session_id'] = $response['session_id'];
    $role = $response['role'];
    $_SESSION['role'] = $role;

    if ($role === 'employer') 
    {
    header("Location: employer.php");
    } elseif ($role === 'job_seeker') 
    { 
    header("Location: jobseeker.php");
} else {
    header("Location: index.html?error=invalid_role"); 
}
    exit;
} else {
    header("Location: index.html?error=login_failed");
    exit;
}
?>
