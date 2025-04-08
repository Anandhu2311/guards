<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GuardSphere - Profile</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #ff33a8;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            background-color: #e6e6e6;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo img {
            height: 40px;
            margin-right: 10px;
        }
        
        .logo-text {
            font-weight: bold;
            font-size: 18px;
            color: #333;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }
        
        .profile-btn {
            background-color: #6b2c6e;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .main-content {
            display: flex;
            padding: 40px;
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .profile-sidebar {
            background-color: #6b2c6e;
            border-radius: 15px;
            padding: 30px;
            width: 250px;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        
        .profile-photo {
            width: 80px;
            height: 80px;
            background-color: #291f4a;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: bold;
            font-size: 24px;
        }
        
        .profile-name {
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .sidebar-btn {
            background-color: #db77a9;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            width: 100%;
            text-align: center;
            margin-bottom: 5px;
            cursor: pointer;
        }
        
        .profile-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .card {
            background-color: #f0f0f0;
            border-radius: 15px;
            padding: 20px;
        }
        
        .card-title {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .card-title i {
            margin-right: 10px;
        }
        
        .form-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .input-group {
            flex: 1;
            min-width: 200px;
        }
        
        .input-group label {
            display: block;
            font-size: 14px;
            margin-bottom: 5px;
            color: #555;
        }
        
        .input-group input {
            width: 100%;
            padding: 10px;
            border: none;
            background-color: #db77a9;
            border-radius: 5px;
        }
        
        .contact-list {
            background-color: #db77a9;
            padding: 15px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .add-btn {
            background-color: #db77a9;
            color: black;
            border: none;
            padding: 10px;
            border-radius: 5px;
            width: 100%;
            text-align: center;
            margin-top: 10px;
            cursor: pointer;
        }
        
        .activity-item {
            border-left: 3px solid #6b2c6e;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        
        .activity-title {
            font-weight: 500;
        }
        
        .activity-time {
            font-size: 12px;
            color: #777;
        }
        
        .footer {
            background-color: transparent;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #333;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
        }
        
        .social-links a {
            color: #333;
            text-decoration: none;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
        }
        
        .footer-logo img {
            height: 20px;
            margin-right: 10px;
        }
        
        .footer-text {
            font-size: 12px;
            color: #333;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <img src="/api/placeholder/40/40" alt="GuardSphere Logo">
            <div class="logo-text">GUARDSPHERE</div>
        </div>
        <div class="nav-links">
            <a href="#">Home</a>
            <a href="#">About Us</a>
            <a href="#">Service</a>
            <a href="#">Location</a>
            <a href="#">Evidence</a>
            <a href="#" class="profile-btn">Profile</a>
        </div>
    </nav>
    
    <div class="main-content">
        <div class="profile-sidebar">
            <div class="profile-photo">US</div>
            <div class="profile-name">User Name</div>
            <button class="sidebar-btn">Edit Profile</button>
            <button class="sidebar-btn">Security Settings</button>
            <button class="sidebar-btn">Emergency Contacts</button>
            <button class="sidebar-btn">Privacy Settings</button>
        </div>
        
        <div class="profile-details">
            <div class="card">
                <div class="card-title">
                    <i>👤</i> Personal Information
                </div>
                <div class="form-group">
                    <div class="input-group">
                        <label>Full Name</label>
                        <input type="text">
                    </div>
                    <div class="input-group">
                        <label>Email</label>
                        <input type="email">
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-group">
                        <label>Phone</label>
                        <input type="tel">
                    </div>
                    <div class="input-group">
                        <label>Location</label>
                        <input type="text">
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-title">
                    <i>📞</i> Emergency Contacts
                </div>
                <div class="contact-list">
                    <div>
                        <div>Contacts</div>
                        <div>Details</div>
                    </div>
                    <div>✏️</div>
                </div>
                <button class="add-btn">Add New Contact</button>
            </div>
            
            <div class="card">
                <div class="card-title">
                    <i>🕒</i> Recent Activity
                </div>
                <div class="activity-item">
                    <div class="activity-title">Emergency Contact Updated</div>
                    <div class="activity-time">Yesterday, 3:45 PM</div>
                </div>
                <div class="activity-item">
                    <div class="activity-title">Location Shared</div>
                    <div class="activity-time">Today, 10:30 AM</div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <div class="footer-logo">
            <img src="/api/placeholder/20/20" alt="GuardSphere Logo">
            <div>GuardSphere</div>
        </div>
        <div class="footer-text">
            Empowering women with safety and security solutions worldwide.
        </div>
        <div class="social-links">
            <a href="#">📷</a>
            <a href="#">📘</a>
            <a href="#">🐦</a>
            <a href="#">👻</a>
        </div>
    </footer>
</body>
</html>