<?php
require 'DBS.inc.php';
session_start();

if (!isset($_SESSION['counselor_id'])) {
    header("Location: counselor_login.php"); // Redirect if not logged in
    exit();
}

$counselor_id = $_SESSION['counselor_id'];
$error = "";
$success = "";

// Fetch current counselor details
$stmt = $pdo->prepare("SELECT counselor_name, counselor_phone_number FROM counselors WHERE counselor_id = ?");
$stmt->execute([$counselor_id]);
$counselor = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $counselor_name = trim($_POST['counselor_name']);
    $counselor_phone_number = trim($_POST['counselor_phone_number']);

    if (empty($counselor_name) || empty($counselor_phone_number)) {
        $error = "All fields are required.";
    } else {
        // Update profile details
        $stmt = $pdo->prepare("UPDATE counselors SET counselor_name = ?, counselor_phone_number = ? WHERE counselor_id = ?");
        if ($stmt->execute([$counselor_name, $counselor_phone_number, $counselor_id])) {
            $success = "Profile updated successfully!";
            header("Location: counselor_dashboard.php"); // Redirect to dashboard after update
            exit();
        } else {
            $error = "Failed to update profile. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - Counselor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            margin: 50px;
        }
        .update-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: inline-block;
        }
        input {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        button {
            background-color: #dc3545;
            color: white;
            padding: 10px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            width: 100%;
        }
        button:hover {
            background-color: #c82333;
        }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="update-container">
        <h2>Update Your Profile</h2>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <?php if (!empty($success)) echo "<p class='success'>$success</p>"; ?>
        <form method="POST" action="">
            <input type="text" name="counselor_name" required placeholder="Enter Full Name" value="<?php echo htmlspecialchars($counselor['counselor_name'] ?? ''); ?>">
            <input type="text" name="counselor_phone_number" required placeholder="Enter Phone Number" value="<?php echo htmlspecialchars($counselor['counselor_phone_number'] ?? ''); ?>">
            <button type="submit">Update Profile</button>
        </form>
    </div>
</body>
</html>
