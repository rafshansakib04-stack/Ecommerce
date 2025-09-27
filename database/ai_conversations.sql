-- AI Conversations Table
CREATE TABLE IF NOT EXISTS ai_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_message TEXT NOT NULL,
    ai_response TEXT NOT NULL,
    context VARCHAR(255),
    user_role ENUM('admin', 'customer', 'technician') NOT NULL,
    response_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_user_role (user_role),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);