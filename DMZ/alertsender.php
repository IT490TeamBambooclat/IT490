#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';


$JOB_QUEUE_SERVER = "DmzInfo";

function sendJobAlerts() {
    global $JOB_QUEUE_SERVER;

    error_log("Starting job alert sender...");

    
    $client = new rabbitMQClient("testRabbitMQ.ini", $JOB_QUEUE_SERVER);
    
    $request = [
        'type' => 'get_alert_emails', 
        'message' => 'Requesting list of users enabled for job alerts'
    ];
    
    
    $response = $client->send_request($request); 
    
    error_log("Received response from DB Listener: " . json_encode($response));

    if (!isset($response['returnCode']) || $response['returnCode'] !== 0 || !isset($response['emails'])) {
        error_log("ERROR: Failed to retrieve emails from the database server.");
        return false;
    }

    $emails = $response['emails'];
    $num_emails = count($emails);
    error_log("Retrieved $num_emails emails. Starting mail process...");

    
    if ($num_emails > 0) {
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();                                            
            $mail->Host       = 'smtp.gmail.com';                     
            $mail->SMTPAuth   = true;                                   
            $mail->Username   = 'teambamboclaat@gmail.com'';                   
            $mail->Password   = 'hhqloyrcdjgrktdx';                              
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
            $mail->Port       = 587;                                    

            
            $mail->setFrom('teambamboclaat@gmail.com', 'Job Alerts');

            
            $mail->isHTML(true);                                  
            $mail->Subject = 'Your Daily Job Alert!';
            $mail->Body    = '<b>Hello User,</b><p>Here are your personalized job recommendations...</p>';
            $mail->AltBody = 'Hello User, Here are your personalized job recommendations...';

            $success_count = 0;
            foreach ($emails as $email) {
                try {
                    // Clear previous recipient and add current one
                    $mail->clearAllRecipients(); 
                    $mail->addAddress($email); 
                    
                    if($mail->send()) {
                        $success_count++;
                        error_log("Mail sent successfully to: $email");
                    } else {
                        error_log("Mail NOT sent to: $email. Mailer Error: " . $mail->ErrorInfo);
                    }
                } catch (Exception $e) {
                    error_log("Mail NOT sent to: $email. Exception: " . $e->getMessage());
                }
            }

            error_log("Finished sending alerts. Total sent successfully: $success_count out of $num_emails.");
            return true;

        } catch (Exception $e) {
            error_log("PHPMailer setup error: " . $e->getMessage());
            return false;
        }
    } else {
        error_log("No enabled users found. Skipping mail process.");
        return true;
    }
}

if (php_sapi_name() == 'cli') {
    sendJobAlerts();
} else {
    echo "Access Denied: This script must be run from the command line (cron job).";
}
?>
