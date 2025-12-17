<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function dlog($error)
{
    error_log($error);
    try
    {
        $client = new rabbitMQClient("testRabbitMQ.ini", 'DLogging');
        $request = [
            'type' => 'dlog',
            'timestamp' => date('Y-m-d H:i:s'),
            'source_host' => gethostname(),
            'message' => $error
        ];
        $client->publish($request);
    }
    catch (Exception $e)
    {
        error_log("DLogging Failed" . $e->getMessage());
    }
}

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.html");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    dlog("Login attempt failed: Empty fields for user $username");
    header("Location: index.html?error=fields_required");
    exit;
}

try {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $request = [
        'type' => 'login',
        'username' => $username,
        'password' => $password
    ];

    $response = $client->send_request($request);

    if (is_array($response) && isset($response['session_id']) && isset($response['role'])) {
        $_SESSION['username'] = $username;
        $_SESSION['session_id'] = $response['session_id'];
        $role = $response['role'];
        $_SESSION['role'] = $role;

        if ($role === 'employer') {
            header("Location: employer.php");
        } elseif ($role === 'job_seeker') {
            header("Location: jobseeker.php");
        } else {
            dlog("Invalid role detected: $role for user $username");
            header("Location: index.html?error=invalid_role");
        }
        exit;
    } else {
        dlog("Login failed or invalid response for user $username: " . json_encode($response));
        header("Location: index.html?error=login_failed");
        exit;
    }
} catch (Exception $e) {
    dlog("Critical login error: " . $e->getMessage());
    header("Location: index.html?error=server_error");
    exit;
}
?>
