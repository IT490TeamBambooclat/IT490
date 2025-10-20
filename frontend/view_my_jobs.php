<?php
session_start();
require_once('api_rabbitmq_client.php');

// Ensure the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}

$username = $_SESSION['username'];

// Ask RabbitMQ for the user's saved job listings
// This relies on the 'get_saved_jobs' case being implemented in the backend server.
$request = ['type' => 'get_saved_jobs', 'username' => $username];
$response = mq_request($request);

// Ensure $response is an array even if empty or failed
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
    border-left: 5px solid #004080; /* Highlight for saved job */
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
        // 'external_link' is the 'apply_uri' column joined from jobs_data
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
        
        // Use apply_uri from jobs_data as the primary link
        echo "<a href='{$external}' target='_blank' class='apply-btn'>View & Apply Externally</a>";
        
        // Optional: Add a button to remove the saved job (requires another backend function)
        // echo "<button class='remove-btn' data-position-id='{$position_id}' data-username='{$username}'>Remove</button>";
        
        echo "</div>";
    }
}
?>
<!-- No JavaScript needed for this basic display view -->
</body>
</html>
