// Comprehensive fixes for the admin scheduling section

document.addEventListener('DOMContentLoaded', function() {
    // Initial setup
    populateTimeOptions();
    refreshScheduleList();
    
    // Setup event listeners for the modal forms
    document.getElementById('day').addEventListener('change', checkSlotConflicts);
    document.getElementById('startTime').addEventListener('change', function() {
        updateEndTimeOptions();
        checkPastTime();
        checkSlotConflicts();
    });
    document.getElementById('endTime').addEventListener('change', checkSlotConflicts);
});

// === TIME SLOT FUNCTIONS ===

// Function to create time dropdown options
function populateTimeOptions() {
    const startTimeSelect = document.getElementById('startTime');
    const endTimeSelect = document.getElementById('endTime');
    
    // Clear existing options
    startTimeSelect.innerHTML = '<option value="">Select Time</option>';
    endTimeSelect.innerHTML = '<option value="">Select Time</option>';
    
    // Define time range (24-hour format)
    for (let hour = 8; hour < 22; hour++) {
        for (let min = 0; min < 60; min += 30) {
            const hourFormatted = hour.toString().padStart(2, '0');
            const minFormatted = min.toString().padStart(2, '0');
            const timeValue = `${hourFormatted}:${minFormatted}`;
            
            // Add to start time dropdown
            const startOption = document.createElement('option');
            startOption.value = timeValue;
            startOption.textContent = formatTime(timeValue);
            startTimeSelect.appendChild(startOption);
            
            // Add to end time dropdown
            const endOption = document.createElement('option');
            endOption.value = timeValue;
            endOption.textContent = formatTime(timeValue);
            endTimeSelect.appendChild(endOption);
        }
    }
    
    // Add final end time option (10:00 PM)
    const finalOption = document.createElement('option');
    finalOption.value = '22:00';
    finalOption.textContent = formatTime('22:00');
    endTimeSelect.appendChild(finalOption);
}

// Function to format time for display
function formatTime(time24) {
    if (!time24) return '';
    
    const [hours, minutes] = time24.split(':');
    const hour = parseInt(hours, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}

// Function to update end time options based on selected start time
function updateEndTimeOptions() {
    const startTime = document.getElementById('startTime').value;
    const endTimeSelect = document.getElementById('endTime');
    const selectedEndTime = endTimeSelect.value;
    
    if (!startTime) return;
    
    // Save current scroll position
    const scrollPos = endTimeSelect.scrollTop;
    
    // Clear all options
    endTimeSelect.innerHTML = '<option value="">Select End Time</option>';
    
    // Add options that are after the start time
    const options = document.getElementById('startTime').options;
    let startFound = false;
    
    for (let i = 0; i < options.length; i++) {
        if (startFound && options[i].value) {
            const option = document.createElement('option');
            option.value = options[i].value;
            option.textContent = options[i].textContent;
            endTimeSelect.appendChild(option);
        }
        
        if (options[i].value === startTime) {
            startFound = true;
        }
    }
    
    // Add final option
    const finalOption = document.createElement('option');
    finalOption.value = '22:00';
    finalOption.textContent = formatTime('22:00');
    endTimeSelect.appendChild(finalOption);
    
    // Restore previously selected end time if it's still valid
    if (selectedEndTime) {
        for (let i = 0; i < endTimeSelect.options.length; i++) {
            if (endTimeSelect.options[i].value === selectedEndTime) {
                endTimeSelect.value = selectedEndTime;
                break;
            }
        }
    }
    
    // Restore scroll position
    endTimeSelect.scrollTop = scrollPos;
}

// Function to check if selected time has passed for today
function checkPastTime() {
    const day = document.getElementById('day').value;
    const startTime = document.getElementById('startTime').value;
    const warningElement = document.getElementById('pastTimeWarning');
    
    if (!day || !startTime) {
        warningElement.style.display = 'none';
        return;
    }
    
    const today = new Date();
    const dayOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][today.getDay()];
    
    if (day === dayOfWeek) {
        const [hours, minutes] = startTime.split(':');
        const selectedTime = new Date();
        selectedTime.setHours(hours, minutes, 0, 0);
        
        if (selectedTime < today) {
            warningElement.style.display = 'block';
        } else {
            warningElement.style.display = 'none';
        }
    } else {
        warningElement.style.display = 'none';
    }
}

// === SCHEDULE MANAGEMENT FUNCTIONS ===

// Function to show modal for adding a new schedule
function showAddScheduleModal() {
    // Reset the form
    document.getElementById('scheduleForm').reset();
    document.getElementById('scheduleId').value = '';
    document.getElementById('modalTitle').textContent = 'Add New Schedule';
    document.getElementById('modalDescription').textContent = 'Define a standard time slot for the schedule.';
    
    // Clear warnings
    document.getElementById('conflictWarning').style.display = 'none';
    document.getElementById('pastTimeWarning').style.display = 'none';
    
    // Enable the save button
    document.getElementById('saveScheduleBtn').disabled = false;
    
    // Show the modal
    document.getElementById('scheduleModal').style.display = 'block';
    
    // Reset and populate time options
    populateTimeOptions();
}

// Function to load and edit an existing schedule
function editSchedule(id) {
    console.log(`Editing schedule ${id}`);
    
    // Reset the form first
    document.getElementById('scheduleForm').reset();
    
    // Show loading state
    document.getElementById('modalTitle').textContent = 'Edit Schedule';
    document.getElementById('modalDescription').textContent = 'Loading schedule data...';
    document.getElementById('scheduleModal').style.display = 'block';
    
    // Fetch schedule data
    fetch(`get_schedule.php?id=${id}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch schedule data');
            }
            return response.json();
        })
        .then(schedule => {
            console.log('Loaded schedule:', schedule);
            
            // Set form values
            document.getElementById('scheduleId').value = schedule.id;
            document.getElementById('day').value = schedule.day;
            document.getElementById('startTime').value = schedule.start_time;
            
            // Update end time options based on start time
            updateEndTimeOptions();
            
            // Set end time value after options are updated
            document.getElementById('endTime').value = schedule.end_time;
            
            // Set active status
            document.getElementById('isActive').value = schedule.is_active;
            
            // Update modal title and description
            document.getElementById('modalTitle').textContent = 'Edit Schedule';
            document.getElementById('modalDescription').textContent = 'Modify the time slot details.';
            
            // Run validations
            checkPastTime();
            checkSlotConflicts();
        })
        .catch(error => {
            console.error('Error loading schedule:', error);
            alert('Failed to load schedule: ' + error.message);
            closeModal();
        });
}

// Function to check for time slot conflicts
function checkSlotConflicts() {
    const day = document.getElementById('day').value;
    const startTime = document.getElementById('startTime').value;
    const endTime = document.getElementById('endTime').value;
    const scheduleId = document.getElementById('scheduleId').value;
    
    const conflictWarning = document.getElementById('conflictWarning');
    const saveButton = document.getElementById('saveScheduleBtn');
    
    // Basic validation - don't check if we don't have complete data
    if (!day || !startTime || !endTime) {
        conflictWarning.style.display = 'none';
        return;
    }
    
    // Create form data for checking conflicts
    const formData = new FormData();
    formData.append('day', day);
    formData.append('startTime', startTime);
    formData.append('endTime', endTime);
    if (scheduleId) {
        formData.append('excludeId', scheduleId);
    }
    
    // Send request to check conflicts
    fetch('check_slot_conflicts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.hasConflict) {
            conflictWarning.style.display = 'block';
            document.getElementById('conflictMessage').textContent = 
                data.message || 'This time slot conflicts with an existing scheduled slot. Time slots cannot overlap.';
            saveButton.disabled = true;
        } else {
            conflictWarning.style.display = 'none';
            saveButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error checking for conflicts:', error);
        conflictWarning.style.display = 'none';
        saveButton.disabled = false; // Allow saving if the conflict check fails
    });
}

// Function to save a schedule (create new or update existing)
function saveSchedule() {
    const scheduleId = document.getElementById('scheduleId').value;
    const day = document.getElementById('day').value;
    const startTime = document.getElementById('startTime').value;
    const endTime = document.getElementById('endTime').value;
    const isActive = document.getElementById('isActive').value;
    
    // Basic validation
    if (!day || !startTime || !endTime) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Check if end time is after start time
    if (startTime >= endTime) {
        alert('End time must be after start time');
        return;
    }
    
    // Prepare the data
    const formData = new FormData();
    formData.append('day', day);
    formData.append('startTime', startTime);
    formData.append('endTime', endTime);
    formData.append('isActive', isActive);
    
    // Add ID if updating an existing schedule
    if (scheduleId) {
        formData.append('scheduleId', scheduleId);
        formData.append('id', scheduleId);
    }
    
    // Show loading state
    document.getElementById('saveScheduleBtn').textContent = 'Saving...';
    document.getElementById('saveScheduleBtn').disabled = true;
    
    // Determine which endpoint to use
    const endpoint = scheduleId ? 'update_time_slot.php' : 'save_time_slot.php';
    
    // Send the request
    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            closeModal();
            refreshScheduleList();
        } else {
            alert('Error: ' + (data.message || 'Unknown error occurred'));
        }
    })
    .catch(error => {
        console.error('Error saving schedule:', error);
        alert('An error occurred while saving the schedule: ' + error.message);
    })
    .finally(() => {
        // Reset button state
        document.getElementById('saveScheduleBtn').textContent = 'Save Schedule';
        document.getElementById('saveScheduleBtn').disabled = false;
    });
}

// Function to toggle schedule status (active/inactive)
function toggleSchedule(scheduleId, newStatus) {
    // Confirm before toggling
    const action = newStatus === 1 ? 'enable' : 'disable';
    if (!confirm(`Are you sure you want to ${action} this time slot?`)) {
        return;
    }
    
    console.log(`Toggling schedule ${scheduleId} to status ${newStatus}`);
    
    const formData = new FormData();
    formData.append('scheduleId', scheduleId);
    formData.append('isActive', newStatus);
    
    fetch('toggle_schedule_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Toggle successful', data);
            // Refresh the schedule list
            refreshScheduleList();
        } else {
            console.error('Toggle failed', data);
            alert(data.message || 'Failed to update schedule status');
        }
    })
    .catch(error => {
        console.error('Error toggling schedule status:', error);
        alert('An error occurred while updating schedule status');
    });
}

// Function to refresh the schedule list
function refreshScheduleList() {
    const scheduleList = document.getElementById('scheduleList');
    
    // Show loading state
    scheduleList.innerHTML = '<p>Loading schedules...</p>';
    
    fetch('get_schedules.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            scheduleList.innerHTML = '';
            
            console.log('Loaded schedules:', data);
            
            if (!Array.isArray(data) || data.length === 0) {
                scheduleList.innerHTML = '<p>No schedules found. Click "Add Time Slot" to create a new schedule.</p>';
                return;
            }
            
            // Group schedules by day for better organization
            const schedulesByDay = {};
            
            data.forEach(schedule => {
                if (!schedulesByDay[schedule.day]) {
                    schedulesByDay[schedule.day] = [];
                }
                schedulesByDay[schedule.day].push(schedule);
            });
            
            // Sort days in correct order
            const dayOrder = {
                'Monday': 1,
                'Tuesday': 2,
                'Wednesday': 3,
                'Thursday': 4,
                'Friday': 5,
                'Saturday': 6,
                'Sunday': 7
            };
            
            const sortedDays = Object.keys(schedulesByDay).sort((a, b) => {
                return dayOrder[a] - dayOrder[b];
            });
            
            // Create cards for each day's schedules
            sortedDays.forEach(day => {
                const daySection = document.createElement('div');
                daySection.className = 'day-section';
                daySection.innerHTML = `<h3 class="day-header">${day}</h3>`;
                
                // Sort schedules by start time
                schedulesByDay[day].sort((a, b) => {
                    return a.start_time.localeCompare(b.start_time);
                });
                
                schedulesByDay[day].forEach(schedule => {
                    const statusClass = schedule.is_active == 1 ? 'active' : 'inactive';
                    const statusText = schedule.is_active == 1 ? 'Active' : 'Inactive';
                    
                    const scheduleCard = document.createElement('div');
                    scheduleCard.className = `schedule-card ${statusClass}`;
                    scheduleCard.innerHTML = `
                        <h3>${day} - ${formatTime(schedule.start_time)} to ${formatTime(schedule.end_time)}</h3>
                        <p><span class="status-badge ${statusClass}">${statusText}</span></p>
                        
                        <div class="staff-availability" id="staff-availability-${schedule.id}">
                            <p>Loading staff availability...</p>
                        </div>
                        
                        <div class="card-actions">
                            <button class="edit-btn" onclick="editSchedule(${schedule.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            
                            ${schedule.is_active == 1 ? 
                                `<button class="disable-btn" onclick="toggleSchedule(${schedule.id}, 0)">
                                    <i class="fas fa-ban"></i> Disable
                                </button>` : 
                                `<button class="enable-btn" onclick="toggleSchedule(${schedule.id}, 1)">
                                    <i class="fas fa-check"></i> Enable
                                </button>`
                            }
                        </div>
                    `;
                    
                    daySection.appendChild(scheduleCard);
                    
                    // Load staff availability for this schedule
                    setTimeout(() => {
                        displayStaffAvailability(schedule.id);
                    }, 100);
                });
                
                scheduleList.appendChild(daySection);
            });
        })
        .catch(error => {
            console.error('Error fetching schedules:', error);
            scheduleList.innerHTML = 
                `<p class="error-message">Error loading schedules: ${error.message}</p>`;
        });
}

// Function to display staff availability for a schedule
function displayStaffAvailability(scheduleId) {
    console.log(`Loading staff availability for schedule ${scheduleId}`);
    
    const availabilityContainer = document.getElementById(`staff-availability-${scheduleId}`);
    if (!availabilityContainer) {
        console.error(`Container for schedule ${scheduleId} not found`);
        return;
    }
    
    availabilityContainer.innerHTML = '<p>Loading staff availability...</p>';
    
    fetch(`get_staff_availability.php?schedule_id=${scheduleId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log(`Received data for schedule ${scheduleId}:`, data);
            
            if (!Array.isArray(data) || data.length === 0) {
                availabilityContainer.innerHTML = '<p class="no-staff">No staff assigned to this time slot.</p>';
                availabilityContainer.innerHTML += `<button class="manage-staff-btn" onclick="showStaffAvailabilityModal(${scheduleId})">
                    <i class="fas fa-user-plus"></i> Manage Staff
                </button>`;
                return;
            }
            
            // Group staff by user_type
            const staffByType = {
                'counselor': [],
                'supporter': [],
                'advisor': []
            };
            
            data.forEach(staff => {
                const userType = staff.user_type?.toLowerCase() || 'unknown';
                if (staffByType.hasOwnProperty(userType)) {
                    staffByType[userType].push(staff);
                }
            });
            
            let html = '';
            let hasStaff = false;
            
            // Display each type of staff
            for (const [type, staffList] of Object.entries(staffByType)) {
                if (staffList.length > 0) {
                    hasStaff = true;
                    html += `<div class="staff-type">
                        <h4>${type.charAt(0).toUpperCase() + type.slice(1)}s (${staffList.length})</h4>
                        <ul class="staff-list">`;
                    
                    staffList.forEach(staff => {
                        const activeStatus = parseInt(staff.staff_active) === 1 ? 'active' : 'inactive';
                        html += `<li class="staff-item ${activeStatus}">
                            ${staff.staff_name || 'Unknown Staff'} 
                            <span class="staff-status ${activeStatus}">${activeStatus}</span>
                        </li>`;
                    });
                    
                    html += `</ul></div>`;
                }
            }
            
            if (!hasStaff) {
                html = '<p class="no-staff">No staff assigned to this time slot.</p>';
            }
            
            html += `<button class="manage-staff-btn" onclick="showStaffAvailabilityModal(${scheduleId})">
                <i class="fas fa-users-cog"></i> Manage Staff
            </button>`;
            
            availabilityContainer.innerHTML = html;
        })
        .catch(error => {
            console.error(`Error fetching staff availability for schedule ${scheduleId}:`, error);
            availabilityContainer.innerHTML = 
                '<p class="error">Error loading staff data. Please refresh.</p>' +
                `<button class="manage-staff-btn" onclick="showStaffAvailabilityModal(${scheduleId})">
                    <i class="fas fa-users-cog"></i> Manage Staff
                </button>`;
        });
}

// Function to show the staff availability management modal
function showStaffAvailabilityModal(scheduleId) {
    console.log(`Showing staff availability modal for schedule ${scheduleId}`);
    
    // Set the schedule ID in the modal
    document.getElementById('modalScheduleId').value = scheduleId;
    
    // Get schedule details to show in the modal
    fetch(`get_schedule.php?id=${scheduleId}`)
        .then(response => response.json())
        .then(schedule => {
            document.getElementById('scheduleTimeInfo').textContent = 
                `${schedule.day} from ${formatTime(schedule.start_time)} to ${formatTime(schedule.end_time)}`;
            
            // Load all staff with their availability for this schedule
            loadStaffForAvailability(scheduleId);
            
            // Show the modal
            document.getElementById('staffAvailabilityModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error loading schedule details:', error);
            alert('Failed to load schedule details');
        });
}

// Function to load staff for availability management
function loadStaffForAvailability(scheduleId) {
    const staffList = document.getElementById('availableStaffList');
    staffList.innerHTML = '<p>Loading staff members...</p>';
    
    // Get all staff members with their availability status
    fetch(`get_all_staff.php?schedule_id=${scheduleId}`)
        .then(response => response.json())
        .then(data => {
            // Group staff by role
            const staffByRole = {
                'counselor': [],
                'supporter': [],
                'advisor': []
            };
            
            data.forEach(staff => {
                const role = staff.role_name?.toLowerCase() || 'unknown';
                if (staffByRole.hasOwnProperty(role)) {
                    staffByRole[role].push(staff);
                }
            });
            
            // Build the HTML
            let html = '';
            
            for (const [role, staffMembers] of Object.entries(staffByRole)) {
                if (staffMembers.length > 0) {
                    html += `<div class="staff-role-section" data-role="${role}">
                        <h3>${role.charAt(0).toUpperCase() + role.slice(1)}s</h3>
                        <ul class="staff-list">`;
                    
                    staffMembers.forEach(staff => {
                        const isAvailable = parseInt(staff.is_available) === 1;
                        const activeClass = parseInt(staff.is_active) === 1 ? 'staff-active' : 'staff-inactive';
                        
                        html += `<li class="staff-item ${activeClass}">
                            <label class="staff-checkbox-label">
                                <input type="checkbox" class="staff-availability" 
                                    data-staff-id="${staff.id}"
                                    data-role="${role}"
                                    ${isAvailable ? 'checked' : ''}>
                                ${staff.name} ${parseInt(staff.is_active) !== 1 ? '(Inactive)' : ''}
                            </label>
                        </li>`;
                    });
                    
                    html += `</ul></div>`;
                }
            }
            
            if (html === '') {
                html = '<p>No staff members found.</p>';
            }
            
            staffList.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading staff members:', error);
            staffList.innerHTML = '<p class="error">Error loading staff data. Please try again.</p>';
        });
}

// Function to save staff availability changes
function saveStaffAvailability() {
    const scheduleId = document.getElementById('modalScheduleId').value;
    const staffCheckboxes = document.querySelectorAll('#availableStaffList input.staff-availability');
    
    // Collect data from checkboxes
    const staffIds = [];
    const staffTypes = [];
    const isAvailable = [];
    
    staffCheckboxes.forEach(checkbox => {
        staffIds.push(checkbox.dataset.staffId);
        staffTypes.push(checkbox.dataset.role);
        isAvailable.push(checkbox.checked ? 1 : 0);
    });
    
    // Prepare form data
    const formData = new FormData();
    formData.append('scheduleId', scheduleId);
    formData.append('staffIds', JSON.stringify(staffIds));
    formData.append('staffTypes', JSON.stringify(staffTypes));
    formData.append('isAvailable', JSON.stringify(isAvailable));
    
    // Send the request
    fetch('update_staff_availability.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal();
            // Refresh the specific schedule card
            displayStaffAvailability(scheduleId);
        } else {
            alert('Error: ' + (data.message || 'Failed to update staff availability'));
        }
    })
    .catch(error => {
        console.error('Error saving staff availability:', error);
        alert('An error occurred while saving staff availability');
    });
}

// Function to filter staff list by staff type
function filterStaffList() {
    const filterValue = document.getElementById('staffFilter').value;
    const staffSections = document.querySelectorAll('.staff-role-section');
    
    if (filterValue === 'all') {
        // Show all sections
        staffSections.forEach(section => {
            section.style.display = 'block';
        });
    } else {
        // Hide sections that don't match the filter
        staffSections.forEach(section => {
            if (section.dataset.role === filterValue) {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        });
    }
}

// Function to close modals
function closeModal() {
    document.getElementById('scheduleModal').style.display = 'none';
    document.getElementById('staffAvailabilityModal').style.display = 'none';
}

// Close on outside click
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            closeModal();
        }
    });
}; 