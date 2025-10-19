<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$API_HOST = "https://data.usajobs.gov/api/Search";
$API_KEY = "JARdgfQahwqDDdgixRjy/i7LyfIoEhmnJhwt9duouWM="; 
$USER_AGENT = "teambamboclaat@gmail.com"; 
$JOB_QUEUE_SERVER = "DmzInfo"; 

// Load Geo Location reference data
$geoCodeData = json_decode('{
    "CodeList": [{
        "ValidValue": [{
            "Code": "530539053",
            "City": "Dupont",
            "USCounty": "Pierce County",
            "CountrySubdivision": "WA",
            "Country": "US",
            "LastModified": "2014-03-10T00:00:00",
            "IsDisabled": "No"
        }],
        "id": "GeoLocCode"
    }],
    "DateGenerated": "2015-04-05T18:55:44.3995692-04:00"
}', true);

function fetchAndQueueJobData() {
    global $API_HOST, $API_KEY, $USER_AGENT, $JOB_QUEUE_SERVER, $geoCodeData;

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
            
            // Extract job data
            $positionLocations = $jobDetails['PositionLocation'] ?? [];
            $firstLocation = $positionLocations[0] ?? [];

            // Match geo data
            $geoInfo = $geoCodeData['CodeList'][0]['ValidValue'][0];
            $geoMatch = ($firstLocation['LocationName'] ?? '') === $geoInfo['City'] ? $geoInfo : [];

            $request = [
                'type' => 'ingest_job_data', 
                'position_id' => $jobDetails['PositionID'] ?? null, 
                'job_title' => $jobDetails['PositionTitle'] ?? 'N/A',
                'organization' => $jobDetails['OrganizationName'] ?? 'N/A',
                'location' => implode(', ', array_column($positionLocations, 'LocationName')), 
                'date_posted' => $jobDetails['PublicationStartDate'] ?? null,
                'geo_code' => $geoMatch['Code'] ?? null,
                'geo_city' => $geoMatch['City'] ?? null,
                'geo_county' => $geoMatch['USCounty'] ?? null,
                'geo_state' => $geoMatch['CountrySubdivision'] ?? null,
                'geo_country' => $geoMatch['Country'] ?? null
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

