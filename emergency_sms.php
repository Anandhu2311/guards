<?php
/**
 * Emergency SMS Handler
 * Handles sending emergency SMS notifications to contacts
 */

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Required includes
require_once 'DBS.inc.php'; // Database connection
require_once __DIR__ . '/twilio-php-main/src/Twilio/autoload.php';
require_once __DIR__ . '/config/config.php';
use Twilio\Rest\Client;

// SMS configuration - Twilio credentials
$twilioAccountSid = getConfig('TWILIO_ACCOUNT_SID');
$twilioAuthToken = getConfig('TWILIO_AUTH_TOKEN');
$twilioPhoneNumber = getConfig('TWILIO_PHONE_NUMBER');

// Create detailed debugging function
function smsLogDebug($message) {
    $logFile = __DIR__ . '/sms_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
    $logMessage = "[{$timestamp}] [{$caller}] {$message}";
    
    // Log to file
    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
    
    // Also log to PHP error log
    error_log("SMS DEBUG: {$message}");
}

// Initialize database connection
function connectSmsDB() {
    try {
        $host = 'localhost';
        $dbname = 'guardsphere';
        $username = 'root';
        $password = '';
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ];
        
        $pdo = new PDO($dsn, $username, $password, $options);
        return $pdo;
    } catch (PDOException $e) {
        smsLogDebug("DB CONNECTION ERROR: " . $e->getMessage());
        return null;
    }
}

/**
 * Send emergency SMS to contacts
 * 
 * @param string $message Emergency message content
 * @param string $senderName Name of person sending the alert
 * @param string $userEmail Email of user whose contacts should receive messages
 * @return array Results of sending (success count, fail count, etc.)
 */
function sendEmergencySMS($message, $senderName, $userEmail = null) {
    global $twilioAccountSid, $twilioAuthToken, $twilioPhoneNumber;
    smsLogDebug("Starting emergency SMS for user: " . ($userEmail ?? 'all users'));
    
    // Initialize return data
    $resultData = [
        'success' => 0,
        'fail' => 0,
        'message' => '',
        'debug' => ''
    ];
    
    try {
        $pdo = connectSmsDB();
        if (!$pdo) {
            $resultData['message'] = 'Database connection failed';
            return $resultData;
        }
        
        // Get emergency contacts
        try {
            if ($userEmail) {
                $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE email = :email");
                $stmt->bindParam(':email', $userEmail, PDO::PARAM_STR);
                $stmt->execute();
            } else {
                $stmt = $pdo->query("SELECT * FROM emergency_contacts");
            }
            
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            smsLogDebug("Found " . count($contacts) . " emergency contacts");
            
            if (empty($contacts)) {
                $resultData['message'] = 'No emergency contacts found';
                return $resultData;
            }
        } catch (PDOException $e) {
            smsLogDebug("Database query error: " . $e->getMessage());
            $resultData['message'] = 'Database query error: ' . $e->getMessage();
            return $resultData;
        }
        
        // Format message
        $fullMessage = "EMERGENCY ALERT from {$senderName}: {$message}";
        
        // Initialize counters
        $successCount = 0;
        $failCount = 0;
        $errorMessages = [];
        
        // Initialize Twilio client
        try {
            // Check if Twilio credentials are set
            if (empty($twilioAccountSid) || empty($twilioAuthToken) || empty($twilioPhoneNumber)) {
                throw new Exception("Twilio credentials not properly configured");
            }
            
            $client = new Client($twilioAccountSid, $twilioAuthToken);
            
            // Process each contact
            foreach ($contacts as $contact) {
                // Determine which field has the phone number
                $phoneNumber = null;
                $possibleFields = ['phone', 'phone_number', 'contact_phone', 'em_number', 'number'];
                
                foreach ($possibleFields as $field) {
                    if (isset($contact[$field]) && !empty($contact[$field])) {
                        $phoneNumber = $contact[$field];
                        break;
                    }
                }
                
                if (empty($phoneNumber)) {
                    smsLogDebug("No phone number found for contact ID: " . ($contact['id'] ?? 'unknown'));
                    $failCount++;
                    continue;
                }
                
                // Clean and format phone number
                $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
                
                // Add country code if needed
                if (substr($phoneNumber, 0, 1) !== '+') {
                    if (strlen($phoneNumber) == 10) {
                        $phoneNumber = '+91' . $phoneNumber; // Assuming India
                    } else {
                        $phoneNumber = '+' . $phoneNumber;
                    }
                }
                
                $contactName = $contact['name'] ?? $contact['emergency_name'] ?? 'Contact #' . ($contact['id'] ?? 'unknown');
                smsLogDebug("Sending SMS to {$contactName} at {$phoneNumber}");
                
                // Send SMS via Twilio
                try {
                    $result = $client->messages->create(
                        $phoneNumber,
                        [
                            'from' => trim($twilioPhoneNumber),
                            'body' => $fullMessage
                        ]
                    );
                    
                    smsLogDebug("Twilio SID: {$result->sid}, Status: {$result->status}");
                    
                    // Log to database if table exists
                    try {
                        $logStmt = $pdo->prepare("INSERT INTO emergency_logs 
                            (contact_id, message, sent_by, sent_at, status, twilio_sid) 
                            VALUES (:contactId, :message, :sentBy, NOW(), :status, :twilioSid)");
                        
                        $contactId = $contact['id'] ?? 0;
                        $status = $result->status ?? 'unknown';
                        $twilioSid = $result->sid ?? '';
                        
                        $logStmt->bindParam(':contactId', $contactId, PDO::PARAM_INT);
                        $logStmt->bindParam(':message', $message, PDO::PARAM_STR);
                        $logStmt->bindParam(':sentBy', $senderName, PDO::PARAM_STR);
                        $logStmt->bindParam(':status', $status, PDO::PARAM_STR);
                        $logStmt->bindParam(':twilioSid', $twilioSid, PDO::PARAM_STR);
                        $logStmt->execute();
                    } catch (Exception $e) {
                        smsLogDebug("Could not log to database: " . $e->getMessage());
                        // Continue even if logging fails
                    }
                    
                    $successCount++;
                    
                } catch (Exception $e) {
                    smsLogDebug("Failed to send SMS to {$phoneNumber}: " . $e->getMessage());
                    $errorMessages[] = "Failed to send to {$contactName}: " . $e->getMessage();
                    $failCount++;
                }
            }
            
            $resultData = [
                'success' => $successCount,
                'fail' => $failCount,
                'message' => "Successfully sent {$successCount} emergency messages",
                'debug' => $failCount > 0 ? implode("; ", $errorMessages) : ''
            ];
            
            return $resultData;
            
        } catch (Exception $e) {
            smsLogDebug("Twilio client error: " . $e->getMessage());
            $resultData = [
                'success' => 0,
                'fail' => count($contacts),
                'message' => 'SMS service error: ' . $e->getMessage(),
                'debug' => $e->getTraceAsString()
            ];
            return $resultData;
        }
        
    } catch (Exception $e) {
        smsLogDebug("Fatal error in sendEmergencySMS: " . $e->getMessage());
        $resultData = [
            'success' => 0,
            'fail' => 0,
            'message' => 'Error: ' . $e->getMessage(),
            'debug' => $e->getTraceAsString()
        ];
        return $resultData;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set JSON content type
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'send_emergency_sms') {
            $userEmail = isset($_POST['user_email']) ? $_POST['user_email'] : '';
            $message = isset($_POST['message']) ? $_POST['message'] : '';
            $senderName = isset($_POST['sender_name']) ? $_POST['sender_name'] : $userEmail;
            
            // Validate required parameters
            if (empty($message)) {
                echo json_encode(['success' => false, 'message' => 'No message provided']);
                exit;
            }
            
            // Get user's name if not provided
            if ($senderName === $userEmail && !empty($userEmail)) {
                $pdo = connectSmsDB();
                if ($pdo) {
                    try {
                        $stmt = $pdo->prepare("SELECT name FROM users WHERE email = :email");
                        $stmt->bindParam(':email', $userEmail, PDO::PARAM_STR);
                        $stmt->execute();
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($user && isset($user['name'])) {
                            $senderName = $user['name'];
                        }
                    } catch (Exception $e) {
                        smsLogDebug("Error getting user name: " . $e->getMessage());
                        // Continue with email as sender name
                    }
                }
            }
            
            if (empty($senderName)) {
                $senderName = "Security System";
            }
            
            $result = sendEmergencySMS($message, $senderName, $userEmail);
            
            echo json_encode([
                'success' => $result['success'] > 0,
                'contacts' => $result['success'],
                'message' => $result['message'],
                'debug' => $result['debug'] ?? ''
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
            exit;
        }
    } catch (Exception $e) {
        smsLogDebug("Fatal error in request handling: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage(),
            'debug' => $e->getTraceAsString()
        ]);
        exit;
    }
}
?>