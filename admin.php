<?php
session_start();
require 'DBS.inc.php'; // Database connection file

// Define the admin credentials
$admin_email = "admin@gmail.com"; // Change this to your actual admin email
$admin_password = "Admin@123"; // Change this to your actual admin password (hashed in production)

// Redirect to login if the user is not logged in
if (!isset($_SESSION['email']) || $_SESSION['email'] !== $admin_email) {
    header("Location: signin.php");
    exit();
}

$logout_message = '';
if (isset($_SESSION['logout_success'])) {
    $logout_message = $_SESSION['logout_success'];
    unset($_SESSION['logout_success']); // Clear the message after displaying
}


// Function to get all schedules from database
function getSchedules($conn) {
    $sql = "SELECT * FROM schedules ORDER BY day, start_time";
    $stmt = $conn->query($sql);
    $schedules = [];
    
    if ($stmt) {
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $schedules;
}

// Get all schedules for initial page load
$allSchedules = getSchedules($pdo);

// Function to get all bookings from database
function getBookings($conn) {
    $sql = "SELECT b.*, u.name as user_name, 
            p.name as provider_name,
            s.day, s.start_time, s.end_time
            FROM bookings b 
            LEFT JOIN users u ON b.user_email = u.email 
            LEFT JOIN users p ON b.provider_email = p.email 
            LEFT JOIN schedules s ON b.schedule_id = s.id
            ORDER BY b.booking_date DESC";
    $stmt = $conn->query($sql);
    $bookings = [];
    
    if ($stmt) {
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $bookings;
}

// Get all bookings for initial page load
$allBookings = getBookings($pdo);

// Function to check if a time slot conflicts with existing slots
function checkSlotConflict($conn, $day, $startTime, $endTime, $userType, $excludeId = null) {
    $sql = "SELECT * FROM schedules 
            WHERE day = :day 
            AND ((start_time <= :startTime AND end_time > :startTime) 
                OR (start_time < :endTime AND end_time >= :endTime)
                OR (start_time >= :startTime AND end_time <= :endTime))
            AND user_type != :userType";
    
    if ($excludeId) {
        $sql .= " AND id != :excludeId";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':day', $day);
    $stmt->bindParam(':startTime', $startTime);
    $stmt->bindParam(':endTime', $endTime);
    $stmt->bindParam(':userType', $userType);
    
    if ($excludeId) {
        $stmt->bindParam(':excludeId', $excludeId);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to check if a time has already passed today
function hasTimePassed($day, $time) {
    $daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $currentDayOfWeek = $daysOfWeek[date('w')];
    $currentTime = date('H:i');
    
    // If we're checking the current day and the time has passed
    if ($day == $currentDayOfWeek && $time < $currentTime) {
        return true;
    }
    
    return false;
}

// Function to enable or disable a schedule
function toggleScheduleStatus($conn, $scheduleId, $status) {
    $sql = "UPDATE schedules SET is_active = :status WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $scheduleId);
    return $stmt->execute();
}

// Function to enable or disable a user
function toggleUserStatus($conn, $userId, $status, $userType) {
    $table = '';
    switch ($userType) {
        case 'counselor':
            $table = 'counselors';
            break;
        case 'supporter':
            $table = 'supporters';
            break;
        case 'advisor':
            $table = 'advisors';
            break;
        case 'user':
            $table = 'users';
            break;
        default:
            return false;
    }
    
    $sql = "UPDATE $table SET is_active = :status WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $userId);
    return $stmt->execute();
}

// Near the beginning of your PHP code, add:
$view = isset($_GET['view']) ? $_GET['view'] : '';


// Fetch all users and their emergency info
$sql = "SELECT users.id, users.name, users.email, users.phone_number, 
        GROUP_CONCAT(emergency_contacts.emergency_name SEPARATOR ', ') AS emergency_names,
        GROUP_CONCAT(emergency_contacts.em_number SEPARATOR ', ') AS emergency_numbers,
        GROUP_CONCAT(emergency_contacts.relationship SEPARATOR ', ') AS relationships
        FROM users
        LEFT JOIN emergency_contacts ON users.email = emergency_contacts.email
        GROUP BY users.id";

$result = $pdo->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GuardSphere Admin Panel</title>
    <script src="disable-navigation.js"></script>
    <!-- Add FullCalendar library dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <title>GuardSphere - Women's Safety</title>
    <script src="disable-navigation.js"></script>
>>>>>>> aecb47c01bea4b9775dbf4af39f7dddb2f07fafa
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
            background: #663399;
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
            color: #FF1493;
        }

        /* Sidebar styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1a1a1a 0%, #2d2d2d 100%);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding-top: 90px;
            padding-left: 20px;
            padding-right: 20px;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
            z-index: 999;
            transition: all 0.3s ease;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar h2 {
            font-size: 1.5rem;
            color: #FF1493;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 105, 180, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar-link {
            position: relative;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            background: rgba(102, 51, 153, 0.1);
        }

        .sidebar-link:hover {
            background: rgba(255, 20, 147, 0.2);
            transform: translateX(5px);
        }

        .sidebar-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .sidebar-link .fa-chevron-down {
            margin-left: auto;
            font-size: 0.8rem;
            transition: transform 0.3s ease;
        }

        .sidebar-link[aria-expanded="true"] .fa-chevron-down,
        .sidebar-link.active .fa-chevron-down {
            transform: rotate(180deg);
        }

        .sub-options {
            margin-left: 20px;
            margin-bottom: 10px;
            display: none;
            animation: slideDown 0.3s ease-out;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 6px;
            padding: 5px;
        }

        .sub-option {
            position: relative;
            color: #e0e0e0;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            margin-bottom: 5px;
        }

        .sub-option:hover {
            color: #FF1493;
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar-link.active {
            background: rgba(255, 20, 147, 0.3);
            border-left: 4px solid #FF1493;
            padding-left: 11px;
        }

        .sub-option.active {
            color: #FF1493;
            background: rgba(255, 255, 255, 0.05);
            font-weight: 500;
        }

        /* Main content area styles */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            background-color: #f4f4f4;
            min-height: 100vh;
            margin-top: 70px;
            transition: margin-left 0.3s ease;
            position: relative;
        }

        /* Schedule and Calendar section styles */
        .schedule-section, .calendar-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 25px;
            animation: fadeIn 0.3s ease;
        }

        .schedule-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .weekly-schedule {
            display: grid;
            grid-template-columns: 80px repeat(7, 1fr);
            gap: 1px;
            background: #eee;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .time-slot {
            height: 60px;
            padding: 5px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            background: #f8f9fa;
        }

        .day-header {
            padding: 10px;
            text-align: center;
            font-weight: bold;
            background: #f8f9fa;
            border-bottom: 1px solid #ddd;
        }

        .day-slots {
            min-height: 540px;
            position: relative;
            background: white;
        }

        .slot-block {
            position: absolute;
            left: 5px;
            right: 5px;
            background: #663399;
            color: white;
            padding: 5px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1;
            display: flex;
            flex-direction: column;
        }

        .slot-block:hover {
            transform: scale(1.02);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .slot-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 2px;
            gap: 5px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .slot-block:hover .slot-actions {
            opacity: 1;
        }

        .slot-actions button {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 3px;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: white;
            padding: 0;
            font-size: 10px;
            transition: background 0.2s ease;
        }

        .slot-actions .edit-btn:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        .slot-actions .delete-btn:hover {
            background: rgba(255, 0, 0, 0.6);
        }

        .add-slot-btn {
            background: #663399;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background 0.2s ease;
        }

        .add-slot-btn:hover {
            background: #7a44b8;
        }

        #userTypeFilter {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            outline: none;
            transition: border 0.2s ease;
        }

        #userTypeFilter:focus {
            border-color: #663399;
        }

        #calendar {
            min-height: 600px;
        }

        /* Modal styles */
        .modal {
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1200;
            animation: fadeIn 0.3s;
        }
        
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .modal-content h2 {
            margin-bottom: 15px;
            color: #663399;
        }
        
        .modal-content p {
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #888;
        }
        
        .close:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group select {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
        
        .modal-content button {
            background-color: #663399;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 12px 15px;
            cursor: pointer;
            width: 100%;
            font-weight: 500;
            margin-top: 10px;
            transition: background-color 0.2s;
        }
        
        .modal-content button:hover {
            background-color: #7a44b8;
        }

        /* Schedule list styles */
        .schedule-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .schedule-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 5px solid #663399;
        }

        .schedule-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }

        .schedule-card h3 {
            color: #663399;
            margin-bottom: 15px;
            font-size: 1.2rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .schedule-card p {
            margin-bottom: 10px;
            color: #555;
        }

        .schedule-card.active {
            border-left-color: #28a745;
        }

        .schedule-card.inactive {
            border-left-color: #dc3545;
            background-color: #f8f9fa;
            opacity: 0.8;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.active {
            background-color: #defbe6;
            color: #28a745;
        }

        .status-badge.inactive {
            background-color: #fbdede;
            color: #dc3545;
        }

        .availability-note {
            font-size: 0.85rem;
            color: #ff8c00;
            font-style: italic;
            display: block;
            margin-top: 5px;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .card-actions button {
            flex: 1;
            padding: 8px 0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .card-actions .edit-btn {
            background-color: #f0f0f0;
            color: #333;
        }

        .card-actions .edit-btn:hover {
            background-color: #e0e0e0;
        }

        .card-actions .enable-btn {
            background-color: #e6f7ec;
            color: #28a745;
        }

        .card-actions .enable-btn:hover {
            background-color: #28a745;
            color: white;
        }

        .card-actions .disable-btn {
            background-color: #f7e6e6;
            color: #dc3545;
        }

        .card-actions .disable-btn:hover {
            background-color: #dc3545;
            color: white;
        }

        .card-actions .delete-btn {
            background-color: #f0f0f0;
            color: #666;
        }

        .card-actions .delete-btn:hover {
            background-color: #757575;
            color: white;
        }

        /* Sidebar toggle button */
        .sidebar-toggle-btn {
            position: absolute;
            top: 120px;
            right: -20px;
            width: 40px;
            height: 60px;
            background: linear-gradient(90deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #FF1493;
            border: none;
            border-radius: 0 10px 10px 0;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 4px 0 10px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }

        .sidebar-toggle-btn:hover {
            color: white;
            background: linear-gradient(90deg, #2d2d2d 0%, #3d3d3d 100%);
        }

        .sidebar-toggle-btn i {
            font-size: 20px;
            font-weight: bold;
        }

        /* Collapsed sidebar styles */
        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar.collapsed h2,
        .sidebar.collapsed .fa-chevron-down,
        .sidebar.collapsed .link-text {
            display: none;
        }

        .sidebar.collapsed .sidebar-link {
            justify-content: center;
            padding: 12px 0;
        }

        .sidebar.collapsed .sidebar-item:hover .sub-options {
            display: block !important;
            position: absolute;
            left: 70px;
            top: 0;
            width: 220px;
            background: #2d2d2d;
            border-radius: 0 8px 8px 0;
            padding: 10px;
            z-index: 1001;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
        }

        /* Notification and status message styles */
        .logout-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #4CAF50;
            color: white;
            padding: 15px 25px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1100;
            animation: fadeOut 3s ease-in-out forwards;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 20, 147, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 20, 147, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 20, 147, 0); }
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .sidebar-toggle {
                display: block;
                position: fixed;
                top: 80px;
                left: 20px;
                background: #663399;
                color: white;
                border: none;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                z-index: 1060;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            }

            .weekly-schedule {
                overflow-x: auto;
            }
        }

        @media (max-width: 768px) {
            .schedule-controls {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .schedule-section, .calendar-section {
                padding: 15px;
            }
            
            #calendar {
                min-height: 400px;
            }
        }

        /* Add this CSS for the calendar event popup */
        .calendar-event-popup {
            z-index: 1000;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            animation: fadeIn 0.2s ease;
        }

        .calendar-event-popup-content {
            display: flex;
            flex-direction: column;
            padding: 10px;
            gap: 5px;
        }

        .calendar-event-popup button {
            background: #f5f5f5;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            text-align: left;
            transition: background 0.2s ease;
        }

        .calendar-event-popup button:hover {
            background: #e8e8e8;
        }

        .calendar-event-popup .edit-event-btn {
            color: #2196F3;
        }

        .calendar-event-popup .delete-event-btn {
            color: #f44336;
        }

        .calendar-event-popup .close-popup-btn {
            color: #666;
            margin-top: 5px;
        }

        /* Add these styles for new elements */
        .active-status {
            color: #4CAF50;
            font-weight: bold;
        }

        .inactive-status {
            color: #f44336;
            font-weight: bold;
        }

        .disabled-slot {
            opacity: 0.6;
            background-color: #f5f5f5;
            border-left: 4px solid #f44336;
        }

        .warning-message {
            display: none;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 4px;
            background-color: #ffebee;
            color: #f44336;
        }

        .enable-btn {
            background-color: #4CAF50 !important;
            color: white !important;
        }

        .disable-btn {
            background-color: #f44336 !important;
            color: white !important;
        }

        .delete-btn {
            background-color: #757575 !important;
            color: white !important;
        }

        /* Existing CSS */

        .disabled-event {
            opacity: 0.6 !important;
            cursor: not-allowed !important;
            background-image: repeating-linear-gradient(45deg, transparent, transparent 5px, rgba(0,0,0,0.1) 5px, rgba(0,0,0,0.1) 10px) !important;
        }

        .schedule-card.active {
            border-left: 4px solid #28a745;
        }

        .schedule-card.inactive {
            border-left: 4px solid #dc3545;
            opacity: 0.7;
        }

        .status-badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-badge.active {
            background-color: #28a745;
            color: white;
        }

        .status-badge.inactive {
            background-color: #dc3545;
            color: white;
        }

        .toggle-btn.active {
            background-color: #dc3545;
            color: white;
        }

        .toggle-btn.inactive {
            background-color: #28a745;
            color: white;
        }

        /* Add these styles in your existing <style> section */
        .booking-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .booking-table th {
            background-color: #663399;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
        }

        .booking-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .booking-table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .booking-table .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            text-align: center;
            min-width: 100px;
        }

        .booking-table .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .booking-table .status-confirmed {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .booking-table .status-completed {
            background-color: #d4edda;
            color: #155724;
        }

        .booking-table .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .booking-action-btn {
            background: none;
            border: none;
            color: #663399;
            cursor: pointer;
            padding: 5px 8px;
            margin: 0 2px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .booking-action-btn:hover {
            background-color: #f0f0f0;
        }

        .booking-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .booking-detail-item {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .booking-detail-item h4 {
            color: #663399;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .booking-detail-item p {
            margin: 0;
            font-size: 1rem;
        }

        .booking-notes {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .table-responsive {
            overflow-x: auto;
            margin-bottom: 20px;
        }

        /* Add styles for the reminder section */
        .reminder-section {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
        }
        
        .reminder-section h3 {
            color: #6c757d;
            margin-top: 0;
            font-size: 1.2rem;
        }
        
        .days-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .day-pill {
            background-color: #ffe0e0;
            color: #d9534f;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        
        /* Medical report styles */
        .medical-report {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
        }
        
        .medical-report h3 {
            color: #2c3e50;
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .report-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .report-content {
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .report-text {
            white-space: pre-line;
            line-height: 1.5;
        }
        
        /* Medical Report Styles */
        .medical-report {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .medical-report h3 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .report-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .report-content {
            background-color: #fff;
            border-radius: 6px;
            padding: 15px;
            border: 1px solid #e0e0e0;
        }
        
        .report-content h4 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .report-text {
            white-space: pre-line;
            line-height: 1.5;
        }
        
        .medical-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .info-section {
            background-color: #f5f5f5;
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid #3498db;
        }
        
        .info-section h5 {
            margin-top: 0;
            margin-bottom: 8px;
            color: #3498db;
            font-size: 14px;
        }
        
        .info-section p {
            margin: 0;
            font-size: 13px;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <?php if ($logout_message): ?>
    <div class="logout-message" id="logoutMessage">
        <?php echo htmlspecialchars($logout_message); ?>
    </div>
    <?php endif; ?>

    <header>
        <nav>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 120" class="logo" onclick="toggleSidebar()" style="cursor: pointer;">
                <g transform="translate(30, 10)">
                    <path d="M50 35
                            C45 25, 30 25, 25 35
                            C20 45, 25 55, 50 75
                            C75 55, 80 45, 75 35
                            C70 25, 55 25, 50 35" 
                        fill="#FF1493"/>
                    <path d="M15 55
                            C12 55, 5 58, 5 75
                            C5 82, 8 87, 15 90
                            L25 92
                            C20 85, 18 80, 20 75
                            C22 70, 25 68, 30 70
                            C28 65, 25 62, 20 62
                            C15 62, 15 65, 15 55" 
                        fill="#9932CC"/>
                    <path d="M85 55
                            C88 55, 95 58, 95 75
                            C95 82, 92 87, 85 90
                            L75 92
                            C80 85, 82 80, 80 75
                            C78 70, 75 68, 70 70
                            C72 65, 75 62, 80 62
                            C85 62, 85 65, 85 55" 
                        fill="#9932CC"/>
                </g>
                <text x="150" y="80" font-family="Arial Black, sans-serif" font-weight="900" font-size="60" fill="#ffffff">GUARDSPHERE</text>
            </svg>
            <div class="nav-links">
                <div class="profile-section">
                    <div class="user-avatar" onclick="toggleProfileDropdown()">
                        <?php 
                        if (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
                            echo strtoupper(substr($_SESSION['email'], 0, 1));
                        } else {
                            echo 'G'; 
                        }
                        ?>
                    </div>
                    <div class="dropdown-content" id="profileDropdown">
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="sidebar" id="sidebar">
        <h2>Admin Dashboard</h2>
        <div class="sidebar-item">
            <a href="#" class="sidebar-link" data-title="Manage Counselors" onclick="toggleSubOptions(event)">
                <i class="fas fa-user-md"></i> 
                <span class="link-text">Manage Counselors</span> 
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="sub-options">
                <a href="add_counselors.php" class="sub-option">
                    <i class="fas fa-user-plus"></i> 
                    <span class="link-text">Add Counselor</span>
                </a>
                <a href="manage_counselors.php" class="sub-option">
                    <i class="fas fa-eye"></i> 
                    <span class="link-text">View Counselors</span>
                </a>
            </div>
        </div>
        <div class="sidebar-item">
            <a href="#" class="sidebar-link" data-title="Manage Supporters" onclick="toggleSubOptions(event)">
                <i class="fas fa-user-friends"></i> 
                <span class="link-text">Manage Supporters</span> 
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="sub-options">
                <a href="add_supporter.php" class="sub-option">
                    <i class="fas fa-user-plus"></i> 
                    <span class="link-text">Add Supporter</span>
                </a>
                <a href="manage_supporters.php" class="sub-option">
                    <i class="fas fa-eye"></i> 
                    <span class="link-text">View Supporters</span>
                </a>
            </div>
        </div>
        <div class="sidebar-item">
            <a href="#" class="sidebar-link" data-title="Manage Advisors" onclick="toggleSubOptions(event)">
                <i class="fas fa-user-tie"></i> 
                <span class="link-text">Manage Advisors</span> 
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="sub-options">
                <a href="add_advisor.php" class="sub-option">
                    <i class="fas fa-user-plus"></i> 
                    <span class="link-text">Add Advisor</span>
                </a>
                <a href="manage_advisors.php" class="sub-option">
                    <i class="fas fa-eye"></i> 
                    <span class="link-text">View Advisors</span>
                </a>
            </div>
        </div>
        <div class="sidebar-item">
            <a href="#" class="sidebar-link" data-title="Manage Users" onclick="toggleSubOptions(event)">
                <i class="fas fa-users"></i> 
                <span class="link-text">Manage Users</span> 
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="sub-options">
                <a href="manage_users.php" class="sub-option">
                    <i class="fas fa-eye"></i> 
                    <span class="link-text">View Users</span>
                </a>
            </div>
        </div>
        <div class="sidebar-item">
            <a href="#" class="sidebar-link" data-title="Scheduling" onclick="toggleSubOptions(event)">
                <i class="fas fa-calendar-alt"></i> 
                <span class="link-text">Scheduling</span> 
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="sub-options">
                <a href="#" class="sub-option" onclick="showScheduleManager()">
                    <i class="fas fa-tasks"></i> 
                    <span class="link-text">Manage Schedules</span>
                </a>
                <!-- <a href="#" class="sub-option" onclick="showCalendarView()">
                    <i class="fas fa-calendar-week"></i> 
                    <span class="link-text">Calendar View</span>
                </a> -->
            </div>
        </div>
        <div class="sidebar-item">
            <a href="#" class="sidebar-link" data-title="Bookings" onclick="toggleSubOptions(event)">
                <i class="fas fa-bookmark"></i> 
                <span class="link-text">Bookings</span> 
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="sub-options">
                <a href="#" class="sub-option" onclick="showBookingManager()">
                    <i class="fas fa-clipboard-list"></i> 
                    <span class="link-text">Manage Bookings</span>
                </a>
            </div>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <h2>Welcome to GuardSphere Admin Panel</h2>
        <p>Select an option from the sidebar to manage users and schedules.</p>
        
        <!-- Schedule Manager Section -->
        <div id="scheduleManager" class="content-section" style="display: none;">
            <h2>Schedule Management</h2>
            <div class="schedule-section">
                <div class="schedule-controls">
                    <button class="add-slot-btn" onclick="showAddScheduleModal()">
                        <i class="fas fa-plus"></i> Add Time Slot
                    </button>
                    <select id="userTypeFilter" onchange="updateScheduleDisplay()">
                        <option value="all">All Provider Types</option>
                        <option value="counselors">Counselors</option>
                        <option value="supporters">Supporters</option>
                        <option value="advisors">Advisors</option>
                    </select>
                </div>
                <div id="scheduleList" class="schedule-list">
                    <!-- Schedule cards will be loaded here -->
                </div>
                
                <!-- Reminder section for days with no time slots -->
                <div id="noSlotsReminder" class="reminder-section">
                    <h3>Days Without Time Slots</h3>
                    <div id="daysList" class="days-list">
                        <!-- Days without slots will be shown here -->
                        <p>Loading days information...</p>
                    </div>
                </div>
            </div>
            <div class="calendar-section">
                <h3>Schedule Calendar View</h3>
                <div id="scheduleCalendar"></div>
            </div>
        </div>
        
        <!-- Calendar View Section -->
        <div id="calendarView" class="content-section" style="display: none;">
            <h2>Calendar View</h2>
            <div class="calendar-section">
                <div id="mainCalendar" style="height: 600px;"></div>
            </div>
        </div>
        
        <!-- Booking Manager Section -->
        <div id="bookingManager" class="content-section" style="display: none;">
            <h2>View Bookings</h2>
            <div class="schedule-section">
                <div class="schedule-controls">
                    <select id="bookingStatusFilter" onchange="filterBookings()">
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <select id="bookingServiceFilter" onchange="filterBookings()">
                        <option value="all">All Services</option>
                        <option value="counseling">Counseling</option>
                        <option value="support">Peer Support</option>
                        <option value="advisory">Advisory</option>
                    </select>
                    <input type="date" id="bookingDateFilter" onchange="filterBookings()" placeholder="Filter by date">
                </div>
                
                <div class="table-responsive">
                    <table class="booking-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Service</th>
                                <th>Provider</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Medical Report</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bookingsList">
                            <!-- Booking rows will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- No bookings message -->
                <div id="noBookingsMessage" style="display: none; text-align: center; padding: 30px;">
                    <i class="fas fa-calendar-times" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                    <p>No bookings found matching your criteria.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="scheduleModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Define New Time Slot</h2>
            <p id="modalDescription">Define a standard time slot that service providers can later mark themselves as available for.</p>
            <div id="conflictWarning" class="warning-message" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i> 
                <span id="conflictMessage">This time slot conflicts with an existing slot for a different provider type.</span>
            </div>
            <div id="pastTimeWarning" class="warning-message" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i> 
                <span>This time has already passed for today. The slot will be available from next week.</span>
            </div>
            <form id="scheduleForm">
                <input type="hidden" id="scheduleId" name="scheduleId" value="">
                <div class="form-group">
                    <label for="day">Day of Week:</label>
                    <select id="day" name="day" required onchange="checkPastTime()">
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                        <option value="Sunday">Sunday</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="startTime">Start Time:</label>
                    <select id="startTime" name="startTime" required onchange="updateEndTimeOptions(); checkPastTime(); checkSlotConflicts()">
                        <!-- Time options will be generated by JS -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="endTime">End Time:</label>
                    <select id="endTime" name="endTime" required onchange="checkSlotConflicts()">
                        <!-- Time options will be generated by JS -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="isActive">Status:</label>
                    <select id="isActive" name="isActive">
                        <option value="1">Active</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
                <button type="button" id="saveScheduleBtn" onclick="saveSchedule()">Define Time Slot</button>
            </form>
        </div>
    </div>

    <!-- User Management Modals -->
    <div id="userManagementModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Manage User Status</h2>
            <p>Enable or disable user access to the system.</p>
            <form id="userStatusForm">
                <input type="hidden" id="userId" name="userId">
                <input type="hidden" id="userTypeField" name="userType">
                <div class="form-group">
                    <label for="userStatus">Status:</label>
                    <select id="userStatus" name="userStatus">
                        <option value="1">Active</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
                <button type="button" onclick="updateUserStatus()">Update Status</button>
            </form>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div id="bookingDetailsModal" class="modal" style="display: none;">
        <div class="modal-content" style="width: 600px; max-width: 90%;">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Booking Details</h2>
            <div id="bookingDetailsContent">
                <!-- Booking details will be loaded here -->
            </div>
            <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" onclick="closeModal()" style="background-color: #f0f0f0; color: #333;">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Toggle profile dropdown
        function toggleProfileDropdown() {
            document.getElementById('profileDropdown').classList.toggle('show');
        }

        // Toggle sidebar submenu options
        function toggleSubOptions(event) {
            event.preventDefault();
            
            // Don't toggle submenu if sidebar is collapsed
            if (document.getElementById('sidebar').classList.contains('collapsed')) {
                return;
            }
            
            const link = event.currentTarget;
            const subOptions = link.nextElementSibling;
            
            // Close other open submenus
            document.querySelectorAll('.sub-options').forEach(menu => {
                if (menu !== subOptions && menu.style.display === 'block') {
                    menu.style.display = 'none';
                    menu.previousElementSibling.classList.remove('active');
                }
            });
            
            // Toggle current submenu
            if (subOptions.style.display === 'block') {
                subOptions.style.display = 'none';
                link.classList.remove('active');
            } else {
                subOptions.style.display = 'block';
                link.classList.add('active');
            }
            
            // Don't change content when clicking on parent menu items that have submenus
            // This prevents changing the dashboard view when clicking on "Scheduling"
            if (link.classList.contains('sidebar-link') && subOptions) {
                return;
            }
        }

        // Toggle sidebar collapse
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            
            if (sidebar.classList.contains('collapsed')) {
                mainContent.style.marginLeft = '70px';
                
                // Add click listeners to all clickable elements in the sidebar when collapsed
                const clickableElements = sidebar.querySelectorAll('.sidebar-link, .sub-option');
                clickableElements.forEach(element => {
                    element.addEventListener('click', handleCollapsedSidebarClick);
                });
            } else {
                mainContent.style.marginLeft = '280px';
                
                // Remove click listeners when expanded
                const clickableElements = sidebar.querySelectorAll('.sidebar-link, .sub-option');
                clickableElements.forEach(element => {
                    element.removeEventListener('click', handleCollapsedSidebarClick);
                });
            }
        }

        // Handle clicks on any sidebar element when collapsed
        function handleCollapsedSidebarClick(event) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('collapsed')) {
                event.preventDefault(); // Prevent the default action
                toggleSidebar(); // Open the sidebar first
                
                // Optional: After a short delay, trigger the original click action
                setTimeout(() => {
                    if (this.dataset.title === "Scheduling" || this.classList.contains('sidebar-link')) {
                        toggleSubOptions({currentTarget: this, preventDefault: () => {}});
                        // Don't show any content when clicking parent menu items
                        return;
                    } else if (this.classList.contains('sub-option')) {
                        // For sub-options, execute their original onclick function
                        const onclickAttr = this.getAttribute('onclick');
                        if (onclickAttr) {
                            eval(onclickAttr);
                        }
                    }
                }, 300);
            }
        }

        // Show schedule manager section
        function showScheduleManager() {
            // Hide all content sections first
            document.getElementById('scheduleManager').style.display = 'none';
            document.getElementById('calendarView').style.display = 'none';
            document.getElementById('bookingManager').style.display = 'none';
            
            // Then show only the schedule manager
            document.getElementById('scheduleManager').style.display = 'block';
            
            // Load schedule data
            refreshScheduleList(); // Use the refresh function for initial load too
            
            // Initialize calendar in the schedule manager
            initializeCalendarInScheduleManager();
            
            // Update URL hash for better navigation
            window.location.hash = 'schedule';
        }

        // Initialize calendar in schedule manager
        function initializeCalendarInScheduleManager() {
            const calendarEl = document.getElementById('scheduleCalendar');
            if (!calendarEl) return;
            
            if (window.scheduleManagerCalendar) {
                window.scheduleManagerCalendar.render();
                return;
            }
            
            window.scheduleManagerCalendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridWeek,timeGridDay'
                },
                slotMinTime: '08:00:00',
                slotMaxTime: '20:00:00',
                height: 'auto',
                allDaySlot: false,
                slotDuration: '00:30:00',
                events: function(info, successCallback, failureCallback) {
                    // Fetch schedule data for the calendar
                    fetch('get_schedules.php')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Convert schedules to calendar events
                            const events = getCalendarEvents(data);
                            successCallback(events);
                        })
                        .catch(error => {
                            console.error('Error fetching schedules for calendar:', error);
                            failureCallback(error);
                        });
                },
                eventClick: function(info) {
                    if (info.event.id) {
                        editSchedule(info.event.id);
                    }
                },
                eventContent: function(arg) {
                    return {
                        html: `
                            <div style="font-size: 0.8em; overflow: hidden; width: 100%;">
                                <div style="font-weight: bold;">${arg.event.title}</div>
                                <div>${arg.event.extendedProps.day}</div>
                                <div>${arg.event.startTime} - ${arg.event.endTime}</div>
                            </div>
                        `
                    };
                },
                eventClassNames: function(arg) {
                    // Add a class to disabled events
                    return arg.event.extendedProps.is_active == 0 ? ['disabled-event'] : [];
                }
            });
            
            window.scheduleManagerCalendar.render();
        }

        // Show calendar view section
        function showCalendarView() {
            console.log("Showing calendar view");
            
            // Hide all content sections first
            document.getElementById('scheduleManager').style.display = 'none';
            document.getElementById('calendarView').style.display = 'none';
            document.getElementById('bookingManager').style.display = 'none';
            
            // Then show only the calendar view
            document.getElementById('calendarView').style.display = 'block';
            
            // Check if FullCalendar is available
            if (typeof FullCalendar === 'undefined') {
                console.error("FullCalendar library is not loaded");
                document.getElementById('mainCalendar').innerHTML = 
                    '<div style="color:red;padding:20px;border:1px solid red;">Error: Calendar library not loaded</div>';
                return;
            }
            
            // Check if calendar container exists
            const calendarEl = document.getElementById('mainCalendar');
            if (!calendarEl) {
                console.error("Calendar container element not found");
                return;
            }
            
            console.log("Calendar container found, initializing with delay");
            
            // Initialize calendar with a delay to ensure the element is visible
            setTimeout(() => {
                initializeCalendar();
            }, 500);
            
            // Update URL hash for better navigation
            window.location.hash = 'calendar';
        }

        // Initialize the main calendar view
        function initializeCalendar() {
            const calendarEl = document.getElementById('mainCalendar');
            if (!calendarEl) {
                console.error('Calendar element not found');
                return;
            }
            
            console.log('Initializing calendar, container:', calendarEl);
            
            // Ensure the container is visible
            calendarEl.style.display = 'block';
            calendarEl.style.height = '600px';
            
            // Destroy existing calendar if it exists
            if (window.mainCalendar) {
                console.log('Destroying existing calendar instance');
                window.mainCalendar.destroy();
            }
            
            // Try with a test event first to see if the calendar renders at all
            const testEvent = {
                title: 'Test Event',
                start: new Date(),
                allDay: false,
                backgroundColor: '#4CAF50'
            };
            
            console.log('Creating new calendar instance');
            window.mainCalendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                slotMinTime: '08:00:00',
                slotMaxTime: '20:00:00',
                height: '600px',
                allDaySlot: false,
                slotDuration: '00:30:00',
                events: [testEvent], // Start with a test event
                eventDidMount: function(info) {
                    console.log('Event mounted:', info.event.title);
                }
            });
            
            console.log('Rendering calendar');
            window.mainCalendar.render();
            
            // After ensuring the calendar renders with the test event,
            // load the actual events with a delay
            setTimeout(() => {
                loadCalendarEvents();
            }, 1000);
            
            // Force a resize
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
                console.log('Resize event triggered');
            }, 200);
        }

        // Function to load the actual events
        function loadCalendarEvents() {
            if (!window.mainCalendar) {
                console.error('Calendar not initialized');
                return;
            }
            
            console.log('Loading actual events');
            
            // Remove test event
            window.mainCalendar.removeAllEvents();
            
            // Fetch schedule data
            fetch('get_schedules.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Received schedule data:', data);
                    
                    // Convert schedules to calendar events
                    const events = [];
                    
                    if (Array.isArray(data)) {
                        data.forEach(schedule => {
                            // Get day number (0 = Sunday, 1 = Monday, etc.)
                            const dayMap = {'Sunday': 0, 'Monday': 1, 'Tuesday': 2, 'Wednesday': 3, 'Thursday': 4, 'Friday': 5, 'Saturday': 6};
                            const dayNumber = dayMap[schedule.day];
                            
                            if (dayNumber === undefined) {
                                console.warn('Invalid day value:', schedule.day);
                                return; // Skip this event
                            }
                            
                            // Calculate dates for current week
                            const today = new Date();
                            const currentDay = today.getDay(); // 0 = Sunday, 1 = Monday, etc.
                            const difference = dayNumber - currentDay;
                            
                            // Clone today and adjust to target day
                            const targetDate = new Date(today);
                            targetDate.setDate(today.getDate() + difference);
                            
                            // Format the date as YYYY-MM-DD
                            const dateStr = targetDate.toISOString().split('T')[0];
                            
                            // Create event
                            const event = {
                                id: schedule.id ? schedule.id.toString() : 'unknown',
                                title: `${schedule.user_type.charAt(0).toUpperCase() + schedule.user_type.slice(1)} Slot`,
                                start: `${dateStr}T${schedule.start_time}`,
                                end: `${dateStr}T${schedule.end_time}`,
                                backgroundColor: schedule.is_active == 1 ? getColorForUserType(schedule.user_type) : '#cccccc',
                                borderColor: schedule.is_active == 1 ? getColorForUserType(schedule.user_type) : '#999999',
                                extendedProps: {
                                    day: schedule.day,
                                    user_type: schedule.user_type,
                                    startTime: schedule.start_time, 
                                    endTime: schedule.end_time,
                                    is_active: schedule.is_active
                                }
                            };
                            
                            events.push(event);
                        });
                    }
                    
                    console.log('Adding events to calendar:', events);
                    window.mainCalendar.addEventSource(events);
                })
                .catch(error => {
                    console.error('Error fetching schedules for calendar:', error);
                    // Add a message in the calendar
                    const calendarEl = document.getElementById('mainCalendar');
                    if (calendarEl) {
                        calendarEl.innerHTML += 
                            '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:red;padding:20px;background:white;border:1px solid red;z-index:100;">' +
                            'Error loading schedule data: ' + error.message + '</div>';
                    }
                });
        }

        // Helper function to calculate end time for bookings
        function calculateEndTime(date, time) {
            const [hours, minutes] = time.split(':').map(Number);
            const endDate = new Date(`${date}T${time}`);
            endDate.setMinutes(endDate.getMinutes() + 30); // Assuming 30 min slots
            return endDate.toISOString().replace(/\.\d{3}Z$/, '');
        }

        // Show event details popup
        function showEventDetails(event) {
            // Create popup for event details
            let popupContent = '';
            const isBooking = event.extendedProps.type === 'booking';
            
            if (isBooking) {
                popupContent = `
                    <h3>Booking Details</h3>
                    <p><strong>User:</strong> ${event.title.split(' - ')[1]}</p>
                    <p><strong>Service:</strong> ${event.title.split(' - ')[0]}</p>
                    <p><strong>Status:</strong> ${event.extendedProps.status}</p>
                    <button onclick="closeEventPopup()">Close</button>
                `;
            } else {
                popupContent = `
                    <h3>Schedule Slot</h3>
                    <p><strong>Day:</strong> ${event.extendedProps.day}</p>
                    <p><strong>Time:</strong> ${event.extendedProps.startTime} - ${event.extendedProps.endTime}</p>
                    <p><strong>Provider:</strong> ${event.extendedProps.user_type}</p>
                    <button onclick="editSchedule(${event.id})">Edit</button>
                    <button onclick="closeEventPopup()">Close</button>
                `;
            }
            
            // Create and show popup
            const popup = document.createElement('div');
            popup.className = 'event-popup';
            popup.style.position = 'fixed';
            popup.style.top = '50%';
            popup.style.left = '50%';
            popup.style.transform = 'translate(-50%, -50%)';
            popup.style.backgroundColor = 'white';
            popup.style.padding = '20px';
            popup.style.borderRadius = '8px';
            popup.style.boxShadow = '0 4px 20px rgba(0,0,0,0.2)';
            popup.style.zIndex = '1500';
            popup.innerHTML = popupContent;
            
            document.body.appendChild(popup);
        }

        // Close event details popup
        function closeEventPopup() {
            const popup = document.querySelector('.event-popup');
            if (popup) {
                popup.remove();
            }
        }

        // Show booking manager section
        function showBookingManager() {
            // Hide all content sections first
            document.getElementById('scheduleManager').style.display = 'none';
            document.getElementById('calendarView').style.display = 'none';
            document.getElementById('bookingManager').style.display = 'none';
            
            // Then show only the booking manager
            document.getElementById('bookingManager').style.display = 'block';
            
            // Load the bookings data
            loadBookings();
            
            // Update URL hash for better navigation
            window.location.hash = 'bookings';
        }

        function loadBookings() {
            const bookingsList = document.getElementById('bookingsList');
            const noBookingsMessage = document.getElementById('noBookingsMessage');
            
            // Show loading indicator
            bookingsList.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">Loading bookings...</td></tr>';
            
            // Add a timestamp to bust cache
            const timestamp = new Date().getTime();
            
            // Fetch booking data
            fetch('get_bookings.php?t=' + timestamp)
                .then(response => {
                    console.log('Response status:', response.status);
                    
                    if (!response.ok) {
                        throw new Error(`Server returned status ${response.status}: ${response.statusText}`);
                    }
                    
                    // First try to get the response as text
                    return response.text().then(text => {
                        console.log('Raw response:', text.substring(0, 200)); // Log first 200 chars
                        
                        // Try to parse as JSON
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error(`Invalid JSON response: ${text.substring(0, 100)}...`);
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed data:', data);
                    
                    // Check if the response is an error object
                    if (data && typeof data === 'object' && data.error) {
                        throw new Error(data.message || 'Unknown server error');
                    }
                    
                    // Check if data is an array
                    if (!Array.isArray(data)) {
                        throw new Error('Invalid response format: expected an array of bookings');
                    }
                    
                    if (data.length === 0) {
                        // Show no bookings message
                        bookingsList.innerHTML = '';
                        noBookingsMessage.style.display = 'block';
                        return;
                    }
                    
                    noBookingsMessage.style.display = 'none';
                    let html = '';
                    
                    // Create rows for each booking
                    data.forEach(booking => {
                        let statusClass = '';
                        switch(booking.status ? booking.status.toLowerCase() : '') {
                            case 'pending': statusClass = 'status-pending'; break;
                            case 'confirmed': statusClass = 'status-confirmed'; break;
                            case 'completed': statusClass = 'status-completed'; break;
                            case 'cancelled': statusClass = 'status-cancelled'; break;
                            default: statusClass = '';
                        }
                        
                        // Determine if there's a medical report
                        const hasMedicalReport = booking.has_medical_report == 1;
                        const medicalReportBtn = hasMedicalReport ? 
                            `<button class="booking-action-btn" onclick="viewMedicalReport(${booking.id})">
                                <i class="fas fa-file-medical"></i> View
                            </button>` : 
                            '<span class="text-muted">None</span>';
                        
                        html += `
                            <tr data-booking-id="${booking.id}" data-service="${booking.service_type || ''}" data-status="${booking.status ? booking.status.toLowerCase() : ''}">
                                <td>${booking.user_name || 'N/A'}</td>
                                <td>${booking.service_type || 'N/A'}</td>
                                <td>${booking.provider_name || 'Not assigned'}</td>
                                <td>${booking.booking_date || 'N/A'}</td>
                                <td>${booking.booking_time || 'N/A'}</td>
                                <td><span class="status-badge ${statusClass}">${booking.status || 'Unknown'}</span></td>
                                <td>${medicalReportBtn}</td>
                                <td>
                                    <button class="booking-action-btn" onclick="viewBookingDetails(${booking.id})">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    bookingsList.innerHTML = html;
                    
                    // Apply filters
                    filterBookings();
                })
                .catch(error => {
                    console.error('Error loading bookings:', error);
                    bookingsList.innerHTML = `<tr><td colspan="8" style="text-align: center; color: red; padding: 20px;">
                        Error loading bookings: ${error.message || 'An unknown error occurred'}</td></tr>`;
                    
                    // Also show the error in the console for debugging
                    console.log('Full error details:', error);
                });
        }

        function viewBookingDetails(bookingId) {
            // Fetch booking details
            fetch(`get_booking_details.php?id=${bookingId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(booking => {
                    // Get the modal element and its content container
                    const modal = document.getElementById('bookingDetailsModal');
                    const contentContainer = document.getElementById('bookingDetailsContent');
                    
                    // Format status with correct class
                    let statusClass = '';
                    switch(booking.status ? booking.status.toLowerCase() : '') {
                        case 'pending': statusClass = 'status-pending'; break;
                        case 'confirmed': statusClass = 'status-confirmed'; break;
                        case 'completed': statusClass = 'status-completed'; break;
                        case 'cancelled': statusClass = 'status-cancelled'; break;
                        default: statusClass = '';
                    }
                    
                    // Build HTML content for the modal
                    let html = `
                        <div class="booking-details-grid">
                            <div class="booking-detail-item">
                                <h4>Booking ID</h4>
                                <p>#${booking.id}</p>
                            </div>
                            <div class="booking-detail-item">
                                <h4>Status</h4>
                                <p><span class="status-badge ${statusClass}">${booking.status}</span></p>
                            </div>
                            <div class="booking-detail-item">
                                <h4>Service Type</h4>
                                <p>${booking.service_type}</p>
                            </div>
                            <div class="booking-detail-item">
                                <h4>Date & Time</h4>
                                <p>${booking.booking_date} at ${booking.booking_time || 'N/A'}</p>
                            </div>
                            <div class="booking-detail-item">
                                <h4>User</h4>
                                <p>${booking.user_name || 'N/A'}</p>
                            </div>
                            <div class="booking-detail-item">
                                <h4>User Email</h4>
                                <p>${booking.user_email || 'N/A'}</p>
                            </div>
                            <div class="booking-detail-item">
                                <h4>Provider</h4>
                                <p>${booking.provider_name || 'Not assigned'}</p>
                            </div>
                            <div class="booking-detail-item">
                                <h4>Last Updated</h4>
                                <p>${booking.updated_at || 'N/A'}</p>
                            </div>
                        </div>
                    `;
                    
                    // Add notes section if exists
                    if (booking.notes) {
                        html += `
                            <div class="booking-notes">
                                <h4>Additional Notes</h4>
                                <p>${booking.notes}</p>
                            </div>
                        `;
                    }
                    
                    // Update modal content and show it
                    document.querySelector('#bookingDetailsModal h2').textContent = 'Booking Details';
                    contentContainer.innerHTML = html;
                    modal.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching booking details:', error);
                    alert(`Error loading booking details: ${error.message}`);
                });
        }
        
        // Function to view medical report
        function viewMedicalReport(bookingId) {
            // Fetch medical report details
            fetch(`get_medical_report.php?booking_id=${bookingId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(response => {
                    // Get the modal element and reuse the booking details modal
                    const modal = document.getElementById('bookingDetailsModal');
                    const contentContainer = document.getElementById('bookingDetailsContent');
                    
                    if (response.error) {
                        contentContainer.innerHTML = `
                            <div class="alert alert-danger">
                                <p>${response.message || 'Error loading medical notes'}</p>
                            </div>`;
                        document.querySelector('#bookingDetailsModal h2').textContent = 'Medical Notes Error';
                        modal.style.display = 'block';
                        return;
                    }
                    
                    const data = response.data;
                    
                    // Build HTML content for the medical report
                    let html = `
                        <div class="medical-report">
                            <h3>Medical Notes</h3>
                            <div class="report-details">
                                <p><strong>Patient:</strong> ${data.user_name || 'N/A'}</p>
                                <p><strong>Submission Date:</strong> ${data.submission_date || data.created_at || 'N/A'}</p>
                                <p><strong>Report ID:</strong> ${data.id || 'N/A'}</p>
                                <p><strong>Status:</strong> <span class="status-badge">${data.note_status || 'N/A'}</span></p>
                            </div>
                            <div class="report-content">
                                <h4>Medical Information</h4>
                                <div class="medical-info-grid">
                                    <div class="info-section">
                                        <h5>Symptoms</h5>
                                        <p>${data.symptoms || 'None recorded'}</p>
                                    </div>
                                    <div class="info-section">
                                        <h5>Diagnosis</h5>
                                        <p>${data.diagnosis || 'None recorded'}</p>
                                    </div>
                                    <div class="info-section">
                                        <h5>Medication</h5>
                                        <p>${data.medication || 'None prescribed'}</p>
                                    </div>
                                    <div class="info-section">
                                        <h5>Further Steps</h5>
                                        <p>${data.further_steps || 'None recommended'}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Update modal content and show it
                    document.querySelector('#bookingDetailsModal h2').textContent = 'Medical Notes';
                    contentContainer.innerHTML = html;
                    modal.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching medical report:', error);
                    alert(`Error loading medical notes: ${error.message}`);
                });
        }

        function filterBookings() {
            const statusFilter = document.getElementById('bookingStatusFilter').value;
            const serviceFilter = document.getElementById('bookingServiceFilter').value;
            const dateFilter = document.getElementById('bookingDateFilter').value;
            
            // Get all booking rows
            const rows = document.querySelectorAll('#bookingsList tr');
            let visibleCount = 0;
            
            // Apply filters
            rows.forEach(row => {
                const status = row.dataset.status;
                const service = row.dataset.service;
                const date = row.cells[4] ? row.cells[4].textContent : '';
                
                const statusMatch = statusFilter === 'all' || (status && status === statusFilter);
                const serviceMatch = serviceFilter === 'all' || (service && service.toLowerCase().includes(serviceFilter.toLowerCase()));
                const dateMatch = !dateFilter || (date && date === dateFilter);
                
                if (statusMatch && serviceMatch && dateMatch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show/hide no bookings message
            const noBookingsMessage = document.getElementById('noBookingsMessage');
            if (visibleCount === 0 && rows.length > 0) {
                noBookingsMessage.style.display = 'block';
            } else {
                noBookingsMessage.style.display = 'none';
            }
        }

        // Update schedule display based on filter
        function updateScheduleDisplay() {
            const userType = document.getElementById('userTypeFilter').value;
            
            // Update the calendar if it exists
            if (window.scheduleManagerCalendar) {
                window.scheduleManagerCalendar.refetchEvents();
            }
            
            // Filter the schedule cards
            document.querySelectorAll('.schedule-card').forEach(card => {
                if (userType === 'all' || card.dataset.userType === userType.slice(0, -1)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Helper function to get color for user type
        function getColorForUserType(userType) {
            switch(userType) {
                case 'supporter': return '#4CAF50';
                case 'advisor': return '#2196F3';
                case 'counselor': return '#FF9800';
                default: return '#663399';
            }
        }

        // Fix the saveSchedule function to handle both adds and updates
        function saveSchedule() {
            // Get form values
            const scheduleId = document.getElementById('scheduleId').value;
            const day = document.getElementById('day').value;
            const startTime = document.getElementById('startTime').value;
            const endTime = document.getElementById('endTime').value;
            const isActive = document.getElementById('isActive').value;
            
            // Validate required fields
            if (!day || !startTime || !endTime) {
                alert('Please fill all required fields');
                return;
            }
            
            // Log data being sent (for debugging)
            console.log('Saving schedule with data:', {
                scheduleId, day, startTime, endTime, isActive, 
                action: scheduleId ? 'update' : 'create'
            });
            
            // Create form data for the request
            const formData = new FormData();
            formData.append('day', day);
            formData.append('startTime', startTime);
            formData.append('endTime', endTime);
            formData.append('isActive', isActive);
            
            // Determine if this is an add or update operation
            if (scheduleId) {
                formData.append('scheduleId', scheduleId);
                formData.append('action', 'update');
            } else {
                formData.append('action', 'create');
                // Ensure any additional required fields are set for new schedules
                formData.append('is_recurring', '1'); // Add recurring flag if needed by backend
            }
            
            // Show loading state
            const saveButton = document.getElementById('saveScheduleBtn');
            const originalButtonText = saveButton.textContent;
            saveButton.disabled = true;
            saveButton.textContent = 'Saving...';
            
            // Send AJAX request using fetch API with improved error handling
            fetch('save_schedule.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Success response:', data);
                
                if (data.success) {
                    // Show success message
                    alert(scheduleId ? 'Schedule updated successfully' : 'Schedule created successfully');
                    
                    // Close the modal
                    closeModal();
                    
                    // Refresh the schedule display
                    refreshScheduleList();
                    
                    // Also refresh calendar if it exists
                    if (window.scheduleManagerCalendar) {
                        window.scheduleManagerCalendar.refetchEvents();
                    }
                } else {
                    // Show error message
                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error saving schedule:', error);
                alert('Error saving schedule: ' + error.message);
            })
            .finally(() => {
                // Reset button state
                saveButton.disabled = false;
                saveButton.textContent = originalButtonText;
            });
        }

        // Function to refresh the schedule list
        function refreshScheduleList() {
            const scheduleList = document.getElementById('scheduleList');
            scheduleList.innerHTML = '<p>Loading schedules...</p>';
            
            // Fetch schedules from the server
            fetch('get_schedules.php')
                .then(response => response.json())
                .then(schedules => {
                    if (schedules.error) {
                        scheduleList.innerHTML = `<p class="error">${schedules.error}</p>`;
                        return;
                    }
                    
                    if (schedules.length === 0) {
                        scheduleList.innerHTML = '<p>No schedules found. Add a new schedule.</p>';
                        return;
                    }
                    
                    let html = '';
                    
                    // Create schedule cards with consistent format - matching the initial PHP rendering
                    schedules.forEach(schedule => {
                        const statusClass = schedule.is_active == 1 ? 'active' : 'inactive';
                        const statusText = schedule.is_active == 1 ? 'Active' : 'Inactive';
                        
                        html += `
                            <div class="schedule-card ${statusClass}" data-user-type="${schedule.user_type || 'general'}">
                                <h3>${schedule.day}</h3>
                                <p><strong>Time:</strong> ${schedule.start_time} - ${schedule.end_time}</p>
                                <p><strong>Status:</strong> <span class="status-badge ${statusClass}">${statusText}</span></p>
                                <p><strong>Note:</strong> <span class="availability-note">This is a standard time slot. Providers must individually confirm their availability in their own dashboards.</span></p>
                                <div class="card-actions">
                                    <button onclick="editSchedule(${schedule.id})">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="${schedule.is_active == 1 ? 'disable-btn' : 'enable-btn'}" onclick="toggleScheduleStatus(${schedule.id}, '${schedule.is_active}')">
                                        <i class="fas ${schedule.is_active == 1 ? 'fa-toggle-off' : 'fa-toggle-on'}"></i> ${schedule.is_active == 1 ? 'Disable' : 'Enable'}
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    
                    scheduleList.innerHTML = html;
                    
                    // Apply filter if needed
                    updateScheduleDisplay();
                    
                    // Update the days without time slots
                    updateDaysWithoutSlots(schedules);
                })
                .catch(error => {
                    console.error('Error fetching schedules:', error);
                    scheduleList.innerHTML = `<p class="error">Error loading schedules: ${error.message}</p>`;
                });
        }
        
        // Function to update days without time slots
        function updateDaysWithoutSlots(schedules) {
            const daysList = document.getElementById('daysList');
            const allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            
            // Create a set of days with slots
            const daysWithSlots = new Set();
            schedules.forEach(schedule => {
                daysWithSlots.add(schedule.day);
            });
            
            // Find days without slots
            const daysWithoutSlots = allDays.filter(day => !daysWithSlots.has(day));
            
            if (daysWithoutSlots.length === 0) {
                daysList.innerHTML = '<p>All days have at least one time slot defined.</p>';
            } else {
                let html = '<p>The following days have no time slots defined:</p><div>';
                daysWithoutSlots.forEach(day => {
                    html += `<span class="day-pill">${day}</span>`;
                });
                html += '</div>';
                daysList.innerHTML = html;
            }
        }

        // Function to format time for display
        function formatTime(timeStr) {
            const time = new Date(`2000-01-01T${timeStr}`);
            return time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        // Function to open modal for adding a new schedule
        function openAddModal() {
            // Reset the form
            document.getElementById('scheduleForm').reset();
            document.getElementById('scheduleId').value = '';
            document.getElementById('isActive').value = '1'; // Set default to active
            document.getElementById('modalTitle').textContent = 'Add New Schedule';
            document.getElementById('saveButton').textContent = 'Save Schedule';
            openModal();
        }

        // Function to edit an existing schedule
        function editSchedule(id) {
            // Show some loading feedback
            const saveButton = document.getElementById('saveButton');
            if (saveButton) saveButton.disabled = true;
            
            // Fetch the schedule details
            fetch(`get_schedule.php?id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(schedule => {
                    if (schedule.error) {
                        alert(schedule.error);
                        return;
                    }
                    
                    // Ensure all required form elements exist before trying to populate them
                    const scheduleIdField = document.getElementById('scheduleId');
                    const dayField = document.getElementById('day');
                    const startTimeField = document.getElementById('startTime');
                    const endTimeField = document.getElementById('endTime');
                    const isActiveField = document.getElementById('isActive');
                    
                    if (!scheduleIdField || !dayField || !startTimeField || !endTimeField || !isActiveField) {
                        console.error('One or more form fields not found');
                        alert('Error: Form not completely loaded. Please try again.');
                        return;
                    }
                    
                    // Populate the form with the schedule details
                    scheduleIdField.value = schedule.id;
                    dayField.value = schedule.day;
                    
                    // Format time values correctly (remove seconds if present)
                    startTimeField.value = schedule.start_time.substring(0, 5);
                    endTimeField.value = schedule.end_time.substring(0, 5);
                    
                    isActiveField.value = schedule.is_active;
                    
                    // Update modal title and button text
                    const modalTitle = document.getElementById('modalTitle');
                    const saveScheduleBtn = document.getElementById('saveScheduleBtn');
                    
                    if (modalTitle) modalTitle.textContent = 'Edit Schedule';
                    if (saveScheduleBtn) saveScheduleBtn.textContent = 'Update Schedule';
                    
                    // Open the modal
                    openModal();
                })
                .catch(error => {
                    console.error('Error fetching schedule:', error);
                    alert(`Error loading schedule details: ${error.message}`);
                })
                .finally(() => {
                    // Re-enable the save button
                    if (saveButton) saveButton.disabled = false;
                });
        }

        // Function to open the modal
        function openModal() {
            const modal = document.getElementById('scheduleModal');
            if (modal) {
                modal.style.display = 'block';
            } else {
                console.error('Schedule modal element not found');
                alert('Error: Could not open the edit form. Please refresh the page and try again.');
            }
        }

        // Function to toggle schedule status
        function toggleScheduleStatus(id, currentStatus) {
            // Convert string to number if needed
            const isActive = parseInt(currentStatus);
            const newStatus = isActive === 1 ? 0 : 1;
            const action = newStatus === 1 ? "enable" : "disable";
            
            if (!confirm(`Are you sure you want to ${action} this time slot?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('scheduleId', id);
            formData.append('isActive', newStatus);
            formData.append('action', 'updateStatus');
            
            // Add required fields to avoid "Missing required fields" error
            // Get the slot details from the DOM
            const card = document.querySelector(`.schedule-card [onclick*="toggleScheduleStatus(${id}"]`).closest('.schedule-card');
            const day = card.querySelector('h3').textContent.trim();
            const timeText = card.querySelector('p:nth-child(2)').textContent;
            const timeMatch = timeText.match(/(\d{2}:\d{2}) - (\d{2}:\d{2})/);
            const startTime = timeMatch ? timeMatch[1] : '09:00';
            const endTime = timeMatch ? timeMatch[2] : '10:00';
            const userTypeText = card.querySelector('p:nth-child(3)').textContent;
            const userType = userTypeText.toLowerCase().includes('counselor') ? 'counselor' : 
                             userTypeText.toLowerCase().includes('supporter') ? 'supporter' : 'advisor';
            
            // Add these required fields
            formData.append('day', day);
            formData.append('startTime', startTime);
            formData.append('endTime', endTime);
            formData.append('userType', userType);
            
            // Show loading state
            const toggleBtn = card.querySelector(`[onclick*="toggleScheduleStatus(${id}"]`);
            toggleBtn.disabled = true;
            toggleBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            
            fetch('save_schedule.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Refresh the schedule list instead of manually updating UI
                    refreshScheduleList();
                    
                    // Also refresh calendar if it exists
                    if (window.scheduleManagerCalendar) {
                        window.scheduleManagerCalendar.refetchEvents();
                    }
                } else {
                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error toggling schedule status:', error);
                alert('Error toggling status: ' + error.message);
            })
            .finally(() => {
                toggleBtn.disabled = false;
            });
        }

        // Show add schedule modal with proper initialization
        function showAddScheduleModal() {
            // Reset form
            document.getElementById('scheduleForm').reset();
            document.getElementById('scheduleId').value = '';
            
            // Set modal title and button text
            document.getElementById('modalTitle').textContent = 'Define New Time Slot';
            document.getElementById('modalDescription').textContent = 'Define a standard time slot that service providers can later mark themselves as available for.';
            document.getElementById('saveScheduleBtn').textContent = 'Define Time Slot';
            
            // Generate time options for start time
            const startTimeSelect = document.getElementById('startTime');
            startTimeSelect.innerHTML = '';
            for (let h = 8; h <= 17; h++) {
                for (let m = 0; m < 60; m += 30) {
                    if (h === 17 && m > 0) continue; // Don't go past 17:30
                    
                    const hour = h.toString().padStart(2, '0');
                    const minute = m.toString().padStart(2, '0');
                    const timeValue = `${hour}:${minute}`;
                    
                    const option = document.createElement('option');
                    option.value = timeValue;
                    option.textContent = timeValue;
                    startTimeSelect.appendChild(option);
                }
            }
            
            // Update end time options based on start time
            updateEndTimeOptions();
            
            // Clear warnings
            document.getElementById('conflictWarning').style.display = 'none';
            document.getElementById('pastTimeWarning').style.display = 'none';
            
            // Show the modal
            document.getElementById('scheduleModal').style.display = 'block';
        }

        // Update end time options based on selected start time
        function updateEndTimeOptions() {
            const startTimeSelect = document.getElementById('startTime');
            const endTimeSelect = document.getElementById('endTime');
            
            if (!startTimeSelect || !endTimeSelect) return;
            
            const startTime = startTimeSelect.value;
            const [startHour, startMinute] = startTime.split(':').map(Number);
            
            // Clear existing options
            endTimeSelect.innerHTML = '';
            
            // Add options starting from 30 minutes after start time
            let nextHour = startHour;
            let nextMinute = startMinute + 30;
            
            // Adjust if we need to roll over to the next hour
            if (nextMinute >= 60) {
                nextHour++;
                nextMinute = 0;
            }
            
            // Generate options until 18:00
            for (let h = nextHour; h <= 18; h++) {
                const startMin = (h === nextHour) ? nextMinute : 0;
                for (let m = startMin; m < 60; m += 30) {
                    if (h === 18 && m > 0) continue; // Don't go past 18:00
                    
                    const hour = h.toString().padStart(2, '0');
                    const minute = m.toString().padStart(2, '0');
                    const timeValue = `${hour}:${minute}`;
                    
                    const option = document.createElement('option');
                    option.value = timeValue;
                    option.textContent = timeValue;
                    endTimeSelect.appendChild(option);
                }
            }
        }

        // Check for time slot conflicts
        function checkSlotConflicts() {
            const day = document.getElementById('day').value;
            const startTime = document.getElementById('startTime').value;
            const endTime = document.getElementById('endTime').value;
            const scheduleId = document.getElementById('scheduleId').value;
            
            const conflictWarning = document.getElementById('conflictWarning');
            const saveButton = document.getElementById('saveScheduleBtn');
            
            // Basic validation - don't check if we don't have complete data
            if (!day || !startTime || !endTime) {
                return;
            }
            
            // Create form data for checking conflicts
            const formData = new FormData();
            formData.append('day', day);
            formData.append('startTime', startTime);
            formData.append('endTime', endTime);
            // Use a default userType for all slots
            formData.append('userType', 'general');
            if (scheduleId) {
                formData.append('excludeId', scheduleId);
            }
            
            // Send request to check conflicts
            fetch('check_slot_conflicts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.hasConflict) {
                    conflictWarning.style.display = 'block';
                    document.getElementById('conflictMessage').textContent = 
                        `This time slot conflicts with an existing time slot. Time slots cannot overlap.`;
                    saveButton.disabled = true;
                } else {
                    conflictWarning.style.display = 'none';
                    saveButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error checking for conflicts:', error);
                conflictWarning.style.display = 'none';
                saveButton.disabled = false; // Allow saving if the conflict check fails
            });
        }

        // Check if time has already passed today
        function checkPastTime() {
            const day = document.getElementById('day').value;
            const startTime = document.getElementById('startTime').value;
            const pastTimeWarning = document.getElementById('pastTimeWarning');
            
            // Get current day of week
            const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const currentDay = daysOfWeek[new Date().getDay()];
            const currentTime = new Date().toTimeString().slice(0, 5); // Get current time in HH:MM format
            
            // Check if selected day is today and the time has passed
            if (day === currentDay && startTime < currentTime) {
                pastTimeWarning.style.display = 'block';
            } else {
                pastTimeWarning.style.display = 'none';
            }
        }

        // Close any open modals
        function closeModal() {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.style.display = 'none';
            });
        }

        // Fix the getCalendarEvents function to handle missing/undefined values
        function getCalendarEvents(schedules) {
            const events = [];
            const userTypeFilter = document.getElementById('userTypeFilter');
            const userType = userTypeFilter ? userTypeFilter.value : 'all';
            
            if (!Array.isArray(schedules)) {
                console.error('Invalid schedules data:', schedules);
                return [];
            }
            
            schedules.forEach(schedule => {
                // Skip if filtering and not matching
                if (userType !== 'all' && userType !== schedule.user_type + 's') {
                    return;
                }
                
                // Get day number (0 = Sunday, 1 = Monday, etc.)
                const dayMap = {'Sunday': 0, 'Monday': 1, 'Tuesday': 2, 'Wednesday': 3, 'Thursday': 4, 'Friday': 5, 'Saturday': 6};
                const dayNumber = dayMap[schedule.day];
                
                if (dayNumber === undefined) {
                    console.warn('Invalid day value:', schedule.day);
                    return; // Skip this event
                }
                
                // Default values for missing properties
                const startTime = schedule.start_time || '08:00';
                const endTime = schedule.end_time || '09:00';
                const isActive = schedule.is_active !== undefined ? schedule.is_active : 1;
                const userTypeValue = schedule.user_type || 'unknown';
                
                // Create event for this specific week's occurrence
                const event = {
                    id: schedule.id ? schedule.id.toString() : 'unknown',
                    title: `${userTypeValue.charAt(0).toUpperCase() + userTypeValue.slice(1)} Slot`,
                    daysOfWeek: [dayNumber],
                    startTime: startTime,
                    endTime: endTime,
                    backgroundColor: isActive == 1 ? getColorForUserType(userTypeValue) : '#cccccc',
                    borderColor: isActive == 1 ? getColorForUserType(userTypeValue) : '#999999',
                    textColor: isActive == 1 ? '#ffffff' : '#666666',
                    extendedProps: {
                        day: schedule.day || 'Unknown',
                        startTime: startTime,
                        endTime: endTime,
                        user_type: userTypeValue,
                        is_active: isActive
                    }
                };
                
                events.push(event);
            });
            
            return events;
        }

        // Update the schedule list in the DOM
        function updateScheduleList(schedules) {
            const scheduleList = document.getElementById('scheduleList');
            if (!scheduleList) return;
            
            // Clear current list
            scheduleList.innerHTML = '';
            
            // Filter by user type
            const userType = document.getElementById('userTypeFilter').value;
            const filteredSchedules = schedules.filter(schedule => 
                userType === 'all' || schedule.user_type === userType.replace('s', '')
            );
            
            // Add each schedule to the list
            filteredSchedules.forEach(schedule => {
                const statusClass = schedule.is_active == 1 ? 'active' : 'inactive';
                const statusText = schedule.is_active == 1 ? 'Active' : 'Inactive';
                
                const card = document.createElement('div');
                card.className = `schedule-card ${statusClass}`;
                card.dataset.userType = schedule.user_type;
                
                card.innerHTML = `
                    <h3>${schedule.day}</h3>
                    <p><strong>Time:</strong> ${schedule.start_time} - ${schedule.end_time}</p>
                    <p><strong>Provider Type:</strong> ${schedule.user_type.charAt(0).toUpperCase() + schedule.user_type.slice(1)}</p>
                    <p><strong>Status:</strong> <span class="status-badge ${statusClass}">${statusText}</span></p>
                    <p><strong>Note:</strong> <span class="availability-note">This is a standard time slot. Providers must individually confirm their availability in their own dashboards.</span></p>
                    <div class="card-actions">
                        <button onclick="editSchedule(${schedule.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="${schedule.is_active == 1 ? 'disable-btn' : 'enable-btn'}" onclick="toggleScheduleStatus(${schedule.id}, '${schedule.is_active}')">
                            <i class="fas ${schedule.is_active == 1 ? 'fa-toggle-off' : 'fa-toggle-on'}"></i> ${schedule.is_active == 1 ? 'Disable' : 'Enable'}
                        </button>
                    </div>
                `;
                
                scheduleList.appendChild(card);
            });
        }

        // Delete schedule
        function deleteSchedule(id, element) {
            // Show loading state
            element.style.opacity = '0.5';
            element.style.pointerEvents = 'none';
            
            fetch('delete_schedule.php?id=' + id, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the UI - remove the slot
                    element.remove();
                    
                    // Close any open modal related to this slot
                    closeModal();
                    
                    // Refresh the calendar view to reflect changes
                    if (window.scheduleManagerCalendar) {
                        window.scheduleManagerCalendar.refetchEvents();
                    }
                    
                    // Show success message
                    alert('Schedule deleted successfully');
                } else {
                    // Reset the slot's appearance if deletion failed
                    element.style.opacity = '1';
                    element.style.pointerEvents = 'auto';
                    
                    alert('Error deleting schedule: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error deleting schedule:', error);
                
                // Reset the slot's appearance
                element.style.opacity = '1';
                element.style.pointerEvents = 'auto';
                
                alert('Error deleting schedule. Please try again.');
            });
        }

        // Show user status management modal
        function showUserStatusModal(userId, userType, currentStatus) {
            document.getElementById('userId').value = userId;
            document.getElementById('userTypeField').value = userType;
            document.getElementById('userStatus').value = currentStatus;
            document.getElementById('userManagementModal').style.display = 'block';
        }

        // Update user status
        function updateUserStatus() {
            const userId = document.getElementById('userId').value;
            const userType = document.getElementById('userTypeField').value;
            const status = document.getElementById('userStatus').value;
            
            const formData = new FormData();
            formData.append('userId', userId);
            formData.append('userType', userType);
            formData.append('status', status);
            
            fetch('toggle_user_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`User ${status === '1' ? 'enabled' : 'disabled'} successfully`);
                    closeModal();
                    // Reload the relevant user management page
                    if (window.location.href.includes('manage_' + userType + 's.php')) {
                        window.location.reload();
                    }
                } else {
                    alert('Error changing user status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error updating user status:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Document ready function with all event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Set up event listeners
            const userTypeFilter = document.getElementById('userTypeFilter');
            if (userTypeFilter) {
                userTypeFilter.addEventListener('change', updateScheduleDisplay);
            }
            
            // Handle clicks outside modal to close it
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('scheduleModal');
                if (modal && event.target === modal) {
                    closeModal();
                }
            });
            
            // Add escape key handler for modal
            window.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
            
            // Show initial view if hash exists
            if (window.location.hash === '#schedule') {
                showScheduleManager();
            } else if (window.location.hash === '#calendar') {
                showCalendarView();
            } else if (window.location.hash === '#bookings') {
                showBookingManager();
            }
        });

        // Add this to your JavaScript section, after window load event:
        document.addEventListener('DOMContentLoaded', function() {
            // Handle direct view parameters
            <?php if($view === 'scheduleManager'): ?>
                showScheduleManager();
            <?php elseif($view === 'calendarView'): ?>
                showCalendarView();
            <?php elseif($view === 'bookingManager'): ?>
                showBookingManager();
            <?php endif; ?>
        });

        // Function to toggle schedule status (enable/disable)
        function toggleScheduleStatus(id, currentStatus) {
            // Convert string to number if needed
            const isActive = parseInt(currentStatus);
            const newStatus = isActive === 1 ? 0 : 1;
            const action = newStatus === 1 ? "enable" : "disable";
            
            if (!confirm(`Are you sure you want to ${action} this time slot?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('scheduleId', id);
            formData.append('isActive', newStatus);
            formData.append('action', 'updateStatus');
            
            fetch('save_schedule.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Refresh the schedule list to show updated status
                    refreshScheduleList();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error toggling schedule status:', error);
                alert('Error toggling status: ' + error.message);
            });
        }
    </script>
</body>
</html>