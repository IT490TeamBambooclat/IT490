#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function getPDO() {
    $dsn = "mysql:host=127.0.0.1;dbname=testdb;charset=utf8mb4";
    $user = "testUser";
    $pass = "12345";
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function doRegister($username, $password) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username=?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) return false;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username,password_hash) VALUES (?,?)");
    return $stmt->execute([$username, $hash]);
}

function doLogin($username, $password) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username=?");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) return false;
    $sid = bin2hex(random_bytes(16));
    $exp = date("Y-m-d H:i:s", strtotime("+1 day"));
    $stmt = $pdo->prepare("INSERT INTO sessions (session_id,username,expires_at) VALUES (?,?,?)");
    $stmt->execute([$sid,$username,$exp]);
    return $sid;
}

function doValidate($sid) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT username,expires_at FROM sessions WHERE session_id=?");
    $stmt->execute([$sid]);
    $row = $stmt->fetch();
    if (!$row) return false;
    if (strtotime($row['expires_at']) < time()) return false;
    return true;
}

function requestProcessor($req) {
    switch ($req['type']) {
        case "register": return doRegister($req['username'],$req['password']);
        case "login": return doLogin($req['username'],$req['password']);
        case "validate_session": return doValidate($req['sessionId']);
    }
    return "Invalid request";
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");
$server->process_requests('requestProcessor');
?>

