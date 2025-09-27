// Dashboard JavaScript Functions
$(document).ready(function() {
    // Initialize dashboard
    initializeDashboard();
    
    // AI Assistant functionality
    initializeAIAssistant();
    
    // Real-time updates
    initializeRealTimeUpdates();
    
    // Notification center
    initializeNotificationCenter();
    
    // Dark mode
    initializeDarkMode();
    
    // Date filters
    initializeDateFilters();
    
    // Export functionality
    initializeExport();
    
    // Refresh button handler
    $('#refreshBtn').click(function() {
        const dateFrom = $('#dateFrom').val();
        const dateTo = $('#dateTo').val();
        refreshDashboardData(dateFrom, dateTo);
    });
    
    // Auto-refresh dashboard data
    setInterval(refreshDashboardData, 30000); // Refresh every 30 seconds
});

function initializeDashboard() {
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

function initializeAIAssistant() {
    const aiInput = $('#aiInput');
    const aiSendBtn = $('#aiSendBtn');
    const aiChatContainer = $('#aiChatContainer');
    
    // Send message on button click
    aiSendBtn.click(function() {
        sendAIMessage();
    });
    
    // Send message on Enter key
    aiInput.keypress(function(e) {
        if (e.which === 13) {
            sendAIMessage();
        }
    });
    
    function sendAIMessage() {
        const message = aiInput.val().trim();
        if (!message) return;
        
        // Add user message to chat
        addMessageToChat(message, 'user');
        aiInput.val('');
        
        // Show typing indicator
        showTypingIndicator();
        
        // Send to AI API
        $.ajax({
            url: '../api/ai/chat.php',
            method: 'POST',
            data: {
                message: message,
                context: 'admin_dashboard'
            },
            dataType: 'json',
            success: function(response) {
                hideTypingIndicator();
                if (response.success) {
                    addMessageToChat(response.message, 'ai');
                } else {
                    addMessageToChat('Sorry, I encountered an error. Please try again.', 'ai');
                }
            },
            error: function() {
                hideTypingIndicator();
                addMessageToChat('Sorry, I'm having trouble connecting. Please try again.', 'ai');
            }
        });
    }
    
    function addMessageToChat(message, sender) {
        const messageHtml = `
            <div class="ai-message mb-3">
                <div class="d-flex ${sender === 'user' ? 'justify-content-end' : ''}">
                    ${sender === 'user' ? '' : '<div class="ai-avatar me-2"><i class="fas fa-robot text-primary"></i></div>'}
                    <div class="ai-content">
                        <div class="ai-bubble ${sender === 'user' ? 'bg-primary text-white' : 'bg-light'}">
                            ${message}
                        </div>
                    </div>
                    ${sender === 'user' ? '<div class="ai-avatar ms-2"><i class="fas fa-user text-secondary"></i></div>' : ''}
                </div>
            </div>
        `;
        
        aiChatContainer.append(messageHtml);
        aiChatContainer.scrollTop(aiChatContainer[0].scrollHeight);
    }
    
    function showTypingIndicator() {
        const typingHtml = `
            <div class="ai-message mb-3" id="typingIndicator">
                <div class="d-flex">
                    <div class="ai-avatar me-2">
                        <i class="fas fa-robot text-primary"></i>
                    </div>
                    <div class="ai-content">
                        <div class="ai-bubble bg-light">
                            <i class="fas fa-circle-notch fa-spin me-2"></i>Thinking...
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        aiChatContainer.append(typingHtml);
        aiChatContainer.scrollTop(aiChatContainer[0].scrollHeight);
    }
    
    function hideTypingIndicator() {
        $('#typingIndicator').remove();
    }
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
        
        // Listen for service request updates
        const servicesRef = database.ref('service_requests');
        servicesRef.on('child_changed', function(snapshot) {
            const service = snapshot.val();
            updateServiceStatus(service.id, service.status);
        });
    }
}

function refreshDashboardData(dateFrom = null, dateTo = null) {
    // Show loading state
    $('#refreshBtn').addClass('loading');
    
    // Build request parameters
    const params = {};
    if (dateFrom) params.date_from = dateFrom;
    if (dateTo) params.date_to = dateTo;
    
    // Refresh statistics without page reload
    $.ajax({
        url: '../api/dashboard/stats.php',
        method: 'GET',
        data: params,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                updateDashboardStats(response.data);
                updateCharts(response.data);
                showToast('Dashboard data refreshed', 'success');
            }
        },
        error: function() {
            showToast('Failed to refresh dashboard data', 'error');
        },
        complete: function() {
            $('#refreshBtn').removeClass('loading');
        }
    });
}

function updateDashboardStats(data) {
    const stats = data.stats;
    
    // Update statistics cards
    $('.stat-number').each(function() {
        const card = $(this).closest('.dashboard-card');
        const statType = card.find('.stat-label').text().toLowerCase().replace(/\s+/g, '_');
        
        if (stats[statType] !== undefined) {
            animateNumber($(this), stats[statType]);
        }
    });
}

function updateCharts(data) {
    // Update revenue chart if it exists
    if (window.revenueChart && data.revenue_data) {
        window.revenueChart.data.labels = data.revenue_data.map(item => item.date);
        window.revenueChart.data.datasets[0].data = data.revenue_data.map(item => item.revenue);
        window.revenueChart.update();
    }
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

function updateServiceStatus(serviceId, status) {
    // Update service status in the table
    const statusBadge = $(`tr[data-service-id="${serviceId}"] .badge`);
    if (statusBadge.length) {
        statusBadge.removeClass('bg-warning bg-info bg-success bg-danger')
                  .addClass('bg-' + getStatusColor(status))
                  .text(status.charAt(0).toUpperCase() + status.slice(1));
    }
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'assigned': 'info',
        'in_progress': 'primary',
        'completed': 'success',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
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

function getCurrentUserId() {
    // This should be set from PHP session
    return window.currentUserId || 1;
}

// Notification Center Functions
function initializeNotificationCenter() {
    // Load notifications on page load
    loadNotifications();
    
    // Set up notification actions
    $('#markAllRead').click(function() {
        markAllNotificationsRead();
    });
    
    $('#clearAll').click(function() {
        clearAllNotifications();
    });
    
    // Auto-refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
}

function loadNotifications() {
    $.ajax({
        url: '../api/notifications/get.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                updateNotificationUI(response.data);
            }
        }
    });
}

function updateNotificationUI(data) {
    const { notifications, unread_count } = data;
    
    // Update notification count
    $('#notificationCount, #navNotificationCount').text(unread_count);
    
    // Update notification list
    const notificationList = $('#notificationList');
    notificationList.empty();
    
    if (notifications.length === 0) {
        notificationList.html(`
            <div class="text-center text-muted py-4">
                <i class="fas fa-bell-slash fa-2x mb-2"></i><br>
                No notifications
            </div>
        `);
        return;
    }
    
    notifications.forEach(notification => {
        const notificationHtml = `
            <div class="notification-item border-bottom py-3 ${!notification.is_read ? 'bg-light' : ''}" data-id="${notification.id}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <i class="fas fa-${getNotificationIcon(notification.type)} text-${getNotificationColor(notification.type)} me-2"></i>
                            <strong class="me-2">${notification.title}</strong>
                            ${!notification.is_read ? '<span class="badge bg-primary">New</span>' : ''}
                        </div>
                        <p class="mb-1 text-muted">${notification.message}</p>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>${getTimeAgo(notification.created_at)}
                        </small>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            ${!notification.is_read ? '<li><a class="dropdown-item" href="#" onclick="markNotificationRead(' + notification.id + ')"><i class="fas fa-check me-1"></i>Mark Read</a></li>' : ''}
                            <li><a class="dropdown-item" href="#" onclick="clearNotification(' + notification.id + ')"><i class="fas fa-trash me-1"></i>Clear</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
        notificationList.append(notificationHtml);
    });
}

function markAllNotificationsRead() {
    $.ajax({
        url: '../api/notifications/mark-read.php',
        method: 'POST',
        data: { mark_all: true },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadNotifications();
                showToast('All notifications marked as read', 'success');
            }
        }
    });
}

function clearAllNotifications() {
    if (confirm('Are you sure you want to clear all notifications?')) {
        $.ajax({
            url: '../api/notifications/clear.php',
            method: 'POST',
            data: { clear_all: true },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    loadNotifications();
                    showToast('All notifications cleared', 'success');
                }
            }
        });
    }
}

function markNotificationRead(notificationId) {
    $.ajax({
        url: '../api/notifications/mark-read.php',
        method: 'POST',
        data: { notification_id: notificationId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadNotifications();
            }
        }
    });
}

function clearNotification(notificationId) {
    $.ajax({
        url: '../api/notifications/clear.php',
        method: 'POST',
        data: { notification_id: notificationId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadNotifications();
            }
        }
    });
}

function getNotificationIcon(type) {
    const icons = {
        'info': 'info-circle',
        'success': 'check-circle',
        'warning': 'exclamation-triangle',
        'error': 'times-circle',
        'service': 'tools',
        'payment': 'credit-card',
        'inventory': 'box',
        'user': 'user'
    };
    return icons[type] || 'bell';
}

function getNotificationColor(type) {
    const colors = {
        'info': 'info',
        'success': 'success',
        'warning': 'warning',
        'error': 'danger',
        'service': 'primary',
        'payment': 'success',
        'inventory': 'warning',
        'user': 'info'
    };
    return colors[type] || 'secondary';
}

// Dark Mode Functions
function initializeDarkMode() {
    // Check for saved dark mode preference
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    if (isDarkMode) {
        enableDarkMode();
    }
    
    $('#darkModeToggle').click(function() {
        toggleDarkMode();
    });
}

function toggleDarkMode() {
    const isDarkMode = $('body').hasClass('dark-mode');
    if (isDarkMode) {
        disableDarkMode();
    } else {
        enableDarkMode();
    }
}

function enableDarkMode() {
    $('body').addClass('dark-mode');
    $('#darkModeToggle').html('<i class="fas fa-sun me-1"></i>Light Mode');
    localStorage.setItem('darkMode', 'true');
}

function disableDarkMode() {
    $('body').removeClass('dark-mode');
    $('#darkModeToggle').html('<i class="fas fa-moon me-1"></i>Dark Mode');
    localStorage.setItem('darkMode', 'false');
}

// Date Filter Functions
function initializeDateFilters() {
    $('#applyDateFilter').click(function() {
        applyDateFilter();
    });
    
    // Set default date range to current month
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    $('#dateFrom').val(formatDate(firstDay));
    $('#dateTo').val(formatDate(today));
}

function applyDateFilter() {
    const dateFrom = $('#dateFrom').val();
    const dateTo = $('#dateTo').val();
    
    if (!dateFrom || !dateTo) {
        showToast('Please select both start and end dates', 'warning');
        return;
    }
    
    if (new Date(dateFrom) > new Date(dateTo)) {
        showToast('Start date cannot be after end date', 'error');
        return;
    }
    
    // Refresh dashboard with new date range
    refreshDashboardData(dateFrom, dateTo);
}

function formatDate(date) {
    return date.toISOString().split('T')[0];
}

// Export Functions
function initializeExport() {
    $('#exportBtn').click(function() {
        exportDashboard();
    });
}

function exportDashboard() {
    const dateFrom = $('#dateFrom').val();
    const dateTo = $('#dateTo').val();
    
    const params = new URLSearchParams({
        export: '1',
        date_from: dateFrom,
        date_to: dateTo
    });
    
    window.open(`../api/dashboard/export.php?${params.toString()}`, '_blank');
}

// Widget Action Functions
function viewCustomers() {
    window.location.href = 'customers.php';
}

function viewTechnicians() {
    window.location.href = 'technicians.php';
}

function viewPendingServices() {
    window.location.href = 'service-requests.php?status=pending';
}

function viewLowStock() {
    window.location.href = 'products.php?filter=low_stock';
}

// Utility Functions
function showToast(message, type = 'info') {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
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
    
    const $toast = $(toastHtml);
    $('#toastContainer').append($toast);
    
    const toast = new bootstrap.Toast($toast[0]);
    toast.show();
    
    // Auto-remove after 5 seconds
    setTimeout(function() {
        $toast.remove();
    }, 5000);
}

function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
    if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + 'd ago';
    return Math.floor(diffInSeconds / 2592000) + 'mo ago';
}

// Export functions for global use
window.dashboardFunctions = {
    showNotification,
    updateServiceStatus,
    refreshDashboardData,
    loadNotifications,
    markNotificationRead,
    clearNotification,
    toggleDarkMode,
    applyDateFilter,
    exportDashboard,
    viewCustomers,
    viewTechnicians,
    viewPendingServices,
    viewLowStock
};