<?php
session_start();
require_once 'DBS.inc.php';

// Set up admin privileges for testing
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 2) {
    $_SESSION['role_id'] = 2; // Set advisor role for testing
}

// Function to check database connection
function checkDatabaseConnection() {
    global $pdo;
    try {
        $pdo->query("SELECT 1");
        return ['status' => 'success', 'message' => 'Database connection successful'];
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
    }
}

// Function to check if bookings table exists and has correct structure
function checkBookingsTable() {
    global $pdo;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'bookings'");
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            return ['status' => 'error', 'message' => 'The bookings table does not exist'];
        }
        
        $stmt = $pdo->query("DESCRIBE bookings");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = ['booking_id', 'user_id', 'user_name', 'user_email'];
        $missingColumns = array_diff($requiredColumns, $columns);
        
        if (!empty($missingColumns)) {
            return [
                'status' => 'error', 
                'message' => 'The bookings table is missing required columns: ' . implode(', ', $missingColumns),
                'columns' => $columns
            ];
        }
        
        return ['status' => 'success', 'message' => 'Bookings table exists with correct structure', 'columns' => $columns];
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Error checking bookings table: ' . $e->getMessage()];
    }
}

// Function to count bookings
function countBookings() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
        $count = $stmt->fetchColumn();
        
        return ['status' => 'success', 'count' => $count];
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Error counting bookings: ' . $e->getMessage()];
    }
}

// Function to add a sample booking if none exist
function addSampleBooking() {
    global $pdo;
    try {
        // Check if user_id 1 exists
        $stmt = $pdo->query("SELECT user_id FROM users WHERE user_id = 1 LIMIT 1");
        $userExists = $stmt->rowCount() > 0;
        
        if (!$userExists) {
            return ['status' => 'error', 'message' => 'Cannot add sample booking: User ID 1 does not exist'];
        }
        
        // Add a sample booking
        $stmt = $pdo->prepare("INSERT INTO bookings (user_id, user_name, user_email, booking_date, advisor_id, status, booking_time) 
                              VALUES (1, 'Test Patient', 'test@example.com', CURDATE(), 1, 'pending', '10:00:00')");
        $stmt->execute();
        
        return ['status' => 'success', 'message' => 'Added a sample booking', 'booking_id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => 'Error adding sample booking: ' . $e->getMessage()];
    }
}

// Run tests
$dbConnectionTest = checkDatabaseConnection();
$bookingsTableTest = $dbConnectionTest['status'] === 'success' ? checkBookingsTable() : ['status' => 'skipped'];
$bookingsCountTest = $bookingsTableTest['status'] === 'success' ? countBookings() : ['status' => 'skipped'];

// Handle POST actions
$actionResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_sample') {
        $actionResult = addSampleBooking();
        // Refresh count after adding
        $bookingsCountTest = countBookings();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking System Troubleshooter</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .container { max-width: 800px; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .test-card { margin-bottom: 15px; border-left: 5px solid #eee; padding-left: 15px; }
        .success-card { border-left-color: #28a745; }
        .error-card { border-left-color: #dc3545; }
        .warning-card { border-left-color: #ffc107; }
        .action-section { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4"><i class="fas fa-stethoscope mr-2"></i> Booking System Troubleshooter</h1>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle mr-2"></i> 
            This script helps diagnose and fix issues with the bookings system.
        </div>
        
        <?php if ($actionResult): ?>
        <div class="alert alert-<?php echo $actionResult['status'] === 'success' ? 'success' : 'danger'; ?>">
            <i class="fas fa-<?php echo $actionResult['status'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
            <?php echo $actionResult['message']; ?>
        </div>
        <?php endif; ?>
        
        <h3 class="mt-4 mb-3">Diagnostic Results</h3>
        
        <!-- Database Connection -->
        <div class="test-card <?php echo $dbConnectionTest['status'] === 'success' ? 'success-card' : 'error-card'; ?>">
            <h5>
                <i class="fas fa-database mr-2"></i> Database Connection
                <span class="float-right <?php echo $dbConnectionTest['status'] === 'success' ? 'success' : 'error'; ?>">
                    <i class="fas fa-<?php echo $dbConnectionTest['status'] === 'success' ? 'check-circle' : 'times-circle'; ?>"></i>
                </span>
            </h5>
            <p><?php echo $dbConnectionTest['message']; ?></p>
        </div>
        
        <!-- Bookings Table -->
        <div class="test-card <?php 
            if ($bookingsTableTest['status'] === 'success') echo 'success-card';
            elseif ($bookingsTableTest['status'] === 'error') echo 'error-card';
            else echo 'warning-card';
        ?>">
            <h5>
                <i class="fas fa-table mr-2"></i> Bookings Table
                <span class="float-right <?php 
                    if ($bookingsTableTest['status'] === 'success') echo 'success';
                    elseif ($bookingsTableTest['status'] === 'error') echo 'error';
                    else echo 'text-secondary';
                ?>">
                    <i class="fas fa-<?php 
                        if ($bookingsTableTest['status'] === 'success') echo 'check-circle';
                        elseif ($bookingsTableTest['status'] === 'error') echo 'times-circle';
                        else echo 'question-circle';
                    ?>"></i>
                </span>
            </h5>
            <?php if ($bookingsTableTest['status'] !== 'skipped'): ?>
                <p><?php echo $bookingsTableTest['message']; ?></p>
                <?php if (isset($bookingsTableTest['columns']) && is_array($bookingsTableTest['columns'])): ?>
                    <p><strong>Available columns:</strong> <?php echo implode(', ', $bookingsTableTest['columns']); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-muted">Test skipped due to database connection failure</p>
            <?php endif; ?>
        </div>
        
        <!-- Bookings Count -->
        <div class="test-card <?php 
            if ($bookingsCountTest['status'] === 'success') {
                echo $bookingsCountTest['count'] > 0 ? 'success-card' : 'warning-card';
            } elseif ($bookingsCountTest['status'] === 'error') {
                echo 'error-card';
            } else {
                echo 'warning-card';
            }
        ?>">
            <h5>
                <i class="fas fa-calendar-check mr-2"></i> Bookings Count
                <span class="float-right <?php 
                    if ($bookingsCountTest['status'] === 'success') {
                        echo $bookingsCountTest['count'] > 0 ? 'success' : 'text-warning';
                    } elseif ($bookingsCountTest['status'] === 'error') {
                        echo 'error';
                    } else {
                        echo 'text-secondary';
                    }
                ?>">
                    <i class="fas fa-<?php 
                        if ($bookingsCountTest['status'] === 'success') {
                            echo $bookingsCountTest['count'] > 0 ? 'check-circle' : 'exclamation-circle';
                        } elseif ($bookingsCountTest['status'] === 'error') {
                            echo 'times-circle';
                        } else {
                            echo 'question-circle';
                        }
                    ?>"></i>
                </span>
            </h5>
            <?php if ($bookingsCountTest['status'] === 'success'): ?>
                <p>Found <strong><?php echo $bookingsCountTest['count']; ?></strong> bookings in the database.</p>
                <?php if ($bookingsCountTest['count'] == 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i> No bookings found. This could be why your form isn't working.
                    </div>
                <?php endif; ?>
            <?php elseif ($bookingsCountTest['status'] === 'error'): ?>
                <p class="error"><?php echo $bookingsCountTest['message']; ?></p>
            <?php else: ?>
                <p class="text-muted">Test skipped due to bookings table issues</p>
            <?php endif; ?>
        </div>
        
        <!-- Actions Section -->
        <div class="action-section">
            <h3 class="mb-3">Available Actions</h3>
            
            <?php if ($bookingsCountTest['status'] === 'success' && $bookingsCountTest['count'] == 0): ?>
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-plus-circle mr-2"></i> Add Sample Booking
                    </div>
                    <div class="card-body">
                        <p>You currently have no bookings in the system. This is likely why your medical notes form isn't working.</p>
                        <form method="post">
                            <input type="hidden" name="action" value="add_sample">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus mr-2"></i> Add Sample Booking
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-code mr-2"></i> SQL Create Bookings Table
                </div>
                <div class="card-body">
                    <p>If your bookings table is missing or has incorrect structure, you can run this SQL to create it:</p>
                    <pre class="bg-light p-3">
CREATE TABLE IF NOT EXISTS `bookings` (
  `booking_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `advisor_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;</pre>
                </div>
            </div>
            
            <div class="mt-4">
                <a href="adv_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Return to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 