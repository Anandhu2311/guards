<?php
session_start();
require_once 'DBS.inc.php';

// Set content type to JSON
header('Content-Type: application/json');

// Debug: Log all incoming data
error_log("POST data: " . print_r($_POST, true));
error_log("SESSION data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to book appointments']);
    exit;
}

// Verify the user exists in the database
try {
    $userEmail = $_SESSION['email'];
    $verifyUserStmt = $pdo->prepare("SELECT id, email FROM users WHERE email = :email");
    $verifyUserStmt->bindParam(':email', $userEmail);
    $verifyUserStmt->execute();
    $user = $verifyUserStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found in database. Please log out and log in again.']);
        exit;
    }
    
    // Store the verified user ID
    $userId = $user['id'];
} catch (Exception $e) {
    error_log("Error verifying user: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error verifying user: ' . $e->getMessage()]);
    exit;
}

// Check if we have all required fields
$requiredFields = ['serviceType', 'scheduleId', 'day', 'startTime', 'providerEmail'];
$missing = [];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $missing[] = $field;
    }
}

if (!empty($missing)) {
    echo json_encode(['success' => false, 'message' => "Missing required fields: " . implode(', ', $missing)]);
    exit;
}

try {
    // Map service type to provider role ID with error handling
    $providerRoleMap = [
        'advising' => 2,
        'counseling' => 3,
        'support' => 4,
        'unknown' => 2 // Default to advisor if unknown
    ];
    
    $serviceType = $_POST['serviceType'];
    
    // If service type is invalid, try to determine from provider's email
    if (!isset($providerRoleMap[$serviceType])) {
        error_log("Invalid service type received: $serviceType - trying to determine from provider");
        
        // Try to get the provider's role_id from their email
        $providerEmail = $_POST['providerEmail'];
        $providerStmt = $pdo->prepare("SELECT role_id FROM users WHERE email = :email");
        $providerStmt->bindParam(':email', $providerEmail);
        $providerStmt->execute();
        $provider = $providerStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($provider && isset($provider['role_id'])) {
            // Map role_id back to service type
            $roleIdToService = [
                2 => 'advising',
                3 => 'counseling',
                4 => 'support'
            ];
            $serviceType = $roleIdToService[$provider['role_id']] ?? 'advising';
            error_log("Determined service type from provider: $serviceType");
        } else {
            // Default to advising if we can't determine
            $serviceType = 'advising';
            error_log("Could not determine service type - defaulting to: $serviceType");
        }
    }
    
    $providerRoleId = $providerRoleMap[$serviceType];
    
    // Get the actual date for the booking
    $bookingDate = getNextDateForDay($_POST['day']);
    $notes = $_POST['notes'] ?? "Booking created by user";
    
    // Create the booking
    $stmt = $pdo->prepare("
        INSERT INTO bookings (
            service_type,
            booking_date,
            booking_time,
            notes,
            user_email,
            user_id,
            provider_email,
            provider_role_id,
            status,
            created_at
        ) VALUES (
            :serviceType,
            :bookingDate,
            :bookingTime,
            :notes,
            :userEmail,
            :userId,
            :providerEmail,
            :providerRoleId,
            'pending',
            NOW()
        )
    ");
    
    $stmt->bindParam(':serviceType', $serviceType);
    $stmt->bindParam(':bookingDate', $bookingDate);
    $stmt->bindParam(':bookingTime', $_POST['startTime']);
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':userEmail', $userEmail); // Use the verified user email
    $stmt->bindParam(':userId', $userId); // Use the verified user ID
    $stmt->bindParam(':providerEmail', $_POST['providerEmail']);
    $stmt->bindParam(':providerRoleId', $providerRoleId);
    
    if ($stmt->execute()) {
        $bookingId = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'Booking created successfully',
            'booking_id' => $bookingId
        ]);
    } else {
        throw new Exception("Database error: " . implode(" ", $stmt->errorInfo()));
    }
    
} catch (Exception $e) {
    error_log("Booking error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get the next date for a given day of the week
 * 
 * @param string $dayName Day name (e.g., 'Monday')
 * @return string Next date for the day in Y-m-d format
 */
function getNextDateForDay($dayName) {
    $today = new DateTime();
    $dayMap = [
        'Sunday' => 0,
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6
    ];
    
    $targetDay = $dayMap[$dayName] ?? 1; // Default to Monday if day name is invalid
    $currentDay = (int)$today->format('w');
    
    // Calculate days to add
    $daysToAdd = ($targetDay - $currentDay + 7) % 7;
    if ($daysToAdd === 0) {
        $daysToAdd = 7; // If today, get next week
    }
    
    $today->add(new DateInterval("P{$daysToAdd}D"));
    return $today->format('Y-m-d');
}
?> 