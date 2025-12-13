<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$API_HOST = "https://data.usajobs.gov/api/Search";
$API_KEY = "JARdgfQahwqDDdgixRjy/i7LyfIoEhmnJhwt9duouWM="; 
$USER_AGENT = "teambamboclaat@gmail.com"; 
$JOB_QUEUE_SERVER = "DmzInfo"; 


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
		
		error_log("DLogging Failed for: " . $error . " - " . $e->getMessage());
	}
}


function fetchAndQueueJobData() {
    global $API_HOST, $API_KEY, $USER_AGENT, $JOB_QUEUE_SERVER;

  
    try {
        $client = new rabbitMQClient("testRabbitMQ.ini", $JOB_QUEUE_SERVER);
    } catch (Exception $e) {
        $error_msg = "RabbitMQ Client done goofed up: " . $e->getMessage();
        dlog($error_msg);
        error_log($error_msg);
        return false;
    }


    $page = 1;
    $results_per_page = 500; 
    $has_more_pages = true;
    $total_jobs_queued = 0;

    error_log("Starting USAJOBS API data collectio");
    dlog("Starting USAJOBS API data collection."); // Log start of process

    while ($has_more_pages) {
        
        $url = $API_HOST."?Keyword=IT" . 
               "&ResultsPerPage=" . $results_per_page . 
               "&Page=" . $page;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: data.usajobs.gov", 
            "User-Agent: " . $USER_AGENT, 
            "Authorization-Key: " . $API_KEY
        ]);

        $response = curl_exec($ch);
        
        
        if ($response === false) {
             $curl_error = "cURL execution failed on page $page: " . curl_error($ch);
             dlog($curl_error);
             error_log($curl_error);
             curl_close($ch);
             break;
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            $error_msg = "API request failed on page $page with HTTP code: " . $http_code;
            dlog($error_msg); // Log API failure
            error_log($error_msg);
            break; 
        }

        $data = json_decode($response, true);
        
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = "JSON decode failed on page $page: " . json_last_error_msg();
            dlog($error_msg); 
            error_log($error_msg);
            break;
        }
        
        if (!isset($data['SearchResult']['SearchResultItems'])) {
            $error_msg = "No jobs found on page $page.";
            dlog($error_msg); 
            error_log($error_msg);
            $has_more_pages = false;
            break; 
        }

        $jobs = $data['SearchResult']['SearchResultItems'];
        $num_jobs = count($jobs);
        
        error_log("Fetched $num_jobs jobs from page $page.");
        
        foreach ($jobs as $jobItem) {
            $jobDetails = $jobItem['MatchedObjectDescriptor'];
            
            $request = [
                'type' => 'ingest_job_data', 
                'position_id' => $jobDetails['PositionID'] ?? null, 
                'job_title' => $jobDetails['PositionTitle'] ?? 'N/A',
                'organization' => $jobDetails['OrganizationName'] ?? 'N/A',
                'location' => implode(', ', array_column($jobDetails['PositionLocation'] ?? [], 'LocationName')), 
                'date_posted' => $jobDetails['PublicationStartDate'] ?? null,
                'apply_uri' => ($jobDetails['ApplyURI'][0]) ?? 'N/A', 
                'qualification_summary' => $jobDetails['QualificationSummary'] ?? 'N/A',
                'major_duties' => implode('; ', $jobDetails['UserArea']['Details']['MajorDuties'] ?? [])
            ];
            
            
            try {
                $client->publish($request); 
            } catch (Exception $e) {
                $publish_error = "RabbitMQ Publish Failed for job: " . ($request['position_id'] ?? 'N/A') . " - " . $e->getMessage();
                dlog($publish_error); 
                error_log($publish_error);
               
            }
            $total_jobs_queued++;
        }

        $page++;
        if ($num_jobs < $results_per_page) {
             $has_more_pages = false;
        }

        sleep(1); 
    }

    error_log("Data collection complete. Total jobs queued: $total_jobs_queued.");
    dlog("Data collection complete. Total jobs queued: $total_jobs_queued."); 
    return true;
}

if (php_sapi_name() == 'cli') {
    fetchAndQueueJobData();
} else {
    echo "Access Denied";
}
?>
