<?php
/**
 * home.php — Authenticated landing page
 *
 * What this script does (plain English):
 * 1) Starts a session so we can identify the current user.
 * 2) If there's no session, sends the user back to the login page (index.html).
 * 3) If there is a session, asks the Auth service over RabbitMQ to confirm the session is still valid.
 * 4) If valid, renders the Home page with a friendly greeting and a Logout button.
 *
 * Security notes:
 * - Always escape user-controlled data before echoing into HTML (we use htmlspecialchars with ENT_QUOTES).
 * - Use strict comparison for the validation boolean.
 * - Call exit immediately after header redirects.
 * - Consider adding a CSRF token to the logout POST.
 */

// --- Load RabbitMQ client dependencies (ensure these files exist on your server) ---
require_once('path.inc');            // Path helpers used by the RabbitMQ library
require_once('get_host_info.inc');   // Resolves host info for the MQ connection
require_once('rabbitMQLib.inc');     // RabbitMQ PHP client wrapper

// --- Start or resume the session so we can read session variables ---
session_start();

// --- Quick gate: if the required session keys are missing, bounce to login ---
// The login flow should set both: $_SESSION['username'] and $_SESSION['session_id'].
if (!isset($_SESSION['username']) || !isset($_SESSION['session_id'])) {
    header('Location: index.html'); // Not logged in → back to login page
    exit;                           // Stop executing this script after redirect
}

// 1) Create a RabbitMQ client instance ("testServer" section in testRabbitMQ.ini)
$client = new rabbitMQClient('testRabbitMQ.ini', 'testServer');

// 2) Prepare the request we send to the auth service to validate this session
$validation_request = [
    'type'      => 'validate_session', // Your auth server routes based on this field
    'sessionId' => $_SESSION['session_id']
];

// 3) Send the request and receive the response from the auth service
// Expectation: boolean true when valid, false when invalid/expired.
$is_session_valid = $client->send_request($validation_request);

// 4) Handle the validation result
if ($is_session_valid !== true) {
    // Session is invalid (expired or revoked). Clean up and redirect to login.
    session_unset();
    session_destroy();
    header('Location: index.html?error=session_expired');
    exit;
}

// --- At this point, the session is valid. Prepare display-safe username. ---
// ENT_QUOTES ensures single & double quotes are escaped; set charset explicitly.
$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home - Welcome <?php echo $username; ?></title>
    <style>
        /* Simple, readable styling for the demo page */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f9f9f9;
        }

        /* Navbar */
        .navbar {
            background-color: #004080; /* Deep blue header */
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

        /* Main content card */
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
        p  { color: #333; font-size: 16px; }

        .logout-btn {
            margin-top: 30px;
            background-color: #d9534f; /* Bootstrap-like danger red */
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }
        .logout-btn:hover { background-color: #c9302c; }
    </style>
</head>
<body>

<!-- Top navigation bar -->
<div class="navbar">
    <div class="logo">
        <!-- Swap logo.png with your real logo path; keep alt text for accessibility -->
        <img src="logo.png" alt="Company Logo">
        <h1>MyCompany</h1>
    </div>
    <div class="menu">
        <!-- If these pages require auth too, repeat the same session check on them -->
        <a href="home.php">Home</a>
        <a href="recommendations.php">Recommendations</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<!-- Main content body -->
<div class="container">
    <h2>Welcome, <?php echo $username; ?>!</h2>
    <p>You are successfully signed in.</p>
    <p>Use the navigation bar above to explore your dashboard, view recommendations, or manage your account.</p>

    <!--
      Logout form:
      - Uses POST so it can't be triggered by a simple link preload.
      - For stronger security, add a CSRF token here and validate it in logout.php.
    -->
    <form action="logout.php" method="POST">
        <button type="submit" class="logout-btn">Log Out</button>
    </form>
</div>

<!-- ===== Job Alerts (added) ===== -->
<div class="container" style="max-width:600px;margin:30px auto 80px;">
  <h2 style="color:#004080;margin-bottom:10px;">Job Alerts</h2>
  <p style="color:#333;font-size:16px;margin-top:0;">
    Get an email or text whenever new jobs match your interests.
  </p>

  <!-- Posts to a new PHP endpoint you'll create: save_alert_prefs.php -->
  <form action="save_alert_prefs.php" method="POST" style="text-align:left;">
    <fieldset style="border:1px solid #eee;padding:12px;border-radius:8px;margin-bottom:14px;">
      <legend style="font-weight:bold;color:#004080;">Notify me via</legend>
      <label style="display:block;margin:8px 0;">
        <input type="checkbox" name="email_enabled" value="1"> Email
      </label>
      <input type="email" name="email" placeholder="you@example.com"
             style="display:block;margin:6px 0 12px;width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">

      <label style="display:block;margin:8px 0;">
        <input type="checkbox" name="sms_enabled" value="1"> Text (SMS)
      </label>
      <input type="tel" name="phone" placeholder="+1 555 123 4567"
             style="display:block;margin:6px 0 12px;width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
    </fieldset>

    <fieldset style="border:1px solid #eee;padding:12px;border-radius:8px;margin-bottom:14px;">
      <legend style="font-weight:bold;color:#004080;">Filters (optional)</legend>
      <label>Keywords (comma separated)</label>
      <input type="text" name="keywords" placeholder="IT, Security, DevOps"
             style="display:block;margin:6px 0 12px;width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
      <label>Location</label>
      <input type="text" name="location" placeholder="NYC or Remote"
             style="display:block;margin:6px 0 12px;width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
    </fieldset>

    <button type="submit" class="logout-btn" style="background:#004080;margin-top:10px;">Save preferences</button>
  </form>

  <!-- Optional success message after redirect -->
  <?php if (isset($_GET['prefs_saved']) && $_GET['prefs_saved'] == '1'): ?>
    <div style="margin-top:10px;color:#2e7d32;">Preferences saved.</div>
  <?php endif; ?>
</div>
<!-- ===== End Job Alerts (added) ===== -->

</body>
</html>

