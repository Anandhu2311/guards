// Immediate-executing function to fix the Manage Schedule button
(function() {
    console.log("⚡ Admin Button Fix Loaded ⚡");
    
    // Fix function that will be called immediately and also on DOM ready
    function fixScheduleButton() {
        console.log("📋 Attempting to fix Manage Schedule button...");
        
        // Try all possible ways to find the button
        const possibleSelectors = [
            '.sidebar-link[href="#schedule"]',
            '.sidebar a[href="#schedule"]',
            'a[href="#schedule"]',
            '.sidebar-menu a:contains("Schedule")',
            '.sidebar a:contains("Manage Schedule")'
        ];
        
        let scheduleBtn = null;
        
        // Try each selector
        for (const selector of possibleSelectors) {
            const elements = document.querySelectorAll(selector);
            if (elements.length > 0) {
                scheduleBtn = elements[0];
                console.log(`Found button using selector: ${selector}`);
                break;
            }
        }
        
        // If selectors failed, try finding by text content
        if (!scheduleBtn) {
            const allLinks = document.querySelectorAll('a');
            for (const link of allLinks) {
                if (link.textContent.includes('Schedule') || 
                    link.textContent.includes('schedule')) {
                    scheduleBtn = link;
                    console.log(`Found button by text content: ${link.textContent.trim()}`);
                    break;
                }
            }
        }
        
        if (scheduleBtn) {
            // Highlight the button to confirm we found it
            scheduleBtn.style.position = 'relative';
            scheduleBtn.style.zIndex = '9999';
            scheduleBtn.style.border = '2px solid green';
            scheduleBtn.style.background = '#e8f7e8';
            
            // Force the href attribute to ensure it points to schedule
            scheduleBtn.setAttribute('href', '#schedule');
            
            // Create a new button that completely replaces the old one
            const newBtn = document.createElement('a');
            newBtn.innerHTML = scheduleBtn.innerHTML;
            newBtn.className = scheduleBtn.className;
            newBtn.href = '#schedule';
            newBtn.style.position = 'relative';
            newBtn.style.zIndex = '9999';
            newBtn.style.border = '2px solid green';
            newBtn.style.background = '#e8f7e8';
            
            // Add click handler before inserting into DOM
            newBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log("🔔 Schedule button clicked!");
                
                // Try to call the showScheduleManager function
                if (typeof showScheduleManager === 'function') {
                    showScheduleManager();
                } else {
                    // If the function doesn't exist, try direct DOM manipulation
                    hideAllSections();
                    showScheduleSection();
                }
                
                return false;
            }, true);
            
            // Replace the original button
            scheduleBtn.parentNode.replaceChild(newBtn, scheduleBtn);
            console.log("✅ Successfully replaced schedule button!");
            
            // Also add a direct way to trigger showing the schedule manager
            addEmergencyButton();
        } else {
            console.error("❌ Could not find the Manage Schedule button!");
            // Add an emergency button that will force show the schedule section
            addEmergencyButton();
        }
    }
    
    // Helper function to hide all sections
    function hideAllSections() {
        const sections = document.querySelectorAll('.main-section, section, .content-section');
        sections.forEach(section => {
            section.style.display = 'none';
        });
    }
    
    // Helper function to show just the schedule section
    function showScheduleSection() {
        // Try different possible IDs/classes for the schedule section
        const possibleSelectors = [
            '#scheduleManager', 
            '#schedule-section',
            '#scheduleSection',
            '.schedule-section',
            'section[data-section="schedule"]'
        ];
        
        let found = false;
        for (const selector of possibleSelectors) {
            const section = document.querySelector(selector);
            if (section) {
                section.style.display = 'block';
                console.log(`Showed schedule section with selector: ${selector}`);
                found = true;
                break;
            }
        }
        
        if (!found) {
            // If we can't find it by ID/class, look for headings that might indicate the right section
            const headings = document.querySelectorAll('h1, h2, h3, h4');
            for (const heading of headings) {
                if (heading.textContent.includes('Schedule') || 
                    heading.textContent.includes('schedule')) {
                    let section = heading.closest('section') || heading.closest('div');
                    if (section) {
                        section.style.display = 'block';
                        console.log(`Showed schedule section containing heading: ${heading.textContent}`);
                        found = true;
                        break;
                    }
                }
            }
        }
        
        if (!found) {
            alert("Could not find the schedule section! Please contact the developer.");
        }
    }
    
    // Add an emergency button fixed at the bottom of the screen
    function addEmergencyButton() {
        const emergencyBtn = document.createElement('button');
        emergencyBtn.textContent = "SHOW SCHEDULE MANAGER";
        emergencyBtn.style.position = 'fixed';
        emergencyBtn.style.bottom = '20px';
        emergencyBtn.style.right = '20px';
        emergencyBtn.style.zIndex = '10000';
        emergencyBtn.style.padding = '15px';
        emergencyBtn.style.background = '#ff6b6b';
        emergencyBtn.style.color = 'white';
        emergencyBtn.style.border = 'none';
        emergencyBtn.style.borderRadius = '5px';
        emergencyBtn.style.fontWeight = 'bold';
        emergencyBtn.style.cursor = 'pointer';
        emergencyBtn.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
        
        emergencyBtn.addEventListener('click', function() {
            if (typeof showScheduleManager === 'function') {
                showScheduleManager();
            } else {
                hideAllSections();
                showScheduleSection();
            }
        });
        
        document.body.appendChild(emergencyBtn);
        console.log("Added emergency schedule button");
    }
    
    // Try fixing immediately
    fixScheduleButton();
    
    // Also fix when DOM is fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fixScheduleButton);
    }
    
    // And one more time after a delay to be absolutely sure
    setTimeout(fixScheduleButton, 1000);
})(); 