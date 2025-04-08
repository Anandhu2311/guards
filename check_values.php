<?php
require_once 'DBS.inc.php';

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

try {
    // Check distinct service types
    echo "DISTINCT SERVICE TYPES:\n";
    $stmt = $pdo->query('SELECT DISTINCT service_type FROM bookings');
    $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    print_r($types);
    echo "\n-------------------\n\n";

    // Check provider tables  
    echo "PROVIDER TABLES:\n";
    
    // Check advisors
    echo "Advisors:\n";
    $stmt = $pdo->query('SELECT adv_email, adv_name FROM advisors LIMIT 5');
    $advisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($advisors);
    
    // Check counselors
    echo "\nCounselors:\n";
    $stmt = $pdo->query('SELECT coun_email, coun_name FROM counselors LIMIT 5');
    $counselors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($counselors);
    
    // Check supporters
    echo "\nSupporters:\n";
    $stmt = $pdo->query('SELECT sup_email, sup_name FROM supporters LIMIT 5');
    $supporters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($supporters);
    
    echo "\n-------------------\n\n";
    
    // Sample of bookings with provider info
    echo "SAMPLE BOOKINGS WITH PROVIDER INFO:\n";
    $query = "
        SELECT 
            b.booking_id,
            b.service_type,
            b.provider_email,
            CASE 
                WHEN b.service_type = 'advising' THEN (SELECT adv_name FROM advisors WHERE adv_email = b.provider_email)
                WHEN b.service_type = 'counseling' THEN (SELECT coun_name FROM counselors WHERE coun_email = b.provider_email)
                WHEN b.service_type = 'support' THEN (SELECT sup_name FROM supporters WHERE sup_email = b.provider_email)
                ELSE 'Not found'
            END as provider_name
        FROM bookings b
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($samples);
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}

echo '</pre>';
?> 