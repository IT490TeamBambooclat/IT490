<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employer') {
    header("Location: role_select.php");
    exit;
}
$username = htmlspecialchars($_SESSION['username']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Employer Dashboard</title>
<style>
body{font-family:Arial, sans-serif;background:#f6f8fb;margin:0}
.navbar{background:#004080;color:#fff;padding:12px 20px;display:flex;justify-content:space-between}
.container{max-width:1000px;margin:30px auto;padding:20px}
.panel{background:#fff;padding:16px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:16px}
input, textarea{width:100%;padding:8px;margin-top:6px;box-sizing:border-box}
button{background:#004080;color:#fff;padding:10px 14px;border:none;border-radius:6px;cursor:pointer}
</style>
</head>
<body>
<div class="navbar">
    <div><img src="logo.png" style="height:34px;vertical-align:middle"> MyCompany</div>
    <div>Signed in: <?php echo $username; ?> | <a href="logout.php" style="color:#fff">Logout</a></div>
</div>

<div class="container">
    <h2>Employer Dashboard</h2>

    <div class="panel">
        <h3>Create a New Job Posting</h3>
        <form action="post_job.php" method="POST">
            <label>Job Title</label>
            <input type="text" name="title" required>
            <label>Location</label>
            <input type="text" name="location">
            <label>Salary / Compensation</label>
            <input type="text" name="salary">
            <label>External Link (application page)</label>
            <input type="text" name="external_link">
            <label>Job Description</label>
            <textarea name="description" rows="6" required></textarea>
            <div style="margin-top:10px;">
                <button type="submit">Post Job</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h3>Your Openings</h3>
        <form action="browse_jobs.php" method="GET">
            <input type="hidden" name="employer" value="<?php echo $username; ?>">
            <button type="submit">View My Postings</button>
        </form>
    </div>
</div>
</body>
</html>
