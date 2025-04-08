<?php
// Include PHPMailer files from the PHPMailer-master folder
require_once 'PHPMailer-master/src/Exception.php';
require_once 'PHPMailer-master/src/PHPMailer.php';
require_once 'PHPMailer-master/src/SMTP.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Send email using PHPMailer
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body
 * @return bool Whether sending was successful
 */
function sendEmail($to, $subject, $message) {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Log the attempt
        error_log("Attempting to send email to $to with subject: $subject");
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'guardsphere01@gmail.com'; // Use secure environment variables in production
        $mail->Password   = 'qvhl kcbg xrph stff';     // App-specific password for Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Disable SSL certificate verification (note: this is not recommended for production)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom('guardsphere01@gmail.com', 'GuardSphere Admin');
        $mail->addAddress($to);
        $mail->addReplyTo('guardsphere01@gmail.com', 'GuardSphere Admin');
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        // Send the email
        $mail->send();
        error_log("Email successfully sent to $to");
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Helper function to get styled success message
function getEmailSuccessMessage($status_text) {
    return "<div style='background-color: #dcfce7; color: #16a34a; padding: 12px 15px; 
            border-left: 4px solid #16a34a; margin: 10px 0; border-radius: 3px; 
            font-family: Arial, sans-serif; font-size: 14px;'>
            <strong>Success:</strong> Account $status_text successfully and email notification sent!</div>";
}

// Helper function to get styled error message
function getEmailErrorMessage($status_text) {
    return "<div style='background-color: #fee2e2; color: #dc2626; padding: 12px 15px; 
            border-left: 4px solid #dc2626; margin: 10px 0; border-radius: 3px; 
            font-family: Arial, sans-serif; font-size: 14px;'>
            <strong>Error:</strong> Account $status_text successfully but failed to send email notification. Check server logs for details.</div>";
}

// Test function that can be called to verify email configuration
function testEmailConfig() {
    $test_result = sendEmail(
        'test@example.com',  // Change this to your email for testing
        'GuardSphere Email Test',
        "This is a test email from GuardSphere.\n\nIf you're reading this, email sending is working correctly!"
    );
    
    if ($test_result) {
        return getEmailSuccessMessage("test completed");
    } else {
        return getEmailErrorMessage("test completed");
    }
}

// If this file is accessed directly, run a test
if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)) {
    echo '<h1>Email Configuration Test</h1>';
    echo '<p>' . testEmailConfig() . '</p>';
    
    if (isset($_GET['email']) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
        echo '<h2>Sending Test Email To: ' . htmlspecialchars($_GET['email']) . '</h2>';
        $result = sendEmail(
            $_GET['email'],
            'GuardSphere Test Email',
            "This is a test email from GuardSphere.\n\nIf you're reading this, the email sending functionality is working correctly!"
        );
        
        if ($result) {
            echo getEmailSuccessMessage("test email sent to " . htmlspecialchars($_GET['email']));
        } else {
            echo getEmailErrorMessage("test email to " . htmlspecialchars($_GET['email']));
        }
    }
    
    echo '<h2>Send Test Email</h2>';
    echo '<form method="GET">';
    echo '<input type="email" name="email" placeholder="Enter your email" required>';
    echo '<button type="submit">Send Test Email</button>';
    echo '</form>';
} 