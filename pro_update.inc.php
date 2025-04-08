<?php
// Include the database connection file
include 'DBS.inc.php';

// Start the session
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_SESSION['email'];
    $name = trim($_POST['name']);
    $phone_number = trim($_POST['phone']);

    // Validate input
    if (empty($name) || empty($phone_number)) {
        echo "Name and Phone Number cannot be empty.";
        exit();
    }

    try {
        // Update the user's profile in the database using PDO
        $query = "UPDATE users SET name = :name, phone_number = :phone_number WHERE email = :email";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':email', $email);

        if ($stmt->execute()) {
            $_SESSION['profile_update_success'] = "Profile updated successfully!";
            header("Location: edit_profile.php");
            exit();
        } else {
            echo "Error updating profile.";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Invalid request.";
    exit();
}
?>