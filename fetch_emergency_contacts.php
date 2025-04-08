<?php

require 'DBS.inc.php'; // Ensure this includes proper database connection



if (!isset($_SESSION['email'])) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit;
}

$email = $_SESSION['email'];

try {
    // Prepare the SQL query
    $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE email = :email");
    $stmt->bindParam(':email', $email, PDO::PARAM_INT);
    $stmt->execute();

    // Fetch all emergency contacts for the user
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

   
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
