<?php
session_start();
require 'DBS.inc.php'; // Database connection file

// Define the admin credentials (should match admin.php)
$admin_email = "admin@gmail.com"; // Change this to your actual admin email
$admin_password = "Admin@123"; // Change this to your actual admin password (hashed in production)

// Redirect to login if the user is not logged in
if (!isset($_SESSION['email']) || $_SESSION['email'] !== $admin_email) {
    header("Location: signin.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $adv_email = trim($_POST['adv_email']);
    $role_id = 2; // Set role_id for advisor (assuming 2 is for advisors)

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT adv_email FROM advisors WHERE adv_email = ?");
    $stmt->execute([$adv_email]);
    if ($stmt->rowCount() > 0) {
        $message = "<div class='alert-error'>Error: This email is already registered as an advisor.</div>";
    } else {
        // Generate a secure password
        $raw_password = generateSecurePassword();
        
        // Hash the password for database storage
        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

        // Insert into advisors table with role_id and hashed password
        $stmt = $pdo->prepare("INSERT INTO advisors (adv_email, adv_password, password_updated, role_id) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$adv_email, $hashed_password, false, $role_id])) {
            
            // Send Email Using PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'guardsphere01@gmail.com'; // 🔴 Replace with your Gmail
                $mail->Password = 'qvhl kcbg xrph stff'; // 🔴 Replace with App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // 🔹 Fix SSL Error
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ),
                );

                // Email Details
                $mail->setFrom('guardsphere01@gmail.com', 'GuardSphere Admin');
                $mail->addAddress($adv_email);
                $mail->Subject = 'Your GuardSphere Advisor Account';
                $mail->Body = "Hello,\n\nAn admin has created your advisor account.\n\nEmail: $adv_email\nPassword: $raw_password\n\nPlease log in and change your password.";

                if ($mail->send()) {
                    $message = "<div class='alert-success'>Advisor added successfully! Email sent.</div>";
                } else {
                    $message = "<div class='alert-warning'>Advisor added, but email sending failed.</div>";
                }
            } catch (Exception $e) {
                $message = "<div class='alert-warning'>Advisor added, but email could not be sent. Error: " . $mail->ErrorInfo . "</div>";
            }
        } else {
            $message = "<div class='alert-error'>Error adding advisor.</div>";
        }
    }
}

function generateSecurePassword($length = 8) {
    $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lower = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
    
    // Ensure the password contains at least one of each character type
    $password = $upper[random_int(0, strlen($upper) - 1)] .
                $lower[random_int(0, strlen($lower) - 1)] .
                $numbers[random_int(0, strlen($numbers) - 1)] .
                $special[random_int(0, strlen($special) - 1)];
    
    // Fill the rest of the password length with random characters from all types
    $allCharacters = $upper . $lower . $numbers . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $allCharacters[random_int(0, strlen($allCharacters) - 1)];
    }
    
    // Shuffle the password to ensure randomness
    return str_shuffle($password);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Advisor - GuardSphere Admin</title>
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

        /* Form styles */
        .form-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 25px;
            animation: fadeIn 0.3s ease;
            max-width: 600px;
            margin: 0 auto;
        }

        .form-card h2 {
            color: #663399;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

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
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: #663399;
            outline: none;
            box-shadow: 0 0 0 2px rgba(102, 51, 153, 0.2);
        }

        .btn {
            background: #663399;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn:hover {
            background: #7a44b8;
            transform: translateY(-2px);
        }

        .btn-block {
            display: block;
            width: 100%;
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

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
    </style>
</head>
<body>
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
            <a href="#" class="sidebar-link active" data-title="Manage Advisors" onclick="toggleSubOptions(event)">
                <i class="fas fa-user-tie"></i> 
                <span class="link-text">Manage Advisors</span> 
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="sub-options" style="display: block;">
                <a href="add_advisor.php" class="sub-option active">
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

    <div class="main-content" id="mainContent">
        <div class="form-card">
            <h2><i class="fas fa-user-plus"></i> Add New Advisor</h2>
            
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="adv_email">Advisor Email</label>
                    <input type="email" id="adv_email" name="adv_email" class="form-control" required 
                           placeholder="Enter advisor's email address">
                    <small>A temporary password will be generated and sent to this email.</small>
                </div>
                
                <button type="submit" class="btn btn-block">
                    <i class="fas fa-plus-circle"></i> Add Advisor
                </button>
            </form>
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
