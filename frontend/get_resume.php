<?php
session_start();
require_once('api_rabbitmq_client.php');

function dlog($error)
{
    error_log($error);
    try
    {
        $client = new rabbitMQClient("testRabbitMQ.ini", 'DLogging');
        $request = [
            'type' => 'dlog',
            'timestamp' => date('Y-m-d H:i:s'),
            'source_host' => gethostname(),
            'message' => $error
        ];
        $client->publish($request);
    }
    catch (Exception $e)
    {
        error_log("DLogging Failed" . $e->getMessage());
    }
}

if (!isset($_SESSION['username']) || !isset($_GET['file'])) {
    dlog("Unauthorized access attempt or missing file parameter.");
    header("HTTP/1.0 403 Forbidden");
    exit;
}

$username = $_SESSION['username'];
$filename = basename($_GET['file']);
$upload_dir = "/var/www/uploads/resumes/";
$file_path = $upload_dir . $filename;

if (!file_exists($file_path)) {
    dlog("File not found: " . $file_path);
    header("HTTP/1.0 404 Not Found");
    exit;
}

$access_request = [
    'type' => 'check_resume_access',
    'username' => $username,
    'file_path' => $filename
];

$has_access = mq_request($access_request);

if ($has_access !== true) 
{
    dlog("Access denied for user $username to file $filename");
    header("HTTP/1.0 403 Forbidden");
    exit;
}

$mime = mime_content_type($file_path);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
?>
