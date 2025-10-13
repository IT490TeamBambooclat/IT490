<?php
// datacollector.php - SCRIPT ON THE DMZ VM

// Assumed RabbitMQ libraries are available
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// --- 1. Configuration ---
$API_HOST = "https://data.usajobs.gov/api/Search";
// TODO: REPLACE with your actual API key from developer.usajobs.gov
$API_KEY = "JARdgfQahwqDDdgixRjy/i7LyfIoEhmnJhwt9duouWM="; 
// TODO: REPLACE with your email address for the User-Agent header (required by USAJOBS API)
$USER_AGENT = "teambamboclaat@gmail.com"; 

// The name of the RabbitMQ server/queue section defined in testRabbitMQ.ini 
// This queue is listened to by job_listener.php on the Internal VM
$JOB_QUEUE_SERVER = "DmzInfo"; 

// --- 2. Main Data Fetching and Queueing Function ---
function fetchAndQueueJobData() {
    global $API_HOST, $API_KEY, $USER_AGENT, $JOB_QUEUE_SERVER;

    // Connect to the RabbitMQ Client (targets the job ingestion queue)
    $client = new rabbitMQClient("testRabbitMQ.ini", $JOB_QUEUE_SERVER);

    $page = 1;
    $results_per_page = 500; // Max allowed by USAJOBS API
    $has_more_pages = true;
    $total_jobs_queued = 0;

    error_log("Starting USAJOBS API data collection...");

    // Loop through pages until no more results are found (PAGINATION)
    while ($has_more_pages) {
        
        $url = $API_HOST . 
               "?Keyword=IT" . 
               "&ResultsPerPage=" . $results_per_page . 
               "&Page=" . $page;

        // --- cURL Setup for API Request ---
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // CRITICAL: Set the required authorization headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: data.usajobs.gov", 
            "User-Agent: " . $USER_AGENT, 
            "Authorization-Key: " . $API_KEY
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // --- Error Handling ---
        if ($http_code !== 200) {
            error_log("API request failed on page $page with HTTP code: " . $http_code);
            // Optionally break the loop on persistent errors
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
        
        // --- Process and Queue Data ---
        foreach ($jobs as $jobItem) {
            $jobDetails = $jobItem['MatchedObjectDescriptor'];
            
            $request = [
                'type' => 'ingest_job_data', // New type for the job_listener
                'job_title' => $jobDetails['PositionTitle'] ?? 'N/A',
                'organization' => $jobDetails['OrganizationName'] ?? 'N/A',
                // Join locations into a single string
                'location' => implode(', ', array_column($jobDetails['PositionLocations'] ?? [], 'LocationName')), 
                // Add any other fields needed
                'date_posted' => $jobDetails['PublicationStartDate'] ?? null
            ];
            
            // Use publish() for non-blocking communication (fire-and-forget)
            $client->publish($request); 
            $total_jobs_queued++;
        }

        // --- Check for Next Page (Pagination Logic) ---
        $page++;
        // Stop if we got fewer jobs than the max expected, meaning we hit the end.
        if ($num_jobs < $results_per_page) {
             $has_more_pages = false;
        }

        // Optional: Implement a small delay to respect API rate limits
        sleep(1); 
    }

    error_log("Data collection complete. Total jobs queued: $total_jobs_queued.");
    return true;
}

// --- 3. Script Execution ---
// Ensure this script is run via the command line (e.g., cron job)
if (php_sapi_name() == 'cli') {
    fetchAndQueueJobData();
} else {
    // Prevent accidental execution via web browser
    echo "Access Denied: This script must be run from the command line (via cron job).";
}
?>
