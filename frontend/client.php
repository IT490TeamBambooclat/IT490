#!/usr/bin/php
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['request_type'] == "login") {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $err = "Error: Username and password are required.";
        dlog($err);
        echo $err;
        exit;
    }

    $request = array();
    $request['type'] = "login";
    $request['username'] = $username;
    $request['password'] = $password;
    
    $response = $client->send_request($request);

    echo "<h2>Login Response:</h2>";
    
    if ($response === false) {
        dlog("Login Failed for user: $username");
        echo "Login **Failed**. Invalid username or password.";
    } elseif (is_string($response) && strlen($response) > 0) {
        echo "Login **Successful**! Session ID: " . htmlspecialchars($response);
    } else {
        $err = "An unexpected error occurred or the response was empty for user: $username";
        dlog($err);
        echo $err;
    }
} else {
    echo "Access this script via the login.html form.";
}
?>
