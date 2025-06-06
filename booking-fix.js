/**
 * GuardSphere Booking Display Fix
 * This script fixes the booking display functionality to only show available slots
 * where is_available = 1 in the staff_availability table
 */

// Self-executing function to avoid global namespace pollution
(function() {
    console.log("Booking fix script loaded - will filter for is_available=1 only");
    
    // Function to load available service slots with proper filtering
    function loadAvailableServices(serviceType = '') {
        console.log("Loading available services with type filter:", serviceType);
        
        // Find the container for available services
        const servicesContainer = document.getElementById('availableServices') || 
                                document.querySelector('.services-list') ||
                                document.querySelector('.tab-content.active');
        
        if (!servicesContainer) {
            console.error("Services container not found");
            return;
        }
        
        // Show loading state
        servicesContainer.innerHTML = '<div class="loading-indicator" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading slots with is_available=1 only...</div>';
        
        // Prepare data for request
        const formData = new FormData();
        formData.append('action', 'get_schedules'); // This uses our fixed handler in service_handler.php
        if (serviceType) {
            formData.append('service_type', serviceType);
        }
        
        // Fetch available services using the fixed handler
        fetch('service_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log("Services data received:", data);
            
            // Check if we have any schedules
            if (!data.schedules || data.schedules.length === 0) {
                servicesContainer.innerHTML = '<div class="alert alert-info">No available service slots found. All staff are currently unavailable.</div>';
                return;
            }
            
            // Build HTML for schedules
            let html = '<div class="row">';
            
            // Sort schedules by day and time
            const dayOrder = {
                'Monday': 1,
                'Tuesday': 2,
                'Wednesday': 3,
                'Thursday': 4, 
                'Friday': 5,
                'Saturday': 6,
                'Sunday': 7
            };
            
            data.schedules.sort((a, b) => {
                // Sort by day first
                const dayDiff = dayOrder[a.day] - dayOrder[b.day];
                if (dayDiff !== 0) return dayDiff;
                
                // Then sort by start time
                return a.start_time.localeCompare(b.start_time);
            });
            
            data.schedules.forEach(schedule => {
                // Skip any schedules that might not have is_available=1 (extra safety check)
                if (schedule.is_available !== 1 && schedule.is_available !== '1') {
                    console.log("Skipping unavailable slot:", schedule);
                    return;
                }
                
                // Determine service type and color
                let cardColor = 'bg-primary';
                let serviceType = 'advising';
                
                if (schedule.service_name?.toLowerCase().includes('counseling') || 
                    schedule.role_id === 3 || schedule.role_id === "3") {
                    cardColor = 'bg-purple';
                    serviceType = 'counseling';
                } else if (schedule.service_name?.toLowerCase().includes('support') || 
                        schedule.role_id === 4 || schedule.role_id === "4") {
                    cardColor = 'bg-success';
                    serviceType = 'support';
                }
                
                // Get provider name
                const providerName = schedule.provider_name || schedule.staff_name || 'Staff Member';
                
                html += `
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card h-100">
                        <div class="${cardColor} text-white p-3">
                            <h5 class="card-title">${schedule.day}</h5>
                            <p class="card-text">${schedule.start_time} - ${schedule.end_time}</p>
                        </div>
                        <div class="card-body">
                            <h5>${schedule.service_name || 'Service'}</h5>
                            <p class="mb-1"><strong>Provider:</strong> ${providerName}</p>
                            <p class="mb-3"><small>Online</small></p>
                            <button class="btn btn-primary book-btn w-100" 
                                data-schedule-id="${schedule.id}"
                                data-day="${schedule.day}"
                                data-start-time="${schedule.start_time}"
                                data-provider-email="${schedule.staff_email}"
                                data-service-type="${serviceType}">
                                Book This Slot
                            </button>
                        </div>
                    </div>
                </div>`;
            });
            
            html += '</div>';
            
            // Update the container with the available schedules
            servicesContainer.innerHTML = html;
            
            // Add event listeners to book buttons
            servicesContainer.querySelectorAll('.book-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const scheduleId = this.getAttribute('data-schedule-id');
                    const day = this.getAttribute('data-day');
                    const startTime = this.getAttribute('data-start-time');
                    const providerEmail = this.getAttribute('data-provider-email');
                    const serviceType = this.getAttribute('data-service-type');
                    
                    // Prompt for booking notes
                    const notes = prompt('Please enter any notes for this booking:', '');
                    
                    // If user cancels the prompt, don't proceed
                    if (notes === null) return;
                    
                    // Create booking
                    createBooking(scheduleId, day, startTime, providerEmail, serviceType, notes);
                });
            });
        })
        .catch(error => {
            console.error('Error loading services:', error);
            servicesContainer.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
        });
    }
    
    // Function to create a booking
    function createBooking(scheduleId, day, startTime, providerEmail, serviceType, notes) {
        console.log(`Creating booking for schedule ${scheduleId} with provider ${providerEmail}`);
        
        // Show loading overlay
        const loadingOverlay = document.createElement('div');
        loadingOverlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;';
        loadingOverlay.innerHTML = '<div style="background:white;padding:20px;border-radius:5px;"><i class="fas fa-spinner fa-spin"></i> Processing your booking...</div>';
        document.body.appendChild(loadingOverlay);
        
        // Prepare booking data
        const formData = new FormData();
        formData.append('action', 'book_service');
        formData.append('schedule_id', scheduleId);
        formData.append('provider_email', providerEmail);
        formData.append('service_type', serviceType);
        formData.append('notes', notes);
        
        // Send booking request
        fetch('service_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Remove loading overlay
            document.body.removeChild(loadingOverlay);
            
            if (data.success) {
                alert('Booking successful! ' + data.message);
                // Reload available services to reflect changes
                loadAvailableServices();
                // If there's a My Bookings tab, switch to it
                const myBookingsTab = document.querySelector('.tab-btn[data-tab="my-bookings"]');
                if (myBookingsTab) {
                    myBookingsTab.click();
                }
            } else {
                alert('Booking failed: ' + data.message);
            }
        })
        .catch(error => {
            // Remove loading overlay
            document.body.removeChild(loadingOverlay);
            console.error('Error creating booking:', error);
            alert('Error creating booking: ' + error.message);
        });
    }
    
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM loaded - initializing booking fix");
        
        // Add click handlers to service type tabs if they exist
        const serviceTabs = document.querySelectorAll('.tab-btn[data-service-type]');
        serviceTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                serviceTabs.forEach(t => t.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Load services for the selected type
                const serviceType = this.getAttribute('data-service-type');
                loadAvailableServices(serviceType);
            });
        });
        
        // Load available services for the active tab or all services
        const activeTab = document.querySelector('.tab-btn.active[data-service-type]');
        if (activeTab) {
            const serviceType = activeTab.getAttribute('data-service-type');
            loadAvailableServices(serviceType);
        } else {
            // Just load all services
            loadAvailableServices();
        }
    });
    
    // If Available Services tab is already active, initialize immediately
    if (document.readyState !== 'loading') {
        if (document.querySelector('.tab-btn.active[data-target="available-services"]')) {
            console.log("Available Services tab is already active, initializing immediately");
            loadAvailableServices();
        }
    }
})();
