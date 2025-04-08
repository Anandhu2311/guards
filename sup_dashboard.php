<?php
// TEMPORARILY ENABLE ERROR DISPLAY FOR DEBUGGING
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', 'supporter_dashboard.log');

// Ensure session is started first thing
session_start();

// Include database connection file
require_once 'DBS.inc.php';

// Add this function at the top, before it's used
function getBookings($pdo, $email, $userType = 'client') {
    try {
        if ($userType == 'provider') {
            // Query for supporters (providers)
            $sql = "SELECT 
                    b.booking_id,
                    b.user_email,
                    b.provider_email,
                    b.service_type,
                    b.booking_date,
                    b.status,
                    b.notes,
                    s.day, 
                    s.start_time, 
                    s.end_time,
                    u.name as patient_name,
                    u.email as patient_email
                FROM bookings b
                LEFT JOIN schedules s ON b.schedule_id = s.id
                LEFT JOIN users u ON b.user_email = u.email
                WHERE b.provider_email = :email 
                ORDER BY 
                    CASE b.status
                        WHEN 'pending' THEN 1
                        WHEN 'confirmed' THEN 2
                        WHEN 'follow_up' THEN 3
                        WHEN 'completed' THEN 4
                        WHEN 'follow_up_completed' THEN 5
                        ELSE 6
                    END,
                    b.booking_date DESC";
        } else {
            // Query for clients
            $sql = "SELECT 
                    b.booking_id,
                    b.user_email,
                    b.provider_email,
                    b.service_type,
                    b.booking_date,
                    b.status,
                    b.notes,
                    s.day, 
                    s.start_time, 
                    s.end_time,
                    u.name as provider_name,
                    u.email as provider_email
                FROM bookings b
                LEFT JOIN schedules s ON b.schedule_id = s.id
                LEFT JOIN users u ON b.provider_email = u.email
                WHERE b.user_email = :email
                ORDER BY b.booking_date DESC";
        }
        
        error_log("Executing booking query for email: $email, userType: $userType");
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Found " . count($bookings) . " bookings");
        
        // Debug output
        error_log("SQL Query: " . $sql);
        error_log("Email parameter: " . $email);
        error_log("User type: " . $userType);
        error_log("Results: " . print_r($bookings, true));
        
        return $bookings;
    } catch (PDOException $e) {
        error_log("Error retrieving bookings: " . $e->getMessage());
        return [];
    }
}

// Add helper function for status badge classes
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'pending':
            return 'warning';
        case 'confirmed':
            return 'success';
        case 'completed':
            return 'info';
        case 'cancelled':
            return 'danger';
        case 'follow_up':
            return 'primary';
        case 'follow_up_completed':
            return 'secondary';
        default:
            return 'secondary';
    }
}

// Get supporter email from session 
$supporter_email = $_SESSION['email'] ?? '';

// Debug log to check supporter email
error_log("Supporter email from session: " . $supporter_email);

// Get supporter's bookings
$supporterBookings = getBookings($pdo, $supporter_email, 'provider');

// Debug log to check number of bookings
error_log("Number of bookings found: " . count($supporterBookings));

// Handle POST requests IMMEDIATELY
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['email']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 4) {
        error_log("Unauthorized access attempt - Session: " . print_r($_SESSION, true));
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }
    
    if (!isset($_POST['schedule_id']) || !isset($_POST['is_available'])) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit();
    }
    
    try {
        $scheduleId = intval($_POST['schedule_id']);
        $isAvailable = intval($_POST['is_available']);
        $supporter_email = $_SESSION['email'];
        
        error_log("Attempting availability update - Email: $supporter_email, Schedule: $scheduleId, Available: $isAvailable");
        echo simpleAvailabilityUpdate($pdo, $supporter_email, $scheduleId, $isAvailable);
        exit();
    } catch (Exception $e) {
        error_log("Error in availability update: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Debug output
ini_set('display_errors', 1);
error_reporting(E_ALL);
error_log("=== SUP_DASHBOARD.PHP START ===");
error_log("Session ID: " . session_id());
error_log("Session Data: " . print_r($_SESSION, true));
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("=== SUP_DASHBOARD.PHP END ===");

// Debug log current session state
error_log("Current session data: " . print_r($_SESSION, true));

// Check authentication with more detailed logging
if (!isset($_SESSION['email']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 4) {
    error_log("Authentication failed - Details:");
    error_log("Email set: " . (isset($_SESSION['email']) ? 'yes' : 'no'));
    error_log("Role ID set: " . (isset($_SESSION['role_id']) ? 'yes' : 'no'));
    error_log("Role ID value: " . ($_SESSION['role_id'] ?? 'not set'));
    error_log("Full session: " . print_r($_SESSION, true));
    
    session_write_close(); // Ensure session is properly closed
    header("Location: signin.php?error=auth&reason=invalid_session");
    exit();
}

// Get supporter email from session BEFORE using it
$supporter_email = $_SESSION['email'];

// Verify supporter exists and is active
try {
    $stmt = $pdo->prepare("SELECT * FROM supporters WHERE sup_email = :email");
    $stmt->bindParam(':email', $supporter_email, PDO::PARAM_STR);
    $stmt->execute();
    $supporter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supporter) {
        error_log("Supporter not found or inactive: " . $supporter_email);
        session_destroy();
        header("Location: signin.php?error=invalid_account");
        exit();
    }
    
    // Refresh session variables
    $_SESSION['user_id'] = $supporter['sup_id'];
    $_SESSION['sup_id'] = $supporter['sup_id'];
    $_SESSION['email'] = $supporter['sup_email'];
    $_SESSION['user_email'] = $supporter['sup_email'];
    $_SESSION['role_id'] = 4;
    $_SESSION['role'] = 'supporter';
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header("Location: signin.php?error=database");
    exit();
}

// Debug session data to log
error_log('Session data in supporter dashboard: ' . print_r($_SESSION, true));

function simpleAvailabilityUpdate($conn, $email, $scheduleId, $isAvailable) {
    try {
        // Get supporter name
        $stmt = $conn->prepare("SELECT sup_name as staff_name FROM supporters WHERE sup_email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff) {
            throw new Exception('Supporter not found or inactive');
        }

        // Get schedule details
        $scheduleStmt = $conn->prepare("SELECT * FROM schedules WHERE id = ? AND is_active = 1");
        $scheduleStmt->execute([$scheduleId]);
        $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$schedule) {
            throw new Exception('Schedule not found or inactive');
        }

        // Begin transaction
        $conn->beginTransaction();

        try {
            // Check if record exists
            $checkStmt = $conn->prepare("SELECT id FROM staff_availability WHERE schedule_id = ? AND staff_email = ?");
            $checkStmt->execute([$scheduleId, $email]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                $sql = "UPDATE staff_availability SET is_available = ?, updated_at = CURRENT_TIMESTAMP WHERE schedule_id = ? AND staff_email = ?";
                $stmt = $conn->prepare($sql);
                $success = $stmt->execute([$isAvailable, $scheduleId, $email]);
            } else {
                $sql = "INSERT INTO staff_availability (schedule_id, staff_email, staff_name, role_id, is_available, day, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $success = $stmt->execute([
                    $scheduleId,
                    $email,
                    $staff['staff_name'],
                    $_SESSION['role_id'],
                    $isAvailable,
                    $schedule['day'],
                    $schedule['start_time'],
                    $schedule['end_time']
                ]);
            }

            if ($success) {
                $conn->commit();
                return json_encode(['success' => true]);
            } else {
                throw new Exception('Failed to update availability');
            }
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        error_log('Error in simpleAvailabilityUpdate: ' . $e->getMessage());
        return json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Get counselor email from session 
$supporter_email = $_SESSION['email'];

// Get counselor data from database
$stmt = $pdo->prepare("SELECT * FROM supporters WHERE sup_email = :email");
$stmt->bindParam(':email', $supporter_email, PDO::PARAM_STR);
$stmt->execute();
$counselor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$counselor) {
    error_log('Supporter not found in database: ' . $supporter_email);
    session_destroy();
    header("Location: signin.php");
    exit();
}

// Get schedules and availability
$supporterSchedules = getSupporterSchedules($pdo);
$userAvailability = getSupporterAvailability($pdo, $supporter_email);

// Get supporter's bookings with proper role
$supporterBookings = getBookings($pdo, $supporter_email, 'provider');

function getSupporterSchedules($pdo) {
    try {
        $sql = "SELECT * FROM schedules WHERE is_active = 1 
                ORDER BY CASE 
                    WHEN day = 'Monday' THEN 1 
                    WHEN day = 'Tuesday' THEN 2 
                    WHEN day = 'Wednesday' THEN 3 
                    WHEN day = 'Thursday' THEN 4 
                    WHEN day = 'Friday' THEN 5 
                    WHEN day = 'Saturday' THEN 6 
                    WHEN day = 'Sunday' THEN 7 
                END, start_time";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting supporter schedules: ' . $e->getMessage());
        return [];
    }
}

function getSupporterAvailability($pdo, $email) {
    try {
        $sql = "SELECT sa.*, s.day, s.start_time, s.end_time 
                FROM staff_availability sa
                JOIN schedules s ON sa.schedule_id = s.id
                WHERE sa.staff_email = :email AND sa.role_id = :role_id";
                
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':role_id', $_SESSION['role_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $availability = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $availability[$row['schedule_id']] = $row['is_available'];
        }
        return $availability;
    } catch (PDOException $e) {
        error_log('Error getting staff availability: ' . $e->getMessage());
        return [];
    }
}

// Keep the exact same HTML structure as sup_dashboard.php, just update the titles and navigation links
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counselor Dashboard - GuardSphere</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            overflow-x: hidden;
            background-color: #f4f4f4;
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

        /* Profile section styles */
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

        /* Main content styles */
        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 2rem;
            min-height: calc(100vh - 70px);
        }

        /* Stats Grid styles */
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
            margin-bottom: 1rem;
        }

        .stat-card-header h3 {
            font-size: 1.1rem;
            color: #333;
            margin: 0;
        }

        .stat-card-header i {
            font-size: 1.5rem;
            color: #9932CC;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 600;
            color: #9932CC;
            margin: 1rem 0;
        }

        /* Availability Grid styles */
        .availability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .availability-slot {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .slot-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        /* Switch toggle styles */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .switch input {
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
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #9932CC;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* Bookings styles */
        .booking-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .booking-status {
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .booking-card.pending .booking-status {
            background: #fff3cd;
            color: #856404;
        }

        .booking-card.confirmed .booking-status {
            background: #d4edda;
            color: #155724;
        }

        .booking-card.completed .booking-status {
            background: #cce5ff;
            color: #004085;
        }

        .booking-card.rejected .booking-status {
            background: #f8d7da;
            color: #721c24;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .availability-grid {
                grid-template-columns: 1fr;
            }
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
            margin-top: 2rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 12px 14px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 12px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .sidebar-link i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }

        .sidebar-link:hover {
            background: rgba(153, 50, 204, 0.15);
            transform: translateX(5px);
        }

        .sidebar-link.active {
            background: #9932CC;
            color: white;
            box-shadow: 0 4px 10px rgba(153, 50, 204, 0.3);
        }

        /* Profile Form Styles */
        .profile-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 2rem auto;
        }

        .info-group {
            margin-bottom: 1.5rem;
        }

        .info-group label {
            font-weight: 600;
            color: #666;
            display: block;
            margin-bottom: 0.5rem;
        }

        .info-group p {
            color: #333;
            font-size: 1.1rem;
            margin: 0;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-primary {
            background: #9932CC;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: #8B2AA3;
            transform: translateY(-1px);
        }

        /* Add these styles to your existing CSS */
        .profile-form-container {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            max-width: 600px;
            margin: 2rem auto;
        }

        .form-group {
            margin-bottom: 1.8rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.8rem;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #9932CC;
            box-shadow: 0 0 0 3px rgba(153, 50, 204, 0.1);
            outline: none;
            background: white;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2.5rem;
            justify-content: flex-end;
        }

        .btn-secondary,
        .btn-primary {
            padding: 0.8rem 1.8rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-secondary {
            background: #f1f3f5;
            color: #495057;
        }

        .btn-primary {
            background: #9932CC;
            color: white;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }

        .btn-primary:hover {
            background: #8B2AA3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(153, 50, 204, 0.2);
        }

        .schedule-slots {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .schedule-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .schedule-row:last-child {
            border-bottom: none;
        }

        .schedule-info {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .day {
            min-width: 100px;
            font-size: 1.1rem;
        }

        .time {
            color: #666;
        }

        .status-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .status-text {
            font-weight: 500;
            min-width: 80px;
        }

        .text-success {
            color: #28a745;
        }

        .text-danger {
            color: #dc3545;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .content-section {
            display: none;
        }

        #dashboardOverview {
            display: block; /* This will be toggled by JavaScript */
        }

        .schedule-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .schedule-row:last-child {
            border-bottom: none;
        }

        .btn {
            cursor: pointer;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-weight: 500;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .text-success {
            color: #28a745;
        }

        .text-danger {
            color: #dc3545;
        }

        /* Add these styles to your existing CSS */
        .bookings-table-container {
            margin: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bookings-table th,
        .bookings-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .bookings-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .btn-view-notes {
            background: #9932CC;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
        }

        .btn-view-notes:hover {
            background: #8B2AA3;
        }

        .patient-info {
            display: flex;
            flex-direction: column;
        }

        .patient-name {
            font-weight: 500;
            color: #212529;
        }

        .patient-email {
            font-size: 0.85em;
            color: #6c757d;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-accept, .btn-reject {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85em;
            transition: all 0.2s;
        }

        .btn-accept {
            background: #28a745;
            color: white;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-accept:hover, .btn-reject:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Add these styles to your existing CSS */
        .admin-notifications-wrapper {
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            z-index: 1;
        }

        .notification-header-container {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .notification-header-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification-header-content i {
            color: #ffc107;
        }

        .toggle-notifications-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
        }

        .admin-notifications-content {
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
        }

        .admin-notifications-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .admin-notification-item {
            padding: 10px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Medical Form Modal Styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .medical-form-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .medical-form-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            position: relative;
        }

        .medical-form-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .close-modal-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .medical-notes-form {
            padding: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .save-notes-btn, .cancel-btn {
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .save-notes-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
        }

        .cancel-btn {
            background-color: #f44336;
            color: white;
            border: none;
        }

        .status-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 5px;
        }

        .status-option {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            padding: 5px;
        }

        .status-option input[type="radio"] {
            margin: 0;
        }

        /* Status button styling */
        .btn-group-toggle {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-group-toggle label.btn {
            display: flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0;
        }

        .btn-group-toggle label.btn input[type="radio"] {
            display: none;
        }

        .btn-group-toggle label.btn i {
            margin-right: 8px;
        }

        /* Button colors */
        .btn-outline-primary {
            color: #4e73df;
            border: 1px solid #4e73df;
        }

        .btn-outline-primary:hover, .btn-outline-primary.active {
            background-color: #4e73df;
            color: white;
        }

        .btn-outline-info {
            color: #36b9cc;
            border: 1px solid #36b9cc;
        }

        .btn-outline-info:hover, .btn-outline-info.active {
            background-color: #36b9cc;
            color: white;
        }

        .btn-outline-success {
            color: #1cc88a;
            border: 1px solid #1cc88a;
        }

        .btn-outline-success:hover, .btn-outline-success.active {
            background-color: #1cc88a;
            color: white;
        }

        .btn-outline-warning {
            color: #f6c23e;
            border: 1px solid #f6c23e;
        }

        .btn-outline-warning:hover, .btn-outline-warning.active {
            background-color: #f6c23e;
            color: white;
        }

        .status-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 10px;
        }

        .status-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            border-radius: 6px;
            border: 2px solid;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .status-btn i {
            font-size: 1.1em;
        }

        /* Confirmed button */
        .status-btn.confirmed {
            border-color: #4e73df;
            color: #4e73df;
        }

        .status-btn.confirmed:hover,
        .status-btn.confirmed.active {
            background-color: #4e73df;
            color: white;
        }

        /* Follow Up button */
        .status-btn.follow-up {
            border-color: #36b9cc;
            color: #36b9cc;
        }

        .status-btn.follow-up:hover,
        .status-btn.follow-up.active {
            background-color: #36b9cc;
            color: white;
        }

        /* Complete button */
        .status-btn.complete {
            border-color: #1cc88a;
            color: #1cc88a;
        }

        .status-btn.complete:hover,
        .status-btn.complete.active {
            background-color: #1cc88a;
            color: white;
        }

        /* Follow Up & Complete button */
        .status-btn.follow-up-complete {
            border-color: #f6c23e;
            color: #f6c23e;
        }

        .status-btn.follow-up-complete:hover,
        .status-btn.follow-up-complete.active {
            background-color: #f6c23e;
            color: white;
        }

        /* Add box shadow for active state */
        .status-btn.active {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        .profile-menu {
            position: absolute;
            top: 60px;
            right: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: background 0.3s;
        }

        .menu-item:hover {
            background: #f5f5f5;
        }

        .menu-item i {
            margin-right: 10px;
            width: 20px;
        }

        .schedule-container {
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }

        .schedule-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .schedule-info {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .availability-status {
            padding: 5px 10px;
            border-radius: 4px;
        }

        .available {
            background-color: #d4edda;
            color: #155724;
        }

        .unavailable {
            background-color: #f8d7da;
            color: #721c24;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .bookings-table th,
        .bookings-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .bookings-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .bookings-table tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }

        .badge-success {
            background-color: #28a745;
            color: #fff;
        }

        .badge-info {
            background-color: #17a2b8;
            color: #fff;
        }

        .badge-primary {
            background-color: #007bff;
            color: #fff;
        }

        .badge-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        .text-muted {
            color: #6c757d;
            font-size: 0.85em;
        }

        .btn-medical-notes {
            background-color: #9932CC;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-medical-notes:hover {
            background-color: #8B2AA3;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <nav>
                <div class="logo">
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
                </div>
               
                <div class="profile-section">
                    <div class="user-avatar" onclick="toggleDropdown()">
                        <?php 
                            if (isset($supporter['sup_name']) && !empty($supporter['sup_name'])) {
                                echo htmlspecialchars(substr($supporter['sup_name'], 0, 1));
                            } else {
                                echo htmlspecialchars(substr($supporter['sup_email'], 0, 1));
                            }
                        ?>
                    </div>
                    <div class="dropdown-content">
                        <a href="#" onclick="showProfile(); return false;">Profile</a>
                        <a href="logout.php">Sign Out</a>
                    </div>
                </div>
            </nav>
        </header>

        <div class="sidebar">
            <div class="sidebar-content">
                <div class="welcome-text">Welcome Supporter</div>
                <div class="user-email"><?php echo htmlspecialchars($supporter['sup_email']); ?></div>
                
                <ul class="sidebar-menu">
                    <li><a href="#dashboard" class="sidebar-link" onclick="showDashboardOverview(); return false;">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a></li>
                    <li><a href="#availability" class="sidebar-link" onclick="showAvailabilityManager(); return false;">
                        <i class="fas fa-calendar-alt"></i> Manage Availability
                    </a></li>
                    <li><a href="#bookings" class="sidebar-link" onclick="showBookings(); return false;">
                        <i class="fas fa-calendar-check"></i> View Bookings
                    </a></li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <div id="dashboardOverview" class="content-section">
                <h1 class="section-title">Dashboard Overview</h1>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <!-- Completed Sessions -->
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h3>Completed Sessions</h3>
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo count(array_filter($supporterBookings, function($booking) { 
                            return $booking['status'] === 'completed'; 
                        })); ?></div>
                    </div>

                    <!-- Upcoming Sessions -->
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h3>Upcoming Sessions</h3>
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-value"><?php echo count(array_filter($supporterBookings, function($booking) { 
                            return $booking['status'] === 'confirmed'; 
                        })); ?></div>
                    </div>

                    <!-- Available Slots -->
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h3>Available Slots</h3>
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?php echo count(array_filter($userAvailability, function($available) { 
                            return $available === 1; 
                        })); ?></div>
                    </div>
                </div>
            </div>

            <div id="availabilityManager" class="content-section">
                <h1 class="section-title">Manage Availability</h1>
                
                <!-- Add notification panel -->
                <div id="adminNotificationsWrapper" class="admin-notifications-wrapper" style="display: none;">
                    <div id="adminNotificationsBadge" class="notification-badge" style="display: none;">0</div>
                    <div id="adminNotificationsHeader" class="notification-header-container">
                        <div class="notification-header-content">
                            <i class="fas fa-bell"></i> 
                            <span>Admin Notifications</span>
                            <span id="adminNotificationsCount" class="notification-count">(0)</span>
                        </div>
                        <button id="toggleNotificationsBtn" class="toggle-notifications-btn">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div id="adminNotificationsContent" class="admin-notifications-content" style="display: none;">
                        <div id="adminNotificationsList" class="admin-notifications-list"></div>
                    </div>
                </div>
                
                <div class="schedule-container">
                    <div class="schedule-slots">
                    <?php foreach ($supporterSchedules as $schedule): ?>
                        <?php 
                                $scheduleId = $schedule['id'];
                                $isAvailable = isset($userAvailability[$scheduleId]) ? $userAvailability[$scheduleId] : 0;
                        ?>
                            <div class="schedule-row">
                            <div class="schedule-info">
                                    <strong><?php echo htmlspecialchars($schedule['day']); ?></strong>
                                    <span><?php echo htmlspecialchars($schedule['start_time']) . ' - ' . htmlspecialchars($schedule['end_time']); ?></span>
                                </div>
                                <div class="status-section">
                                    <span class="status-text <?php echo $isAvailable ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $isAvailable ? 'Available' : 'Unavailable'; ?>
                                </span>
                                    <button type="button" 
                                        onclick="toggleAvailability(<?php echo $scheduleId; ?>, <?php echo $isAvailable; ?>)"
                                        class="btn <?php echo $isAvailable ? 'btn-danger' : 'btn-success'; ?>">
                                <?php echo $isAvailable ? 'Set Unavailable' : 'Set Available'; ?>
                            </button>
                                </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div id="bookingsSection" class="content-section">
                <h1>Bookings</h1>
                <div class="bookings-table-container">
                    <?php
                    // Debug information
                    echo "<!-- Debug Info: Supporter Email = " . htmlspecialchars($supporter_email) . " -->";
                    echo "<!-- Debug Info: Number of Bookings = " . count($supporterBookings) . " -->";
                    
                    if (empty($supporterBookings)): 
                    ?>
                        <div style="text-align: center; padding: 20px;">
                            <p>No bookings found for email: <?php echo htmlspecialchars($supporter_email); ?></p>
                        </div>
                    <?php else: ?>
                        <table class="bookings-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Service Type</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($supporterBookings as $booking): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d', strtotime($booking['booking_date'])); ?></td>
                                        <td>
                                            <?php 
                                            echo htmlspecialchars($booking['start_time'] ?? 'N/A'); 
                                            echo ' - ';
                                            echo htmlspecialchars($booking['end_time'] ?? 'N/A');
                                            ?>
                                        </td>
                                        <td>
                                            <div class="patient-info">
                                                <span class="patient-name">
                                                    <?php echo htmlspecialchars($booking['patient_name'] ?? 'N/A'); ?>
                                                </span>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($booking['user_email']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['service_type']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo getStatusBadgeClass($booking['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($booking['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($booking['notes'])): ?>
                                                <?php echo htmlspecialchars(substr($booking['notes'], 0, 50)) . '...'; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" 
                                                    onclick="showMedicalForm('<?php echo htmlspecialchars(addslashes($booking['patient_name'] ?? 'N/A')); ?>', 
                                                                '<?php echo htmlspecialchars(addslashes($booking['user_email'])); ?>', 
                                                                <?php echo $booking['booking_id']; ?>)">
                                                <i class="fas fa-notes-medical"></i> Medical Notes
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div id="profileSection" class="content-section" style="display: none;">
                <h1 class="section-title">Supporter Profile</h1>
                
                <!-- View Profile Section -->
                <div id="viewProfile" class="profile-container">
                    <div class="profile-info">
                        <div class="info-group">
                            <label>Full Name</label>
                            <p><?php echo htmlspecialchars($supporter['sup_name'] ?? 'Not set'); ?></p>
                        </div>
                        
                        <div class="info-group">
                            <label>Email</label>
                            <p><?php echo htmlspecialchars($supporter['sup_email']); ?></p>
                        </div>
                        
                        <div class="info-group">
                            <label>Phone Number</label>
                            <p><?php echo htmlspecialchars($supporter['phone_number'] ?? 'Not set'); ?></p>
                        </div>
                        
                        <div class="info-group">
                            <label>Location</label>
                            <p><?php echo htmlspecialchars($supporter['sup_location'] ?? 'Not set'); ?></p>
                        </div>
                        
                        <div class="info-group">
                            <label>Areas of Expertise</label>
                            <p><?php echo htmlspecialchars($supporter['sup_specialization'] ?? 'Not set'); ?></p>
                        </div>
                    </div>
                    
                    <button class="btn-primary" onclick="toggleProfileEdit()">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                </div>

                <!-- Update Profile Form (Initially Hidden) -->
                <div id="editProfile" class="profile-form-container" style="display: none;">
                    <form id="counselorProfileForm" method="POST" action="update_supporter_profile.php">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="<?php echo htmlspecialchars($supporter['sup_name'] ?? ''); ?>" 
                                   placeholder="Enter your full name"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?php echo htmlspecialchars($supporter['phone_number'] ?? ''); ?>" 
                                   placeholder="Enter your phone number"
                                   pattern="[0-9]{10}"
                                   title="Please enter a valid 10-digit phone number"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" 
                                   id="location" 
                                   name="location" 
                                   value="<?php echo htmlspecialchars($supporter['sup_location'] ?? ''); ?>" 
                                   placeholder="Enter your location"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="expertise">Areas of Expertise</label>
                            <textarea id="expertise" 
                                      name="expertise" 
                                      rows="4" 
                                      placeholder="Describe your areas of expertise"
                                      required><?php echo htmlspecialchars($supporter['sup_specialization'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" onclick="toggleProfileEdit()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-check"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="profile-menu" id="profileMenu" style="display: none;">
        <a href="profile.php" class="menu-item">
            <i class="fas fa-user"></i> Profile
        </a>
        <a href="logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>
    </div>

    <script>
        // Toggle dropdown function
        function toggleDropdown() {
            document.querySelector('.dropdown-content').classList.toggle('show');
        }

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.user-avatar')) {
                const dropdowns = document.getElementsByClassName('dropdown-content');
                for (const dropdown of dropdowns) {
                    if (dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                    }
                }
            }
        }

        // Update availability function
        function toggleAvailability(scheduleId, currentStatus) {
            console.log('Toggling availability:', { scheduleId, currentStatus });
            
            // Create form data
            const formData = new FormData();
            formData.append('schedule_id', scheduleId);
            formData.append('is_available', currentStatus ? 0 : 1);

            // Send request to update_availability_sup.php instead of update_availability.php
            fetch('update_availability_sup.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Response received:', response);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Data received:', data);
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Failed to update availability: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating availability');
            });
        }

        // Handle booking function
        function handleBooking(bookingId, action) {
            if (!confirm(`Are you sure you want to ${action} this booking?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('action', action);
            
            fetch('handle_booking_sup.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'Failed to process booking');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing the booking: ' + error.message);
            });
        }

        function showDashboardOverview() {
            document.getElementById('dashboardOverview').style.display = 'block';
            document.getElementById('availabilityManager').style.display = 'none';
            document.getElementById('bookingsSection').style.display = 'none';
        }

        function showAvailabilityManager() {
            document.getElementById('dashboardOverview').style.display = 'none';
            document.getElementById('availabilityManager').style.display = 'block';
            document.getElementById('bookingsSection').style.display = 'none';
        }

        function showBookings() {
            document.querySelectorAll('.content-section').forEach(section => {
                section.style.display = 'none';
            });
            document.getElementById('bookingsSection').style.display = 'block';
            updateActiveLink(2);
        }

        function updateActiveLink(index) {
            document.querySelectorAll('.sidebar-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelectorAll('.sidebar-link')[index].classList.add('active');
        }

        function showProfile() {
            // Hide all sections
            document.getElementById('dashboardOverview').style.display = 'none';
            document.getElementById('availabilityManager').style.display = 'none';
            document.getElementById('bookingsSection').style.display = 'none';
            document.getElementById('profileSection').style.display = 'block';
            
            // Update active class
            const links = document.querySelectorAll('.sidebar-link');
            links.forEach(link => link.classList.remove('active'));
        }

        function toggleProfileEdit() {
            const viewSection = document.getElementById('viewProfile');
            const editSection = document.getElementById('editProfile');
            
            if (viewSection.style.display === 'none') {
                viewSection.style.display = 'block';
                editSection.style.display = 'none';
            } else {
                viewSection.style.display = 'none';
                editSection.style.display = 'block';
            }
        }

        document.getElementById('counselorProfileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            fetch('update_supporter_profile.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update profile');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the profile');
            });
        });

        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 300);
                }, 2000);
            }, 100);
        }

        // Add this to ensure dashboard shows by default on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a hash in the URL
            if (window.location.hash === '#availability') {
                showAvailabilityManager();
            } else {
                showDashboardOverview();
            }
        });

        // Add this variable at the top of your script section
        let lastNotificationCount = 0;

        // Update the checkDisabledSlots function
        function checkDisabledSlots() {
            fetch('check_admin_disabled_slots.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.hasDisabledSlots) {
                        const disabledSlots = data.disabledSlots;
                        const notificationWrapper = document.getElementById('adminNotificationsWrapper');
                        const notificationsList = document.getElementById('adminNotificationsList');
                        const notificationsCount = document.getElementById('adminNotificationsCount');
                        const notificationsBadge = document.getElementById('adminNotificationsBadge');
                        
                        // Update notification count
                        const currentCount = disabledSlots.length;
                        notificationsCount.textContent = `(${currentCount})`;
                        
                        // Only show badge if there are new notifications since last view
                        const notificationsOpen = localStorage.getItem('adminNotificationsOpen') === 'true';
                        const lastViewedCount = parseInt(localStorage.getItem('lastViewedNotificationCount') || '0');
                        
                        if (currentCount > lastViewedCount && !notificationsOpen) {
                            notificationsBadge.textContent = currentCount;
                            notificationsBadge.style.display = 'block';
                        } else {
                            notificationsBadge.style.display = 'none';
                        }
                        
                        // Store current count for comparison next time
                        lastNotificationCount = currentCount;
                        
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
                        });
                        
                        // Show the notifications container
                        notificationWrapper.style.display = 'block';
                    } else {
                        document.getElementById('adminNotificationsWrapper').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error checking disabled slots:', error);
                });
        }

        // Update the toggleNotifications function
        function toggleNotifications(forceOpen = null) {
            const content = document.getElementById('adminNotificationsContent');
            const button = document.getElementById('toggleNotificationsBtn');
            const icon = button.querySelector('i');
            const notificationsBadge = document.getElementById('adminNotificationsBadge');
            
            const isOpen = forceOpen !== null ? forceOpen : content.style.display === 'none';
            
            content.style.display = isOpen ? 'block' : 'none';
            icon.className = isOpen ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
            
            // When opening notifications, update the last viewed count and hide badge
            if (isOpen) {
                localStorage.setItem('lastViewedNotificationCount', lastNotificationCount);
                notificationsBadge.style.display = 'none';
            }
            
            localStorage.setItem('adminNotificationsOpen', isOpen);
        }

        // Add a function to periodically check for new notifications
        function startNotificationChecker() {
            // Check immediately on page load
            checkDisabledSlots();
            
            // Then check every 60 seconds for new notifications
            setInterval(checkDisabledSlots, 60000);
        }

        // Call this function when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Your existing code...
            
            // Start the notification checker
            startNotificationChecker();
            
            // Add click handler for notifications toggle
            document.getElementById('adminNotificationsHeader').addEventListener('click', function() {
                toggleNotifications();
            });
        });

        // Add this function to handle medical form display
        function showMedicalForm(patientName, patientEmail, bookingId) {
            // Create modal backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop';
            document.body.appendChild(backdrop);
            
            // Create modal container
            const modal = document.createElement('div');
            modal.className = 'medical-form-modal';
            modal.innerHTML = `
                <div class="medical-form-header">
                    <h2>Medical Notes: ${patientName}</h2>
                    <span class="patient-email">${patientEmail}</span>
                    <button class="close-modal-btn">&times;</button>
                </div>
                <form id="medicalNotesForm" class="medical-notes-form">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                    
                    <div class="form-group">
                        <label for="symptoms">Symptoms</label>
                        <textarea id="symptoms" name="symptoms" rows="3" placeholder="Enter patient symptoms"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="diagnosis">Diagnosis</label>
                        <textarea id="diagnosis" name="diagnosis" rows="3" placeholder="Enter your diagnosis"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="medication">Medication/Recommendations</label>
                        <textarea id="medication" name="medication" rows="3" placeholder="Enter medication or recommendations"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="further_steps">Further Procedure</label>
                        <textarea id="further_steps" name="further_steps" rows="3" placeholder="Enter further procedures if any"></textarea>
                    </div>

                    <div class="form-group mt-3">
                        <label><i class="fas fa-tasks"></i> Update Status</label>
                        <div class="status-buttons">
                            <input type="radio" name="status" value="confirmed" id="status_confirmed" hidden>
                            <label for="status_confirmed" class="status-btn confirmed" onclick="selectStatus('confirmed')">
                                <i class="fas fa-check-circle"></i> Confirmed
                            </label>
                            
                            <input type="radio" name="status" value="follow_up" id="status_follow_up" hidden>
                            <label for="status_follow_up" class="status-btn follow-up" onclick="selectStatus('follow_up')">
                                <i class="fas fa-calendar-plus"></i> Follow Up
                            </label>
                            
                            <input type="radio" name="status" value="completed" id="status_completed" hidden>
                            <label for="status_completed" class="status-btn complete" onclick="selectStatus('completed')">
                                <i class="fas fa-check-double"></i> Complete
                            </label>
                            
                            <input type="radio" name="status" value="follow_up_completed" id="status_follow_up_completed" hidden>
                            <label for="status_follow_up_completed" class="status-btn follow-up-complete" onclick="selectStatus('follow_up_completed')">
                                <i class="fas fa-calendar-check"></i> Follow Up & Complete
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="save-notes-btn">Save Notes</button>
                        <button type="button" class="cancel-btn">Cancel</button>
                    </div>
                </form>
            `;
            document.body.appendChild(modal);
            
            // Add event listeners
            const closeModal = () => {
                document.body.removeChild(backdrop);
                document.body.removeChild(modal);
            };
            
            modal.querySelector('.close-modal-btn').addEventListener('click', closeModal);
            modal.querySelector('.cancel-btn').addEventListener('click', closeModal);
            
            // Add form submission handler
            const form = modal.querySelector('#medicalNotesForm');
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                
                try {
                    // Save medical notes
                    const response = await fetch('update_medical_notes_sup.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        alert('Medical notes saved successfully');
                        closeModal();
                        // Instead of reloading, we could update the UI dynamically
                        // But for now, let's use reload to ensure everything is fresh
                        window.location.reload();
                    } else {
                        throw new Error(data.message || 'Failed to save medical notes');
                    }
                } catch (error) {
                    alert('An error occurred while saving medical notes: ' + error.message);
                }
            });
            
            // Load existing notes with modified error handling
            fetch(`get_medical_notes_sup.php?booking_id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.notes) {
                        // Fill in the form with existing data
                        modal.querySelector('#symptoms').value = data.notes.symptoms || '';
                        modal.querySelector('#diagnosis').value = data.notes.diagnosis || '';
                        modal.querySelector('#medication').value = data.notes.medication || '';
                        modal.querySelector('#further_steps').value = data.notes.further_steps || '';
                        
                        // Set the status if it exists
                        if (data.notes.status) {
                            const statusBtn = modal.querySelector(`[for="status_${data.notes.status}"]`);
                            if (statusBtn) {
                                statusBtn.click();
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading medical notes:', error);
                    // Don't show an error to the user, just log it
                    // The form will still be usable with empty fields
                });
        }

        // Add click handler for profile icon
        document.querySelector('.profile-icon').addEventListener('click', function(e) {
            e.stopPropagation();
            const menu = document.getElementById('profileMenu');
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        });

        // Close menu when clicking outside
        document.addEventListener('click', function() {
            document.getElementById('profileMenu').style.display = 'none';
        });

        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.availability-btn');
            
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    const scheduleId = this.getAttribute('data-schedule-id');
                    const isCurrentlyAvailable = this.getAttribute('data-is-available') === '1';
                    const makeAvailable = !isCurrentlyAvailable;
                    
                    console.log('Clicking button:', {
                        scheduleId,
                        isCurrentlyAvailable,
                        makeAvailable
                    });

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `schedule_id=${scheduleId}&is_available=${makeAvailable ? 1 : 0}`,
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Server response:', data);
                        if (data.success) {
                            // Update button state
                            this.setAttribute('data-is-available', makeAvailable ? '1' : '0');
                            this.textContent = makeAvailable ? 'Set Unavailable' : 'Set Available';
                            this.className = `btn ${makeAvailable ? 'btn-danger' : 'btn-success'} availability-btn`;
                            
                            // Update status text
                            const statusElement = this.parentElement.querySelector('.availability-status');
                            if (statusElement) {
                                statusElement.textContent = makeAvailable ? 'Available' : 'Unavailable';
                                statusElement.className = `availability-status ${makeAvailable ? 'available' : 'unavailable'}`;
                            }
                            
                            // Reload the page to refresh the data
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to update availability');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while updating availability');
                    });
                });
            });
        });

        function updateBookingStatus(bookingId, status) {
            if (!confirm('Are you sure you want to update this booking to ' + status + '?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_booking');
            formData.append('booking_id', bookingId);
            formData.append('status', status);
            formData.append('provider_email', '<?php echo $supporter_email; ?>');

            fetch('services.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Booking status updated successfully!');
                    location.reload(); // Refresh to show updated status
                } else {
                    alert('Error updating booking status: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating booking status');
            });
        }

        // Add this to your medical notes form submission handler
        document.getElementById('medicalNotesForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const status = formData.get('status');
            const bookingId = formData.get('booking_id');

            // First update the medical notes
            fetch('update_medical_notes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Then update the booking status
                    return updateBookingStatus(bookingId, status);
                } else {
                    throw new Error(data.message || 'Failed to save medical notes');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred: ' + error.message);
            });
        });

        // Add this to your existing JavaScript
        function updateMedicalStatus(bookingId, status) {
            const formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('status', status);
            
            // First update the medical notes
            fetch('update_medical_notes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // If medical notes update is successful, update the booking status
                    return fetch('services.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=update_booking&booking_id=${bookingId}&status=${status}`
                    });
                }
                throw new Error(data.message || 'Failed to update medical notes');
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Status updated successfully');
                    location.reload();
                } else {
                    throw new Error(data.message || 'Failed to update status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred: ' + error.message);
            });
        }

        // Add event listener to the medical notes form
        document.getElementById('medicalNotesForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const status = formData.get('status');
            const bookingId = formData.get('booking_id');
            
            updateMedicalStatus(bookingId, status);
        });
    </script>
</body>
</html>  