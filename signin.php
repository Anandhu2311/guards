<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GuardSphere - Welcome Back</title>
    <script src="val2.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    }

    body {
        background-color: #f5f5f5;
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

    .login-container {
        background: rgba(255, 105, 180, 0.15);
        backdrop-filter: blur(20px);
        padding: 2.5rem;
        border-radius: 20px;
        width: 100%;
        max-width: 420px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .login-tabs {
        display: flex;
        margin-bottom: 20px;
        border-radius: 12px;
        overflow: hidden;
        background: rgba(0, 0, 0, 0.2);
    }

    .tab-btn {
        flex: 1;
        background: transparent;
        border: none;
        padding: 12px 5px;
        cursor: pointer;
        color: white;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .tab-btn:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .tab-btn.active {
        background: rgba(255, 105, 180, 0.3);
        font-weight: 600;
    }

    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 25%;
        width: 50%;
        height: 3px;
        background: #ff69b4;
        border-radius: 3px 3px 0 0;
    }

    .login-form {
        display: none;
        animation: fadeIn 0.5s ease-in-out;
    }

    .login-form.active {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
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
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        color: white;
        margin-bottom: 0.5rem;
    }

    .form-group input {
        width: 100%;
        padding: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        background: rgba(0, 0, 0, 0.2);
        color: white;
        transition: all 0.3s ease;
    }

    .form-group input:focus {
        outline: none;
        border-color: rgba(255, 255, 255, 0.3);
        box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
    }

    .form-group input.error {
        border-color: #ff4757;
        box-shadow: 0 0 0 2px rgba(255, 71, 87, 0.1);
    }

    .form-group input.valid {
        border-color: #2ed573;
        box-shadow: 0 0 0 2px rgba(46, 213, 115, 0.1);
    }

    .error-message {
        color: #ff4757;
        font-size: 0.8rem;
        margin-top: 0.5rem;
        padding-left: 0.5rem;
        opacity: 0;
        transform: translateY(-5px);
        transition: all 0.3s ease;
    }

    .error-message:not(:empty) {
        opacity: 1;
        transform: translateY(0);
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
        color: white;
        text-decoration: none;
        font-size: 0.9rem;
    }

    .signin-btn {
        width: 100%;
        padding: 1rem;
        background: linear-gradient(45deg, #ff69b4, #9932cc);
        border: none;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        margin: 1.5rem 0;
        position: relative;
        overflow: hidden;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 15px rgba(255, 105, 180, 0.2);
    }

    .signin-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 105, 180, 0.3);
        background: linear-gradient(45deg, #ff69b4, #b040ff);
    }

    .signin-btn:active {
        transform: translateY(1px);
        box-shadow: 0 2px 10px rgba(255, 105, 180, 0.2);
    }

    .signin-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(
            120deg,
            transparent,
            rgba(255, 255, 255, 0.2),
            transparent
        );
        transition: 0.5s;
    }

    .signin-btn:hover::before {
        left: 100%;
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

        .tab-btn {
            padding: 10px 5px;
            font-size: 0.8rem;
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

    /* Remove underline from links */
    a {
        text-decoration: none;
        /* Removes the underline */
    }

    /* Style for the button */
    .button {
        background-color: #4CAF50;
        /* Green background */
        border: none;
        /* No border */
        color: white;
        /* White text */
        padding: 15px 32px;
        /* Padding */
        text-align: center;
        /* Centered text */
        text-decoration: none;
        /* No underline */
        display: inline-block;
        /* Inline block */
        font-size: 16px;
        /* Font size */
        margin: 4px 2px;
        /* Margin */
        cursor: pointer;
        /* Pointer cursor on hover */
        border-radius: 12px;
        /* Rounded corners */
        transition: background-color 0.3s;
        /* Smooth transition */
    }

    /* Button hover effect */
    .button:hover {
        background-color: #45a049;
        /* Darker green on hover */
    }

    .validation-list {
        list-style: none;
        padding: 0;
        margin: 10px 0;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.9);
        background: rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        padding: 10px 15px;
    }

    .validation-item {
        margin: 5px 0;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
    }

    .validation-item:before {
        content: '●';
        margin-right: 8px;
        color: #ff4757;
        font-size: 0.8rem;
        transition: all 0.3s ease;
    }

    .validation-item.valid {
        color: #2ed573;
    }

    .validation-item.valid:before {
        content: '✓';
        color: #2ed573;
    }

    .input-wrapper {
        position: relative;
        margin-bottom: 5px;
    }

    .live-validation {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1rem;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .input-wrapper input:focus ~ .live-validation,
    .input-wrapper input.error ~ .live-validation,
    .input-wrapper input.valid ~ .live-validation {
        opacity: 1;
    }

    .error-message {
        color: red;
        font-size: 0.8rem;
        margin-top: 0.3rem;
    }

    .loading-message {
        color: #666;
        padding: 10px;
        margin-top: 10px;
        background-color: #f5f5f5;
        border-radius: 5px;
        text-align: center;
        font-size: 0.9rem;
    }

    #error-container {
        margin: 10px 0;
        display: none;
    }

    .error-message-box {
        background-color: rgba(255, 82, 82, 0.1);
        border: 1px solid rgba(255, 82, 82, 0.3);
        color: #ff5252;
        padding: 12px;
        border-radius: 8px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
        animation: fadeIn 0.3s ease-in-out;
    }

    .error-message-box i {
        font-size: 1.1rem;
    }
    </style>
</head>

<body>

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
            <a href="land.php">About Us</a>
            <a href="#services">Services</a>
            <a href="#contact">Contact</a>
        </div>
    </nav>

    <div class="main-content">
        <div class="login-container">
            <h2>Welcome Back</h2>
            <p>Sign in to your GuardSphere account</p>

            <form id="loginForm" onsubmit="return validateForm(event)">
                <div id="error-container"></div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="Enter your email" required>
                        <span class="live-validation" id="emailLiveValidation"></span>
                    </div>
                    <span class="error-message" id="emailError"></span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <span class="live-validation" id="passwordLiveValidation"></span>
                    </div>
                    <span class="error-message" id="passwordError"></span>
                    <ul class="validation-list" id="passwordValidationList">
                        <li class="validation-item" id="lengthCheck">At least 6 characters</li>
                        <li class="validation-item" id="uppercaseCheck">One uppercase letter</li>
                        <li class="validation-item" id="lowercaseCheck">One lowercase letter</li>
                        <li class="validation-item" id="numberCheck">One number</li>
                        <li class="validation-item" id="specialCheck">One special character</li>
                    </ul>
                </div>

                <a href="forgot.php" class="forgot-password">Forgot Password?</a>
                <button type="submit" class="signin-btn">Sign In</button>
                <div id="loadingMessage" class="loading-message" style="display: none;">Logging in...</div>
            </form>

            <a href="signup.php" class="signup-link">Don't have an account? Sign up now</a>
        </div>

        <img src="pics/Women-s-safety-at-workplace.jpg" alt="Diverse Women Illustration">
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-tagline">
                <div class="tagline-content">
                    <h3>GuardSphere</h3>
                    <p>Empowering women with safety and security solutions worldwide.</p>
                </div>
            </div>
            <div class="quick-links"
                style="width: 100%; padding-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.1);">
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

    <script>
    // Redirect if the back button is pressed after logout

    window.history.pushState(null, null, window.location.href);
    window.onpopstate = function() {
        window.history.pushState(null, null, window.location.href);
        alert("You have been logged out. Please log in again.");
        window.location.href = "signin.php";
    };
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        
        // Email live validation
        emailInput.addEventListener('input', function() {
            const email = this.value.trim();
            const isValidEmail = email.endsWith('@gmail.com');
            const emailLiveValidation = document.getElementById('emailLiveValidation');
            
            emailLiveValidation.textContent = isValidEmail ? '✅' : '❌';
            this.classList.toggle('error', !isValidEmail);
        });

        // Password live validation
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const validations = {
                lengthCheck: password.length >= 6,
                uppercaseCheck: /[A-Z]/.test(password),
                lowercaseCheck: /[a-z]/.test(password),
                numberCheck: /[0-9]/.test(password),
                specialCheck: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };

            // Update validation list items
            for (const [check, isValid] of Object.entries(validations)) {
                const element = document.getElementById(check);
                element.classList.toggle('valid', isValid);
            }

            // Update overall password validation indicator
            const isValidPassword = Object.values(validations).every(v => v);
            const passwordLiveValidation = document.getElementById('passwordLiveValidation');
            passwordLiveValidation.textContent = isValidPassword ? '✅' : '❌';
            this.classList.toggle('error', !isValidPassword);
        });
    });

    function validateForm(event) {
        event.preventDefault();
        let valid = true;
        
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const loadingMessage = document.getElementById('loadingMessage');
        const errorContainer = document.getElementById('error-container');
        
        // Reset errors
        document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
        document.querySelectorAll('input').forEach(el => el.classList.remove('error'));
        errorContainer.style.display = 'none';
        loadingMessage.style.display = 'none';
        
        // Email validation
        const emailValue = emailInput.value.trim();
        if (!emailValue.endsWith('@gmail.com')) {
            valid = false;
            document.getElementById('emailError').textContent = 'Please enter a valid Gmail address';
            emailInput.classList.add('error');
        }
        
        // Password validation
        const password = passwordInput.value;
        const passwordValidations = {
            length: password.length >= 6,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
        };
        
        if (!Object.values(passwordValidations).every(v => v)) {
            valid = false;
            document.getElementById('passwordError').textContent = 'Password does not meet requirements';
            passwordInput.classList.add('error');
        }
        
        if (valid) {
            loadingMessage.style.display = 'block';
            
            const formData = new FormData();
            formData.append('email', emailValue);
            formData.append('password', password);
            
            fetch('signin.inc.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    loadingMessage.style.display = 'none';
                    errorContainer.innerHTML = `
                        <div class="error-message-box">
                            <i class="fas fa-exclamation-circle"></i>
                            ${data.message}
                        </div>`;
                    errorContainer.style.display = 'block';
                }
            })
            .catch(error => {
                loadingMessage.style.display = 'none';
                errorContainer.innerHTML = `
                    <div class="error-message-box">
                        <i class="fas fa-exclamation-circle"></i>
                        An error occurred. Please try again.
                    </div>`;
                errorContainer.style.display = 'block';
            });
        }
        
        return false;
    }
    </script>
</body>

</html>