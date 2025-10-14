<?php
// api_rabbitmq_client.php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function mq_request(array $payload) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    // send_request may block until consumer responds
    return $client->send_request($payload);
}
?>
