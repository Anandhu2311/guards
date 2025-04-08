<?php
/**
 * Service Management System
 * Handles provider availability, booking, and emergency notifications
 */

// Required includes
require_once 'DBS.inc.php'; // Database connection
require __DIR__ . '/twilio-php-main/src/Twilio/autoload.php';
use Twilio\Rest\Client;

// SMS configuration - replace with your actual credentials
$twilioAccountSid = 'AC0303d1817540e57d2bf2e7ad730764d5';
$twilioAuthToken = '0aebd7ab8dd1441cfcbd7ffec24cafe2';
$twilioPhoneNumber = '+12184605587 ';

// Turn off output buffering and disable error display for production
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start session and set headers
session_start();
header('Content-Type: application/json');

// Enable detailed error logging
ini_set('display_errors', 0); // Don't show errors to users
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log'); // Make sure this directory is writable

// Create detailed debugging function
function logDebug($message) {
    error_log("[" . date('Y-m-d H:i:s') . "] " . $message);
}

// Initialize database connection
if (!function_exists('connectDB')) {
    function connectDB() {
        try {
            $host = 'localhost';
            $dbname = 'guardsphere';
            $username = 'root';
            $password = '';
            
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            logDebug("DB CONNECTION ERROR: " . $e->getMessage());
            return null;
        }
    }
}

// Function to safely return JSON
function returnJson($data) {
    // Clear any previous output
    ob_clean();
    
    // Set JSON header
    header('Content-Type: application/json');
    
    // Output JSON and exit
    echo json_encode($data);
    ob_end_flush();
    exit;
}

/**
 * PROVIDER AVAILABILITY MANAGEMENT
 */

/**
 * Get role-based service name
 */
function getServiceNameByRoleId($roleId) {
    switch ($roleId) {
        case 2:
            return "Academic Advising Session";
        case 3:
            return "Personal Counseling Session";
        case 4:
            return "Technical Support Session";
        default:
            return "Consultation Session";
    }
}

/**
 * Get provider details based on role and staff_id
 * 
 * @param PDO $pdo Database connection
 * @param int $roleId Role ID (2=advisor, 3=counselor, 4=supporter)
 * @param int $staffId Staff ID (corresponds to adv_id, coun_id, or sup_id in respective tables)
 * @param string $email Provider email (used as fallback)
 * @return array|null Provider details or null if not found
 */
function getProviderDetailsByRoleAndId($pdo, $roleId, $staffId, $email = null) {
    try {
        $details = null;
        
        switch ($roleId) {
            case 2: // Advisor
                $sql = "SELECT a.*, a.email as provider_email, 
                              COALESCE(u.name, a.name) as provider_name,
                              'Academic Advising Session' as service_name
                        FROM advisor a
                        LEFT JOIN users u ON a.email = u.email
                        WHERE a.adv_id = :staff_id";
                break;
            case 3: // Counselor
                $sql = "SELECT c.*, c.email as provider_email, 
                              COALESCE(u.name, c.name) as provider_name,
                              'Personal Counseling Session' as service_name
                        FROM counselor c
                        LEFT JOIN users u ON c.email = u.email
                        WHERE c.coun_id = :staff_id";
                break;
            case 4: // Support
                $sql = "SELECT s.*, s.email as provider_email, 
                              COALESCE(u.name, s.name) as provider_name,
                              'Technical Support Session' as service_name
                        FROM supporter s
                        LEFT JOIN users u ON s.email = u.email
                        WHERE s.sup_id = :staff_id";
                break;
            default:
                // Try to get at least basic user info
                $sql = "SELECT id, name as provider_name, email as provider_email, 
                              role_id, 'Consultation Session' as service_name
                        FROM users
                        WHERE id = :staff_id";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':staff_id', $staffId, PDO::PARAM_INT);
        $stmt->execute();
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$details && $email) {
            // Fallback to email if ID lookup fails
            logDebug("Falling back to email lookup for provider: " . $email);
            switch ($roleId) {
                case 2: // Advisor
                    $sql = "SELECT a.*, a.email as provider_email, 
                                  COALESCE(u.name, a.name) as provider_name,
                                  'Academic Advising Session' as service_name
                            FROM advisor a
                            LEFT JOIN users u ON a.email = u.email
                            WHERE a.email = :email";
                    break;
                case 3: // Counselor
                    $sql = "SELECT c.*, c.email as provider_email, 
                                  COALESCE(u.name, c.name) as provider_name,
                                  'Personal Counseling Session' as service_name
                            FROM counselor c
                            LEFT JOIN users u ON c.email = u.email
                            WHERE c.email = :email";
                    break;
                case 4: // Support
                    $sql = "SELECT s.*, s.email as provider_email, 
                                  COALESCE(u.name, s.name) as provider_name,
                                  'Technical Support Session' as service_name
                            FROM supporter s
                            LEFT JOIN users u ON s.email = u.email
                            WHERE s.email = :email";
                    break;
                default:
                    $sql = "SELECT id, name as provider_name, email as provider_email, 
                                  role_id, 'Consultation Session' as service_name
                            FROM users
                            WHERE email = :email";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            $details = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($details) {
            // Add role_id if not already present
            if (!isset($details['role_id'])) {
                $details['role_id'] = $roleId;
            }
            
            // Add service name if not already present
            if (!isset($details['service_name'])) {
                $details['service_name'] = getServiceNameByRoleId($roleId);
            }
        }
        
        return $details;
    } catch (PDOException $e) {
        logDebug("Error fetching provider details: " . $e->getMessage());
        return null;
    }
}

/**
 * Debug function to help troubleshoot availability issues
 */
function debugAvailability($pdo) {
    try {
        $debug = [];
        
        // Check table structure
        try {
            $tableInfo = $pdo->query("DESCRIBE staff_availability")->fetchAll(PDO::FETCH_ASSOC);
            $debug['staff_availability_columns'] = array_column($tableInfo, 'Field');
        } catch (Exception $e) {
            $debug['staff_availability_columns_error'] = $e->getMessage();
        }
        
        // Count all records in staff_availability table
        $debug['staff_availability_total_count'] = $pdo->query("SELECT COUNT(*) FROM staff_availability")->fetchColumn();
        
        // Check all schedules
        $debug['schedules'] = $pdo->query("SELECT * FROM schedules WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        $debug['schedules_count'] = count($debug['schedules']);
        
        // Check staff availability with direct fields
        $debug['staff_availability'] = $pdo->query("
            SELECT * FROM staff_availability 
            WHERE is_available = 1
        ")->fetchAll(PDO::FETCH_ASSOC);
        $debug['staff_availability_count'] = count($debug['staff_availability']);
        
        // Check users with provider roles
        $debug['providers'] = $pdo->query("
            SELECT id, name, email, role_id 
            FROM users 
            WHERE role_id IN (2,3,4) AND is_active = 1
        ")->fetchAll(PDO::FETCH_ASSOC);
        $debug['providers_count'] = count($debug['providers']);
        
        return $debug;
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get available schedules with all available providers - simplified direct approach
 */
function getAvailableSchedules($pdo, $providerType = '') {
    try {
        logDebug("Getting available services directly from staff_availability with type: " . $providerType);
        
        // Direct query from staff_availability with minimal filtering
        $sql = "SELECT 
                    sa.*,
                    u.name as provider_name,
                    CASE 
                        WHEN sa.role_id = 2 THEN 'Academic Advising Session'
                        WHEN sa.role_id = 3 THEN 'Personal Counseling Session'
                        WHEN sa.role_id = 4 THEN 'Technical Support Session'
                        ELSE 'Consultation Session'
                    END as service_name
                FROM staff_availability sa
                LEFT JOIN users u ON sa.staff_email = u.email
                WHERE sa.is_available = 1"; // ONLY get records where is_available = 1
        
        // Apply service type filter if provided
        if (!empty($providerType)) {
            switch($providerType) {
                case 'advising':
                    $sql .= " AND sa.role_id = 2";
                    break;
                case 'counseling':
                    $sql .= " AND sa.role_id = 3";
                    break;
                case 'support':
                    $sql .= " AND sa.role_id = 4";
                    break;
            }
        }
        
        // Order by day and time
        $sql .= " ORDER BY 
                  CASE 
                    WHEN sa.day = 'Monday' THEN 1
                    WHEN sa.day = 'Tuesday' THEN 2
                    WHEN sa.day = 'Wednesday' THEN 3
                    WHEN sa.day = 'Thursday' THEN 4
                    WHEN sa.day = 'Friday' THEN 5
                    WHEN sa.day = 'Saturday' THEN 6
                    WHEN sa.day = 'Sunday' THEN 7
                  END,
                  sa.start_time";
        
        logDebug("Executing query: " . $sql);
        
        $stmt = $pdo->query($sql);
        $availabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format each availability record as a schedule with a single provider
        $schedules = [];
        foreach ($availabilities as $availability) {
            $scheduleId = $availability['schedule_id'];
            
            $schedule = [
                'id' => $scheduleId,
                'day' => $availability['day'],
                'start_time' => $availability['start_time'],
                'end_time' => $availability['end_time'],
                'location' => $availability['location'] ?? '',
                'providers' => [
                    [
                        'provider_email' => $availability['staff_email'],
                        'provider_name' => $availability['provider_name'] ?? 'Staff Member',
                        'role_id' => $availability['role_id'],
                        'service_name' => $availability['service_name'],
                        'is_bookable' => ($availability['is_available'] == 1)
                    ]
                ],
                'bookable' => ($availability['is_available'] == 1)
            ];
            
            $schedules[] = $schedule;
        }
        
        logDebug("Found " . count($schedules) . " service entries in staff_availability");
        
        return [
            'success' => true,
            'schedules' => $schedules,
            'count' => count($schedules),
            'debug_sql' => $sql
        ];
        
    } catch (Exception $e) {
        logDebug("ERROR in getAvailableSchedules: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'schedules' => []
        ];
    }
}

/**
 * Handle get_schedules action
 */
function getSchedules($pdo) {
    try {
        // Get provider type filter if any
        $providerType = isset($_POST['service_type']) ? $_POST['service_type'] : '';
        
        // Get available schedules with providers
        $result = getAvailableSchedules($pdo, $providerType);
        
        // Add debugging info
        $result['debug_info'] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'provider_type_filter' => $providerType,
            'php_version' => PHP_VERSION
        ];
        
        // Return the result directly
        returnJson($result);
    } catch (Exception $e) {
        logDebug("Error in getSchedules: " . $e->getMessage());
        returnJson([
            'success' => false,
            'message' => 'Error fetching schedules: ' . $e->getMessage(),
            'schedules' => [],
            'count' => 0
        ]);
    }
}

/**
 * Update provider availability
 * 
 * @param PDO $pdo Database connection
 * @param string $providerEmail Provider email
 * @param array $schedules Array of schedule IDs
 * @return bool Success status
 */
function updateProviderAvailability($pdo, $providerEmail, $schedules) {
    try {
        $pdo->beginTransaction();
        
        // Delete existing availability for this provider
        $deleteStmt = $pdo->prepare("DELETE FROM availability WHERE email = :email");
        $deleteStmt->bindParam(':email', $providerEmail, PDO::PARAM_STR);
        $deleteStmt->execute();
        
        // Insert new availability
        $insertStmt = $pdo->prepare("INSERT INTO availability 
            (email, id, is_available) VALUES (:email, :scheduleId, 1)");
            
        foreach ($schedules as $scheduleId) {
            $insertStmt->bindParam(':email', $providerEmail, PDO::PARAM_STR);
            $insertStmt->bindParam(':scheduleId', $scheduleId, PDO::PARAM_INT);
            $insertStmt->execute();
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating availability: " . $e->getMessage());
        return false;
    }
}

/**
 * Toggle availability for a schedule
 *
 * @param PDO $pdo Database connection
 * @param int $scheduleId Schedule ID
 * @param string $email User email
 * @param bool $isAvailable New availability status
 * @return bool Success status
 */
function toggleAvailability($pdo, $scheduleId, $email, $isAvailable) {
    try {
        // Get user role_id
        $userStmt = $pdo->prepare("SELECT role_id FROM users WHERE email = :email");
        $userStmt->bindParam(':email', $email, PDO::PARAM_STR);
        $userStmt->execute();
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            logDebug("User not found for email: $email");
            return false;
        }
        
        $roleId = $userData['role_id'];
        
        // Get staff name based on role
        switch($roleId) {
            case 2:
                $nameStmt = $pdo->prepare("SELECT adv_name as staff_name FROM advisors WHERE adv_email = :email");
                break;
            case 3:
                $nameStmt = $pdo->prepare("SELECT coun_name as staff_name FROM counselors WHERE coun_email = :email");
                break;
            case 4:
                $nameStmt = $pdo->prepare("SELECT sup_name as staff_name FROM supporters WHERE sup_email = :email");
                break;
            default:
                throw new Exception('Invalid role ID');
        }
        $nameStmt->bindParam(':email', $email, PDO::PARAM_STR);
        $nameStmt->execute();
        $staffData = $nameStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get schedule details
        $scheduleStmt = $pdo->prepare("SELECT day, start_time, end_time FROM schedules WHERE id = :scheduleId");
        $scheduleStmt->bindParam(':scheduleId', $scheduleId, PDO::PARAM_INT);
        $scheduleStmt->execute();
        $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);

        // Check if a record already exists
        $checkStmt = $pdo->prepare("SELECT id FROM staff_availability 
                                   WHERE staff_email = :staff_email AND schedule_id = :scheduleId");
        $checkStmt->bindParam(':staff_email', $email, PDO::PARAM_STR);
        $checkStmt->bindParam(':scheduleId', $scheduleId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->fetch()) {
            // Update existing record
            $stmt = $pdo->prepare("UPDATE staff_availability 
                                  SET is_available = :isAvailable, updated_at = CURRENT_TIMESTAMP 
                                  WHERE staff_email = :staff_email AND schedule_id = :scheduleId");
        } else {
            // Insert new record
            $stmt = $pdo->prepare("INSERT INTO staff_availability 
                                  (staff_email, staff_name, schedule_id, role_id, is_available, day, start_time, end_time, created_at, updated_at) 
                                  VALUES (:staff_email, :staff_name, :scheduleId, :role_id, :isAvailable, :day, :start_time, :end_time, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $stmt->bindParam(':staff_name', $staffData['staff_name'], PDO::PARAM_STR);
            $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->bindParam(':day', $schedule['day'], PDO::PARAM_STR);
            $stmt->bindParam(':start_time', $schedule['start_time'], PDO::PARAM_STR);
            $stmt->bindParam(':end_time', $schedule['end_time'], PDO::PARAM_STR);
        }
        
        $stmt->bindParam(':staff_email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':scheduleId', $scheduleId, PDO::PARAM_INT);
        $stmt->bindParam(':isAvailable', $isAvailable, PDO::PARAM_BOOL);
        
        $result = $stmt->execute();
        logDebug($result ? "Successfully toggled availability" : "Failed to toggle availability");
        return $result;
    } catch (Exception $e) {
        logDebug("Error in toggleAvailability: " . $e->getMessage());
        return false;
    }
}

/**
 * BOOKING FUNCTIONALITY
 */

/**
 * Book an appointment with a provider
 */
function bookAppointment($pdo) {
    try {
        // Get POST parameters
        $userEmail = $_POST['user_email'] ?? '';
        $providerEmail = $_POST['provider_email'] ?? '';
        $scheduleId = $_POST['schedule_id'] ?? 0;
        $notes = $_POST['notes'] ?? '';
        
        if (empty($userEmail) || empty($providerEmail) || empty($scheduleId)) {
            returnJson([
                'success' => false,
                'message' => 'Missing required booking information'
            ]);
            return;
        }
        
        // Verify slot availability
        $checkSql = "SELECT sa.* 
                    FROM staff_availability sa
                    WHERE sa.schedule_id = :schedule_id 
                    AND sa.staff_email = :provider_email 
                    AND sa.is_available = 1";
                    
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindParam(':schedule_id', $scheduleId);
        $checkStmt->bindParam(':provider_email', $providerEmail);
        $checkStmt->execute();
        
        $availability = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$availability) {
            returnJson([
                'success' => false,
                'message' => 'This time slot is no longer available.'
            ]);
            return;
        }

        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Insert booking
            $bookingSql = "INSERT INTO bookings 
                          (user_email, provider_email, schedule_id, service_type, notes, status) 
                          VALUES (:user_email, :provider_email, :schedule_id, :service_type, :notes, 'pending')";
            
            $serviceType = getServiceNameByRoleId($availability['role_id']);
            
            $bookingStmt = $pdo->prepare($bookingSql);
            $bookingStmt->bindParam(':user_email', $userEmail);
            $bookingStmt->bindParam(':provider_email', $providerEmail);
            $bookingStmt->bindParam(':schedule_id', $scheduleId);
            $bookingStmt->bindParam(':service_type', $serviceType);
            $bookingStmt->bindParam(':notes', $notes);
            $bookingStmt->execute();
            
            // Update availability
            $updateSql = "UPDATE staff_availability 
                         SET is_available = 0 
                         WHERE schedule_id = :schedule_id 
                         AND staff_email = :provider_email";
            
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->bindParam(':schedule_id', $scheduleId);
            $updateStmt->bindParam(':provider_email', $providerEmail);
            $updateStmt->execute();
            
            $pdo->commit();
            
            returnJson([
                'success' => true,
                'message' => 'Appointment booked successfully!'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        returnJson([
            'success' => false,
            'message' => 'Database Error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get bookings for a user or provider
 * 
 * @param PDO $pdo Database connection
 * @param string $email User email
 * @param string $userType 'client' or 'provider'
 * @return array Array of bookings
 */
function getBookings($pdo, $email, $userType = 'client') {
    try {
        // Fixed SQL queries with correct column names and JOIN conditions
        if ($userType == 'client') {
            $sql = "SELECT b.*, 
                    s.day, s.start_time, s.end_time,
                    '' as location,
                    b.user_email as client_name, 
                    b.provider_email as provider_name, 
                    b.service_type as provider_type
                FROM bookings b
                LEFT JOIN schedules s ON s.user_type = b.service_type AND s.start_time = b.booking_time
                WHERE b.user_email = :email
                ORDER BY b.booking_date, b.booking_time";
        } else {
            $sql = "SELECT b.*, 
                    s.day, s.start_time, s.end_time,
                    '' as location,
                    b.user_email as client_name, 
                    b.provider_email as provider_name, 
                    b.service_type as provider_type
                FROM bookings b
                LEFT JOIN schedules s ON s.user_type = b.service_type AND s.start_time = b.booking_time
                WHERE b.provider_email = :email
                ORDER BY b.booking_date, b.booking_time";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        logDebug("Retrieved " . $stmt->rowCount() . " bookings for $email (userType: $userType)");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error retrieving bookings: " . $e->getMessage());
        logDebug("Error retrieving bookings: " . $e->getMessage());
        return [];
    }
}

/**
 * Update booking status
 * 
 * @param PDO $pdo Database connection
 * @param int $bookingId Booking ID
 * @param string $status New status ('confirmed', 'cancelled', 'completed')
 * @param string $providerEmail Provider email (for authorization)
 * @return bool Success status
 */
function updateBookingStatus($pdo, $bookingId, $status, $providerEmail) {
    try {
        // Fixed column name from advisor_email to provider_email
        $checkStmt = $pdo->prepare("SELECT * FROM bookings 
                                  WHERE id = :bookingId AND provider_email = :providerEmail");
        $checkStmt->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
        $checkStmt->bindParam(':providerEmail', $providerEmail, PDO::PARAM_STR);
        $checkStmt->execute();
        
        if (!$checkStmt->fetch()) {
            logDebug("Not authorized to update booking $bookingId by $providerEmail");
            return false; // Not authorized
        }
        
        // Update booking status
        $updateStmt = $pdo->prepare("UPDATE bookings SET status = :status WHERE id = :bookingId");
        $updateStmt->bindParam(':status', $status, PDO::PARAM_STR);
        $updateStmt->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
        $updateStmt->execute();
        
        logDebug("Updated booking $bookingId status to $status");
        return true;
    } catch (PDOException $e) {
        error_log("Error updating booking status: " . $e->getMessage());
        logDebug("Error updating booking status: " . $e->getMessage());
        return false;
    }
}

/**
 * EMERGENCY NOTIFICATION SYSTEM
 */

/**
 * Get emergency contacts for a specific user
 * 
 * @param PDO $pdo Database connection
 * @param string $userEmail Email of the user
 * @return array List of emergency contacts
 */
function getEmergencyContacts($pdo, $userEmail) {
    try {
        // Get contacts using email directly
        $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE email = :email ORDER BY created_at DESC");
        $stmt->bindParam(':email', $userEmail, PDO::PARAM_STR);
        $stmt->execute();
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Map to the expected field names for the frontend
        $mappedContacts = array_map(function($contact) {
            return [
                'id' => $contact['id'],
                'name' => $contact['emergency_name'],
                'email' => $contact['emergency_email'],
                'phone' => $contact['em_number'],
                'relationship' => $contact['relationship'],
                'created_at' => $contact['created_at']
            ];
        }, $contacts);
        
        return [
            'success' => true,
            'data' => $mappedContacts
        ];
    } catch (PDOException $e) {
        error_log("Error fetching emergency contacts: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error fetching emergency contacts: ' . $e->getMessage()
        ];
    }
}

/**
 * Send emergency SMS to all contacts for a specific user
 * 
 * @param PDO $pdo Database connection
 * @param string $message Emergency message
 * @param string $sentBy Name of person sending
 * @param string $userEmail Email of the user whose contacts should receive SMS
 * @return array Results of sending (success count, fail count)
 */
function sendEmergencySMS($pdo, $message, $sentBy, $userEmail = null) {
    try {
        // Get emergency contacts for the specific user
        if ($userEmail) {
            $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE email = :email AND active = 1");
            $stmt->bindParam(':email', $userEmail, PDO::PARAM_STR);
            $stmt->execute();
        } else {
            $stmt = $pdo->query("SELECT * FROM emergency_contacts WHERE active = 1");
        }
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($contacts)) {
            return ['success' => 0, 'fail' => 0, 'message' => 'No emergency contacts found'];
        }
        
        $successCount = 0;
        $failCount = 0;
        $errorMessages = [];
        
        // Format message
        $fullMessage = "EMERGENCY ALERT from {$sentBy}: {$message}";
        
        // Twilio credentials (directly defined, not using environment variables)
        $account_sid = 'AC0303d1817540e57d2bf2e7ad730764d5'; // Your actual SID
        $auth_token = '0aebd7ab8dd1441cfcbd7ffec24cafe2';    // Your actual token
        $twilio_number = '+12184605587';                      // Your Twilio phone number
        
        // Create a new Twilio client
        try {
            $client = new Client($account_sid, $auth_token);
            
            // Send SMS to each contact
            foreach ($contacts as $contact) {
                try {
                    // Ensure phone number has proper format (E.164 format)
                    $phoneNumber = $contact['phone_number'];
                    // Add + prefix if not present and ensure it has country code
                    if (substr($phoneNumber, 0, 1) !== '+') {
                        // If no country code, assume India (+91) for 10-digit numbers
                        if (strlen($phoneNumber) == 10) {
                            $phoneNumber = '+91' . $phoneNumber;
                        } else {
                            $phoneNumber = '+' . $phoneNumber;
                        }
                    }
                    
                    logDebug("Sending SMS to: " . $phoneNumber . " from: " . $twilio_number);
                    
                    // Log before sending
                    logDebug("Attempting to send SMS via Twilio - From: {$twilio_number}, To: {$phoneNumber}");
                    
                    // Send the message via Twilio
                    $result = $client->messages->create(
                        $phoneNumber,
                        [
                            'from' => $twilio_number,
                            'body' => $fullMessage
                        ]
                    );
                    
                    // Log ALL details from the response
                    logDebug("TWILIO RESPONSE: " . json_encode([
                        'sid' => $result->sid,
                        'status' => $result->status,
                        'error_code' => $result->errorCode,
                        'error_message' => $result->errorMessage,
                        'date_created' => $result->dateCreated->format('Y-m-d H:i:s'),
                        'date_sent' => $result->dateSent ? $result->dateSent->format('Y-m-d H:i:s') : null,
                        'direction' => $result->direction,
                        'price' => $result->price,
                        'price_unit' => $result->priceUnit
                    ]));
                    
                    // Check if the status indicates success
                    if ($result->status == 'queued' || $result->status == 'sent' || $result->status == 'delivered') {
                        // Success handling
                        $successCount++;
                    } else {
                        // Failure handling
                        $failCount++;
                        $errorMessages[] = "Message to {$contact['emergency_name']} queued but status is: {$result->status}";
                    }
                    
                    // Record success in database
                    $logStmt = $pdo->prepare("INSERT INTO emergency_logs 
                        (contact_id, message, sent_by, sent_at, status, twilio_sid) 
                        VALUES (:contactId, :message, :sentBy, NOW(), :status, :twilioSid)");
                    $logStmt->bindParam(':contactId', $contact['id'], PDO::PARAM_INT);
                    $logStmt->bindParam(':message', $message, PDO::PARAM_STR);
                    $logStmt->bindParam(':sentBy', $sentBy, PDO::PARAM_STR);
                    $logStmt->bindParam(':status', $result->status, PDO::PARAM_STR);
                    $logStmt->bindParam(':twilioSid', $result->sid, PDO::PARAM_STR);
                    $logStmt->execute();
                    
                } catch (Exception $e) {
                    logDebug("Failed to send SMS: " . $e->getMessage());
                    
                    $failCount++;
                    $errorMessages[] = "Failed to send to {$contact['name']}: {$e->getMessage()}";
                    
                    // Log the error in database
                    $logStmt = $pdo->prepare("INSERT INTO emergency_logs 
                        (contact_id, message, sent_by, sent_at, status, error) 
                        VALUES (:contactId, :message, :sentBy, NOW(), 'failed', :error)");
                    $logStmt->bindParam(':contactId', $contact['id'], PDO::PARAM_INT);
                    $logStmt->bindParam(':message', $message, PDO::PARAM_STR);
                    $logStmt->bindParam(':sentBy', $sentBy, PDO::PARAM_STR);
                    $logStmt->bindParam(':error', $e->getMessage(), PDO::PARAM_STR);
                    $logStmt->execute();
                }
            }
            
            return [
                'success' => $successCount,
                'fail' => $failCount,
                'errors' => $errorMessages,
                'message' => "Successfully sent {$successCount} emergency messages"
            ];
            
        } catch (Exception $e) {
            logDebug("Twilio client error: " . $e->getMessage());
            return ['success' => 0, 'fail' => count($contacts), 'message' => 'SMS service error: ' . $e->getMessage()];
        }
    } catch (PDOException $e) {
        logDebug("Database error in emergency SMS: " . $e->getMessage());
        return ['success' => 0, 'fail' => 0, 'message' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        logDebug("General error in emergency SMS: " . $e->getMessage());
        return ['success' => 0, 'fail' => 0, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Add new emergency contact
 * 
 * @param PDO $pdo Database connection
 * @param string $name Contact name
 * @param string $phoneNumber Phone number with country code
 * @param string $relationship Relationship to organization
 * @return int|bool New contact ID or false on failure
 */
function addEmergencyContact($pdo, $name, $phoneNumber, $relationship) {
    try {
        $stmt = $pdo->prepare("INSERT INTO emergency_contacts 
            (name, phone_number, relationship, active, created_at) 
            VALUES (:name, :phoneNumber, :relationship, 1, NOW())");
            
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':phoneNumber', $phoneNumber, PDO::PARAM_STR);
        $stmt->bindParam(':relationship', $relationship, PDO::PARAM_STR);
        $stmt->execute();
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error adding emergency contact: " . $e->getMessage());
        return false;
    }
}

/**
 * Get provider type from role ID
 * 
 * @param int $roleId Role ID (2=advisor, 3=counselor, 4=supporter)
 * @return string Provider type
 */
function getProviderTypeFromRoleId($roleId) {
    switch ($roleId) {
        case 2:
            return 'advising';
        case 3:
            return 'counseling';
        case 4:
            return 'support';
        default:
            return 'unknown';
    }
}

/**
 * Get available staff with their schedules
 */
function getAvailableStaff($pdo) {
    try {
        $serviceType = $_POST['service_type'] ?? '';
        
        $sql = "SELECT 
                    sa.schedule_id, sa.staff_email, sa.staff_name,
                    sa.day, sa.start_time, sa.end_time, 
                    sa.is_available, sa.role_id
                FROM 
                    staff_availability sa
                WHERE 
                    sa.is_available = 1";
        
        if (!empty($serviceType)) {
            switch($serviceType) {
                case 'advising':
                    $sql .= " AND sa.role_id = 2";
                    break;
                case 'counseling':
                    $sql .= " AND sa.role_id = 3";
                    break;
                case 'support':
                    $sql .= " AND sa.role_id = 4";
                    break;
            }
        }
        
        $sql .= " ORDER BY 
                    CASE 
                        WHEN sa.day = 'Monday' THEN 1
                        WHEN sa.day = 'Tuesday' THEN 2
                        WHEN sa.day = 'Wednesday' THEN 3
                        WHEN sa.day = 'Thursday' THEN 4
                        WHEN sa.day = 'Friday' THEN 5
                        WHEN sa.day = 'Saturday' THEN 6
                        WHEN sa.day = 'Sunday' THEN 7
                    END,
                    sa.start_time";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $availability = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $availability
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Add time slot columns to staff_availability table
 */
function alterStaffAvailabilityTable($pdo) {
    try {
        // First check if columns already exist
        $columnsExist = false;
        try {
            $checkStmt = $pdo->query("SELECT day FROM staff_availability LIMIT 1");
            $columnsExist = true;
        } catch (PDOException $e) {
            // Column doesn't exist - that's expected
            $columnsExist = false;
        }
        
        if ($columnsExist) {
            returnJson([
                'success' => true,
                'message' => 'Columns already exist in staff_availability table.'
            ]);
            return;
        }
        
        // Add new columns to staff_availability
        $alterTableSql = "
            ALTER TABLE staff_availability 
            ADD day VARCHAR(20) NULL AFTER schedule_id,
            ADD start_time TIME NULL AFTER day,
            ADD end_time TIME NULL AFTER start_time,
            ADD location VARCHAR(100) NULL AFTER end_time
        ";
        
        $pdo->exec($alterTableSql);
        
        // Update existing records with time slot information from schedules table
        $updateSql = "
            UPDATE staff_availability sa
            JOIN schedules s ON sa.schedule_id = s.id
            SET 
                sa.day = s.day,
                sa.start_time = s.start_time,
                sa.end_time = s.end_time,
                sa.location = s.location
        ";
        
        $pdo->exec($updateSql);
        
        // Create an index for better performance
        $indexSql = "
            CREATE INDEX idx_staff_availability_time 
            ON staff_availability(day, start_time, end_time)
        ";
        
        $pdo->exec($indexSql);
        
        returnJson([
            'success' => true,
            'message' => 'Successfully added time slot columns to staff_availability table and updated existing records.'
        ]);
    } catch (PDOException $e) {
        returnJson([
            'success' => false,
            'message' => 'Error altering staff_availability table: ' . $e->getMessage()
        ]);
    }
}

/**
 * Create test staff availability data for demonstration
 */
function createTestAvailabilityData($pdo) {
    try {
        // First check if we already have availability data
        $count = $pdo->query("SELECT COUNT(*) FROM staff_availability")->fetchColumn();
        
        if ($count > 0) {
            return [
                'success' => true,
                'message' => 'Test data not created. Staff availability table already has ' . $count . ' records.',
                'count' => $count
            ];
        }
        
        // Get active schedules
        $schedules = $pdo->query("SELECT id FROM schedules WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($schedules)) {
            return [
                'success' => false,
                'message' => 'No active schedules found. Please create schedules first.'
            ];
        }
        
        // Get staff with provider roles
        $providers = $pdo->query("
            SELECT id, email, role_id 
            FROM users 
            WHERE role_id IN (2,3,4) AND is_active = 1
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($providers)) {
            return [
                'success' => false,
                'message' => 'No active providers (staff with role_id 2, 3, or 4) found. Please create providers first.'
            ];
        }
        
        // Get schedule details to copy to staff_availability
        $scheduleDetails = [];
        foreach ($schedules as $scheduleId) {
            $stmt = $pdo->prepare("SELECT id, day, start_time, end_time, location FROM schedules WHERE id = :id");
            $stmt->bindParam(':id', $scheduleId);
            $stmt->execute();
            $scheduleDetails[$scheduleId] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Insert sample availability data
        $insertStmt = $pdo->prepare("
            INSERT INTO staff_availability 
            (staff_id, staff_email, schedule_id, role_id, is_available, day, start_time, end_time, location) 
            VALUES (:staff_id, :staff_email, :schedule_id, :role_id, 1, :day, :start_time, :end_time, :location)
        ");
        
        $insertCount = 0;
        
        // Create at least one availability for each provider
        foreach ($providers as $provider) {
            // Assign each provider to a random schedule
            $randomScheduleId = $schedules[array_rand($schedules)];
            $scheduleDetail = $scheduleDetails[$randomScheduleId];
            
            if (!$scheduleDetail) continue;
            
            $insertStmt->bindParam(':staff_id', $provider['id']);
            $insertStmt->bindParam(':staff_email', $provider['email']);
            $insertStmt->bindParam(':schedule_id', $randomScheduleId);
            $insertStmt->bindParam(':role_id', $provider['role_id']);
            $insertStmt->bindParam(':day', $scheduleDetail['day']);
            $insertStmt->bindParam(':start_time', $scheduleDetail['start_time']);
            $insertStmt->bindParam(':end_time', $scheduleDetail['end_time']);
            $insertStmt->bindParam(':location', $scheduleDetail['location']);
            $insertStmt->execute();
            $insertCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Successfully created ' . $insertCount . ' test availability records',
            'count' => $insertCount
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error creating test data: ' . $e->getMessage()
        ];
    }
}

/**
 * Get all available slots directly from staff_availability 
 */
function getAllSchedules($pdo, $serviceType = '') {
    try {
        logDebug("Getting all slots directly from staff_availability with filter: " . $serviceType);
        
        // Direct query from staff_availability with no joins or provider checks
        $sql = "SELECT * FROM staff_availability WHERE 1=1";
        
        // Apply service type filter if provided
        if (!empty($serviceType)) {
            switch($serviceType) {
                case 'advising':
                    $sql .= " AND role_id = 2";
                    break;
                case 'counseling':
                    $sql .= " AND role_id = 3";
                    break;
                case 'support':
                    $sql .= " AND role_id = 4";
                    break;
            }
        }
        
        // Order by day and time
        $sql .= " ORDER BY 
                  CASE 
                    WHEN day = 'Monday' THEN 1
                    WHEN day = 'Tuesday' THEN 2
                    WHEN day = 'Wednesday' THEN 3
                    WHEN day = 'Thursday' THEN 4
                    WHEN day = 'Friday' THEN 5
                    WHEN day = 'Saturday' THEN 6
                    WHEN day = 'Sunday' THEN 7
                  END,
                  start_time";
        
        logDebug("Executing query: " . $sql);
        
        $stmt = $pdo->query($sql);
        $availabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logDebug("Found " . count($availabilities) . " staff availability records");
        
        return [
            'success' => true,
            'schedules' => $availabilities,
            'count' => count($availabilities)
        ];
        
    } catch (Exception $e) {
        logDebug("ERROR in getAllSchedules: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'schedules' => []
        ];
    }
}

// Main handler for service AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the action parameter
    $action = $_POST['action'] ?? '';
    
    // Set the response content type to JSON
    header('Content-Type: application/json');
    
    // Connect to database
    $pdo = connectDB();
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Handle different actions
    switch ($action) {
        case 'get_schedules':
            try {
                // Get provider type filter if any
                $providerType = isset($_POST['service_type']) ? $_POST['service_type'] : '';
                
                // Get available schedules with providers
                $schedules = getAvailableSchedules($pdo, $providerType);
                
                returnJson([
                    'success' => true,
                    'schedules' => $schedules,
                    'count' => count($schedules)
                ]);
            } catch (Exception $e) {
                logDebug("Error in getSchedules: " . $e->getMessage());
                returnJson([
                    'success' => false,
                    'message' => 'Error fetching schedules: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'book_service':
            bookAppointment($pdo);
            break;
            
        case 'update_availability':
            $providerEmail = $_POST['provider_email'] ?? '';
            $scheduleIds = json_decode($_POST['schedules'] ?? '[]', true);
            
            if (!$providerEmail || empty($scheduleIds)) {
                echo json_encode(['success' => false, 'message' => 'Missing required data']);
                break;
            }
            
            $result = updateProviderAvailability($pdo, $providerEmail, $scheduleIds);
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Availability updated successfully' : 'Failed to update availability'
            ]);
            break;
            
        case 'get_bookings':
            $email = $_POST['user_email'] ?? '';
            $userType = $_POST['user_type'] ?? 'client';
            
            if (!$email) {
                echo json_encode(['success' => false, 'message' => 'Missing user email']);
                break;
            }
            
            $response = [
                'success' => true,
                'data' => getBookings($pdo, $email, $userType)
            ];
            echo json_encode($response);
            break;
            
        case 'update_booking':
            $bookingId = $_POST['booking_id'] ?? 0;
            $status = $_POST['status'] ?? '';
            $providerEmail = $_POST['provider_email'] ?? '';
            
            if (!$bookingId || !$status || !$providerEmail) {
                echo json_encode(['success' => false, 'message' => 'Missing required data']);
                break;
            }
            
            $result = updateBookingStatus($pdo, $bookingId, $status, $providerEmail);
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Booking updated successfully' : 'Failed to update booking'
            ]);
            break;
            
        case 'get_emergency_contacts':
            $userEmail = $_POST['user_email'] ?? '';
            
            if (empty($userEmail)) {
                echo json_encode(['success' => false, 'message' => 'Missing user email']);
                break;
            }
            
            try {
                // Get contacts using email directly (based on your table structure)
                $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE email = :email ORDER BY created_at DESC");
                $stmt->bindParam(':email', $userEmail, PDO::PARAM_STR);
                $stmt->execute();
                $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Map to the expected field names for the frontend
                $mappedContacts = array_map(function($contact) {
                    return [
                        'id' => $contact['id'],
                        'name' => $contact['emergency_name'],
                        'email' => $contact['emergency_email'],
                        'phone' => $contact['em_number'],
                        'relationship' => $contact['relationship'],
                        'created_at' => $contact['created_at']
                    ];
                }, $contacts);
                
                echo json_encode([
                    'success' => true,
                    'data' => $mappedContacts
                ]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching emergency contacts: ' . $e->getMessage()]);
            }
            break;
            
        case 'add_emergency_contact':
            $userEmail = $_POST['user_email'] ?? '';
            $contactName = $_POST['contact_name'] ?? '';
            $contactPhone = $_POST['contact_phone'] ?? '';
            $contactRelationship = $_POST['contact_relationship'] ?? '';
            $contactEmail = $_POST['contact_email'] ?? '';
            
            if (empty($userEmail) || empty($contactName) || empty($contactPhone) || empty($contactRelationship)) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                break;
            }
            
            try {
                // Add contact using your table structure
                $stmt = $pdo->prepare("INSERT INTO emergency_contacts 
                                       (email, emergency_name, emergency_email, em_number, relationship) 
                                       VALUES (:email, :emergencyName, :emergencyEmail, :emNumber, :relationship)");
                $stmt->bindParam(':email', $userEmail, PDO::PARAM_STR);
                $stmt->bindParam(':emergencyName', $contactName, PDO::PARAM_STR);
                $stmt->bindParam(':emergencyEmail', $contactEmail, PDO::PARAM_STR);
                $stmt->bindParam(':emNumber', $contactPhone, PDO::PARAM_STR);
                $stmt->bindParam(':relationship', $contactRelationship, PDO::PARAM_STR);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Emergency contact added successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error adding emergency contact: ' . $e->getMessage()]);
            }
            break;
            
        case 'delete_emergency_contact':
            $userEmail = $_POST['user_email'] ?? '';
            $contactId = $_POST['contact_id'] ?? '';
            
            if (empty($userEmail) || empty($contactId)) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                break;
            }
            
            try {
                // Delete contact (ensuring it belongs to the user)
                $stmt = $pdo->prepare("DELETE FROM emergency_contacts WHERE id = :contactId AND email = :email");
                $stmt->bindParam(':contactId', $contactId, PDO::PARAM_INT);
                $stmt->bindParam(':email', $userEmail, PDO::PARAM_STR);
                $stmt->execute();
                
                if ($stmt->rowCount() == 0) {
                    echo json_encode(['success' => false, 'message' => 'Contact not found or not authorized to delete']);
                    break;
                }
                
                echo json_encode(['success' => true, 'message' => 'Emergency contact deleted successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error deleting emergency contact: ' . $e->getMessage()]);
            }
            break;
            
        case 'send_emergency_sms':
            $userEmail = $_POST['user_email'] ?? '';
            $message = $_POST['message'] ?? '';
            
            if (empty($userEmail) || empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                break;
            }
            
            try {
                // Get user ID and name
                $userStmt = $pdo->prepare("SELECT id, name FROM users WHERE email = :email");
                $userStmt->bindParam(':email', $userEmail, PDO::PARAM_STR);
                $userStmt->execute();
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                    break;
                }
                
                $userId = $user['id'];
                $userName = $user['name'];
                
                // Get user's emergency contacts
                $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE email = :email");
                $stmt->bindParam(':email', $userEmail, PDO::PARAM_STR);
                $stmt->execute();
                $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($contacts)) {
                    echo json_encode(['success' => false, 'message' => 'No emergency contacts found']);
                    break;
                }
                
                // Create emergency_alerts table if it doesn't exist
                $pdo->exec("CREATE TABLE IF NOT EXISTS emergency_alerts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sender_id INT,
                    sender_name VARCHAR(255),
                    recipients_count INT,
                    message_id VARCHAR(50),
                    status VARCHAR(20),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                // Generate message ID for tracking
                $messageId = 'MSG_' . time() . '_' . rand(1000, 9999);
                $recipientsCount = count($contacts);
                
                // Insert emergency alert
                $alertStmt = $pdo->prepare("INSERT INTO emergency_alerts 
                    (sender_id, sender_name, recipients_count, message_id, status) 
                    VALUES (:senderId, :senderName, :recipientsCount, :messageId, 'sent')");
                    
                $alertStmt->bindParam(':senderId', $userId, PDO::PARAM_INT);
                $alertStmt->bindParam(':senderName', $userName, PDO::PARAM_STR);
                $alertStmt->bindParam(':recipientsCount', $recipientsCount, PDO::PARAM_INT);
                $alertStmt->bindParam(':messageId', $messageId, PDO::PARAM_STR);
                $alertStmt->execute();
                
                $alertId = $pdo->lastInsertId();
                logDebug("Created emergency alert with ID: " . $alertId);
                
                // Check if contact_alerts table exists, create if not
                $pdo->exec("CREATE TABLE IF NOT EXISTS contact_alerts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    alert_id INT NOT NULL,
                    contact_id INT NOT NULL,
                    message TEXT NOT NULL,
                    status VARCHAR(20) DEFAULT 'sent',
                    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY `
                    KEY `alert_id` (`alert_id`),
                    KEY `contact_id` (`contact_id`)
                )");
                
                // Create emergency_logs table if it doesn't exist
                $pdo->exec("CREATE TABLE IF NOT EXISTS emergency_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    contact_id INT,
                    message TEXT,
                    sent_by VARCHAR(255),
                    sent_at TIMESTAMP,
                    status VARCHAR(20),
                    error TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                // Record the message sent to each contact
                $contactStmt = $pdo->prepare("INSERT INTO contact_alerts (alert_id, contact_id, message) 
                                             VALUES (:alertId, :contactId, :message)");
                
                foreach ($contacts as $contact) {
                    $contactStmt->bindParam(':alertId', $alertId, PDO::PARAM_INT);
                    $contactStmt->bindParam(':contactId', $contact['id'], PDO::PARAM_INT);
                    $contactStmt->bindParam(':message', $message, PDO::PARAM_STR);
                    $contactStmt->execute();
                }
                
                // Set response instead of echoing directly
                echo json_encode([
                    'success' => true,
                    'message' => 'Emergency alert sent successfully to ' . $recipientsCount . ' contacts',
                    'contacts' => $recipientsCount,
                    'alert_id' => $alertId
                ]);
            } catch (PDOException $e) {
                logDebug("Error in send_emergency_sms: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error sending emergency alert: ' . $e->getMessage()]);
            }
            break;
            
        case 'emergency_diagnostic':
            // Check database connection
            try {
                // Check schedules table
                $schedulesCount = $pdo->query("SELECT COUNT(*) FROM schedules WHERE is_active = 1")->fetchColumn();
                
                // Check available providers
                $providersSQL = "SELECT role_id, COUNT(*) as count FROM users WHERE role_id IN (2,3,4) GROUP BY role_id";
                $providersStmt = $pdo->query($providersSQL);
                $providers = $providersStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Check availability records
                $availabilitySQL = "SELECT COUNT(*) FROM availability WHERE is_available = 1";
                $availabilityCount = $pdo->query($availabilitySQL)->fetchColumn();
                
                // Gather test data
                $testData = [
                    'database_connected' => true,
                    'active_schedules' => $schedulesCount,
                    'providers' => $providers,
                    'available_slots' => $availabilityCount
                ];
                
                echo json_encode(['success' => true, 'diagnostic' => $testData]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'db_check':
            try {
                $schedules = $pdo->query("SELECT COUNT(*) FROM schedules WHERE is_active = 1")->fetchColumn();
                $providers = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id IN (2,3,4)")->fetchColumn();
                $availability = $pdo->query("SELECT COUNT(*) FROM availability WHERE is_available = 1")->fetchColumn();
                
                echo json_encode([
                    'success' => true,
                    'schedules' => $schedules,
                    'providers' => $providers,
                    'availability' => $availability
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'check_availability':
            try {
                $scheduleId = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
                
                if ($scheduleId <= 0) {
                    returnJson([
                        'success' => false,
                        'message' => 'Invalid schedule ID'
                    ]);
                }
                
                // Check if schedule exists and is active
                $scheduleSql = "SELECT * FROM schedules WHERE id = :id AND is_active = 1";
                $scheduleStmt = $pdo->prepare($scheduleSql);
                $scheduleStmt->bindParam(':id', $scheduleId);
                $scheduleStmt->execute();
                $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$schedule) {
                    returnJson([
                        'success' => false,
                        'message' => 'Schedule not found or inactive'
                    ]);
                }
                
                // Get available providers for this schedule
                $providersSql = "SELECT u.id, u.name, u.email, r.name as provider_type
                                 FROM staff_availability sa
                                 JOIN users u ON sa.staff_id = u.id
                                 JOIN roles r ON u.role_id = r.id
                                 WHERE sa.schedule_id = :schedule_id
                                 AND sa.is_available = 1
                                 AND u.is_active = 1
                                 ORDER BY r.name, u.name";
                
                $providersStmt = $pdo->prepare($providersSql);
                $providersStmt->bindParam(':schedule_id', $scheduleId);
                $providersStmt->execute();
                $providers = $providersStmt->fetchAll(PDO::FETCH_ASSOC);
                
                returnJson([
                    'success' => true,
                    'is_available' => !empty($providers),
                    'providers' => $providers,
                    'schedule' => $schedule
                ]);
            } catch (Exception $e) {
                returnJson([
                    'success' => false,
                    'message' => 'Error checking availability: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'debug_availability':
            // Add this new action to help troubleshoot
            $debug = debugAvailability($pdo);
            returnJson([
                'success' => true,
                'debug_info' => $debug
            ]);
            break;
            
        case 'get_available_staff':
            getAvailableStaff($pdo);
            break;
            
        case 'alter_staff_availability':
            alterStaffAvailabilityTable($pdo);
            break;
            
        case 'create_test_data':
            $result = createTestAvailabilityData($pdo);
            returnJson($result);
            break;
            
        case 'get_all_schedules':
            $serviceType = $_POST['service_type'] ?? '';
            $result = getAllSchedules($pdo, $serviceType);
            returnJson($result);
            break;
            
        case 'book_schedule':
            $availabilityId = isset($_POST['availability_id']) ? intval($_POST['availability_id']) : 0;
            $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
            
            if (!$availabilityId) {
                returnJson(['success' => false, 'message' => 'Invalid availability ID']);
                break;
            }
            
            if (!isset($_SESSION['email'])) {
                returnJson(['success' => false, 'message' => 'You must be logged in to book a slot']);
                break;
            }
            
            $userEmail = $_SESSION['email'];
            
            try {
                // Verify the user email exists in the users table
                $userCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
                $userCheckStmt->bindParam(':email', $userEmail);
                $userCheckStmt->execute();
                
                if ($userCheckStmt->fetchColumn() == 0) {
                    returnJson(['success' => false, 'message' => 'Your email is not registered in the system']);
                    break;
                }
                
                // Get the staff availability details
                $availStmt = $pdo->prepare("SELECT * FROM staff_availability WHERE id = :id");
                $availStmt->bindParam(':id', $availabilityId, PDO::PARAM_INT);
                $availStmt->execute();
                $availability = $availStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$availability) {
                    returnJson(['success' => false, 'message' => 'Time slot not found']);
                    break;
                }
                
                // Get schedule_id from availability
                $scheduleId = $availability['schedule_id'] ?? 0;
                
                // Determine service type based on role_id
                $serviceType = 'General Service';
                switch ($availability['role_id'] ?? 0) {
                    case 2:
                        $serviceType = 'Academic Advising';
                        break;
                    case 3:
                        $serviceType = 'Personal Counseling';
                        break;
                    case 4:
                        $serviceType = 'Technical Support';
                        break;
                }
                
                // Find a valid provider email that exists in the users table
                $providerEmail = null;
                
                // First check if staff_email from availability exists in users table
                if (!empty($availability['staff_email'])) {
                    $checkStmt = $pdo->prepare("SELECT email FROM users WHERE email = :email");
                    $checkStmt->bindParam(':email', $availability['staff_email']);
                    $checkStmt->execute();
                    $existingEmail = $checkStmt->fetchColumn();
                    
                    if ($existingEmail) {
                        $providerEmail = $existingEmail;
                    }
                }
                
                // If no valid provider email yet, find a user with matching role_id
                if (!$providerEmail && isset($availability['role_id'])) {
                    $roleId = $availability['role_id'];
                    $roleStmt = $pdo->prepare("SELECT email FROM users WHERE role_id = :roleId LIMIT 1");
                    $roleStmt->bindParam(':roleId', $roleId);
                    $roleStmt->execute();
                    $roleEmail = $roleStmt->fetchColumn();
                    
                    if ($roleEmail) {
                        $providerEmail = $roleEmail;
                    }
                }
                
                // If still no provider email, get any admin user
                if (!$providerEmail) {
                    $adminStmt = $pdo->prepare("SELECT email FROM users WHERE role_id = 1 LIMIT 1");
                    $adminStmt->execute();
                    $adminEmail = $adminStmt->fetchColumn();
                    
                    if ($adminEmail) {
                        $providerEmail = $adminEmail;
                    }
                }
                
                // Last resort: get any user with a valid email (even if it's the current user)
                if (!$providerEmail) {
                    $anyUserStmt = $pdo->prepare("SELECT email FROM users LIMIT 1");
                    $anyUserStmt->execute();
                    $anyEmail = $anyUserStmt->fetchColumn();
                    
                    if ($anyEmail) {
                        $providerEmail = $anyEmail;
                    } else {
                        // If we can't find any valid email, we can't proceed
                        returnJson(['success' => false, 'message' => 'Cannot book: No valid service providers in the system']);
                        break;
                    }
                }
                
                // Log the provider we're using
                logDebug("Using provider email for booking: " . $providerEmail);
                
                // Insert the booking record with the exact column structure
                $sql = "INSERT INTO bookings (user_email, provider_email, schedule_id, service_type, status, notes) 
                        VALUES (:userEmail, :providerEmail, :scheduleId, :serviceType, 'pending', :notes)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':userEmail', $userEmail, PDO::PARAM_STR);
                $stmt->bindParam(':providerEmail', $providerEmail, PDO::PARAM_STR);
                $stmt->bindParam(':scheduleId', $scheduleId, PDO::PARAM_INT);
                $stmt->bindParam(':serviceType', $serviceType, PDO::PARAM_STR);
                $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
                $stmt->execute();
                
                $bookingId = $pdo->lastInsertId();
                
                returnJson([
                    'success' => true, 
                    'message' => 'Your booking has been successfully submitted and is awaiting confirmation!',
                    'booking_id' => $bookingId
                ]);
            } catch (Exception $e) {
                logDebug("Error booking time slot: " . $e->getMessage());
                
                // Provide detailed error for debugging
                returnJson([
                    'success' => false, 
                    'message' => 'Error booking time slot: ' . $e->getMessage(),
                    'details' => [
                        'availability_id' => $availabilityId,
                        'user_email' => $userEmail,
                        'provider_email' => $providerEmail ?? 'none',
                        'schedule_id' => $scheduleId ?? 0,
                        'service_type' => $serviceType ?? 'unknown'
                    ]
                ]);
            }
            break;
            
        case 'get_my_bookings':
            if (!isset($_SESSION['email'])) {
                returnJson(['success' => false, 'message' => 'Not logged in']);
                break;
            }
            
            $userEmail = $_SESSION['email'];
            
            try {
                // Get all bookings for the current user - removed location from the query
                $stmt = $pdo->prepare("
                    SELECT b.*, 
                           DATE_FORMAT(b.booking_date, '%M %d, %Y %h:%i %p') as formatted_date,
                           s.day, s.start_time, s.end_time,
                           u.name as provider_name
                    FROM bookings b
                    LEFT JOIN schedules s ON b.schedule_id = s.id
                    LEFT JOIN users u ON b.provider_email = u.email
                    WHERE b.user_email = :email
                    ORDER BY b.booking_date DESC
                ");
                $stmt->bindParam(':email', $userEmail);
                $stmt->execute();
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                returnJson(['success' => true, 'bookings' => $bookings]);
            } catch (Exception $e) {
                logDebug("Error getting bookings: " . $e->getMessage());
                returnJson(['success' => false, 'message' => 'Error retrieving bookings: ' . $e->getMessage()]);
            }
            break;

        case 'cancel_booking':
            if (!isset($_SESSION['email'])) {
                returnJson(['success' => false, 'message' => 'Not logged in']);
                break;
            }
            
            $bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
            $userEmail = $_SESSION['email'];
            
            if (!$bookingId) {
                returnJson(['success' => false, 'message' => 'Invalid booking ID']);
                break;
            }
            
            try {
                // First check if the booking belongs to this user
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_id = :id AND user_email = :email");
                $checkStmt->bindParam(':id', $bookingId);
                $checkStmt->bindParam(':email', $userEmail);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn() == 0) {
                    returnJson(['success' => false, 'message' => 'Booking not found or not authorized to cancel']);
                    break;
                }
                
                // Update booking status to cancelled
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = :id");
                $stmt->bindParam(':id', $bookingId);
                $stmt->execute();
                
                returnJson(['success' => true, 'message' => 'Booking cancelled successfully']);
            } catch (Exception $e) {
                logDebug("Error cancelling booking: " . $e->getMessage());
                returnJson(['success' => false, 'message' => 'Error cancelling booking: ' . $e->getMessage()]);
            }
            break;

        case 'submit_emergency_contact':
            if (!isset($_SESSION['email'])) {
                returnJson(['success' => false, 'message' => 'Not logged in']);
                break;
            }
            
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
            $relationship = isset($_POST['relationship']) ? trim($_POST['relationship']) : '';
            $userEmail = $_SESSION['email'];
            
            if (empty($name) || empty($phone)) {
                returnJson(['success' => false, 'message' => 'Name and phone are required']);
                break;
            }
            
            try {
                // Check if emergency_contacts table exists
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'emergency_contacts'");
                if ($tableCheck->rowCount() == 0) {
                    // Create the table if it doesn't exist
                    $pdo->exec("CREATE TABLE emergency_contacts (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_email VARCHAR(255) NOT NULL,
                        name VARCHAR(255) NOT NULL,
                        phone VARCHAR(50) NOT NULL,
                        relationship VARCHAR(100),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE
                    )");
                }
                
                // Check if user already has emergency contacts
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM emergency_contacts WHERE user_email = :email");
                $checkStmt->bindParam(':email', $userEmail);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn() > 0) {
                    // Update existing emergency contact
                    $stmt = $pdo->prepare("
                        UPDATE emergency_contacts 
                        SET name = :name, phone = :phone, relationship = :relationship 
                        WHERE user_email = :email
                    ");
                } else {
                    // Insert new emergency contact
                    $stmt = $pdo->prepare("
                        INSERT INTO emergency_contacts (user_email, name, phone, relationship)
                        VALUES (:email, :name, :phone, :relationship)
                    ");
                }
                
                $stmt->bindParam(':email', $userEmail);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':relationship', $relationship);
                $stmt->execute();
                
                returnJson(['success' => true, 'message' => 'Emergency contact saved successfully']);
            } catch (Exception $e) {
                logDebug("Error saving emergency contact: " . $e->getMessage());
                returnJson(['success' => false, 'message' => 'Error saving emergency contact: ' . $e->getMessage()]);
            }
            break;

        case 'get_emergency_contact':
            if (!isset($_SESSION['email'])) {
                returnJson(['success' => false, 'message' => 'Not logged in']);
                break;
            }
            
            $userEmail = $_SESSION['email'];
            
            try {
                // Check if emergency_contacts table exists
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'emergency_contacts'");
                if ($tableCheck->rowCount() == 0) {
                    returnJson(['success' => false, 'message' => 'No emergency contacts found']);
                    break;
                }
                
                // Get emergency contact for user
                $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE user_email = :email");
                $stmt->bindParam(':email', $userEmail);
                $stmt->execute();
                $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($contact) {
                    returnJson(['success' => true, 'contact' => $contact]);
                } else {
                    returnJson(['success' => false, 'message' => 'No emergency contact found']);
                }
            } catch (Exception $e) {
                logDebug("Error retrieving emergency contact: " . $e->getMessage());
                returnJson(['success' => false, 'message' => 'Error retrieving emergency contact: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// Get current user ID based on email
function getUserId($pdo, $email) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

// Main request handler with explicit error catching
function handleRequest() {
    // Log the request details
    logDebug("Received request: " . json_encode($_POST));
    
    // Get database connection
    $pdo = connectDB();
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    // Get action from request
    $action = $_POST['action'] ?? '';
    logDebug("Processing action: " . $action);
    
    try {
        // Validate that the action function exists 
        $functionName = null;
        switch ($action) {
            case 'get_schedules':
                $functionName = 'getSchedules';
                break;
            case 'book_service':
                $functionName = 'bookAppointment';
                break;
            case 'get_bookings':
                $functionName = 'getBookings';
                break;
            case 'update_booking':
                $functionName = 'updateBooking';
                break;
            case 'update_availability':
                $functionName = 'updateAvailability';
                break;
            case 'get_emergency_contacts':
                $functionName = 'getEmergencyContacts';
                break;
            case 'add_emergency_contact':
                $functionName = 'addEmergencyContact';
                break;
            case 'delete_emergency_contact':
                $functionName = 'deleteEmergencyContact';
                break;
            case 'send_emergency_sms':
                $functionName = 'sendEmergencySMS';
                break;
            case 'get_available_staff':
                $functionName = 'getAvailableStaff';
                break;
            case 'alter_staff_availability':
                $functionName = 'alterStaffAvailabilityTable';
                break;
            case 'create_test_data':
                $functionName = 'createTestAvailabilityData';
                break;
            case 'get_all_schedules':
                $functionName = 'getAllSchedules';
                break;
            case 'book_schedule':
                $functionName = 'bookSchedule';
                break;
            case 'get_my_bookings':
                $functionName = 'getMyBookings';
                break;
            case 'cancel_booking':
                $functionName = 'cancelBooking';
                break;
            case 'submit_emergency_contact':
                $functionName = 'submitEmergencyContact';
                break;
            case 'get_emergency_contact':
                $functionName = 'getEmergencyContact';
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
                return;
        }
        
        // Check if function exists
        if (!function_exists($functionName)) {
            logDebug("Function not found: $functionName");
            echo json_encode(['success' => false, 'message' => 'Action handler not implemented']);
            return;
        }
        
        // Call the function
        logDebug("Calling function: $functionName");
        call_user_func($functionName, $pdo);
        
    } catch (PDOException $e) {
        logDebug("PDO ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        $errorCode = $e->getCode();
        $errorInfo = $pdo->errorInfo();
        echo json_encode([
            'success' => false, 
            'message' => "Database error: " . $e->getMessage(),
            'error_code' => $errorCode,
            'error_info' => $errorInfo
        ]);
    } catch (Exception $e) {
        logDebug("GENERAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        echo json_encode([
            'success' => false, 
            'message' => "Server error: " . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

// Execute the main request handler
logDebug("Starting request handler");
handleRequest();
logDebug("Request handler completed");
?>

<script>
// Improved version of loadAvailableSlots function to fix the error
function loadAvailableSlots(serviceType = '') {
    console.log("Loading available slots for service type: " + serviceType);
    
    // Check for the container and create it if it doesn't exist
    let container = document.getElementById('scheduleSlotsContainer');
    if (!container) {
        console.log("Container not found, creating one...");
        // Find a suitable parent element - using an element we know exists from your snippets
        const parentElement = document.querySelector('.content-area') || 
                             document.querySelector('main') || 
                             document.querySelector('body');
        
        // Create the container
        container = document.createElement('div');
        container.id = 'scheduleSlotsContainer';
        container.className = 'service-slots';
        
        // Add a heading before the container
        const heading = document.createElement('h2');
        heading.textContent = 'Available Service Slots';
        heading.className = 'section-title';
        
        // Insert the elements
        parentElement.appendChild(heading);
        parentElement.appendChild(container);
    }
    
    // Show loading indicator
    container.innerHTML = '<div class="loading-indicator"><i class="fas fa-spinner fa-spin"></i> Loading available time slots...</div>';
    
    // Prepare data for AJAX
    const formData = new FormData();
    formData.append('action', 'get_schedules');
    if (serviceType) {
        formData.append('service_type', serviceType);
    }
    
    // Make AJAX request
    fetch('service.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log("Received schedules data:", data);
        
        if (!data.success) {
            container.innerHTML = `<div class="alert alert-danger">Error: ${data.message || 'Failed to load schedules'}</div>`;
            return;
        }
        
        // Check if we have any schedules
        if (!data.schedules || data.schedules.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No available service slots found. Please check back later.</div>';
            return;
        }
        
        // Build HTML for schedules
        let html = '';
        
        data.schedules.forEach(schedule => {
            // Skip schedules with no providers
            if (!schedule.providers || schedule.providers.length === 0 || schedule.id <= 0) {
                return;
            }
            
            html += `
            <div class="time-slot available" data-schedule-id="${schedule.id}">
                <div class="slot-time">
                    <div class="day">${schedule.day}</div>
                    <div class="time">${schedule.start_time} - ${schedule.end_time}</div>
                </div>
                <div class="available-providers">
                    <h4>Available Staff:</h4>
                    <ul>`;
            
            schedule.providers.forEach(provider => {
                // Determine provider type for styling
                let providerTypeClass = '';
                let serviceTypeLabel = '';
                
                switch (Number(provider.role_id)) {
                    case 2:
                        providerTypeClass = 'advisor';
                        serviceTypeLabel = 'Academic Advising';
                        break;
                    case 3:
                        providerTypeClass = 'counselor';
                        serviceTypeLabel = 'Personal Counseling';
                        break;
                    case 4:
                        providerTypeClass = 'supporter';
                        serviceTypeLabel = 'Technical Support';
                        break;
                    default:
                        providerTypeClass = '';
                        serviceTypeLabel = 'Consultation';
                }
                
                html += `
                    <li class="provider-item ${providerTypeClass}">
                        <strong>${provider.provider_name || provider.name || 'Staff Member'}</strong>
                        <span class="provider-role">${serviceTypeLabel}</span>
                        <button class="book-button" 
                            onclick="bookAppointment(${schedule.id}, '${provider.provider_email || provider.email || ''}', '${provider.provider_name || provider.name || 'Staff Member'}', '${schedule.day}', '${schedule.start_time} - ${schedule.end_time}', '${serviceTypeLabel}')">
                            Book Now
                        </button>
                    </li>`;
            });
            
            html += `
                    </ul>
                </div>
            </div>`;
        });
        
        // Update the container content
        container.innerHTML = html;
        
    })
    .catch(error => {
        console.error('Error fetching schedules:', error);
        container.innerHTML = `<div class="alert alert-danger">Failed to load available slots: ${error.message}</div>`;
    });
}

// Initialize when the page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM loaded - initializing service booking system");
    
    // Try to find the service type selector
    const serviceTypeSelector = document.getElementById('serviceTypeSelector');
    if (serviceTypeSelector) {
        serviceTypeSelector.addEventListener('change', function() {
            loadAvailableSlots(this.value);
        });
        console.log("Service type selector found and event listener attached");
    } else {
        console.log("Service type selector not found, using default view");
    }
    
    // Load all available slots initially
    loadAvailableSlots();
});
</script>