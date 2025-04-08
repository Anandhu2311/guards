<?php
$password = "Admin@123"; // Replace with the actual password
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password Hash: " . $hash;
?>