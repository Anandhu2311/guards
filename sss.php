<?php
session_start();
require 'DBS.inc.php'; // Database connection file

// Define the admin credentials
// $admin_email = "admin@gmail.com"; // Change this to your actual admin email
// $admin_password = "Admin@123"; // Change this to your actual admin password (hashed in production)

// // Redirect to login if the user is not logged in
// if (!isset($_SESSION['email']) || $_SESSION['email'] !== $admin_email) {
//     header("Location: signin.php");
//     exit();
// }

// $logout_message = '';
// if (isset($_SESSION['logout_success'])) {
//     $logout_message = $_SESSION['logout_success'];
//     unset($_SESSION['logout_success']); // Clear the message after displaying
// }

// Fetch all users and their emergency info
$sql = "SELECT users.id, users.name, users.email, users.phone_number, 
        GROUP_CONCAT(emergency_contacts.emergency_name SEPARATOR ', ') AS emergency_names,
        GROUP_CONCAT(emergency_contacts.em_number SEPARATOR ', ') AS emergency_numbers,
        GROUP_CONCAT(emergency_contacts.relationship SEPARATOR ', ') AS relationships
        FROM users
        LEFT JOIN emergency_contacts ON users.email = emergency_contacts.email
        GROUP BY users.id";

$result = $pdo->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GuardSphere - Admin Dashboard</title>
    <script src="disable-navigation.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        body {
            background-color: #f4f4f4;
            padding: 20px;
        }
        .user-container {
            background: #fff;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.1);
        }
        .dropdown-content {
            display: none;
            margin-top: 5px;
            padding: 10px;
            background: #e9e9e9;
            border-radius: 5px;
        }
    </style>
    <script>
        function toggleDropdown(id) {
            var content = document.getElementById('dropdown-' + id);
            content.style.display = (content.style.display === 'block') ? 'none' : 'block';
        }
    </script>
</head>
<body>
    <nav>
        <div style="display: inline; margin-right: 15px;"><a href="index.php">Home</a></div>
        <div style="display: inline; margin-right: 15px;"><a href="about.php">About Us</a></div>
        <div style="display: inline; margin-right: 15px;"><a href="services.php">Service</a></div>
        <div style="display: inline; margin-right: 15px;"><a href="location.php">Location</a></div>
        <div style="display: inline; margin-right: 15px;"><a href="evidence.php">Evidence</a></div>
    </nav>
    <h2>Admin Dashboard</h2>
    <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)) { ?>
        <div class="user-container">
            <p><strong>Name:</strong> <?php echo $row['name']; ?></p>
            <p><strong>Email:</strong> <?php echo $row['email']; ?></p>
            <p><strong>Phone Number:</strong> <?php echo $row['phone_number'] ?? 'N/A'; ?></p>
            <button onclick="toggleDropdown(<?php echo $row['id']; ?>)">Show Emergency Info</button>
            <div id="dropdown-<?php echo $row['id']; ?>" class="dropdown-content">
                <p><strong>Emergency Contacts:</strong> <?php echo $row['emergency_names'] ?? 'N/A'; ?></p>
                <p><strong>Emergency Numbers:</strong> <?php echo $row['emergency_numbers'] ?? 'N/A'; ?></p>
                <p><strong>Relationships:</strong> <?php echo $row['relationships'] ?? 'N/A'; ?></p>
            </div>
        </div>
    <?php } ?>
</body>
</html>
