<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}

$username = $_SESSION['username'];

$request = ['type' => 'get_saved_jobs', 'username' => $username];
$response = mq_request($request);

if (!is_array($response)) {
    $response = [];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>My Saved Jobs</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f7f9fb;
    margin: 0;
    padding: 20px;
}
.job {
    background: #fff;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
    border-left: 5px solid #004080;
}
.job h3 {
    color: #004080;
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
}
.job p {
    margin: 5px 0;
}
.job .details {
    display: flex;
    gap: 20px;
    font-size: 0.9em;
    color: #555;
}
.apply-btn {
    background-color: #28a745;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 10px;
    text-decoration: none;
    display: inline-block;
}
.apply-btn:hover {
    background-color: #218838;
}
.top-links {
    margin-bottom: 25px;
}
.top-links a {
    text-decoration: none;
    background: #004080;
    color: white;
    padding: 8px 15px;
    border-radius: 4px;
    margin-right: 10px;
    transition: background 0.2s;
}
.top-links a:hover {
    background: #0066cc;
}
</style>
</head>
<body>
    <h2>My Saved Jobs for <?php echo htmlspecialchars($username); ?></h2>

    <div class="top-links">
        <a href="jobseeker.php">🏠 Dashboard</a>
        <a href="browse_jobs.php">🔍 Browse All Jobs</a>
    </div>

<?php
if (empty($response)) {
    echo "<p>You haven't saved any job postings yet. Head to the <a href='browse_jobs.php'>Browse Jobs</a> page to start saving!</p>";
} else {
    foreach ($response as $job) {
        $title = htmlspecialchars($job['title'] ?? 'Untitled');
        $employer = htmlspecialchars($job['organization'] ?? 'N/A');
        $loc = htmlspecialchars($job['location'] ?? 'N/A');
        $dateposted = htmlspecialchars($job['date_posted'] ?? 'Unknown');
        $date_saved = htmlspecialchars($job['date_saved'] ?? 'N/A');
        $external = htmlspecialchars($job['external_link'] ?? '#');
        $position_id = htmlspecialchars($job['position_id'] ?? '');

        echo "<div class='job'>";
        echo "<h3>{$title}</h3>";
        echo "<div class='details'>";
        echo "<p><strong>Employer:</strong> {$employer}</p>";
        echo "<p><strong>Location:</strong> {$loc}</p>";
        echo "<p><strong>Posted:</strong> {$dateposted}</p>";
        echo "<p><strong>Saved On:</strong> {$date_saved}</p>";
        echo "</div>";
        echo "<a href='{$external}' target='_blank' class='apply-btn apply-link' data-jobid='{$position_id}'>View & Apply Externally</a>";
        echo "</div>";
    }
}
?>
<script>
document.querySelectorAll('.apply-link').forEach(link => {
    link.addEventListener('click', (e) => {
        const jobID = link.dataset.jobid;
        if (!jobID) return;
        fetch('apply_job.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'job_id=' + encodeURIComponent(jobID)
        }).catch(err => console.error('Apply tracking failed:', err));
    });
});
</script>
</body>
</html>
