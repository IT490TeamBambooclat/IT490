<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}

$username = $_SESSION['username'];

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    header("Location: jobseeker.php?search_error=empty");
    exit;
}

$request = [
    'type' => 'search_jobs_local',
    'query' => $q,
    'page' => intval($_GET['page'] ?? 1),
    'per_page' => intval($_GET['per_page'] ?? 20)
];

$response = mq_request($request);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Search Results for "<?php echo htmlspecialchars($q); ?>"</title>
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
.job-details p {
    font-size: 0.9em;
    color: #555;
    line-height: 1.4;
}
.save-btn, .apply-btn {
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
    <h2>Search results for "<?php echo htmlspecialchars($q); ?>"</h2>

    <div class="top-links">
        <a href="jobseeker.php">🏠 Dashboard</a>
        <a href="view_my_jobs.php">💼 My Saved & Applied Jobs</a>
    </div>

<?php
if (!$response || !is_array($response) || empty($response['results'])) {
    echo "<p>No results found or an error occurred.</p>";
} else {
    foreach ($response['results'] as $job) {
        $position_id = htmlspecialchars($job['position_id'] ?? '');
        $title = htmlspecialchars($job['title'] ?? 'Untitled');
        $org = htmlspecialchars($job['organization'] ?? '');
        $location = htmlspecialchars($job['location'] ?? '');
        $summary = htmlspecialchars($job['summary'] ?? '');
        $link = htmlspecialchars($job['apply_link'] ?? '#');

        echo "<div class='job'>";
        echo "<h3>{$title}</h3>";
        echo "<p><strong>Organization:</strong> {$org} &nbsp; <strong>Location:</strong> {$location}</p>";
        echo "<p>{$summary}</p>";

        echo "<p><a class='apply-btn apply-link' href='{$link}' target='_blank' data-jobid='{$position_id}'>More / Apply Now</a></p>";
        echo "<button class='save-btn' data-position-id='{$position_id}' data-username='{$username}'>Save Job</button>";
        echo "</div>";
    }
}
?>
<script>
document.querySelectorAll('.apply-link').forEach(link => {
    link.addEventListener('click', () => {
        const jobID = link.dataset.jobid;
        if (!jobID) return;
        fetch('apply_job.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'job_id=' + encodeURIComponent(jobID)
        }).catch(err => console.error('Apply tracking failed:', err));
    });
});

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
</script>
</body>
</html>
