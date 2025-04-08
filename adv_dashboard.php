<?php
// Turn off error reporting to prevent PHP errors from breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Ensure session is started first thing
session_start();
ob_start(); // Buffer all output to prevent premature HTML

// Include database connection file for all page requests
require_once 'DBS.inc.php';

// Debug session data to log
error_log('Session data in advisor dashboard: ' . print_r($_SESSION, true));

// Handle POST requests IMMEDIATELY - AJAX calls must be processed before ANY output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Make sure we only output JSON
    ob_end_clean(); // Clear any previous output
    
    // Critical headers to ensure proper JSON handling
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    try {
        require 'DBS.inc.php'; // Database connection file - include inside try block
        
        if (isset($_POST['schedule_id']) && isset($_POST['is_available'])) {
            // Handle availability updates
            $scheduleId = intval($_POST['schedule_id']);
            $isAvailable = intval($_POST['is_available']);
            
            // Get advisor email from session
            if (!isset($_SESSION['email'])) {
                echo json_encode(['success' => false, 'message' => 'User not logged in']);
                exit;
            }
            
            $advisor_email = $_SESSION['email'];
            error_log("Updating availability: User: $advisor_email, Schedule: $scheduleId, Available: $isAvailable");
            
            // Call the updateAvailability function
            echo updateAvailability($pdo, $advisor_email, $scheduleId, $isAvailable);
            exit;
        } elseif (isset($_POST['action']) && isset($_POST['booking_id'])) {
            $bookingId = intval($_POST['booking_id']);
            $responseMessage = isset($_POST['message']) ? $_POST['message'] : null;
            
            // Handle booking actions
            switch ($_POST['action']) {
                case 'accept':
                    echo acceptBooking($pdo, $bookingId, $_SESSION['email'], $responseMessage);
                    break;
                case 'reject':
                    echo rejectBooking($pdo, $bookingId, $_SESSION['email'], $responseMessage);
                    break;
                case 'complete':
                    echo completeBooking($pdo, $bookingId, $_SESSION['email']);
                    break;
                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
            exit;
        } elseif (isset($_POST['action']) && $_POST['action'] === 'handle_booking') {
            $bookingId = $_POST['booking_id'] ?? 0;
            $bookingAction = $_POST['booking_action'] ?? '';
            
            echo handleBooking($pdo, $bookingId, $bookingAction, $_SESSION['email']);
            exit;
        }
        
        // If we get here, no valid action was found
        echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    } catch (Exception $e) {
        error_log('Error in AJAX handler: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    
    exit; // CRITICAL: Ensure no further output
}

// Verify user is logged in and is an advisor
if (!isset($_SESSION['email']) || $_SESSION['role_id'] != 2) {
    error_log('Advisor session check failed: ' . print_r($_SESSION, true));
    header("Location: signin.php");
    exit();
}

// Get advisor email from session 
$advisor_email = $_SESSION['email'];
$advisor_id = $_SESSION['user_id']; // Standardize on user_id

// Get advisor data from database
$stmt = $pdo->prepare("SELECT * FROM advisors WHERE adv_email = :email");
$stmt->bindParam(':email', $advisor_email, PDO::PARAM_STR);
$stmt->execute();
$advisor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$advisor) {
    // If advisor not found in database, redirect to login
    error_log('Advisor not found in database: ' . $advisor_email);
    session_destroy();
    header("Location: signin.php");
    exit();
}

// We don't need to check password_updated here as signin.inc.php will handle redirecting to change_password.php
$userEmail = $advisor_email;

// Function to get all schedules for this advisor type
function getAdvisorSchedules($conn) {
    // Remove the reference to user_type which doesn't exist
    $sql = "SELECT * FROM schedules WHERE is_active = 1 ORDER BY 
            CASE 
                WHEN day = 'Monday' THEN 1 
                WHEN day = 'Tuesday' THEN 2 
                WHEN day = 'Wednesday' THEN 3 
                WHEN day = 'Thursday' THEN 4 
                WHEN day = 'Friday' THEN 5 
                WHEN day = 'Saturday' THEN 6 
                WHEN day = 'Sunday' THEN 7 
            END, start_time";
    
    $result = $conn->query($sql);
    
    if ($result) {
        return $result->fetchAll(PDO::FETCH_ASSOC);
    } else {
        return [];
    }
}

// Get all advisor schedules for initial page load
$advisorSchedules = getAdvisorSchedules($pdo);

// Function to get this advisor's availability
function getAdvisorAvailability($conn, $email) {
    $sql = "SELECT * FROM availability WHERE email = :email";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    
    $availability = [];
    if ($stmt) {
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Index by schedule_id (which is actually named 'id' in your table)
        foreach ($results as $row) {
            $availability[$row['id']] = $row['is_available'];
        }
    }
    
    return $availability;
}

// Get this advisor's availability
$userAvailability = getAdvisorAvailability($pdo, $userEmail);

// Add functions to count actual dashboard stats
function getCompletedSessionsCount($conn, $advisorId) {
    $sql = "SELECT COUNT(*) FROM bookings 
            WHERE provider_email = :advisor_email 
            AND status = 'completed'";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':advisor_email', $_SESSION['email'], PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn();
}

function getUpcomingSessionsCount($conn, $advisorId) {
    $sql = "SELECT COUNT(*) FROM bookings 
            WHERE provider_email = :advisor_email 
            AND status = 'confirmed' 
            AND booking_date >= CURDATE() 
            AND booking_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':advisor_email', $_SESSION['email'], PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn();
}

function getAvailableSlotsCount($conn, $advisorEmail) {
    $sql = "SELECT COUNT(*) FROM availability WHERE email = :email AND is_available = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $advisorEmail, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Get dashboard stats
$completedSessions = getCompletedSessionsCount($pdo, $advisor_id);
$upcomingSessions = getUpcomingSessionsCount($pdo, $advisor_id);
$availableSlots = getAvailableSlotsCount($pdo, $advisor_email);

// Function to get bookings for the current advisor
function getAdvisorBookings($conn, $advisorEmail, $advisorId) {
    // Get all bookings related to this advisor's specialization or where this advisor is assigned
    $sql = "SELECT b.*, 
               COALESCE(u.name, b.user_email) as user_name,
               COALESCE(u.email, b.user_email) as user_email_display
           FROM bookings b 
           LEFT JOIN users u ON b.user_email = u.email
           WHERE b.provider_email = :email 
              OR b.service_type = 'advising'
           ORDER BY b.booking_date DESC, b.booking_time DESC";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $advisorEmail, PDO::PARAM_STR);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug logging to check if we're getting any results
        error_log("Found " . count($results) . " bookings for advisor: " . $advisorEmail);
        
        return $results;
    } catch (PDOException $e) {
        error_log("Error getting advisor bookings: " . $e->getMessage());
        return [];
    }
}

// Function to accept a booking
function acceptBooking($conn, $bookingId, $advisorEmail, $responseMessage = null) {
    $sql = "UPDATE bookings 
            SET status = 'confirmed', 
                provider_email = :email,
                response_message = :message,
                responded_at = NOW()
            WHERE booking_id = :booking_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $advisorEmail, PDO::PARAM_STR);
    $stmt->bindParam(':message', $responseMessage, PDO::PARAM_STR);
    $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        return json_encode(['success' => true]);
    } else {
        error_log('Booking acceptance failed: ' . implode(", ", $stmt->errorInfo()));
        return json_encode(['success' => false, 'message' => 'Failed to accept booking']);
    }
}

// Function to reject a booking
function rejectBooking($conn, $bookingId, $advisorEmail, $responseMessage = null) {
    $sql = "UPDATE bookings 
            SET status = 'rejected', 
                provider_email = :email,
                response_message = :message,
                responded_at = NOW()
            WHERE booking_id = :booking_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $advisorEmail, PDO::PARAM_STR);
    $stmt->bindParam(':message', $responseMessage, PDO::PARAM_STR);
    $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        return json_encode(['success' => true]);
    } else {
        error_log('Booking rejection failed: ' . implode(", ", $stmt->errorInfo()));
        return json_encode(['success' => false, 'message' => 'Failed to reject booking']);
    }
}

// Function to complete a booking
function completeBooking($conn, $bookingId, $advisorEmail) {
    $sql = "UPDATE bookings 
            SET status = 'completed', 
                updated_at = NOW()
            WHERE booking_id = :booking_id 
              AND provider_email = :email 
              AND status = 'confirmed'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $advisorEmail, PDO::PARAM_STR);
    $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        return json_encode(['success' => true]);
    } else {
        error_log('Booking completion failed: ' . implode(", ", $stmt->errorInfo()));
        return json_encode(['success' => false, 'message' => 'Failed to complete booking']);
    }
}

/**
 * Handle booking actions (accept, reject, complete)
 */
function handleBooking($pdo, $bookingId, $action, $advisorEmail) {
    try {
        // Verify the booking belongs to this advisor
        $checkStmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND provider_email = ?");
        $checkStmt->execute([$bookingId, $advisorEmail]);
        $booking = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            return json_encode(['success' => false, 'message' => 'Booking not found or not assigned to you']);
        }
        
        $newStatus = '';
        switch ($action) {
            case 'accept':
                $newStatus = 'confirmed';
                break;
            case 'reject':
                $newStatus = 'cancelled';
                break;
            case 'complete':
                $newStatus = 'complete';
                break;
            default:
                return json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
        // Update the booking status
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
        $stmt->execute([$newStatus, $bookingId]);
        
        return json_encode([
            'success' => true, 
            'message' => 'Booking updated successfully',
            'new_status' => ucfirst(str_replace('_', ' ', $newStatus))
        ]);
    } catch (PDOException $e) {
        error_log("Error handling booking: " . $e->getMessage());
        return json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Add this function to update advisor availability
function updateAvailability($pdo, $advisor_email, $scheduleId, $isAvailable) {
    try {
        // Validate inputs
        $scheduleId = intval($scheduleId);
        $isAvailable = intval($isAvailable);
        
        if ($scheduleId <= 0) {
            return json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
        }
        
        // First, check if the schedule exists
        $checkSchedule = $pdo->prepare("SELECT id FROM schedules WHERE id = :id");
        $checkSchedule->bindParam(':id', $scheduleId, PDO::PARAM_INT);
        $checkSchedule->execute();
        
        if (!$checkSchedule->fetch()) {
            return json_encode(['success' => false, 'message' => 'Schedule not found']);
        }
        
        // Check if a record already exists in the availability table
        $checkStmt = $pdo->prepare("SELECT id FROM availability WHERE email = :email AND id = :scheduleId");
        $checkStmt->bindParam(':email', $advisor_email, PDO::PARAM_STR);
        $checkStmt->bindParam(':scheduleId', $scheduleId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->fetch()) {
            // Update existing record
            $stmt = $pdo->prepare("UPDATE availability SET is_available = :isAvailable WHERE email = :email AND id = :scheduleId");
        } else {
            // Insert new record
            $stmt = $pdo->prepare("INSERT INTO availability (email, id, is_available) VALUES (:email, :scheduleId, :isAvailable)");
        }
        
        $stmt->bindParam(':email', $advisor_email, PDO::PARAM_STR);
        $stmt->bindParam(':scheduleId', $scheduleId, PDO::PARAM_INT);
        $stmt->bindParam(':isAvailable', $isAvailable, PDO::PARAM_INT);
        
        $result = $stmt->execute();
        
        if ($result) {
            return json_encode(['success' => true, 'message' => 'Availability updated successfully']);
        } else {
            return json_encode(['success' => false, 'message' => 'Failed to update availability']);
        }
    } catch (PDOException $e) {
        error_log("Error toggling availability: " . $e->getMessage());
        return json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Get advisor bookings
$advisorBookings = getAdvisorBookings($pdo, $advisor_email, $advisor_id);

// Function to check if medical_notes handling is working
function testMedicalNotesForm() {
    global $pdo;
    
    try {
        // Check if we can connect to the database
        $dbStatus = "Connected to database successfully";
        
        // Check if medical_notes table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'medical_notes'");
        $tableExists = $stmt->rowCount() > 0;
        
        // Check if there's at least one booking
        $stmt = $pdo->query("SELECT * FROM bookings LIMIT 1");
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasBookings = count($bookings) > 0;
        
        return [
            'status' => 'success',
            'dbConnection' => true,
            'tableExists' => $tableExists,
            'hasBookings' => $hasBookings,
            'bookingSample' => $hasBookings ? $bookings[0] : null
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

$testResult = testMedicalNotesForm();

function getAvailableSchedules($conn, $providerType = '') {
    try {
        $sql = "SELECT DISTINCT s.id, s.day, s.start_time, s.end_time, s.is_active,
                a.staff_id, a.email as staff_email, u.role_id, 
                COALESCE(adv.adv_name, u.name) as provider_name,
                'Academic Advising Session' as service_name
                FROM schedules s
                JOIN availability a ON s.id = a.schedule_id
                JOIN users u ON a.email = u.email
                LEFT JOIN advisors adv ON a.email = adv.adv_email
                WHERE s.is_active = 1 
                AND a.is_available = 1";
        
        if (!empty($providerType)) {
            $sql .= " AND u.role_id = :role_id";
        }
        
        $sql .= " ORDER BY s.day, s.start_time";
        
        $stmt = $conn->prepare($sql);
        
        if (!empty($providerType)) {
            $roleId = getRoleIdFromProviderType($providerType);
            $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] ERROR in getAvailableSchedules: " . $e->getMessage());
        return [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GuardSphere Advisor Dashboard</title>
    <script src="disable-navigation.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Header and Navigation styles */
        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            background: #1a1a1a;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5%;
            height: 70px;
        }

        .logo {
            height: 40px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: #ffffff;
            font-weight: 500;
            transition: opacity 0.3s ease;
        }

        .nav-links a:hover {
            opacity: 0.8;
        }

        /* Profile dropdown styles */
        .profile-section {
            position: relative;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #9932CC;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .user-avatar:hover {
            transform: scale(1.05);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 12px;
            min-width: 200px;
            z-index: 1001;
            margin-top: 0.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .dropdown-content.show {
            display: block;
            animation: fadeIn 0.3s;
        }

        .dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
        }

        .dropdown-content a:hover {
            background: #f0f0f0;
            color: #9932CC;
        }

        /* Sidebar styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1a1a1a 0%, #2d2d2d 100%);
            color: white;
            height: 100vh;
            position: fixed;
            top: 70px;
            left: 0;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 999;
            padding-bottom: 70px; /* Add padding to avoid content being cut off */
        }

        .sidebar-content {
            padding: 1.5rem;
        }

        .welcome-text {
            font-size: 1.2rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .user-email {
            color: #9932CC;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 2rem;
            word-break: break-all;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 5px 0;
            cursor: pointer;
        }

        .sidebar-link:hover {
            background: rgba(153, 50, 204, 0.1);
            color: #9932CC;
            text-decoration: none;
        }

        .sidebar-link.active {
            background: #9932CC;
            color: white;
        }

        .sidebar-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main content styles */
        .main-content {
            flex: 1;
            padding: 30px;
            background: #f8f9fa;
            margin-left: 250px;
            min-height: calc(100vh - 60px);
        }

        /* Dashboard overview styles */
        .section-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .stat-card-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: #777;
            letter-spacing: 1px;
        }

        .stat-card-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .stat-card-icon.purple {
            background: linear-gradient(135deg, #9932CC 0%, #B768D4 100%);
        }

        .stat-card-icon.blue {
            background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%);
        }

        .stat-card-icon.green {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
        }

        .stat-card-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.3rem;
        }

        .stat-card-description {
            font-size: 0.9rem;
            color: #777;
        }

        /* Availability manager styles */
        .availability-manager {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .availability-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .schedule-list {
            display: grid;
            gap: 1.2rem;
        }

        .schedule-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-radius: 12px;
            background: #f8f8f8;
            transition: all 0.2s ease;
        }

        .schedule-item:hover {
            background: #f0f0f0;
            transform: translateX(5px);
        }

        .schedule-info {
            display: flex;
            flex-direction: column;
        }

        .schedule-day {
            font-weight: 600;
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 0.3rem;
        }

        .schedule-time {
            font-size: 0.9rem;
            color: #666;
        }

        .schedule-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .schedule-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .schedule-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 26px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #9932CC;
        }

        input:focus + .slider {
            box-shadow: 0 0 1px #9932CC;
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

        .availability-status {
            min-width: 110px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Bookings section styles */
        .bookings-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .bookings-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .bookings-table th,
        .bookings-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .bookings-table th {
            font-weight: 600;
            color: #555;
            background: #f9f9f9;
        }

        .bookings-table tr:hover {
            background: #f8f8f8;
        }

        /* Action buttons styles */
        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 8px;
            font-size: 0.9rem;
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .action-btn:active {
            transform: translateY(0);
        }

        .accept-btn {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
        }

        .reject-btn {
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
        }

        .complete-btn {
            background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%);
        }

        .no-actions {
            color: #999;
            font-style: italic;
        }

        /* Status cell styling */
        .status-cell {
            font-weight: 500;
        }

        tr.pending-row .status-cell {
            color: #f39c12;
        }

        tr.confirmed-row .status-cell {
            color: #3498db;
        }

        tr.completed-row .status-cell {
            color: #27ae60;
        }

        tr.rejected-row .status-cell {
            color: #e74c3c;
        }

        .error-message {
            color: #e74c3c;
            font-weight: 500;
        }

        /* Animation keyframes */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }

        .user-email-link {
            color: #4e73df;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-email-link:hover {
            color: #2e59d9;
            text-decoration: underline;
        }

        .user-email-link i {
            margin-left: 5px;
            font-size: 14px;
            opacity: 0.7;
        }

        .user-email-link:hover i {
            opacity: 1;
            transform: translateX(2px);
        }

        .badge-warning {
            background-color: #f6c23e;
        }

        .badge-primary {
            background-color: #4e73df;
        }

        .badge-success {
            background-color: #1cc88a;
        }

        .badge-info {
            background-color: #36b9cc;
        }

        .badge-danger {
            background-color: #e74a3b;
        }

        .patient-info {
            border-left: 4px solid #4e73df;
        }

        .btn-group-toggle .btn {
            margin-right: 5px;
        }

        /* Bookings Table Styling */
        #bookingsTable {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 14px;
        }

        #bookingsTable th {
            background-color: #4e73df;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
        }

        #bookingsTable td {
            padding: 10px 15px;
            border-bottom: 1px solid #e3e6f0;
            vertical-align: middle;
        }

        #bookingsTable tr:hover {
            background-color: #f8f9fc;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
            display: inline-block;
        }

        /* Modal Styling */
        .modal-content {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .modal-header {
            background-color: #4e73df;
            color: white;
            border-radius: 10px 10px 0 0;
            padding: 15px 20px;
            align-items: center;
        }

        .modal-header .close {
            color: white;
            opacity: 0.8;
            text-shadow: none;
        }

        .modal-header .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 20px;
        }

        .patient-info {
            background-color: #f8f9fc;
            border-left: 4px solid #4e73df;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .patient-info h6 {
            color: #4e73df;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .form-group label {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 5px;
        }

        .form-control {
            border: 1px solid #d1d3e2;
            border-radius: 5px;
            padding: 10px 15px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #bac8f3;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        /* Status buttons styling */
        .btn-group-toggle {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .btn-group-toggle .btn {
            flex: 1;
            min-width: 120px;
            text-align: center;
            border-radius: 5px;
            font-size: 13px;
            margin: 0;
            padding: 8px 10px;
            white-space: nowrap;
        }

        .btn-outline-primary {
            color: #4e73df;
            border-color: #4e73df;
        }

        .btn-outline-primary:hover,
        .btn-outline-primary.active {
            background-color: #4e73df;
            color: white;
        }

        .btn-outline-info {
            color: #36b9cc;
            border-color: #36b9cc;
        }

        .btn-outline-info:hover,
        .btn-outline-info.active {
            background-color: #36b9cc;
            color: white;
        }

        .btn-outline-success {
            color: #1cc88a;
            border-color: #1cc88a;
        }

        .btn-outline-success:hover,
        .btn-outline-success.active {
            background-color: #1cc88a;
            color: white;
        }

        .btn-outline-warning {
            color: #f6c23e;
            border-color: #f6c23e;
        }

        .btn-outline-warning:hover,
        .btn-outline-warning.active {
            background-color: #f6c23e;
            color: white;
        }

        /* Fix for mobile responsiveness */
        @media (max-width: 768px) {
            .btn-group-toggle {
                flex-direction: column;
            }

            .btn-group-toggle .btn {
                margin-bottom: 5px;
            }

            .modal-dialog {
                margin: 10px;
            }
        }

        @media (min-width: 576px) {
            .modal-dialog {
                max-width: 500px;
            }
        }

        @media (min-width: 992px) {
            .modal-dialog.modal-lg {
                max-width: 800px;
            }
        }

        /* Ensure modal is hidden by default */
        .modal {
            display: none;
            opacity: 0;
            visibility: hidden;
        }

        /* Fix content width */
        .main-content {
            width: calc(100% - 240px);
            margin-left: 240px;
            padding: 20px;
            box-sizing: border-box;
        }

        @media (max-width: 768px) {
            .main-content {
                width: 100%;
                margin-left: 0;
            }
        }

        /* Prevent text overflow in table cells */
        #bookingsTable td {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Fix for modal extending beyond sidebar */
        .modal-dialog {
            max-width: calc(100% - 30px);
            margin: 1.75rem auto;
        }

        @media (min-width: 576px) {
            .modal-dialog {
                max-width: 500px;
            }
        }

        @media (min-width: 992px) {
            .modal-dialog.modal-lg {
                max-width: 800px;
            }
        }

        /* Ensure modal is hidden by default */
        .modal.fade {
            display: none !important;
        }

        .modal.fade.show {
            display: block !important;
        }

        /* Fix content width */
        .main-content {
            width: calc(100% - 240px);
            margin-left: 240px;
            padding: 20px;
            box-sizing: border-box;
            overflow-x: hidden; /* Prevent horizontal scrolling */
        }

        @media (max-width: 768px) {
            .main-content {
                width: 100%;
                margin-left: 0;
            }
        }

        /* Prevent text overflow in table cells */
        #bookingsTable td {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Ensure table is responsive */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Add these refined styles to improve the existing dashboard */
        .card {
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            border: none;
        }

        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            display: flex;
            align-items: center;
        }

        .card-header h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .card-header h5 i {
            margin-right: 0.5rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        /* Table improvements */
        .table-responsive {
            overflow-x: auto;
        }

        .table th {
            background-color: #4e73df;
            color: white;
            font-weight: 500;
            border-bottom: none;
            vertical-align: middle;
        }

        .table td {
            vertical-align: middle;
            padding: 0.75rem;
        }

        /* Badge styling */
        .badge {
            padding: 0.4rem 0.6rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Improved form styling */
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        /* User email link styling */
        .user-email-link {
            color: #4e73df;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-email-link:hover {
            color: #2e59d9;
            text-decoration: underline;
        }

        .user-email-link i {
            margin-left: 5px;
            color: #1cc88a;
            transition: transform 0.2s;
        }

        .user-email-link:hover i {
            transform: translateX(2px);
        }

        /* Button improvements */
        .btn {
            border-radius: 0.35rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }

        /* Improved modal styling */
        #medicalNotesModal .modal-dialog {
            max-width: 500px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        #medicalNotesModal .modal-content {
            border: none;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        #medicalNotesModal .modal-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            border-bottom: none;
        }

        #medicalNotesModal .modal-body {
            padding: 1.5rem;
        }

        #medicalNotesModal .patient-info {
            background-color: #f8f9fc;
            border-left: 4px solid #4e73df;
            padding: 1rem;
            border-radius: 0.35rem;
            margin-bottom: 1.5rem;
        }

        /* Status badges */
        .badge-primary {
            background-color: #4e73df;
        }

        .badge-success {
            background-color: #1cc88a;
        }

        .badge-warning {
            background-color: #f6c23e;
            color: #fff;
        }

        .badge-danger {
            background-color: #e74a3b;
        }

        .badge-info {
            background-color: #36b9cc;
        }

        /* Availability toggle button styling */
        .availability-toggle {
            min-width: 120px;
            text-align: center;
        }

        /* Fix alignment of icons in buttons */
        .btn i {
            margin-right: 5px;
        }

        /* Consistency in color scheme */
        .text-primary {
            color: #4e73df !important;
        }

        /* Fix table hover */
        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }

        /* Ensure modal is hidden by default */
        .modal.fade {
            display: none;
        }

        .modal.fade.show {
            display: block;
        }

        /* Add these CSS rules to fix the sidebar and main content separation */
        .sidebar {
            position: fixed;
            top: 70px; /* Adjust based on your header height */
            left: 0;
            width: 240px;
            height: calc(100vh - 70px);
            background-color: #2c3e50; /* Use your existing sidebar color */
            z-index: 100;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        /* Ensure main content doesn't overlap sidebar */
        .main-content {
            margin-left: 240px; /* Same as sidebar width */
            margin-top: 70px;
            padding: 20px;
            width: calc(100% - 240px);
            min-height: calc(100vh - 70px);
            transition: all 0.3s ease; /* Smooth transitions if sidebar collapses */
            overflow-x: hidden; /* Prevent horizontal scrolling */
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }

        /* Fix any potential z-index issues */
        .modal-backdrop {
            z-index: 1040;
        }

        .modal {
            z-index: 1050;
        }

        /* Animation for notification appearance */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-completed {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .user-email-link {
            color: #4e73df;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-email-link:hover {
            color: #2e59d9;
            text-decoration: underline;
        }

        .user-email-link i {
            margin-left: 5px;
            font-size: 14px;
            opacity: 0.7;
        }

        .user-email-link:hover i {
            opacity: 1;
            transform: translateX(2px);
        }

        .filter-btn.active {
            font-weight: bold;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        /* Animation for clicked items */
        .clicked {
            animation: pulse 0.3s ease-in-out;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(0.97); }
            100% { transform: scale(1); }
        }

        /* Improved table styles */
        .table-bordered {
            border: 1px solid #e3e6f0;
        }

        .table-bordered th,
        .table-bordered td {
            border: 1px solid #e3e6f0;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }

        .card.shadow {
            box-shadow: 0 .15rem 1.75rem 0 rgba(58, 59, 69, .15)!important;
        }

        .card-header.py-3 {
            padding-top: 1rem!important;
            padding-bottom: 1rem!important;
        }

        .font-weight-bold {
            font-weight: 700!important;
        }

        .text-primary {
            color: #4e73df!important;
        }

        /* Replace the existing admin notifications CSS with this improved version */
        .admin-notifications-wrapper {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 320px;
            max-width: 95vw;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            z-index: 1080;
            overflow: hidden;
            transition: all 0.3s ease;
            font-family: "Nunito", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .admin-notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: linear-gradient(to right, #4e73df, #224abe);
            color: white;
            cursor: pointer;
        }

        .admin-notifications-header h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
        }

        .admin-notifications-header h5 i {
            margin-right: 8px;
        }

        .admin-notifications-badge {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            margin-left: 8px;
            background-color: #e74a3b;
            color: white;
            font-size: 0.7rem;
            border-radius: 50px;
            font-weight: 700;
        }

        .toggle-notifications {
            background: none;
            color: white;
            border: none;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
            padding: 5px;
        }

        .toggle-notifications:focus {
            outline: none;
        }

        .toggle-notifications.collapsed i {
            transform: rotate(-180deg);
        }

        .admin-notifications-content {
            max-height: 60vh;
            overflow-y: auto;
            padding: 0;
            background-color: #fff;
            transition: max-height 0.3s ease;
        }

        .notification-item {
            display: flex;
            padding: 15px;
            border-bottom: 1px solid #e3e6f0;
            position: relative;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background-color: #f8f9fc;
        }

        .notification-icon {
            flex: 0 0 40px;
            height: 40px;
            margin-right: 15px;
            background-color: #f2f4f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4e73df;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            margin: 0 0 5px 0;
            font-weight: 700;
            font-size: 0.9rem;
            color: #5a5c69;
        }

        .notification-message {
            margin: 0 0 10px 0;
            font-size: 0.85rem;
            color: #858796;
            line-height: 1.5;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #b7b9cc;
            margin-bottom: 8px;
        }

        .notification-actions {
            display: flex;
            gap: 8px;
        }

        .notification-actions .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        /* Animation for notification appearance */
        @keyframes notify-slide-in {
            0% { transform: translateX(100%); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }

        .new-notification {
            animation: notify-slide-in 0.4s forwards;
        }

        /* Add these styles to your CSS section */
        .schedule-notifications {
            border-radius: 8px;
            overflow: hidden;
        }

        .schedule-notifications .notification-item {
            border-left: 4px solid #4e73df;
            margin-bottom: 15px;
            background-color: #f8f9fc;
            border-radius: 5px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .schedule-notifications .notification-item:last-child {
            margin-bottom: 0;
        }

        .schedule-notifications .notification-item:hover {
            box-shadow: 0 0.15rem 0.35rem rgba(0, 0, 0, 0.1);
        }

        .schedule-notifications .notification-icon {
            background-color: #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        /* Notification types */
        .schedule-notifications .notification-item.alert {
            border-left-color: #e74a3b;
        }

        .schedule-notifications .notification-item.success {
            border-left-color: #1cc88a;
        }

        .schedule-notifications .notification-item.warning {
            border-left-color: #f6c23e;
        }

        /* Empty notifications state */
        .schedule-notifications-empty {
            text-align: center;
            padding: 30px 15px;
            color: #858796;
        }

        .schedule-notifications-empty i {
            font-size: 3rem;
            color: #dddfeb;
            margin-bottom: 15px;
        }

        .schedule-notifications-empty p {
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        /* Add this CSS for schedule item highlighting */
        .schedule-item.highlight {
            border: 2px solid #4e73df !important;
            box-shadow: 0 0 10px rgba(78, 115, 223, 0.3) !important;
        }

        @keyframes pulse-animation {
            0% { box-shadow: 0 0 0 0 rgba(78, 115, 223, 0.5); }
            70% { box-shadow: 0 0 0 10px rgba(78, 115, 223, 0); }
            100% { box-shadow: 0 0 0 0 rgba(78, 115, 223, 0); }
        }

        .pulse-animation {
            animation: pulse-animation 1.5s infinite;
        }

        /* Toast styling */
        .toast {
            background-color: white;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
        }

        .toast-header {
            background-color: rgba(255, 255, 255, 0.85);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Add these styles for the embedded schedule notifications */
        .schedule-admin-notifications {
            border-radius: 8px;
            background-color: #f8f9fc;
            padding: 15px;
            border-left: 4px solid #4e73df;
        }

        .schedule-admin-notifications h6 {
            margin-top: 0;
            font-size: 0.9rem;
        }

        /* Make notifications in the schedule section more compact */
        .schedule-management .notification-item {
            margin-bottom: 10px;
            background-color: white;
        }

        .schedule-management .notification-icon {
            width: 36px;
            height: 36px;
        }

        /* Notification type colors specifically for the schedule section */
        .schedule-admin-notifications .notification-item.alert {
            border-left-color: #e74a3b;
        }

        .schedule-admin-notifications .notification-item.success {
            border-left-color: #1cc88a;
        }

        .schedule-admin-notifications .notification-item.warning {
            border-left-color: #f6c23e;
        }

        /* Toggle button to show/hide schedule notifications */
        .toggle-schedule-notifications {
            background: none;
            border: none;
            color: #4e73df;
            font-size: 0.8rem;
            padding: 0;
            margin-left: 10px;
            cursor: pointer;
        }

        .toggle-schedule-notifications:focus {
            outline: none;
        }

        .toggle-schedule-notifications i {
            transition: transform 0.2s;
        }

        .toggle-schedule-notifications.collapsed i {
            transform: rotate(-180deg);
        }

        /* Add these styles for the availability grid */
        .week-navigation {
            padding: 10px 0;
            border-bottom: 1px solid #e3e6f0;
            margin-bottom: 20px;
        }

        .availability-grid {
            border: 1px solid #e3e6f0;
            border-radius: 5px;
            overflow: hidden;
        }

        .availability-grid-header {
            display: grid;
            grid-template-columns: 80px repeat(7, 1fr);
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }

        .availability-grid-day {
            padding: 10px;
            text-align: center;
            font-weight: 700;
            font-size: 0.85rem;
            color: #4e73df;
            border-left: 1px solid #e3e6f0;
        }

        .availability-grid-day.weekend {
            background-color: rgba(78, 115, 223, 0.05);
            color: #858796;
        }

        .availability-grid-time-header {
            border-right: 1px solid #e3e6f0;
            background-color: #f8f9fc;
        }

        .availability-grid-row {
            display: grid;
            grid-template-columns: 80px repeat(7, 1fr);
            border-bottom: 1px solid #e3e6f0;
        }

        .availability-grid-row:last-child {
            border-bottom: none;
        }

        .availability-grid-time {
            padding: 10px;
            font-size: 0.85rem;
            color: #5a5c69;
            text-align: center;
            border-right: 1px solid #e3e6f0;
            background-color: #f8f9fc;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .availability-grid-cell {
            height: 50px;
            border-left: 1px solid #e3e6f0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            position: relative;
        }

        .availability-grid-cell:hover {
            background-color: rgba(78, 115, 223, 0.1);
        }

        .availability-grid-cell.weekend {
            background-color: rgba(78, 115, 223, 0.05);
        }

        .availability-grid-cell.available {
            background-color: rgba(28, 200, 138, 0.1);
        }

        .availability-grid-cell.available:hover {
            background-color: rgba(28, 200, 138, 0.2);
        }

        .availability-grid-cell.available.has-booking {
            background-color: rgba(246, 194, 62, 0.1);
        }

        .availability-grid-cell.available.has-booking:hover {
            background-color: rgba(246, 194, 62, 0.2);
        }

        .availability-status {
            color: #1cc88a;
            font-size: 0.75rem;
        }

        .availability-bookings {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: #f6c23e;
            color: white;
            font-size: 0.65rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Legend */
        .legend {
            display: inline-flex;
            align-items: center;
            margin: 0 15px;
        }

        .legend-item {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            margin-right: 8px;
        }

        .legend-item.available {
            background-color: rgba(28, 200, 138, 0.2);
            border: 1px solid rgba(28, 200, 138, 0.5);
        }

        .legend-item.unavailable {
            background-color: #f8f9fc;
            border: 1px solid #e3e6f0;
        }

        /* Add loading indicator */
        .availability-grid-cell.loading {
            position: relative;
        }

        .availability-grid-cell.loading:after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(78, 115, 223, 0.2);
            border-top: 2px solid #4e73df;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .status-filters {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            background: #9932CC;
            color: white;
            border-color: #9932CC;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .status-badge.pending { background: #ffd700; }
        .status-badge.confirmed { background: #90EE90; }
        .status-badge.completed { background: #87CEEB; }
        .status-badge.cancelled { background: #FFB6C1; }

        .btn-view {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            background: #9932CC;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-view:hover {
            background: #7B2C9D;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card-title {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 600;
            color: #9932CC;
        }

        .recent-activity {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .activity-list {
            margin-top: 20px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: rgba(153, 50, 204, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9932CC;
            margin-right: 15px;
        }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            color: #333;
        }

        .activity-time {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 120" class="logo">
                <g transform="translate(30, 10)">
                    <path d="M50 35 C45 25, 30 25, 25 35 C20 45, 25 55, 50 75 C75 55, 80 45, 75 35 C70 25, 55 25, 50 35"
                        fill="#FF1493" />
                    <path
                        d="M15 55 C12 55, 5 58, 5 75 C5 82, 8 87, 15 90 L25 92 C20 85, 18 80, 20 75 C22 70, 25 68, 30 70 C28 65, 25 62, 20 62 C15 62, 15 65, 15 55"
                        fill="#9932CC" />
                    <path
                        d="M85 55 C88 55, 95 58, 95 75 C95 82, 92 87, 85 90 L75 92 C80 85, 82 80, 80 75 C78 70, 75 68, 70 70 C72 65, 75 62, 80 62 C85 62, 85 65, 85 55"
                        fill="#9932CC" />
                    <path d="M45 40 Q50 45, 55 40 Q52 35, 45 40" fill="#FF69B4" opacity="0.5" />
                </g>
                <text x="150" y="80" font-family="Arial Black, sans-serif" font-weight="900" font-size="60"
                    fill="#333">GUARDSPHERE</text>
                <text x="150" y="105" font-family="Arial, sans-serif" font-size="20" fill="#666">GUARDED BY
                    GUARDSPHERE.</text>
            </svg>
            <div class="nav-links">
                <div class="profile-section">
                    <div class="user-avatar" onclick="toggleDropdown()">
                        <?php 
                            // Display the first character of the email
                            echo strtoupper(substr($userEmail, 0, 1)); 
                        ?>
                    </div>
                    <div id="profileDropdown" class="dropdown-content">
                        <a href="#"><i class="fas fa-cog"></i> Settings</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    
    <div class="sidebar">
        <div class="sidebar-content">
            <div class="welcome-text">Welcome,</div>
            <div class="user-email"><?php echo $userEmail; ?></div>
            
            <ul class="sidebar-menu">
                <li><a class="sidebar-link active" onclick="showDashboardOverview()"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a class="sidebar-link" onclick="showAvailabilityManager()"><i class="fas fa-calendar-alt"></i> Manage Availability</a></li>
                <li><a class="sidebar-link" onclick="showBookings()"><i class="fas fa-calendar-check"></i> View Bookings</a></li>
            </ul>
        </div>
    </div>
    
    <div class="main-content">
        <!-- Dashboard Overview Section -->
        <div id="dashboardOverview" class="content-section">
            <h1 class="section-title">Dashboard Overview</h1>
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-card-title">Total Bookings</div>
                    <div class="stat-card-value"><?php echo $totalBookings; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-title">Pending Bookings</div>
                    <div class="stat-card-value"><?php echo $pendingBookings; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-title">Available Slots</div>
                    <div class="stat-card-value"><?php echo $availableSlots; ?></div>
                </div>
            </div>
            <div class="recent-activity">
                <h2>Recent Activity</h2>
                <div class="activity-list">
                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon"><i class="fas fa-calendar-check"></i></div>
                            <div class="activity-details">
                                <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                                <div class="activity-time"><?php echo $activity['time']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Availability Manager Section -->
        <div id="availabilityManager" class="content-section" style="display: none;">
            <h1 class="section-title">Manage Availability</h1>
            <div class="availability-controls">
                <button id="addTimeSlotBtn" class="btn-primary">
                    <i class="fas fa-plus"></i> Add Time Slot
                </button>
            </div>
            <div class="schedule-grid">
                <table class="availability-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <?php foreach ($weekDays as $day): ?>
                                <th><?php echo $day; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timeSlots as $time): ?>
                            <tr>
                                <td class="time-cell"><?php echo $time; ?></td>
                                <?php foreach ($weekDays as $day): ?>
                                    <td class="schedule-cell" data-day="<?php echo $day; ?>" data-time="<?php echo $time; ?>">
                                        <?php if (isset($availabilityData[$day][$time])): ?>
                                            <div class="availability-status">
                                                <i class="fas fa-check"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bookings Section -->
        <div id="bookingsSection" class="content-section" style="display: none;">
            <h1 class="section-title">Your Bookings</h1>
            <div class="bookings-section">
                <div class="bookings-title">Manage Bookings</div>
                <div class="status-filters">
                    <button class="filter-btn active" data-status="all">All</button>
                    <button class="filter-btn" data-status="pending">Pending</button>
                    <button class="filter-btn" data-status="confirmed">Confirmed</button>
                    <button class="filter-btn" data-status="completed">Completed</button>
                    <button class="filter-btn" data-status="cancelled">Cancelled</button>
                </div>
                <div id="bookingsList">
                    <table id="bookingsTable" class="bookings-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Student</th>
                                <th>Service</th>
                                <th>Notes</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($advisorBookings as $booking): ?>
                                <tr data-status="<?php echo $booking['status']; ?>">
                                    <td><?php echo date('Y-m-d', strtotime($booking['booking_date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($booking['booking_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($booking['user_email']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['service_type']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['notes'] ?? ''); ?></td>
                                    <td><span class="status-badge <?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                    <td>
                                        <button class="btn-view" onclick="viewBookingDetails(<?php echo $booking['booking_id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script>
        // Toggle dropdown function
        function toggleDropdown() {
            document.getElementById('profileDropdown').classList.toggle('show');
        }
        
        // Show dashboard overview
        function showDashboardOverview() {
            document.querySelectorAll('.content-section').forEach(section => {
                section.style.display = 'none';
            });
            document.getElementById('dashboardOverview').style.display = 'block';
            updateActiveLink('showDashboardOverview()');
        }
        
        // Show availability manager
        function showAvailabilityManager() {
            document.querySelectorAll('.content-section').forEach(section => {
                section.style.display = 'none';
            });
            document.getElementById('availabilityManager').style.display = 'block';
            updateActiveLink('showAvailabilityManager()');
        }
        
        function showProfileSection() {
            // Hide all sections
            document.getElementById('dashboardOverview').style.display = 'none';
            document.getElementById('availabilityManager').style.display = 'none';
            document.getElementById('profileSection').style.display = 'block';
            
            // Update active class (optional, if you want to highlight a menu item)
            const links = document.querySelectorAll('.sidebar-link');
            links.forEach(link => link.classList.remove('active'));
            
            // Close dropdown
            document.getElementById('profileDropdown').classList.remove('show');
        }
        
        // Add this new function to refresh bookings via AJAX
        function refreshBookings() {
            // Show a loading indicator
            document.getElementById('bookingsList').innerHTML = '<p>Loading bookings...</p>';
            
            // Fetch updated bookings
            fetch('get_bookings.php')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('bookingsList').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error refreshing bookings:', error);
                    document.getElementById('bookingsList').innerHTML = 
                        '<p class="error-message">Error loading bookings. Please try again.</p>';
                });
        }
        
        // Improved updateAvailability function
        function updateAvailability(checkbox) {
            const scheduleId = checkbox.dataset.scheduleId;
            const isAvailable = checkbox.checked ? 1 : 0;
            const statusElement = checkbox.parentNode.nextElementSibling;
            
            // Store original state in case we need to revert
            const originalState = checkbox.checked;
            
            // Update status text immediately for better UX
            statusElement.textContent = checkbox.checked ? 'Available' : 'Not Available';
            
            // Disable the checkbox while processing
            checkbox.disabled = true;
            
            console.log('Sending availability update request:', { scheduleId, isAvailable });
            
            // Create a unique request ID to help with debugging
            const requestId = Math.random().toString(36).substring(2, 15);
            
            // Send data to server with added debugging
            fetch(window.location.pathname + `?request=${requestId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `schedule_id=${scheduleId}&is_available=${isAvailable}`
            })
            .then(response => {
                console.log(`[${requestId}] Response status:`, response.status);
                // Log full response for debugging
                response.clone().text().then(text => {
                    console.log(`[${requestId}] Full response:`, text.substring(0, 200) + (text.length > 200 ? '...' : ''));
                });
                
                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                
                // Get the content type header
                const contentType = response.headers.get('content-type');
                console.log(`[${requestId}] Content-Type:`, contentType);
                
                // Check for valid JSON content type
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    throw new Error(`Expected JSON response but got ${contentType || 'unknown content type'}`);
                }
            })
            .then(data => {
                console.log(`[${requestId}] Parsed data:`, data);
                if (data.success) {
                    console.log(`[${requestId}] Availability updated successfully`);
                    
                    // Update the available slots count in the stats card if exists
                    const statCards = document.querySelectorAll('.stat-card-value');
                    if (statCards && statCards.length >= 3) {
                        const availableSlotsElement = statCards[2];
                        let currentCount = parseInt(availableSlotsElement.textContent);
                        currentCount = isAvailable ? currentCount + 1 : currentCount - 1;
                        currentCount = Math.max(0, currentCount);
                        availableSlotsElement.textContent = currentCount;
                    }
                } else {
                    console.error(`[${requestId}] Error updating availability:`, data.message);
                    alert('Failed to update availability: ' + (data.message || 'Unknown error'));
                    // Revert changes if update failed
                    checkbox.checked = originalState;
                    statusElement.textContent = checkbox.checked ? 'Available' : 'Not Available';
                }
            })
            .catch(error => {
                console.error(`[${requestId}] Error:`, error.message);
                alert('An error occurred while updating availability: ' + error.message);
                // Revert changes on error
                checkbox.checked = originalState;
                statusElement.textContent = checkbox.checked ? 'Available' : 'Not Available';
            })
            .finally(() => {
                // Re-enable the checkbox
                checkbox.disabled = false;
            });
        }

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.user-avatar')) {
                const dropdown = document.getElementById('profileDropdown');
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        }

        // Initialize the dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Start with dashboard overview
            showDashboardOverview();
        });

        // Add this to your existing JavaScript
        function handleBooking(bookingId, action) {
            let message = '';
            
            if (action === 'accept' || action === 'reject') {
                message = prompt(action === 'accept' 
                    ? 'Add any notes for the patient (optional):' 
                    : 'Please provide a reason for rejecting this booking:');
                
                if (action === 'reject' && !message) {
                    alert('You must provide a reason for rejection.');
                    return;
                }
            }
            
            // Send the request to the server
            fetch('adv_dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=${action}&booking_id=${bookingId}&message=${encodeURIComponent(message || '')}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Booking ${action}ed successfully!`);
                    // Refresh just the bookings data instead of reloading the page
                    refreshBookings();
                } else {
                    alert(`Failed to ${action} booking: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(`An error occurred while trying to ${action} the booking.`);
            });
        }

        // Add function to show profile section
        function showProfileSection() {
            // Hide all sections
            document.getElementById('dashboardOverview').style.display = 'none';
            document.getElementById('availabilityManager').style.display = 'none';
            document.getElementById('profileSection').style.display = 'block';
            
            // Update active class (optional, if you want to highlight a menu item)
            const links = document.querySelectorAll('.sidebar-link');
            links.forEach(link => link.classList.remove('active'));
            
            // Close dropdown
            document.getElementById('profileDropdown').classList.remove('show');
        }

        // Add event listener for profile form submission
        document.addEventListener('DOMContentLoaded', function() {
            // ... existing code ...
            
            // Add form submission handler
            const profileForm = document.getElementById('advisorProfileForm');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(profileForm);
                    
                    fetch('update_advisor_profile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        const messageContainer = document.getElementById('profileUpdateMessage');
                        if (data.success) {
                            messageContainer.innerHTML = '<div class="success-message">' + data.message + '</div>';
                        } else {
                            messageContainer.innerHTML = '<div class="error-message">' + data.message + '</div>';
                        }
                        
                        // Scroll to the message
                        messageContainer.scrollIntoView();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('profileUpdateMessage').innerHTML = 
                            '<div class="error-message">An error occurred while updating your profile. Please try again.</div>';
                    });
                });
            }
        });

        // Function to check for admin-disabled slots
        function checkAdminDisabledSlots() {
            fetch('check_admin_disabled_slots.php')
                .then(response => response.json())
                .then(data => {
                    if (data.hasDisabledSlots) {
                        const disabledSlots = data.disabledSlots;
                        const notificationWrapper = document.getElementById('adminNotificationsWrapper');
                        const notificationsList = document.getElementById('adminNotificationsList');
                        const notificationsCount = document.getElementById('adminNotificationsCount');
                        const notificationsBadge = document.getElementById('adminNotificationsBadge');
                        
                        // Update notification count
                        notificationsCount.textContent = `(${disabledSlots.length})`;
                        notificationsBadge.textContent = disabledSlots.length;
                        notificationsBadge.style.display = 'block';
                        
                        // Clear previous notifications
                        notificationsList.innerHTML = '';
                        
                        // Create notifications for each disabled slot
                        disabledSlots.forEach(slot => {
                            const notification = document.createElement('div');
                            notification.className = 'admin-notification-item fade-in';
                            notification.innerHTML = `
                                <strong>Schedule Disabled:</strong><br>
                                The slot on <strong>${slot.day}</strong> from <strong>${slot.start_time}</strong> to <strong>${slot.end_time}</strong> 
                                has been disabled by an administrator.
                            `;
                            notificationsList.appendChild(notification);
                            
                            // Find and mark the corresponding slot in the UI
                            const scheduleItems = document.querySelectorAll('.schedule-item');
                            scheduleItems.forEach(item => {
                                const scheduleId = item.querySelector('input[data-schedule-id]')?.dataset.scheduleId;
                                if (scheduleId && scheduleId == slot.id) {
                                    item.classList.add('admin-disabled');
                                }
                            });
                        });
                        
                        // Show the notifications container
                        notificationWrapper.style.display = 'block';
                        
                        // Auto-open notifications if this is the first time or if preference is set
                        const notificationsOpen = localStorage.getItem('adminNotificationsOpen');
                        if (notificationsOpen === 'true' || notificationsOpen === null) {
                            toggleNotifications(true);
                        }
                    } else {
                        // No disabled slots, hide the notifications
                        document.getElementById('adminNotificationsWrapper').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error checking for admin disabled slots:', error);
                });
        }
        
        // Function to toggle notifications
        function toggleNotifications(forceOpen = null) {
            const content = document.getElementById('adminNotificationsContent');
            const button = document.getElementById('toggleNotificationsBtn');
            const notificationsBadge = document.getElementById('adminNotificationsBadge');
            
            // Determine if we should open or close
            let shouldOpen = forceOpen !== null ? forceOpen : content.style.display === 'none';
            
            if (shouldOpen) {
                // Open notifications
                content.style.display = 'block';
                setTimeout(() => {
                    content.classList.add('open');
                }, 10); // Small delay for transition to work
                button.classList.add('open');
                notificationsBadge.style.display = 'none';
                localStorage.setItem('adminNotificationsOpen', 'true');
            } else {
                // Close notifications
                content.classList.remove('open');
                setTimeout(() => {
                    content.style.display = 'none';
                }, 300); // Match transition duration
                button.classList.remove('open');
                notificationsBadge.style.display = 'block';
                localStorage.setItem('adminNotificationsOpen', 'false');
            }
        }
        
        // Add event listeners when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // ... existing code ...
            
            // Add toggle notifications event listener
            const notificationsHeader = document.getElementById('adminNotificationsHeader');
            if (notificationsHeader) {
                notificationsHeader.addEventListener('click', function() {
                    toggleNotifications();
                });
            }
        });

        $(document).ready(function() {
            // Check for admin notifications
            checkAdminNotifications();
            
            // Add notification toggle functionality
            $('#adminNotificationsHeader, #toggleNotificationsBtn').on('click', function(e) {
                toggleAdminNotifications();
            });
            
            function toggleAdminNotifications() {
                const content = $('#adminNotificationsContent');
                const button = $('#toggleNotificationsBtn');
                
                if (content.hasClass('open')) {
                    content.removeClass('open');
                    button.removeClass('open');
                } else {
                    content.addClass('open');
                    button.addClass('open');
                    $('#adminNotificationsBadge').hide(); // Hide the badge when opened
                }
            }
            
            function checkAdminNotifications() {
                // This would normally fetch notifications from the server
                // For now, just show the static notifications
                $('#adminNotificationsWrapper').show();
                
                // Sample code to fetch real notifications:
                /*
                $.ajax({
                    url: 'advisor_ajax_handler.php',
                    type: 'POST',
                    data: {
                        action: 'get_admin_notifications'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.notifications.length > 0) {
                            // Update notification count
                            $('#adminNotificationsBadge').text(response.notifications.length);
                            
                            // Clear existing notifications
                            $('#adminNotificationsContent').empty();
                            
                            // Add new notifications
                            response.notifications.forEach(notification => {
                                // Add notification HTML
                            });
                            
                            // Show the notifications wrapper
                            $('#adminNotificationsWrapper').show();
                        }
                    }
                });
                */
            }
        });

        $(document).ready(function() {
            // Set up test booking data
            const testBookingId = <?php echo isset($testResult['bookingSample']['booking_id']) ? $testResult['bookingSample']['booking_id'] : 1; ?>;
            const testUserEmail = <?php echo isset($testResult['bookingSample']['user_email']) ? "'".$testResult['bookingSample']['user_email']."'" : "'test_user@example.com'"; ?>;
            const testUserName = 'Test Patient';
            
            // Open test modal
            $('#openTestModal').click(function() {
                // Set form data
                $('#modal-booking-id').val(testBookingId);
                $('#patient-name').text(testUserName);
                $('#patient-email').text(testUserEmail);
                
                // Clear form
                $('#symptoms, #diagnosis, #medication, #further-procedure').val('');
                $('input[name="status"]').prop('checked', false).parent().removeClass('active');
                
                // Fetch existing notes if any
                $.ajax({
                    url: 'advisor_ajax_handler.php',
                    type: 'POST',
                    data: {
                        action: 'get_medical_notes',
                        booking_id: testBookingId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            // Fill form with existing data
                            $('#symptoms').val(response.data.symptoms || '');
                            $('#diagnosis').val(response.data.diagnosis || '');
                            $('#medication').val(response.data.medication || '');
                            $('#further-procedure').val(response.data.further_procedure || '');
                            
                            // Set status radio button
                            if (response.data.status) {
                                $(`input[name="status"][value="${response.data.status}"]`)
                                    .prop('checked', true)
                                    .parent().addClass('active');
                            }
                        }
                        
                        // Show the modal
                        $('#medicalNotesModal').modal('show');
                    },
                    error: function() {
                        // Just show the modal
                        $('#medicalNotesModal').modal('show');
                    }
                });
            });
            
            // Handle saving medical notes
            $('#saveMedicalNotes').on('click', function() {
                // Show loading state
                const $btn = $(this);
                const originalHtml = $btn.html();
                
                // Basic validation
                if (!$('#symptoms').val() || !$('#diagnosis').val()) {
                    alert('Please complete all fields');
                    return;
                }
                
                // Show loading
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                $btn.prop('disabled', true);
                
                // Get form data
                const formData = $('#medicalNotesForm').serialize();
                
                // Send AJAX request
                $.ajax({
                    url: 'advisor_ajax_handler.php',
                    type: 'POST',
                    data: formData + '&action=save_medical_notes',
                    dataType: 'json',
                    success: function(response) {
                        $btn.html(originalHtml);
                        $btn.prop('disabled', false);
                        
                        if (response.success) {
                            // Show success message
                            const successAlert = `
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle mr-2"></i> Medical notes saved successfully!
                                    <button type="button" class="ml-2 mb-1 close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            `;
                            
                            // Add toast to DOM
                            if ($('#toastContainer').length === 0) {
                                $('body').append('<div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>');
                            }
                            
                            $('#toastContainer').append(toast);
                            $('.toast').toast('show');
                            
                            // Close modal after a short delay and refresh page
                            setTimeout(function() {
                                $('#medicalNotesModal').modal('hide');
                                
                                // Show confirmation on the page
                                const pageAlert = `
                                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                                        <i class="fas fa-check-circle mr-2"></i> Medical notes saved successfully!
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                `;
                                $('.test-section:last').append(pageAlert);
                            }, 1500);
                        } else {
                            alert('Error: ' + (response.message || 'Failed to save medical notes'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $btn.html(originalHtml);
                        $btn.prop('disabled', false);
                        
                        console.error('Error saving medical notes:', error);
                        alert('Error saving medical notes. Please try again.');
                    }
                });
            });
        });

        $(document).ready(function() {
            // Only initialize DataTable if there are rows
            if ($('#bookingsTable tbody tr').length > 0) {
                $('#bookingsTable').DataTable({
                    order: [[2, 'desc']], // Sort by date descending
                    responsive: true,
                    pageLength: 10,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search bookings..."
                    }
                });
            }
            
            // User email link and view booking handler
            $('.user-email-link, .view-booking').on('click', function(e) {
                e.preventDefault();
                
                const bookingId = $(this).data('booking-id');
                let userEmail, userName;
                
                if ($(this).hasClass('user-email-link')) {
                    userEmail = $(this).data('user-email');
                    userName = $(this).data('user-name');
                } else {
                    // If view button was clicked, get the data from the same row
                    const row = $(this).closest('tr');
                    userEmail = row.find('.user-email-link').data('user-email');
                    userName = row.find('.user-email-link').data('user-name');
                }
                
                // Validate required data
                if (!bookingId || !userEmail) {
                    console.error('Missing required data:', { bookingId, userEmail });
                    alert('Error: Missing booking information');
                    return;
                }
                
                console.log('Opening medical notes for:', { bookingId, userEmail, userName });
                
                // Set modal values
                $('#modal-booking-id').val(bookingId);
                $('#patient-email').text(userEmail);
                $('#patient-name').text(userName || userEmail.split('@')[0]);
                
                // Clear previous form data
                $('#symptoms, #diagnosis, #medication, #further-procedure').val('');
                $('input[name="status"]').prop('checked', false).parent().removeClass('active');
                
                // Remove any existing loading indicators or alerts
                $('#loading-indicator, .alert').remove();
                
                // Add loading indicator
                $('.modal-body').prepend(`
                    <div id="loading-indicator" class="text-center my-3">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-2">Loading medical data...</p>
                    </div>
                `);
                
                // Show the modal immediately to improve perceived performance
                $('#medicalNotesModal').modal('show');
                
                // Fetch existing medical notes
                $.ajax({
                    url: 'advisor_ajax_handler.php',
                    type: 'POST',
                    data: {
                        action: 'get_medical_notes',
                        booking_id: bookingId
                    },
                    dataType: 'json',
                    success: function(response) {
                        // Remove loading indicator
                        $('#loading-indicator').remove();
                        
                        if (response.success && response.data) {
                            // Fill form with existing data
                            $('#symptoms').val(response.data.symptoms || '');
                            $('#diagnosis').val(response.data.diagnosis || '');
                            $('#medication').val(response.data.medication || '');
                            $('#further-procedure').val(response.data.further_procedure || '');
                            
                            // Set status radio button
                            if (response.data.status) {
                                $(`input[name="status"][value="${response.data.status}"]`)
                                    .prop('checked', true)
                                    .parent().addClass('active');
                            }
                        }
                        
                        // Show the modal
                        $('#medicalNotesModal').modal('show');
                    },
                    error: function(xhr, status, error) {
                        // Remove loading indicator
                        $('#loading-indicator').remove();
                        console.error('Error fetching medical notes:', error);
                        
                        // Add error message
                        $('.modal-body').prepend(`
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle mr-2"></i> Could not load existing data. Creating new record.
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        `);
                    }
                });
            });
        });

        $(document).ready(function() {
            // Handle refreshing schedule notifications
            $('#refreshScheduleNotifications').on('click', function() {
                const $btn = $(this);
                const originalHtml = $btn.html();
                
                // Show loading state
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Refreshing...');
                $btn.prop('disabled', true);
                
                // Simulate loading (replace with actual AJAX call)
                setTimeout(function() {
                    // Reset button
                    $btn.html(originalHtml);
                    $btn.prop('disabled', false);
                    
                    // Show success message
                    const toast = `
                        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3000">
                            <div class="toast-header">
                                <i class="fas fa-check-circle text-success mr-2"></i>
                                <strong class="mr-auto">Success</strong>
                                <small>Just now</small>
                                <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="toast-body">
                                Schedule notifications refreshed successfully!
                            </div>
                        </div>
                    `;
                    
                    // Add toast to DOM
                    if ($('#toastContainer').length === 0) {
                        $('body').append('<div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>');
                    }
                    
                    $('#toastContainer').append(toast);
                    $('.toast').toast('show');
                    
                    // Show confirmation on the page
                    const pageAlert = `
                        <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                            <i class="fas fa-check-circle mr-2"></i> Schedule notifications refreshed successfully!
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    `;
                    $('.test-section:last').append(pageAlert);
                }, 1000);
            });
            
            // Handle action buttons in notifications
            $('.view-schedule').on('click', function() {
                const day = $(this).data('day');
                const time = $(this).data('time');
                
                // Scroll to schedule section
                $('html, body').animate({
                    scrollTop: $('#scheduleSection').offset().top - 20
                }, 500);
                
                // Highlight the relevant schedule item (you'll need to adapt this to your schedule structure)
                $('.schedule-item').removeClass('highlight');
                $(`.schedule-item[data-day="${day}"][data-time="${time}"]`).addClass('highlight').addClass('pulse-animation');
            });
            
            // Handle pending bookings button
            $('.view-pending-bookings').on('click', function() {
                // Redirect to bookings tab or open bookings modal
                $('#bookingsTab').tab('show');
                
                // Filter bookings to pending if you have that functionality
                $('.filter-btn[data-filter="pending"]').click();
            });
        });

        $(document).ready(function() {
            // Initialize DataTables with proper checking to prevent reinitialisation error
            if ($.fn.DataTable.isDataTable('#bookingsTable')) {
                // Destroy the existing DataTable instance first
                $('#bookingsTable').DataTable().destroy();
            }
            
            // Then initialize a new DataTable
            $('#bookingsTable').DataTable({
                responsive: true,
                ordering: true,
                searching: true,
                paging: true,
                lengthMenu: [5, 10, 25, 50],
                pageLength: 5,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search bookings...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ bookings",
                    infoEmpty: "Showing 0 to 0 of 0 bookings",
                    infoFiltered: "(filtered from _MAX_ total bookings)"
                },
                columnDefs: [
                    { orderable: false, targets: -1 } // Disable sorting on the actions column
                ],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control');
                    $('.dataTables_length select').addClass('form-control');
                }
            });
        });

        $(document).ready(function() {
            // Add this JavaScript for the schedule section notifications
            $('.toggle-schedule-notifications').on('click', function() {
                const $icon = $(this).find('i');
                const $notifications = $(this).closest('.schedule-admin-notifications').find('.schedule-notifications');
                
                if ($notifications.is(':visible')) {
                    $notifications.slideUp(300);
                    $icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    $(this).addClass('collapsed');
                } else {
                    $notifications.slideDown(300);
                    $icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                    $(this).removeClass('collapsed');
                }
            });
            
            // Handle the review schedule button
            $('.view-schedule').on('click', function() {
                const day = $(this).data('day');
                const time = $(this).data('time');
                
                // Find the corresponding schedule item
                const $scheduleItem = $(`.schedule-item[data-day="${day}"][data-time="${time}"]`);
                
                if ($scheduleItem.length) {
                    // Scroll to the item
                    $('html, body').animate({
                        scrollTop: $scheduleItem.offset().top - 100
                    }, 500);
                    
                    // Highlight the item
                    $scheduleItem.addClass('highlight pulse-animation');
                    
                    // Remove highlight after a few seconds
                    setTimeout(function() {
                        $scheduleItem.removeClass('highlight pulse-animation');
                    }, 3000);
                }
            });
        });

        $(document).ready(function() {
            // Add this JavaScript for the availability management
            $('.availability-grid-cell').on('click', function() {
                const $cell = $(this);
                const scheduleId = $cell.data('schedule-id');
                const day = $cell.data('day');
                const time = $cell.data('time');
                
                // If we have a schedule ID, it means this slot exists in the database
                if (scheduleId > 0) {
                    const isCurrentlyAvailable = $cell.hasClass('available');
                    toggleAvailability(scheduleId, !isCurrentlyAvailable, $cell);
                } else {
                    // This is a new slot, fill the add form and show modal
                    $('#dayOfWeek').val(day);
                    $('#startTime').val(convertTimeFormat(time));
                    $('#isAvailable').prop('checked', true);
                    $('#addTimeSlotModal').modal('show');
                }
            });
            
            // Handle adding a new time slot
            $('#addTimeSlotBtn').on('click', function() {
                $('#addTimeSlotModal').modal('show');
            });
            
            // Save new time slot
            $('#saveTimeSlotBtn').on('click', function() {
                const $btn = $(this);
                const originalText = $btn.html();
                
                // Basic validation
                if (!$('#dayOfWeek').val() || !$('#startTime').val()) {
                    alert('Please complete all fields');
                    return;
                }
                
                // Show loading
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                $btn.prop('disabled', true);
                
                // Get form data
                const dayOfWeek = $('#dayOfWeek').val();
                const startTime = $('#startTime').val();
                const isAvailable = $('#isAvailable').is(':checked') ? 1 : 0;
                
                // Send AJAX request
                $.ajax({
                    url: 'advisor_ajax_handler.php',
                    type: 'POST',
                    data: {
                        action: 'add_time_slot',
                        day_of_week: dayOfWeek,
                        start_time: startTime,
                        is_available: isAvailable
                    },
                    dataType: 'json',
                    success: function(response) {
                        $btn.html(originalText);
                        $btn.prop('disabled', false);
                        
                        if (response.success) {
                            // Close modal and reload page to show new slot
                            $('#addTimeSlotModal').modal('hide');
                            
                            // Show success message
                            const toast = `
                                <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3000">
                                    <div class="toast-header">
                                        <i class="fas fa-check-circle text-success mr-2"></i>
                                        <strong class="mr-auto">Success</strong>
                                        <small>Just now</small>
                                        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="toast-body">
                                        New time slot added successfully!
                                    </div>
                                </div>
                            `;
                            
                            // Add toast to DOM
                            if ($('#toastContainer').length === 0) {
                                $('body').append('<div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>');
                            }
                            
                            $('#toastContainer').append(toast);
                            $('.toast').toast('show');
                            
                            // Reload page after a short delay
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            alert('Error: ' + (response.message || 'Failed to add time slot'));
                        }
                    },
                    error: function() {
                        $btn.html(originalText);
                        $btn.prop('disabled', false);
                        alert('Server error. Please try again.');
                    }
                });
            });
            
            // Handle week navigation
            $('#prevWeekBtn, #nextWeekBtn').on('click', function() {
                // In a real app, this would navigate to different weeks
                // For now, just show a message
                alert('Week navigation would be implemented here with actual date ranges');
            });
            
            // Helper function to toggle availability
            function toggleAvailability(scheduleId, makeAvailable, $cell) {
                // Add loading indicator
                $cell.addClass('loading');
                
                // Send AJAX request to update availability
                $.ajax({
                    url: 'advisor_ajax_handler.php',
                    type: 'POST',
                    data: {
                        action: 'update_availability',
                        schedule_id: scheduleId,
                        is_available: makeAvailable ? 1 : 0
                    },
                    dataType: 'json',
                    success: function(response) {
                        $cell.removeClass('loading');
                        
                        if (response.success) {
                            // Update UI
                            if (makeAvailable) {
                                $cell.addClass('available');
                                $cell.html("<div class='availability-status'><i class='fas fa-check'></i></div>");
                            } else {
                                $cell.removeClass('available');
                                $cell.empty();
                            }
                        } else {
                            alert('Error: ' + (response.message || 'Failed to update availability'));
                        }
                    },
                    error: function() {
                        $cell.removeClass('loading');
                        alert('Server error. Please try again.');
                    }
                });
            }
            
            // Helper function to convert time format
            function convertTimeFormat(time12h) {
                // Convert "09:00 AM" to "09:00:00"
                const [time, modifier] = time12h.split(' ');
                let [hours, minutes] = time.split(':');
                
                if (hours === '12') {
                    hours = '00';
                }
                
                if (modifier === 'PM') {
                    hours = parseInt(hours, 10) + 12;
                }
                
                return `${hours}:${minutes}:00`;
            }
        });

        $(document).ready(function() {
            // Initialize DataTables with proper checking to prevent reinitialisation error
            if ($.fn.DataTable.isDataTable('#bookingsTable')) {
                // Destroy the existing DataTable instance first
                $('#bookingsTable').DataTable().destroy();
            }
            
            // Then initialize a new DataTable
            $('#bookingsTable').DataTable({
                responsive: true,
                ordering: true,
                searching: true,
                paging: true,
                lengthMenu: [5, 10, 25, 50],
                pageLength: 5,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search bookings...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ bookings",
                    infoEmpty: "Showing 0 to 0 of 0 bookings",
                    infoFiltered: "(filtered from _MAX_ total bookings)"
                },
                columnDefs: [
                    { orderable: false, targets: -1 } // Disable sorting on the actions column
                ],
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control');
                    $('.dataTables_length select').addClass('form-control');
                }
            });
        });

        function showBookings() {
            document.querySelectorAll('.content-section').forEach(section => {
                section.style.display = 'none';
            });
            document.getElementById('bookingsSection').style.display = 'block';
            updateActiveLink('showBookings()');
        }

        // Add filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const status = this.dataset.status;
                
                // Update active state of filter buttons
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Filter the table rows
                const table = $('#bookingsTable').DataTable();
                if (status === 'all') {
                    table.column(5).search('').draw();
                } else {
                    table.column(5).search(status).draw();
                }
            });
        });

        function viewBookingDetails(bookingId) {
            // Fetch booking details
            fetch(`get_booking_details.php?id=${bookingId}`)
                .then(response => response.json())
                .then(booking => {
                    // Get the modal element
                    const modal = document.getElementById('bookingDetailsModal');
                    const contentContainer = document.getElementById('bookingDetailsContent');
                    
                    // Format status with correct class
                    let statusClass = '';
                    switch(booking.status.toLowerCase()) {
                        case 'pending': statusClass = 'status-pending'; break;
                        case 'confirmed': statusClass = 'status-confirmed'; break;
                        case 'completed': statusClass = 'status-completed'; break;
                        case 'cancelled': statusClass = 'status-cancelled'; break;
                        default: statusClass = '';
                    }
                    
                    // Build HTML content for the modal
                    const content = `
                        <div class="booking-details">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Patient Information</h5>
                                    <p><strong>Name:</strong> ${booking.user_name}</p>
                                    <p><strong>Email:</strong> ${booking.user_email}</p>
                                    <p><strong>Service:</strong> ${booking.service_type}</p>
                                </div>
                                <div class="col-md-6">
                                    <h5>Appointment Details</h5>
                                    <p><strong>Date:</strong> ${booking.booking_date}</p>
                                    <p><strong>Time:</strong> ${booking.booking_time}</p>
                                    <p><strong>Status:</strong> <span class="badge ${statusClass}">${booking.status}</span></p>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h5>Provider Information</h5>
                                    <p><strong>Provider:</strong> ${booking.provider_name || 'Not assigned'}</p>
                                    <p><strong>Provider Email:</strong> ${booking.provider_email || 'N/A'}</p>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <button class="btn btn-primary" onclick="openMedicalNotes(${booking.id}, '${booking.user_email}', '${booking.user_name}')">
                                        <i class="fas fa-notes-medical"></i> View/Edit Medical Notes
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    contentContainer.innerHTML = content;
                    $(modal).modal('show');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading booking details');
                });
        }

        function openMedicalNotes(bookingId, userEmail, userName) {
            // Hide the booking details modal
            $('#bookingDetailsModal').modal('hide');
            
            // Set form data
            $('#modal-booking-id').val(bookingId);
            $('#patient-name').text(userName || userEmail.split('@')[0]);
            $('#patient-email').text(userEmail);
            
            // Clear form
            $('#symptoms, #diagnosis, #medication, #further-procedure').val('');
            $('input[name="status"]').prop('checked', false).parent().removeClass('active');
            
            // Fetch existing notes
            $.ajax({
                url: 'advisor_ajax_handler.php',
                type: 'POST',
                data: {
                    action: 'get_medical_notes',
                    booking_id: bookingId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        // Fill form with existing data
                        $('#symptoms').val(response.data.symptoms || '');
                        $('#diagnosis').val(response.data.diagnosis || '');
                        $('#medication').val(response.data.medication || '');
                        $('#further-procedure').val(response.data.further_procedure || '');
                        
                        // Set status radio button
                        if (response.data.status) {
                            $(`input[name="status"][value="${response.data.status}"]`)
                                .prop('checked', true)
                                .parent().addClass('active');
                        }
                    }
                    
                    // Show the modal
                    $('#medicalNotesModal').modal('show');
                },
                error: function() {
                    // Just show the modal
                    $('#medicalNotesModal').modal('show');
                }
            });
        }

        // Add this to your existing document.ready function
        $(document).ready(function() {
            // Handle user name clicks in bookings table
            $(document).on('click', '.user-name-link', function(e) {
                e.preventDefault();
                const bookingId = $(this).data('booking-id');
                const userEmail = $(this).data('user-email');
                const userName = $(this).data('user-name');
                openMedicalNotes(bookingId, userEmail, userName);
            });
        });

        function updateActiveLink(functionName) {
            document.querySelectorAll('.sidebar-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`.sidebar-link[onclick="${functionName}"]`).classList.add('active');
        }
    </script>
</body>
</html>



