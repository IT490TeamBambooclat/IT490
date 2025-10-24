<?php
session_start();
require_once('api_rabbitmq_client.php');
if (!isset($_SESSION['username']) || !isset($_GET['file'])) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

$username = $_SESSION['username'];
$filename = basename($_GET['file']);
$upload_dir = "/var/www/uploads/resumes/";
$file_path = $upload_dir . $filename;
if (!file_exists($file_path)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}
$access_request = [
    'type' => 'check_resume_access',
    'username' => $username,
    'file_path' => $filename // Only sending the filename (path in DB)
];
$has_access = mq_request($access_request);

if ($has_access !== true) 
{
    	header("HTTP/1.0 403 Forbidden");
       	exit;
}
$mime = mime_content_type($file_path);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
?>
