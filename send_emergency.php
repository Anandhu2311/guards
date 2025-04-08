<?php
// EMERGENCY SMS SENDER
// This is a standalone file with ONE job: send emergency SMS messages

// Start session
session_start();

// Logging function for debugging
function emergency_log($message) {
    $logFile = 'emergency_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Phone number formatting function for Twilio
function formatPhoneForTwilio($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Add India country code (+91) if not already present
    // Assuming most numbers are from India, adjust as needed
    if (strlen($phone) == 10) {
        return '+91' . $phone;
    }
    
    // If it's already got a country code (more than 10 digits)
    // Just ensure it has a + prefix
    return '+' . $phone;
}

emergency_log("Emergency script started");

// We'll display results in plain HTML
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Emergency Alert Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px; }
        .alert-success { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
        .btn { background-color: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Emergency Alert Results</h2>
        <?php
        // Check if user is logged in
        $userEmail = $_SESSION['email'] ?? '';
        $userName = $_SESSION['name'] ?? 'User';

        if (empty($userEmail)) {
            emergency_log("Error: User not logged in");
            echo '<div class="alert-danger">You must be logged in to send emergency alerts.</div>';
            echo '<a href="login.php" class="btn">Go to Login</a>';
            exit;
        }

        emergency_log("User: $userEmail ($userName)");

        // Get emergency message
        $message = $_POST['message'] ?? '';
        if (empty($message)) {
            $message = "EMERGENCY ALERT! This person needs immediate assistance.";
        }

        emergency_log("Message: $message");

        // Create full message with user info
        $fullMessage = "EMERGENCY ALERT from $userName: $message";

        try {
            // Database connection
            $host = 'localhost';
            $db   = 'guardsphere';
            $user = 'root';
            $pass = '';
            
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            emergency_log("Connected to database");
            
            // Twilio credentials
            $twilio_sid = "AC0303d1817540e57d2bf2e7ad730764d5";
            $twilio_token = "0aebd7ab8dd1441cfcbd7ffec24cafe2";
            $twilio_number = "+12184605587";
            
            // Try to get credentials from a config file if available
            if (file_exists('twilio_config.php')) {
                include('twilio_config.php');
            }
            
            // Check if emergency_contacts table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'emergency_contacts'");
            if ($stmt->rowCount() == 0) {
                throw new Exception("Emergency contacts table not found in database");
            }
            
            // Get column names from the table
            $columns = [];
            $stmt = $pdo->query("DESCRIBE emergency_contacts");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['Field'];
            }
            
            emergency_log("Table columns: " . implode(", ", $columns));
            
            // Determine which columns to use
            $emailColumn = in_array('user_email', $columns) ? 'user_email' : 'email';
            
            // Get emergency contacts
            $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE $emailColumn = ?");
            $stmt->execute([$userEmail]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            emergency_log("Found " . count($contacts) . " contacts");
            
            if (count($contacts) == 0) {
                echo '<div class="alert-danger">You have no emergency contacts. Please add contacts first.</div>';
                echo '<a href="profile.php" class="btn">Add Contacts</a>';
                exit;
            }
            
            // Send SMS to each contact
            $successCount = 0;
            $failCount = 0;
            $details = [];
            
            foreach ($contacts as $contact) {
                // Find name in contact record
                $contactName = "Contact";
                foreach (['emergency_name', 'name', 'contact_name'] as $nameCol) {
                    if (isset($contact[$nameCol]) && !empty($contact[$nameCol])) {
                        $contactName = $contact[$nameCol];
                        break;
                    }
                }
                
                // Find phone in contact record
                $phoneNumber = null;
                foreach (['em_number', 'phone', 'number', 'phone_number'] as $phoneCol) {
                    if (isset($contact[$phoneCol]) && !empty($contact[$phoneCol])) {
                        $phoneNumber = $contact[$phoneCol];
                        break;
                    }
                }
                
                if (empty($phoneNumber)) {
                    $failCount++;
                    $details[] = "No phone number found for $contactName";
                    continue;
                }
                
                // Format phone number using our improved function
                $formattedPhone = formatPhoneForTwilio($phoneNumber);
                emergency_log("Formatted phone number for $contactName: $phoneNumber → $formattedPhone");
                
                // Send SMS via Twilio API
                $url = "https://api.twilio.com/2010-04-01/Accounts/$twilio_sid/Messages.json";
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'From' => $twilio_number,
                    'To' => $formattedPhone,
                    'Body' => $fullMessage
                ]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, "$twilio_sid:$twilio_token");
                
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                if (curl_errno($ch)) {
                    $error = curl_error($ch);
                    emergency_log("cURL Error: $error");
                    $failCount++;
                    $details[] = "Failed to send to $contactName: $error";
                } else {
                    $response = json_decode($result, true);
                    
                    if ($httpCode >= 200 && $httpCode < 300 && isset($response['sid'])) {
                        $successCount++;
                        $details[] = "Alert sent to $contactName";
                        emergency_log("Success! SID: " . $response['sid']);
                    } else {
                        $error = $response['message'] ?? "HTTP Error: $httpCode";
                        $failCount++;
                        $details[] = "Failed to send to $contactName: $error";
                        
                        // Log more details for debugging
                        emergency_log("Twilio Error: $error");
                        emergency_log("Full response: " . print_r($response, true));
                        emergency_log("Phone number used: $formattedPhone");
                    }
                }
                
                curl_close($ch);
            }
            
            // Display results
            if ($successCount > 0) {
                echo '<div class="alert-success">';
                echo "<h3>Successfully sent $successCount emergency alerts</h3>";
                
                if ($failCount > 0) {
                    echo "<p>Note: $failCount messages failed to send.</p>";
                }
                
                echo '<ul>';
                foreach ($details as $detail) {
                    echo "<li>$detail</li>";
                }
                echo '</ul>';
                echo '</div>';
            } else {
                echo '<div class="alert-danger">';
                echo "<h3>Failed to send any emergency alerts</h3>";
                
                echo '<ul>';
                foreach ($details as $detail) {
                    echo "<li>$detail</li>";
                }
                echo '</ul>';
                
                echo "<p><strong>Important:</strong> Please verify that your contact phone numbers are in valid formats. For Indian numbers, make sure they are 10 digits.</p>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            emergency_log("ERROR: " . $e->getMessage());
            echo '<div class="alert-danger">';
            echo "<h3>Error</h3>";
            echo "<p>" . $e->getMessage() . "</p>";
            echo '</div>';
        }
        ?>
        
        <a href="javascript:history.back()" class="btn">Go Back</a>
    </div>
</body>
</html> 