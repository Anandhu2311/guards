<?php
// Standalone Emergency SMS Sender
// This file works independently - it doesn't require any action parameter

// Start session if needed
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set proper content type for JSON response
header('Content-Type: application/json');

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Logging function
function debug_log($message) {
    error_log("[EMERGENCY] " . $message);
}

debug_log("Emergency handler started - " . date('Y-m-d H:i:s'));

// Check if user is logged in
$userEmail = $_SESSION['email'] ?? '';
$userName = $_SESSION['name'] ?? 'User';

if (empty($userEmail)) {
    debug_log("Error: User not logged in");
    echo json_encode([
        'success' => 0,
        'message' => 'You must be logged in to send emergency alerts'
    ]);
    exit;
}

debug_log("Processing emergency request for user: $userEmail ($userName)");

// Get emergency message from POST
$message = $_POST['message'] ?? '';
if (empty($message)) {
    $message = "EMERGENCY ALERT! This person needs immediate assistance.";
}

debug_log("Emergency message: $message");

// Add user information to the message
$fullMessage = "EMERGENCY ALERT from $userName: $message";

try {
    // Database connection
    $host = 'localhost';
    $db   = 'guardsphere';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';
    
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $options);
    debug_log("Connected to database");
    
    // Twilio credentials 
    // Try to get them from service.php first
    $twilio_sid = '';
    $twilio_token = '';
    $twilio_number = '';
    
    if (file_exists('service.php')) {
        debug_log("Loading Twilio credentials from service.php");
        include_once('service.php');
        
        if (defined('TWILIO_SID')) $twilio_sid = TWILIO_SID;
        if (defined('TWILIO_TOKEN')) $twilio_token = TWILIO_TOKEN;
        if (defined('TWILIO_NUMBER')) $twilio_number = TWILIO_NUMBER;
    }
    
    // If credentials weren't found in service.php, use these defaults
    if (empty($twilio_sid)) $twilio_sid = "AC0303d1817540e57d2bf2e7ad730764d5";
    if (empty($twilio_token)) $twilio_token = "0aebd7ab8dd1441cfcbd7ffec24cafe2";
    if (empty($twilio_number)) $twilio_number = "+12184605587";
    
    debug_log("Using Twilio SID: " . substr($twilio_sid, 0, 5) . "...");
    
    // Find emergency contacts
    // First determine which column holds the user's email
    $emailColumns = ['user_email', 'email'];
    $emailColumn = null;
    
    foreach ($emailColumns as $column) {
        $checkQuery = "SHOW COLUMNS FROM emergency_contacts LIKE '$column'";
        $result = $pdo->query($checkQuery);
        if ($result->rowCount() > 0) {
            $emailColumn = $column;
            debug_log("Found email column: $emailColumn");
            break;
        }
    }
    
    if (!$emailColumn) {
        throw new Exception("Could not find email column in emergency_contacts table");
    }
    
    // Get emergency contacts
    $query = "SELECT * FROM emergency_contacts WHERE $emailColumn = :email";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':email', $userEmail);
    $stmt->execute();
    $contacts = $stmt->fetchAll();
    
    debug_log("Found " . count($contacts) . " emergency contacts");
    
    if (count($contacts) == 0) {
        echo json_encode([
            'success' => 0,
            'message' => 'You have no emergency contacts. Please add contacts first.'
        ]);
        exit;
    }
    
    // Send SMS to each contact
    $successCount = 0;
    $failCount = 0;
    $details = [];
    
    // Find name and phone number columns
    $nameColumns = ['emergency_name', 'name', 'contact_name'];
    $phoneColumns = ['em_number', 'phone', 'number', 'phone_number', 'contact_number'];
    
    foreach ($contacts as $contact) {
        // Find the name in the contact record
        $contactName = "Contact";
        foreach ($nameColumns as $col) {
            if (isset($contact[$col]) && !empty($contact[$col])) {
                $contactName = $contact[$col];
                break;
            }
        }
        
        // Find the phone number in the contact record
        $phoneNumber = null;
        foreach ($phoneColumns as $col) {
            if (isset($contact[$col]) && !empty($contact[$col])) {
                $phoneNumber = $contact[$col];
                break;
            }
        }
        
        if (empty($phoneNumber)) {
            debug_log("No phone number found for contact: $contactName");
            $failCount++;
            $details[] = "Failed to send to $contactName: No phone number";
            continue;
        }
        
        // Format the phone number for Twilio (add + if missing)
        if (substr($phoneNumber, 0, 1) !== '+') {
            $phoneNumber = '+' . ltrim($phoneNumber, '0');
        }
        
        debug_log("Sending SMS to $contactName at $phoneNumber");
        
        // Twilio API endpoint
        $url = "https://api.twilio.com/2010-04-01/Accounts/$twilio_sid/Messages.json";
        
        // Prepare Twilio request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'From' => $twilio_number,
            'To' => $phoneNumber,
            'Body' => $fullMessage
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$twilio_sid:$twilio_token");
        
        // Send the request
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            debug_log("cURL Error for $contactName: $error");
            $failCount++;
            $details[] = "Failed to send to $contactName: $error";
        } else {
            $response = json_decode($result, true);
            
            if ($httpCode >= 200 && $httpCode < 300 && isset($response['sid'])) {
                debug_log("Successfully sent SMS to $contactName: SID=" . $response['sid']);
                $successCount++;
                $details[] = "Alert sent to $contactName at " . substr($phoneNumber, 0, 4) . "..." . substr($phoneNumber, -4);
            } else {
                $error = $response['message'] ?? "HTTP Error: $httpCode";
                debug_log("Twilio Error for $contactName: $error");
                $failCount++;
                $details[] = "Failed to send to $contactName: $error";
            }
        }
        
        curl_close($ch);
    }
    
    // Prepare success/failure message
    $status = "";
    if ($successCount > 0) {
        $status = "Successfully sent $successCount emergency alerts";
        if ($failCount > 0) {
            $status .= " ($failCount failed)";
        }
    } else {
        $status = "Failed to send any emergency alerts";
    }
    
    // Return results
    debug_log("Finished sending alerts: $successCount sent, $failCount failed");
    echo json_encode([
        'success' => $successCount,
        'failed' => $failCount,
        'message' => $status,
        'details' => $details
    ]);
    
} catch (Exception $e) {
    debug_log("ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => 0,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?> 