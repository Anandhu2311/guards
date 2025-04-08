<?php
session_start();
require 'DBS.inc.php';

// Verify user is logged in and is a supervisor
if (!isset($_SESSION['email']) || $_SESSION['role_id'] != 3) {
    echo '<p class="error-message">Not authorized. Please sign in as a supervisor.</p>';
    exit();
}

// Get supervisor email from session
$supervisor_email = $_SESSION['email'];

// Function to get bookings for the current supervisor
function getSupervisorBookings($conn, $supervisorEmail) {
    // Get bookings where this supervisor is assigned or pending supervision bookings
    $sql = "SELECT * FROM bookings 
            WHERE (provider_email = :email AND provider_role_id = 3 AND status IN ('confirmed', 'completed'))
               OR (service_type = 'supervision' AND status = 'pending')
            ORDER BY booking_date, booking_time";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $supervisorEmail, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get supervisor bookings
$supervisorBookings = getSupervisorBookings($pdo, $supervisor_email);

// Return HTML for the bookings table
if (empty($supervisorBookings)) {
    echo '<p>You have no bookings to manage.</p>';
} else {
    ?>
    <table class="bookings-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Student</th>
                <th>Service</th>
                <th>Notes</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($supervisorBookings as $booking): ?>
                <tr data-id="<?php echo $booking['booking_id']; ?>" class="<?php echo $booking['status']; ?>-row">
                    <td><?php echo $booking['booking_date']; ?></td>
                    <td><?php echo $booking['booking_time']; ?></td>
                    <td><?php echo $booking['user_email']; ?></td>
                    <td><?php echo ucfirst($booking['service_type']); ?></td>
                    <td><?php echo htmlspecialchars($booking['notes'] ?? 'No notes'); ?></td>
                    <td class="status-cell"><?php echo ucfirst($booking['status']); ?></td>
                    <td class="actions-cell">
                        <?php if ($booking['status'] === 'pending'): ?>
                            <button class="action-btn accept-btn" onclick="handleBooking(<?php echo $booking['booking_id']; ?>, 'accept')">Accept</button>
                            <button class="action-btn reject-btn" onclick="handleBooking(<?php echo $booking['booking_id']; ?>, 'reject')">Reject</button>
                        <?php elseif ($booking['status'] === 'confirmed'): ?>
                            <button class="action-btn complete-btn" onclick="handleBooking(<?php echo $booking['booking_id']; ?>, 'complete')">Complete</button>
                        <?php else: ?>
                            <span class="no-actions">No actions</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
?> 