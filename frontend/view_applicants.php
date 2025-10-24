<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employer') {
    header("Location: index.html?error=invalid_role");
    exit;
}

$username = $_SESSION['username'];

$request = ['type'=>'get_applicants','username'=>$username];
$response = mq_request($request);
$applicants = [];
if (is_array($response) && isset($response['data'])) $applicants = $response['data'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Applicants - JobConnect</title>
<style>
body{font-family:Arial, sans-serif;background:#f6f8fb;margin:0;padding:20px}
.navbar{background:#004080;color:#fff;padding:12px 20px;display:flex;justify-content:space-between}
.container{max-width:1000px;margin:30px auto;padding:20px}
.table{width:100%;border-collapse:collapse;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
.table th{background:#004080;color:#fff;padding:12px;text-align:left}
.table td{padding:12px;border-top:1px solid #eee}
.no-data{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
a.button-link{display:inline-block;background:#004080;color:white;padding:8px 12px;border-radius:6px;text-decoration:none}
a.button-link:hover{background:#0066cc}
.resume-btn {
    background: #28a745;
    color: white;
    padding: 6px 10px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.9em;
}
.resume-btn:hover {
    background: #218838;
}
</style>
</head>
<body>
<div class="navbar">
    <div><img src="logo.png" style="height:34px;vertical-align:middle"> JobConnect</div>
    <div>Signed in: <?php echo htmlspecialchars($username); ?> | <a href="employer.php" style="color:#fff">Back to Dashboard</a></div>
</div>

<div class="container">
    <h2>Applicants for Your Job Postings</h2>

    <?php if (!empty($applicants)): ?>
    <table class="table" aria-describedby="applicants-table">
        <thead>
            <tr>
                <th>Job Title</th>
                <th>Job ID</th>
                <th>Applicant Username</th>
                <th>Applicant Email</th>
                <th>Applied At</th>
                <th>Resume</th> </tr>
        </thead>
        <tbody>
        <?php foreach ($applicants as $a): ?>
            <tr>
                <td><?php echo htmlspecialchars($a['job_title'] ?? $a['title'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($a['job_id'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($a['applicant_username'] ?? $a['username'] ?? $a['applicant_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($a['email'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($a['applied_at'] ?? ''); ?></td>
                <td>
                    <?php if (!empty($a['resume_path'])): ?>
                        <a href="get_resume.php?file=<?php echo htmlspecialchars($a['resume_path']); ?>" 
                           target="_blank" class="resume-btn">
                            View Resume
                        </a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td> </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="no-data">
        <p>No applicants yet.</p>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
