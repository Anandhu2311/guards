<?php
require 'send_email.php';

// Add a simple test function
if (isset($_GET['test'])) {
    $test_email = 'your-test-email@example.com'; // Replace with your email for testing
    
    $result = sendEmail(
        $test_email,
        'GuardSphere Email Test',
        "This is a test email from GuardSphere.\n\nIf you're reading this, email sending is working correctly!"
    );
    
    if ($result) {
        echo "Test email sent successfully! Check $test_email inbox.";
    } else {
        echo "Failed to send test email. Check server error logs for details.";
    }
    exit;
}

// Display a simple UI for testing
?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Testing</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        button { padding: 10px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Email Functionality Test</h1>
    <p>Click the button below to test email sending:</p>
    <a href="test_email.php?test=1"><button>Send Test Email</button></a>
</body>
</html> 