<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$API_HOST = "https://data.usajobs.gov/api/Search";
$API_KEY = "JARdgfQahwqDDdgixRjy/i7LyfIoEhmnJhwt9duouWM="; 
$USER_AGENT = "teambamboclaat@gmail.com"; 
$JOB_QUEUE_SERVER = "DmzInfo"; 

function fetchAndQueueJobData() {
    global $API_HOST, $API_KEY, $USER_AGENT, $JOB_QUEUE_SERVER;

    $client = new rabbitMQClient("testRabbitMQ.ini", $JOB_QUEUE_SERVER);

    $page = 1;
    $results_per_page = 500; 
    $has_more_pages = true;
    $total_jobs_queued = 0;

    error_log("Starting USAJOBS API data collection...");

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
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            error_log("API request failed on page $page with HTTP code: " . $http_code);
            break; 
        }

        $data = json_decode($response, true);
        
        if (!isset($data['SearchResult']['SearchResultItems'])) {
            error_log("No jobs found or data structure invalid on page $page.");
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
            
            $client->publish($request); 
            $total_jobs_queued++;
        }

        $page++;
        if ($num_jobs < $results_per_page) {
             $has_more_pages = false;
        }

        sleep(1); 
    }

    error_log("Data collection complete. Total jobs queued: $total_jobs_queued.");
    return true;
}

if (php_sapi_name() == 'cli') {
    fetchAndQueueJobData();
} else {
    echo "Access Denied: This script must be run from the command line (cron job).";
}
?>
