// Dashboard JavaScript Functions
$(document).ready(function() {
    // Initialize dashboard
    initializeDashboard();
    
    // AI Assistant functionality
    initializeAIAssistant();
    
    // Real-time updates
    initializeRealTimeUpdates();
    
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

function refreshDashboardData() {
    // Refresh statistics without page reload
    $.ajax({
        url: '../api/dashboard/stats.php',
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

// Export functions for global use
window.dashboardFunctions = {
    showNotification,
    updateServiceStatus,
    refreshDashboardData
};