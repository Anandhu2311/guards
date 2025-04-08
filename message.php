<?php
require __DIR__ . '/twilio-php-main/src/Twilio/autoload.php';
use Twilio\Rest\Client;

// Your credentials
$account_sid = 'AC0303d1817540e57d2bf2e7ad730764d5';
$auth_token = '0aebd7ab8dd1441cfcbd7ffec24cafe2';
$twilio_number = '+12184605587';
$to_number = '+919633394540'; // Your verified number

// Create client
$client = new Client($account_sid, $auth_token);

// Try to send test message
try {
    echo "Attempting to send message...\n";
    $message = $client->messages->create(
        $to_number,
        [
            'from' => $twilio_number,
            'body' => 'Test message from debugging script'
        ]
    );
    
    echo "Message SID: " . $message->sid . "\n";
    echo "Status: " . $message->status . "\n";
    
    // Wait a few seconds and fetch the updated status
    sleep(5);
    $updatedMessage = $client->messages($message->sid)->fetch();
    echo "Updated status: " . $updatedMessage->status . "\n";
    echo "Error code (if any): " . $updatedMessage->errorCode . "\n";
    echo "Error message (if any): " . $updatedMessage->errorMessage . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>