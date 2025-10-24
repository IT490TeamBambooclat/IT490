<?php
session_start();
require_once('api_rabbitmq_client.php');

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'job_seeker') {
    header("Location: index.html");
    exit;
}

$username = $_SESSION['username'];
$target_dir = "/var/www/uploads/resumes/";
$file_key = 'resume';

if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
    header("Location: jobseeker.php?upload=failed&msg=No file selected or upload error.");
    exit;
}

$file_tmp = $_FILES[$file_key]['tmp_name'];
$file_ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
$allowed_ext = ['pdf', 'doc', 'docx'];

if (!in_array($file_ext, $allowed_ext)) {
    header("Location: jobseeker.php?upload=failed&msg=Invalid file type.");
    exit;
}
if ($_FILES[$file_key]['size'] > 3000000) {
    header("Location: jobseeker.php?upload=failed&msg=File too large.");
    exit;
}

$safe_filename = $username . "_resume_" . time() . "." . $file_ext;
$target_file = $target_dir . $safe_filename;

if (copy($file_tmp, $target_file)) {
    error_log("DEBUG: File copy SUCCESS to " . $target_file);
    @unlink($file_tmp); 
    
    $request = [
        'type' => 'save_resume_path',
        'username' => $username,
        'file_path' => $safe_filename
    ];

    error_log("DEBUG: Sending RabbitMQ request: " . json_encode($request));
    $response = mq_request($request);

    error_log("DEBUG: Received RabbitMQ response: " . print_r($response, true));

    if ($response === true || (is_array($response) && isset($response['status']) && $response['status'] == 'success')) {
        header("Location: jobseeker.php?upload=success");
    } else {
        unlink($target_file); 
        error_log("ERROR: Database save failed for user " . $username);
        header("Location: jobseeker.php?upload=failed&msg=Database save failed.");
    }
} else {
    error_log("FATAL ERROR: File copy FAILED from " . $file_tmp . " to " . $target_file);
    header("Location: jobseeker.php?upload=failed&msg=File move failed on server.");
}
exit;
?>
