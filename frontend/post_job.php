<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'employer') {
    header("Location: index.html");
    exit;
}

$employer = $_SESSION['username'];
$title = trim($_POST['title'] ?? '');
$location = trim($_POST['location'] ?? '');
$salary = trim($_POST['salary'] ?? '');
$external_link = trim($_POST['external_link'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($title === '' || $description === '') {
    header("Location: employer.php?error=required");
    exit;
}

// Build payload
$request = [
    'type' => 'post_job',
    'employer' => $employer,
    'title' => $title,
    'location' => $location,
    'salary' => $salary,
    'external_link' => $external_link,
    'description' => $description,
    'posted_at' => date(DATE_ATOM)
];

$response = mq_request($request);

if ($response === true) {
    header("Location: employer.php?post=success");
} else {
    header("Location: employer.php?post=failed");
}
exit;
?>
