<?php
require 'DBS.inc.php';
session_start();

if (!isset($_SESSION['adv_id'])) {
    die("Access denied.");
}

$updateError = "";
$updateSuccess = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = trim($_POST['new_password']);

    if (strlen($new_password) < 6) {
        $updateError = "Password must be at least 6 characters.";
    } else {
        // 🔒 Hash the new password before storing it
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password and set password_updated = TRUE
        $stmt = $pdo->prepare("UPDATE advisors SET adv_password = ?, password_updated = 1 WHERE adv_id = ?");
        if ($stmt->execute([$hashed_password, $_SESSION['adv_id']])) {
            $updateSuccess = "Password updated successfully! Please log in again.";
            session_destroy();
            header("Location: advisor_signin.php"); // Redirect to login page
            exit();
        } else {
            $updateError = "Error updating password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - GuardSphere</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            margin: 50px;
        }
        .container {
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
        .error {
            color: red;
        }
        .success {
            color: green;
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>Change Password</h2>
        <?php if (!empty($updateError)) echo "<p class='error'>$updateError</p>"; ?>
        <?php if (!empty($updateSuccess)) echo "<p class='success'>$updateSuccess</p>"; ?>
        <form method="POST">
            <input type="password" name="new_password" required placeholder="Enter New Password">
            <button type="submit">Update Password</button>
        </form>
    </div>

</body>
</html>
