<?php
session_start();
require_once 'DBS.inc.php';


$notificationCount = 0;
$notifications = [];

if (isset($_SESSION['email'])) {
    try {
        // Get unread notifications count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM bookings 
            WHERE user_email = ? 
            AND (status = 'follow_up' OR status = 'follow_up_completed')
            AND (notification_read = 0 OR notification_read IS NULL)
        ");
        $stmt->execute([$_SESSION['email']]);
        $notificationCount = (int)$stmt->fetchColumn();

        // Get notifications
        $stmt = $pdo->prepare("
            SELECT *, 
                   DATE_FORMAT(booking_date, '%M %d, %Y') as formatted_date,
                   DATE_FORMAT(created_at, '%h:%i %p') as formatted_time
            FROM bookings 
            WHERE user_email = ? 
            AND (status = 'follow_up' OR status = 'follow_up_completed')
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['email']]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error loading notifications: " . $e->getMessage());
    }
}


?>
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

    nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 5%;
        background: #1a1a1a;
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

    .profile-section {
        display: flex;
        align-items: center;
        gap: 1rem;
        position: relative;
    }

    .profile-btn {
        background: #663399;
        color: white;
        padding: 0.5rem 1.5rem;
        border-radius: 20px;
        text-decoration: none;
        cursor: pointer;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        min-width: 200px;
        z-index: 1;
        margin-top: 0.5rem;
        padding: 0.5rem 0;
        transition: all 0.3s ease;
    }

    .dropdown-content.show {
        display: block;
    }

    .dropdown-content a {
        color: #333;
        padding: 12px 16px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: background 0.2s ease;
        cursor: pointer;
    }

    .dropdown-content a:hover {
        background: #f0f0f0;
    }

    .dropdown-content a i {
        font-size: 1.2rem;
        color: #663399;
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
    }

    .logout-message {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #4CAF50;
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    /* Services specific styles */
    .services-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 20px;
    }

    .services-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .services-header h1 {
        color: #333;
        font-size: 2.5rem;
        margin-bottom: 1rem;
    }

    .services-header p {
        color: #666;
        font-size: 1.1rem;
        max-width: 700px;
        margin: 0 auto;
    }

    .tabs-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .tabs-nav {
        display: flex;
        background: #f8f8f8;
        border-bottom: 1px solid #eee;
    }

    .tab-btn {
        padding: 15px 20px;
        border: none;
        background: none;
        cursor: pointer;
        font-size: 16px;
        font-weight: 500;
        color: #666;
        transition: all 0.3s ease;
        flex: 1;
        text-align: center;
    }

    .tab-btn:hover {
        background: #f0f0f0;
    }

    .tab-btn.active {
        background: #663399;
        color: white;
    }

    .tab-content {
        display: none;
        padding: 25px;
    }

    .tab-content.active {
        display: block;
    }

    /* Services list styles */
    .services-list {
        list-style: none;
        padding: 0;
    }

    .service-item {
        border-bottom: 1px solid #eee;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .service-item:last-child {
        border-bottom: none;
    }

    .service-info {
        flex: 1;
    }

    .service-info h3 {
        margin-bottom: 5px;
        color: #333;
    }

    .service-info p {
        color: #666;
        margin: 5px 0;
    }

    .service-actions {
        display: flex;
        gap: 10px;
    }

    .btn {
        padding: 8px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
        transition: background 0.3s ease;
    }

    .btn-primary {
        background: #663399;
        color: white;
    }

    .btn-primary:hover {
        background: #5a2d8a;
    }

    .btn-danger {
        background: #e74c3c;
        color: white;
    }

    .btn-danger:hover {
        background: #c0392b;
    }

    .btn-success {
        background: #27ae60;
        color: white;
    }

    .btn-success:hover {
        background: #219653;
    }

    /* Form styles */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #333;
    }

    .form-control {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 16px;
    }

    .form-control:focus {
        border-color: #663399;
        outline: none;
    }

    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }

    /* Time slots styles */
    .time-slot {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
        flex-wrap: wrap;
        align-items: center;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 5px;
    }

    .time-slot select,
    .time-slot input {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    /* Footer styles */
    footer {
        background-color: #1a1a1a;
        color: #ffffff;
        padding: 3rem 0 1rem;
    }

    .footer-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 5%;
    }

    .footer-section {
        flex: 1;
        min-width: 250px;
        margin-bottom: 2rem;
    }

    .footer-section h3 {
        font-size: 1.2rem;
        margin-bottom: 1rem;
        position: relative;
    }

    .footer-section h3:after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -5px;
        width: 50px;
        height: 2px;
        background: #FF1493;
    }

    .footer-links {
        list-style: none;
    }

    .footer-links li {
        margin-bottom: 0.8rem;
    }

    .footer-links a {
        color: #ddd;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .footer-links a:hover {
        color: #FF1493;
    }

    .social-links {
        display: flex;
        gap: 10px;
        margin-top: 1rem;
    }

    .social-links a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        color: white;
        text-decoration: none;
        transition: transform 0.3s ease;
    }

    .social-links a:hover {
        transform: translateY(-3px);
    }

    .contact-info {
        list-style: none;
    }

    .contact-info li {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 1rem;
        color: #ddd;
    }

    .footer-bottom {
        text-align: center;
        padding-top: 2rem;
        border-top: 1px solid #333;
        margin-top: 1rem;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .tabs-nav {
            flex-direction: column;
        }
        
        .time-slot {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .time-slot > * {
            width: 100%;
        }
    }
</style>
</head>
<!-- Add this to your existing <style> section -->
<style>
    /* ... existing styles ... */

    /* Available Slots Styling */
    .time-slots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        padding: 20px;
    }

    .time-slot {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
    }

    .time-slot:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    }

    .slot-header {
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .slot-day {
        font-size: 1.2em;
        font-weight: bold;
        color: #333;
        margin-bottom: 5px;
    }

    .slot-time {
        color: #666;
        font-size: 1.1em;
    }

    .slot-info {
        margin-bottom: 20px;
    }

    .slot-type {
        font-weight: bold;
        color: #663399;
        margin-bottom: 10px;
        font-size: 1.1em;
    }

    .staff-name {
        color: #555;
        margin-bottom: 10px;
    }

    .book-btn {
        width: 100%;
        padding: 12px;
        background: #663399;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.3s ease;
    }

    .book-btn:hover {
        background: #552288;
    }

    /* Role-specific styling */
    .role-2 { 
        border-left: 4px solid #007bff; /* Advisor */
    }

    .role-3 { 
        border-left: 4px solid #6f42c1; /* Counselor */
    }

    .role-4 { 
        border-left: 4px solid #28a745; /* Support */
    }

    /* Loading indicator */
    .loading-indicator {
        text-align: center;
        padding: 20px;
        color: #666;
    }

    /* Error message */
    .error-message {
        background: #fee;
        color: #c00;
        padding: 15px;
        border-radius: 6px;
        margin: 10px 0;
    }

    /* Info message */
    .info-message {
        background: #f8f9fa;
        color: #666;
        padding: 15px;
        border-radius: 6px;
        margin: 10px 0;
        text-align: center;
    }
    /* Add this to your existing <style> section */

        /* Booking styles */
        .bookings-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .booking-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-left: 4px solid #663399;
        }

        .booking-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .booking-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.2em;
        }

        .booking-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .booking-status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .booking-status.confirmed {
            background: #d4edda;
            color: #155724;
        }

        .booking-status.cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .booking-details {
            margin-bottom: 15px;
        }

        .booking-details p {
            margin: 8px 0;
            color: #555;
        }

        .booking-details strong {
            color: #333;
            margin-right: 5px;
        }

        .booking-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .cancel-booking {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-size: 0.9em;
        }

        .cancel-booking:hover {
            background: #c82333;
        }

        /* Empty state styling */
        .empty-bookings {
            text-align: center;
            padding: 40px 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin: 20px 0;
        }

        .empty-bookings p {
            color: #666;
            margin-bottom: 20px;
        }

        .book-now-btn {
            background: #663399;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-weight: 500;
        }

        .book-now-btn:hover {
            background: #552288;
        }

        /* Loading state */
        .loading-indicator {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .loading-indicator i {
            margin-right: 8px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .booking-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .booking-status {
                align-self: flex-start;
            }

            .booking-actions {
                justify-content: flex-start;
                margin-top: 15px;
            }
        }
        /* Emergency Contacts Container */
.contacts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    padding: 20px;
}

/* Contact Card */
.contact-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border-left: 4px solid #663399;
    position: relative;
    overflow: hidden;
}

.contact-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.contact-card h4 {
    color: #333;
    font-size: 1.3em;
    margin: 0 0 15px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid #eee;
    position: relative;
}

.contact-card h4::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 50px;
    height: 2px;
    background: #663399;
}

/* Contact Details */
.contact-details {
    margin-bottom: 20px;
}

.contact-details p {
    margin: 12px 0;
    color: #555;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.1em;
}

.contact-details i {
    color: #663399;
    width: 20px;
}

/* Contact Actions */
.contact-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.contact-actions button {
    flex: 1;
    padding: 10px 15px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-primary {
    background: #663399;
    color: white;
}

.btn-primary:hover {
    background: #552288;
    transform: translateY(-2px);
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
    transform: translateY(-2px);
}

/* Add Contact Form */
.add-contact-form {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin: 20px 0;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.add-contact-form h3 {
    color: #333;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #eee;
    position: relative;
}

.add-contact-form h3::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 50px;
    height: 2px;
    background: #663399;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #333;
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid #eee;
    border-radius: 8px;
    font-size: 1em;
    transition: all 0.3s ease;
}

.form-group input:focus {
    border-color: #663399;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 51, 153, 0.1);
}

/* Emergency Alert Section */
.emergency-alert-section {
    background: linear-gradient(135deg, #ff3366, #dc3545);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin: 20px 0;
    text-align: center;
}

.emergency-alert-btn {
    background: white;
    color: #dc3545;
    border: none;
    padding: 15px 30px;
    border-radius: 30px;
    font-size: 1.2em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    margin-top: 20px;
}

.emergency-alert-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    background: #f8f9fa;
}

/* Responsive Design */
@media (max-width: 768px) {
    .contacts-grid {
        grid-template-columns: 1fr;
    }

    .contact-actions {
        flex-direction: column;
    }

    .contact-actions button {
        width: 100%;
    }

    .add-contact-form {
        padding: 20px;
    }
}

/* Status Messages */
.alert {
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-info {
    background: #e9ecef;
    color: #495057;
    border: 1px solid #dee2e6;
}

/* Add to your existing <style> section */
/* Notification Styling */
.notification-icon {
    position: relative;
    cursor: pointer;
    padding: 8px;
    color: white;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff4757;
    color: white;
    border-radius: 50%;
    padding: 4px 8px;
    font-size: 12px;
    min-width: 20px;
    text-align: center;
    border: 2px solid #1a1a1a;
    animation: pulse 1.5s infinite;
}

.notification-panel {
    display: none;
    position: absolute;
    top: 60px;
    right: 20px;
    background: white;
    min-width: 350px;
    max-width: 400px;
    border-radius: 12px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.2);
    z-index: 1000;
}

.notification-panel.show {
    display: block;
    animation: slideDown 0.3s ease;
}

.notification-header {
    padding: 15px 20px;
    background: #663399;
    color: white;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-content {
    max-height: 400px;
    overflow-y: auto;
    padding: 10px 0;
}

.notification-item {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    transition: background 0.3s ease;
}

.notification-item:hover {
    background: #f8f9fa;
}

.empty-notification {
    text-align: center;
    padding: 20px;
    color: #666;
}

.error-notification {
    color: #dc3545;
    text-align: center;
    padding: 20px;
}

.btn-book-followup {
    background: #663399;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-book-followup:hover {
    background: #552288;
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
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}
/* ... rest of the notification styles from the previous response ... */
/* Booking Status Groups */
.booking-status-group {
    margin-bottom: 30px;
}

.status-heading {
    color: #333;
    font-size: 1.2em;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #eee;
}

.booking-group {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

/* Status-specific styling */
.status-pending {
    border-left: 4px solid #ffc107;
}

.status-confirmed {
    border-left: 4px solid #28a745;
}

.status-follow_up {
    border-left: 4px solid #17a2b8;
}

.status-follow_up_completed {
    border-left: 4px solid #6610f2;
}

.status-completed {
    border-left: 4px solid #198754;
}

.status-cancelled {
    border-left: 4px solid #dc3545;
}

/* Status badges */
.booking-status {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.9em;
    font-weight: 500;
}

.booking-status.pending { background: #fff3cd; color: #856404; }
.booking-status.confirmed { background: #d4edda; color: #155724; }
.booking-status.follow_up { background: #cff4fc; color: #055160; }
.booking-status.follow_up_completed { background: #e2d9f3; color: #59359a; }
.booking-status.completed { background: #d1e7dd; color: #0f5132; }
.booking-status.cancelled { background: #f8d7da; color: #721c24; }


/* Booking History styles */
.history-filters {
    margin-bottom: 20px;
    padding: 15px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.history-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
    border-left: 4px solid #663399;
}

.history-card:hover {
    transform: translateY(-2px);
}

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.history-date {
    color: #666;
    font-size: 0.9em;
}

.history-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.history-detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.history-detail-item i {
    color: #663399;
    width: 20px;
}

.history-status {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.85em;
    font-weight: 500;
}
</style>

<body>
   

    <nav>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 120" class="logo">
            <g transform="translate(30, 10)">
                <path d="M50 35
                         C45 25, 30 25, 25 35
                         C20 45, 25 55, 50 75
                         C75 55, 80 45, 75 35
                         C70 25, 55 25, 50 35" fill="#FF1493" />
                <path d="M15 55
                         C12 55, 5 58, 5 75
                         C5 82, 8 87, 15 90
                         L25 92
                         C20 85, 18 80, 20 75
                         C22 70, 25 68, 30 70
                         C28 65, 25 62, 20 62
                         C15 62, 15 65, 15 55" fill="#9932CC" />
                <path d="M85 55
                         C88 55, 95 58, 95 75
                         C95 82, 92 87, 85 90
                         L75 92
                         C80 85, 82 80, 80 75
                         C78 70, 75 68, 70 70
                         C72 65, 75 62, 80 62
                         C85 62, 85 65, 85 55" fill="#9932CC" />
                <path d="M45 40
                         Q50 45, 55 40
                         Q52 35, 45 40" fill="#FF69B4" opacity="0.5" />
            </g>
            <text x="150" y="80" font-family="Arial Black, sans-serif" font-weight="900" font-size="60"
                fill="#ffffff">GUARDSPHERE</text>
            <text x="150" y="105" font-family="Arial, sans-serif" font-size="20" fill="#ffffff">GUARDED BY
                GUARDSPHERE.</text>
        </svg>

        <div class="nav-links">
            <a href="home.php">Home</a>
            <a href="Aboutus.php">About Us</a>
            <a href="services.php">Service</a>
            <a href="location.php">Location</a>
            
            
            <!-- Add this right after your nav-links div in the navigation -->
            <!-- Replace your existing notification panel code -->
<!-- filepath: c:\xampp\htdocs\GuardSphere-main\services.php -->
<!-- <div class="notification-icon" onclick="toggleNotifications()">
    <i class="fas fa-bell"></i>
    <?php if ($notificationCount > 0): ?>
        <span class="notification-badge"><?php echo $notificationCount; ?></span>
    <?php endif; ?>
</div>

<div id="notificationPanel" class="notification-panel">
    <div class="notification-header">
        <h3><i class="fas fa-bell"></i> Notifications</h3>
        <?php if ($notificationCount > 0): ?>
            <span class="notification-count"><?php echo $notificationCount; ?> new</span>
        <?php endif; ?>
    </div>
    <div class="notification-content">
        <?php if (isset($_SESSION['email'])): ?>
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item">
                        <div class="notification-message">
                            <?php if ($notification['status'] === 'follow_up'): ?>
                                <i class="fas fa-calendar-plus"></i>
                                A follow-up session is required for your appointment on 
                                <?php echo $notification['formatted_date']; ?>
                            <?php elseif ($notification['status'] === 'follow_up_completed'): ?>
                                <i class="fas fa-check-circle"></i>
                                Your follow-up session has been successfully completed on 
                                <?php echo $notification['formatted_date']; ?>
                            <?php endif; ?>
                        </div>
                        <div class="notification-time">
                            <i class="far fa-clock"></i> 
                            <?php echo $notification['formatted_time']; ?>
                        </div>
                        <?php if ($notification['status'] === 'follow_up'): ?>
                            <div class="notification-actions">
                                <button onclick="bookFollowUp()" class="btn-book-followup">
                                    <i class="fas fa-calendar-check"></i> Book Follow-up
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-notification">
                    <i class="far fa-bell-slash"></i>
                    <p>No new notifications</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-notification">
                <p>Please log in to see notifications</p>
            </div>
        <?php endif; ?>
    </div>
</div>                       -->

            <div class="profile-section">
                <div class="user-avatar" onclick="toggleDropdown()">
                    <?php 
                    if (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
                        echo strtoupper(substr($_SESSION['email'], 0, 1));
                    } else {
                        echo 'G'; // Default letter if no user is logged in
                    }
                    ?>
                </div>
                <div class="dropdown-content" id="profileDropdown">
                    <a href="pro_update.php"><i class="fas fa-user-cog"></i> Manage Profile</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>

        
    </nav>

     

    <div class="services-container">
        <div class="services-header">
            <h1>Our Services</h1>
            <p>We provide comprehensive safety and support services designed to help women feel secure and empowered in every situation.</p>
        </div>

        <div class="tabs-container">
            <div class="tabs-nav">
                <button class="tab-btn active" data-tab="services">Available Services</button>
                <button class="tab-btn" data-tab="bookings">My Bookings</button>
                <button class="tab-btn" data-tab="history">Booking History</button>
              
                
                
                <button class="tab-btn" data-tab="emergency">Emergency Contacts</button>
            </div>

            <div id="services" class="tab-content active">
                <div id="provider-filter-container" class="filter-container">
                    <!-- Filter buttons will be inserted here by JavaScript -->
                </div>
                
                <!-- Add search bar for provider search -->
                <div class="search-container" style="margin: 15px 0; display: flex; max-width: 500px;">
                    <input type="text" id="providerSearchInput" placeholder="Search providers by name..." 
                           style="flex: 1; padding: 10px; border-radius: 4px 0 0 4px; border: 1px solid #ccc; border-right: none;">
                    <button id="clearSearchBtn" style="background: #ddd; border: 1px solid #ccc; border-left: none; 
                            border-radius: 0 4px 4px 0; padding: 0 15px; cursor: pointer; display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div id="schedules-container">
                  
                </div>
            </div>

            <div id="bookings" class="tab-content">
                <h2>Your Bookings</h2>
                <div id="bookings-list">
                    <div class="booking-card">
                        <div class="booking-header">
                            <h3><?php echo $booking['service_name']; ?></h3>
                            <span class="booking-status <?php echo strtolower($booking['status']); ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                        </div>
                        <div class="booking-details">
                            <p><strong>Booked on:</strong> <?php echo $booking['booking_date']; ?></p>
                            <p><strong>Day:</strong> <?php echo $booking['day'] ?? 'N/A'; ?></p>
                            <p><strong>Time:</strong> <?php echo $booking['booking_time'] ?? '-'; ?></p>
                            <p><strong>Provider:</strong> <?php echo $booking['provider_name'] ?? 'Guard'; ?></p>
                            <p><strong>Location:</strong> <?php echo $booking['location'] ?? 'Online'; ?></p>
                        </div>
                        <?php if ($booking['status'] !== 'accepted' && $booking['status'] !== 'completed'): ?>
                            <button class="cancel-booking" onclick="cancelBooking(<?php echo $booking['booking_id']; ?>)">
                                Cancel Booking
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            
           
            

            <div id="history" class="tab-content">
                <h2>Booking History</h2>
                <div id="booking-history-container">
                    <!-- Booking history will be dynamically loaded here -->
                </div>
            </div>

            <div id="emergency" class="tab-content">
                <h2>Emergency Contacts</h2>
                <div id="contacts-list">
                    <p>Loading emergency contacts...</p>
                </div>

                <!-- Add this to the emergency tab section in services.php -->
                <!-- <div class="emergency-message-container" style="margin-bottom: 20px;">
                  <label for="emergency-message" style="display: block; margin-bottom: 5px; font-weight: bold;">Emergency Message:</label>
                  <textarea id="emergency-message" 
                    placeholder="Enter a message to send to your emergency contacts (optional)" 
                    style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc; min-height: 80px;"
                  ></textarea>
                </div> -->

                <!-- Add a single clear emergency button -->
                <!-- <div class="emergency-actions text-center">
                  <button id="sendEmergencyAlertBtn" class="btn btn-danger btn-lg">
                    <i class="fas fa-exclamation-triangle"></i> SEND EMERGENCY ALERT
                  </button>
                </div> -->

                <div class="add-contact-form">
                    <h3>Add New Emergency Contact</h3>
                    <form id="add-contact-form">
                        <div class="form-group">
                            <label for="contact-name">Name:</label>
                            <input type="text" class="form-control" id="contact-name" required>
                        </div>
                        <div class="form-group">
                            <label for="contact-phone">Phone Number:</label>
                            <input type="tel" class="form-control" id="contact-phone" required>
                        </div>
                        <div class="form-group">
                            <label for="contact-email">Email:</label>
                            <input type="email" class="form-control" id="contact-email">
                        </div>
                        <div class="form-group">
                            <label for="contact-relationship">Relationship:</label>
                            <input type="text" class="form-control" id="contact-relationship" required>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="addEmergencyContact()">Add Contact</button>
                    </form>
                </div>

                <!-- Add this HTML anywhere in your emergency tab content -->
                <div class="direct-emergency-button">
                    <h4>Emergency Alert System</h4>
                    <p>Click the button below to send SMS alerts to all your emergency contacts.</p>
                    
                    <form action="send_emergency.php" method="post">
                      <textarea name="message" class="form-control" placeholder="Enter emergency message (optional)"></textarea>
                      <button type="submit" class="btn-danger">SEND EMERGENCY ALERT</button>
                    </form>
                    
                    <div id="emergency-status" class="mt-3" style="display:none;"></div>
                    
                    <script>
                        // Simple direct form submission with AJAX
                        document.getElementById('direct-emergency-form').addEventListener('submit', function(e) {
                            e.preventDefault();
                            
                            // Confirm before sending
                            if(!confirm('Are you sure you want to send emergency alerts to all your contacts?')) {
                                return;
                            }
                            
                            // Show status
                            const status = document.getElementById('emergency-status');
                            status.style.display = 'block';
                            status.className = 'alert alert-info';
                            status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending emergency alerts...';
                            
                            // Get the form data
                            const formData = new FormData(this);
                            
                            // Send direct request to emergency_handler.php
                            fetch('emergency_handler.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.text())
                            .then(text => {
                                console.log('Emergency response:', text);
                                
                                try {
                                    const data = JSON.parse(text);
                                    if (data.success > 0) {
                                        status.className = 'alert alert-success';
                                        status.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                                        
                                        // Show details if available
                                        if (data.details && data.details.length) {
                                            let list = '<ul class="mt-2">';
                                            data.details.forEach(detail => {
                                                list += '<li>' + detail + '</li>';
                                            });
                                            list += '</ul>';
                                            status.innerHTML += list;
                                        }
                                        
                                        // Clear the form
                                        document.querySelector('#direct-emergency-form textarea').value = '';
                                    } else {
                                        status.className = 'alert alert-danger';
                                        status.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                                    }
                                } catch (e) {
                                    console.error('JSON parse error:', e);
                                    status.className = 'alert alert-danger';
                                    status.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error: Could not parse server response';
                                    status.innerHTML += '<div class="small text-muted mt-2">Server response: ' + text.substring(0, 100) + '...</div>';
                                }
                            })
                            .catch(error => {
                                console.error('Fetch error:', error);
                                status.className = 'alert alert-danger';
                                status.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error: ' + error.message;
                            });
                        });
                    </script>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-container">
            <div class="footer-section">
                <h3>About GuardSphere</h3>
                <p>Empowering women with safety and security solutions worldwide. Join our community to make a
                    difference.</p>
                <div class="social-links">
                    <a href="#instagram" class="instagram" aria-label="Instagram"
                        style="background: linear-gradient(45deg, #405DE6, #5851DB, #833AB4, #C13584, #E1306C, #FD1D1D);">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#facebook" class="facebook" aria-label="Facebook" style="background: #1877F2;">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#twitter" class="twitter" aria-label="Twitter" style="background: #000000;">
                        <i class="fab fa-twitter" style="color: #ffffff;"></i>
                    </a>
                    <a href="#snapchat" class="snapchat" aria-label="Snapchat" style="background: #FFFC00;">
                        <i class="fab fa-snapchat-ghost" style="color: #000;"></i>
                    </a>
                </div>
            </div>

            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="Aboutus.php">About Us</a></li>
                    <li><a href="services.php">Services</a></li>
                    <li><a href="location.php">Location</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Safety Resources</h3>
                <ul class="footer-links">
                    <li><a href="#emergency">Emergency Contacts</a></li>
                    <li><a href="#guides">Safety Guides</a></li>
                    <li><a href="#community">Community Support</a></li>
                    <li><a href="#faq">FAQ</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Contact Us</h3>
                <ul class="contact-info">
                    <li><i class="fas fa-phone"></i> Emergency: 911</li>
                    <li><i class="fas fa-envelope"></i> support@guardsphere.com</li>
                    <li><i class="fas fa-map-marker-alt"></i> Global Headquarters</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 GuardSphere. All rights reserved.</p>
        </div>
    </footer>

   
    <script>
        // Toggle profile dropdown
        function toggleDropdown() {
            document.getElementById('profileDropdown').classList.toggle('show');
        }

        // Add this debug helper function near the top of your JavaScript
        function showDebugMessage(message) {
            console.log(message);
            
            // Create debug panel if it doesn't exist
            let debugPanel = document.getElementById('debugPanel');
            if (!debugPanel && false) { // Set to true to enable visual debugging
                debugPanel = document.createElement('div');
                debugPanel.id = 'debugPanel';
                debugPanel.innerHTML = '<h4>Debug Messages</h4>';
                document.body.appendChild(debugPanel);
            }
            
            if (debugPanel) {
                const timestamp = new Date().toLocaleTimeString();
                const msgEl = document.createElement('div');
                msgEl.className = 'debug-message';
                msgEl.innerHTML = `<span class="debug-timestamp">[${timestamp}]</span> ${message}`;
                debugPanel.appendChild(msgEl);
                debugPanel.scrollTop = debugPanel.scrollHeight;
            }
        }

        function loadAvailableSlots(serviceType = '') {
            showDebugMessage(`Starting to load available slots with service type: ${serviceType || 'all'}`);
            
            let container = document.getElementById('scheduleSlotsContainer');
            if (!container) {
                showDebugMessage("Container not found, creating one...");
                const parentElement = document.querySelector('.tab-content.active') || 
                                     document.querySelector('.tab-content') || 
                                     document.querySelector('main') || 
                                     document.querySelector('body');
                
                container = document.createElement('div');
                container.id = 'scheduleSlotsContainer';
                container.className = 'service-slots';
                
                if (parentElement) {
                    parentElement.appendChild(container);
                }
            }
            
            container.innerHTML = '<div class="loading-indicator"><i class="fas fa-spinner fa-spin"></i> Loading available slots...</div>';
            
            const params = new URLSearchParams({
                action: 'get_all_schedules',
                service_type: serviceType || ''
            });
            
            fetch('service_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    container.innerHTML = `<div class="error-message">Error: ${data.message || 'Failed to load schedules'}</div>`;
                    return;
                }
        
                // Filter only available slots (where is_available is 1)
                const availableSlots = data.schedules.filter(slot => parseInt(slot.is_available) === 1);
                
                if (!availableSlots.length) {
                    container.innerHTML = '<div class="info-message">No available service slots found.</div>';
                    return;
                }
        
                let html = '<div class="time-slots-grid">';
                
                availableSlots.forEach(slot => {
                    let roleLabel = 'General Service';
                    let roleClass = '';
                    
                    switch (Number(slot.role_id)) {
                        case 2:
                            roleLabel = 'Academic Advising';
                            roleClass = 'advisor';
                            break;
                        case 3:
                            roleLabel = 'Personal Counseling';
                            roleClass = 'counselor';
                            break;
                        case 4:
                            roleLabel = 'Technical Support';
                            roleClass = 'supporter';
                            break;
                    }
                    
                    html += `
                    <div class="time-slot ${roleClass}">
                        <div class="slot-header">
                            <div class="slot-day">${slot.day || 'N/A'}</div>
                            <div class="slot-time">${slot.start_time || '00:00'} - ${slot.end_time || '00:00'}</div>
                        </div>
                        <div class="slot-info">
                            <div class="slot-type">${roleLabel}</div>
                            <div class="staff-name"><strong>Provider:</strong> ${slot.staff_name || 'Staff Member'}</div>
                        </div>
                        <div class="slot-action">
                            <button class="book-btn" onclick="bookTimeSlot(${slot.id}, '${slot.day || ''}', '${slot.start_time || ''} - ${slot.end_time || ''}', '${roleLabel}', '${slot.staff_name || 'Staff Member'}')">
                                Book This Slot
                            </button>
                        </div>
                    </div>`;
                });
                
                html += '</div>';
                container.innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = `<div class="error-message">Error loading schedules: ${error.message}</div>`;
            });
        }
        
        // Function to book a time slot
        function bookTimeSlot(slotId, day, time, serviceType, staffName) {
            if (!confirm(`Would you like to book an appointment with ${staffName} for ${serviceType} on ${day} at ${time}?`)) {
                return;
            }

            // Rest of your existing booking code...
            const formData = new FormData();
            formData.append('action', 'book_service');
            formData.append('schedule_id', slotId);
            formData.append('service_type', serviceType);
            
            fetch('service_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Booking confirmed with ${staffName}!`);
                    loadMyBookings(); // Refresh bookings list
                } else {
                    alert('Booking failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while booking. Please try again.');
            });
        }
        
        // Function to confirm booking
        function confirmBooking(slotId) {
            const notes = document.getElementById('bookingNotes').value.trim();
            
            if (!notes) {
                alert("Please provide a reason for your booking.");
                return;
            }
            
            showDebugMessage(`Sending booking request for slot ID ${slotId}`);
            
            // Use XMLHttpRequest for better error handling
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'service.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    // Close the booking dialog
                    const bookingDialog = document.getElementById('bookingDialog');
                    if (bookingDialog) {
                        bookingDialog.style.display = 'none';
                    }
                    
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            console.log("Booking response:", response);
                            
                            if (response.success) {
                                showDebugMessage("Booking confirmed successfully");
                                alert("Your booking has been confirmed!");
                                
                                // Refresh time slots
                                loadAvailableSlots();
                            } else {
                                showDebugMessage(`Booking failed: ${response.message}`, true);
                                alert(`Booking failed: ${response.message}`);
                            }
                        } catch (e) {
                            showDebugMessage(`Error parsing booking response: ${e.message}`, true);
                            alert("Error processing your booking. Please try again.");
                        }
                    } else {
                        showDebugMessage(`Server returned error status: ${xhr.status}`, true);
                        alert("Server error. Please try again later.");
                    }
                }
            };
            
            xhr.onerror = function() {
                showDebugMessage("Network error during booking", true);
                alert("Network error. Please check your connection and try again.");
                
                const bookingDialog = document.getElementById('bookingDialog');
                if (bookingDialog) {
                    bookingDialog.style.display = 'none';
                }
            };
            
            // Send the request with availability_id instead of schedule_id
            xhr.send(`action=book_schedule&availability_id=${slotId}&notes=${encodeURIComponent(notes)}`);
        }

        // Initialize when the page is ready
        document.addEventListener('DOMContentLoaded', function() {
            showDebugMessage("DOM loaded, initializing services page");
            
            // Add a diagnostic button
            const diagnosticBtn = document.createElement('button');
            diagnosticBtn.textContent = 'Run Service Diagnostic';
            diagnosticBtn.style.cssText = 'position:fixed; top:70px; right:20px; z-index:1000; padding:8px 16px; background:#663399; color:white; border:none; border-radius:4px; cursor:pointer;';
            diagnosticBtn.onclick = checkDatabaseStatus;
            document.body.appendChild(diagnosticBtn);
            
            // Try to find the service type selector
            const serviceTypeSelector = document.getElementById('serviceTypeSelector');
            if (serviceTypeSelector) {
                serviceTypeSelector.addEventListener('change', function() {
                    loadAvailableSlots(this.value);
                });
                showDebugMessage("Service type selector found and event listener attached");
            } else {
                showDebugMessage("Service type selector not found, using default view");
            }
            
            // Load all available slots initially
            loadAvailableSlots();
        });
        
        // Also add a fallback initialization for jQuery if available
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ready(function($) {
                if (!window._slotsInitialized) {
                    showDebugMessage("jQuery ready event fired, initializing services (if not already done)");
                    loadAvailableSlots();
                    window._slotsInitialized = true;
                }
            });
        }

        // Handle clicks outside of dropdown to close it
        window.onclick = function(event) {
            if (!event.target.matches('.user-avatar')) {
                const dropdowns = document.getElementsByClassName('dropdown-content');
                for (let i = 0; i < dropdowns.length; i++) {
                    const openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        // Update the loadMyBookings function to include better error handling and debugging
        function loadMyBookings() {
            showDebugMessage("Loading user bookings...");
            
            const bookingsContainer = document.getElementById('bookings-list');
            if (!bookingsContainer) {
                console.error("Bookings container element not found!");
                return;
            }
            
            bookingsContainer.innerHTML = '<div class="loading-indicator"><i class="fas fa-spinner fa-spin"></i> Loading your bookings...</div>';
            
            // Fetch bookings with detailed error handling
            fetch('service_handler.php?action=get_my_bookings')
                .then(response => {
                    showDebugMessage("Got response from server: " + response.status);
                    return response.json().catch(e => {
                        throw new Error("Invalid JSON response from server: " + e.message);
                    });
                })
                .then(data => {
                    showDebugMessage("Parsed JSON data: " + JSON.stringify(data).substring(0, 100) + "...");
                    
                    if (data.success && data.bookings && data.bookings.length > 0) {
                        showDebugMessage(`Found ${data.bookings.length} bookings`);
                        displayBookings(data.bookings, bookingsContainer);
                    } else {
                        showDebugMessage("No bookings found or error in response");
                        bookingsContainer.innerHTML = `
                            <div class="alert alert-info">
                                <p>You don't have any bookings yet.</p>
                                <button id="bookNowBtn" class="btn btn-primary mt-3">Book a Service</button>
                            </div>`;
                        
                        // Attach event listener to the "Book a Service" button
                        const bookNowBtn = document.getElementById('bookNowBtn');
                        if (bookNowBtn) {
                            bookNowBtn.addEventListener('click', function() {
                                // Find the services tab button and click it
                                const servicesTabBtn = document.querySelector('.tab-btn[data-tab="services"]');
                                if (servicesTabBtn) {
                                    servicesTabBtn.click();
                                } else {
                                    console.error("Services tab button not found");
                                }
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading bookings:', error);
                    showDebugMessage("Error loading bookings: " + error.message);
                    
                    bookingsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <p>Error loading bookings: ${error.message}</p>
                            <button onclick="loadMyBookings()" class="btn btn-primary mt-3">Try Again</button>
                        </div>`;
                });
        }

        // Make sure tab switching properly calls loadMyBookings and loadBookingHistory
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the tab system
            const tabButtons = document.querySelectorAll('.tab-btn');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Get the tab ID from the data-tab attribute
                    const tabId = this.getAttribute('data-tab');
                    
                    showDebugMessage(`Tab clicked: ${tabId}`);
                    
                    // Remove active class from all tabs and buttons
                    document.querySelectorAll('.tab-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.style.display = 'none'; // Add this line to hide all tabs
                        content.classList.remove('active');
                    });
                    
                    // Add active class to the clicked button and its corresponding tab
                    this.classList.add('active');
                    const tabContent = document.getElementById(tabId);
                    if (tabContent) {
                        tabContent.classList.add('active');
                        tabContent.style.display = 'block'; // Add this line to show the active tab
                    } else {
                        console.error(`Tab content #${tabId} not found`);
                    }
                    
                    // Load appropriate content based on the tab
                    if (tabId === 'bookings') {
                        loadMyBookings();
                    } else if (tabId === 'emergency') {
                        loadEmergencyContact();
                    } else if (tabId === 'services') {
                        loadAvailableSlots();
                    } else if (tabId === 'history') {
                        loadBookingHistory();
                    } else if (tabId === 'availability') {
                        // Load provider availability if needed
                    }
                });
            });
            
            // Initialize the first tab to be active
            const firstTab = document.querySelector('.tab-btn');
            if (firstTab) {
                firstTab.click();
            } else {
                // If no tabs found, default to services
                const bookingsTab = document.querySelector('.tab-btn[data-tab="bookings"]');
                if (bookingsTab) {
                    bookingsTab.click();
                }
            }
            
            // Your existing initialization code can remain...
            showDebugMessage("DOM loaded, initializing services page");
            
            // ... rest of your existing DOMContentLoaded code ...
        });

        // Load booking history when the history tab is clicked
        function loadBookingHistory() {
    const container = document.getElementById('booking-history-container');
    if (!container) return;

    container.innerHTML = '<div class="loading-indicator"><i class="fas fa-spinner fa-spin"></i> Loading booking history...</div>';

    fetch('service_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_booking_history'
    })
    .then(response => response.json())
    .then(data => {
        console.log('Booking history response:', data);

        if (data.success && Array.isArray(data.bookings)) {
            if (data.bookings.length > 0) {
                let html = '<div class="booking-history-grid">';
                
                data.bookings.forEach(booking => {
                    html += `
                        <div class="history-card">
                            <div class="history-header">
                                <h3>${booking.service_type || 'Service Booking'}</h3>
                                <span class="history-date">${booking.formatted_date || 'Date not available'}</span>
                            </div>
                            <div class="history-details">
                                <div class="history-detail-item">
                                    <i class="far fa-clock"></i>
                                    <span>${booking.start_time || ''} - ${booking.end_time || ''}</span>
                                </div>
                                <div class="history-detail-item">
                                    <i class="far fa-user"></i>
                                    <span>${booking.provider_name || 'Staff Member'}</span>
                                </div>
                                <div class="history-detail-item">
                                    <i class="fas fa-info-circle"></i>
                                    <span class="booking-status ${booking.status?.toLowerCase() || 'pending'}">
                                        ${booking.status?.charAt(0).toUpperCase() + booking.status?.slice(1) || 'Pending'}
                                    </span>
                                </div>
                            </div>
                        </div>`;
                });

                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="far fa-calendar-times"></i>
                        <p>No booking history found. Book your first service now!</p>
                        <button onclick="switchToServices()" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Book a Service
                        </button>
                    </div>`;
            }
        } else {
            throw new Error(data.message || 'Invalid response format');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = `
            <div class="error-state">
                <i class="fas fa-exclamation-circle"></i>
                <p>Error loading booking history. Please try again.</p>
                <p class="error-details">${error.message}</p>
            </div>`;
    });
}

function switchToServices() {
    const servicesTab = document.querySelector('.tab-btn[data-tab="services"]');
    if (servicesTab) {
        servicesTab.click();
    }
}

function switchToServices() {
    const servicesTab = document.querySelector('.tab-btn[data-tab="services"]');
    if (servicesTab) {
        servicesTab.click();
    }
}

        function displayBookingHistory(bookings, container) {
            let html = `
                <div class="history-filters">
                    <input type="text" id="historySearch" placeholder="Search bookings..." 
                           onkeyup="filterBookings()" class="form-control">
                </div>
                <div class="booking-history-grid">`;

            bookings.forEach(booking => {
                const date = new Date(booking.booking_date);
                const formattedDate = date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });

                html += `
                    <div class="history-card" data-booking-id="${booking.id}">
                        <div class="history-header">
                            <h3>${booking.service_type || 'Service Booking'}</h3>
                            <span class="history-date">${formattedDate}</span>
                        </div>
                        <div class="history-details">
                            <div class="history-detail-item">
                                <i class="far fa-clock"></i>
                                <span>${booking.start_time || ''} - ${booking.end_time || ''}</span>
                            </div>
                            <div class="history-detail-item">
                                <i class="far fa-user"></i>
                                <span>${booking.provider_name || 'Staff'}</span>
                            </div>
                            <div class="history-detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>${booking.location || 'Online'}</span>
                            </div>
                            <div class="history-detail-item">
                                <i class="fas fa-info-circle"></i>
                                <span class="history-status ${booking.status.toLowerCase()}">
                                    ${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}
                                </span>
                            </div>
                        </div>
                    </div>`;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        function filterBookings() {
            const searchText = document.getElementById('historySearch').value.toLowerCase();
            const cards = document.querySelectorAll('.history-card');

            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchText) ? 'block' : 'none';
            });
        }

        function displayBookings(bookings, container) {
    showDebugMessage("Displaying bookings...");
    let html = '<div class="bookings-container">';
    
    if (!bookings || bookings.length === 0) {
        html += `
            <div class="empty-bookings">
                <p>You don't have any bookings yet.</p>
                <button onclick="switchToServices()" class="book-now-btn">
                    <i class="fas fa-calendar-plus"></i> Book a Service
                </button>
            </div>`;
    } else {
        // Group bookings by status
        const groupedBookings = {
            pending: [],
            confirmed: [],
            follow_up: [],
            follow_up_completed: [],
            cancelled: [],
            completed: []
        };

        // Sort bookings into groups
        bookings.forEach(booking => {
            if (groupedBookings[booking.status]) {
                groupedBookings[booking.status].push(booking);
            }
        });

        // Display each status group
        const statusOrder = ['pending', 'confirmed', 'follow_up', 'follow_up_completed', 'completed', 'cancelled'];
        const statusLabels = {
            pending: 'Pending Bookings',
            confirmed: 'Confirmed Bookings',
            follow_up: 'Follow-up Required',
            follow_up_completed: 'Follow-up Completed',
            completed: 'Completed Bookings',
            cancelled: 'Cancelled Bookings'
        };

        statusOrder.forEach(status => {
            const bookingsInStatus = groupedBookings[status];
            if (bookingsInStatus && bookingsInStatus.length > 0) {
                html += `
                    <div class="booking-status-group">
                        <h3 class="status-heading">${statusLabels[status]}</h3>
                        <div class="booking-group">`;

                bookingsInStatus.forEach(booking => {
                    html += `
                        <div class="booking-card status-${booking.status}">
                            <div class="booking-header">
                                <h3>${booking.service_type || 'Service Booking'}</h3>
                                <span class="booking-status ${booking.status}">${statusLabels[booking.status] || booking.status}</span>
                            </div>
                            <div class="booking-details">
                                <p><i class="far fa-calendar"></i> <strong>Date:</strong> ${booking.formatted_date || booking.booking_date}</p>
                                <p><i class="far fa-clock"></i> <strong>Time:</strong> ${booking.start_time || ''} - ${booking.end_time || ''}</p>
                                <p><i class="far fa-user"></i> <strong>Provider:</strong> ${booking.provider_name || 'Staff'}</p>
                                ${booking.location ? `<p><i class="fas fa-map-marker-alt"></i> <strong>Location:</strong> ${booking.location}</p>` : ''}
                            </div>
                            ${booking.status === 'pending' ? `
                                <div class="booking-actions">
                                    <button onclick="cancelBooking(${booking.id})" class="btn-danger">
                                        <i class="fas fa-times"></i> Cancel Booking
                                    </button>
                                </div>` : ''}
                            ${booking.status === 'follow_up' ? `
                                <div class="booking-actions">
                                    <button onclick="bookFollowUp(${booking.id})" class="btn-primary">
                                        <i class="fas fa-calendar-check"></i> Book Follow-up
                                    </button>
                                </div>` : ''}
                        </div>`;
                });

                html += `</div></div>`;
            }
        });
    }

    html += '</div>';
    container.innerHTML = html;

    // Add event listener to "Book Now" button if it exists
    const bookNowBtn = container.querySelector('.book-now-btn');
    if (bookNowBtn) {
        bookNowBtn.addEventListener('click', function() {
            const servicesTabBtn = document.querySelector('.tab-btn[data-tab="services"]');
            if (servicesTabBtn) {
                servicesTabBtn.click();
            }
        });
    }
}

function switchToServices() {
    const servicesTab = document.querySelector('.tab-btn[data-tab="services"]');
    if (servicesTab) {
        servicesTab.click();
    }
}

        function cancelBooking(bookingId) {
            if (!confirm('Are you sure you want to cancel this booking?')) {
                return;
            }
            
            fetch('service_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=cancel_booking&booking_id=${bookingId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    handleBookingUpdate(bookingId, 'cancelled');
                    alert('Booking has been cancelled successfully.');
                } else {
                    alert('Error: ' + (data.message || 'Could not cancel booking'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Emergency Contact functionality
        function loadEmergencyContact() {
            showDebugMessage("Loading emergency contact...");
            
            const contactContainer = document.getElementById('emergencyContactContainer') || createEmergencyContactContainer();
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'service.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            
                            if (response.success && response.contact) {
                                displayEmergencyContact(response.contact, contactContainer);
                            } else {
                                displayEmergencyContactForm(null, contactContainer);
                            }
                        } catch (e) {
                            showDebugMessage("Error parsing emergency contact: " + e.message, true);
                            contactContainer.innerHTML = '<div class="error">Error loading emergency contact information</div>';
                        }
                    } else {
                        contactContainer.innerHTML = '<div class="error">Server error</div>';
                    }
                }
            };
            
            xhr.send('action=get_emergency_contact');
        }

        function createEmergencyContactContainer() {
            const container = document.createElement('div');
            container.id = 'emergencyContactContainer';
            container.className = 'emergency-contact-container';
            
            // Find a place to add it
            const parentElement = document.querySelector('.tab-pane#emergency-contact') || 
                                 document.querySelector('#emergency-contact') ||
                                 document.querySelector('.tab-content') ||
                                 document.querySelector('main');
            
            if (parentElement) {
                parentElement.appendChild(container);
            }
            
            return container;
        }

        function displayEmergencyContact(contact, container) {
            container.innerHTML = `
            <div class="emergency-contact-card">
                <h3>Your Emergency Contact</h3>
                <div class="contact-details">
                    <p><strong>Name:</strong> ${contact.name}</p>
                    <p><strong>Phone:</strong> ${contact.phone}</p>
                    <p><strong>Relationship:</strong> ${contact.relationship || 'Not specified'}</p>
                </div>
                <button onclick="displayEmergencyContactForm(${JSON.stringify(contact).replace(/"/g, '&quot;')}, this.parentNode.parentNode)" class="edit-btn">
                    Edit Contact
                </button>
            </div>`;
        }

        // function displayEmergencyContactForm(contact, container) {
        //     container.innerHTML = `
        //     <div class="emergency-contact-form">
        //         <h3>${contact ? 'Edit' : 'Add'} Emergency Contact</h3>
        //         <form id="emergencyContactForm" onsubmit="saveEmergencyContact(event)">
        //             <div class="form-group">
        //                 <label for="contactName">Name *</label>
        //                 <input type="text" id="contactName" name="name" required value="${contact ? contact.name : ''}">
        //             </div>
        //             <div class="form-group">
        //                 <label for="contactPhone">Phone Number *</label>
        //                 <input type="tel" id="contactPhone" name="phone" required value="${contact ? contact.phone : ''}">
        //             </div>
        //             <div class="form-group">
        //                 <label for="contactRelationship">Relationship</label>
        //                 <input type="text" id="contactRelationship" name="relationship" value="${contact ? contact.relationship || '' : ''}">
        //             </div>
        //             <div class="form-actions">
        //                 <button type="submit" class="save-btn">Save Contact</button>
        //             </div>
        //         </form>
        //     </div>`;
        // }

        function saveEmergencyContact(event) {
            event.preventDefault();
            
            const name = document.getElementById('contactName').value.trim();
            const phone = document.getElementById('contactPhone').value.trim();
            const relationship = document.getElementById('contactRelationship').value.trim();
            
            if (!name || !phone) {
                alert('Name and phone number are required');
                return;
            }
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'service.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            alert('Emergency contact saved successfully');
                            loadEmergencyContact(); // Reload the contact
                        } else {
                            alert('Error: ' + (response.message || 'Could not save emergency contact'));
                        }
                    } catch (e) {
                        alert('Error processing response');
                    }
                }
            };
            
            xhr.send(`action=submit_emergency_contact&name=${encodeURIComponent(name)}&phone=${encodeURIComponent(phone)}&relationship=${encodeURIComponent(relationship)}`);
        }

        // Add or update this function in your services.php JavaScript
        function bookService(serviceId, staffEmail, serviceType, staffName) {
            if (!confirm(`Would you like to book an appointment with ${staffName} for ${serviceType}?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'book_service');
            formData.append('schedule_id', serviceId);
            formData.append('service_type', serviceType);
            formData.append('provider_email', staffEmail);
            
            // Get notes if there's a notes field
            const notes = document.getElementById('booking-notes')?.value || '';
            formData.append('notes', notes);
            
            fetch('service_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Booking confirmed with ${staffName}!`);
                    loadMyBookings(); // Refresh bookings list
                } else {
                    alert('Booking failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while booking. Please try again.');
            });
        }

        function displayAvailableServices(services) {
            const container = document.getElementById('available-services');
            if (!container) return;

            let html = '';
            services.forEach(service => {
                html += `
                <div class="service-slot ${service.role_id ? `role-${service.role_id}` : ''}">
                    <div class="service-header">
                        <h3>${service.service_type}</h3>
                        <span class="staff-name">Provider: ${service.staff_name || 'Staff Member'}</span>
                    </div>
                    <div class="service-details">
                        <p><strong>Day:</strong> ${service.day}</p>
                        <p><strong>Time:</strong> ${service.start_time} - ${service.end_time}</p>
                    </div>
                    <button class="book-button" 
                        onclick="bookService(${service.id}, '${service.staff_email}', '${service.service_type}', '${service.staff_name}')">
                        Book Appointment
                    </button>
                </div>`;
            });

            container.innerHTML = html;
        }

        // Add some CSS styles
        const styles = `
            .service-slot {
                background: white;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 15px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .service-header {
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
                margin-bottom: 10px;
            }

            .staff-name {
                display: block;
                color: #666;
                font-size: 0.9em;
                margin-top: 5px;
            }

            .service-details {
                margin: 15px 0;
            }

            .role-2 { border-left: 4px solid #007bff; } /* Advisor */
            .role-3 { border-left: 4px solid #6f42c1; } /* Counselor */
            .role-4 { border-left: 4px solid #28a745; } /* Support */

            .book-button {
                background: #663399;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
                transition: background 0.3s ease;
            }

            .book-button:hover {
                background: #552288;
            }
        `;

        // Add the styles to the page
        const styleSheet = document.createElement("style");
        styleSheet.textContent = styles;
        document.head.appendChild(styleSheet);
    </script>

    <!-- Add this script right before the closing </body> tag -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Find the contacts-list div
        const contactsList = document.querySelector('#contacts-list');
        
        if (contactsList) {
            loadEmergencyContacts();
        }
        
        function loadEmergencyContacts() {
            console.log("LOADING EMERGENCY CONTACTS");
            const contactsList = document.getElementById('contacts-list');
            
            if (!contactsList) {
                console.error("FATAL ERROR: contacts-list element not found!");
                return;
            }
            
            contactsList.innerHTML = '<p style="text-align:center"><i class="fas fa-spinner fa-spin"></i> Loading your emergency contacts...</p>';
            
            // Make an AJAX request to get emergency contacts
            fetch('service_handler.php?action=get_emergency_contacts')
                .then(response => {
                    console.log("Response status:", response.status);
                    return response.json();
                })
                .then(data => {
                    console.log("Emergency contacts response data:", data);
                    
                    // Create debug info to display
                    let debugInfo = '';
                    if (data.user_email) {
                        debugInfo += `<div style="margin-top:10px;font-size:12px;color:#666;">Debug: Using email ${data.user_email}</div>`;
                    }
                    if (data.db_count !== undefined) {
                        debugInfo += `<div style="font-size:12px;color:#666;">Debug: Database reports ${data.db_count} contacts</div>`;
                    }
                    
                    // Check database table structure and show to help debugging
                    if (data.debug_table_structure) {
                        debugInfo += `<div style="font-size:12px;color:#666;">Table structure: ${data.debug_table_structure}</div>`;
                    }
                    
                    // Try with both data.contacts and data.data for flexibility
                    const contacts = data.contacts || data.data || [];
                    
                    if (data.success && contacts && contacts.length > 0) {
                        console.log("SUCCESS: Found", contacts.length, "contacts");
                        displayContacts(contacts);
                        
                        // Add debug info at the bottom
                        const debugElement = document.createElement('div');
                        debugElement.innerHTML = debugInfo;
                        contactsList.appendChild(debugElement);
                    } else {
                        console.log("NO CONTACTS FOUND or error occurred");
                        contactsList.innerHTML = `
                            <div class="alert alert-info">
                                <p>You don't have any emergency contacts yet.</p>
                                <p>Add important contacts that should be notified in case of emergency.</p>
                                ${debugInfo}
                            </div>
                            <div style="margin-top:20px;text-align:center;">
                                <button onclick="showAddContactForm()" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Emergency Contact
                                </button>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading contacts:', error);
                    contactsList.innerHTML = `
                        <div class="alert alert-danger">
                            <p>Error loading contacts. Please try again.</p>
                            <p>Technical details: ${error.message}</p>
                        </div>
                    `;
                });
        }
        
        function displayContacts(contacts) {
            console.log("Displaying contacts:", contacts);
            const contactsList = document.getElementById('contacts-list');
            let html = '<h3 style="margin-bottom:15px;">Your Emergency Contacts</h3>';
            
            html += '<div class="contacts-grid">';
            
            contacts.forEach(contact => {
                // Log each contact to see what fields are available
                console.log("Contact data:", contact);
                
                html += `
                <div class="contact-card">
                    <h4>${contact.emergency_name}</h4>
                    <div class="contact-details">
                        <p><strong>Phone:</strong> ${contact.em_number}</p>
                        <p><strong>Email:</strong> ${contact.emergency_email}</p>
                        <p><strong>Relationship:</strong> ${contact.relationship || 'Not specified'}</p>
                    </div>
                    <div class="contact-actions">
                        <button onclick="editEmergencyContact(${contact.id})" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button onclick="deleteEmergencyContact(${contact.id})" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>`;
            });
            
            html += '</div>';
            
            html += `
            <div style="margin-top:20px;text-align:center;">
                <button onclick="showAddContactForm()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Contact
                </button>
            </div>`;
            
            contactsList.innerHTML = html;
        }
        
        function displayEmptyState() {
            contactsList.innerHTML = `
            <div style="text-align:center;padding:20px;">
                <h3 style="margin-bottom:15px;">Emergency Contacts</h3>
                <p style="color:#666;margin-bottom:20px;">You don't have any emergency contacts yet.</p>
                
                <button onclick="showAddContactForm()" 
                        style="background:#9932CC;color:white;border:none;padding:10px 20px;border-radius:4px;cursor:pointer;">
                    Add New Contact
                </button>
            </div>`;
        }
        
        window.showAddContactForm = function() {
            contactsList.innerHTML = `
            <div style="max-width:500px;margin:0 auto;background:white;padding:20px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                <h3 style="margin-top:0;margin-bottom:15px;">Add Emergency Contact</h3>
                <form id="add-contact-form" onsubmit="event.preventDefault(); saveEmergencyContact(this);">
                    <div style="margin-bottom:15px;">
                        <label style="display:block;margin-bottom:5px;">Name*</label>
                        <input type="text" name="emergency_name" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="display:block;margin-bottom:5px;">Email*</label>
                        <input type="email" name="emergency_email" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="display:block;margin-bottom:5px;">Phone Number*</label>
                        <input type="tel" name="em_number" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="display:block;margin-bottom:5px;">Relationship*</label>
                        <input type="text" name="relationship" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
                    </div>
                    <div style="text-align:right;">
                        <button type="button" onclick="loadEmergencyContacts()" 
                                style="background:#ccc;color:#333;border:none;padding:8px 15px;border-radius:4px;margin-right:10px;cursor:pointer;">
                            Cancel
                        </button>
                        <button type="submit" style="background:#9932CC;color:white;border:none;padding:8px 15px;border-radius:4px;cursor:pointer;">
                            Save Contact
                        </button>
                    </div>
                </form>
            </div>`;
        };
        
        window.editEmergencyContact = function(contactId) {
            fetch('service_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=get_emergency_contact&id=${contactId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.contact) {
                    const contact = data.contact;
                    contactsList.innerHTML = `
                    <div style="max-width:500px;margin:0 auto;background:white;padding:20px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                        <h3 style="margin-top:0;margin-bottom:15px;">Edit Emergency Contact</h3>
                        <form id="edit-contact-form" onsubmit="event.preventDefault(); updateEmergencyContact(this, ${contactId});">
                            <div style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:5px;">Name*</label>
                                <input type="text" name="emergency_name" value="${contact.emergency_name}" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
                            </div>
                            <div style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:5px;">Email*</label>
                                <input type="email" name="emergency_email" value="${contact.emergency_email}" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
                            </div>
                            <div style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:5px;">Phone Number*</label>
                                <input type="tel" name="em_number" value="${contact.em_number}" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
                            </div>
                            <div style="margin-bottom:20px;">
                                <label style="display:block;margin-bottom:5px;">Relationship*</label>
                                <input type="text" name="relationship" value="${contact.relationship}" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
                            </div>
                            <div style="text-align:right;">
                                <button type="button" onclick="loadEmergencyContacts()" 
                                        style="background:#ccc;color:#333;border:none;padding:8px 15px;border-radius:4px;margin-right:10px;cursor:pointer;">
                                    Cancel
                                </button>
                                <button type="submit" style="background:#9932CC;color:white;border:none;padding:8px 15px;border-radius:4px;cursor:pointer;">
                                    Update Contact
                                </button>
                            </div>
                        </form>
                    </div>`;
                } else {
                    alert('Error: Could not load contact details.');
                    loadEmergencyContacts();
                }
            })
            .catch(error => {
                console.error('Error loading contact details:', error);
                alert('Error loading contact details. Please try again.');
                loadEmergencyContacts();
            });
        };
        
        window.deleteEmergencyContact = function(contactId) {
            if (confirm('Are you sure you want to delete this emergency contact?')) {
                fetch('service_handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=delete_emergency_contact&id=${contactId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Contact deleted successfully.');
                        loadEmergencyContacts();
                    } else {
                        alert('Error: ' + (data.message || 'Could not delete contact'));
                    }
                })
                .catch(error => {
                    console.error('Error deleting contact:', error);
                    alert('Error deleting contact. Please try again.');
                });
            }
        };
        
        window.saveEmergencyContact = function(form) {
            const formData = new FormData(form);
            formData.append('action', 'save_emergency_contact');
            
            fetch('service_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Contact saved successfully.');
                    loadEmergencyContacts();
                } else {
                    alert('Error: ' + (data.message || 'Could not save contact'));
                }
            })
            .catch(error => {
                console.error('Error saving contact:', error);
                alert('Error saving contact. Please try again.');
            });
        };
        
        window.updateEmergencyContact = function(form, contactId) {
            const formData = new FormData(form);
            formData.append('action', 'update_emergency_contact');
            formData.append('id', contactId);
            
            fetch('service_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Contact updated successfully.');
                    loadEmergencyContacts();
                } else {
                    alert('Error: ' + (data.message || 'Could not update contact'));
                }
            })
            .catch(error => {
                console.error('Error updating contact:', error);
                alert('Error updating contact. Please try again.');
            });
        };
        
        // Make loadEmergencyContacts available globally
        window.loadEmergencyContacts = loadEmergencyContacts;
    });
    </script>

    <!-- Add this script near the end of the services.php file, right before the closing </body> tag -->

    <script>
    // Emergency Alert functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Find all emergency alert buttons
        const emergencyButtons = document.querySelectorAll('.emergency-alert-btn, #sendEmergencyAlertBtn, [data-action="send-emergency-alert"]');
        
        // Log for debugging
        console.log(`Found ${emergencyButtons.length} emergency alert buttons`);
        
        // Add click handlers to all emergency buttons
        emergencyButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                sendEmergencyAlert();
            });
        });
        
        // If we couldn't find buttons by class/id, try to find by text content
        if (emergencyButtons.length === 0) {
            document.querySelectorAll('button').forEach(button => {
                if (button.textContent.toLowerCase().includes('emergency') || 
                    button.textContent.toLowerCase().includes('alert')) {
                    console.log("Found button by text:", button.textContent);
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        sendEmergencyAlert();
                    });
                }
            });
        }
    });

    // Function to handle sending emergency alerts
    function sendEmergencyAlert() {
        console.log("Emergency alert button clicked");
        
        // Check if we have emergency contacts first
        fetch('service_handler.php?action=get_emergency_contacts')
            .then(response => response.json())
            .then(data => {
                if (data.contacts && data.contacts.length > 0) {
                    // We have contacts, confirm the alert
                    if (confirm("Are you sure you want to send an emergency alert to your contacts?")) {
                        // Show a loading indicator
                        const alertStatus = document.createElement('div');
                        alertStatus.className = 'alert-status';
                        alertStatus.innerHTML = '<div class="alert alert-info">Sending emergency alerts... <i class="fas fa-spinner fa-spin"></i></div>';
                        
                        // Find a good place to insert the status
                        const container = document.querySelector('#emergency') || 
                                         document.querySelector('.tab-content') || 
                                         document.body;
                        container.prepend(alertStatus);
                        
                        // Send the alert
                        fetch('service_handler.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=send_emergency_alert'
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                alertStatus.innerHTML = '<div class="alert alert-success">Emergency alerts have been sent to your contacts.</div>';
                            } else {
                                alertStatus.innerHTML = `<div class="alert alert-danger">Error: ${result.message || 'Could not send emergency alerts'}</div>`;
                            }
                            
                            // Remove the status message after a few seconds
                            setTimeout(() => {
                                alertStatus.style.opacity = '0';
                                setTimeout(() => alertStatus.remove(), 1000);
                            }, 5000);
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alertStatus.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
                        });
                    }
                } else {
                    // No contacts found
                    alert("You need to add emergency contacts before you can send alerts.");
                    
                    // Focus the tab with emergency contacts
                    const contactsTab = document.querySelector('.tab-btn[data-tab="emergency"]');
                    if (contactsTab) {
                        contactsTab.click();
                    }
                }
            })
            .catch(error => {
                console.error('Error checking contacts:', error);
                alert("Error checking emergency contacts. Please try again.");
            });
    }
    </script>

    <!-- Add this right before the closing </body> tag -->
    <script>
    // Make sure we have just one version of this script
    document.addEventListener('DOMContentLoaded', function() {
        // Remove any previous handlers first to prevent duplicates
        const oldEmergencyButtons = document.querySelectorAll('.emergency-alert-btn, #sendEmergencyAlertBtn, [data-action="send-emergency-alert"]');
        oldEmergencyButtons.forEach(btn => {
            // Clone and replace to remove all event listeners
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
        });
        
        // Find emergency button by ID first
        let emergencyBtn = document.getElementById('sendEmergencyAlertBtn');
        
        // If not found by ID, look for specific classes
        if (!emergencyBtn) {
            emergencyBtn = document.querySelector('.emergency-alert-btn, [data-action="send-emergency-alert"]');
        }
        
        // If still not found, look for text content containing "emergency"
        if (!emergencyBtn) {
            document.querySelectorAll('button').forEach(btn => {
                if (btn.textContent.toLowerCase().includes('emergency') || 
                    btn.textContent.toLowerCase().includes('alert')) {
                    emergencyBtn = btn;
                }
            });
        }
        
        // Debug
        console.log("Emergency button found:", emergencyBtn ? "Yes" : "No");
        
        if (emergencyBtn) {
            // Make the button more visible if it exists
            emergencyBtn.style.backgroundColor = '#e74c3c';
            emergencyBtn.style.color = 'white';
            emergencyBtn.style.fontWeight = 'bold';
            
            // Add click handler to this specific button
            emergencyBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log("Emergency alert button clicked!");
                sendEmergencyAlert();
            });
        } else {
            // If button doesn't exist, let's create one
            console.log("Emergency button not found, creating one");
            const alertButton = document.createElement('button');
            alertButton.textContent = 'SEND EMERGENCY ALERT';
            alertButton.id = 'sendEmergencyAlertBtn';
            alertButton.className = 'emergency-alert-btn';
            alertButton.style.backgroundColor = '#e74c3c';
            alertButton.style.color = 'white';
            alertButton.style.fontWeight = 'bold';
            alertButton.style.padding = '12px 20px';
            alertButton.style.borderRadius = '4px';
            alertButton.style.border = 'none';
            alertButton.style.cursor = 'pointer';
            alertButton.style.margin = '20px auto';
            alertButton.style.display = 'block';
            
            // Find a good place to add it
            const targetContainer = document.querySelector('#emergency') || 
                                    document.querySelector('.tab-content') || 
                                    document.querySelector('main') ||
                                    document.body;
            
            targetContainer.prepend(alertButton);
            
            alertButton.addEventListener('click', function(e) {
                e.preventDefault();
                console.log("Created emergency alert button clicked!");
                sendEmergencyAlert();
            });
        }
        
        // Function to handle emergency alerts - make it global
        window.sendEmergencyAlert = function() {
            console.log("Emergency alert function triggered");
            
            // Check if we have emergency contacts first
            fetch('service_handler.php?action=get_emergency_contacts')
                .then(response => response.json())
                .then(data => {
                    console.log("Contacts data:", data);
                    
                    if (data.contacts && data.contacts.length > 0) {
                        // We have contacts, confirm the alert
                        if (confirm("Are you sure you want to send an emergency alert to your contacts?")) {
                            // Show a loading indicator
                            const alertStatus = document.createElement('div');
                            alertStatus.className = 'alert-status';
                            alertStatus.style.padding = '15px';
                            alertStatus.style.margin = '10px 0';
                            alertStatus.style.borderRadius = '4px';
                            alertStatus.innerHTML = '<div style="background-color:#3498db; color:white; padding:10px; border-radius:4px;">Sending emergency alerts... <i class="fas fa-spinner fa-spin"></i></div>';
                            
                            // Add it to the page
                            const container = document.querySelector('#emergency') || 
                                             document.querySelector('.tab-content') || 
                                             document.body;
                            container.prepend(alertStatus);
                            
                            // Send the alert
                            fetch('service_handler.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'action=send_emergency_alert'
                            })
                            .then(response => response.json())
                            .then(result => {
                                console.log("Alert result:", result);
                                
                                if (result.success) {
                                    alertStatus.innerHTML = '<div style="background-color:#2ecc71; color:white; padding:10px; border-radius:4px;">Emergency alerts have been sent to your contacts!</div>';
                                    
                                    // Show more details
                                    if (result.details && result.details.length) {
                                        let detailsHtml = '<ul style="background-color:#f9f9f9; padding:10px; border-radius:4px; margin-top:10px;">';
                                        result.details.forEach(msg => {
                                            detailsHtml += `<li>${msg}</li>`;
                                        });
                                        detailsHtml += '</ul>';
                                        alertStatus.innerHTML += detailsHtml;
                                    }
                                } else {
                                    alertStatus.innerHTML = `<div style="background-color:#e74c3c; color:white; padding:10px; border-radius:4px;">Error: ${result.message || 'Could not send emergency alerts'}</div>`;
                                }
                                
                                // Remove the status message after a while
                                setTimeout(() => {
                                    alertStatus.style.opacity = '0';
                                    alertStatus.style.transition = 'opacity 1s ease';
                                    setTimeout(() => alertStatus.remove(), 1000);
                                }, 8000);
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alertStatus.innerHTML = `<div style="background-color:#e74c3c; color:white; padding:10px; border-radius:4px;">Error: ${error.message}</div>`;
                            });
                        }
                    } else {
                        // No contacts found
                        alert("You need to add emergency contacts before you can send alerts.");
                        
                        // Focus the tab with emergency contacts
                        const contactsTab = document.querySelector('.tab-btn[data-tab="emergency"]');
                        if (contactsTab) {
                            contactsTab.click();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking contacts:', error);
                    alert("Error checking emergency contacts. Please try again.");
                });
        };
    });
    </script>

    <!-- Replace all existing emergency alert scripts with this single version -->
    <script>
    // Only run this once when the page loads
    (function() {
        // Find the emergency button
        let emergencyBtn = document.getElementById('sendEmergencyAlertBtn');
        
        // If not found by ID, create a new one
        if (!emergencyBtn) {
            console.log("Creating new emergency button");
            emergencyBtn = document.createElement('button');
            emergencyBtn.id = 'sendEmergencyAlertBtn';
            emergencyBtn.className = 'btn btn-danger emergency-alert-btn';
            emergencyBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> SEND EMERGENCY ALERT';
            emergencyBtn.style.fontWeight = 'bold';
            emergencyBtn.style.padding = '12px 20px';
            emergencyBtn.style.margin = '20px 0';
            
            // Add it to the page in the emergency tab
            const emergencyTab = document.getElementById('emergency');
            if (emergencyTab) {
                emergencyTab.prepend(emergencyBtn);
            } else {
                const container = document.querySelector('.tab-content') || document.body;
                container.prepend(emergencyBtn);
            }
        }
        
        // Log for debugging
        console.log("Emergency button found or created:", emergencyBtn);
        
        // Add click handler - ONLY to this button
        emergencyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log("Emergency alert button clicked");
            
            // Confirm before sending
            if (confirm("Are you sure you want to send an emergency alert to all your contacts?")) {
                // Show loading state
                const alertStatus = document.createElement('div');
                alertStatus.className = 'alert alert-info';
                alertStatus.innerHTML = 'Sending alerts... <i class="fas fa-spinner fa-spin"></i>';
                emergencyBtn.parentNode.insertBefore(alertStatus, emergencyBtn.nextSibling);
                
                // IMPORTANT: Make sure the action parameter is correctly set
                fetch('service_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': 'send_emergency_alert',
                        'message': messageText || 'Emergency alert! Please contact me immediately.'
                    }).toString()
                })
                .then(response => {
                    // Log the raw response for debugging
                    console.log("Raw response status:", response.status);
                    return response.text().then(text => {
                        console.log("Raw response:", text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error("Invalid JSON response: " + text);
                        }
                    });
                })
                .then(result => {
                    console.log("Alert result:", result);
                    
                    if (result.success) {
                        alertStatus.className = 'alert alert-success';
                        alertStatus.innerHTML = 'Emergency alerts sent successfully!';
                        
                        if (result.details && result.details.length) {
                            const ul = document.createElement('ul');
                            result.details.forEach(msg => {
                                const li = document.createElement('li');
                                li.textContent = msg;
                                ul.appendChild(li);
                            });
                            alertStatus.appendChild(ul);
                        }
                    } else {
                        alertStatus.className = 'alert alert-danger';
                        alertStatus.innerHTML = 'Error: ' + (result.message || 'Unknown error');
                    }
                    
                    // Remove after 10 seconds
                    setTimeout(() => {
                        alertStatus.style.opacity = '0';
                        alertStatus.style.transition = 'opacity 1s';
                        setTimeout(() => alertStatus.remove(), 1000);
                    }, 10000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alertStatus.className = 'alert alert-danger';
                    alertStatus.innerHTML = 'Error: ' + error.message;
                });
            }
        });
    })(); // Self-execute once
    </script>

    <!-- Update the emergency alert button functionality to include the message -->
    <script>
    // Emergency Alert functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Find emergency alert button
        const emergencyButton = document.getElementById('sendEmergencyAlertBtn') || 
                               document.querySelector('.emergency-alert-btn');
        
        if (emergencyButton) {
            console.log("Found emergency alert button:", emergencyButton);
            
            // Add the event listener
            emergencyButton.addEventListener('click', function(e) {
                e.preventDefault();
                console.log("Emergency alert button clicked");
                
                // Get the message if available
                const messageElement = document.getElementById('emergency-message');
                const message = messageElement ? messageElement.value.trim() : '';
                
                const confirmMessage = message 
                    ? `Are you sure you want to send this emergency message to all your contacts?\n\nMessage: ${message}` 
                    : "Are you sure you want to send an emergency alert to all your contacts?";
                    
                if (confirm(confirmMessage)) {
                    // Show loading state
                    const alertStatus = document.createElement('div');
                    alertStatus.className = 'alert alert-info';
                    alertStatus.style.margin = '10px 0';
                    alertStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending emergency alerts...';
                    
                    // Insert status message
                    emergencyButton.parentNode.insertBefore(alertStatus, emergencyButton.nextSibling);
                    
                    // Create form data with message if available
                    const formData = new FormData();
                    formData.append('action', 'send_emergency_alert');
                    if (message) {
                        formData.append('message', message);
                    }
                    
                    // Send the request
                    fetch('service_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log("Response status:", response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log("Alert result:", data);
                        
                        if (data.success) {
                            alertStatus.className = 'alert alert-success';
                            alertStatus.innerHTML = '<i class="fas fa-check-circle"></i> Emergency alerts sent successfully!';
                            
                            if (data.details && data.details.length) {
                                let detailsHtml = '<ul style="margin-top:10px;">';
                                data.details.forEach(detail => {
                                    detailsHtml += `<li>${detail}</li>`;
                                });
                                detailsHtml += '</ul>';
                                alertStatus.innerHTML += detailsHtml;
                            }
                        } else {
                            alertStatus.className = 'alert alert-danger';
                            alertStatus.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error: ${data.message || 'Failed to send alerts'}`;
                        }
                        
                        // Clear the message textarea if the alert was sent successfully
                        if (data.success && messageElement) {
                            messageElement.value = '';
                        }
                        
                        // Remove the alert after some time
                        setTimeout(() => {
                            alertStatus.style.opacity = '0';
                            alertStatus.style.transition = 'opacity 1s ease';
                            setTimeout(() => alertStatus.remove(), 1000);
                        }, 10000);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alertStatus.className = 'alert alert-danger';
                        alertStatus.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error: ${error.message}`;
                    });
                }
            });
        } else {
            console.error("Emergency alert button not found!");
        }
    });
    </script>

    <!-- Fix the emergency alert functionality to prevent duplicate events and handle JSON errors -->
    <script>
    // Emergency Alert functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Find emergency alert button
        const emergencyButton = document.getElementById('sendEmergencyAlertBtn') || 
                               document.querySelector('.emergency-alert-btn');
        
        if (emergencyButton) {
            console.log("Found emergency alert button:", emergencyButton);
            
            // Add the event listener
            emergencyButton.addEventListener('click', function(e) {
                e.preventDefault();
                console.log("Emergency alert button clicked");
                
                // Get the message if available
                const messageElement = document.getElementById('emergency-message');
                const message = messageElement ? messageElement.value.trim() : '';
                
                const confirmMessage = message 
                    ? `Are you sure you want to send this emergency message to all your contacts?\n\nMessage: ${message}` 
                    : "Are you sure you want to send an emergency alert to all your contacts?";
                    
                if (confirm(confirmMessage)) {
                    // Show loading state
                    const alertStatus = document.createElement('div');
                    alertStatus.className = 'alert alert-info';
                    alertStatus.style.margin = '10px 0';
                    alertStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending emergency alerts...';
                    
                    // Insert status message
                    emergencyButton.parentNode.insertBefore(alertStatus, emergencyButton.nextSibling);
                    
                    // Create form data with message if available
                    const formData = new FormData();
                    formData.append('action', 'send_emergency_alert');
                    if (message) {
                        formData.append('message', message);
                    }
                    
                    // Send the request
                    fetch('service_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log("Response status:", response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log("Alert result:", data);
                        
                        if (data.success) {
                            alertStatus.className = 'alert alert-success';
                            alertStatus.innerHTML = '<i class="fas fa-check-circle"></i> Emergency alerts sent successfully!';
                            
                            if (data.details && data.details.length) {
                                let detailsHtml = '<ul style="margin-top:10px;">';
                                data.details.forEach(detail => {
                                    detailsHtml += `<li>${detail}</li>`;
                                });
                                detailsHtml += '</ul>';
                                alertStatus.innerHTML += detailsHtml;
                            }
                        } else {
                            alertStatus.className = 'alert alert-danger';
                            alertStatus.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error: ${data.message || 'Failed to send alerts'}`;
                        }
                        
                        // Clear the message textarea if the alert was sent successfully
                        if (data.success && messageElement) {
                            messageElement.value = '';
                        }
                        
                        // Remove the alert after some time
                        setTimeout(() => {
                            alertStatus.style.opacity = '0';
                            alertStatus.style.transition = 'opacity 1s ease';
                            setTimeout(() => alertStatus.remove(), 1000);
                        }, 10000);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alertStatus.className = 'alert alert-danger';
                        alertStatus.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error: ${error.message}`;
                    });
                }
            });
        } else {
            console.error("Emergency alert button not found!");
        }
    });
    </script>

    <!-- Complete replacement of the emergency SMS functionality to prevent duplicate events -->
    <script>
    // Emergency Alert functionality
    document.addEventListener('DOMContentLoaded', function() {
        // First, find the emergency SMS button
        const emergencyButton = document.querySelector('.btn-danger[onclick="sendEmergencySMS()"]') || 
                               document.querySelector('.emergency-alert-btn') ||
                               document.getElementById('sendEmergencyAlertBtn');
        
        if (emergencyButton) {
            console.log("Found emergency button:", emergencyButton);
            
            // Remove the onclick attribute completely to prevent multiple calls
            emergencyButton.removeAttribute('onclick');
            
            // Replace the button with a clone to remove any existing event listeners
            const newButton = emergencyButton.cloneNode(true);
            emergencyButton.parentNode.replaceChild(newButton, emergencyButton);
            
            // Add a single event listener
            newButton.addEventListener('click', function(e) {
                e.preventDefault();
                console.log("Emergency button clicked - single event handler");
                
                // Get the message from the textarea
                const messageElement = document.getElementById('emergency-message');
                const message = messageElement ? messageElement.value.trim() : '';
                
                const confirmMessage = message 
                    ? `Are you sure you want to send this emergency message to all your contacts?\n\nMessage: ${message}` 
                    : "Are you sure you want to send an emergency alert to all your contacts?";
                    
                if (confirm(confirmMessage)) {
                    // Show loading state
                    const emergencyTab = document.getElementById('emergency');
                    
                    // Create status alert if it doesn't exist
                    let alertStatus = document.getElementById('emergency-alert-status');
                    if (!alertStatus) {
                        alertStatus = document.createElement('div');
                        alertStatus.id = 'emergency-alert-status';
                        alertStatus.className = 'alert alert-info';
                        alertStatus.style.margin = '10px 0';
                        
                        // Find a place to insert the status
                        const actionsDiv = document.querySelector('.emergency-actions');
                        if (actionsDiv) {
                            actionsDiv.appendChild(alertStatus);
                        } else if (emergencyTab) {
                            emergencyTab.insertBefore(alertStatus, emergencyTab.firstChild);
                        }
                    }
                    
                    alertStatus.className = 'alert alert-info';
                    alertStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending emergency alerts...';
                    alertStatus.style.display = 'block';
                    alertStatus.style.opacity = '1';
                    
                    // Create form data with message if available
                    const formData = new FormData();
                    formData.append('action', 'send_emergency_alert');
                    if (message) {
                        formData.append('message', message);
                    }
                    
                    // Send the request
                    fetch('service_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log("Response status:", response.status);
                        return response.text().then(text => {
                            console.log("Raw response:", text);
                            if (text.trim()) {
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    console.error("JSON parse error:", e);
                                    throw new Error("Invalid server response: " + text.substring(0, 100));
                                }
                            } else {
                                throw new Error("Empty response from server");
                            }
                        });
                    })
                    .then(data => {
                        console.log("Alert result:", data);
                        
                        if (data && data.success) {
                            alertStatus.className = 'alert alert-success';
                            alertStatus.innerHTML = '<i class="fas fa-check-circle"></i> Emergency alerts sent successfully!';
                            
                            if (data.details && data.details.length) {
                                let detailsHtml = '<ul style="margin-top:10px;">';
                                data.details.forEach(detail => {
                                    detailsHtml += `<li>${detail}</li>`;
                                });
                                detailsHtml += '</ul>';
                                alertStatus.innerHTML += detailsHtml;
                            }
                            
                            // Clear the message textarea if the alert was sent successfully
                            if (messageElement) {
                                messageElement.value = '';
                            }
                        } else {
                            alertStatus.className = 'alert alert-danger';
                            alertStatus.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error: ${data && data.message ? data.message : 'Failed to send alerts'}`;
                        }
                        
                        // Remove the alert after some time
                        setTimeout(() => {
                            alertStatus.style.opacity = '0';
                            alertStatus.style.transition = 'opacity 1s ease';
                            setTimeout(() => {
                                alertStatus.style.display = 'none';
                            }, 1000);
                        }, 10000);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alertStatus.className = 'alert alert-danger';
                        alertStatus.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error: ${error.message}`;
                    });
            });
            
            console.log("Single event listener attached to emergency button");
        } else {
            console.warn("Emergency button not found on page load");
        }
    });
    </script>

    <!-- Replace all existing emergency alert scripts with this single version -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Find or create the emergency message textarea
        let messageArea = document.getElementById('emergency-message');
        if (!messageArea) {
            // Create the textarea if it doesn't exist
            const emergencyTab = document.getElementById('emergency');
            if (emergencyTab) {
                const container = document.createElement('div');
                container.className = 'emergency-message-container';
                container.style.marginBottom = '20px';
                
                const label = document.createElement('label');
                label.htmlFor = 'emergency-message';
                label.style.display = 'block';
                label.style.marginBottom = '5px';
                label.style.fontWeight = 'bold';
                label.textContent = 'Emergency Message:';
                
                messageArea = document.createElement('textarea');
                messageArea.id = 'emergency-message';
                messageArea.placeholder = 'Enter a message to send to your emergency contacts (optional)';
                messageArea.style.width = '100%';
                messageArea.style.padding = '10px';
                messageArea.style.borderRadius = '4px';
                messageArea.style.border = '1px solid #ccc';
                messageArea.style.minHeight = '80px';
                
                container.appendChild(label);
                container.appendChild(messageArea);
                
                // Find a good place to insert it
                const button = document.getElementById('sendEmergencyAlertBtn');
                if (button) {
                    emergencyTab.insertBefore(container, button.parentNode);
                } else {
                    emergencyTab.prepend(container);
                }
            }
        }
        
        // Clean up any existing emergency buttons
        document.querySelectorAll('.emergency-alert-btn, #sendEmergencyAlertBtn, [onclick*="sendEmergencySMS"]').forEach(btn => {
            // Remove onclick attribute
            btn.removeAttribute('onclick');
            
            // Clone to remove event listeners
            const newBtn = btn.cloneNode(true);
            if (btn.parentNode) {
                btn.parentNode.replaceChild(newBtn, btn);
            }
        });
        
        // Find or create the emergency button
        let emergencyBtn = document.getElementById('sendEmergencyAlertBtn');
        if (!emergencyBtn) {
            emergencyBtn = document.createElement('button');
            emergencyBtn.id = 'sendEmergencyAlertBtn';
            emergencyBtn.className = 'btn btn-danger btn-lg';
            emergencyBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> SEND EMERGENCY ALERT';
            emergencyBtn.style.margin = '10px 0';
            
            // Add it to the page
            const emergencyTab = document.getElementById('emergency');
            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'emergency-actions text-center';
            actionsDiv.appendChild(emergencyBtn);
            
            if (emergencyTab) {
                emergencyTab.appendChild(actionsDiv);
            } else {
                document.querySelector('.tab-content')?.appendChild(actionsDiv);
            }
        }
        
        console.log("Set up emergency button:", emergencyBtn);
        
        // Add a single event listener
        emergencyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log("Emergency button clicked");
            
            // Get message text
            const messageText = messageArea ? messageArea.value.trim() : '';
            const confirmMsg = messageText ? 
                `Are you sure you want to send this emergency message?\n\n"${messageText}"` : 
                "Are you sure you want to send an emergency alert?";
                
            if (confirm(confirmMsg)) {
                // Show loading state
                let statusAlert = document.getElementById('emergency-status');
                if (!statusAlert) {
                    statusAlert = document.createElement('div');
                    statusAlert.id = 'emergency-status';
                    statusAlert.className = 'alert alert-info';
                    statusAlert.style.margin = '15px 0';
                    emergencyBtn.parentNode.appendChild(statusAlert);
                }
                
                statusAlert.className = 'alert alert-info';
                statusAlert.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending emergency alerts...';
                statusAlert.style.display = 'block';
                
                // Create form data
                const formData = new FormData();
                formData.append('action', 'send_emergency_alert');
                if (messageText) {
                    formData.append('message', messageText);
                }
                
                // Send the request
                fetch('service_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log("Response status:", response.status);
                    return response.text().then(text => {
                        console.log("Raw response:", text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error("Invalid server response: " + text.substring(0, 100));
                        }
                    });
                })
                .then(data => {
                    console.log("Alert result:", data);
                    
                    if (data.success) {
                        statusAlert.className = 'alert alert-success';
                        statusAlert.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                        
                        // Clear the textarea
                        if (messageArea) {
                            messageArea.value = '';
                        }
                        
                        // Show detailed results
                        if (data.details && data.details.length) {
                            let detailsHtml = '<ul style="margin-top: 10px;">';
                            data.details.forEach(detail => {
                                detailsHtml += `<li>${detail}</li>`;
                            });
                            detailsHtml += '</ul>';
                            statusAlert.innerHTML += detailsHtml;
                        }
                    } else {
                        statusAlert.className = 'alert alert-danger';
                        statusAlert.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + 
                            (data.message || 'Error sending emergency alerts');
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    statusAlert.className = 'alert alert-danger';
                    statusAlert.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error: ' + error.message;
                });
            }
        });
    });
    </script>

    <!-- Add this function to retry with the dedicated endpoint if the main request fails -->
    <script>
    function sendEmergencyAlertFallback(messageText, statusAlert) {
        console.log("Using emergency fallback endpoint");
        
        fetch('emergency_alert.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'message': messageText || 'Emergency alert! Please contact me immediately.'
            }).toString()
        })
        .then(response => response.json())
        .then(data => {
            console.log("Fallback alert result:", data);
            
            if (data.success) {
                statusAlert.className = 'alert alert-success';
                statusAlert.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                
                // Show details if available
                if (data.details && data.details.length) {
                    let detailsHtml = '<ul style="margin-top: 10px;">';
                    data.details.forEach(detail => {
                        detailsHtml += `<li>${detail}</li>`;
                    });
                    detailsHtml += '</ul>';
                    statusAlert.innerHTML += detailsHtml;
                }
            } else {
                statusAlert.className = 'alert alert-danger';
                statusAlert.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + 
                    (data.message || 'Error sending emergency alerts');
            }
        })
        .catch(error => {
            console.error("Fallback error:", error);
            statusAlert.className = 'alert alert-danger';
            statusAlert.innerHTML = '<i class="fas fa-exclamation-circle"></i> All attempts failed: ' + error.message;
        });
    }

    // Add this catch to the main fetch request to use the fallback
    .catch(error => {
        console.error("Error:", error);
        statusAlert.className = 'alert alert-warning';
        statusAlert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Primary method failed. Trying alternative...';
        
        // Try the fallback endpoint
        sendEmergencyAlertFallback(messageText, statusAlert);
    });
    </script>

    <!-- EMERGENCY BUTTON FIX -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM loaded - Fixing emergency button");
        
        // Replace the entire emergency actions container with a fresh one
        const emergencyActions = document.querySelector('.emergency-actions');
        if (emergencyActions) {
            console.log("Found emergency actions container, replacing with fresh HTML");
            emergencyActions.innerHTML = `
                <button id="sendEmergencyAlertBtn" class="btn btn-danger btn-lg" type="button">
                    <i class="fas fa-exclamation-triangle"></i> SEND EMERGENCY ALERT
                </button>
                <div id="emergency-alert-status" style="display:none;margin-top:10px;"></div>
            `;
            
            // Get the fresh button
            const freshButton = document.getElementById('sendEmergencyAlertBtn');
            if (freshButton) {
                console.log("Successfully created fresh emergency button");
                
                // Add the click event with a very simple implementation
                freshButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log("Emergency button clicked - using simplified approach");
                    
                    if (confirm("Are you sure you want to send an emergency alert?")) {
                        // Show status message
                        const statusDiv = document.getElementById('emergency-alert-status');
                        statusDiv.style.display = 'block';
                        statusDiv.className = 'alert alert-info';
                        statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending emergency alerts...';
                        
                        // Use very simple fetch with explicit action parameter
                        fetch('service_handler.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=send_emergency_alert'
                        })
                        .then(function(response) {
                            return response.text();
                        })
                        .then(function(text) {
                            console.log("Raw response:", text);
                            
                            try {
                                const data = JSON.parse(text);
                                if (data.success) {
                                    statusDiv.className = 'alert alert-success';
                                    statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> Emergency alerts sent successfully!';
                                } else {
                                    statusDiv.className = 'alert alert-danger';
                                    statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error: ' + (data.message || 'Unknown error');
                                }
                            } catch (e) {
                                statusDiv.className = 'alert alert-danger';
                                statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error parsing response: ' + e.message;
                                console.error("Response text was:", text);
                            }
                        })
                        .catch(function(error) {
                            console.error('Fetch error:', error);
                            statusDiv.className = 'alert alert-danger';
                            statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error: ' + error.message;
                        });
                    }
                });
            }
        } else {
            console.error("Emergency actions container not found!");
        }
    });
    </script>

    <!-- Update the emergency button to use the simplified handler -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM loaded - Setting up emergency button with simplified handler");
        
        // Find the Send Emergency Alert button
        const emergencyButton = document.getElementById('sendEmergencyAlertBtn');
        
        if (emergencyButton) {
            console.log("Found emergency button, setting up click handler");
            
            // Replace the button with a clone to remove any existing event listeners
            const newButton = emergencyButton.cloneNode(true);
            emergencyButton.parentNode.replaceChild(newButton, emergencyButton);
            
            // Add click handler to the new button
            newButton.addEventListener('click', function() {
                console.log("Emergency button clicked - using dedicated handler");
                
                if (confirm("Are you sure you want to send an emergency alert to all your contacts?")) {
                    // Create status element if it doesn't exist
                    let statusDiv = document.getElementById('alert-status-message');
                    if (!statusDiv) {
                        statusDiv = document.createElement('div');
                        statusDiv.id = 'alert-status-message';
                        statusDiv.className = 'alert alert-info';
                        statusDiv.style.margin = '10px 0';
                        newButton.parentNode.insertBefore(statusDiv, newButton.nextSibling);
                    }
                    
                    statusDiv.style.display = 'block';
                    statusDiv.className = 'alert alert-info';
                    statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending emergency alerts...';
                    
                    // Use the simplified emergency handler
                    fetch('emergency_handler.php')
                    .then(function(response) {
                        return response.text();
                    })
                    .then(function(text) {
                        console.log("Response:", text);
                        
                        try {
                            const data = JSON.parse(text);
                            if (data.success) {
                                statusDiv.className = 'alert alert-success';
                                statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                                
                                if (data.details && data.details.length) {
                                    let detailsHtml = '<ul style="margin-top:10px;">';
                                    data.details.forEach(function(detail) {
                                        detailsHtml += '<li>' + detail + '</li>';
                                    });
                                    detailsHtml += '</ul>';
                                    statusDiv.innerHTML += detailsHtml;
                                }
                            } else {
                                statusDiv.className = 'alert alert-danger';
                                statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                            }
                        } catch (e) {
                            statusDiv.className = 'alert alert-danger';
                            statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error parsing response';
                            console.error("JSON parse error:", e);
                            console.error("Response was:", text);
                        }
                    })
                    .catch(function(error) {
                        statusDiv.className = 'alert alert-danger';
                        statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error: ' + error.message;
                        console.error("Fetch error:", error);
                    });
                }
            });
            
            console.log("Emergency button handler set up successfully");
        } else {
            console.error("Emergency button not found!");
        }
    });
    </script>

    <!-- Update the emergency button to use Twilio SMS function -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM loaded - Setting up emergency button with Twilio integration");
        
        // Find the Send Emergency Alert button
        const emergencyButton = document.getElementById('sendEmergencyAlertBtn');
        
        if (emergencyButton) {
            console.log("Found emergency button, setting up click handler");
            
            // Replace the button with a clone to remove any existing event listeners
            const newButton = emergencyButton.cloneNode(true);
            emergencyButton.parentNode.replaceChild(newButton, emergencyButton);
            
            // Add click handler to the new button
            newButton.addEventListener('click', function() {
                console.log("Emergency button clicked - with Twilio integration");
                
                // Get the message from the textarea
                const messageElement = document.getElementById('emergency-message');
                const message = messageElement ? messageElement.value.trim() : '';
                const confirmText = message ? 
                    `Send this emergency message?\n\n"${message}"` : 
                    "Send an emergency alert to all your contacts?";
                
                if (confirm(confirmText)) {
                    // Create status element if it doesn't exist
                    let statusDiv = document.getElementById('alert-status-message');
                    if (!statusDiv) {
                        statusDiv = document.createElement('div');
                        statusDiv.id = 'alert-status-message';
                        statusDiv.className = 'alert alert-info';
                        statusDiv.style.margin = '10px 0';
                        newButton.parentNode.insertBefore(statusDiv, newButton.nextSibling);
                    }
                    
                    statusDiv.style.display = 'block';
                    statusDiv.className = 'alert alert-info';
                    statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending emergency alerts via Twilio...';
                    
                    // Create FormData to send the message
                    const formData = new FormData();
                    if (message) {
                        formData.append('message', message);
                    }
                    
                    // Use the integrated emergency handler
                    fetch('emergency_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(response) {
                        return response.text();
                    })
                    .then(function(text) {
                        console.log("Twilio response:", text);
                        
                        try {
                            const data = JSON.parse(text);
                            if (data.success > 0) {
                                statusDiv.className = 'alert alert-success';
                                statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + (data.message || 'Emergency alerts sent successfully via Twilio!');
                                
                                // Clear the message textarea
                                if (messageElement) {
                                    messageElement.value = '';
                                }
                                
                                // Add success details if available
                                if (data.details) {
                                    let detailsHtml = '<ul style="margin-top:10px;">';
                                    if (Array.isArray(data.details)) {
                                        data.details.forEach(function(detail) {
                                            detailsHtml += '<li>' + detail + '</li>';
                                        });
                                    } else {
                                        detailsHtml += '<li>' + data.details + '</li>';
                                    }
                                    detailsHtml += '</ul>';
                                    statusDiv.innerHTML += detailsHtml;
                                }
                            } else {
                                statusDiv.className = 'alert alert-danger';
                                statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.message || 'Failed to send SMS');
                            }
                        } catch (e) {
                            statusDiv.className = 'alert alert-danger';
                            statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error parsing response';
                            console.error("JSON parse error:", e);
                            console.error("Response was:", text);
                        }
                    })
                    .catch(function(error) {
                        statusDiv.className = 'alert alert-danger';
                        statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error: ' + error.message;
                        console.error("Fetch error:", error);
                    });
                }
            });
            
            console.log("Emergency button with Twilio integration set up successfully");
        } else {
            console.error("Emergency button not found!");
        }
    });
    </script>

    <!-- Update the emergency button to directly integrate with Twilio -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM loaded - Setting up emergency button with direct Twilio integration");
        
        // Find the Send Emergency Alert button
        const emergencyButton = document.getElementById('sendEmergencyAlertBtn');
        
        if (emergencyButton) {
            console.log("Found emergency button, setting up click handler");
            
            // Replace the button with a clone to remove any existing event listeners
            const newButton = emergencyButton.cloneNode(true);
            emergencyButton.parentNode.replaceChild(newButton, emergencyButton);
            
            // Add click handler to the new button
            newButton.addEventListener('click', function(e) {
                e.preventDefault();
                console.log("Emergency button clicked - with direct Twilio integration");
                
                // Get the message from the textarea
                const messageElement = document.getElementById('emergency-message');
                const message = messageElement ? messageElement.value.trim() : '';
                const confirmText = message ? 
                    `Send this emergency message?\n\n"${message}"` : 
                    "Send an emergency alert to all your contacts?";
                
                if (confirm(confirmText)) {
                    // Create status element if it doesn't exist
                    let statusDiv = document.getElementById('alert-status-message');
                    if (!statusDiv) {
                        statusDiv = document.createElement('div');
                        statusDiv.id = 'alert-status-message';
                        statusDiv.className = 'alert alert-info';
                        statusDiv.style.margin = '10px 0';
                        newButton.parentNode.insertBefore(statusDiv, newButton.nextSibling);
                    }
                    
                    statusDiv.style.display = 'block';
                    statusDiv.className = 'alert alert-info';
                    statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending emergency alerts via Twilio...';
                    
                    // Create FormData to send the message
                    const formData = new FormData();
                    if (message) {
                        formData.append('message', message);
                    }
                    
                    // Use the dedicated emergency handler with direct Twilio integration
                    fetch('emergency_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(response) {
                        return response.text();
                    })
                    .then(function(text) {
                        console.log("Twilio direct response:", text);
                        
                        try {
                            const data = JSON.parse(text);
                            // Check if any messages were sent successfully
                            if (data.success > 0) {
                                statusDiv.className = 'alert alert-success';
                                statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                                
                                // Clear the message textarea
                                if (messageElement) {
                                    messageElement.value = '';
                                }
                                
                                // Show details of sent messages
                                if (data.details && data.details.length) {
                                    let detailsHtml = '<ul class="mt-2">';
                                    data.details.forEach(function(detail) {
                                        detailsHtml += '<li>' + detail + '</li>';
                                    });
                                    detailsHtml += '</ul>';
                                    statusDiv.innerHTML += detailsHtml;
                                }
                            } else {
                                statusDiv.className = 'alert alert-danger';
                                statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                            }
                        } catch (e) {
                            console.error("JSON parse error:", e);
                            console.error("Response was:", text);
                            statusDiv.className = 'alert alert-danger';
                            statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error parsing server response';
                        }
                    })
                    .catch(function(error) {
                        console.error("Fetch error:", error);
                        statusDiv.className = 'alert alert-danger';
                        statusDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error: ' + error.message;
                    });
                }
            });
            
            console.log("Emergency button with direct Twilio integration set up successfully");
        } else {
            console.error("Emergency button not found on page!");
        }
    });
    </script>

    <script>
    // Add provider search functionality with error handling
    (function() {
        // Wait for page to be fully loaded
        window.addEventListener('load', function() {
            try {
                // Create search bar HTML with improved styling
                const searchHTML = `
                    <div class="search-container" style="margin: 20px 0; display: flex; max-width: 500px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); border-radius: 50px; overflow: hidden; transition: all 0.3s ease;">
                        <div style="position: relative; flex: 1; display: flex; align-items: center;">
                            <i class="fas fa-search" style="position: absolute; left: 15px; color: #777; font-size: 14px;"></i>
                            <input type="text" id="providerSearchInput" placeholder="Search providers by name..." 
                                style="width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #e0e0e0; border-right: none; outline: none; font-size: 14px; color: #333; transition: all 0.3s ease; background: #f9f9f9;">
                            <button id="clearSearchBtn" style="background: none; border: none; padding: 0 15px; cursor: pointer; display: none; color: #999; transition: color 0.2s ease;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <button id="searchBtn" style="background: linear-gradient(45deg, #127dff, #0057b8); color: white; border: none; padding: 0 20px; cursor: pointer; transition: all 0.3s ease;">
                            <span>Search</span>
                        </button>
                    </div>
                `;
                
                // Add search bar to services tab
                const servicesTab = document.getElementById('services');
                if (servicesTab) {
                    const filterContainer = servicesTab.querySelector('#provider-filter-container');
                    if (filterContainer) {
                        // Insert search bar after filter container
                        filterContainer.insertAdjacentHTML('afterend', searchHTML);
                        
                        // Set up event listeners
                        const searchInput = document.getElementById('providerSearchInput');
                        const clearBtn = document.getElementById('clearSearchBtn');
                        const searchBtn = document.getElementById('searchBtn');
                        
                        if (searchInput && clearBtn && searchBtn) {
                            // Add focus effects
                            searchInput.addEventListener('focus', function() {
                                this.style.background = '#fff';
                                this.parentElement.parentElement.style.boxShadow = '0 3px 10px rgba(0,0,0,0.15)';
                            });
                            
                            searchInput.addEventListener('blur', function() {
                                this.style.background = '#f9f9f9';
                                this.parentElement.parentElement.style.boxShadow = '0 2px 6px rgba(0,0,0,0.1)';
                            });
                            
                            // Handle input events
                            searchInput.addEventListener('input', function() {
                                const searchTerm = this.value.trim().toLowerCase();
                                clearBtn.style.display = searchTerm ? 'block' : 'none';
                                
                                // Find all service slots
                                const slots = document.querySelectorAll('.time-slot');
                                let hasResults = false;
                                
                                // Filter slots based on provider name
                                slots.forEach(slot => {
                                    const staffNameEl = slot.querySelector('.staff-name');
                                    if (staffNameEl) {
                                        const staffName = staffNameEl.textContent.toLowerCase();
                                        const isMatch = searchTerm === '' || staffName.includes(searchTerm);
                                        slot.style.display = isMatch ? '' : 'none';
                                        if (isMatch) hasResults = true;
                                    }
                                });
                                
                                // Show/hide no results message
                                let noResultsMsg = document.getElementById('noSearchResults');
                                if (!hasResults && searchTerm !== '') {
                                    if (!noResultsMsg) {
                                        noResultsMsg = document.createElement('div');
                                        noResultsMsg.id = 'noSearchResults';
                                        noResultsMsg.style.padding = '20px';
                                        noResultsMsg.style.margin = '15px 0';
                                        noResultsMsg.style.background = '#f8f9fa';
                                        noResultsMsg.style.borderRadius = '8px';
                                        noResultsMsg.style.textAlign = 'center';
                                        noResultsMsg.style.color = '#666';
                                        noResultsMsg.style.fontStyle = 'italic';
                                        noResultsMsg.style.boxShadow = '0 1px 3px rgba(0,0,0,0.05)';
                                        const container = document.getElementById('schedules-container');
                                        if (container) container.appendChild(noResultsMsg);
                                    }
                                    noResultsMsg.textContent = `No providers found matching "${searchTerm}"`;
                                    noResultsMsg.style.display = 'block';
                                } else if (noResultsMsg) {
                                    noResultsMsg.style.display = 'none';
                                }
                            });
                            
                            // Handle search button click
                            searchBtn.addEventListener('click', function() {
                                searchInput.dispatchEvent(new Event('input'));
                            });
                            
                            // Hover effect for search button
                            searchBtn.addEventListener('mouseover', function() {
                                this.style.background = 'linear-gradient(45deg, #0062cc, #004a9f)';
                            });
                            
                            searchBtn.addEventListener('mouseout', function() {
                                this.style.background = 'linear-gradient(45deg, #127dff, #0057b8)';
                            });
                            
                            // Handle clear button click
                            clearBtn.addEventListener('click', function() {
                                searchInput.value = '';
                                this.style.display = 'none';
                                
                                // Show all slots
                                document.querySelectorAll('.time-slot').forEach(slot => {
                                    slot.style.display = '';
                                });
                                
                                // Hide no results message
                                const noResultsMsg = document.getElementById('noSearchResults');
                                if (noResultsMsg) noResultsMsg.style.display = 'none';
                                
                                // Focus back on search input
                                searchInput.focus();
                            });
                            
                            // Hover effect for clear button
                            clearBtn.addEventListener('mouseover', function() {
                                this.style.color = '#555';
                            });
                            
                            clearBtn.addEventListener('mouseout', function() {
                                this.style.color = '#999';
                            });
                        }
                    }
                }
            } catch (err) {
                console.error("Provider search setup error:", err.message);
            }
        });
    })();
    </script>

    


   
</body>
</html>