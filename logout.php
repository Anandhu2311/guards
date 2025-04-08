<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Destroy the session
session_destroy();

// Set a logout success message
session_start();
$_SESSION['logout_success'] = "You have been successfully logged out.";

// Redirect to signin page
header("Location: signin.php");
exit();
?> 