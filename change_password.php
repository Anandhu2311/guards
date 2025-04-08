<?php
session_start();
error_log('Session data in change_password.php: ' . print_r($_SESSION, true)); // Log session data for debugging

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['role_id'])) {
    error_log('Change password session check failed: no email or role_id');
    header("Location: signin.php");
    exit();
}

// Check if this is a first login scenario
$first_login = isset($_GET['first_login']) && $_GET['first_login'] == 1;

// Role-specific variables
$role_id = $_SESSION['role_id'];
$user_id = $_SESSION['user_id'] ?? 0;
$user_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
$user_type = '';

switch ($role_id) {
    case 2:
        $user_type = 'advisor';
        break;
    case 3:
        $user_type = 'counselor';
        break;
    case 4:
        $user_type = 'supporter';
        break;
    default:
        // Redirect non-staff users
        error_log('Change password: Invalid role_id: ' . $role_id);
        header("Location: signin.php");
        exit();
}

error_log("Change password for $user_type (ID: $user_id, Email: $user_email)");

// Process password update if form is submitted
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Database connection
    require 'DBS.inc.php'; // Use the same database connection file as other pages
    
    if (!$pdo) {
        $error_message = "Database connection failed";
        error_log("Change password: Database connection failed");
    } else {
        // Get form data
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate passwords
        if ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
            error_log("Change password: New passwords do not match");
        } else {
            // Verify current password and update to new password
            $table = '';
            $id_field = '';
            $password_field = '';
            $email_field = '';
            $id_value = $user_id;
            
            switch ($role_id) {
                case 2:
                    $table = 'advisors';
                    $id_field = 'adv_id';
                    $password_field = 'adv_password';
                    $email_field = 'adv_email';
                    break;
                case 3:
                    $table = 'counselors';
                    $id_field = 'coun_id';
                    $password_field = 'coun_password';
                    $email_field = 'coun_email';
                    break;
                case 4:
                    $table = 'supporters';
                    $id_field = 'sup_id';
                    $password_field = 'sup_password';
                    $email_field = 'sup_email';
                    break;
            }
            
            error_log("Change password: Table=$table, ID Field=$id_field, Password Field=$password_field");
            
            // First, check if the password column exists in the table
            try {
                $checkStmt = $pdo->prepare("DESCRIBE $table");
                $checkStmt->execute();
                $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                
                error_log("Table columns for $table: " . print_r($columns, true));
                
                // If the expected password field doesn't exist, try 'password' instead
                if (!in_array($password_field, $columns) && in_array('password', $columns)) {
                    $password_field = 'password';
                    error_log("Using alternative password field: password");
                }
                
                // Check current password
                $stmt = $pdo->prepare("SELECT $password_field FROM $table WHERE $id_field = :id");
                $stmt->bindParam(':id', $id_value, PDO::PARAM_INT);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $hashed_password = $user[$password_field];
                    error_log("Retrieved hashed password: " . substr($hashed_password, 0, 10) . "...");
                    
                    if (password_verify($current_password, $hashed_password)) {
                        // Current password is correct, update to new password
                        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        // Prepare the update statement with the correct fields
                        $update_stmt = $pdo->prepare("UPDATE $table SET $password_field = :password, password_updated = 1 WHERE $id_field = :id");
                        $update_stmt->bindParam(':password', $new_hashed_password, PDO::PARAM_STR);
                        $update_stmt->bindParam(':id', $id_value, PDO::PARAM_INT);
                        
                        if ($update_stmt->execute()) {
                            $success_message = "Password updated successfully!";
                            error_log("Password updated successfully for $user_email");
                            
                            // Redirect after 2 seconds
                            header("refresh:2;url=" . getRedirectUrl($role_id));
                        } else {
                            $error_message = "Error updating password: " . implode(", ", $update_stmt->errorInfo());
                            error_log("Error updating password: " . implode(", ", $update_stmt->errorInfo()));
                        }
                    } else {
                        $error_message = "Current password is incorrect.";
                        error_log("Current password verification failed for $user_email");
                    }
                } else {
                    $error_message = "User not found in the system.";
                    error_log("User not found in $table where $id_field = $id_value");
                }
            } catch (PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
                error_log("Database error in change_password: " . $e->getMessage());
            }
        }
    }
}

// Function to get redirect URL based on role
function getRedirectUrl($role_id) {
    switch ($role_id) {
        case 2:
            return "adv_dashboard.php";
        case 3:
            return "coun_dashboard.php";
        case 4:
            return "sup_dashboard.php";
        default:
            return "signin.php";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GuardSphere - Update Password</title>
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

        .main-content {
            background-color: rgb(33, 29, 105);
            min-height: 80vh;
            padding: 4rem 5%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-form {
            background: rgba(255, 105, 180, 0.15);
            backdrop-filter: blur(20px);
            padding: 2.5rem;
            border-radius: 20px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .password-form h2 {
            color: white;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .password-form p {
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
        }

        .form-group input:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
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
        }

        .validation-item:before {
            content: '●';
            margin-right: 8px;
            color: #ff4757;
            font-size: 0.8rem;
        }

        .validation-item.valid {
            color: #2ed573;
        }

        .validation-item.valid:before {
            content: '✓';
            color: #2ed573;
        }

        .update-btn {
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

        .update-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(153, 50, 204, 0.3);
            background: linear-gradient(45deg, #b040ff, #ff1493);
        }

        .update-btn:active {
            transform: translateY(1px);
            box-shadow: 0 2px 10px rgba(153, 50, 204, 0.2);
        }

        .update-btn::before {
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

        .update-btn:hover::before {
            left: 100%;
        }

        .input-wrapper {
            position: relative;
        }

        .live-validation {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
        }

        .error-message {
            color: #ff4757;
            background-color: rgba(255, 71, 87, 0.1);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .success-message {
            color: #2ed573;
            background-color: rgba(46, 213, 115, 0.1);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }

        footer {
            background: linear-gradient(135deg, #211d69 0%, #FF1493 100%);
            padding: 1rem 5%;
            color: white;
            text-align: center;
        }

        .first-login-note {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #ff1493;
        }

        .first-login-note p {
            margin-bottom: 0;
            font-weight: 500;
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
            <text x="150" y="80" font-family="Arial Black, sans-serif" font-weight="900" font-size="60" fill="#fff">GUARDSPHERE</text>
            <text x="150" y="105" font-family="Arial, sans-serif" font-size="20" fill="rgba(255, 255, 255, 0.7)">GUARDED BY GUARDSPHERE.</text>
        </svg>
    </nav>

    <div class="main-content">
        <div class="password-form">
            <h2>Update Your Password</h2>
            
            <?php if ($first_login): ?>
            <div class="first-login-note">
                <p>Welcome to GuardSphere! As this is your first login, please update your temporary password to continue.</p>
            </div>
            <?php else: ?>
            <p>Create a new secure password for your account</p>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form id="passwordForm" method="post" action="" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="current_password" name="current_password" placeholder="Enter your current password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="new_password" name="new_password" placeholder="Enter your new password" required>
                        <span class="live-validation" id="passwordLiveValidation"></span>
                    </div>
                    <ul class="validation-list" id="passwordValidationList">
                        <li class="validation-item" id="lengthCheck">At least 6 characters</li>
                        <li class="validation-item" id="uppercaseCheck">One uppercase letter</li>
                        <li class="validation-item" id="lowercaseCheck">One lowercase letter</li>
                        <li class="validation-item" id="numberCheck">One number</li>
                        <li class="validation-item" id="specialCheck">One special character</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your new password" required>
                        <span class="live-validation" id="confirmPasswordLiveValidation"></span>
                    </div>
                </div>

                <button type="submit" class="update-btn">Update Password</button>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2024 GuardSphere. All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            // Password live validation
            newPasswordInput.addEventListener('input', function() {
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
                const originalPass = newPasswordInput.value;
                const isMatch = confirmPass === originalPass;
                const confirmPasswordLiveValidation = document.getElementById('confirmPasswordLiveValidation');
                
                confirmPasswordLiveValidation.textContent = isMatch ? '✅' : '❌';
                this.classList.toggle('error', !isMatch);
            });
        });

        function validateForm() {
            let valid = true;
            const currentPasswordInput = document.getElementById('current_password');
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            // Password validation
            const password = newPasswordInput.value;
            const passwordValidations = {
                length: password.length >= 6,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };
            
            if (!Object.values(passwordValidations).every(v => v)) {
                valid = false;
                alert('New password does not meet the requirements.');
                newPasswordInput.focus();
            }
            
            // Confirm password validation
            if (newPasswordInput.value !== confirmPasswordInput.value) {
                valid = false;
                alert('New passwords do not match.');
                confirmPasswordInput.focus();
            }
            
            return valid;
        }
    </script>
</body>
</html> 