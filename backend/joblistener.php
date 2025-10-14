#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$JOB_QUEUE_SERVER = "DmzInfo"; 

function getPDO() {
    $dsn = "mysql:host=127.0.0.1;dbname=testdb;charset=utf8mb4";
    $user = "testUser";
    $pass = "12345";
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function doIngestJobData($title, $organization, $location, $date_posted) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("INSERT INTO jobs_data 
                            (job_title, organization, location, date_posted) 
                            VALUES (?, ?, ?, ?)");
    
    if ($stmt->execute([$title, $organization, $location, $date_posted])) {
        error_log("SUCCESS: Inserted job: " . $title);
        return true;
    } else {
        error_log("FAILURE: Failed to insert job: " . $title . " - DB Error: " . json_encode($stmt->errorInfo()));
        return false;
    }
}

function requestProcessor($req) {
    error_log("Received job data request: " . json_encode($req));
    
    if (isset($req['type']) && $req['type'] == 'ingest_job_data') {
        $result = doIngestJobData(
            $req['job_title'] ?? 'N/A', 
            $req['organization'] ?? 'N/A', 
            $req['location'] ?? 'N/A',
            $req['date_posted'] ?? null
        );

        if ($result) {
             return ['returnCode' => 0, 'message' => 'Data inserted successfully'];
        } else {
             return ['returnCode' => 1, 'message' => 'Data insertion failed'];
        }
    }
    
    error_log("Unknown request type received in joblistener script: " . json_encode($req));
    return ['returnCode' => 1, 'message' => 'Invalid request type for this listener'];
}

$server = new rabbitMQServer("testRabbitMQ.ini",$JOB_QUEUE_SERVER);

error_log("Job Data Listener started and waiting for messages on queue: " . $JOB_QUEUE_SERVER . "...");
$server->process_requests('requestProcessor');
?>
