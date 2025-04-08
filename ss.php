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
    <title>GuardSphere - Women's Safety</title>
    <script src="disable-navigation.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
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
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
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

        .main-content {
            background-color:rgb(33, 29, 105);
            min-height: 80vh;
            padding: 4rem 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 4rem;
        }

        .hero-content {
            flex: 1;
            color: white;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .hero-text {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .hero-description {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            max-width: 600px;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
        }

        .cta-btn {
            padding: 1rem 2rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.3s ease;
        }

        .primary-btn {
            background: black;
            color: white;
        }

        .secondary-btn {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .features-grid {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            max-width: 600px;
            perspective: 1000px;
        }

        .feature-card {
            background: rgba(102, 51, 153, 0.8);
            padding: 2rem 1.5rem;
            border-radius: 15px;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            backdrop-filter: blur(10px);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #fff;
            transition: transform 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1);
        }

        .feature-card h3 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .feature-card p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .feature-hover {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(102, 51, 153, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .feature-card:hover .feature-hover {
            opacity: 1;
        }

        .feature-hover p {
            font-size: 0.9rem;
            line-height: 1.4;
        }

        footer {
            background: #1a1a1a;
            padding: 4rem 5% 2rem;
            color: #ffffff;
        }

        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            color: #663399;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .footer-section h3::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 50px;
            height: 2px;
            background: #663399;
        }

        .footer-section p {
            line-height: 1.6;
            margin-bottom: 1.5rem;
            color: #cccccc;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.8rem;
        }

        .footer-links a {
            color: #cccccc;
            text-decoration: none;
            transition: color 0.3s ease;
            display: inline-block;
        }

        .footer-links a:hover {
            color: #663399;
            transform: translateX(5px);
        }

        .contact-info {
            list-style: none;
        }

        .contact-info li {
            margin-bottom: 1rem;
            color: #cccccc;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .contact-info i {
            color: #663399;
            width: 20px;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-links a {
            color: #ffffff;
            background: #663399;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 51, 153, 0.3);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-bottom p {
            color: #888888;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .footer-container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer-section h3::after {
                left: 50%;
                transform: translateX(-50%);
            }

            .social-links {
                justify-content: center;
            }

            .contact-info li {
                justify-content: center;
            }
        }

        @media (max-width: 1024px) {
            .features-grid {
                max-width: 500px;
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .features-grid {
                grid-template-columns: 1fr;
                max-width: 400px;
                margin: 0 auto;
            }

            .feature-card {
                padding: 1.5rem;
            }

            .feature-icon {
                font-size: 2rem;
            }

            .feature-card h3 {
                font-size: 1.5rem;
            }
        }

        /* Add these new styles */
        .logout-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #4CAF50;
            color: white;
            padding: 15px 25px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
            animation: fadeOut 3s ease-in-out;
        }

        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; }
        }

        .elegant-container {
            background: rgba(102, 51, 153, 0.8);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .elegant-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .show-info-btn {
            background: #663399;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .show-info-btn:hover {
            background: #4b2e7e;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php if ($logout_message): ?>
    <div class="logout-message" id="logoutMessage">
        <?php echo htmlspecialchars($logout_message); ?>
    </div>
    <?php endif; ?>

    <nav style="background: #1a1a1a;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 120" class="logo">
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
                <path d="M45 40
                         Q50 45, 55 40
                         Q52 35, 45 40" 
                      fill="#FF69B4" 
                      opacity="0.5"/>
            </g>
            <text x="150" y="80" font-family="Arial Black, sans-serif" font-weight="900" font-size="60" fill="#ffffff">GUARDSPHERE</text>
            <text x="150" y="105" font-family="Arial, sans-serif" font-size="20" fill="#ffffff">GUARDED BY GUARDSPHERE.</text>
        </svg>
        <div class="nav-links">
            <a href="#home" style="color: #ffffff;">Home</a>
            <a href="Aboutus.php" style="color: #ffffff;">About Us</a>
            <a href="#service" style="color: #ffffff;">Service</a>
            <a href="#location" style="color: #ffffff;">Location</a>
            <a href="#evidence" style="color: #ffffff;">Evidence</a>
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

    <div class="main-content">
    <h2>Admin Dashboard</h2>
    <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)) { ?>
        <div class="user-container elegant-container">
            <p><strong>Name:</strong> <?php echo $row['name']; ?></p>
            <p><strong>Email:</strong> <?php echo $row['email']; ?></p>
            <p><strong>Phone Number:</strong> <?php echo $row['phone_number'] ?? 'N/A'; ?></p>
            <button class="showEmergencyBtn" data-user-id="<?php echo $row['id']; ?>">Show Emergency Info</button>
            <div id="dropdown-<?php echo $row['id']; ?>" class="dropdown-content" style="display: none;">
            <p><strong>Emergency Contacts:</strong> <?php echo $row['emergency_names'] ?? 'N/A'; ?></p>
            <p><strong>Emergency Numbers:</strong> <?php echo $row['emergency_numbers'] ?? 'N/A'; ?></p>
            <p><strong>Relationships:</strong> <?php echo $row['relationships'] ?? 'N/A'; ?></p>
            </div>
        </div>
    <?php } ?>
    </div>

    <footer>
        <div class="footer-container">
            <div class="footer-section">
                <h3>About GuardSphere</h3>
                <p>Empowering women with safety and security solutions worldwide. Join our community to make a difference.</p>
                <div class="social-links">
                    <a href="#instagram" class="instagram" aria-label="Instagram" style="background: linear-gradient(45deg, #405DE6, #5851DB, #833AB4, #C13584, #E1306C, #FD1D1D);">
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
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#contact">Contact</a></li>
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
         document.querySelectorAll('.showEmergencyBtn').forEach(button => {
    button.addEventListener('click', function() {
        let userId = this.getAttribute('data-user-id'); // Get user ID from button attribute
        let dropdown = document.getElementById('dropdown-' + userId); // Get correct dropdown

        if (!dropdown) {
            console.error("Dropdown not found for user ID:", userId);
            return;
        }

        // Toggle visibility of the dropdown
        if (dropdown.style.display === 'none' || dropdown.style.display === '') {
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
    });
});

    </script> 
    
</body>

</html>