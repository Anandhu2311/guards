<?php
function getBookings($pdo, $email, $userType = 'client') {
    try {
        if ($userType == 'client') {
            $sql = "SELECT 
                    b.*,
                    s.day, 
                    s.start_time, 
                    s.end_time,
                    u.name as provider_name,
                    b.status,
                    b.notes,
                    b.booking_date,
                    b.service_type
                FROM bookings b
                LEFT JOIN schedules s ON b.schedule_id = s.id
                LEFT JOIN users u ON b.provider_email = u.email
                WHERE b.user_email = :email
                ORDER BY 
                    CASE b.status
                        WHEN 'pending' THEN 1
                        WHEN 'confirmed' THEN 2
                        WHEN 'follow_up' THEN 3
                        WHEN 'completed' THEN 4
                        ELSE 5
                    END,
                    b.booking_date DESC";
        } else {
            $sql = "SELECT 
                    b.*,
                    s.day, 
                    s.start_time, 
                    s.end_time,
                    u.name as client_name,
                    b.status,
                    b.notes,
                    b.booking_date,
                    b.service_type
                FROM bookings b
                LEFT JOIN schedules s ON b.schedule_id = s.id
                LEFT JOIN users u ON b.user_email = u.email
                WHERE b.provider_email = :email
                ORDER BY b.booking_date DESC";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("getBookings found " . count($results) . " bookings for email: $email");
        return $results;
    } catch (PDOException $e) {
        error_log("Error in getBookings: " . $e->getMessage());
        return [];
    }
}
