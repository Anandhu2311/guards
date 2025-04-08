<?php
// Turn off error reporting to prevent PHP errors from breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Ensure session is started first thing
session_start();
ob_start(); // Buffer all output to prevent premature HTML

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
            AND provider_role_id = 2 
            AND status = 'completed'";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':advisor_email', $_SESSION['email'], PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn();
}

function getUpcomingSessionsCount($conn, $advisorId) {
    $sql = "SELECT COUNT(*) FROM bookings 
            WHERE provider_email = :advisor_email 
            AND provider_role_id = 2 
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
    // Get bookings where this advisor is assigned or pending advising bookings
    $sql = "SELECT * FROM bookings 
            WHERE (provider_email = :email AND provider_role_id = 2 AND status IN ('confirmed', 'completed'))
               OR (service_type = 'advising' AND status = 'pending')
            ORDER BY booking_date, booking_time";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $advisorEmail, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to accept a booking
function acceptBooking($conn, $bookingId, $advisorEmail, $responseMessage = null) {
    $sql = "UPDATE bookings 
            SET status = 'confirmed', 
                provider_email = :email,
                provider_role_id = 2,
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
                provider_role_id = 2,
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
              AND provider_role_id = 2
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

// Get advisor bookings
$advisorBookings = getAdvisorBookings($pdo, $advisor_email, $advisor_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GuardSphere Advisor Dashboard</title>
    <script src="disable-navigation.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        /* Main content styles */
        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 2rem;
            min-height: calc(100vh - 70px);
        }

        /* Dashboard overview styles */
        .section-title {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1.8rem;
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

        /* Media queries for responsiveness */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 250px;
            }
            .sidebar {
                width: 250px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .section-title {
                font-size: 1.7rem;
            }
        }

        /* Profile styles */
        .profile-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .profile-form {
            display: grid;
            grid-gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-group input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .form-group input:focus {
            border-color: #9932CC;
            outline: none;
            box-shadow: 0 0 0 2px rgba(153, 50, 204, 0.2);
        }

        .form-group input[readonly] {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }

        .form-group small {
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }

        .form-actions {
            margin-top: 10px;
            display: flex;
            justify-content: flex-end;
        }

        .btn-primary {
            background-color: #9932CC;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #8927b0;
            transform: translateY(-2px);
        }

        .message-container {
            margin-bottom: 20px;
        }

        .success-message {
            background-color: #dff0d8;
            color: #3c763d;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #5cb85c;
        }

        .error-message {
            background-color: #f2dede;
            color: #a94442;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #d9534f;
        }

        .action-buttons {
            margin-top: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-end;
        }

        .profile-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background-color: #9932CC;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .profile-btn:hover {
            background-color: #8927b0;
            transform: translateY(-2px);
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
        <div id="dashboardOverview">
            <h1 class="section-title">Dashboard Overview</h1>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">COMPLETED SESSIONS</div>
                        <div class="stat-card-icon purple">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $completedSessions; ?></div>
                    <div class="stat-card-description">All-time bookings</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">UPCOMING SESSIONS</div>
                        <div class="stat-card-icon blue">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $upcomingSessions; ?></div>
                    <div class="stat-card-description">Scheduled for the next 7 days</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">AVAILABLE SLOTS</div>
                        <div class="stat-card-icon green">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $availableSlots; ?></div>
                    <div class="stat-card-description">Slots where you are available</div>
                </div>
            </div>
            
            <!-- Add this button -->
            <div class="action-buttons">
                <button class="btn-primary profile-btn" onclick="showProfileSection()">
                    <i class="fas fa-user"></i> My Profile
                </button>
            </div>
        </div>
        
        <!-- Availability Manager Section -->
        <div id="availabilityManager" style="display: none;">
            <h1 class="section-title">Manage Your Availability</h1>
            
            <div class="availability-manager">
                <div class="availability-title">Your Schedule</div>
                
                <div class="schedule-list">
                    <?php foreach ($advisorSchedules as $schedule): ?>
                        <div class="schedule-item">
                            <div class="schedule-info">
                                <div class="schedule-day"><?php echo ucfirst($schedule['day']); ?></div>
                                <div class="schedule-time"><?php echo $schedule['start_time'] . ' - ' . $schedule['end_time']; ?></div>
                            </div>
                            <div class="schedule-controls">
                                <label class="schedule-toggle">
                                    <input 
                                        type="checkbox" 
                                        data-schedule-id="<?php echo $schedule['id']; ?>"
                                        <?php echo (isset($userAvailability[$schedule['id']]) && $userAvailability[$schedule['id']] == 1) ? 'checked' : ''; ?>
                                        onchange="updateAvailability(this)"
                                    >
                                    <span class="slider"></span>
                                </label>
                                <div class="availability-status">
                                    <?php echo (isset($userAvailability[$schedule['id']]) && $userAvailability[$schedule['id']] == 1) ? 'Available' : 'Not Available'; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Bookings Section -->
        <div id="bookingsSection" style="display: none;">
            <h1 class="section-title">Your Bookings</h1>
            
            <div class="bookings-section">
                <div class="bookings-title">Manage Bookings</div>
                <div id="bookingsList">
                    <?php if (empty($advisorBookings)): ?>
                        <p>You have no bookings to manage.</p>
                    <?php else: ?>
                        <table class="bookings-table">
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
                                    <tr data-id="<?php echo $booking['booking_id']; ?>" class="<?php echo $booking['status']; ?>-row">
                                        <td><?php echo $booking['booking_date']; ?></td>
                                        <td><?php echo $booking['booking_time']; ?></td>
                                        <td><?php echo $booking['user_email']; ?></td>
                                        <td><?php echo ucfirst($booking['service_type']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['notes'] ?? 'No notes'); ?></td>
                                        <td class="status-cell"><?php echo ucfirst($booking['status']); ?></td>
                                        <td class="actions-cell">
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <button class="action-btn accept-btn" onclick="handleBooking(<?php echo $booking['booking_id']; ?>, 'accept')">Accept</button>
                                                <button class="action-btn reject-btn" onclick="handleBooking(<?php echo $booking['booking_id']; ?>, 'reject')">Reject</button>
                                            <?php elseif ($booking['status'] === 'confirmed'): ?>
                                                <button class="action-btn complete-btn" onclick="handleBooking(<?php echo $booking['booking_id']; ?>, 'complete')">Complete</button>
                                            <?php else: ?>
                                                <span class="no-actions">No actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profileSection" style="display: none;">
            <h1 class="section-title">My Profile</h1>
            
            <div class="profile-container">
                <div id="profileUpdateMessage" class="message-container"></div>
                
                <form id="advisorProfileForm" class="profile-form">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($advisor['adv_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($advisor['adv_email'] ?? ''); ?>" readonly>
                        <small>Email cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($advisor['phone_number'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($advisor['adv_location'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="specialization">Specialization</label>
                        <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($advisor['adv_specialization'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle dropdown function
        function toggleDropdown() {
            document.getElementById('profileDropdown').classList.toggle('show');
        }
        
        // Show dashboard overview
        function showDashboardOverview() {
            // Hide all sections
            document.getElementById('availabilityManager').style.display = 'none';
            document.getElementById('bookingsSection').style.display = 'none';
            document.getElementById('dashboardOverview').style.display = 'block';
            
            // Update active class
            const links = document.querySelectorAll('.sidebar-link');
            links.forEach(link => link.classList.remove('active'));
            document.querySelectorAll('.sidebar-link')[0].classList.add('active');
        }
        
        // Show availability manager
        function showAvailabilityManager() {
            // Hide all sections
            document.getElementById('dashboardOverview').style.display = 'none';
            document.getElementById('bookingsSection').style.display = 'none';
            document.getElementById('availabilityManager').style.display = 'block';
            
            // Update active class
            const links = document.querySelectorAll('.sidebar-link');
            links.forEach(link => link.classList.remove('active'));
            document.querySelectorAll('.sidebar-link')[1].classList.add('active');
        }
        
        function showBookings() {
            // Hide all sections
            document.getElementById('dashboardOverview').style.display = 'none';
            document.getElementById('availabilityManager').style.display = 'none';
            document.getElementById('bookingsSection').style.display = 'block';
            
            // Update active class
            const links = document.querySelectorAll('.sidebar-link');
            links.forEach(link => link.classList.remove('active'));
            document.querySelectorAll('.sidebar-link')[2].classList.add('active');
            
            // Refresh the bookings data without reloading the page
            refreshBookings();
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
                    ? 'Add any notes for the student (optional):' 
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
            document.getElementById('bookingsSection').style.display = 'none';
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
    </script>
</body>
</html>

