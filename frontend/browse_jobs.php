<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}

// 1. Check for the 'employer' GET parameter (passed from employer.php)
$employer_filter = null;
$page_title = "All Openings";

if (isset($_GET['employer']) && !empty($_GET['employer'])) {
    // Sanitize and set the filter if it exists
    $employer_filter = htmlspecialchars($_GET['employer']); 
    $page_title = "Postings by: " . $employer_filter;
}

// 2. Build the RabbitMQ request payload
$request = [
    'type' => 'get_jobs', 
    'scope' => 'browse', // Default scope
    'organization' => $employer_filter // Pass the filter (will be null or the username)
];

// 3. Send the request
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
    <h2><?php echo $page_title; ?></h2>
<?php
if (!$response || empty($response) || is_string($response)) { // Check for error message strings too
    echo "<p>No postings available or an error occurred.</p>";
} else {
    foreach ($response as $job) {
        $title = htmlspecialchars($job['title'] ?? 'Untitled Position');
        $employer = htmlspecialchars($job['organization'] ?? 'N/A');
        $loc = htmlspecialchars($job['location'] ?? 'N/A');
        $dateposted = htmlspecialchars($job['date_posted'] ?? 'N/A');
        $apply_uri = htmlspecialchars($job['apply_uri'] ?? '#'); // Using the 'apply_uri' field
        $position_id = htmlspecialchars($job['position_id'] ?? 'N/A');
        $qualification_summary = htmlspecialchars($job['qualification_summary'] ?? 'No summary provided.');
        $major_duties = htmlspecialchars($job['major_duties'] ?? 'No major duties listed.');
        echo "<div class='job'>";
        echo "<h3>{$title}</h3>";
        echo "<p><strong>Employer:</strong> {$employer} &nbsp; <strong>Location:</strong> {$loc}</p>";
        echo "<p><strong>Date Posted:</strong> {$dateposted}</p>";

        // Display additional details in a dedicated section
        echo "<div class='job-details'>";
        echo "<p><strong>ID:</strong> {$position_id}</p>";

        echo "<h4>Qualifications</h4>";
        echo "<p>" . nl2br(substr($qualification_summary, 0, 600)) . (strlen($qualification_summary) > 600 ? "..." : "") . "</p>";

        echo "<h4>Major Duties</h4>";
        echo "<p>" . nl2br(substr($major_duties, 0, 600)) . (strlen($major_duties) > 600 ? "..." : "") . "</p>";
        echo "</div>"; // end job-details

        // Use 'apply_uri' for the application link
        echo "<p><a href='{$apply_uri}' target='_blank'>More / Apply Now</a></p>";
        echo "</div>"; // end job
    }
}
?>
    <p><a href="jobseeker.php">Back to Dashboard</a></p>
</body>
</html>
