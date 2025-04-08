<?php
session_start();

// Simulate advisor session
if (!isset($_SESSION['email'])) {
    $_SESSION['email'] = 'test_advisor@example.com';
    $_SESSION['role_id'] = 2;
    $_SESSION['user_id'] = 1;
}

require_once 'DBS.inc.php';

// Function to check if medical_notes handling is working
function testMedicalNotesForm() {
    global $pdo;
    
    try {
        // Check if we can connect to the database
        $dbStatus = "Connected to database successfully";
        
        // Check if medical_notes table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'medical_notes'");
        $tableExists = $stmt->rowCount() > 0;
        
        // Check if there's at least one booking
        $stmt = $pdo->query("SELECT * FROM bookings LIMIT 1");
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasBookings = count($bookings) > 0;
        
        return [
            'status' => 'success',
            'dbConnection' => true,
            'tableExists' => $tableExists,
            'hasBookings' => $hasBookings,
            'bookingSample' => $hasBookings ? $bookings[0] : null
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

$testResult = testMedicalNotesForm();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Form Test</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .test-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 500;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        h1 {
            margin-bottom: 30px;
            color: #343a40;
        }
        .test-section {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .form-test-button {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1><i class="fas fa-vial mr-2"></i> Medical Form Test</h1>
        
        <div class="test-section">
            <h3>Test Results</h3>
            
            <div class="card mb-3">
                <div class="card-header">
                    <h5>Database Connection</h5>
                </div>
                <div class="card-body">
                    <?php if ($testResult['status'] === 'success'): ?>
                        <span class="status-badge success">
                            <i class="fas fa-check-circle mr-1"></i> Connected
                        </span>
                    <?php else: ?>
                        <span class="status-badge error">
                            <i class="fas fa-times-circle mr-1"></i> Error: <?php echo $testResult['message']; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header">
                    <h5>Medical Notes Table</h5>
                </div>
                <div class="card-body">
                    <?php if ($testResult['tableExists']): ?>
                        <span class="status-badge success">
                            <i class="fas fa-check-circle mr-1"></i> Exists
                        </span>
                    <?php else: ?>
                        <span class="status-badge error">
                            <i class="fas fa-times-circle mr-1"></i> Not Found
                        </span>
                        <p class="mt-2">Please run create_medical_notes_table.php to create the table.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header">
                    <h5>Bookings Data</h5>
                </div>
                <div class="card-body">
                    <?php if ($testResult['hasBookings']): ?>
                        <span class="status-badge success">
                            <i class="fas fa-check-circle mr-1"></i> Found Bookings
                        </span>
                        <div class="mt-3">
                            <h6>Sample Booking:</h6>
                            <pre class="bg-light p-3"><?php print_r($testResult['bookingSample']); ?></pre>
                        </div>
                    <?php else: ?>
                        <span class="status-badge error">
                            <i class="fas fa-times-circle mr-1"></i> No Bookings Found
                        </span>
                        <p class="mt-2">The system needs bookings to test the medical form.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($testResult['status'] === 'success' && $testResult['hasBookings']): ?>
        <div class="test-section">
            <h3>Test Medical Form</h3>
            <p>Click the button below to open the medical form modal with sample data:</p>
            
            <button id="openTestModal" class="btn btn-primary">
                <i class="fas fa-notes-medical mr-2"></i> Open Medical Form
            </button>
            
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle mr-2"></i>
                When you save the form, the data will be stored in the medical_notes table.
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Medical Notes Modal -->
    <div class="modal fade" id="medicalNotesModal" tabindex="-1" role="dialog" aria-labelledby="medicalNotesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="medicalNotesModalLabel">
                        <i class="fas fa-notes-medical mr-2"></i> Medical Notes
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="medicalNotesForm">
                        <input type="hidden" id="modal-booking-id" name="booking_id">
                        
                        <div class="patient-info mb-4 p-3 border rounded bg-light">
                            <h6><i class="fas fa-user-circle mr-2"></i> Patient Information</h6>
                            <p class="mb-1"><strong>Name:</strong> <span id="patient-name"></span></p>
                            <p class="mb-0"><strong>Email:</strong> <span id="patient-email"></span></p>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="symptoms"><i class="fas fa-clipboard-list text-primary mr-2"></i> Symptoms</label>
                                    <textarea class="form-control" id="symptoms" name="symptoms" rows="3" 
                                        placeholder="Describe patient symptoms here..."></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="diagnosis"><i class="fas fa-stethoscope text-primary mr-2"></i> Diagnosis</label>
                                    <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"
                                        placeholder="Enter your diagnosis..."></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="medication"><i class="fas fa-pills text-primary mr-2"></i> Medication/Recommendations</label>
                                    <textarea class="form-control" id="medication" name="medication" rows="3"
                                        placeholder="List medications or recommendations..."></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="further-procedure"><i class="fas fa-clipboard-check text-primary mr-2"></i> Further Procedure</label>
                                    <textarea class="form-control" id="further-procedure" name="further_procedure" rows="3"
                                        placeholder="Describe any follow-up procedures..."></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mt-3">
                            <label><i class="fas fa-tasks text-primary mr-2"></i> Update Status</label>
                            <div class="btn-group-toggle d-flex flex-wrap" data-toggle="buttons">
                                <label class="btn btn-outline-primary mr-2 mb-2">
                                    <input type="radio" name="status" value="confirmed"> <i class="fas fa-check-circle"></i> Confirmed
                                </label>
                                <label class="btn btn-outline-info mr-2 mb-2">
                                    <input type="radio" name="status" value="follow_up"> <i class="fas fa-calendar-plus"></i> Follow Up
                                </label>
                                <label class="btn btn-outline-success mr-2 mb-2">
                                    <input type="radio" name="status" value="complete"> <i class="fas fa-check-double"></i> Complete
                                </label>
                                <label class="btn btn-outline-warning mb-2">
                                    <input type="radio" name="status" value="follow_up_complete"> <i class="fas fa-calendar-check"></i> Follow Up & Complete
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveMedicalNotes">
                        <i class="fas fa-save mr-1"></i> Save Notes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Set up test booking data
            const testBookingId = <?php echo isset($testResult['bookingSample']['booking_id']) ? $testResult['bookingSample']['booking_id'] : 1; ?>;
            const testUserEmail = <?php echo isset($testResult['bookingSample']['user_email']) ? "'".$testResult['bookingSample']['user_email']."'" : "'test_user@example.com'"; ?>;
            const testUserName = 'Test Patient';
            
            // Open test modal
            $('#openTestModal').click(function() {
                // Set form data
                $('#modal-booking-id').val(testBookingId);
                $('#patient-name').text(testUserName);
                $('#patient-email').text(testUserEmail);
                
                // Clear form
                $('#symptoms, #diagnosis, #medication, #further-procedure').val('');
                $('input[name="status"]').prop('checked', false).parent().removeClass('active');
                
                // Fetch existing notes if any
                $.ajax({
                    url: 'advisor_ajax_handler.php',
                    type: 'POST',
                    data: {
                        action: 'get_medical_notes',
                        booking_id: testBookingId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            // Fill form with existing data
                            $('#symptoms').val(response.data.symptoms || '');
                            $('#diagnosis').val(response.data.diagnosis || '');
                            $('#medication').val(response.data.medication || '');
                            $('#further-procedure').val(response.data.further_procedure || '');
                            
                            // Set status radio button
                            if (response.data.status) {
                                $(`input[name="status"][value="${response.data.status}"]`)
                                    .prop('checked', true)
                                    .parent().addClass('active');
                            }
                        }
                        
                        // Show the modal
                        $('#medicalNotesModal').modal('show');
                    },
                    error: function() {
                        // Just show the modal
                        $('#medicalNotesModal').modal('show');
                    }
                });
            });
            
            // Handle saving medical notes
            $('#saveMedicalNotes').click(function() {
                // Show loading state
                const $btn = $(this);
                const originalText = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                $btn.prop('disabled', true);
                
                const formData = $('#medicalNotesForm').serialize();
                
                $.ajax({
                    url: 'advisor_ajax_handler.php',
                    type: 'POST',
                    data: formData + '&action=save_medical_notes',
                    dataType: 'json',
                    success: function(response) {
                        $btn.html(originalText);
                        $btn.prop('disabled', false);
                        
                        if (response.success) {
                            // Show success message
                            const successAlert = `
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle mr-2"></i> Medical notes saved successfully!
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            `;
                            $('.modal-body').prepend(successAlert);
                            
                            // Close modal after a short delay
                            setTimeout(function() {
                                $('#medicalNotesModal').modal('hide');
                                
                                // Show confirmation on the page
                                const pageAlert = `
                                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                                        <i class="fas fa-check-circle mr-2"></i> Medical notes saved successfully!
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                `;
                                $('.test-section:last').append(pageAlert);
                            }, 1500);
                        } else {
                            alert('Error: ' + (response.message || 'Failed to save medical notes'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $btn.html(originalText);
                        $btn.prop('disabled', false);
                        
                        console.error('Error saving medical notes:', error);
                        alert('Error saving medical notes. Please try again.');
                    }
                });
            });
        });
    </script>
</body>
</html>
