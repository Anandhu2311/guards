<?php
require 'DBS.inc.php';
session_start();

$loginError = ""; // Variable to store error messages

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $adv_email = trim($_POST['adv_email']);
    $adv_password = trim($_POST['adv_password']);

    // Fetch advisor details
    $stmt = $pdo->prepare("SELECT * FROM advisors WHERE adv_email = ?");
    $stmt->execute([$adv_email]);
    $advisor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($advisor) {
        if ($advisor['password_updated']) {
            // Compare hashed password after first update
            if (password_verify($adv_password, $advisor['adv_password'])) {
                $_SESSION['adv_id'] = $advisor['adv_id'];

                // Redirect based on profile completion
                if (empty($advisor['adv_name']) || empty($advisor['adv_phone_number'])) {
                    header("Location: adv_update_profile.php");
                } else {
                    header("Location: adv_dashboard.php");
                }
                exit();
            } else {
                $loginError = "Invalid email or password.";
            }
        } else {
            // 🔹 First login (password is not hashed yet)
            if ($adv_password === $advisor['adv_password']) {
                $_SESSION['adv_id'] = $advisor['adv_id'];
                header("Location: adv_change_pass.php"); // Redirect to change password
                exit();
            } else {
                $loginError = "Invalid email or password.";
            }
        }
    } else {
        $loginError = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advisor Sign In - GuardSphere</title>
    <style>
    
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to right, #6a82fb, #fc5c7d); /* Body background gradient */
            text-align: center;
            margin: 0; /* Remove margin to avoid constraining */
            padding: 0; /* Remove padding to avoid constraining */
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        nav {
            background: linear-gradient(to right, #211d69, #ff69b4);
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        nav::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .logo {
            height: 40px;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: white;
            padding: 0.7rem 1.2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-weight: 500;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .main-content {
            background-color: rgb(33, 29, 105);
            min-height: 80vh;
            padding: 4rem 5%;
            display: flex;
            align-items: center;
            justify-content: space-around;
            gap: 2rem;
        }

        .login-form {
            background: rgba(255, 105, 180, 0.3);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 20px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .login-form h2 {
            color: white;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .login-form p {
            color: white;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            color: white;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: none;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.2);
            color: white;
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .form-group input.error {
            border: 2px solid red;
        }

        .error-message {
            color: red;
            font-size: 0.8rem;
            margin-top: 0.3rem;
        }

        /* Add new success message styles */
        .success-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: rgba(46, 213, 115, 0.9);
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.5s ease-out, fadeOut 0.5s ease-out 2.5s forwards;
            z-index: 1000;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem 0;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
        }

        .forgot-password {
            color: #007bff; /* Bright blue color for visibility */
            text-decoration: none; /* Remove underline */
            font-size: 0.9rem; /* Font size */
            margin-top: 1rem; /* Margin for spacing */
            display: inline-block; /* Make it an inline block for better spacing */
            transition: color 0.3s ease, transform 0.3s ease; /* Smooth transition for color and transform */
        }

        .forgot-password:hover {
            color: #0056b3; /* Darker blue on hover */
            transform: scale(1.05); /* Slightly enlarge on hover */
        }

        .signin-btn {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-bottom: 1rem;
        }

        .signin-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .divider {
            text-align: center;
            color: white;
            margin: 1rem 0;
        }

        .social-login {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .social-btn {
            flex: 1;
            padding: 0.8rem;
            border: none;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.2);
            color: white;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1rem;
        }

        .social-btn:hover {
            background: rgba(0, 0, 0, 0.3);
            transform: scale(1.05);
        }

        .signup-link {
            display: block;
            text-align: center;
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
        }

        footer {
            background: linear-gradient(to right, #211d69, #ff69b4);
            padding: 3rem 5%;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        footer p {
            font-size: 1.1rem;
            max-width: 400px;
            line-height: 1.6;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .quick-links {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .quick-links a {
            text-decoration: none;
            color: white;
            padding: 0.7rem 1.2rem;
            border-radius: 25px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .quick-links a:hover::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            z-index: -1;
        }

        .quick-links a:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .error-message {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .main-content {
                flex-direction: column;
                padding: 2rem;
            }

            .quick-links {
                justify-content: center;
                margin-top: 1rem;
            }

            footer {
                flex-direction: column;
                text-align: center;
                gap: 2rem;
                padding: 2rem;
            }

            footer p {
                max-width: 100%;
            }

            .social-login {
                flex-direction: column;
                gap: 1rem;
            }
        }

        /* Update logo text colors for better visibility on dark background */
        .logo text[y="80"] {
            fill: white;
        }
        
        .logo text[y="105"] {
            fill: rgba(255, 255, 255, 0.7);
        }

        .footer-content {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 2rem;
            align-items: center;
            justify-content: center;
            min-height: 200px;
        }

        .footer-tagline {
            max-width: 600px;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem 0;
        }

        .tagline-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .tagline-content h3 {
            font-size: 1.8rem;
            margin: 0;
            line-height: 1.2;
            color: white;
        }

        .tagline-content p {
            font-size: 1.2rem;
            margin: 0;
            line-height: 1.2;
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
        }

        .footer-bottom {
            width: 100%;
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .footer-bottom p {
            font-size: 0.9rem;
            opacity: 0.8;
            margin: 0;
        }

        @media (max-width: 768px) {
            .footer-content {
                gap: 1.5rem;
            }

            .footer-tagline {
                max-width: 100%;
                text-align: center;
            }

            .footer-bottom {
                padding-top: 1.5rem;
            }
        }

        /* Remove underline from links */
        a {
            text-decoration: none; /* Removes the underline */
        }

        /* Style for the button */
        .button {
            background-color: #4CAF50; /* Green background */
            border: none; /* No border */
            color: white; /* White text */
            padding: 15px 32px; /* Padding */
            text-align: center; /* Centered text */
            text-decoration: none; /* No underline */
            display: inline-block; /* Inline block */
            font-size: 16px; /* Font size */
            margin: 4px 2px; /* Margin */
            cursor: pointer; /* Pointer cursor on hover */
            border-radius: 12px; /* Rounded corners */
            transition: background-color 0.3s; /* Smooth transition */
        }

        /* Button hover effect */
        .button:hover {
            background-color: #45a049; /* Darker green on hover */
        }
    
        .login-container {
            background: linear-gradient(to right, #ffffff, #f1f1f1); /* Elegant gradient for login form */
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            display: inline-block;
            color: black; /* Change text color for better contrast */
            text-align: center;
            margin: 50px auto; /* Center the container with margin */
            max-width: 400px; /* Set a max width for the container */
        }
        .login-container h2 {
            color: #333; /* Darker color for the heading */
            margin-bottom: 1rem;
        }
        .login-container input {
            background: rgba(255, 255, 255, 0.8); /* Slightly transparent input background */
            color: black; /* Change input text color for better contrast */
            padding: 10px;
            border: none;
            border-radius: 8px;
            margin-bottom: 1rem;
            width: 100%;
        }
        .login-container button {
            background-color: #ff69b4;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s;
            width: 100%;
        }
        .login-container button:hover {
            background-color: #ff1493;
        }
        .error {
            color: red;
            font-size: 0.9rem;
            margin-top: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
<nav>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 120" class="logo">
            <g transform="translate(30, 10)">
                <path d="M50 35 C45 25, 30 25, 25 35 C20 45, 25 55, 50 75 C75 55, 80 45, 75 35 C70 25, 55 25, 50 35" fill="#FF1493"/>
                <path d="M15 55 C12 55, 5 58, 5 75 C5 82, 8 87, 15 90 L25 92 C20 85, 18 80, 20 75 C22 70, 25 68, 30 70 C28 65, 25 62, 20 62 C15 62, 15 65, 15 55" fill="#9932CC"/>
                <path d="M85 55 C88 55, 95 58, 95 75 C95 82, 92 87, 85 90 L75 92 C80 85, 82 80, 80 75 C78 70, 75 68, 70 70 C72 65, 75 62, 80 62 C85 62, 85 65, 85 55" fill="#9932CC"/>
                <path d="M45 40 Q50 45, 55 40 Q52 35, 45 40" fill="#FF69B4" opacity="0.5"/>
            </g>
            <text x="150" y="80" font-family="Arial Black, sans-serif" font-weight="900" font-size="60" fill="#333">GUARDSPHERE</text>
            <text x="150" y="105" font-family="Arial, sans-serif" font-size="20" fill="#666">GUARDED BY GUARDSPHERE.</text>
        </svg>
        <div class="nav-links">
            <a href="land.php">About Us</a>
            <a href="#services">Services</a>
            <a href="#contact">Contact</a>
        </div>
    </nav>

    <div class="login-container">
        <h2>Advisor Login</h2>
        <?php if (!empty($loginError)) echo "<p class='error'>$loginError</p>"; ?>
        <form method="POST" action="">
            <input type="email" name="adv_email" required placeholder="Enter Email">
            <input type="password" name="adv_password" required placeholder="Enter Password">
            <a href="adv_forgot.php" class="forgot-password">Forgot Password?</a>
            <button type="submit">Login</button>
        </form>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-tagline">
                <div class="tagline-content">
                    <h3>GuardSphere</h3>
                    <p>Empowering women with safety and security solutions worldwide.</p>
                </div>
            </div>
            <div class="quick-links" style="width: 100%; padding-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <a href="#about" style="font-size: 1.1rem;">About Us</a>
                <a href="#courses" style="font-size: 1.1rem;">Safety Courses</a>
                <a href="#products" style="font-size: 1.1rem;">Products</a>
                <a href="#help" style="font-size: 1.1rem;">Emergency Help</a>
                <a href="#plans" style="font-size: 1.1rem;">Subscription Plans</a>
            </div>
            <div class="footer-bottom">
                <p style="font-size: 1rem;">&copy; 2024 GuardSphere. All rights reserved.</p>
            </div>
        </div>
    </footer>

</body>
</html>
