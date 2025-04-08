<?php
session_start();
require_once 'db_connect.php';
require_once 'vendor/autoload.php';

// Function to send SMS alerts (same as in service.php)
function sendSMSAlert($phone, $message, $provider = 'twilio') {
    if ($provider === "infobip") {
        $base_url = "https://your-base-url.api.infobip.com";
        $api_key = "your API key";

        $configuration = new \Infobip\Configuration(host: $base_url, apiKey: $api_key);
        $api = new \Infobip\Api\SmsApi(config: $configuration);
        $destination = new \Infobip\Model\SmsDestination(to: $phone);
        $smsMessage = new \Infobip\Model\SmsTextualMessage(
            destinations: [$destination],
            text: $message,
            from: "GuardSphere"
        );
        $request = new \Infobip\Model\SmsAdvancedTextualRequest(messages: [$smsMessage]);
        return $api->sendSmsMessage($request);
    } else {
        // Twilio
        $account_id = "your account SID";
        $auth_token = "your auth token";
        $twilio_number = "+your outgoing Twilio phone number";
        
        $client = new \Twilio\Rest\Client($account_id, $auth_token);
        return $client->messages->create(
            $phone,
            [
                "from" => $twilio_number,
                "body" => $message
            ]
        );
    }
}

// Handle emergency alert
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the POST data
    $data = json_decode(file_get_contents('php://input'), true);
    $location = isset($data['location']) ? 
                "Lat: {$data['location']['lat']}, Lng: {$data['location']['lng']}" : 
                "Unknown location";
    
    if (!isset($_SESSION['email'])) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }
    
    $user_email = $_SESSION['email'];
    
    // Get user's emergency contacts
    $stmt = $conn->prepare("SELECT ec.contact_name, ec.contact_phone FROM emergency_contacts ec 
                          JOIN users u ON ec.user_id = u.id 
                          WHERE u.email = ?");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $success = false;
    $message = "Failed to send alert. No emergency contacts found.";
    
    if ($result->num_rows > 0) {
        $alertMessage = "EMERGENCY ALERT from " . $user_email . "! They need help at location: " . $location;
        $successCount = 0;
        
        while ($contact = $result->fetch_assoc()) {
            // Send SMS to each emergency contact
            try {
                sendSMSAlert($contact['contact_phone'], $alertMessage);
                $successCount++;
            } catch (Exception $e) {
                // Log the error but continue trying other contacts
                error_log("Failed to send SMS to {$contact['contact_name']}: " . $e->getMessage());
            }
        }
        
        if ($successCount > 0) {
            $success = true;
            $message = "Emergency alert sent successfully to " . $successCount . " contact(s)!";
            
            // Log the emergency in the database
            $stmt = $conn->prepare("INSERT INTO emergency_alerts (user_email, location, timestamp) VALUES (?, ?, NOW())");
            $stmt->bind_param("ss", $user_email, $location);
            $stmt->execute();
        }
    }
    
    // Return JSON response
    echo json_encode(['success' => $success, 'message' => $message]);
}