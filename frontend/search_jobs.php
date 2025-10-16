<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    header("Location: jobseeker.php?search_error=empty");
    exit;
}

// prepare request for MQ consumer that will call USAJOBS API
$request = [
    'type' => 'search_jobs_local',
    'query' => $q,
    'page' => intval($_GET['page'] ?? 1),
    'per_page' => intval($_GET['per_page'] ?? 20)
];

$response = mq_request($request);

// We expect $response to be an associative array with 'results' and possibly pagination.
// If you prefer to display results here, render them.
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Search Results for "<?php echo htmlspecialchars($q); ?>"</title>
    <style>
        body{font-family:Arial, sans-serif;background:#f7f9fb;margin:0;padding:20px}
        .job{background:#fff;padding:12px;margin-bottom:10px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
        a.apply{background:#004080;color:#fff;padding:8px 10px;border-radius:6px;text-decoration:none}
    </style>
</head>
<body>
    <h2>Search results for "<?php echo htmlspecialchars($q); ?>"</h2>

<?php
if (!$response || !is_array($response) || empty($response['results'])) {
    echo "<p>No results found or an error occurred.</p>";
} else {
    foreach ($response['results'] as $job) {
        // job fields depend on your MQ consumer mapping from USAJOBS
        $title = htmlspecialchars($job['title'] ?? 'Untitled');
        $org = htmlspecialchars($job['organization'] ?? '');
        $location = htmlspecialchars($job['location'] ?? '');
        $summary = htmlspecialchars($job['summary'] ?? '');
        $link = htmlspecialchars($job['apply_link'] ?? '#');

        echo "<div class='job'>";
        echo "<h3>{$title}</h3>";
        echo "<p><strong>Organization:</strong> {$org} &nbsp; <strong>Location:</strong> {$location}</p>";
        echo "<p>{$summary}</p>";
        echo "<p><a class='apply' href='{$link}' target='_blank'>View / Apply</a></p>";
        echo "</div>";
    }
}
?>
    <p><a href="jobseeker.php">Back to Dashboard</a></p>
</body>
</html>
