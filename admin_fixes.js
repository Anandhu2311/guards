// Replace the checkSlotConflicts function with this updated version
function checkSlotConflicts() {
    const day = document.getElementById('day').value;
    const startTime = document.getElementById('startTime').value;
    const endTime = document.getElementById('endTime').value;
    const scheduleId = document.getElementById('scheduleId').value;
    
    const conflictWarning = document.getElementById('conflictWarning');
    const saveButton = document.getElementById('saveScheduleBtn');
    
    // Basic validation - don't check if we don't have complete data
    if (!day || !startTime || !endTime) {
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

// Fix for the toggleSchedule function - ensure it works properly with the UI
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

// Fix for the deleteSchedule function - if required
function deleteSchedule(scheduleId) {
    if (!confirm('Are you sure you want to delete this schedule? This action cannot be undone.')) {
        return;
    }
    
    fetch('delete_time_slot.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: scheduleId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            refreshScheduleList(); // Refresh the schedule list
        } else {
            alert(data.message || 'Failed to delete schedule');
        }
    })
    .catch(error => {
        console.error('Error deleting schedule:', error);
        alert('An error occurred while deleting the schedule');
    });
}

// Updated refreshScheduleList function that properly renders schedule cards
function refreshScheduleList() {
    fetch('get_schedules.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const scheduleList = document.getElementById('scheduleList');
            scheduleList.innerHTML = '';
            
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
            document.getElementById('scheduleList').innerHTML = 
                `<p class="error-message">Error loading schedules: ${error.message}</p>`;
        });
}

// Update the displayStaffAvailability function to fix the loading issue
function displayStaffAvailability(scheduleId) {
    console.log(`Loading staff availability for schedule ${scheduleId}`);
    
    fetch(`get_staff_availability.php?schedule_id=${scheduleId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log(`Received data for schedule ${scheduleId}:`, data);
            const availabilityContainer = document.getElementById(`staff-availability-${scheduleId}`);
            if (!availabilityContainer) {
                console.error(`Container for schedule ${scheduleId} not found`);
                return;
            }
            
            if (!Array.isArray(data) || data.length === 0) {
                availabilityContainer.innerHTML = '<p class="no-staff">No staff assigned to this time slot.</p>';
                availabilityContainer.innerHTML += `<button class="add-staff-btn" onclick="showAddStaffModal(${scheduleId})">
                    <i class="fas fa-user-plus"></i> Assign Staff
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
                const userType = staff.user_type.toLowerCase();
                if (staffByType.hasOwnProperty(userType)) {
                    staffByType[userType].push(staff);
                }
            });
            
            let html = '';
            
            // Display each type of staff
            for (const [type, staffList] of Object.entries(staffByType)) {
                if (staffList.length > 0) {
                    html += `<div class="staff-type">
                        <h4>${type.charAt(0).toUpperCase() + type.slice(1)}s (${staffList.length})</h4>
                        <ul class="staff-list">`;
                    
                    staffList.forEach(staff => {
                        const activeStatus = staff.staff_active == 1 ? 'active' : 'inactive';
                        html += `<li class="staff-item ${activeStatus}">
                            ${staff.staff_name} 
                            <span class="staff-status ${activeStatus}">${activeStatus}</span>
                            <button class="remove-staff-btn" onclick="removeStaff(${scheduleId}, ${staff.staff_id})">
                                <i class="fas fa-times"></i>
                            </button>
                        </li>`;
                    });
                    
                    html += `</ul></div>`;
                }
            }
            
            html += `<button class="add-staff-btn" onclick="showAddStaffModal(${scheduleId})">
                <i class="fas fa-user-plus"></i> Assign Staff
            </button>`;
            
            availabilityContainer.innerHTML = html;
        })
        .catch(error => {
            console.error(`Error fetching staff availability for schedule ${scheduleId}:`, error);
            const availabilityContainer = document.getElementById(`staff-availability-${scheduleId}`);
            if (availabilityContainer) {
                availabilityContainer.innerHTML = '<p class="error">Error loading staff data. Please refresh.</p>';
                availabilityContainer.innerHTML += `<button class="add-staff-btn" onclick="showAddStaffModal(${scheduleId})">
                    <i class="fas fa-user-plus"></i> Assign Staff
                </button>`;
            }
        });
}

// Make sure the formatTime function is available
function formatTime(time24) {
    if (!time24) return '';
    
    const [hours, minutes] = time24.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
} 