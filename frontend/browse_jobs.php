<?php
session_start();
require_once('api_rabbitmq_client.php');

// Ensure the user is logged in before showing jobs
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}

$username = $_SESSION['username'];

// Ask RabbitMQ for job listings
$request = ['type' => 'get_jobs', 'scope' => 'all'];
$response = mq_request($request);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Browse Jobs</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f7f9fb;
    margin: 0;
    padding: 20px;
}
.job {
    background: #fff;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.save-btn, .apply-btn {
    background-color: #004080;
    color: white;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
    cursor: pointer;
    margin-right: 8px;
}
.save-btn:hover, .apply-btn:hover {
    background-color: #0066cc;
}
.top-links {
    margin-bottom: 20px;
}
.top-links a {
    text-decoration: none;
    background: #004080;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    margin-right: 10px;
}
.top-links a:hover {
    background: #0066cc;
}
</style>
</head>
<body>
    <h2>Available Job Openings</h2>

    <div class="top-links">
        <a href="jobseeker.php">🏠 Dashboard</a>
        <a href="view_my_jobs.php">💼 My Saved & Applied Jobs</a>
    </div>

<?php
if (!$response || empty($response)) {
    echo "<p>No job postings available at the moment.</p>";
} else {
    foreach ($response as $job) {
        $position_id = htmlspecialchars($job['position_id'] ?? '');
        $title = htmlspecialchars($job['title'] ?? 'Untitled');
        $employer = htmlspecialchars($job['organization'] ?? 'N/A');
        $loc = htmlspecialchars($job['location'] ?? 'N/A');
        $dateposted = htmlspecialchars($job['date_posted'] ?? 'Unknown');
        $external = htmlspecialchars($job['external_link'] ?? '#');

        echo "<div class='job'>";
        echo "<h3>{$title}</h3>";
        echo "<p><strong>Employer:</strong> {$employer} &nbsp; <strong>Location:</strong> {$loc}</p>";
        echo "<p><strong>Posted:</strong> {$dateposted}</p>";
        echo "<p><a href='{$external}' target='_blank' class='apply-link' data-jobid='{$position_id}'>More / Apply</a></p>";

        echo "<button class='save-btn'
                data-position-id='{$position_id}'
                data-title='{$title}'
                data-org='{$employer}'
                data-location='{$loc}'
                data-date='{$dateposted}'>
                Save Job
              </button>";

        echo "</div>";
    }
}
?>
<script>
// SAVE job button
document.querySelectorAll('.save-btn').forEach(button => {
    button.addEventListener('click', () => {
        const formData = new FormData();
        formData.append('position_id', button.dataset.positionId);
        formData.append('job_title', button.dataset.title);
        formData.append('organization', button.dataset.org);
        formData.append('location', button.dataset.location);
        formData.append('date_posted', button.dataset.date);

        fetch('save_job.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
        })
        .catch(err => {
            console.error(err);
            alert('Error saving job.');
        });
    });
});

// APPLY link tracking
document.querySelectorAll('.apply-link').forEach(link => {
    link.addEventListener('click', (e) => {
        const jobID = link.dataset.jobid;
        if (!jobID) return;

        // Record application
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
