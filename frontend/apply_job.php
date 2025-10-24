<?php
session_start();
require_once('api_rabbitmq_client.php');

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['status'=>'error','message'=>'Not logged in.']);
    exit;
}

$username = $_SESSION['username'];
$position_id = trim($_POST['job_id'] ?? '');

if ($position_id === '') {
    echo json_encode(['status'=>'error','message'=>'Missing job ID.']);
    exit;
}

$resume_request = ['type' => 'get_resume_path', 'username' => $username];
$resume_response = mq_request($resume_request);

$resume_path = null;
if (is_array($resume_response) && !empty($resume_response['file_path'])) 
{
	$resume_path = $resume_response['file_path'];
}

$applyResp = mq_request([
    'type'=>'apply_job',
    'username'=>$username,
    'position_id'=>$position_id,
    'resume_path'=>$resume_path
]);

if ($applyResp === true || (is_array($applyResp) && isset($applyResp['status']) && $applyResp['status']=='success')) {
    echo json_encode(['status'=>'success','message'=>'Application recorded.']);
    exit;
}

$msg = 'Application failed.';
if (is_array($applyResp) && isset($applyResp['message'])) $msg = $applyResp['message'];
echo json_encode(['status'=>'error','message'=>$msg]);
?>
