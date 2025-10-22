#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
function doGetSavedJobs($username) {
    $pdo = getPDO();
    
    // SQL JOIN statement: Select details from jobs_data (jd) and the date_saved from saved_jobs (sj)
    $sql = "SELECT 
                jd.job_title AS title, 
                jd.organization, 
                jd.location, 
                jd.date_posted, 
                jd.apply_uri AS external_link,
                jd.position_id,
                sj.date_saved
            FROM saved_jobs sj
            JOIN jobs_data jd ON sj.position_id = jd.position_id
            WHERE sj.username = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    
    // Return all resulting rows
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function doSaveJob($username, $position_id) {
    $pdo = getPDO();
    
    // 1. Check if the job actually exists in jobs_data
    $stmt = $pdo->prepare("SELECT position_id FROM jobs_data WHERE position_id = ?");
    $stmt->execute([$position_id]);
    if (!$stmt->fetch()) {
        return ['status' => 'error', 'message' => 'Job ID not found in database.'];
    }

    // 2. Check if the job is already saved by the user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_jobs WHERE username = ? AND position_id = ?");
    $stmt->execute([$username, $position_id]);
    if ($stmt->fetchColumn() > 0) {
        return ['status' => 'error', 'message' => 'This job is already saved.'];
    }

    // 3. Insert the saved job record (only username and position_id)
    $stmt = $pdo->prepare("INSERT INTO saved_jobs 
                            (username, position_id, date_saved) 
                            VALUES (?, ?, NOW())");
                            
    $success = $stmt->execute([$username, $position_id]);

    if ($success) {
        return ['status' => 'success', 'message' => 'Job saved. You can view it in your dashboard.'];
    } else {
        return ['status' => 'error', 'message' => 'Database insert failed.'];
    }
}
function getPDO() {
    $dsn = "mysql:host=127.0.0.1;dbname=testdb;charset=utf8mb4";
    $user = "testUser";
    $pass = "12345";
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function doRegister($username, $password, $role, $email, $alerts_email_enabled) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) 
    {
        return false;
    }
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) 
    {
        return false;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, password_hash, role, email, alerts_email_enabled) 
         VALUES (?, ?, ?, ?, ?)"
    );   
    return $stmt->execute([$username, $hash, $role, $email, $alerts_email_enabled]);
}
function doLogin($username, $password) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT password_hash, role FROM users WHERE username=?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }

    $sid = bin2hex(random_bytes(16));
    $exp = date("Y-m-d H:i:s", strtotime("+1 day"));
    
    $stmt = $pdo->prepare("INSERT INTO sessions (session_id,username,expires_at) VALUES (?,?,?)");
    $stmt->execute([$sid,$username,$exp]);
    
    return [
        'session_id' => $sid,
        'role' => $row['role']
    ];
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
function doPostJob($title, $organization, $location,$qualifications,$external_link,$description) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("INSERT INTO jobs_data 
                            (job_title, organization, location, date_posted, qualification_summary, apply_uri, major_duties) 
                            VALUES (?, ?, ?, CURDATE(), ?, ?, ?)");
    return $stmt->execute([$title, $organization, $location, $qualifications, $external_link, $description]); 
}


function doGetJobs($scope, $organization = null) {
    $pdo = getPDO();
    $sql = "SELECT job_title AS title,organization,location,date_posted,major_duties,apply_uri,position_id from jobs_data";
    $params=[];
    if($organization!==null)
    {
	    $sql.= " Where organization = ?";
	    $params[]=$organization;
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
    
    try { // <-- START OF ERROR HANDLING
	    switch ($req['type']) {
	    case "register":
                return doRegister($req['username'],$req['password'],$req['role'],$req['email'],$req['alerts_email_enabled']??0);
            case "login": 
                return doLogin($req['username'],$req['password']);
            case "validate_session": 
                return doValidate($req['sessionId']);
            case "post_job": 
                return doPostJob(
                    $req['title'] ?? '',
                    $req['employer'] ?? '',
                    $req['location'] ?? '',
                    $req['qualifications'] ?? '',
                    $req['external_link'] ?? '' ,
                    $req['description'] ?? ''
                );
            case "search_jobs_local": 
                return doSearchJobsLocal($req['query'] ?? '');
            case "get_jobs": 
                $scope = $req['scope'] ?? 'all';
                $organization = $req['organization'] ?? null;
		return doGetJobs($scope, $organization);
	    case "save_job":
                return doSaveJob(
                    $req['username'] ?? '',
                    $req['position_id'] ?? ''
		);
	    case "get_saved_jobs":
		    return doGetSavedJobs($req['username'] ?? '');
	    		      
            default:
                error_log("Unknown request type received: " . $req['type']);
                return "Invalid request: Unknown type"; 
        }
    } catch (PDOException $e) { // <-- CATCH DATABASE ERRORS
        $error_message = "Database Error in processing " . $req['type'] . ": " . $e->getMessage();
        error_log($error_message);
        return $error_message;
    } catch (Exception $e) { // <-- CATCH ALL OTHER ERRORS
        $error_message = "General Error in processing " . $req['type'] . ": " . $e->getMessage();
        error_log($error_message);
        return $error_message;
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");
$server->process_requests('requestProcessor');
?>
