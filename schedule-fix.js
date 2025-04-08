/**
 * Fixed schedule management script
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Improved schedule fix loaded');
    
    // Wait for the page to fully load
    setTimeout(function() {
        // Function to show schedule section
        function showScheduleSection() {
            document.querySelectorAll('.main-section').forEach(section => {
                section.style.display = 'none';
            });
            
            const scheduleSection = document.getElementById('scheduleManager');
            if (scheduleSection) {
                scheduleSection.style.display = 'block';
            }
        }
        
        // Fix navigation links
        document.querySelectorAll('.sidebar-link').forEach(link => {
            if (link.textContent.includes('Schedule') || 
                link.getAttribute('href') === '#schedule') {
                link.addEventListener('click', e => {
                    e.preventDefault();
                    showScheduleSection();
                });
            }
        });
        
        // Handle URL hash
        if (window.location.hash === '#schedule') {
            showScheduleSection();
        }
        
        // Replace saveTimeSlot with one that uses save_schedule.php directly
        window.saveTimeSlot = function(modalId) {
            console.log('Enhanced saveTimeSlot called for modal:', modalId);
            
            // Get the modal element
            const modal = document.getElementById(modalId);
            if (!modal) {
                console.error('Modal not found:', modalId);
                return;
            }
            
            // Get form values
            const scheduleId = document.getElementById(`${modalId}_id`)?.value || '';
            const day = document.getElementById(`${modalId}_day`)?.value || '';
            const startTime = document.getElementById(`${modalId}_startTime`)?.value || '';
            const endTime = document.getElementById(`${modalId}_endTime`)?.value || '';
            
            // Get isActive value
            let isActive = 1;
            const isActiveElement = document.getElementById(`${modalId}_isActive`);
            if (isActiveElement) {
                if (isActiveElement.type === 'checkbox') {
                    isActive = isActiveElement.checked ? 1 : 0;
                } else {
                    isActive = isActiveElement.value || 1;
                }
            }
            
            // Log all values for debugging
            console.log('Form values:', { scheduleId, day, startTime, endTime, isActive });
            
            // Basic validation
            if (!day) {
                alert('Please select a day');
                return;
            }
            if (!startTime) {
                alert('Please enter a start time');
                return;
            }
            if (!endTime) {
                alert('Please enter an end time');
                return;
            }
            
            // Show saving indicator
            const saveBtn = document.getElementById(`${modalId}_saveBtn`);
            if (saveBtn) {
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                saveBtn.disabled = true;
                
                // Reset button after 10s in case of errors
                setTimeout(() => {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }, 10000);
            }
            
            // Create form data for save_schedule.php
            const formData = new FormData();
            
            // Add action based on whether we're creating or updating
            if (scheduleId) {
                formData.append('action', 'update');
                formData.append('scheduleId', scheduleId);
            } else {
                formData.append('action', 'create');
            }
            
            // Add all required fields
            formData.append('day', day);
            formData.append('startTime', startTime);
            formData.append('endTime', endTime);
            formData.append('isActive', isActive);
            
            // Use fetch to send to save_schedule.php with better error handling
            fetch('save_schedule.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Server returned ' + response.status);
                }
                
                // Try to parse JSON response
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                }
                
                // If not JSON, get text and try to parse it
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        // If not valid JSON, create a simple result object
                        return { success: text.includes('success'), message: text };
                    }
                });
            })
            .then(data => {
                console.log('Server response:', data);
                
                if (data.success) {
                    // Close modal and show success
                    if (modal) {
                        try {
                            // Try using Bootstrap modal close if available
                            const bsModal = bootstrap.Modal.getInstance(modal);
                            if (bsModal) {
                                bsModal.hide();
                            } else {
                                modal.style.display = 'none';
                            }
                        } catch (e) {
                            // Fallback to simple hide
                            modal.style.display = 'none';
                        }
                    }
                    
                    // Show success message and refresh
                    alert('Schedule saved successfully');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                    if (saveBtn) {
                        saveBtn.innerHTML = 'Save';
                        saveBtn.disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Error saving schedule:', error);
                
                // Try a different approach if fetch fails
                alert('Using alternative save method...');
                
                // Use form submission as fallback
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'save_schedule.php';
                form.style.display = 'none';
                
                // Add all form fields
                const fields = {
                    'action': scheduleId ? 'update' : 'create',
                    'day': day,
                    'startTime': startTime,
                    'endTime': endTime,
                    'isActive': isActive
                };
                
                if (scheduleId) {
                    fields['scheduleId'] = scheduleId;
                }
                
                // Add each field to the form
                for (const [key, value] of Object.entries(fields)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
                
                // Add form to document and submit
                document.body.appendChild(form);
                form.submit();
            });
        };
        
        // Keep the existing toggle status functionality
        window.ajaxToggleStatus = function(scheduleId, newStatus) {
            if (!confirm('Are you sure you want to ' + (newStatus == 1 ? 'enable' : 'disable') + ' this time slot?')) {
                return;
            }
            
            console.log('Toggling schedule:', scheduleId, 'to status:', newStatus);
            
            // Create form data for direct submission to save_schedule.php
            const formData = new FormData();
            formData.append('scheduleId', scheduleId);
            formData.append('isActive', newStatus);
            formData.append('action', 'updateStatus');
            formData.append('day', 'Monday'); // Add these to avoid "missing required fields"
            formData.append('startTime', '09:00');
            formData.append('endTime', '17:00');
            
            // Send to save_schedule.php
            fetch('save_schedule.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Server returned ' + response.status);
                }
                return response.text();
            })
            .then(() => {
                // Always reload on success
                window.location.reload();
            })
            .catch(error => {
                console.error('Error toggling status:', error);
                
                // Fallback to direct URL approach
                window.location.href = `admin.php?action=toggle_schedule&id=${scheduleId}&status=${newStatus}`;
            });
        };
        
        // Intercept disable/enable button clicks
        document.querySelectorAll('.disable-btn, .enable-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                
                const onclickAttr = btn.getAttribute('onclick') || '';
                const match = onclickAttr.match(/ajaxToggleStatus\((\d+),\s*(\d+)\)/);
                
                if (match && match.length >= 3) {
                    const scheduleId = match[1];
                    const newStatus = match[2];
                    window.ajaxToggleStatus(scheduleId, newStatus);
                }
                
                return false;
            }, true);
        });
    }, 500);
});
