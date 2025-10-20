<?php
// Displays job listings from the API and lets logged-in users save jobs.

session_start();
require_once('api_rabbitmq_client.php');

// Ensure the user is logged in before showing jobs
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}

// Ask RabbitMQ for job listings (the backend consumer fetches from DB/API)
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
.save-btn {
    background-color: #004080;
    color: white;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
    cursor: pointer;
}
.save-btn:hover {
    background-color: #0066cc;
}
</style>
</head>
<body>
    <h2>Available Job Openings</h2>

<?php
// Check if we got any jobs back from the MQ request
if (!$response || empty($response)) {
    echo "<p>No job postings available at the moment.</p>";
} else {
    foreach ($response as $job) {
        // Safely extract data with defaults
        $position_id = htmlspecialchars($job['position_id'] ?? '');
        $title = htmlspecialchars($job['title'] ?? 'Untitled');
        $employer = htmlspecialchars($job['organization'] ?? 'N/A');
        $loc = htmlspecialchars($job['location'] ?? 'N/A');
        $dateposted = htmlspecialchars($job['date_posted'] ?? 'Unknown');
        $external = htmlspecialchars($job['external_link'] ?? '#');

        // Output job info with Save button
        echo "<div class='job'>";
        echo "<h3>{$title}</h3>";
        echo "<p><strong>Employer:</strong> {$employer} &nbsp; <strong>Location:</strong> {$loc}</p>";
        echo "<p><strong>Posted:</strong> {$dateposted}</p>";
        echo "<p><a href='{$external}' target='_blank'>More / Apply</a></p>";

        // Save Job button — sends job info to backend/save_job.php
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
    <p><a href='jobseeker.php'>Back to Dashboard</a></p>

<script>
// Sends job data to backend/save_job.php when the "Save Job" button is clicked.

document.querySelectorAll('.save-btn').forEach(button => {
    button.addEventListener('click', () => {
        const formData = new FormData();
        formData.append('position_id', button.dataset.positionId);
        formData.append('job_title', button.dataset.title);
        formData.append('organization', button.dataset.org);
        formData.append('location', button.dataset.location);
        formData.append('date_posted', button.dataset.date);

        // Send data to backend using fetch()
        fetch('../backend/save_job.php', {
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
</script>

</body>
</html>

