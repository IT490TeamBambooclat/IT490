<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}
$username = $_SESSION['username'];

$uploadDir = __DIR__ . '/uploads/resumes';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: jobseeker.php");
    exit;
}

if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
    header("Location: jobseeker.php?upload_error=1");
    exit;
}

$file = $_FILES['resume'];
$originalName = basename($file['name']);

// Basic validation
$allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$maxSize = 3 * 1024 * 1024; // 3MB
if (!in_array($mime, $allowed) || $file['size'] > $maxSize) {
    header("Location: jobseeker.php?upload_error=invalid_type_or_size");
    exit;
}

// Create a safe filename: username + timestamp + random
$safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$filename = $username . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destination = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    header("Location: jobseeker.php?upload_error=move_failed");
    exit;
}

// Send message to RabbitMQ to record resume metadata (do NOT send file bytes over MQ)
$request = [
    'type' => 'save_resume',
    'username' => $username,
    'filename' => $filename,
    'original_name' => $originalName,
    'path' => '/uploads/resumes/' . $filename
];
$response = mq_request($request);

// response expected true/false
if ($response === true) {
    header("Location: jobseeker.php?upload=success");
} else {
    // Optionally log or remove file if backend failed
    error_log("Failed to save resume metadata for $username");
    header("Location: jobseeker.php?upload_error=backend");
}
exit;
?>
