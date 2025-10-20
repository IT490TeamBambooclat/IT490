<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['session_id'])) {
    header('Location: index.html');
    exit;
}

$client = new rabbitMQClient('testRabbitMQ.ini', 'testServer');
$validation_request = [
    'type' => 'validate_session',
    'sessionId' => $_SESSION['session_id']
];
$is_session_valid = $client->send_request($validation_request);

if ($is_session_valid !== true) {
    session_unset();
    session_destroy();
    header('Location: index.html?error=session_expired');
    exit;
}

$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$prefs_file = __DIR__ . '/data/alert_prefs.json';
$email_enabled = 0;
$email_value = '';

if (file_exists($prefs_file)) {
    $data = json_decode(file_get_contents($prefs_file), true);
    if (isset($data[$_SESSION['username']])) {
        $email_enabled = $data[$_SESSION['username']]['email_enabled'] ?? 0;
        $email_value = $data[$_SESSION['username']]['email'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home - Welcome <?php echo $username; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f9f9f9;
        }
        .navbar {
            background-color: #004080;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 25px;
        }
        .navbar .logo { display: flex; align-items: center; }
        .navbar .logo img { height: 40px; margin-right: 10px; }
        .navbar h1 { margin: 0; font-size: 22px; }
        .navbar .menu a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            font-weight: bold;
        }
        .navbar .menu a:hover { text-decoration: underline; }
        .container {
            max-width: 600px;
            margin: 80px auto;
            background-color: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        h2 { color: #004080; margin-bottom: 10px; }
        p { color: #333; font-size: 16px; }
        .logout-btn {
            margin-top: 30px;
            background-color: #d9534f;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }
        .logout-btn:hover { background-color: #c9302c; }
        .email-alerts {
            margin-top: 30px;
            text-align: left;
        }
        .email-alerts input[type="email"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            box-sizing: border-box;
        }
        .email-alerts button {
            background-color: #004080;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .email-alerts button:hover { background-color: #0066cc; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">
        <img src="logo.png" alt="Company Logo">
        <h1>MyCompany</h1>
    </div>
    <div class="menu">
        <a href="home.php">Home</a>
        <a href="recommendations.php">Recommendations</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h2>Welcome, <?php echo $username; ?>!</h2>
    <p>You are successfully signed in.</p>
    <p>Use the navigation bar above to explore your dashboard, view recommendations, or manage your account.</p>

    <div class="email-alerts">
        <h3>Email Alert Preferences</h3>
        <form action="save_alert_prefs.php" method="POST">
            <label>
                <input type="checkbox" name="alerts_email_enabled" value="1" <?php if($email_enabled) echo 'checked'; ?>>
                Enable Email Alerts
            </label>
            <input type="email" name="alerts_email" value="<?php echo htmlspecialchars($email_value); ?>" placeholder="you@example.com">
            <button type="submit">Save</button>
        </form>
    </div>

    <form action="logout.php" method="POST">
        <button type="submit" class="logout-btn">Log Out</button>
    </form>
</div>

</body>
</html>

