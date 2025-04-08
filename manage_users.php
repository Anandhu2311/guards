<?php
session_start();
require 'DBS.inc.php'; // Database connection file
require_once 'send_email.php';

// Define the admin credentials
$admin_email = "admin@gmail.com"; // Change this to your actual admin email
$admin_password = "Admin@123"; // Change this to your actual admin password (hashed in production)

// Redirect to login if the user is not logged in
if (!isset($_SESSION['email']) || $_SESSION['email'] !== $admin_email) {
    header("Location: signin.php");
    exit();
}

// Handle delete action if provided
$message = '';
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = $_GET['delete'];
    $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($delete_stmt->execute([$user_id])) {
        $message = "<div class='alert-success'>User deleted successfully!</div>";
    } else {
        $message = "<div class='alert-error'>Failed to delete user.</div>";
    }
}

// Add a toggle status handler
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $user_id = $_GET['toggle'];
    
    // First get current status
    $status_query = $pdo->prepare("SELECT is_active, email FROM users WHERE id = ?");
    $status_query->execute([$user_id]);
    $current_status = $status_query->fetch(PDO::FETCH_ASSOC);
    
    // Toggle the status (1 to 0 or 0 to 1)
    $new_status = $current_status['is_active'] ? 0 : 1;
    
    $update_stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    if ($update_stmt->execute([$new_status, $user_id])) {
        // Send email notification
        $user_email = $current_status['email'];
        $status_text = $new_status ? "enabled" : "disabled";
        
        $subject = "GuardSphere Account " . ucfirst($status_text);
        $message_body = "Dear User,\n\n";
        $message_body .= "Your account has been " . $status_text . " by the administrator.\n";
        if ($new_status) {
            $message_body .= "You can now log in to your account and access all features.\n";
        } else {
            $message_body .= "You will not be able to access your account until it is enabled again.\n";
            $message_body .= "If you believe this is an error, please contact support.\n";
        }
        $message_body .= "\n\nRegards,\nGuardSphere Admin Team";
        
        // Log this email sending attempt for debugging
        error_log("Sending account status email to user: $user_email (Status: $status_text)");
        
        if (sendEmail($user_email, $subject, $message_body)) {
            error_log("Email sent successfully to $user_email");
            $message = "<div class='alert-success'>User account " . $status_text . " successfully and email notification sent!</div>";
        } else {
            error_log("Email sending failed to $user_email");
            $message = "<div class='alert-warning'>User account " . $status_text . " successfully but failed to send email notification. Check error logs for details.</div>";
        }
    } else {
        $message = "<div class='alert-error'>Failed to update user status.</div>";
    }
}

// Fetch all normal users
$sql = "SELECT * FROM users ORDER BY id DESC"; // Adjust the query as needed
$result = $pdo->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - GuardSphere Admin</title>
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

        /* User list styles */
        .user-list-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 25px;
            animation: fadeIn 0.3s ease;
        }

        .user-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .user-list-header h2 {
            color: #663399;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .user-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid #eaeaea;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.12);
        }

        .user-info {
            margin-bottom: 15px;
        }

        .user-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            word-break: break-all;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 8px;
        }

        .user-detail {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            color: #555;
            font-size: 0.95rem;
            align-items: center;
        }

        .user-detail i {
            width: 16px;
            color: #663399;
            font-size: 14px;
        }
        
        .user-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 10px;
        }
        
        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            width: 100%;
            justify-content: center;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .enable-btn {
            background-color: #28a745;
            color: white;
        }

        .enable-btn:hover {
            background-color: #218838;
        }

        .disable-btn {
            background-color: #dc3545;
            color: white;
        }

        .disable-btn:hover {
            background-color: #c82333;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-top: 5px;
            margin-bottom: 10px;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .user-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
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
        }
    </style>
</head>
<body>
    <!-- Header -->
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
                <i class="fas fa-hands-helping"></i> 
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
            <a href="manage_users.php" class="sidebar-link active" data-title="Manage Users">
                <i class="fas fa-users"></i> 
                <span class="link-text">Manage Normal Users</span>
            </a>
        </div>
        <div class="sidebar-item">
            <a href="#" class="sidebar-link" data-title="Scheduling" onclick="toggleSubOptions(event)">
                <i class="fas fa-calendar-alt"></i> 
                <span class="link-text">Scheduling</span> 
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="sub-options">
                <a href="#" class="sub-option" onclick="showScheduleManager()">
                    <i class="fas fa-cog"></i> 
                    <span class="link-text">Manage Schedules</span>
                </a>
                <a href="#" class="sub-option" onclick="showCalendarView()">
                    <i class="fas fa-calendar-week"></i> 
                    <span class="link-text">Calendar View</span>
                </a>
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

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="user-list-container">
            <div class="user-list-header">
                <h2><i class="fas fa-users"></i> Manage Normal Users</h2>
            </div>
            
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>
            
            <?php if ($result && $result->rowCount() > 0): ?>
                <div class="user-grid">
                    <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)): ?>
                        <div class="user-card">
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($row['name']); ?></div>
                                
                                <?php if (isset($row['is_active'])): ?>
                                <div class="user-status <?php echo $row['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <i class="fas <?php echo $row['is_active'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                    <?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="user-detail">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($row['email']); ?></span>
                                </div>
                                
                                <?php if (isset($row['phone_number']) && !empty($row['phone_number'])): ?>
                                <div class="user-detail">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($row['phone_number']); ?></span>
                                </div>
                                <?php else: ?>
                                <div class="user-detail">
                                    <i class="fas fa-phone"></i>
                                    <span>N/A</span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($row['created_at']) && !empty($row['created_at'])): ?>
                                <div class="user-detail">
                                    <i class="fas fa-calendar"></i>
                                    <span>Joined: <?php echo htmlspecialchars(date('M d, Y', strtotime($row['created_at']))); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="user-actions">
                                <?php if (isset($row['is_active']) && $row['is_active'] == 1): ?>
                                    <button class="action-btn disable-btn" onclick="confirmToggle(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['email']); ?>', 'disable')">
                                        <i class="fas fa-ban"></i> Disable
                                    </button>
                                <?php else: ?>
                                    <button class="action-btn enable-btn" onclick="confirmToggle(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['email']); ?>', 'enable')">
                                        <i class="fas fa-check"></i> Enable
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Users Found</h3>
                    <p>There are no regular users registered in the system yet.</p>
                </div>
            <?php endif; ?>
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
            
            // Toggle current submenu
            if (subOptions.style.display === 'block') {
                subOptions.style.display = 'none';
                link.classList.remove('active');
            } else {
                subOptions.style.display = 'block';
                link.classList.add('active');
            }
        }

        // Toggle sidebar collapse
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('active');
            
            if (sidebar.classList.contains('active')) {
                mainContent.style.marginLeft = '280px';
            } else {
                mainContent.style.marginLeft = '0';
            }
        }

        // Add click event listener to document to close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const profileDropdown = document.getElementById('profileDropdown');
            const userAvatar = document.querySelector('.user-avatar');
            
            if (profileDropdown.classList.contains('show') && 
                !profileDropdown.contains(event.target) && 
                !userAvatar.contains(event.target)) {
                profileDropdown.classList.remove('show');
            }
        });

        // Replace the confirmDelete function with confirmToggle in the JavaScript
        function confirmToggle(id, email, action) {
            if (confirm(`Are you sure you want to ${action} user with email: ${email}?`)) {
                window.location.href = `manage_users.php?toggle=${id}`;
            }
        }

        // Show schedule manager
        function showScheduleManager() {
            window.location.href = "admin.php?view=scheduleManager";
        }

        // Show calendar view
        function showCalendarView() {
            window.location.href = "admin.php?view=calendarView";
        }

        // Show booking manager
        function showBookingManager() {
            window.location.href = "admin.php?view=bookingManager";
        }
    </script>
</body>
</html>
