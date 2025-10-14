<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}

// ask MQ for local/saved jobs
$request = ['type' => 'get_jobs', 'scope' => 'all']; // the consumer should read DB and return array
$response = mq_request($request);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Browse Jobs</title>
<style>
body{font-family:Arial, sans-serif;background:#f7f9fb;margin:0;padding:20px}
.job{background:#fff;padding:12px;margin-bottom:10px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
</style>
</head>
<body>
    <h2>Openings</h2>
<?php
if (!$response || empty($response)) {
    echo "<p>No postings available.</p>";
} else {
    foreach ($response as $job) {
        $title = htmlspecialchars($job['title'] ?? 'Untitled');
        $employer = htmlspecialchars($job['employer'] ?? '');
        $loc = htmlspecialchars($job['location'] ?? '');
        $desc = htmlspecialchars($job['description'] ?? '');
        $external = htmlspecialchars($job['external_link'] ?? '#');
        echo "<div class='job'>";
        echo "<h3>{$title}</h3>";
        echo "<p><strong>Employer:</strong> {$employer} &nbsp; <strong>Location:</strong> {$loc}</p>";
        echo "<p>" . nl2br(substr($desc, 0, 600)) . (strlen($desc) > 600 ? "..." : "") . "</p>";
        echo "<p><a href='{$external}' target='_blank'>More / Apply</a></p>";
        echo "</div>";
    }
}
?>
    <p><a href="jobseeker.php">Back to Dashboard</a></p>
</body>
</html>
