<?php
session_start();
require_once('api_rabbitmq_client.php');

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['status'=>'error','message'=>'Not logged in']);
    exit;
}

$username = $_SESSION['username'];
$job_id = trim($_POST['job_id'] ?? '');

if ($job_id === '') {
    echo json_encode(['status'=>'error','message'=>'Missing job id']);
    exit;
}

$emailResp = mq_request(['type'=>'get_user_email','username'=>$username]);
$user_email = '';
if (is_array($emailResp) && isset($emailResp['email'])) {
    $user_email = $emailResp['email'];
} elseif (is_string($emailResp)) {
    $user_email = $emailResp;
}

$checkResp = mq_request(['type'=>'check_existing_application','username'=>$username,'job_id'=>$job_id]);
if (isset($checkResp['exists']) && $checkResp['exists'] === true) {
    echo json_encode(['status'=>'error','message'=>'You have already applied to this job']);
    exit;
}

$applyResp = mq_request(['type'=>'apply_job','username'=>$username,'email'=>$user_email,'job_id'=>$job_id]);
if ($applyResp === true || (is_array($applyResp) && isset($applyResp['status']) && $applyResp['status']=='success')) {
    echo json_encode(['status'=>'success','message'=>'Application recorded']);
    exit;
}

$msg = 'Application failed';
if (is_array($applyResp) && isset($applyResp['message'])) $msg = $applyResp['message'];
echo json_encode(['status'=>'error','message'=>$msg]);
