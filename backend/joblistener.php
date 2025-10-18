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

// Updated function signature to accept new fields
function doIngestJobData($position_id, $title, $organization, $location, $date_posted, $apply_uri, $qualification_summary, $major_duties) {
    
    $pdo = getPDO();
    
    // Updated SQL to include new columns
    $sql = "INSERT INTO jobs_data 
            (position_id, job_title, organization, location, date_posted, apply_uri, qualification_summary, major_duties) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            job_title = VALUES(job_title), 
            organization = VALUES(organization),
            location = VALUES(location),
            date_posted = VALUES(date_posted),
            apply_uri = VALUES(apply_uri),
            qualification_summary = VALUES(qualification_summary),
            major_duties = VALUES(major_duties),
            ingestion_date = NOW();";

    $stmt = $pdo->prepare($sql);
    
    // Updated execute call with new variables
    if ($stmt->execute([$position_id, $title, $organization, $location, $date_posted, $apply_uri, $qualification_summary, $major_duties])) {
        error_log("SUCCESS: Processed job ID: " . $position_id);
        return true;
    } else {
        error_log("FAILURE: Failed to process job ID: " . $position_id . " - DB Error: " . json_encode($stmt->errorInfo()));
        return false;
    }
}

function requestProcessor($req) {
    error_log("Received job data request: " . json_encode($req));
    
    if (isset($req['type']) && $req['type'] == 'ingest_job_data') {
        
        // Updated function call to pass new data from the request
        $result = doIngestJobData(
            $req['position_id'] ?? null,
            $req['job_title'] ?? 'N/A', 
            $req['organization'] ?? 'N/A', 
            $req['location'] ?? 'N/A',
            $req['date_posted'] ?? null,
            $req['apply_uri'] ?? 'N/A',
            $req['qualification_summary'] ?? 'N/A',
            $req['major_duties'] ?? 'N/A'
        );

        if ($result) {
             return ['returnCode' => 0, 'message' => 'Data ingested successfully'];
        } else {
             return ['returnCode' => 1, 'message' => 'Database ingestion failed'];
        }
    }
    
    error_log("Unknown request type received in job_listener: " . json_encode($req));
    return ['returnCode' => 1, 'message' => 'Invalid request type for this listener'];
}

$server = new rabbitMQServer("testRabbitMQ.ini", $JOB_QUEUE_SERVER);

error_log("Job Data Listener started and waiting for messages on queue: " . $JOB_QUEUE_SERVER . "...");
$server->process_requests('requestProcessor');
?>
