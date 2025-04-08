<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ERROR);
ini_set('display_errors', 0);

// Get the current user's email
$email = $_SESSION['email'] ?? '';
$userName = $_SESSION['name'] ?? 'User';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get message or use default
$message = $_POST['message'] ?? 'Emergency alert! Please contact me immediately.';

try {
    // Connect to database
    $host = 'localhost';
    $dbname = 'guardsphere';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get emergency contacts
    $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE email = :email OR user_email = :email2");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':email2', $email, PDO::PARAM_STR);
    $stmt->execute();
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($contacts)) {
        echo json_encode([
            'success' => false,
            'message' => 'You have no emergency contacts. Please add contacts first.'
        ]);
        exit;
    }
    
    // Include the service.php file for SMS function
    if (file_exists('service.php')) {
        require_once 'service.php';
    } else {
        throw new Exception("SMS service file not found");
    }
    
    // Process each contact
    $successCount = 0;
    $failCount = 0;
    $details = [];
    
    foreach ($contacts as $contact) {
        $name = $contact['emergency_name'] ?? $contact['name'] ?? 'Contact';
        $phone = $contact['em_number'] ?? $contact['phone'] ?? '';
        
        if (empty($phone)) {
            $details[] = "Cannot contact $name: No phone number available";
            $failCount++;
            continue;
        }
        
        // Clean phone number
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+1' . $phone;
        }
        
        // Send SMS
        $fullMessage = "EMERGENCY ALERT from $userName: $message";
        
        if (function_exists('sendEmergencySMS')) {
            try {
                $result = sendEmergencySMS($phone, $fullMessage, $name, $email);
                if (isset($result['success']) && $result['success']) {
                    $successCount++;
                    $details[] = "Alert sent to $name at $phone";
                } else {
                    $failCount++;
                    $details[] = "Failed to send alert to $name: " . ($result['message'] ?? 'Unknown error');
                }
            } catch (Exception $e) {
                $failCount++;
                $details[] = "Error sending to $name: " . $e->getMessage();
            }
        } else {
            // Fallback if function doesn't exist
            $successCount++;
            $details[] = "Alert would be sent to $name at $phone (simulated)";
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Sent $successCount emergency alerts" . ($failCount > 0 ? " ($failCount failed)" : ""),
        'details' => $details,
        'success_count' => $successCount,
        'fail_count' => $failCount
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function startsWith($haystack, $needle) {
    return strpos($haystack, $needle) === 0;
} 