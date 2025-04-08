<?php
require 'DBS.inc.php';
session_start();

if (!isset($_SESSION['adv_id'])) {
    header("Location: advisor_login.php"); // Redirect if not logged in
    exit();
}

$adv_id = $_SESSION['adv_id'];
$error = "";
$success = "";

// Fetch current advisor details
$stmt = $pdo->prepare("SELECT adv_name, adv_phone_number FROM advisors WHERE adv_id = ?");
$stmt->execute([$adv_id]);
$advisor = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $adv_name = trim($_POST['adv_name']);
    $adv_phone_number = trim($_POST['adv_phone_number']);

    if (empty($adv_name) || empty($adv_phone_number)) {
        $error = "All fields are required.";
    } else {
        // Update profile details
        $stmt = $pdo->prepare("UPDATE advisors SET adv_name = ?, adv_phone_number = ? WHERE adv_id = ?");
        if ($stmt->execute([$adv_name, $adv_phone_number, $adv_id])) {
            $success = "Profile updated successfully!";
            header("Location: adv_dashboard.php"); // Redirect to dashboard after update
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
    <title>Update Profile - Advisor</title>
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
            background-color: #007bff;
            color: white;
            padding: 10px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            width: 100%;
        }
        button:hover {
            background-color: #0056b3;
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
            <input type="text" name="adv_name" required placeholder="Enter Full Name" value="<?php echo htmlspecialchars($advisor['adv_name'] ?? ''); ?>">
            <input type="text" name="adv_phone_number" required placeholder="Enter Phone Number" value="<?php echo htmlspecialchars($advisor['adv_phone_number'] ?? ''); ?>">
            <button type="submit">Update Profile</button>
        </form>
    </div>
</body>
</html>
