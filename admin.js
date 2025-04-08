// Add this to your JavaScript in the admin page
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

function formatTime(time24) {
    const [hours, minutes] = time24.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}

function updateEndTimeOptions() {
    const startTime = document.getElementById('startTime').value;
    const endTimeSelect = document.getElementById('endTime');
    
    if (!startTime) {
        return;
    }
    
    // Disable all options before or equal to start time
    const options = endTimeSelect.options;
    for (let i = 0; i < options.length; i++) {
        if (options[i].value <= startTime && options[i].value !== '') {
            options[i].disabled = true;
        } else {
            options[i].disabled = false;
        }
    }
    
    // If the current selected end time is now invalid, select the next available time
    if (endTimeSelect.value <= startTime) {
        for (let i = 0; i < options.length; i++) {
            if (!options[i].disabled && options[i].value !== '') {
                endTimeSelect.value = options[i].value;
                break;
            }
        }
    }
}

function checkPastTime() {
    const pastTimeWarning = document.getElementById('pastTimeWarning');
    const day = document.getElementById('day').value;
    const startTime = document.getElementById('startTime').value;
    
    if (day && startTime) {
        if (hasTimePassed(day, startTime)) {
            pastTimeWarning.style.display = 'block';
        } else {
            pastTimeWarning.style.display = 'none';
        }
    }
}

function hasTimePassed(day, time) {
    const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const currentDayOfWeek = daysOfWeek[new Date().getDay()];
    const currentHour = new Date().getHours();
    const currentMin = new Date().getMinutes();
    const currentTime = `${currentHour.toString().padStart(2, '0')}:${currentMin.toString().padStart(2, '0')}`;
    
    // If we're checking the current day and the time has passed
    if (day === currentDayOfWeek && time < currentTime) {
        return true;
    }
    
    return false;
}

function showAddScheduleModal() {
    document.getElementById('modalTitle').textContent = 'Add New Schedule';
    document.getElementById('modalDescription').textContent = 'Define a new time slot for appointments.';
    document.getElementById('scheduleId').value = '';
    document.getElementById('scheduleForm').reset();
    
    populateTimeOptions();
    
    document.getElementById('conflictWarning').style.display = 'none';
    document.getElementById('scheduleModal').style.display = 'flex';
}

function showEditScheduleModal(scheduleId) {
    document.getElementById('modalTitle').textContent = 'Edit Schedule';
    document.getElementById('modalDescription').textContent = 'Modify the existing time slot.';
    document.getElementById('scheduleId').value = scheduleId;
    
    populateTimeOptions();
    
    // Fetch schedule data and populate form
    fetch(`get_schedule.php?id=${scheduleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            
            document.getElementById('day').value = data.day;
            document.getElementById('startTime').value = data.start_time;
            updateEndTimeOptions();
            document.getElementById('endTime').value = data.end_time;
            document.getElementById('isActive').value = data.is_active;
            
            checkPastTime();
            document.getElementById('scheduleModal').style.display = 'flex';
        })
        .catch(error => console.error('Error fetching schedule:', error));
}

function saveSchedule() {
    const scheduleId = document.getElementById('scheduleId').value;
    const day = document.getElementById('day').value;
    const startTime = document.getElementById('startTime').value;
    const endTime = document.getElementById('endTime').value;
    const isActive = document.getElementById('isActive').value;
    
    // Validate inputs
    if (!day || !startTime || !endTime) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Validate time range
    if (startTime >= endTime) {
        alert('Start time must be before end time');
        return;
    }
    
    // Prepare form data
    const formData = new FormData();
    formData.append('day', day);
    formData.append('startTime', startTime);
    formData.append('endTime', endTime);
    formData.append('isActive', isActive);
    
    if (scheduleId) {
        formData.append('scheduleId', scheduleId);
        formData.append('action', 'update');
    } else {
        formData.append('action', 'add');
    }
    
    // Save the schedule
    fetch('save_schedule.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(scheduleId ? 'Schedule updated successfully' : 'Schedule created successfully');
            closeModal();
            loadSchedules(); // Refresh the schedule list
        } else {
            alert(data.message || 'Failed to save schedule');
        }
    })
    .catch(error => {
        console.error('Error saving schedule:', error);
        alert('An error occurred while saving the schedule');
    });
}

function showStaffAvailabilityModal(scheduleId, day, startTime, endTime) {
    // Set the schedule info
    document.getElementById('scheduleTimeInfo').textContent = 
        `Schedule: ${day} from ${formatTime(startTime)} to ${formatTime(endTime)}`;
    
    // Load staff availability for this schedule
    fetch(`get_staff_availability.php?schedule_id=${scheduleId}`)
        .then(response => response.json())
        .then(data => {
            populateStaffAvailability(scheduleId, data);
            document.getElementById('staffAvailabilityModal').style.display = 'flex';
        })
        .catch(error => console.error('Error fetching staff availability:', error));
}

function populateStaffAvailability(scheduleId, staffList) {
    const staffContainer = document.getElementById('availableStaffList');
    staffContainer.innerHTML = '';
    
    if (staffList.length === 0) {
        staffContainer.innerHTML = '<p>No staff assigned to this schedule yet.</p>';
        return;
    }
    
    // Group by user type
    const staffByType = {
        'counselor': [],
        'supporter': [],
        'advisor': []
    };
    
    staffList.forEach(staff => {
        const userType = staff.role_name.toLowerCase();
        if (staffByType[userType]) {
            staffByType[userType].push(staff);
        }
    });
    
    // Create sections for each user type
    for (const [type, staff] of Object.entries(staffByType)) {
        if (staff.length > 0) {
            const typeHeading = document.createElement('h3');
            typeHeading.textContent = type.charAt(0).toUpperCase() + type.slice(1) + 's';
            typeHeading.className = 'staff-type-heading';
            typeHeading.dataset.type = type;
            staffContainer.appendChild(typeHeading);
            
            const staffList = document.createElement('div');
            staffList.className = 'staff-list';
            staffList.dataset.type = type;
            
            staff.forEach(staffMember => {
                const staffItem = document.createElement('div');
                staffItem.className = 'staff-item';
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = `staff-${staffMember.staff_id}`;
                checkbox.name = 'availableStaff';
                checkbox.value = staffMember.staff_id;
                checkbox.dataset.type = type;
                checkbox.checked = staffMember.is_available === '1';
                
                const label = document.createElement('label');
                label.htmlFor = `staff-${staffMember.staff_id}`;
                label.textContent = `${staffMember.staff_name} (${staffMember.staff_email})`;
                
                if (staffMember.staff_active === '0') {
                    staffItem.classList.add('inactive-staff');
                    label.innerHTML += ' <span class="inactive-badge">Inactive</span>';
                    checkbox.disabled = true;
                }
                
                staffItem.appendChild(checkbox);
                staffItem.appendChild(label);
                staffList.appendChild(staffItem);
            });
            
            staffContainer.appendChild(staffList);
        }
    }
    
    // Store the schedule ID for the save function
    staffContainer.dataset.scheduleId = scheduleId;
}

function filterStaffList() {
    const filterValue = document.getElementById('staffFilter').value;
    const staffTypes = document.querySelectorAll('.staff-type-heading, .staff-list');
    
    if (filterValue === 'all') {
        staffTypes.forEach(item => item.style.display = 'block');
    } else {
        staffTypes.forEach(item => {
            if (item.dataset.type === filterValue) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }
}

function saveStaffAvailability() {
    const staffContainer = document.getElementById('availableStaffList');
    const scheduleId = staffContainer.dataset.scheduleId;
    const checkboxes = staffContainer.querySelectorAll('input[name="availableStaff"]');
    
    const formData = new FormData();
    formData.append('scheduleId', scheduleId);
    
    // Add each staff availability status
    checkboxes.forEach(checkbox => {
        if (!checkbox.disabled) {
            formData.append('staffIds[]', checkbox.value);
            formData.append('staffTypes[]', checkbox.dataset.type);
            formData.append('isAvailable[]', checkbox.checked ? '1' : '0');
        }
    });
    
    fetch('update_staff_availability.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Staff availability updated successfully');
            closeModal();
            loadSchedules(); // Refresh the schedule list
        } else {
            alert(data.message || 'Failed to update staff availability');
        }
    })
    .catch(error => {
        console.error('Error updating staff availability:', error);
        alert('An error occurred while updating staff availability');
    });
}

function loadSchedules() {
    fetch('get_schedules.php')
        .then(response => response.json())
        .then(data => {
            displaySchedules(data);
        })
        .catch(error => console.error('Error loading schedules:', error));
}

function displaySchedules(schedules) {
    const scheduleList = document.getElementById('scheduleList');
    scheduleList.innerHTML = '';
    
    if (schedules.length === 0) {
        scheduleList.innerHTML = '<p>No schedules found. Create a new schedule to get started.</p>';
        return;
    }
    
    schedules.forEach(schedule => {
        const card = document.createElement('div');
        card.className = `schedule-card ${schedule.is_active === '1' ? 'active' : 'inactive'}`;
        
        let staffInfo = '';
        if (schedule.staff_count) {
            staffInfo = `<p><strong>Available Staff:</strong> ${schedule.staff_count}</p>`;
        }
        
        card.innerHTML = `
            <h3>${schedule.day}</h3>
            <p><strong>Time:</strong> ${formatTime(schedule.start_time)} - ${formatTime(schedule.end_time)}</p>
            <p><strong>Status:</strong> <span class="status-badge ${schedule.is_active === '1' ? 'active' : 'inactive'}">
                ${schedule.is_active === '1' ? 'Active' : 'Inactive'}
            </span></p>
            ${staffInfo}
            <div class="card-actions">
                <button class="edit-btn" onclick="showEditScheduleModal(${schedule.id})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="manage-staff-btn" onclick="showStaffAvailabilityModal(${schedule.id}, '${schedule.day}', '${schedule.start_time}', '${schedule.end_time}')">
                    <i class="fas fa-users"></i> Staff
                </button>
                <button class="${schedule.is_active === '1' ? 'disable-btn' : 'enable-btn'}" 
                    onclick="toggleScheduleStatus(${schedule.id}, ${schedule.is_active === '1' ? 0 : 1})">
                    <i class="fas fa-${schedule.is_active === '1' ? 'ban' : 'check'}"></i> 
                    ${schedule.is_active === '1' ? 'Disable' : 'Enable'}
                </button>
                <button class="delete-btn" onclick="deleteSchedule(${schedule.id})">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        `;
        
        scheduleList.appendChild(card);
    });
}

function toggleScheduleStatus(scheduleId, status) {
    if (!confirm(`Are you sure you want to ${status === 1 ? 'enable' : 'disable'} this schedule?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('scheduleId', scheduleId);
    formData.append('status', status);
    
    fetch('toggle_schedule_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadSchedules(); // Refresh the schedule list
        } else {
            alert(data.message || 'Failed to update schedule status');
        }
    })
    .catch(error => {
        console.error('Error toggling schedule status:', error);
        alert('An error occurred while updating schedule status');
    });
}

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
            loadSchedules(); // Refresh the schedule list
        } else {
            alert(data.message || 'Failed to delete schedule');
        }
    })
    .catch(error => {
        console.error('Error deleting schedule:', error);
        alert('An error occurred while deleting the schedule');
    });
}

function closeModal() {
    document.getElementById('scheduleModal').style.display = 'none';
    document.getElementById('staffAvailabilityModal').style.display = 'none';
}

// Initialize the admin panel
document.addEventListener('DOMContentLoaded', function() {
    // Load schedules on page load
    loadSchedules();
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                closeModal();
            }
        });
    }
}); 