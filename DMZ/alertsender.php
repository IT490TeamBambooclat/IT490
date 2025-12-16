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
		// Log dat og error message
		error_log("DLogging Failed bruh: " . $error . " - " . $e->getMessage());
	}
}


function sendJobAlerts() {
    global $JOB_QUEUE_SERVER;

    error_log("Starting job alert sender...");
    dlog("Starting dat job sender process."); 
   
   
    try {
        $client = new rabbitMQClient("testRabbitMQ.ini", $JOB_QUEUE_SERVER);
    } catch (Exception $e) {
        $error_msg = "RabbitMQ Client Initialization Failed: " . $e->getMessage();
        dlog($error_msg);
        error_log($error_msg);
        return false;
    }
    
    $request = [
        'type' => 'get_alert_emails', 
        'message' => 'Requesting list of users enabled for job alerts'
    ];
    
    
    try {
        $response = $client->send_request($request); 
    } catch (Exception $e) {
        $error_msg = "RabbitMQ Request (get_alert_emails) Failed: " . $e->getMessage();
        dlog($error_msg); 
        error_log($error_msg);
        return false;
    }
    
    error_log("Received response from DB Listener: " . json_encode($response));

    if (!isset($response['returnCode']) || $response['returnCode'] !== 0 || !isset($response['emails'])) {
        $error_msg = "ERROR: Failed to retrieve emails from the database server. Response: " . json_encode($response);
        dlog($error_msg); 
        error_log($error_msg);
        return false;
    }

    $emails = $response['emails'];
    $num_emails = count($emails);
    error_log("Retrieved $num_emails emails. Starting mail process...");

   
    if ($num_emails > 0) {
        $mail = new PHPMailer(true);
        try {
           
            $mail->isSMTP();                                            
            $mail->Host       = 'smtp.gmail.com';              
            $mail->SMTPAuth   = true;                                   
            $mail->Username   = 'teambamboclaat@gmail.com';                   
            $mail->Password   = 'hhqloyrcdjgrktdx';                   
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
            $mail->Port       = 587;                                    

            // Sender 
            $mail->setFrom('teambamboclaat@gmail.com', 'Job Alerts Service');

     

            
            $mail->isHTML(true);                                  
            $mail->Subject = 'Your Daily Personalized Job Alert!';
            $mail->Body    = '<b>Hello User,</b><p>Here are your personalized job recommendations...</p>';
            $mail->AltBody = 'Hello User, Here are your personalized job recommendations...';

            $success_count = 0;
            // Loop through all retrieved emails and send the alert
            foreach ($emails as $email) {
                try {
                    
                    $mail->clearAllRecipients(); 
                    $mail->addAddress($email); 
                    
                    if($mail->send()) {
                        $success_count++;
                        error_log("Mail sent successfully to: $email");
                    } else {
                        
                        $mailer_error = "Mail NOT sent to: $email. Mailer Error: " . $mail->ErrorInfo;
                        dlog($mailer_error);
                        error_log($mailer_error);
                    }
                } catch (Exception $e) {
                    
                    $email_exception = "Mail AINT send shit to: $email. Exception: " . $e->getMessage();
                    dlog($email_exception);
                    error_log($email_exception);
                }
            }

            error_log("Finished sending alerts. Total: $success_count out of $num_emails.");
            dlog("Alerts finished. Total: $success_count out of $num_emails.");
            return true;

        } catch (Exception $e) {
            // Log PHPMailer setup or fatal error
            $fatal_error = "PHPMailer setup or fatal error: " . $e->getMessage();
            dlog($fatal_error);
            error_log($fatal_error);
            return false;
        }
    } else {
        error_log("No enabled users found bruh");
        dlog("No enabled users found.");
        return true;
    }
}

if (php_sapi_name() == 'cli') {
    sendJobAlerts();
} else {
    echo "Access Denied: This script must be run from the command line (cron job).";
}
?>
