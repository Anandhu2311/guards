<?php
session_start();
require_once 'DBS.inc.php';

// Initialize notification count at the top of the file
$notificationCount = 0;
if (isset($_SESSION['email'])) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings 
        WHERE user_email = ? 
        AND status = 'follow_up'
        AND (notification_read = 0 OR notification_read IS NULL)
    ");
    $stmt->execute([$_SESSION['email']]);
    $notificationCount = $stmt->fetchColumn();
}

// Add this after session_start() and before the redirect check
$logout_message = '';
if (isset($_SESSION['logout_success'])) {
    $logout_message = $_SESSION['logout_success'];
    unset($_SESSION['logout_success']); // Clear the message after displaying
}

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login if the user is not logged in
if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}

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

        .main-content {
            background-color: rgb(33, 29, 105);
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
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: none;
            animation: fadeOut 3s ease-in-out;
        }

        @keyframes fadeOut {
            0% {
                opacity: 1;
            }

            70% {
                opacity: 1;
            }

            100% {
                opacity: 0;
            }
        }

        #map {
            height: 500px;
            width: 100%;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'notification_styles.php'; ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
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

        // Add this to handle the logout message
        document.addEventListener('DOMContentLoaded', function() {
            const logoutMessage = document.getElementById('logoutMessage');
            if (logoutMessage) {
                logoutMessage.style.display = 'block';
                setTimeout(() => {
                    logoutMessage.style.display = 'none';
                }, 3000);
            }
        });
    </script>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""> </script>
        <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
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
            <a href="home.php" style="color: #ffffff;">Home</a>
            <a href="Aboutus.php" style="color: #ffffff;">About Us</a>
            <a href="services.php" style="color: #ffffff;">Service</a>
            <a href="location.php" style="color: #ffffff;">Location</a>
            <div class="profile-section">
                <div class="notification-icon" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </div>
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
        <div id="map"></div>
        <button onclick="getCurrentLocation()" style="margin-top:10px; padding:10px; font-size:16px; cursor:pointer;">
            Get My Current Location
        </button>
        <div class="share-buttons" style="margin-top:15px; display:flex; gap:10px;">
            <button id="shareEmailBtn" onclick="shareLocationViaEmail()" style="padding:10px; font-size:16px; background-color:#4CAF50; color:white; border:none; border-radius:5px; cursor:pointer;">
                <i class="fas fa-envelope"></i> Share via Email
            </button>
            <button id="shareWhatsAppBtn" onclick="shareLocationViaWhatsApp()" style="padding:10px; font-size:16px; background-color:#25D366; color:white; border:none; border-radius:5px; cursor:pointer;">
                <i class="fab fa-whatsapp"></i> Share via WhatsApp
            </button>
        </div>
        <div id="notification-container" style="display:none; margin-top:15px; padding:15px; border-radius:5px; text-align:center; font-weight:500; transition:all 0.3s ease;">
            <span id="notification-message"></span>
            <button onclick="closeNotification()" style="background:transparent; border:none; margin-left:10px; cursor:pointer;">
                <i class="fas fa-times"></i>
            </button>
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
    var userID = 1; // Replace with actual logged-in user ID
    var lastClickedMarker = null;
    var lastClickedCircle = null;
    var lastClickedLocation = null;

    function updateLocation(latlng, message, autoShare = false) {
        if (lastClickedMarker) map.removeLayer(lastClickedMarker);
        if (lastClickedCircle) map.removeLayer(lastClickedCircle);

        lastClickedMarker = L.marker(latlng).addTo(map)
            .bindPopup(message + " " + latlng.toString())
            .openPopup();

        lastClickedCircle = L.circle(latlng, {
            color: 'blue',
            fillColor: 'lightblue',
            fillOpacity: 0.3,
            radius: 500
        }).addTo(map);

        lastClickedLocation = latlng;
        map.setView(latlng, 13);

        // Send location to database
        updateLocationInDB(latlng.lat, latlng.lng);

        // Enable share buttons once location is set
        document.getElementById('shareEmailBtn').disabled = false;
        document.getElementById('shareWhatsAppBtn').disabled = false;

        // Automatically share the location if requested
        if (autoShare) {
            shareLocationViaEmail();
        }
    }

    function updateLocationInDB(latitude, longitude) {
        fetch('update_location.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `user_id=${userID}&latitude=${latitude}&longitude=${longitude}`
        })
        .then(response => response.json())
        .then(data => console.log(data.message))
        .catch(error => console.error("Error updating location:", error));
    }

    function showNotification(message, type = 'success') {
        const container = document.getElementById('notification-container');
        const messageEl = document.getElementById('notification-message');
        
        // Set styles based on notification type
        if (type === 'success') {
            container.style.backgroundColor = '#4CAF50';
            container.style.color = 'white';
            container.style.boxShadow = '0 4px 8px rgba(76, 175, 80, 0.3)';
        } else if (type === 'error') {
            container.style.backgroundColor = '#f44336';
            container.style.color = 'white';
            container.style.boxShadow = '0 4px 8px rgba(244, 67, 54, 0.3)';
        } else if (type === 'warning') {
            container.style.backgroundColor = '#ff9800';
            container.style.color = 'white';
            container.style.boxShadow = '0 4px 8px rgba(255, 152, 0, 0.3)';
        } else if (type === 'info') {
            container.style.backgroundColor = '#2196F3';
            container.style.color = 'white';
            container.style.boxShadow = '0 4px 8px rgba(33, 150, 243, 0.3)';
        }
        
        // Set message
        messageEl.textContent = message;
        
        // Show with animation
        container.style.display = 'block';
        container.style.opacity = '0';
        setTimeout(() => {
            container.style.opacity = '1';
        }, 10);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            closeNotification();
        }, 5000);
    }

    function closeNotification() {
        const container = document.getElementById('notification-container');
        container.style.opacity = '0';
        setTimeout(() => {
            container.style.display = 'none';
        }, 300);
    }

    function shareLocationViaEmail() {
        if (!lastClickedLocation) {
            showNotification("Please get your current location first!", "warning");
            return;
        }

        console.log("Attempting to share location:", lastClickedLocation);

        // Show loading indication
        document.getElementById('shareEmailBtn').disabled = true;
        document.getElementById('shareEmailBtn').innerHTML = 'Sending...';
        showNotification("Sending location to your emergency contacts...", "info");

        // Log the request data
        const requestData = `latitude=${lastClickedLocation.lat}&longitude=${lastClickedLocation.lng}`;
        console.log("Sending request with data:", requestData);

        // Send request to server to email the location to registered supporters
        fetch('share_location_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: requestData
        })
        .then(response => {
            console.log("Raw response status:", response.status);
            // Try to get the raw text first to see what's being returned
            return response.text().then(text => {
                console.log("Raw response text:", text);
                try {
                    // Try to parse as JSON
                    return text ? JSON.parse(text) : {};
                } catch (e) {
                    console.error("JSON parse error:", e);
                    throw new Error("Invalid JSON response: " + text);
                }
            });
        })
        .then(data => {
            console.log("Server response (parsed):", data);
            if (data.success) {
                showNotification("Location shared successfully with your emergency contacts via email!", "success");
            } else {
                showNotification("Failed to share location: " + (data.message || "Unknown error"), "error");
                console.error("Error details:", data.details || "No details provided");
            }
        })
        .catch(error => {
            console.error("Error sharing location:", error);
            showNotification("An error occurred while sharing your location: " + error.message, "error");
        })
        .finally(() => {
            // Re-enable the button regardless of outcome
            document.getElementById('shareEmailBtn').disabled = false;
            document.getElementById('shareEmailBtn').innerHTML = '<i class="fas fa-envelope"></i> Share via Email';
        });
    }

    function shareLocationViaWhatsApp() {
        if (!lastClickedLocation) {
            showNotification("Please get your current location first!", "warning");
            return;
        }
        
        // Create Google Maps link with the location
        const googleMapsLink = `https://www.google.com/maps?q=${lastClickedLocation.lat},${lastClickedLocation.lng}`;
        
        // Create WhatsApp message
        const message = encodeURIComponent("I'm sharing my current location with you: " + googleMapsLink);
        
        // Open WhatsApp with the location
        window.open(`https://wa.me/?text=${message}`, '_blank');
        
        showNotification("WhatsApp opened with your location. Choose your contacts to share with.", "info");
    }

    function getCurrentLocation() {
        if (!navigator.geolocation) {
            alert("Geolocation is not supported by your browser.");
            return;
        }

        navigator.geolocation.getCurrentPosition(function(position) {
            var latlng = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };

            updateLocation(latlng, "Your current location:", false);
        }, function() {
            alert("Unable to retrieve your location.");
        });
    }

    // Initialize the map
    const map = L.map('map').setView([51.505, -0.09], 13); // Set initial view to a specific location

    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Optional: Add a marker to the map
    const marker = L.marker([51.505, -0.09]).addTo(map)
        .bindPopup('You are here!')
        .openPopup();
    </script>  

    <?php 
    // Include notification panel
    include 'notification_panel.php';
    ?>

    <?php include 'notification_script.php'; ?>

</body>

</html>