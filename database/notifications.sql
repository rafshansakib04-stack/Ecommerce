-- Notifications table for real-time notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    from_user_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'service', 'payment', 'inventory', 'user') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Insert sample notifications
INSERT INTO notifications (user_id, title, message, type, action_url) VALUES
(1, 'New Service Request', 'A new service request has been submitted by customer ABC Company', 'service', 'admin/service-requests.php'),
(1, 'Low Stock Alert', 'Product "Water Filter Cartridge" is running low on stock', 'inventory', 'admin/products.php'),
(1, 'Payment Received', 'Payment of ₹2,500 received from customer XYZ Ltd', 'payment', 'admin/invoices.php'),
(1, 'Technician Assignment', 'Service request #SR001 has been assigned to technician John Doe', 'service', 'admin/service-requests.php'),
(1, 'System Update', 'System has been updated to version 2.1.0 with new features', 'info', 'admin/settings.php');