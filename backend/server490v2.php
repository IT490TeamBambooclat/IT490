#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

//dlogger function 
function dlog($error)
{
	error_log($error);
	try
	{
		$client=new rabbitMQClient("testRabbitMQ.ini",'DLogging');
		$request= 
		[
			'type' => 'dlog',
			'timestamp'=> date('Y-m-d H:i:s'),
			'source_host' => gethostname(),
			'message' => $error
		];
		$client->publish($request);
	}catch (Exception $e)
	{
		error_log("DLogging Failed").$e->getMessage());
	}
}

function doGetEmployerJobs($organization_username) {
    $pdo = getPDO();
    $sql = "SELECT job_title AS title,location,qualification_summary AS qualifications,major_duties AS description,apply_uri AS external_link FROM jobs_data WHERE organization = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$organization_username]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
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
    if (!$stmt->fetch()) 
    {
	    dlog("Job ID not found in database".$position_id);//line for dlogger
	    return ['status' => 'error', 'message' => 'Job ID not found in database.'];
    }

    // 2. Check if the job was already saved by the user
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
    } else 
    {
	    dlog("Database Insert Failed".$position_id);
	    return ['status' => 'error', 'message' => 'Database insert failed.'];
    }
}
function getPDO() {
    $dsn = "mysql:host=127.0.0.1;dbname=testdb;charset=utf8mb4";
    $user = "testUser";
    $pass = "12345";
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function doRegister($username, $password, $role, $email, $alertEmailEnabled) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) 
    {
	dlog("register failed: username already exists".$username);
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
    return $stmt->execute([$username, $hash, $role, $email, $alertEmailEnabled]);
}
function doLogin($username, $password) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT password_hash, role FROM users WHERE username=?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($password, $row['password_hash'])) 
    {
	    dlog("Login Failed: Wrong password".$username);
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
    if (!$row) {
        //dlog lines
        dlog("Session validation failed: Session ID not found: " . $sid);
        return false;
    }
    if (strtotime($row['expires_at']) < time()) {
        //dlog line
        dlog("Session validation failed: Session ID expired: " . $sid);
        return false;
    }
    return true;
}
function doGetUserEmail($username) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT email FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        return ['email' => $row['email']];
    } else 
    {
	dlog("Email not found for username: ".$username);
        return ['email' => ''];
    }
}
function doCheckExistingApplication($username, $position_id) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applied_jobs WHERE username = ? AND position_id = ?");
    $stmt->execute([$username, $position_id]);
    $exists = $stmt->fetchColumn() > 0;
    return ['exists' => $exists];
}
function doApplyJob($username, $position_id,$resume_path) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT email FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_email = $row['email'] ?? '';
    
    if (empty($user_email)) {
         dlog("Apply job failed: User email not found for: " . $username);
         return ['status' => 'error', 'message' => 'User email not found.'];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applied_jobs WHERE username = ? AND position_id = ?");
    $stmt->execute([$username, $position_id]);
    if ($stmt->fetchColumn() > 0) {
        dlog("Apply job failed: User " . $username . " already applied to " . $position_id);
        return ['status' => 'error', 'message' => 'You have already applied to this job.'];
    }
    
    $stmt = $pdo->prepare("INSERT INTO applied_jobs 
                            (username, position_id, email, date_applied,resume_path) 
                            VALUES (?, ?, ?, NOW(),?)");
                            
    $success = $stmt->execute([$username, $position_id, $user_email,$resume_path]);

    if ($success) {
        return ['status' => 'success', 'message' => 'Application recorded successfully.'];
    } else {
        dlog("Apply job database insert failed for: " . $position_id);
        return ['status' => 'error', 'message' => 'Database insert failed.'];
    }
}
function doPostJob($title, $organization, $location,$qualifications,$external_link,$description,$position_id) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("INSERT INTO jobs_data 
                            (job_title, organization, location, date_posted, qualification_summary, apply_uri, major_duties,position_id) 
                            VALUES (?, ?, ?, CURDATE(), ?, ?, ?,?)");
    return $stmt->execute([$title, $organization, $location, $qualifications, $external_link, $description,$position_id]); 
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
function doSearchJobsLocal($query) 
{
	dlog("Attempting local job search for query".$query);
    if (empty($query)) {
        return ['results' => []];
    }
    $pdo = getPDO();
    $select_fields = "job_title as title, organization, location, date_posted, CONCAT('ID:', id) as apply_link, 'Local job post.' as summary";
    $sql = "SELECT $select_fields
            FROM jobs_data
            WHERE job_title LIKE ? OR organization LIKE ?
            ORDER BY ingestion_date DESC";
    
    $search_param = "%" . $query . "%";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$search_param, $search_param]);
    return ['results' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}
function getApplicants($username) {
    $pdo = getPDO();
    
    // Select application details (from applied_jobs) and job title (from jobs_data)
    $sql = "
        SELECT
            aj.username AS applicant_username,
            aj.position_id AS job_id,
            aj.email,
	    aj.date_applied AS applied_at,
            aj.resume_path,
            jd.job_title
        FROM applied_jobs aj
        JOIN jobs_data jd ON aj.position_id = jd.position_id
        WHERE jd.organization = ?
        ORDER BY jd.job_title, aj.date_applied DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    return ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];//frontend is waiting for 'data' values.
}

function doCheckResumeAccess($username, $resume_path) {
    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND resume_file_path = ?");
    $stmt->execute([$username, $resume_path]);
    if ($stmt->fetchColumn() > 0) {
        return true; // Owner access
    }

    $sql = "
        SELECT COUNT(*)
        FROM applied_jobs aj
        JOIN jobs_data jd ON aj.position_id = jd.position_id
        WHERE jd.organization = ? AND aj.resume_path = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $resume_path]);
    if ($stmt->fetchColumn() > 0) {
        return true; // Employer have access
    }
    dlog("resume access denied for user: ".$username."trying to access file in path: ".$resume_path);
    return false; // No access
}

function doSaveResumePath($username,$file_path)
{
	$pdo=getPDO();
	$sql = "UPDATE users SET resume_file_path = ? WHERE username = ?";
	$stmt = $pdo->prepare($sql);
	$success = $stmt->execute([$file_path, $username]);
	if ($success) {
		return ['status' => 'success'];
	} else {
		dlog("Failed to update resume path: ".$file_path."for user: ".$username);
		return ['status' => 'error', 'message' => 'Failed to update resume path.'];
	}
}
function doGetResumePath($username)
{
	$pdo = getPDO();
	$stmt = $pdo->prepare("SELECT resume_file_path FROM users WHERE username = ?");
	$stmt->execute([$username]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	return ['file_path' => $row['resume_file_path'] ?? null];
}
function doGetPathAndApply($username, $position_id) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT resume_file_path FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $resume_path = $row['resume_file_path'] ?? null;
    return doApplyJob($username, $position_id, $resume_path);
}
function requestProcessor($req) {
    if (!isset($req['type'])) {
	    error_log("Received request with no 'type' field: " . json_encode($req));
	    dlog("Received request with no type field".json_encode($req));
        return "Invalid request: Missing type field";
    }

    error_log("Received request type: " . $req['type']); 
    dlog("Received request type: ".$req['type']);
    
    try {
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
		    $req['description'] ?? '',
		    $req['position_id']??''
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
	    case "get_employer_jobs":
		   $jobs=doGetEmployerJobs($req['username'??'']);
		   return ['jobs'=>$jobs];
	    case "check_existing_application":
		    return doCheckExistingApplication($req['username']??'',$req['position_id']??'');
	    case "apply_job":
		    return doGetPathAndApply($req['username'] ?? '',$req['position_id'] ?? '');
	    case "get_user_email":
		    return doGetUserEmail($req['username']??'');
	    case "get_applicants":
		    return getApplicants($req['username']??'');
	    case "save_resume_path":
		    return doSaveResumePath($req['username']??'',$req['file_path']??'');
	    case "get_resume_path":
		    return doGetResumePath($req['username']??'');
	    case "check_resume_access":
		    return doCheckResumeAccess($req['username']??'', $req['file_path']??'');
	    default:
		    error_log("Unknown request type received: " . $req['type']);
		    dlog("Unknown request type".$req['type']);
                return "Invalid request: Unknown type"; 
        }
    } catch (PDOException $e) { //CATCH DATABASE ERRORS
        $error_message = "Database Error in processing " . $req['type'] . ": " . $e->getMessage();
	error_log($error_message);
	dlog($error_message);
        return $error_message;
    } catch (Exception $e) { //CATCH ALL OTHER ERRORS
        $error_message = "General Error in processing " . $req['type'] . ": " . $e->getMessage();
	error_log($error_message);
	dlog($error_message);
        return $error_message;
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");
$server->process_requests('requestProcessor');
?>
