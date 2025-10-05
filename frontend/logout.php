<?php
session_start();

// 1. Unset all session variables
$_SESSION = array();

// 2. Destroy the session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// 3. Redirect to the login page (index.html)
header("Location: index.html");
exit;
?>
