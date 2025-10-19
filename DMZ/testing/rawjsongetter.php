<?php
/**
 * PHP Script to fetch raw JSON data from the USAJOBS API (Page 1)
 * and save it to a local text file for inspection.
 * * NOTE: Replace the API_KEY and USER_AGENT with your actual credentials.
 */

// --- Configuration ---
$API_HOST = "https://data.usajobs.gov/api/Search";
// Using your provided API key (ensure this is secured in a real production environment)
$API_KEY = "JARdgfQahwqDDdgixRjy/i7LyfIoEhmnJhwt9duouWM="; 
$USER_AGENT = "teambamboclaat@gmail.com"; 

// Output file path - Changed to .txt extension per request
$OUTPUT_FILE = 'raw_api_output.txt';
// --- End Configuration ---


function fetchRawJson() {
    global $API_HOST, $API_KEY, $USER_AGENT, $OUTPUT_FILE;

    $results_per_page = 500;
    $page = 1;
    
    // Construct the URL for the first page of IT jobs
    $url = $API_HOST . "?Keyword=IT" . 
           "&ResultsPerPage=" . $results_per_page . 
           "&Page=" . $page;

    echo "Attempting to fetch data from: " . $url . "\n";

    // 1. Initialize cURL session
    $ch = curl_init($url);
    
    // 2. Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // IMPORTANT: Return the transfer as a string instead of outputting it
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout after 30 seconds
    
    // 3. Set required headers for USAJOBS API
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Host: data.usajobs.gov", 
        "User-Agent: " . $USER_AGENT, 
        "Authorization-Key: " . $API_KEY
    ]);

    // 4. Execute the cURL request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    // 5. Close cURL session
    curl_close($ch);

    // 6. Error Handling and Output
    if ($curl_error) {
        echo "cURL Error: " . $curl_error . "\n";
        return false;
    }

    if ($http_code !== 200) {
        echo "API Request failed with HTTP code: " . $http_code . "\n";
        echo "Response (partial): " . substr($response, 0, 200) . "\n";
        return false;
    }

    // 7. Save the raw response string to a file
    if ($response) {
        // file_put_contents writes the raw string to the specified file.
        file_put_contents($OUTPUT_FILE, $response);
        echo "\n✅ Success! Raw JSON data saved to text file: " . $OUTPUT_FILE . "\n";
        echo "File size: " . round(filesize($OUTPUT_FILE) / 1024, 2) . " KB\n";
        return true;
    }

    echo "Error: Received an empty response from the API.\n";
    return false;
}

// Run the script function when executed from the command line
if (php_sapi_name() == 'cli') {
    fetchRawJson();
} else {
    echo "Access Denied: This script must be run from the command line (CLI).";
}

?>

