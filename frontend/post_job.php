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
$qualifications = trim($_POST['qualifications'] ?? '');
$external_link = trim($_POST['external_link'] ?? '');
$major_duties = trim($_POST['description'] ?? '');

if ($title === '' || $major_duties === '') {
    header("Location: employer.php?error=required");
    exit;
}
$position_id='LOCAL-'.uniqid();
// Build payload
$request = [
    'type' => 'post_job',
    'employer' => $employer,
    'title' => $title,
    'location' => $location,
    'qualifications' => $qualifications,
    'external_link' => $external_link,
    'description' => $major_duties,
    'posted_at' => date(DATE_ATOM),
    'position_id'=>$position_id
];

$response = mq_request($request);

if ($response === true) {
    header("Location: employer.php?post=success");
} else {
    header("Location: employer.php?post=failed");
}
exit;
?>
