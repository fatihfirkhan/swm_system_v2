$(document).ready(function () {
    // Helper function to show toast notification
    function showToast(title, message, type = 'success') {
        const toastEl = document.getElementById('toastNotification');
        const toastTitle = document.getElementById('toastTitle');
        const toastMessage = document.getElementById('toastMessage');
        
        if (!toastEl || !toastTitle || !toastMessage) {
            // Fallback to alert if toast elements don't exist
            alert(title + ': ' + message);
            return;
        }
        
        // Set title and message
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        
        // Set toast style based on type
        toastEl.className = 'toast';
        if (type === 'success') {
            toastEl.classList.add('bg-success', 'text-white');
        } else if (type === 'error') {
            toastEl.classList.add('bg-danger', 'text-white');
        } else {
            toastEl.classList.add('bg-info', 'text-white');
        }
        
        // Show toast
        const toast = new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: 3000
        });
        toast.show();
    }

    // init selects
    $('.select2').select2({ width: '100%' });

    // sidebar toggle and persistence
    function setSidebarCollapsed(collapsed) {
        if (collapsed) {
            document.body.classList.add('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', '1');
        } else {
            document.body.classList.remove('sidebar-collapsed');
            localStorage.removeItem('sidebarCollapsed');
        }
    }
    setSidebarCollapsed(localStorage.getItem('sidebarCollapsed') === '1');

    // use the new hamburger button to toggle the sidebar
    $('#sidebarHamburger').on('click', function () {
        setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
        // redraw select2 if layout changed
        $('.select2').select2({ width: '100%' });
    });

    // Validate main schedule form for truck selection
    $('#scheduleForm').on('submit', function(e) {
        const truckId = $('#truck_id').val();
        if (!truckId) {
            e.preventDefault();
            showToast('Error', 'Please select a truck for this schedule.', 'error');
            return false;
        }
    });

    // calendar controls
    $('#calendar_area, #calendar_month').on('change', fetchCalendarData);

    function fetchCalendarData(showLoading = false) {
        const areaId = $('#calendar_area').val();
        const month = $('#calendar_month').val();

        if (!areaId || !month) {
            $('#calendar').html('<p class="text-muted">Please select area and month to view schedule.</p>');
            return;
        }

        // Show loading indicator if requested
        if (showLoading) {
            $('#calendar').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">Refreshing calendar...</p></div>');
        }

        $.ajax({
            url: '../backend/fetch_calendar_schedule.php',
            method: 'GET',
            data: { area_id: areaId, month: month },
            success: function (response) {
                let data = [];
                try { 
                    data = JSON.parse(response); 
                } catch (e) { 
                    $('#calendar').html('<p class="text-danger">Invalid schedule data.</p>'); 
                    return; 
                }
                renderCalendar(data, month);
            },
            error: function () {
                $('#calendar').html('<p class="text-danger">Failed to load schedule data.</p>');
            }
        });
    }

    function renderCalendar(schedules, month) {
        const parts = month.split('-');
        const daysInMonth = new Date(parts[0], parts[1], 0).getDate();
        let calendarHTML = '<table class="table table-bordered"><thead class="table-light"><tr>';

        const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        weekdays.forEach(day => calendarHTML += `<th>${day}</th>`);
        calendarHTML += '</tr></thead><tbody><tr>';

        const firstDay = new Date(`${month}-01`).getDay();
        for (let i = 0; i < firstDay; i++) calendarHTML += '<td></td>';

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${month}-${String(day).padStart(2, '0')}`;
            const schedule = schedules.find(s => s.collection_date === dateStr);
            const currentDate = new Date(dateStr);
            const isPast = currentDate < today;

            let bgColor = '#ffffff';
            let borderColor = '';
            if (schedule) {
                // More vibrant colors with distinct borders
                if (schedule.collection_type === 'Recycle') {
                    bgColor = '#d4edda'; // Green for recycle
                    borderColor = 'border-left: 4px solid #28a745;';
                } else {
                    bgColor = '#cfe2ff'; // Blue for domestic
                    borderColor = 'border-left: 4px solid #0d6efd;';
                }
            }

            let attrs = ` style="vertical-align: top; background-color: ${bgColor}; padding: 8px;"`;
            let classes = '';
            
            if (schedule) {
                classes = isPast ? 'calendar-day past-date' : 'calendar-day';
                attrs = ` style="vertical-align: top; background-color: ${bgColor}; ${borderColor} cursor: pointer; padding: 8px;" class="${classes}" data-schedule='${JSON.stringify(schedule)}' data-past="${isPast}"`;
            }

            calendarHTML += `<td${attrs} id="cell-${dateStr}">`;
            calendarHTML += `<div class="date-content"><strong style="font-size: 1.1em;">${day}</strong><br>`;
            if (schedule) {
                calendarHTML += `<small class="text-dark fw-bold">${schedule.collection_type}</small>`;
            }
            calendarHTML += `</div></td>`;

            if ((day + firstDay) % 7 === 0) calendarHTML += '</tr><tr>';
        }

        calendarHTML += '</tr></tbody></table>';
        $('#calendar').html(calendarHTML);

        $('.calendar-day').on('click', function () {
            // Prevent clicking on past dates
            if ($(this).data('past') === true || $(this).hasClass('past-date')) {
                alert('Cannot edit schedules for past dates.');
                return;
            }
            
            const schedule = $(this).data('schedule');
            const cellId = $(this).attr('id');
            showStaffInfo(schedule, cellId);
        });
    }

    function showStaffInfo(schedule, cellId) {
        // Remove any existing truck info displays
        $('.truck-info-display').remove();
        
        const truckName = schedule.truck_number || 'No truck assigned';
        const infoHTML = `
            <div class="truck-info-display mt-2 p-2 bg-white border rounded shadow-sm">
                <small><i class="fas fa-truck me-1"></i>${truckName}</small><br>
                <button class="btn btn-sm btn-primary mt-1 edit-schedule-btn" data-schedule-id="${schedule.schedule_id}">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
        `;
        
        $('#' + cellId + ' .date-content').append(infoHTML);
        
        // Attach edit button handler
        $('.edit-schedule-btn').off('click').on('click', function(e) {
            e.stopPropagation();
            openEditModal(schedule);
        });
    }

    function openEditModal(schedule) {
        // Remove truck info display when modal opens
        $('.truck-info-display').remove();
        
        $('#modal_schedule_id').val(schedule.schedule_id || '');
        $('#modal-date-info').text("Schedule for " + schedule.collection_date);
        $('#modal_collection_type').val(schedule.collection_type).trigger('change');
        
        // Display assigned truck
        const truckName = schedule.truck_number || 'No truck assigned';
        $('#modal-truck-name').text(truckName);

        // populate truck select
        const truckId = schedule.truck_id || '';
        
        // Destroy existing select2 if it exists
        if ($('#modal_truck_id').data('select2')) {
            $('#modal_truck_id').select2('destroy');
        }
        
        // Set the value first
        $('#modal_truck_id').val(truckId);
        
        // Re-initialize select2 for modal dropdowns
        $('#modal_truck_id').select2({
            width: '100%',
            dropdownParent: $('#scheduleModal')
        });
        
        // Set value again after select2 is initialized and trigger change
        if (truckId) {
            $('#modal_truck_id').val(truckId).trigger('change');
        }

        const mb = new bootstrap.Modal(document.getElementById('scheduleModal'));
        mb.show();
    }

    // Handle delete button click
    $(document).off('click', '#deleteScheduleBtn').on('click', '#deleteScheduleBtn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const scheduleId = $('#modal_schedule_id').val();
        
        if (!scheduleId) {
            showToast('Error', 'Schedule ID is missing', 'error');
            return false;
        }

        if (!confirm('Are you sure you want to delete this schedule? This action cannot be undone.')) {
            return false;
        }
        
        const $btn = $(this);
        const originalHtml = $btn.html();
        
        // Show loading state
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
        
        console.log('DELETE REQUEST - Schedule ID:', scheduleId);
        
        // Use FormData to send POST request
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('schedule_id', scheduleId);
        
        fetch('../backend/handle_adminschedule.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text()) // Get raw text first
        .then(rawText => {
            console.log('DELETE RAW RESPONSE:', rawText);
            
            // Try to parse as JSON
            try {
                const jsonData = JSON.parse(rawText);
                console.log('DELETE PARSED JSON:', jsonData);
                
                // Close modal on success
                $('#scheduleModal').modal('hide');
                
                if (jsonData.status === 'success') {
                    showToast('Success', jsonData.message || 'Schedule deleted!', 'success');
                    fetchCalendarData(true);
                } else {
                    showToast('Error', jsonData.message || 'Failed to delete schedule', 'error');
                }
            } catch (parseError) {
                console.error('JSON PARSE ERROR:', parseError);
                console.error('RAW TEXT:', rawText);
                
                // ALERT THE RAW TEXT - This will show PHP errors!
                alert('PHP ERROR DETECTED:\n\n' + rawText);
                showToast('Error', 'Server returned invalid JSON. Check the alert for details.', 'error');
            }
        })
        .catch(error => {
            console.error('DELETE FETCH ERROR:', error);
            alert('NETWORK ERROR:\n\n' + error.message);
            showToast('Error', 'Network error: ' + error.message, 'error');
        })
        .finally(() => {
            console.log('DELETE COMPLETE - Resetting button state');
            $btn.prop('disabled', false).html(originalHtml);
        });
        
        return false;
    });

    // Handle update form submit
    $(document).off('submit', '#updateScheduleForm').on('submit', '#updateScheduleForm', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const scheduleId = $('#modal_schedule_id').val();
        const collectionType = $('#modal_collection_type').val();
        const truckId = $('#modal_truck_id').val();

        if (!truckId || truckId === '') {
            showToast('Error', 'Please select a truck for this schedule.', 'error');
            return false;
        }

        if (!scheduleId) {
            showToast('Error', 'Schedule ID is missing.', 'error');
            return false;
        }

        const $submitBtn = $(this).find('button[type="submit"]');
        const originalHtml = $submitBtn.html();
        
        // Show loading state
        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');

        console.log('UPDATE REQUEST - Data:', {
            action: 'update',
            schedule_id: scheduleId,
            collection_type: collectionType,
            truck_id: truckId
        });

        // Use FormData to send POST request
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('schedule_id', scheduleId);
        formData.append('collection_type', collectionType);
        formData.append('truck_id', truckId);
        
        fetch('../backend/handle_adminschedule.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text()) // Get raw text first
        .then(rawText => {
            console.log('UPDATE RAW RESPONSE:', rawText);
            
            // Try to parse as JSON
            try {
                const jsonData = JSON.parse(rawText);
                console.log('UPDATE PARSED JSON:', jsonData);
                
                // Close modal on success
                $('#scheduleModal').modal('hide');
                
                if (jsonData.status === 'success') {
                    showToast('Success', jsonData.message || 'Schedule updated!', 'success');
                    fetchCalendarData(true);
                } else {
                    showToast('Error', jsonData.message || 'Failed to update schedule', 'error');
                }
            } catch (parseError) {
                console.error('JSON PARSE ERROR:', parseError);
                console.error('RAW TEXT:', rawText);
                
                // ALERT THE RAW TEXT - This will show PHP errors!
                alert('PHP ERROR DETECTED:\n\n' + rawText);
                showToast('Error', 'Server returned invalid JSON. Check the alert for details.', 'error');
            }
        })
        .catch(error => {
            console.error('UPDATE FETCH ERROR:', error);
            alert('NETWORK ERROR:\n\n' + error.message);
            showToast('Error', 'Network error: ' + error.message, 'error');
        })
        .finally(() => {
            console.log('UPDATE COMPLETE - Resetting button state');
            $submitBtn.prop('disabled', false).html(originalHtml);
        });
        
        return false;
    });
});