<?php
session_start();
require_once('api_rabbitmq_client.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['username'], $_POST['position_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data.']);
    exit;
}

// Security: Ensure the username from POST matches the active session
if (!isset($_SESSION['username']) || $_SESSION['username'] !== $_POST['username']) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication error or session mismatch.']);
    exit;
}

$request = [
    'type' => 'save_job',
    'username' => $_POST['username'],
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
