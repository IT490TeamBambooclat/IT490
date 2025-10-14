<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}
$username = htmlspecialchars($_SESSION['username']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Choose Role</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f8; margin:0; }
        .navbar { background:#004080; color:#fff; padding:12px 20px; display:flex; justify-content:space-between; align-items:center;}
        .container { max-width:900px; margin:50px auto; text-align:center;}
        .card { display:inline-block; width:300px; background:#fff; padding:24px; margin:12px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08);}
        button{ background:#004080;color:#fff;padding:10px 14px;border:none;border-radius:6px;cursor:pointer;}
        button:hover{background:#0066cc}
    </style>
</head>
<body>
<div class="navbar">
    <div><img src="logo.png" alt="logo" style="height:36px;vertical-align:middle;"> <strong>MyCompany</strong></div>
    <div>Signed in as <?php echo $username; ?> | <a href="logout.php" style="color:#fff;text-decoration:none;margin-left:10px;">Logout</a></div>
</div>

<div class="container">
    <h2>Choose Your Role</h2>
    <p>Select whether you're a Job Seeker or an Employer. You can switch later from your profile.</p>

    <div class="card">
        <h3>Job Seeker</h3>
        <p>Search & browse jobs, upload your resume, apply to openings.</p>
        <form action="set_role.php" method="POST">
            <input type="hidden" name="role" value="jobseeker">
            <button type="submit">Continue as Job Seeker</button>
        </form>
    </div>

    <div class="card">
        <h3>Employer</h3>
        <p>Post new roles, manage applications, view candidates.</p>
        <form action="set_role.php" method="POST">
            <input type="hidden" name="role" value="employer">
            <button type="submit">Continue as Employer</button>
        </form>
    </div>
</div>
</body>
</html>
