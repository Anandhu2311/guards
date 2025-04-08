<?php
session_start();
include 'DBS.inc.php'; // Database connection (should use $pdo for PDO)

if ($_SERVER["REQUEST_METHOD"] == "POST"  && isset($_SESSION['email'])) {
    $contact_id = intval($_POST['contact_id']);
    $user_email = $_SESSION['email']; // Get logged-in user's email

    try {
        // Verify if the contact belongs to the logged-in user
        $sql = "SELECT email FROM emergency_contacts WHERE email = :user_email";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":contact_id", $contact_id, PDO::PARAM_INT);
        $stmt->bindParam(":user_email", $user_email, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Contact exists for this user, proceed with deletion
            $delete_sql = "DELETE FROM emergency_contacts WHERE email = :user_email";
            $delete_stmt = $pdo->prepare($delete_sql);
            $delete_stmt->bindParam(":contact_id", $contact_id, PDO::PARAM_INT);
            $delete_stmt->bindParam(":user_email", $user_email, PDO::PARAM_STR);
            
            if ($delete_stmt->execute()) {
                $_SESSION['message'] = "Emergency contact deleted successfully.";
            } else {
                $_SESSION['error'] = "Error deleting contact.";
            }
        } else {
            $_SESSION['error'] = "Unauthorized action: This contact does not belong to you.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }

    // Redirect back to the dashboard
    header("Location: pro_update.php");
    exit();
}
?>
