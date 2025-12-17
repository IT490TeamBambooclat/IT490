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

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    dlog("Unauthorized access attempt.");
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit;
}

$username = $_SESSION['username'];
$position_id = trim($_POST['job_id'] ?? '');

if ($position_id === '') {
    dlog("Job application failed: Missing job ID for user $username.");
    echo json_encode(['status' => 'error', 'message' => 'Missing job ID.']);
    exit;
}

try {
    $applyResp = mq_request([
        'type' => 'apply_job',
        'username' => $username,
        'position_id' => $position_id
    ]);

    if ($applyResp === true || (is_array($applyResp) && isset($applyResp['status']) && $applyResp['status'] == 'success')) {
        echo json_encode(['status' => 'success', 'message' => 'Application recorded.']);
        exit;
    }

    $msg = 'Application failed.';
    if (is_array($applyResp) && isset($applyResp['message'])) {
        $msg = $applyResp['message'];
    }
    
    dlog("Application error for $username on job $position_id: $msg");
    echo json_encode(['status' => 'error', 'message' => $msg]);

} catch (Exception $e) {
    dlog("Critical application failure for $username: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal server error.']);
}
?>
