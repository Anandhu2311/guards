<?php
include 'DBS.inc.php'; // Database connection

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    $sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "exists"; // Email is already in use
    } else {
        echo "available"; // Email is available
    }

    $stmt->close();
    $conn->close();
}
?>
