<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}

$username = $_SESSION['username'];

$employer_filter = null;
$page_title = "All Openings";

if (isset($_GET['employer']) && !empty($_GET['employer'])) {
    $employer_filter = htmlspecialchars($_GET['employer']);
    $page_title = "Postings by: " . $employer_filter;
}

$request = [
    'type' => 'get_jobs',
    'scope' => 'browse',
    'organization' => $employer_filter
];

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
    padding: 18px;
    margin-bottom: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
}
.job h3 {
    margin-top: 0;
    color: #004080;
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
}
.job-details h4 {
    margin: 10px 0 5px 0;
    color: #333;
    font-size: 1em;
}
.job-details p {
    font-size: 0.9em;
    line-height: 1.4;
    color: #555;
}
.save-btn, .apply-btn, .visit-btn {
    background-color: #004080;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    margin-right: 8px;
    text-decoration: none;
    display: inline-block;
    transition: background 0.2s;
}

.apply-btn {
    background-color: #28a745;
}

.save-btn:hover, .visit-btn:hover {
    background-color: #0066cc;
}

.apply-btn:hover {
    background-color: #218838;
}

.top-links {
    margin-bottom: 20px;
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
    <h2>Available Job Openings</h2>

    <div class="top-links">
        <a href="jobseeker.php">🏠 Dashboard</a>
        <a href="view_my_jobs.php">💼 My Saved & Applied Jobs</a>
    </div>

<?php
if (!$response || empty($response) || is_string($response)) {
    echo "<p>No job postings available at the moment or an error occurred.</p>";
} else {
    foreach ($response as $job) {
        $position_id = htmlspecialchars($job['position_id'] ?? '');
        $title = htmlspecialchars($job['title'] ?? 'Untitled');
        $employer = htmlspecialchars($job['organization'] ?? 'N/A');
        $loc = htmlspecialchars($job['location'] ?? 'N/A');
        $dateposted = htmlspecialchars($job['date_posted'] ?? 'Unknown');
        $external = htmlspecialchars($job['apply_uri'] ?? '#');
        $qualification_summary = htmlspecialchars($job['qualification_summary'] ?? 'No summary provided.');
        $major_duties = htmlspecialchars($job['major_duties'] ?? 'No major duties listed.');

        echo "<div class='job'>";
        echo "<h3>{$title}</h3>";
        echo "<p><strong>Employer:</strong> {$employer} &nbsp; <strong>Location:</strong> {$loc}</p>";
        echo "<p><strong>Posted:</strong> {$dateposted}</p>";
        echo "<div class='job-details'>";
        echo "<p style='font-size:0.8em; color:#999;'><strong>Job ID:</strong> {$position_id}</p>";
        echo "<h4>Qualifications Summary</h4>";
        echo "<p>" . nl2br(substr($qualification_summary, 0, 400)) . (strlen($qualification_summary) > 400 ? "..." : "") . "</p>";
        echo "<h4>Major Duties</h4>";
        echo "<p>" . nl2br(substr($major_duties, 0, 400)) . (strlen($major_duties) > 400 ? "..." : "") . "</p>";
        echo "</div>";
        
        echo "<button class='apply-btn track-apply-btn' data-jobid='{$position_id}' data-uri='{$external}'>Mark as Applied</button>";
        echo "<a href='{$external}' target='_blank' class='visit-btn'>Visit Website</a>";
        echo "<button class='save-btn' data-position-id='{$position_id}' data-username='{$username}'>Save Job</button>";
        
        echo "</div>";
    }
}
?>
<script>
document.querySelectorAll('.save-btn').forEach(button => {
    button.addEventListener('click', () => {
        const formData = new FormData();
        formData.append('username', button.dataset.username);
        formData.append('position_id', button.dataset.positionId);
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

document.querySelectorAll('.track-apply-btn').forEach(button => {
    button.addEventListener('click', (e) => {
        const jobID = button.dataset.jobid;

        if (!jobID) return;

        fetch('apply_job.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'job_id=' + encodeURIComponent(jobID)
        })
        .then(res => {
            if (res.ok) {
                alert('Job marked as Applied! You can visit the website using the "Visit Website" button if you haven\'t already.');
                button.disabled = true;
                button.textContent = 'Applied (Tracked)';
            } else {
                console.error('Apply tracking failed with status:', res.status);
                alert('Tracking failed, but you can try again or apply directly using the "Visit Website" button.');
            }
        })
        .catch(err => {
            console.error('Apply tracking failed:', err);
            alert('A network error occurred while tracking the application.');
        });
    });
});
</script>
</body>
</html>
