<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$db = Database::getInstance();

// Get user's recent conversations
$conversations = $db->fetchAll("
    SELECT * FROM ai_conversations 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
", [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - Water Purifier ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .chat-container {
            height: 80vh;
            display: flex;
            flex-direction: column;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }
        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }
        .message.user {
            justify-content: flex-end;
        }
        .message.ai {
            justify-content: flex-start;
        }
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
        }
        .message.user .message-bubble {
            background: #0d6efd;
            color: white;
            border-bottom-right-radius: 4px;
        }
        .message.ai .message-bubble {
            background: white;
            color: #333;
            border: 1px solid #dee2e6;
            border-bottom-left-radius: 4px;
        }
        .message-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .chat-input {
            border-top: 1px solid #dee2e6;
            padding: 20px;
            background: white;
        }
        .suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        .suggestion-chip {
            background: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .suggestion-chip:hover {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        .typing-indicator {
            display: none;
            align-items: center;
            gap: 8px;
            color: #6c757d;
            font-style: italic;
        }
        .typing-dots {
            display: flex;
            gap: 4px;
        }
        .typing-dot {
            width: 6px;
            height: 6px;
            background: #6c757d;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }
        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
            }
            30% {
                transform: translateY(-10px);
            }
        }
        .ai-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #0d6efd, #6610f2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 10px;
            flex-shrink: 0;
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            background: #6c757d;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-left: 10px;
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-robot me-2"></i>AI Assistant
                        </h4>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary btn-sm" onclick="clearChat()">
                                <i class="fas fa-trash me-1"></i>Clear Chat
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="showHelp()">
                                <i class="fas fa-question-circle me-1"></i>Help
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="chat-container">
                            <div class="chat-messages" id="chatMessages">
                                <!-- Welcome message -->
                                <div class="message ai">
                                    <div class="ai-avatar">
                                        <i class="fas fa-robot"></i>
                                    </div>
                                    <div>
                                        <div class="message-bubble">
                                            <strong>AI Assistant:</strong> Hello! I'm your AI assistant for the Water Purifier ERP system. I can help you with dashboard information, service requests, sales data, and much more. How can I assist you today?
                                        </div>
                                        <div class="message-time"><?php echo date('H:i'); ?></div>
                                    </div>
                                </div>
                                
                                <!-- Recent conversations -->
                                <?php foreach ($conversations as $conversation): ?>
                                <div class="message user">
                                    <div>
                                        <div class="message-bubble">
                                            <?php echo htmlspecialchars($conversation['user_message']); ?>
                                        </div>
                                        <div class="message-time"><?php echo date('H:i', strtotime($conversation['created_at'])); ?></div>
                                    </div>
                                    <div class="user-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>
                                
                                <div class="message ai">
                                    <div class="ai-avatar">
                                        <i class="fas fa-robot"></i>
                                    </div>
                                    <div>
                                        <div class="message-bubble">
                                            <?php echo nl2br(htmlspecialchars($conversation['ai_response'])); ?>
                                        </div>
                                        <div class="message-time"><?php echo date('H:i', strtotime($conversation['created_at'])); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <!-- Typing indicator -->
                                <div class="typing-indicator" id="typingIndicator">
                                    <div class="ai-avatar">
                                        <i class="fas fa-robot"></i>
                                    </div>
                                    <div>
                                        <div class="message-bubble">
                                            <span>AI is typing</span>
                                            <div class="typing-dots">
                                                <div class="typing-dot"></div>
                                                <div class="typing-dot"></div>
                                                <div class="typing-dot"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Suggestions -->
                            <div class="suggestions" id="suggestions">
                                <div class="suggestion-chip" onclick="sendMessage('Show me today\'s dashboard')">Dashboard</div>
                                <div class="suggestion-chip" onclick="sendMessage('What are my pending tasks?')">Pending Tasks</div>
                                <div class="suggestion-chip" onclick="sendMessage('Show sales summary')">Sales Summary</div>
                                <div class="suggestion-chip" onclick="sendMessage('Help me with reports')">Reports</div>
                            </div>
                            
                            <!-- Chat input -->
                            <div class="chat-input">
                                <form id="chatForm">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="messageInput" placeholder="Type your message..." autocomplete="off" required>
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-question-circle me-2"></i>AI Assistant Help
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>General Commands:</h6>
                            <ul>
                                <li>"Show me the dashboard"</li>
                                <li>"What's my sales summary?"</li>
                                <li>"Help me with reports"</li>
                                <li>"Show pending tasks"</li>
                                <li>"What's the inventory status?"</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Role-specific Help:</h6>
                            <ul>
                                <li><strong>Admin:</strong> Sales, customers, reports</li>
                                <li><strong>Customer:</strong> Service requests, tracking</li>
                                <li><strong>Technician:</strong> Tasks, billing, expenses</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        let isTyping = false;
        
        $(document).ready(function() {
            // Scroll to bottom
            scrollToBottom();
            
            // Form submission
            $('#chatForm').submit(function(e) {
                e.preventDefault();
                const message = $('#messageInput').val().trim();
                if (message) {
                    sendMessage(message);
                    $('#messageInput').val('');
                }
            });
            
            // Enter key handling
            $('#messageInput').keypress(function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    $('#chatForm').submit();
                }
            });
        });
        
        function sendMessage(message) {
            if (isTyping) return;
            
            // Add user message to chat
            addMessage(message, 'user');
            
            // Show typing indicator
            showTypingIndicator();
            
            // Send to AI
            $.ajax({
                url: 'chat.php',
                method: 'POST',
                data: {
                    message: message,
                    context: 'chat_interface'
                },
                dataType: 'json',
                success: function(response) {
                    hideTypingIndicator();
                    
                    if (response.success) {
                        addMessage(response.data.message, 'ai');
                        
                        // Update suggestions if provided
                        if (response.data.suggestions) {
                            updateSuggestions(response.data.suggestions);
                        }
                    } else {
                        addMessage('Sorry, I encountered an error. Please try again.', 'ai');
                    }
                },
                error: function() {
                    hideTypingIndicator();
                    addMessage('Sorry, I\'m having trouble connecting. Please try again.', 'ai');
                }
            });
        }
        
        function addMessage(message, sender) {
            const chatMessages = $('#chatMessages');
            const time = new Date().toLocaleTimeString('en-IN', {hour: '2-digit', minute: '2-digit'});
            
            let messageHtml;
            if (sender === 'user') {
                messageHtml = `
                    <div class="message user">
                        <div>
                            <div class="message-bubble">${escapeHtml(message)}</div>
                            <div class="message-time">${time}</div>
                        </div>
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                `;
            } else {
                messageHtml = `
                    <div class="message ai">
                        <div class="ai-avatar">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div>
                            <div class="message-bubble">${escapeHtml(message).replace(/\n/g, '<br>')}</div>
                            <div class="message-time">${time}</div>
                        </div>
                    </div>
                `;
            }
            
            chatMessages.append(messageHtml);
            scrollToBottom();
        }
        
        function showTypingIndicator() {
            isTyping = true;
            $('#typingIndicator').show();
            scrollToBottom();
        }
        
        function hideTypingIndicator() {
            isTyping = false;
            $('#typingIndicator').hide();
        }
        
        function updateSuggestions(suggestions) {
            const suggestionsContainer = $('#suggestions');
            suggestionsContainer.empty();
            
            suggestions.forEach(function(suggestion) {
                suggestionsContainer.append(`
                    <div class="suggestion-chip" onclick="sendMessage('${suggestion}')">${suggestion}</div>
                `);
            });
        }
        
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        function clearChat() {
            if (confirm('Are you sure you want to clear the chat history?')) {
                $('#chatMessages').html(`
                    <div class="message ai">
                        <div class="ai-avatar">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div>
                            <div class="message-bubble">
                                <strong>AI Assistant:</strong> Chat cleared! How can I help you today?
                            </div>
                            <div class="message-time">${new Date().toLocaleTimeString('en-IN', {hour: '2-digit', minute: '2-digit'})}</div>
                        </div>
                    </div>
                `);
            }
        }
        
        function showHelp() {
            $('#helpModal').modal('show');
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Auto-focus on input
        $('#messageInput').focus();
        
        // Handle suggestion clicks
        $(document).on('click', '.suggestion-chip', function() {
            const message = $(this).text();
            sendMessage(message);
        });
    </script>
</body>
</html>