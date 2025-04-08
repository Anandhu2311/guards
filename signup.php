<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GuardSphere - Create Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="vall.js"></script>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5%;
            background: linear-gradient(135deg, #211d69 0%, #FF1493 100%);
        }

        .logo {
            height: 40px;
        }

        .logo text {
            fill: #fff;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-links a:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3), 
                       0 4px 8px rgba(255, 20, 147, 0.2);
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

        .signup-form {
            background: rgba(255, 105, 180, 0.15);
            backdrop-filter: blur(20px);
            padding: 2.5rem;
            border-radius: 20px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .signup-form h2 {
            color: white;
            margin-bottom: 0.5rem;
        }

        .signup-form p {
            color: white;
            margin-bottom: 1.5rem;
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

        .error {
            color:rgb(2, 11, 11);
            font-size: 0.8rem;
            margin-top: 0.3rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .checkbox-group label {
            color: white;
            font-size: 0.9rem;
        }

        .create-account-btn {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .create-account-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .login-link {
            display: block;
            text-align: center;
            color: white;
            margin-top: 1rem;
            text-decoration: none;
            font-size: 0.9rem;
        }

        footer {
            background: linear-gradient(135deg, #211d69 0%, #FF1493 100%);
            padding: 1rem 5%;
            color: white;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-tagline {
            text-align: center;
            margin-bottom: 1rem;
        }

        .footer-tagline h3 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            background: linear-gradient(to right, #fff, #ffd1e8);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }

        .footer-tagline p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            justify-items: center;
            padding: 2rem 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .quick-links a {
            text-decoration: none;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.95rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .quick-links a:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .footer-bottom {
            text-align: center;
            margin-top: 2rem;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }
        .error {
    color: red;
    font-size: 14px;
    margin-top: 5px;
        }

        .validation-list {
    list-style: none;
    padding: 0;
    margin: 5px 0;
    font-size: 0.8rem;
    color: white;
}

.validation-item {
    margin: 2px 0;
    display: flex;
    align-items: center;
}

.validation-item:before {
    content: '❌';
    margin-right: 5px;
}

.validation-item.valid:before {
    content: '✅';
}

.input-wrapper {
    position: relative;
}

.live-validation {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: white;
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

        @media (max-width: 768px) {
            footer {
                padding: 3rem 1.5rem;
            }
            
            .quick-links {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .footer-tagline h3 {
                font-size: 1.5rem;
            }
        }

        .signin-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(45deg, #9932cc, #ff1493);
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
            box-shadow: 0 4px 15px rgba(153, 50, 204, 0.2);
        }

        .signin-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(153, 50, 204, 0.3);
            background: linear-gradient(45deg, #b040ff, #ff1493);
        }

        .signin-btn:active {
            transform: translateY(1px);
            box-shadow: 0 2px 10px rgba(153, 50, 204, 0.2);
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

        .signup-link {
            display: block;
            text-align: center;
            color: white;
            text-decoration: none;
            font-size: 0.95rem;
            margin-top: 1.5rem;
            padding: 0.5rem;
            transition: all 0.3s ease;
        }

        .signup-link:hover {
            color: #ff69b4;
            transform: translateY(-1px);
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

    <div class="main-content">
        <div class="signup-form">
            <h2>Create Account</h2>
            <p>Join GuardSphere community</p>

            <form id="signupForm" method="post" action="signup.inc.php" onsubmit="return validateForm()">
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

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                        <span class="live-validation" id="confirmPasswordLiveValidation"></span>
                    </div>
                    <span class="error-message" id="confirmPasswordError"></span>
                </div>

                <button type="submit" class="signin-btn">Create Account</button>
            </form>

            <a href="signin.php" class="signup-link">Already have an account? Sign in</a>
        </div>

        <img src="pics/Women-s-safety-at-workplace.jpg" alt="Diverse Women Illustration">
    </div>


    <footer>
        <div class="footer-content">
            <div class="footer-tagline">
                <h3>GuardSphere</h3>
                <p>Empowering women with safety and security solutions worldwide.</p>
            </div>
            <div class="quick-links">
                <a href="#about">About Us</a>
                <a href="#courses">Safety Courses</a>
                <a href="#products">Products</a>
                <a href="#help">Emergency Help</a>
                <a href="#plans">Subscription Plans</a>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 GuardSphere. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
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

            // Confirm password live validation
            confirmPasswordInput.addEventListener('input', function() {
                const confirmPass = this.value;
                const originalPass = passwordInput.value;
                const isMatch = confirmPass === originalPass;
                const confirmPasswordLiveValidation = document.getElementById('confirmPasswordLiveValidation');
                
                confirmPasswordLiveValidation.textContent = isMatch ? '✅' : '❌';
                this.classList.toggle('error', !isMatch);
            });
        });

        function validateForm() {
            let valid = true;
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            // Reset error messages
            document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
            document.querySelectorAll('input').forEach(el => el.classList.remove('error'));

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
                document.getElementById('passwordError').textContent = 'Password does not meet all requirements';
                passwordInput.classList.add('error');
            }

            // Confirm password validation
            if (password !== confirmPasswordInput.value) {
                valid = false;
                document.getElementById('confirmPasswordError').textContent = 'Passwords do not match';
                confirmPasswordInput.classList.add('error');
            }

            if (valid) {
                const formData = new FormData(document.getElementById('signupForm'));
                
                fetch('signup.inc.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const successMessage = document.createElement('div');
                        successMessage.className = 'success-message';
                        successMessage.textContent = 'Account created successfully! Redirecting...';
                        document.body.appendChild(successMessage);

                        setTimeout(() => {
                            window.location.href = 'signin.php';
                        }, 1500);
                    } else {
                        alert(data.message || 'Registration failed. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }

            return false;
        }
    </script>
</body>
</html>
