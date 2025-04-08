<?php
require 'DBS.inc.php'; // Ensure database connection is included

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email']; 
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];

    if (!empty($email) && !empty($latitude) && !empty($longitude)) {
        $location = $latitude . "," . $longitude;
        
        $sql = "UPDATE users SET shared_location = :location WHERE email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':location', $location, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Location updated successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update location"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Invalid parameters"]);
    }
}
?>
