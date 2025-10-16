#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function getPDO() {
    $dsn = "mysql:host=127.0.0.1;dbname=testdb;charset=utf8mb4";
    $user = "testUser";
    $pass = "12345";
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function doRegister($username, $password) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username=?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) return false;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username,password_hash) VALUES (?,?)");
    return $stmt->execute([$username, $hash]);
}

function doLogin($username, $password) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username=?");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) return false;
    $sid = bin2hex(random_bytes(16));
    $exp = date("Y-m-d H:i:s", strtotime("+1 day"));
    $stmt = $pdo->prepare("INSERT INTO sessions (session_id,username,expires_at) VALUES (?,?,?)");
    $stmt->execute([$sid,$username,$exp]);
    return $sid;
}

function doValidate($sid) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT username,expires_at FROM sessions WHERE session_id=?");
    $stmt->execute([$sid]);
    $row = $stmt->fetch();
    if (!$row) return false;
    if (strtotime($row['expires_at']) < time()) return false;
    return true;
}

// CORRECTED FUNCTION: Only uses the columns available in jobs_data table
function doPostJob($title, $organization, $location) {
    $pdo = getPDO();
    // Insert only the fields available in the provided CREATE TABLE script.
    // date_posted is set to today's date, ingestion_date defaults to CURRENT_TIMESTAMP.
    $stmt = $pdo->prepare("INSERT INTO jobs_data 
                            (job_title, organization, location, date_posted) 
                            VALUES (?, ?, ?, CURDATE())");
    // external_link and description are ignored here
    return $stmt->execute([$title, $organization, $location]); 
}


function doGetJobs($scope, $organization = null) {
    $pdo = getPDO();
    
    // Select the necessary fields (title, location, date_posted, organization)
    $select_fields = "job_title as title, organization, location, date_posted";
    
    $sql = "SELECT $select_fields
            FROM jobs_data 
            ORDER BY ingestion_date DESC";
    $params = [];
    
    if ($scope === 'employer' && $organization) {
        $sql = "SELECT $select_fields
                FROM jobs_data 
                WHERE organization = ? 
                ORDER BY ingestion_date DESC";
        $params = [$organization];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function doSearchJobsLocal($query) {
    error_log("Attempting local job search for query: " . $query);
    if (empty($query)) {
        return ['results' => []];
    }
    
    $pdo = getPDO();
    // Use 'title', 'organization', 'location', and a placeholder for 'apply_link' and 'summary'
    // to match the expected structure of the frontend display logic.
    $select_fields = "job_title as title, organization, location, date_posted, CONCAT('ID:', id) as apply_link, 'Local job post.' as summary";
    
    // Use LIKE to find the query string anywhere in the job title or organization.
    $sql = "SELECT $select_fields
            FROM jobs_data
            WHERE job_title LIKE ? OR organization LIKE ?
            ORDER BY ingestion_date DESC";
    
    $search_param = "%" . $query . "%";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$search_param, $search_param]);
    
    // Package results in the expected format
    return ['results' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function requestProcessor($req) {
    if (!isset($req['type'])) {
        error_log("Received request with no 'type' field: " . json_encode($req));
        return "Invalid request: Missing type field";
    }

    error_log("Received request type: " . $req['type']); 
    
    switch ($req['type']) {
        case "register": 
            return doRegister($req['username'],$req['password']);
        case "login": 
            return doLogin($req['username'],$req['password']);
        case "validate_session": 
            return doValidate($req['sessionId']);
            
        case "post_job": 
            // Only using title, organization (from session), and location
            return doPostJob(
                $req['title'] ?? '',
                $req['organization'] ?? '',
                $req['location'] ?? ''
                // Ignored frontend fields: salary, external_link, description
            );
            
        case "get_jobs": 
            $scope = $req['scope'] ?? 'all';
            $organization = $req['organization'] ?? null;
            return doGetJobs($scope, $organization);
            
        default:
            error_log("Unknown request type received: " . $req['type']);
            return "Invalid request: Unknown type"; 
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");
$server->process_requests('requestProcessor');
?>
