#!/usr/bin/php
<?php
// Ensure these paths are correct for your environment
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['request_type'] == "login") {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    // Get username and password from the POST request
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo "Error: Username and password are required.";
        exit;
    }

    // Prepare the request array for the server
    $request = array();
    $request['type'] = "login";
    $request['username'] = $username;
    $request['password'] = $password;
    
    // Send the request and get the response (session ID or false)
    $response = $client->send_request($request);

    echo "<h2>Login Response:</h2>";
    
    // Check the response from the server
    if ($response === false) {
        echo "Login **Failed**. Invalid username or password.";
    } elseif (is_string($response) && strlen($response) > 0) {
        echo "Login **Successful**! Session ID: " . htmlspecialchars($response);
        // In a real application, you would set a cookie here.
    } else {
        echo "An unexpected error occurred or the response was empty.";
    }
} else {
    // If accessed directly or not a login POST
    echo "Access this script via the login.html form.";
}
?>
