<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'signin_errors.log');

session_start();
header('Content-Type: application/json');
require 'DBS.inc.php';

// Verify database connection
error_log('Database connection check: ' . ($pdo ? 'Connected' : 'Failed'));

// Enhanced debug logging
error_log('POST data received: ' . print_r($_POST, true));

// Enhanced debugging for database structure
function debugTableStructure($pdo, $tableName) {
    try {
        $stmt = $pdo->prepare("DESCRIBE $tableName");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Table structure for $tableName: " . print_r($columns, true));
    } catch (PDOException $e) {
        error_log("Error getting structure for $tableName: " . $e->getMessage());
    }
}

// Debug all relevant tables
debugTableStructure($pdo, 'supporters');
debugTableStructure($pdo, 'counselors');
debugTableStructure($pdo, 'advisors');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Check if email and password are provided
    if (empty($email) || empty($password)) {
        error_log('Missing email or password');
        echo json_encode([
            'success' => false,
            'message' => 'Email and password are required'
        ]);
        exit();
    }
    
    // Log the email and password for debugging (remove in production)
    error_log("Attempting login with email: $email and password: $password");
    
    try {
        // First check admin table - Check both possible table names
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE admin_email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Checking admin table for $email: " . ($user ? 'Found' : 'Not found'));
            
            if ($user) {
                error_log("User found in admin table. Password in DB: " . substr($user['admin_password'], 0, 10) . "...");
                $passwordValid = password_verify($password, $user['admin_password']);
                error_log("Password verification result: " . ($passwordValid ? 'Valid' : 'Invalid'));
                
                // Only check status if password is valid - this ensures we're checking the right account
                if ($passwordValid) {
                    // Check if admin account is active
                    $isActive = isset($user['is_active']) ? (int)$user['is_active'] : 1;
                    
                    if ($isActive === 0) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Your admin account has been disabled. Please contact the system administrator.'
                        ]);
                        exit();
                    }
                    
                    $_SESSION['email'] = $email;
                    $_SESSION['role_id'] = 1;
                    $_SESSION['user_id'] = $user['id'] ?? 0;
                    
                    echo json_encode([
                        'success' => true,
                        'redirect' => 'admin.php',
                        'role_id' => 1,
                        'message' => 'Admin login successful'
                    ]);
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log("Error checking admin table: " . $e->getMessage());
            // Try alternative table name
            try {
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("Checking admins table for $email: " . ($user ? 'Found' : 'Not found'));
                
                if ($user) {
                    $passwordValid = password_verify($password, $user['password']);
                    
                    if ($passwordValid) {
                        // Only check status if password is valid
                        $isActive = isset($user['is_active']) ? (int)$user['is_active'] : 1;
                        
                        if ($isActive === 0) {
                            echo json_encode([
                                'success' => false,
                                'message' => 'Your admin account has been disabled. Please contact the system administrator.'
                            ]);
                            exit();
                        }
                        
                        $_SESSION['email'] = $email;
                        $_SESSION['role_id'] = 1;
                        $_SESSION['user_id'] = $user['id'];
                        
                        echo json_encode([
                            'success' => true,
                            'redirect' => 'admin.php',
                            'role_id' => 1,
                            'message' => 'Admin login successful'
                        ]);
                        exit();
                    }
                }
            } catch (PDOException $e2) {
                error_log("Error checking admins table: " . $e2->getMessage());
            }
        }
        
        // Check other user tables one by one instead of in sequence
        // First check advisors table
        try {
            $stmt = $pdo->prepare("SELECT * FROM advisors WHERE adv_email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Checking advisors table for $email: " . ($user ? 'Found' : 'Not found'));
            
            if ($user) {
                // Be flexible with password column name
                $passwordField = isset($user['adv_password']) ? 'adv_password' : 'password';
                $hashed_password = $user[$passwordField] ?? '';
                
                // Only check status if password is valid
                if (password_verify($password, $hashed_password)) {
                    // Check if advisor account is active
                    $isActive = isset($user['is_active']) ? (int)$user['is_active'] : 1;
                    
                    if ($isActive === 0) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Your advisor account has been disabled. Please contact administration.'
                        ]);
                        exit();
                    }
                    
                    // Set ALL required session variables consistently
                    $_SESSION['user_id'] = $user['adv_id'];
                    $_SESSION['adv_id'] = $user['adv_id'];
                    $_SESSION['email'] = $user['adv_email'];
                    $_SESSION['user_email'] = $user['adv_email']; 
                    $_SESSION['role_id'] = 2;
                    
                    // Log session data for debugging
                    error_log("Session data for advisor: " . print_r($_SESSION, true));
                    
                    // Check for first login
                    $password_updated = isset($user['password_updated']) ? $user['password_updated'] : 1;
                    
                    if ($password_updated == 0) {
                        echo json_encode([
                            'success' => true,
                            'redirect' => 'change_password.php',
                            'role_id' => 2,
                            'message' => 'First login detected. Please change your password.'
                        ]);
                    } else {
                        echo json_encode([
                            'success' => true,
                            'redirect' => 'adv_dashboard.php',
                            'role_id' => 2,
                            'message' => 'Login successful'
                        ]);
                    }
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log("Database error when checking advisors table: " . $e->getMessage());
        }
        
        // Check counselors table
        try {
            $stmt = $pdo->prepare("SELECT * FROM counselors WHERE coun_email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Checking counselors table for $email: " . ($user ? 'Found' : 'Not found'));
            
            if ($user) {
                // Be flexible with password column name
                $passwordField = isset($user['coun_password']) ? 'coun_password' : 'password';
                $hashed_password = $user[$passwordField] ?? '';
                
                error_log("Password field being used: $passwordField");
                error_log("Hashed password from DB: " . substr($hashed_password, 0, 10) . "...");
                
                if (password_verify($password, $hashed_password)) {
                    // Check if counselor account is active
                    $isActive = isset($user['is_active']) ? (int)$user['is_active'] : 1;
                    
                    if ($isActive === 0) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Your counselor account has been disabled. Please contact administration.'
                        ]);
                        exit();
                    }
                    
                    // Set session variables consistently
                    $_SESSION['user_id'] = $user['coun_id'];
                    $_SESSION['coun_id'] = $user['coun_id'];
                    $_SESSION['email'] = $user['coun_email'];
                    $_SESSION['user_email'] = $user['coun_email'];
                    $_SESSION['role_id'] = 3;
                    $_SESSION['redirect_url'] = 'coun_dashboard.php'; // Extra tracking
                    
                    // Check for first login
                    $password_updated = isset($user['password_updated']) ? (int)$user['password_updated'] : 0;
                    
                    if ($password_updated === 0) {
                        echo json_encode([
                            'success' => true,
                            'redirect' => 'change_password.php',
                            'role_id' => 3,
                            'message' => 'First login detected. Please change your password.'
                        ]);
                    } else {
                        error_log("Login successful for counselor. Session: " . print_r($_SESSION, true));
                        error_log("Sending JSON response: " . json_encode([
                            'success' => true,
                            'redirect' => 'coun_dashboard.php',
                            'role_id' => 3,
                            'message' => 'Login successful'
                        ]));
                        echo json_encode([
                            'success' => true,
                            'redirect' => 'coun_dashboard.php',
                            'role_id' => 3,
                            'message' => 'Login successful'
                        ]);
                        exit();
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Database error when checking counselors table: " . $e->getMessage());
        }
        
        // Check supporters table
        try {
            $stmt = $pdo->prepare("SELECT * FROM supporters WHERE sup_email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Checking supporters table for $email: " . ($user ? 'Found' : 'Not found'));
            
            if ($user) {
                // Check if account is active
                if ($user['is_active'] != 1) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Your account has been disabled. Please contact administration.'
                    ]);
                    exit();
                }

                if (password_verify($password, $user['sup_password'])) {
                    session_regenerate_id(true); // Add security by regenerating session ID
                    
                    // Set ALL required session variables
                    $_SESSION = [
                        'authenticated' => true,
                        'user_id' => $user['sup_id'],
                        'sup_id' => $user['sup_id'],
                        'email' => $user['sup_email'],
                        'user_email' => $user['sup_email'],
                        'role_id' => 4,
                        'role' => 'supporter'
                    ];
                    
                    session_write_close(); // Ensure session is properly saved
                    
                    error_log("Supporter login successful - Session data: " . print_r($_SESSION, true));
                    
                    echo json_encode([
                        'success' => true,
                        'redirect' => 'sup_dashboard.php',
                        'role_id' => 4,
                        'message' => 'Login successful'
                    ]);
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log("Database error when checking supporters table: " . $e->getMessage());
        }
        
        // Finally check normal users table
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Checking users table for $email: " . ($user ? 'Found' : 'Not found'));
            
            if ($user) {
                $passwordValid = password_verify($password, $user['password'] ?? '');
                error_log("Password verification for users: " . ($passwordValid ? 'Valid' : 'Invalid'));
                
                // Only check status if password is valid
                if ($passwordValid) {
                    // Check if user account is active
                    $isActive = isset($user['is_active']) ? (int)$user['is_active'] : 1;
                    
                    if ($isActive === 0) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Your account has been disabled. Please contact administration.'
                        ]);
                        exit();
                    }
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['role_id'] = 5; // Assuming role_id 5 for regular users
                    
                    echo json_encode([
                        'success' => true,
                        'redirect' => 'home.php',
                        'role_id' => 5,
                        'message' => 'Login successful'
                    ]);
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log("Database error when checking users table: " . $e->getMessage());
        }
        
        // If we get here, no valid user was found
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password. Please try again.'
        ]);
        
    } catch (Exception $e) {
        error_log("General error in signin process: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'A system error occurred. Please try again later.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}

function getRedirectURL($role_id) {
    switch ($role_id) {
        case 1:
            return 'admin.php';
        case 2:
            return 'adv_dashboard.php';
        case 3:
            return 'coun_dashboard.php';
        case 4:
            return 'sup_dashboard.php';
        case 5:
            return 'home.php';
        default:
            return 'signin.php';
    }
}
