// Technician Dashboard JavaScript
$(document).ready(function() {
    // Initialize technician dashboard
    initializeTechnicianDashboard();
    
    // Form submissions
    $('#updateTaskForm').submit(handleUpdateTask);
    
    // Real-time updates
    initializeRealTimeUpdates();
    
    // Auto-refresh dashboard data
    setInterval(refreshDashboardData, 30000); // Refresh every 30 seconds
});

function initializeTechnicianDashboard() {
    // Animate statistics cards on load
    $('.dashboard-card').each(function(index) {
        $(this).css('opacity', '0').delay(index * 100).animate({
            opacity: 1
        }, 500);
    });
    
    // Add hover effects to cards
    $('.dashboard-card').hover(
        function() {
            $(this).addClass('shadow-lg');
        },
        function() {
            $(this).removeClass('shadow-lg');
        }
    );
}

function viewTask(taskId) {
    $.ajax({
        url: '../api/technician/get-task.php',
        method: 'GET',
        data: { id: taskId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayTaskDetails(response.data);
                $('#viewTaskModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading task details.', 'danger');
        }
    });
}

function displayTaskDetails(task) {
    const detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Task Information</h6>
                <p><strong>Ticket Number:</strong> ${task.ticket_number}</p>
                <p><strong>Service Type:</strong> <span class="badge bg-info">${task.service_type}</span></p>
                <p><strong>Priority:</strong> <span class="badge bg-${getPriorityBadge(task.priority)}">${task.priority}</span></p>
                <p><strong>Status:</strong> <span class="badge bg-${getStatusBadge(task.status)}">${task.status}</span></p>
                <p><strong>Problem Description:</strong></p>
                <div class="bg-light p-3 rounded">${task.problem_description || 'No description provided'}</div>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Customer Information</h6>
                <p><strong>Customer:</strong> ${task.company_name || task.contact_person}</p>
                <p><strong>Contact Person:</strong> ${task.contact_person}</p>
                <p><strong>Phone:</strong> <a href="tel:${task.customer_phone}">${task.customer_phone}</a></p>
                <p><strong>Address:</strong> ${task.address || 'Not provided'}</p>
                <p><strong>Preferred Date:</strong> ${task.preferred_date || 'Not specified'}</p>
                <p><strong>Preferred Time:</strong> ${task.preferred_time || 'Not specified'}</p>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Timing Information</h6>
                <p><strong>Created:</strong> ${formatDateTime(task.created_at)}</p>
                <p><strong>Last Updated:</strong> ${formatDateTime(task.updated_at)}</p>
                ${task.actual_start_time ? `<p><strong>Started:</strong> ${formatDateTime(task.actual_start_time)}</p>` : ''}
                ${task.actual_end_time ? `<p><strong>Completed:</strong> ${formatDateTime(task.actual_end_time)}</p>` : ''}
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Customer Feedback</h6>
                ${task.customer_rating ? `
                    <p><strong>Rating:</strong> ${getStarRating(task.customer_rating)}</p>
                    <p><strong>Feedback:</strong> ${task.customer_feedback || 'No feedback provided'}</p>
                ` : '<p class="text-muted">No feedback yet</p>'}
            </div>
        </div>
        ${task.location_lat && task.location_lng ? `
        <hr>
        <div class="row">
            <div class="col-12">
                <h6 class="text-muted">Location</h6>
                <p><strong>Coordinates:</strong> ${task.location_lat}, ${task.location_lng}</p>
                <button class="btn btn-sm btn-outline-primary" onclick="openLocation(${task.location_lat}, ${task.location_lng})">
                    <i class="fas fa-map-marker-alt me-1"></i>Open in Maps
                </button>
            </div>
        </div>
        ` : ''}
    `;
    
    $('#taskDetails').html(detailsHtml);
}

function startTask(taskId) {
    if (confirm('Start this task? This will update the status to "In Progress".')) {
        updateTaskStatus(taskId, 'in_progress', 'Task started');
    }
}

function updateTask(taskId) {
    $('#update_task_id').val(taskId);
    $('#updateTaskModal').modal('show');
}

function handleUpdateTask(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const taskId = formData.get('task_id');
    const status = formData.get('status');
    const notes = formData.get('notes');
    
    updateTaskStatus(taskId, status, notes);
    $('#updateTaskModal').modal('hide');
}

function updateTaskStatus(taskId, status, notes = '') {
    $.ajax({
        url: '../api/technician/update-task.php',
        method: 'POST',
        data: {
            task_id: taskId,
            status: status,
            notes: notes
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Task status updated successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error updating task status. Please try again.', 'danger');
        }
    });
}

function checkIn() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                $.ajax({
                    url: '../api/technician/check-in.php',
                    method: 'POST',
                    data: {
                        latitude: lat,
                        longitude: lng
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('Checked in successfully!', 'success');
                        } else {
                            showAlert(response.message, 'danger');
                        }
                    },
                    error: function() {
                        showAlert('Error checking in. Please try again.', 'danger');
                    }
                });
            },
            function(error) {
                showAlert('Unable to get location for check-in.', 'warning');
            }
        );
    } else {
        showAlert('Geolocation is not supported by this browser.', 'warning');
    }
}

function checkOut() {
    if (confirm('Check out for today?')) {
        $.ajax({
            url: '../api/technician/check-out.php',
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('Checked out successfully!', 'success');
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error checking out. Please try again.', 'danger');
            }
        });
    }
}

function openLocation(lat, lng) {
    const url = `https://www.google.com/maps?q=${lat},${lng}`;
    window.open(url, '_blank');
}

function getPriorityBadge(priority) {
    const badges = {
        'low': 'secondary',
        'normal': 'info',
        'high': 'warning',
        'urgent': 'danger'
    };
    return badges[priority] || 'secondary';
}

function getStatusBadge(status) {
    const badges = {
        'pending': 'warning',
        'assigned': 'info',
        'in_progress': 'primary',
        'completed': 'success',
        'cancelled': 'danger'
    };
    return badges[status] || 'secondary';
}

function getStarRating(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<i class="fas fa-star text-warning"></i>';
        } else {
            stars += '<i class="far fa-star text-muted"></i>';
        }
    }
    return stars + ` (${rating}/5)`;
}

function formatDateTime(dateString) {
    if (!dateString) return 'Not specified';
    const date = new Date(dateString);
    return date.toLocaleString('en-IN', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function initializeRealTimeUpdates() {
    // Initialize Firebase for real-time updates
    if (typeof firebase !== 'undefined') {
        const firebaseConfig = {
            apiKey: "your-firebase-api-key",
            authDomain: "water-purifier-erp.firebaseapp.com",
            databaseURL: "https://water-purifier-erp-default-rtdb.firebaseio.com/",
            projectId: "water-purifier-erp"
        };
        
        firebase.initializeApp(firebaseConfig);
        const database = firebase.database();
        
        // Listen for notifications
        const notificationsRef = database.ref('notifications/' + getCurrentUserId());
        notificationsRef.on('child_added', function(snapshot) {
            const notification = snapshot.val();
            showNotification(notification.title, notification.message, notification.type);
        });
        
        // Listen for task updates
        const tasksRef = database.ref('technician_tasks/' + getCurrentUserId());
        tasksRef.on('child_changed', function(snapshot) {
            const task = snapshot.val();
            updateTaskStatus(task.id, task.status);
        });
    }
}

function refreshDashboardData() {
    // Refresh statistics without page reload
    $.ajax({
        url: '../api/technician/dashboard-stats.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                updateDashboardStats(response.data);
            }
        }
    });
}

function updateDashboardStats(stats) {
    // Update statistics cards
    $('.stat-number').each(function() {
        const card = $(this).closest('.dashboard-card');
        const statType = card.find('.stat-label').text().toLowerCase().replace(/\s+/g, '_');
        
        if (stats[statType] !== undefined) {
            animateNumber($(this), stats[statType]);
        }
    });
}

function animateNumber(element, newValue) {
    const currentValue = parseInt(element.text()) || 0;
    const increment = (newValue - currentValue) / 20;
    let current = currentValue;
    
    const timer = setInterval(function() {
        current += increment;
        if ((increment > 0 && current >= newValue) || (increment < 0 && current <= newValue)) {
            current = newValue;
            clearInterval(timer);
        }
        element.text(Math.floor(current));
    }, 50);
}

function showNotification(title, message, type = 'info') {
    const notificationHtml = `
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}</strong><br>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    // Create toast container if it doesn't exist
    if ($('#toastContainer').length === 0) {
        $('body').append('<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3"></div>');
    }
    
    const $toast = $(notificationHtml);
    $('#toastContainer').append($toast);
    
    const toast = new bootstrap.Toast($toast[0]);
    toast.show();
    
    // Auto-remove after 5 seconds
    setTimeout(function() {
        $toast.remove();
    }, 5000);
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('body').append(alertHtml);
    
    // Auto-remove after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}

function getCurrentUserId() {
    // This should be set from PHP session
    return window.currentUserId || 1;
}

// Export functions for global use
window.technicianFunctions = {
    showNotification,
    updateTaskStatus,
    refreshDashboardData
};