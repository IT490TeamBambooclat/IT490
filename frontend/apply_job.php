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
$applyResp = mq_request([
    'type'=>'apply_job',
    'username'=>$username,
    'position_id'=>$position_id
]);

if ($applyResp === true || (is_array($applyResp) && isset($applyResp['status']) && $applyResp['status']=='success')) {
    echo json_encode(['status'=>'success','message'=>'Application recorded.']);
    exit;
}

$msg = 'Application failed.';
if (is_array($applyResp) && isset($applyResp['message'])) $msg = $applyResp['message'];
echo json_encode(['status'=>'error','message'=>$msg]);
?>
