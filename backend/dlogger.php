#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$LogFileDest = '/home/it490/Desktop/dlog.log'; 

function log_processor($req) {
    global $LogFileDest;
    if (isset($req['type']) && $req['type'] == 'dlog' && isset($req['message'])) {
        
        $log_line = sprintf(
            "%s [ERROR] %s: %s\n",
            $req['timestamp'] ?? date('Y-m-d H:i:s'),
            $req['source_host'] ?? 'UNKNOWN_HOST',
            $req['message']
        );
        
        // Adds/appends the new log line
        if (file_put_contents($LogFileDest, $log_line, FILE_APPEND | LOCK_EX) !== false) {
            return ['returnCode' => 0, 'message' => 'Log Appended'];
        } else {
            return ['returnCode' => 1, 'message' => 'Log Failed'];
        }
    }
    return ['returnCode' => 1, 'message' => 'Invalid log message format'];
}

$server = new rabbitMQServer("testRabbitMQ.ini", 'BEQueue');

error_log("Universal Log Collector started and waiting for messages on queue: BEQueue");
$server->process_requests('log_processor');
?>
