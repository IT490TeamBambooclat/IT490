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


$request = [
    'type' => 'get_applicants_by_employer',
    'employer' => $username
];

$response = send_request($request); 

$applicants = [];
if (isset($response['status']) && $response['status'] === 'success') {
    $applicants = $response['data'];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Applicants - JobConnect</title>
<style>
body{font-family:Arial, sans-serif;background:#f6f8fb;margin:0}
.navbar{background:#004080;color:#fff;padding:12px 20px;display:flex;justify-content:space-between}
.container{max-width:1000px;margin:30px auto;padding:20px}
.panel{background:#fff;padding:16px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:16px}
table{width:100%;border-collapse:collapse;margin-top:10px}
th, td{border:1px solid #ddd;padding:10px;text-align:left}
th{background:#004080;color:white}
a.button-link {
    display:inline-block;
    background:#004080;
    color:white;
    padding:8px 12px;
    border-radius:6px;
    text-decoration:none;
    font-weight:bold;
}
a.button-link:hover{background:#0066cc}
</style>
</head>
<body>
<div class="navbar">
    <div><img src="logo.png" style="height:34px;vertical-align:middle"> JobConnect</div>
    <div>Signed in: <?php echo $username; ?> | <a href="logout.php" style="color:#fff">Logout</a></div>
</div>

<div class="container">
    <h2>Applicants for Your Job Postings</h2>

    <?php if (!empty($applicants)): ?>
        <table>
            <tr>
                <th>Applicant Name</th>
                <th>Job Title</th>
                <th>Email</th>
                <th>Resume</th>
                <th>Message</th>
            </tr>
            <?php foreach ($applicants as $app): ?>
                <tr>
                    <td><?php echo htmlspecialchars($app['applicant_name']); ?></td>
                    <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                    <td><?php echo htmlspecialchars($app['email']); ?></td>
                    <td>
                        <?php if (!empty($app['resume_link'])): ?>
                            <a href="<?php echo htmlspecialchars($app['resume_link']); ?>" target="_blank">View Resume</a>
                        <?php else: ?>
                            No Resume
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($app['message'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No applicants yet for your job postings.</p>
    <?php endif; ?>

    <a href="employer.php" class="button-link">⬅ Back to Dashboard</a>
</div>

<?php include('chat_widget.php'); ?>

</body>
</html>
