<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employer') {
    header("Location: index.html?error=invalid_role_access");
    exit;
}

$username = $_SESSION['username'];

$request = [
    'type' => 'get_employer_jobs',
    'username' => $username
];

$response = mq_request($request);
$jobs = $response['jobs'] ?? [];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>My Job Postings</title>
<style>
body{font-family:Arial, sans-serif;background:#f6f8fb;margin:0}
.navbar{background:#004080;color:#fff;padding:12px 20px;display:flex;justify-content:space-between}
.container{max-width:1000px;margin:30px auto;padding:20px}
.panel{background:#fff;padding:16px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:16px}
.job-card{border:1px solid #ddd;padding:12px;border-radius:6px;margin-top:10px;background:#fafafa}
.job-card h4{margin:0;color:#004080}
.job-card p{margin:5px 0}
a.button-link{background:#004080;color:white;padding:8px 12px;border-radius:5px;text-decoration:none}
a.button-link:hover{background:#0066cc}
</style>
</head>
<body>
<div class="navbar">
  <div><img src="logo.png" style="height:34px;vertical-align:middle"> JobConnect</div>
  <div>Signed in: <?php echo htmlspecialchars($username); ?> | <a href="employer.php" style="color:#fff">Back to Dashboard</a></div>
</div>

<div class="container">
  <h2>Your Job Postings</h2>

  <?php if (!empty($jobs)): ?>
    <?php foreach ($jobs as $job): ?>
      <div class="job-card">
        <h4><?php echo htmlspecialchars($job['title']); ?></h4>
        <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location'] ?? 'N/A'); ?></p>
        <p><strong>Qualifications:</strong> <?php echo htmlspecialchars($job['qualifications'] ?? ''); ?></p>
        <p><strong>Description:</strong> <?php echo htmlspecialchars($job['description']); ?></p>
        <?php if (!empty($job['external_link'])): ?>
          <p><a href="<?php echo htmlspecialchars($job['external_link']); ?>" target="_blank" class="button-link">View Job</a></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="panel">
      <p>You haven’t posted any jobs yet.</p>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
