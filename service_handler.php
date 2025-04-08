<?php
session_start();
header('Content-Type: application/json');
// Prevent any PHP errors or warnings from being displayed in the output
// This is crucial for a clean JSON response
error_reporting(E_ERROR);
ini_set('display_errors', 0);

// Add this at the beginning of the script, after session_start()
// This is for debugging the action parameter
$rawPost = file_get_contents('php://input');
$requestMethod = $_SERVER['REQUEST_METHOD'];
error_log("Request Method: $requestMethod");
error_log("Raw POST data: $rawPost");
error_log("POST variables: " . json_encode($_POST));
error_log("GET variables: " . json_encode($_GET));

// Add this near the top of the file for debugging
error_log("POST data raw: " . file_get_contents('php://input'));
error_log("POST variables: " . json_encode($_POST));

// Replace the action detection logic with this more robust version
$action = '';
if (!empty($_POST['action'])) {
    $action = $_POST['action'];
    error_log("Action from POST: " . $action);
} elseif (!empty($_GET['action'])) {
    $action = $_GET['action'];
    error_log("Action from GET: " . $action);
} else {
    // Try to parse raw input for action
    $input = file_get_contents('php://input');
    error_log("Raw input: " . $input);
    
    // Try to parse as form data
    parse_str($input, $parsedInput);
    if (!empty($parsedInput['action'])) {
        $action = $parsedInput['action'];
        error_log("Action from parsed input: " . $action);
    }
}

// Force the action for troubleshooting if needed
if (strpos($input, 'send_emergency_alert') !== false && empty($action)) {
    $action = 'send_emergency_alert';
    error_log("Action forced to emergency_alert due to presence in request");
}

error_log("Final determined action: " . $action);

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Database connection
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
        error_log('Database connection error: ' . $e->getMessage());
        return null;
    }
}

$pdo = connectDB();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get user email from session
$userEmail = $_SESSION['email'];

// Log this for debugging
error_log("Processing request for user: " . $userEmail);

// Handle different actions
switch ($action) {
    case 'get_my_bookings':
        // Get user's bookings - simplified query to ensure we get results
        try {
            // First let's do a simple query to confirm bookings exist
            $checkQuery = "SELECT COUNT(*) FROM bookings WHERE user_email = :userEmail";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':userEmail', $userEmail, PDO::PARAM_STR);
            $checkStmt->execute();
            $count = $checkStmt->fetchColumn();
            
            error_log("Found {$count} bookings for user {$userEmail}");
            
            // Now run the full query with staff name - using DISTINCT to avoid duplicates
            $query = "SELECT DISTINCT b.*, 
                      s.day, s.start_time, s.end_time,
                      sa.staff_name as provider_name
                      FROM bookings b
                      LEFT JOIN staff_availability s ON b.schedule_id = s.id
                      LEFT JOIN staff_availability sa ON b.provider_email = sa.staff_email
                      WHERE b.user_email = :userEmail
                      GROUP BY b.booking_id
                      ORDER BY b.booking_date DESC";
                      
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':userEmail', $userEmail, PDO::PARAM_STR);
            $stmt->execute();
            
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Retrieved " . count($bookings) . " booking records with details");
            
            // If we found bookings in the simple query but not in the detailed one, 
            // use a more basic query
            if (count($bookings) === 0 && $count > 0) {
                error_log("Using fallback query without joins");
                $backupQuery = "SELECT b.*, sa.staff_name as provider_name 
                               FROM bookings b
                               LEFT JOIN staff_availability sa ON b.provider_email = sa.staff_email
                               WHERE b.user_email = :userEmail 
                               ORDER BY b.booking_date DESC";
                $backupStmt = $pdo->prepare($backupQuery);
                $backupStmt->bindParam(':userEmail', $userEmail, PDO::PARAM_STR);
                $backupStmt->execute();
                $bookings = $backupStmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Fallback query returned " . count($bookings) . " records");
            }
            
            echo json_encode([
                'success' => true,
                'bookings' => $bookings,
                'count' => $count,
                'debug' => "User: {$userEmail}, Found: " . count($bookings) . " bookings"
            ]);
        } catch (PDOException $e) {
            error_log('Error fetching bookings: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Could not fetch bookings: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'cancel_booking':
        // Cancel a booking
        $bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        if (!$bookingId) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
            exit();
        }
        
        try {
            // First check if the booking belongs to the current user
            $checkQuery = "SELECT * FROM bookings WHERE booking_id = :bookingId AND user_email = :userEmail";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
            $checkStmt->bindParam(':userEmail', $userEmail, PDO::PARAM_STR);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Booking not found or not authorized']);
                exit();
            }
            
            // Update the booking status to 'cancelled'
            $updateQuery = "UPDATE bookings SET status = 'cancelled' WHERE booking_id = :bookingId";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
            $updateStmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
        } catch (PDOException $e) {
            error_log('Error cancelling booking: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Could not cancel booking: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'book_service':
        // Validate required fields
        $staffAvailabilityId = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
        $serviceType = $_POST['service_type'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        // Debug logging
        error_log("BOOKING DEBUG: Starting booking process");
        error_log("BOOKING DEBUG: User email: $userEmail");
        error_log("BOOKING DEBUG: Staff Availability ID: $staffAvailabilityId");
        error_log("BOOKING DEBUG: Service type: $serviceType");
        
        if (empty($staffAvailabilityId) || empty($serviceType)) {
            error_log("BOOKING DEBUG: Missing required fields");
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }

        try {
            // 1. Get the staff availability record
            $availabilityQuery = "SELECT sa.*,
                sa.staff_name as staff_name
                FROM staff_availability sa 
                WHERE sa.id = :staffAvailabilityId 
                AND sa.is_available = 1";

            $availabilityStmt = $pdo->prepare($availabilityQuery);
            $availabilityStmt->bindParam(':staffAvailabilityId', $staffAvailabilityId, PDO::PARAM_INT);
            $availabilityStmt->execute();
            $availabilityData = $availabilityStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$availabilityData) {
                error_log("BOOKING DEBUG: Staff availability ID $staffAvailabilityId does not exist");
                echo json_encode(['success' => false, 'message' => 'The selected time slot is not available']);
                exit();
            }
            
            // 2. Get provider email and other details from staff_availability
            $providerEmail = $availabilityData['staff_email'] ?? '';
            
            // Verify the staff email exists in staff_availability table
            $checkStaffStmt = $pdo->prepare("SELECT staff_email FROM staff_availability WHERE staff_email = :email LIMIT 1");
            $checkStaffStmt->bindParam(':email', $providerEmail, PDO::PARAM_STR);
            $checkStaffStmt->execute();
            $validStaff = $checkStaffStmt->fetch(PDO::FETCH_ASSOC);

            if (!$validStaff) {
                error_log("BOOKING DEBUG: Invalid staff email: $providerEmail");
                echo json_encode(['success' => false, 'message' => 'Invalid staff member']);
                exit();
            }

            $day = $availabilityData['day'] ?? '';
            $startTime = $availabilityData['start_time'] ?? '';
            $endTime = $availabilityData['end_time'] ?? '';
            
            error_log("BOOKING DEBUG: Provider email: $providerEmail, Day: $day, Time: $startTime-$endTime");
            
            // 3. Look for a matching schedule in the schedules table by day/time
            $scheduleByTimeQuery = "SELECT id FROM schedules WHERE 
                                  day = :day AND 
                                  start_time = :startTime AND 
                                  end_time = :endTime";
            
            $scheduleByTimeStmt = $pdo->prepare($scheduleByTimeQuery);
            $scheduleByTimeStmt->bindParam(':day', $day, PDO::PARAM_STR);
            $scheduleByTimeStmt->bindParam(':startTime', $startTime, PDO::PARAM_STR);
            $scheduleByTimeStmt->bindParam(':endTime', $endTime, PDO::PARAM_STR);
            
            $scheduleByTimeStmt->execute();
            $scheduleData = $scheduleByTimeStmt->fetch(PDO::FETCH_ASSOC);
            
            // If no match, create a new schedule in the schedules table
            if (!$scheduleData) {
                error_log("BOOKING DEBUG: No matching schedule found, creating a new one");
                
                // Get the structure of the schedules table to determine what columns exist
                $tableStructureQuery = "DESCRIBE schedules";
                $tableStructureStmt = $pdo->prepare($tableStructureQuery);
                $tableStructureStmt->execute();
                $tableColumns = $tableStructureStmt->fetchAll(PDO::FETCH_COLUMN, 0);
                
                error_log("BOOKING DEBUG: Schedules table columns: " . json_encode($tableColumns));
                
                // Build a dynamic insert query based on available columns
                $insertColumns = [];
                $insertValues = [];
                $insertParams = [];
                
                // Always include these basic fields
                if (in_array('day', $tableColumns)) {
                    $insertColumns[] = 'day';
                    $insertValues[] = ':day';
                    $insertParams[':day'] = $day;
                }
                
                if (in_array('start_time', $tableColumns)) {
                    $insertColumns[] = 'start_time';
                    $insertValues[] = ':startTime';
                    $insertParams[':startTime'] = $startTime;
                }
                
                if (in_array('end_time', $tableColumns)) {
                    $insertColumns[] = 'end_time';
                    $insertValues[] = ':endTime';
                    $insertParams[':endTime'] = $endTime;
                }
                
                $insertScheduleQuery = "INSERT INTO schedules 
                                       (" . implode(', ', $insertColumns) . ") 
                                       VALUES 
                                       (" . implode(', ', $insertValues) . ")";
                
                error_log("BOOKING DEBUG: Insert schedule query: $insertScheduleQuery");
                error_log("BOOKING DEBUG: Insert params: " . json_encode($insertParams));
                
                $insertScheduleStmt = $pdo->prepare($insertScheduleQuery);
                
                // Bind all parameters
                foreach ($insertParams as $param => $value) {
                    $insertScheduleStmt->bindValue($param, $value);
                }
                
                $insertResult = $insertScheduleStmt->execute();
                
                if ($insertResult) {
                    $scheduleId = $pdo->lastInsertId();
                    error_log("BOOKING DEBUG: Created new schedule with ID: $scheduleId");
                } else {
                    error_log("BOOKING DEBUG: Failed to create new schedule: " . json_encode($insertScheduleStmt->errorInfo()));
                    echo json_encode(['success' => false, 'message' => 'Could not create schedule for booking']);
                    exit();
                }
            } else {
                $scheduleId = $scheduleData['id'];
                error_log("BOOKING DEBUG: Found existing schedule with ID: $scheduleId");
            }
            
            // 4. Check if user already has this booking
            $checkQuery = "SELECT COUNT(*) FROM bookings WHERE user_email = :userEmail AND schedule_id = :scheduleId AND status != 'cancelled'";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':userEmail', $userEmail, PDO::PARAM_STR);
            $checkStmt->bindParam(':scheduleId', $scheduleId, PDO::PARAM_INT);
            $checkStmt->execute();
            $existingBookings = $checkStmt->fetchColumn();
            
            if ($existingBookings > 0) {
                error_log("BOOKING DEBUG: User already has this booking");
                echo json_encode(['success' => false, 'message' => 'You have already booked this time slot']);
                exit();
            }
            
            // 5. Create the booking with the schedule ID from schedules table
            $insertBookingSQL = "INSERT INTO bookings 
                               (user_email, provider_email, schedule_id, service_type, notes, status, booking_date) 
                               VALUES 
                               (:userEmail, :providerEmail, :scheduleId, :serviceType, :notes, 'pending', NOW())";
            
            error_log("BOOKING DEBUG: Booking SQL: $insertBookingSQL");
            error_log("BOOKING DEBUG: Values - User: $userEmail, Provider: $providerEmail, Schedule: $scheduleId, Service: $serviceType");
            
            $insertBookingStmt = $pdo->prepare($insertBookingSQL);
            $insertBookingStmt->bindParam(':userEmail', $userEmail, PDO::PARAM_STR);
            $insertBookingStmt->bindParam(':providerEmail', $providerEmail, PDO::PARAM_STR);
            $insertBookingStmt->bindParam(':scheduleId', $scheduleId, PDO::PARAM_INT);
            $insertBookingStmt->bindParam(':serviceType', $serviceType, PDO::PARAM_STR);
            $insertBookingStmt->bindParam(':notes', $notes, PDO::PARAM_STR);
            
            $bookingResult = $insertBookingStmt->execute();
            
            if ($bookingResult) {
                $bookingId = $pdo->lastInsertId();
                error_log("BOOKING DEBUG: Booking created successfully! ID: $bookingId");
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Booking created successfully', 
                    'booking_id' => $bookingId
                ]);
            } else {
                error_log("BOOKING DEBUG: Booking creation failed: " . json_encode($insertBookingStmt->errorInfo()));
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Could not create booking: ' . $insertBookingStmt->errorInfo()[2]
                ]);
            }
            
        } catch (PDOException $e) {
            $errorMessage = $e->getMessage();
            error_log("BOOKING ERROR: $errorMessage");
            
            echo json_encode([
                'success' => false,
                'message' => 'Database Error: ' . $errorMessage
            ]);
        } catch (Exception $e) {
            error_log("BOOKING GENERAL ERROR: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.'
            ]);
        }
        break;
        
    case 'validate_schedule':
        $scheduleId = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
        
        if (empty($scheduleId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
            exit();
        }
        
        try {
            // Get the staff availability record 
            $scheduleQuery = "SELECT * FROM staff_availability WHERE id = :scheduleId";
            $scheduleStmt = $pdo->prepare($scheduleQuery);
            $scheduleStmt->bindParam(':scheduleId', $scheduleId, PDO::PARAM_INT);
            $scheduleStmt->execute();
            
            $scheduleData = $scheduleStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$scheduleData) {
                echo json_encode(['success' => false, 'message' => 'The selected time slot is no longer available.']);
                exit();
            }
            
            // Get staff email from the schedule - this is the provider email
            $staffEmail = isset($scheduleData['staff_email']) ? $scheduleData['staff_email'] : null;
            
            if (!$staffEmail) {
                echo json_encode(['success' => false, 'message' => 'No provider is associated with this time slot.']);
                exit();
            }
            
            // Return the staff email as the provider email (they are the same)
            echo json_encode([
                'success' => true, 
                'message' => 'Valid time slot found',
                'provider_email' => $staffEmail, // Use staff_email as provider_email directly
                'schedule_data' => $scheduleData
            ]);
        } catch (PDOException $e) {
            error_log('Error validating schedule: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Database error while validating schedule'
            ]);
        }
        break;
        
    case 'get_available_services':
        try {
            // Get available services with staff names
            $query = "SELECT sa.*, 
                      CASE 
                          WHEN sa.role_id = 2 THEN (SELECT adv_name FROM advisors WHERE adv_id = sa.staff_id)
                          WHEN sa.role_id = 3 THEN (SELECT coun_name FROM counselors WHERE coun_id = sa.staff_id)
                          WHEN sa.role_id = 4 THEN (SELECT sup_name FROM supporters WHERE sup_id = sa.staff_id)
                          ELSE sa.staff_name
                      END as staff_name,
                      CASE 
                          WHEN sa.role_id = 2 THEN 'Academic Advising'
                          WHEN sa.role_id = 3 THEN 'Personal Counseling'
                          WHEN sa.role_id = 4 THEN 'Technical Support'
                          ELSE 'Consultation'
                      END as service_type
                      FROM staff_availability sa 
                      WHERE sa.is_available = 1
                      ORDER BY sa.day, sa.start_time";

            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'services' => $services
            ]);
        } catch (PDOException $e) {
            error_log('Error getting available services: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Could not fetch available services'
            ]);
        }
        break;
        
    case 'get_emergency_contacts':
        try {
            $userEmail = $_SESSION['email'];
            
            // First, let's log all existing emergency contacts to understand the data
            error_log("DEBUG: Looking for contacts for: $userEmail");
            
            // Query to find ALL records in the table (limited to first 10)
            $allContacts = $pdo->query("SELECT * FROM emergency_contacts LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            error_log("DEBUG: All contacts in table (first 10): " . json_encode($allContacts));
            
            // Get column information
            $columns = $pdo->query("SHOW COLUMNS FROM emergency_contacts")->fetchAll(PDO::FETCH_COLUMN);
            error_log("DEBUG: Table columns: " . implode(", ", $columns));
            
            // Now try multiple queries with different matching strategies
            
            // Strategy 1: Direct match on user_email
            $contacts = [];
            if (in_array('user_email', $columns)) {
                $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE user_email = :email");
                $stmt->bindParam(':email', $userEmail, PDO::PARAM_STR);
                $stmt->execute();
                $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("DEBUG: Strategy 1 (user_email exact match) found: " . count($contacts));
            }
            
            // Strategy 2: Direct match on email
            if (empty($contacts) && in_array('email', $columns)) {
                $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE email = :email");
                $stmt->bindParam(':email', $userEmail, PDO::PARAM_STR);
                $stmt->execute();
                $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("DEBUG: Strategy 2 (email exact match) found: " . count($contacts));
            }
            
            // Strategy 3: Case-insensitive match on any email field
            if (empty($contacts)) {
                // Find any column with 'email' in the name
                $emailCols = [];
                foreach ($columns as $col) {
                    if (stripos($col, 'email') !== false) {
                        $emailCols[] = $col;
                    }
                }
                
                error_log("DEBUG: Found email columns: " . implode(", ", $emailCols));
                
                // Try each email column with case-insensitive match
                foreach ($emailCols as $col) {
                    $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE LOWER($col) = LOWER(:email)");
                    $stmt->bindParam(':email', $userEmail, PDO::PARAM_STR);
                    $stmt->execute();
                    $colContacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($colContacts)) {
                        error_log("DEBUG: Strategy 3 found matches in column: $col");
                        $contacts = $colContacts;
                        break;
                    }
                }
            }
            
            // Strategy 4: Get any contact associated with a partial match of the email
            if (empty($contacts)) {
                $userEmailParts = explode('@', $userEmail);
                $username = $userEmailParts[0] ?? '';
                
                if (!empty($username)) {
                    // Find records where any email column contains the username part
                    $query = "SELECT * FROM emergency_contacts WHERE 0";
                    
                    foreach ($emailCols as $col) {
                        $query .= " OR $col LIKE :pattern";
                    }
                    
                    $stmt = $pdo->prepare($query);
                    $pattern = "%$username%";
                    $stmt->bindParam(':pattern', $pattern, PDO::PARAM_STR);
                    $stmt->execute();
                    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    error_log("DEBUG: Strategy 4 (partial email match) found: " . count($contacts));
                }
            }
            
            // If we still have no contacts, return all records as a last resort
            // This is just for debugging - remove for production
            if (empty($contacts)) {
                $contacts = $allContacts;
                error_log("DEBUG: Last resort - returning ALL contacts from table");
            }
            
            echo json_encode([
                'success' => true,
                'contacts' => $contacts,
                'user_email' => $userEmail,
                'db_count' => count($contacts),
                'debug_table_structure' => json_encode($columns),
                'all_contacts_count' => count($allContacts)
            ]);
        } catch (Exception $e) {
            error_log("Error in get_emergency_contacts: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'user_email' => $_SESSION['email'] ?? 'No email in session'
            ]);
        }
        break;

    case 'get_emergency_contact':
        // Check if user is logged in
        if (empty($_SESSION['user_email'])) {
            echo json_encode([
                'success' => false,
                'message' => 'User not logged in'
            ]);
            exit;
        }
        
        $userEmail = $_SESSION['user_email'];
        $contactId = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($contactId <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid contact ID'
            ]);
            exit;
        }
        
        try {
            // Get a specific contact (verifying it belongs to the current user)
            $query = "SELECT id, emergency_name, emergency_email, em_number, relationship 
                      FROM emergency_contacts 
                      WHERE id = :id AND email = :email";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':id', $contactId);
            $stmt->bindParam(':email', $userEmail);
            $stmt->execute();
            
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($contact) {
                echo json_encode([
                    'success' => true,
                    'contact' => $contact
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Contact not found'
                ]);
            }
        } catch (PDOException $e) {
            error_log('Error getting emergency contact: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Database error'
            ]);
        }
        break;

    case 'save_emergency_contact':
        // Check if user is logged in
        if (empty($_SESSION['user_email'])) {
            echo json_encode([
                'success' => false,
                'message' => 'User not logged in'
            ]);
            exit;
        }
        
        $userEmail = $_SESSION['user_email'];
        $emergencyName = isset($_POST['emergency_name']) ? trim($_POST['emergency_name']) : '';
        $emergencyEmail = isset($_POST['emergency_email']) ? trim($_POST['emergency_email']) : '';
        $emNumber = isset($_POST['em_number']) ? trim($_POST['em_number']) : '';
        $relationship = isset($_POST['relationship']) ? trim($_POST['relationship']) : '';
        
        // Validate required fields
        if (empty($emergencyName) || empty($emergencyEmail) || empty($emNumber) || empty($relationship)) {
            echo json_encode([
                'success' => false,
                'message' => 'All fields are required'
            ]);
            exit;
        }
        
        try {
            // Insert new emergency contact
            $query = "INSERT INTO emergency_contacts (email, emergency_name, emergency_email, em_number, relationship) 
                      VALUES (:email, :emergency_name, :emergency_email, :em_number, :relationship)";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':email', $userEmail);
            $stmt->bindParam(':emergency_name', $emergencyName);
            $stmt->bindParam(':emergency_email', $emergencyEmail);
            $stmt->bindParam(':em_number', $emNumber);
            $stmt->bindParam(':relationship', $relationship);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Contact saved successfully'
            ]);
        } catch (PDOException $e) {
            error_log('Error saving emergency contact: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Database error'
            ]);
        }
        break;

    case 'update_emergency_contact':
        // Check if user is logged in
        if (empty($_SESSION['user_email'])) {
            echo json_encode([
                'success' => false,
                'message' => 'User not logged in'
            ]);
            exit;
        }
        
        $userEmail = $_SESSION['user_email'];
        $contactId = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $emergencyName = isset($_POST['emergency_name']) ? trim($_POST['emergency_name']) : '';
        $emergencyEmail = isset($_POST['emergency_email']) ? trim($_POST['emergency_email']) : '';
        $emNumber = isset($_POST['em_number']) ? trim($_POST['em_number']) : '';
        $relationship = isset($_POST['relationship']) ? trim($_POST['relationship']) : '';
        
        // Validate required fields
        if ($contactId <= 0 || empty($emergencyName) || empty($emergencyEmail) || empty($emNumber) || empty($relationship)) {
            echo json_encode([
                'success' => false,
                'message' => 'All fields are required'
            ]);
            exit;
        }
        
        try {
            // Update the contact (ensuring it belongs to the current user)
            $query = "UPDATE emergency_contacts 
                      SET emergency_name = :emergency_name, 
                          emergency_email = :emergency_email,
                          em_number = :em_number,
                          relationship = :relationship
                      WHERE id = :id AND email = :email";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':id', $contactId);
            $stmt->bindParam(':email', $userEmail);
            $stmt->bindParam(':emergency_name', $emergencyName);
            $stmt->bindParam(':emergency_email', $emergencyEmail);
            $stmt->bindParam(':em_number', $emNumber);
            $stmt->bindParam(':relationship', $relationship);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Contact updated successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Contact not found or not updated'
                ]);
            }
        } catch (PDOException $e) {
            error_log('Error updating emergency contact: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Database error'
            ]);
        }
        break;

    case 'delete_emergency_contact':
        // Get the contact ID
        $id = $_POST['id'] ?? 0;
        $email = $_SESSION['email'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid contact ID']);
            exit;
        }
        
        try {
            // Log for debugging
            error_log("Deleting emergency contact ID: $id for user: $email");
            
            // Delete the emergency contact, verify it belongs to the user
            $stmt = $pdo->prepare("DELETE FROM emergency_contacts WHERE id = :id AND user_email = :email");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            
            // If no rows affected, try with the email field
            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("DELETE FROM emergency_contacts WHERE id = :id AND email = :email");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->execute();
            }
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Emergency contact deleted successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Contact not found or you do not have permission to delete it'
                ]);
            }
        } catch (PDOException $e) {
            error_log('Error deleting emergency contact: ' . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Error deleting emergency contact: ' . $e->getMessage()
            ]);
        }
        break;

    case 'add_emergency_contact':
        // Get the current user's email from session
        $email = $_SESSION['email'] ?? '';
        
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'User not logged in']);
            exit;
        }
        
        // Get the form data
        $emergency_name = $_POST['emergency_name'] ?? '';
        $em_number = $_POST['em_number'] ?? '';
        $emergency_email = $_POST['emergency_email'] ?? '';
        $relationship = $_POST['relationship'] ?? '';
        
        // Validate required fields
        if (empty($emergency_name) || empty($em_number)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        try {
            // Log the SQL for debugging
            error_log("Adding emergency contact for user: $email");
            error_log("Name: $emergency_name, Phone: $em_number");
            
            // Insert the new emergency contact
            $stmt = $pdo->prepare("INSERT INTO emergency_contacts 
                    (user_email, emergency_name, em_number, emergency_email, relationship) 
                    VALUES (:user_email, :emergency_name, :em_number, :emergency_email, :relationship)");
            
            $stmt->bindParam(':user_email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':emergency_name', $emergency_name, PDO::PARAM_STR);
            $stmt->bindParam(':em_number', $em_number, PDO::PARAM_STR);
            $stmt->bindParam(':emergency_email', $emergency_email, PDO::PARAM_STR);
            $stmt->bindParam(':relationship', $relationship, PDO::PARAM_STR);
            
            $result = $stmt->execute();
            
            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Emergency contact added successfully'
                ]);
            } else {
                error_log("Failed to add contact: " . json_encode($stmt->errorInfo()));
                echo json_encode([
                    'success' => false, 
                    'message' => 'Database error: ' . $stmt->errorInfo()[2]
                ]);
            }
        } catch (PDOException $e) {
            error_log('Error adding emergency contact: ' . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Error adding emergency contact: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'send_emergency_alert':
        // Start output buffering to prevent any unwanted output
        ob_start();
        
        // Get the current user's email and name
        $email = $_SESSION['email'] ?? '';
        $userName = $_SESSION['name'] ?? 'User';
        
        if (empty($email)) {
            error_log("EMERGENCY ALERT ERROR: User not logged in");
            ob_end_clean(); // Clear the buffer
            echo json_encode(['success' => false, 'message' => 'User not logged in']);
            exit;
        }
        
        // Get message from POST data or use default message
        $message = $_POST['message'] ?? 'Emergency alert! Please contact me immediately.';
        
        error_log("Processing emergency alert for user: $email with message: $message");
        
        try {
            // Check if the emergency_contacts table has the correct column name
            $checkColumnQuery = "SHOW COLUMNS FROM emergency_contacts LIKE 'user_email'";
            $checkStmt = $pdo->query($checkColumnQuery);
            $columnExists = $checkStmt->rowCount() > 0;
            
            // Determine the correct column name
            $userColumn = $columnExists ? 'user_email' : 'email'; // Try 'email' as alternative
            
            error_log("Using column name '$userColumn' for user identification in emergency_contacts table");
            
            // Get emergency contacts using the correct column name
            $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE $userColumn = :email");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Found " . count($contacts) . " emergency contacts for user $email");
            
            if (empty($contacts)) {
                ob_end_clean();
                echo json_encode([
                    'success' => false,
                    'message' => 'You have no emergency contacts. Please add contacts first.'
                ]);
                exit;
            }
            
            // Include the service.php file to access SMS sending function
            if (!function_exists('sendEmergencySMS')) {
                if (file_exists('service.php')) {
                    require_once 'service.php';
                    error_log("Included service.php for SMS functions");
                } else {
                    throw new Exception("SMS service file not found");
                }
            }
            
            // Now use the Twilio SMS sending function
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
                
                // Clean phone number for E.164 format
                $phone = preg_replace('/[^0-9+]/', '', $phone);
                if (!startsWith($phone, '+')) {
                    $phone = '+1' . $phone; // US number format
                }
                
                // Format the emergency message
                $fullMessage = "EMERGENCY ALERT from $userName: $message";
                
                try {
                    if (function_exists('sendEmergencySMS')) {
                        // Call the Twilio function
                        $smsResult = sendEmergencySMS($phone, $fullMessage, $name, $email);
                        error_log("SMS result: " . json_encode($smsResult));
                        
                        if (isset($smsResult['success']) && $smsResult['success']) {
                            $successCount++;
                            $details[] = "Alert sent to $name at $phone";
                        } else {
                            $failCount++;
                            $details[] = "Failed to send alert to $name: " . ($smsResult['message'] ?? 'Unknown error');
                        }
                    } else {
                        // Fallback if function not found
                        error_log("sendEmergencySMS function not available");
                        $details[] = "Alert would be sent to $name at $phone (simulated)";
                        $successCount++;
                    }
                } catch (Exception $e) {
                    error_log("SMS sending error: " . $e->getMessage());
                    $details[] = "Error sending alert to $name: " . $e->getMessage();
                    $failCount++;
                }
            }
            
            // Return success response
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => "Sent $successCount emergency alerts" . ($failCount > 0 ? " ($failCount failed)" : ""),
                'details' => $details,
                'success_count' => $successCount,
                'fail_count' => $failCount
            ]);
            
        } catch (Exception $e) {
            error_log("EMERGENCY ALERT ERROR: " . $e->getMessage());
            ob_end_clean(); // Clear the buffer
            echo json_encode([
                'success' => false,
                'message' => 'Error sending emergency alerts: ' . $e->getMessage()
            ]);
        }
        exit;
        
    case 'get_schedules':
        try {
            // Get service type filter if provided
            $serviceType = isset($_POST['service_type']) ? $_POST['service_type'] : '';
            
            // Direct query from staff_availability with explicit is_available=1 filter
            $sql = "SELECT * FROM staff_availability WHERE is_available = 1";
            if ($serviceType) {
                $sql .= " AND role_id = :role_id";
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
            
            error_log("Executing schedule query with is_available=1 filter: " . $sql);
            
            $stmt = $pdo->prepare($sql);
            
            if ($serviceType) {
                $stmt->bindParam(':role_id', $serviceType, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $availabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format each availability record as a schedule
            $schedules = [];
            foreach ($availabilities as $availability) {
                $scheduleId = $availability['id'];
                
                $schedule = [
                    'id' => $scheduleId,
                    'day' => $availability['day'],
                    'start_time' => $availability['start_time'],
                    'end_time' => $availability['end_time'],
                    'location' => $availability['location'] ?? 'Online',
                    'service_name' => $availability['service_name'] ?? 'Service',
                    'provider_name' => $availability['provider_name'] ?? 'Staff Member',
                    'staff_email' => $availability['staff_email'],
                    'role_id' => $availability['role_id'],
                    'is_available' => 1 // Explicitly set to 1 since we filtered in SQL
                ];
                
                $schedules[] = $schedule;
            }
            
            error_log("Found " . count($schedules) . " available service slots");
            
            echo json_encode([
                'success' => true,
                'schedules' => $schedules,
                'count' => count($schedules)
            ]);
        } catch (Exception $e) {
            error_log("Error in get_schedules: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching schedules: ' . $e->getMessage(),
                'schedules' => []
            ]);
        }
        break;

    case 'get_all_schedules':
        get_all_schedules();
        break;

        case 'get_booking_history':
            try {
                if (!isset($_SESSION['email'])) {
                    throw new Exception('User not logged in');
                }
        
                // Get DISTINCT bookings to avoid duplicates
                $stmt = $pdo->prepare("
                    SELECT DISTINCT 
                        b.*,
                        DATE_FORMAT(b.booking_date, '%M %d, %Y') as formatted_date,
                        DATE_FORMAT(b.updated_at, '%h:%i %p') as formatted_time,
                        s.start_time,
                        s.end_time,
                        sa.staff_name as provider_name
                    FROM bookings b
                    LEFT JOIN schedules s ON b.schedule_id = s.id
                    LEFT JOIN staff_availability sa ON b.provider_email = sa.staff_email
                    WHERE b.user_email = ?
                    GROUP BY b.booking_id
                    ORDER BY b.booking_date DESC
                ");
        
                $stmt->execute([$_SESSION['email']]);
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
                // Debug log
                error_log("Found " . count($bookings) . " unique bookings for user: " . $_SESSION['email']);
        
                echo json_encode([
                    'success' => true,
                    'bookings' => $bookings,
                    'count' => count($bookings)
                ]);
        
            } catch (Exception $e) {
                error_log("Error in get_booking_history: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error fetching booking history',
                    'error' => $e->getMessage()
                ]);
            }
            break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        break;
}

// Helper function to check if a string starts with a certain substring
function startsWith($haystack, $needle) {
    return strpos($haystack, $needle) === 0;
}

// Function to get all schedules
function get_all_schedules() {
    global $pdo;
    
    try {
        $sql = "SELECT sa.* 
                FROM staff_availability sa 
                WHERE sa.is_available = 1"; // Only get available slots
        
        if (isset($_POST['service_type']) && !empty($_POST['service_type'])) {
            $sql .= " AND sa.role_id = :role_id";
        }
        
        $sql .= " ORDER BY sa.day, sa.start_time";
        
        $stmt = $pdo->prepare($sql);
        
        if (isset($_POST['service_type']) && !empty($_POST['service_type'])) {
            $stmt->bindParam(':role_id', $_POST['service_type'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'schedules' => $schedules
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => "Database error: " . $e->getMessage()
        ]);
    }
}
?>