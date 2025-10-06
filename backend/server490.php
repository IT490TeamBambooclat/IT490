#!/usr/bin/php
<?php
// Include required RabbitMQ and host configuration files
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Function to connect to the MySQL database using PDO
function getPDO() {
    $dsn = "mysql:host=127.0.0.1;dbname=testdb;charset=utf8mb4"; // Database connection string
    $user = "testUser"; // Database username
    $pass = "12345"; // Database password
    // Create a new PDO instance and enable exception mode for errors
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

// Function to handle user registration
function doRegister($username, $password) {
    $pdo = getPDO(); // Connect to database
    // Check if the username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username=?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) return false; // If found, registration fails
    // Hash the password for secure storage
    $hash = password_hash($password, PASSWORD_DEFAULT);
    // Insert new user with hashed password into the database
    $stmt = $pdo->prepare("INSERT INTO users (username,password_hash) VALUES (?,?)");
    return $stmt->execute([$username, $hash]); // Return true if insert successful
}

// Function to handle user login
function doLogin($username, $password) {
    $pdo = getPDO(); // Connect to database
    // Retrieve the stored password hash for the given username
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username=?");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    // If user not found or password is invalid, return false
    if (!$row || !password_verify($password, $row['password_hash'])) return false;
    // Generate a random session ID
    $sid = bin2hex(random_bytes(16));
    // Set session expiration time to 1 day from now
    $exp = date("Y-m-d H:i:s", strtotime("+1 day"));
    // Store the new session in the sessions table
    $stmt = $pdo->prepare("INSERT INTO sessions (session_id,username,expires_at) VALUES (?,?,?)");
    $stmt->execute([$sid,$username,$exp]);
    // Return the session ID to the client
    return $sid;
}

// Function to validate if a given session ID is still valid
function doValidate($sid) {
    $pdo = getPDO(); // Connect to database
    // Check if the session ID exists
    $stmt = $pdo->prepare("SELECT username,expires_at FROM sessions WHERE session_id=?");
    $stmt->execute([$sid]);
    $row = $stmt->fetch();
    if (!$row) return false; // Invalid session
    // Check if session has expired
    if (strtotime($row['expires_at']) < time()) return false;
    return true; // Session is valid
}


function requestProcessor($req) {
    switch ($req['type']) {
        case "register": 
            return doRegister($req['username'],$req['password']); // Handle registration
        case "login": 
            return doLogin($req['username'],$req['password']); // Handle login
        case "validate_session": 
            return doValidate($req['sessionId']); // Handle session validation
    }
    return "Invalid request"; 
}

// Create a RabbitMQ server instance and start listening for requests
$server = new rabbitMQServer("testRabbitMQ.ini","testServer");
// Process incoming requests using the requestProcessor function
$server->process_requests('requestProcessor');
?>

