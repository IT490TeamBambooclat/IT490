<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}
if (!isset($_SESSION['role'])) {
    header("Location: role_select.php");
    exit;
}
if ($_SESSION['role'] !== 'jobseeker') {
    header("Location: role_select.php");
    exit;
}
$username = htmlspecialchars($_SESSION['username']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Job Seeker Dashboard</title>
    <style>
        body{font-family:Arial, sans-serif;background:#f5f7fa;margin:0}
        .navbar{background:#004080;color:#fff;padding:12px 20px;display:flex;justify-content:space-between}
        .container{max-width:1000px;margin:30px auto;padding:20px}
        .panel{background:#fff;padding:16px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:16px}
        input[type="text"], textarea, select{width:100%;padding:8px;margin-top:6px;box-sizing:border-box}
        button{background:#004080;color:#fff;padding:10px 14px;border:none;border-radius:6px;cursor:pointer}
        .two-col{display:flex;gap:16px}
        .col{flex:1}
    </style>
</head>
<body>
<div class="navbar">
    <div><img src="logo.png" style="height:34px;vertical-align:middle"> JobConnect </div>
    <div>Hi, <?php echo $username; ?> | <a href="logout.php" style="color:#fff">Logout</a></div>
</div>

<div class="container">
    <h2>Job Seeker Dashboard</h2>

    <div class="panel">
        <h3>Search Jobs (USAJOBS)</h3>
        <form id="searchForm" action="search_jobs.php" method="GET">
            <input type="text" name="q" placeholder="Search job title, keywords, or location..." required>
            <div style="margin-top:10px;">
                <button type="submit">Search</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h3>Browse Jobs (Saved / Local)</h3>
        <form action="browse_jobs.php" method="GET">
            <button type="submit">Browse Openings</button>
        </form>
    </div>

    <div class="panel">
        <h3>Upload Resume</h3>
        <p>Accepted: PDF, DOC, DOCX. Max 3MB.</p>
        <form action="upload_resume.php" method="POST" enctype="multipart/form-data">
            <input type="file" name="resume" required>
            <div style="margin-top:10px;">
                <button type="submit">Upload Resume</button>
            </div>
        </form>
    </div>

</div>
</body>
</html>
