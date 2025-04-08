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
    $supporter_id = $_GET['delete'];
    $delete_stmt = $pdo->prepare("DELETE FROM supporters WHERE id = ?");
    if ($delete_stmt->execute([$supporter_id])) {
        $message = "<div class='alert-success'>Supporter deleted successfully!</div>";
    } else {
        $message = "<div class='alert-error'>Failed to delete supporter.</div>";
    }
}

// Handle enable/disable actions
$message = '';
if (isset($_GET['disable']) && is_numeric($_GET['disable'])) {
    $supporter_id = $_GET['disable'];
    
    // Get supporter email before updating
    $email_query = $pdo->prepare("SELECT sup_email, sup_name FROM supporters WHERE sup_id = ?");
    $email_query->execute([$supporter_id]);
    $supporter_data = $email_query->fetch(PDO::FETCH_ASSOC);
    
    $update_stmt = $pdo->prepare("UPDATE supporters SET is_active = 0 WHERE sup_id = ?");
    if ($update_stmt->execute([$supporter_id])) {
        // Send email notification
        $supporter_email = $supporter_data['sup_email'];
        $supporter_name = !empty($supporter_data['sup_name']) ? $supporter_data['sup_name'] : "Supporter";
        
        $subject = "GuardSphere Supporter Account Disabled";
        $message_body = "Dear " . $supporter_name . ",\n\n";
        $message_body .= "Your supporter account has been disabled by the administrator.\n";
        $message_body .= "You will not be able to access your account until it is enabled again.\n";
        $message_body .= "If you believe this is an error, please contact the administrator.\n";
        $message_body .= "\n\nRegards,\nGuardSphere Admin Team";
        
        if (sendEmail($supporter_email, $subject, $message_body)) {
            $message = "<div class='alert-success'>Supporter disabled successfully and email notification sent!</div>";
        } else {
            $message = "<div class='alert-warning'>Supporter disabled successfully but failed to send email notification. Please check server logs.</div>";
        }
    } else {
        $message = "<div class='alert-error'>Failed to disable supporter.</div>";
    }
}

if (isset($_GET['enable']) && is_numeric($_GET['enable'])) {
    $supporter_id = $_GET['enable'];
    
    // Get supporter email before updating
    $email_query = $pdo->prepare("SELECT sup_email, sup_name FROM supporters WHERE sup_id = ?");
    $email_query->execute([$supporter_id]);
    $supporter_data = $email_query->fetch(PDO::FETCH_ASSOC);
    
    $update_stmt = $pdo->prepare("UPDATE supporters SET is_active = 1 WHERE sup_id = ?");
    if ($update_stmt->execute([$supporter_id])) {
        // Send email notification
        $supporter_email = $supporter_data['sup_email'];
        $supporter_name = !empty($supporter_data['sup_name']) ? $supporter_data['sup_name'] : "Supporter";
        
        $subject = "GuardSphere Supporter Account Enabled";
        $message_body = "Dear " . $supporter_name . ",\n\n";
        $message_body .= "Your supporter account has been enabled by the administrator.\n";
        $message_body .= "You can now log in to your account and provide support services.\n";
        $message_body .= "\n\nRegards,\nGuardSphere Admin Team";
        
        if (sendEmail($supporter_email, $subject, $message_body)) {
            $message = "<div class='alert-success'>Supporter enabled successfully and email notification sent!</div>";
        } else {
            $message = "<div class='alert-warning'>Supporter enabled successfully but failed to send email notification. Please check server logs.</div>";
        }
    } else {
        $message = "<div class='alert-error'>Failed to enable supporter.</div>";
    }
}

// Fetch all supporters
$sql = "SELECT * FROM supporters ORDER BY sup_id DESC";
$result = $pdo->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Supporters - GuardSphere Admin</title>
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

        /* Supporter list styles */
        .supporter-list-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 25px;
            animation: fadeIn 0.3s ease;
        }

        .supporter-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .supporter-list-header h2 {
            color: #663399;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .supporter-list-header .add-btn {
            background: #663399;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .supporter-list-header .add-btn:hover {
            background: #7a44b8;
            transform: translateY(-2px);
        }

        .supporter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .supporter-card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
        }

        .supporter-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .supporter-info {
            margin-bottom: 15px;
        }

        .supporter-email {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            word-break: break-all;
        }

        .supporter-detail {
            display: flex;
            gap: 8px;
            margin-bottom: 5px;
            color: #555;
            font-size: 0.95rem;
        }

        .supporter-detail i {
            width: 16px;
            color: #663399;
        }

        .supporter-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.2s ease;
        }

        .edit-btn {
            background: #2196F3;
            color: white;
        }

        .edit-btn:hover {
            background: #0d8aee;
        }

        .delete-btn {
            background: #f44336;
            color: white;
        }

        .delete-btn:hover {
            background: #e53935;
        }

        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active {
            background: #c8f7c5;
            color: #0a8043;
        }

        .status-pending {
            background: #fff8e1;
            color: #ff8f00;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            text-align: center;
            color: #666;
        }

        .empty-state i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            font-size: 1.4rem;
        }

        .empty-state p {
            margin-bottom: 20px;
            max-width: 500px;
        }

        .empty-state .btn {
            background: #663399;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .empty-state .btn:hover {
            background: #7a44b8;
            transform: translateY(-2px);
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #4CAF50;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #f44336;
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
            .supporter-grid {
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

        /* Add styles for enable/disable buttons */
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
            <a href="#" class="sidebar-link active" data-title="Manage Supporters" onclick="toggleSubOptions(event)">
                <i class="fas fa-hands-helping"></i> 
                <span class="link-text">Manage Supporters</span> 
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="sub-options" style="display: block;">
                <a href="add_supporter.php" class="sub-option">
                    <i class="fas fa-user-plus"></i> 
                    <span class="link-text">Add Supporter</span>
                </a>
                <a href="manage_supporters.php" class="sub-option active">
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
            <a href="manage_users.php" class="sidebar-link" data-title="Manage Users">
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
        <div class="supporter-list-container">
            <div class="supporter-list-header">
                <h2><i class="fas fa-hands-helping"></i> Manage Supporters</h2>
                <a href="add_supporter.php" class="add-btn">
                    <i class="fas fa-plus"></i> Add New Supporter
                </a>
            </div>
            
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>
            
            <?php if ($result && $result->rowCount() > 0): ?>
                <div class="supporter-grid">
                    <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)): ?>
                        <div class="supporter-card">
                            <span class="status-badge <?php echo isset($row['password_updated']) && $row['password_updated'] ? 'status-active' : 'status-pending'; ?>">
                                <?php echo isset($row['password_updated']) && $row['password_updated'] ? 'Password Updated' : 'Password Not Updated'; ?>
                            </span>
                            
                            <div class="supporter-info">
                                <div class="supporter-email"><?php echo htmlspecialchars($row['sup_email']); ?></div>
                                
                                <div class="supporter-detail">
                                    <i class="fas fa-id-card"></i>
                                    <span>ID: <?php echo htmlspecialchars($row['sup_id']); ?></span>
                                </div>
                                
                                <?php if (isset($row['sup_name']) && !empty($row['sup_name'])): ?>
                                <div class="supporter-detail">
                                    <i class="fas fa-user"></i>
                                    <span>Name: <?php echo htmlspecialchars($row['sup_name']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($row['sup_phone_number']) && !empty($row['sup_phone_number'])): ?>
                                <div class="supporter-detail">
                                    <i class="fas fa-phone"></i>
                                    <span>Phone: <?php echo htmlspecialchars($row['sup_phone_number']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="supporter-detail">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Role ID: <?php echo htmlspecialchars($row['role_id']); ?></span>
                                </div>
                            </div>
                            
                            <div class="supporter-actions">
                                <?php if (isset($row['is_active']) && $row['is_active'] == 0): ?>
                                    <button class="action-btn enable-btn" onclick="location.href='manage_supporters.php?enable=<?php echo $row['sup_id']; ?>'">
                                        <i class="fas fa-check-circle"></i> Enable
                                    </button>
                                <?php else: ?>
                                    <button class="action-btn disable-btn" onclick="confirmDisable(<?php echo $row['sup_id']; ?>, '<?php echo htmlspecialchars($row['sup_email']); ?>')">
                                        <i class="fas fa-ban"></i> Disable
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-hands-helping"></i>
                    <h3>No Supporters Found</h3>
                    <p>There are no supporters in the system yet. Add your first supporter to get started.</p>
                    <a href="add_supporter.php" class="btn">
                        <i class="fas fa-plus"></i> Add Supporter
                    </a>
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
            
            sidebar.classList.toggle('collapsed');
            
            if (sidebar.classList.contains('collapsed')) {
                mainContent.style.marginLeft = '70px';
            } else {
                mainContent.style.marginLeft = '280px';
            }
        }

        // Confirm disable function
        function confirmDisable(id, email) {
            if (confirm(`Are you sure you want to disable supporter with email: ${email}?`)) {
                window.location.href = `manage_supporters.php?disable=${id}`;
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