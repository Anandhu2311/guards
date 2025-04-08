<!-- Notification Panel -->
<div id="notificationPanel" class="notification-panel">
    <div class="notification-header">
        <h3>Notifications</h3>
        <?php if ($notificationCount > 0): ?>
            <span class="notification-count"><?php echo $notificationCount; ?></span>
        <?php endif; ?>
    </div>
    <div class="notification-content">
        <?php
        if (isset($_SESSION['email'])) {
            $stmt = $pdo->prepare("
                SELECT * FROM bookings 
                WHERE user_email = ? 
                AND status = 'follow_up'
                ORDER BY booking_date DESC
            ");
            $stmt->execute([$_SESSION['email']]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($notifications)) {
                foreach ($notifications as $notification): ?>
                    <div class="notification-item">
                        <div class="notification-message">
                            Please book a follow-up session within one week
                        </div>
                        <div class="notification-actions">
                            <a href="booking.php" class="btn-book-followup">Book Now</a>
                        </div>
                    </div>
                <?php endforeach;
            } else { ?>
                <div class="notification-item">
                    <div class="notification-message">No new notifications</div>
                </div>
            <?php }
        } ?>
    </div>
</div> 