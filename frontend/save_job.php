<?php
session_start();
require_once('api_rabbitmq_client.php');

header('Content-Type: application/json');
if (!isset($_SESSION['username'])) //check if logged-in
{
    echo json_encode(['status' => 'error', 'message' => 'User is not logged in.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['position_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data or missing position ID.']);
    exit;
}

$request = [
    'type' => 'save_job',
    'username' => $_SESSION['username'],
    'position_id' => $_POST['position_id']
];

$response = mq_request($request);

if (isset($response['status']) && $response['status'] === 'success') {
    echo json_encode(['status' => 'success', 'message' => 'Job saved successfully!']);
} else {
    // Check for a specific error message from the backend
    $message = $response['message'] ?? 'Failed to save job. Unknown backend error.';
    echo json_encode(['status' => 'error', 'message' => $message]);
}
?>
