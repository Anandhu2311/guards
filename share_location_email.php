<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly to avoid breaking JSON response

// Replace the autoloader with direct includes if autoloader isn't working
require_once 'PHPMailer-master/src/Exception.php';
require_once 'PHPMailer-master/src/PHPMailer.php';
require_once 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Function to safely end with a JSON response
function endWithJson($success, $message, $details = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($details !== null) {
        $response['details'] = $details;
    }
    echo json_encode($response);
    exit;
}

// Log errors to file instead of output
function logError($message) {
    error_log("[" . date("Y-m-d H:i:s") . "] Share Location Error: " . $message);
}

// Make sure user is logged in
if (!isset($_SESSION['email'])) {
    logError("User not logged in");
    endWithJson(false, 'User not logged in');
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logError("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    endWithJson(false, 'Invalid request method');
}

// Check if latitude and longitude are provided
if (!isset($_POST['latitude']) || !isset($_POST['longitude'])) {
    $postData = json_encode($_POST);
    logError("Location data missing. POST data: " . $postData);
    endWithJson(false, 'Location data missing');
}

// Log received data for debugging
$latitude = $_POST['latitude'];
$longitude = $_POST['longitude'];
$userEmail = $_SESSION['email'];

logError("Location sharing request - Email: $userEmail, Lat: $latitude, Long: $longitude");

try {
    // Connect to database using PDO
    $dsn = "mysql:host=localhost;dbname=guardsphere;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    try {
        $pdo = new PDO($dsn, "root", "", $options);
    } catch (PDOException $e) {
        logError("Database connection failed: " . $e->getMessage());
        endWithJson(false, 'Database connection failed: ' . $e->getMessage());
    }

    // First, try to get emergency contacts from the emergency_contacts table
    $sql = "SELECT emergency_email FROM emergency_contacts WHERE email = :email";
    logError("Attempting to fetch emergency contacts with query: " . $sql);
    
    // Try to get user ID from email
    $userIdStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $userIdStmt->execute(['email' => $userEmail]);
    $userRow = $userIdStmt->fetch();
    
    $recipients = [];
    if ($userRow) {
        $userId = $userRow['id'];
        logError("Found user ID: " . $userId);
        
        // Query using email parameter instead of user_id
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $userEmail]);
        
        while ($row = $stmt->fetch()) {
            $recipients[] = $row['emergency_email'];
        }
        
        logError("Found " . count($recipients) . " emergency contacts");
    }
    
    // If still no emergency contacts, use the user's own email as fallback
    if (empty($recipients)) {
        logError("No emergency contacts found, using fallback");
        $recipients[] = $userEmail;
    }
    
    logError("Final recipient list: " . implode(", ", $recipients));

    // Create the Google Maps link
    $googleMapsLink = "https://www.google.com/maps?q=$latitude,$longitude";

    // Email configuration
   
    
    $subject = "Emergency Location Share from GuardSphere";
    $emailBody = "
    <html>
    <head>
    <title>Emergency Location Share</title>
    </head>
    <body>
    <h2>Emergency Location Share from GuardSphere</h2>
    <p>User {$userEmail} has shared their location with you:</p>
    <p><a href='{$googleMapsLink}'>Click here to view the location on Google Maps</a></p>
    <p>Location coordinates: {$latitude}, {$longitude}</p>
    <p>This is an automated message from GuardSphere safety application.</p>
    </body>
    </html>
    ";

    // Log the email content (keep this for debugging)
    logError("Sending email to: " . implode(", ", $recipients));
    logError("EMAIL SUBJECT: " . $subject);
    
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = 'html';
        $mail->Host       = 'smtp.gmail.com';    // Updated to Gmail SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'guardsphere01@gmail.com';
        $mail->Password   = 'qvhl kcbg xrph stff';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Add SSL options to prevent certificate verification issues
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            )
        );
        
        // Sender
        $mail->setFrom('anandhulalcv000@gmail.com', 'GuardSphere');
        $mail->addReplyTo('noreply@guardsphere.com', 'GuardSphere');
        
        // Add all recipients
        foreach ($recipients as $recipient) {
            $mail->addAddress($recipient);
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $emailBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $emailBody));
        
        // Send the email
        $mail->send();
        
        logError("Email sent successfully");
        
        // Return success response
        endWithJson(true, 'Location shared successfully', [
            'recipients' => $recipients
        ]);
        
    } catch (Exception $e) {
        logError("Email could not be sent. Mailer Error: " . $mail->ErrorInfo);
        logError("Exception: " . $e->getMessage()); // Log the exception message
        
        // Still save the email content to file as fallback
        $logDir = __DIR__ . '/email_logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/email_' . time() . '.html';
        file_put_contents($logFile, $emailBody);
        
        logError("Email content saved to: " . $logFile);
        
        // Return partial success (we logged the email but couldn't send it)
        endWithJson(false, 'Location shared but email delivery failed. Error: ' . $mail->ErrorInfo, [
            'recipients' => $recipients,
            'emailContent' => 'Saved to ' . basename($logFile)
        ]);
    }

} catch (Exception $e) {
    logError("Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    endWithJson(false, 'Error: ' . $e->getMessage());
}
?>