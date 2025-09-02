-- =====================================================
-- PUREFIT WATER PURIFIER BUSINESS MANAGEMENT SYSTEM
-- Complete Database Schema for Production Use
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS purefit_business;
CREATE DATABASE purefit_business CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE purefit_business;

-- =====================================================
-- USER MANAGEMENT TABLES
-- =====================================================

-- Main users table (customers, admins, technicians)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    firebase_uid VARCHAR(128) UNIQUE,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE,
    password_hash VARCHAR(255),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('customer', 'admin', 'super_admin', 'technician', 'sales_agent') DEFAULT 'customer',
    status ENUM('active', 'inactive', 'suspended', 'pending_verification') DEFAULT 'pending_verification',
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    profile_image VARCHAR(255),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_firebase_uid (firebase_uid),
    INDEX idx_role (role),
    INDEX idx_status (status)
);

-- User addresses
CREATE TABLE user_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('home', 'office', 'billing', 'shipping') DEFAULT 'home',
    is_default BOOLEAN DEFAULT FALSE,
    label VARCHAR(100),
    address_line_1 VARCHAR(255) NOT NULL,
    address_line_2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    district VARCHAR(100) NOT NULL,
    division VARCHAR(100) NOT NULL,
    postal_code VARCHAR(10),
    country VARCHAR(100) DEFAULT 'Bangladesh',
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_default (is_default)
);

-- User sessions and login tracking
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_info JSON,
    location_info JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at)
);

-- =====================================================
-- PRODUCT MANAGEMENT TABLES
-- =====================================================

-- Product categories
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    parent_id INT NULL,
    image VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    meta_title VARCHAR(255),
    meta_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_parent_id (parent_id),
    INDEX idx_slug (slug),
    INDEX idx_active (is_active)
);

-- Product brands
CREATE TABLE brands (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    logo VARCHAR(255),
    website VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (is_active)
);

-- Main products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    barcode VARCHAR(100) UNIQUE,
    category_id INT NOT NULL,
    brand_id INT,
    description TEXT,
    short_description TEXT,
    specifications JSON,
    features JSON,
    price DECIMAL(10, 2) NOT NULL,
    sale_price DECIMAL(10, 2),
    cost_price DECIMAL(10, 2),
    stock_quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 5,
    max_stock_level INT DEFAULT 1000,
    weight DECIMAL(8, 3),
    dimensions JSON, -- {length, width, height}
    warranty_period INT DEFAULT 12, -- months
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    status ENUM('active', 'inactive', 'out_of_stock', 'discontinued') DEFAULT 'active',
    meta_title VARCHAR(255),
    meta_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
    INDEX idx_category_id (category_id),
    INDEX idx_brand_id (brand_id),
    INDEX idx_slug (slug),
    INDEX idx_sku (sku),
    INDEX idx_status (status),
    INDEX idx_featured (is_featured),
    INDEX idx_price (price),
    INDEX idx_stock (stock_quantity)
);

-- Product images
CREATE TABLE product_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_id (product_id),
    INDEX idx_primary (is_primary)
);

-- Product variants (for different models/sizes)
CREATE TABLE product_variants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    sale_price DECIMAL(10, 2),
    stock_quantity INT DEFAULT 0,
    attributes JSON, -- {color, size, capacity, etc}
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_id (product_id),
    INDEX idx_sku (sku)
);

-- Product reviews and ratings
CREATE TABLE product_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255),
    review_text TEXT,
    pros TEXT,
    cons TEXT,
    images JSON, -- array of image paths
    is_verified_purchase BOOLEAN DEFAULT FALSE,
    is_approved BOOLEAN DEFAULT FALSE,
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_product_id (product_id),
    INDEX idx_user_id (user_id),
    INDEX idx_rating (rating),
    INDEX idx_approved (is_approved)
);

-- =====================================================
-- INVENTORY MANAGEMENT
-- =====================================================

-- Inventory movements tracking
CREATE TABLE inventory_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    variant_id INT,
    movement_type ENUM('in', 'out', 'adjustment', 'return', 'damage', 'transfer') NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10, 2),
    reference_type ENUM('purchase', 'sale', 'adjustment', 'return', 'damage', 'transfer'),
    reference_id INT,
    notes TEXT,
    performed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    FOREIGN KEY (performed_by) REFERENCES users(id),
    INDEX idx_product_id (product_id),
    INDEX idx_movement_type (movement_type),
    INDEX idx_created_at (created_at)
);

-- Suppliers
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Bangladesh',
    payment_terms VARCHAR(100),
    credit_limit DECIMAL(12, 2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active)
);

-- =====================================================
-- ORDER MANAGEMENT TABLES
-- =====================================================

-- Main orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'partial', 'failed', 'refunded') DEFAULT 'pending',
    total_amount DECIMAL(12, 2) NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    shipping_amount DECIMAL(10, 2) DEFAULT 0,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'BDT',
    
    -- Shipping information
    shipping_method VARCHAR(100),
    shipping_address JSON,
    billing_address JSON,
    
    -- Payment information
    payment_method VARCHAR(100),
    payment_gateway VARCHAR(100),
    transaction_id VARCHAR(255),
    
    -- Order tracking
    estimated_delivery DATE,
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    
    -- Additional info
    notes TEXT,
    admin_notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_user_id (user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
);

-- Order items
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(12, 2) NOT NULL,
    product_name VARCHAR(255) NOT NULL, -- snapshot for history
    product_sku VARCHAR(100) NOT NULL,
    product_specifications JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    INDEX idx_order_id (order_id),
    INDEX idx_product_id (product_id)
);

-- Order status history
CREATE TABLE order_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    previous_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id),
    INDEX idx_order_id (order_id),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- SHOPPING CART & WISHLIST
-- =====================================================

-- Shopping cart
CREATE TABLE cart_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    session_id VARCHAR(255),
    product_id INT NOT NULL,
    variant_id INT,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_product_id (product_id)
);

-- Wishlist
CREATE TABLE wishlist_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (user_id, product_id),
    INDEX idx_user_id (user_id),
    INDEX idx_product_id (product_id)
);

-- =====================================================
-- PAYMENT MANAGEMENT
-- =====================================================

-- Payment transactions
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    payment_method ENUM('bkash', 'nagad', 'rocket', 'card', 'bank_transfer', 'cash_on_delivery', 'installment') NOT NULL,
    gateway VARCHAR(100),
    transaction_id VARCHAR(255) UNIQUE,
    gateway_transaction_id VARCHAR(255),
    amount DECIMAL(12, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'BDT',
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    gateway_response JSON,
    failure_reason TEXT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    INDEX idx_order_id (order_id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_status (status),
    INDEX idx_payment_method (payment_method)
);

-- Invoices
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    total_amount DECIMAL(12, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'BDT',
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    due_date DATE,
    paid_at TIMESTAMP NULL,
    invoice_data JSON, -- Complete invoice details for PDF generation
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_order_id (order_id),
    INDEX idx_user_id (user_id),
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_status (status)
);

-- =====================================================
-- SERVICE MANAGEMENT
-- =====================================================

-- Service types
CREATE TABLE service_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    base_price DECIMAL(10, 2) DEFAULT 0,
    estimated_duration INT DEFAULT 60, -- minutes
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
);

-- Service requests
CREATE TABLE service_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    product_id INT,
    order_id INT, -- if related to a purchase
    service_type_id INT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    
    -- Request details
    problem_description TEXT NOT NULL,
    customer_address JSON,
    preferred_date DATE,
    preferred_time_slot VARCHAR(50),
    
    -- Assignment
    assigned_technician_id INT,
    assigned_at TIMESTAMP NULL,
    
    -- Service details
    service_date DATE,
    service_time_slot VARCHAR(50),
    estimated_cost DECIMAL(10, 2),
    actual_cost DECIMAL(10, 2),
    
    -- Completion
    work_performed TEXT,
    parts_used JSON,
    customer_feedback TEXT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    completed_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_technician_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_assigned_technician_id (assigned_technician_id),
    INDEX idx_service_date (service_date),
    INDEX idx_request_number (request_number)
);

-- Warranty registrations
CREATE TABLE warranties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    warranty_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    order_id INT,
    purchase_date DATE NOT NULL,
    warranty_start_date DATE NOT NULL,
    warranty_end_date DATE NOT NULL,
    warranty_period_months INT NOT NULL,
    status ENUM('active', 'expired', 'claimed', 'void') DEFAULT 'active',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_product_id (product_id),
    INDEX idx_warranty_number (warranty_number),
    INDEX idx_status (status),
    INDEX idx_warranty_end_date (warranty_end_date)
);

-- =====================================================
-- TECHNICIAN MANAGEMENT
-- =====================================================

-- Technician profiles
CREATE TABLE technician_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    specializations JSON, -- array of specialization areas
    service_areas JSON, -- array of service area codes/districts
    hourly_rate DECIMAL(8, 2),
    commission_rate DECIMAL(5, 2) DEFAULT 0,
    is_available BOOLEAN DEFAULT TRUE,
    max_daily_services INT DEFAULT 8,
    rating DECIMAL(3, 2) DEFAULT 5.00,
    total_services INT DEFAULT 0,
    joined_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_employee_id (employee_id),
    INDEX idx_available (is_available)
);

-- Technician schedules
CREATE TABLE technician_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    technician_id INT NOT NULL,
    date DATE NOT NULL,
    time_slot VARCHAR(50) NOT NULL, -- e.g., "09:00-10:00"
    is_available BOOLEAN DEFAULT TRUE,
    service_request_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_request_id) REFERENCES service_requests(id) ON DELETE SET NULL,
    UNIQUE KEY unique_schedule (technician_id, date, time_slot),
    INDEX idx_technician_id (technician_id),
    INDEX idx_date (date),
    INDEX idx_available (is_available)
);

-- =====================================================
-- MARKETING & PROMOTIONS
-- =====================================================

-- Promo codes and discounts
CREATE TABLE promo_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type ENUM('percentage', 'fixed_amount', 'free_shipping') NOT NULL,
    value DECIMAL(10, 2) NOT NULL,
    minimum_order_amount DECIMAL(10, 2) DEFAULT 0,
    maximum_discount_amount DECIMAL(10, 2),
    usage_limit INT,
    used_count INT DEFAULT 0,
    user_limit INT DEFAULT 1, -- per user
    applicable_to ENUM('all', 'specific_products', 'specific_categories') DEFAULT 'all',
    applicable_items JSON, -- product/category IDs
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_code (code),
    INDEX idx_active (is_active),
    INDEX idx_dates (start_date, end_date)
);

-- Promo code usage tracking
CREATE TABLE promo_code_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    promo_code_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    discount_amount DECIMAL(10, 2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promo_code_id) REFERENCES promo_codes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_promo_code_id (promo_code_id),
    INDEX idx_user_id (user_id),
    INDEX idx_order_id (order_id)
);

-- Email campaigns
CREATE TABLE email_campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    template VARCHAR(100),
    target_audience ENUM('all_customers', 'recent_customers', 'inactive_customers', 'high_value_customers', 'custom') DEFAULT 'all_customers',
    target_criteria JSON,
    status ENUM('draft', 'scheduled', 'sending', 'sent', 'paused') DEFAULT 'draft',
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    total_recipients INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    opened_count INT DEFAULT 0,
    clicked_count INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at)
);

-- =====================================================
-- COMMUNICATION & SUPPORT
-- =====================================================

-- Customer support tickets
CREATE TABLE support_tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    category ENUM('technical', 'billing', 'general', 'complaint', 'suggestion') DEFAULT 'general',
    assigned_to INT,
    resolved_at TIMESTAMP NULL,
    resolution_notes TEXT,
    customer_satisfaction INT CHECK (customer_satisfaction >= 1 AND customer_satisfaction <= 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_ticket_number (ticket_number),
    INDEX idx_status (status),
    INDEX idx_priority (priority)
);

-- Support ticket messages
CREATE TABLE support_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    attachments JSON,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_created_at (created_at)
);

-- Live chat sessions
CREATE TABLE chat_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    user_id INT,
    visitor_info JSON, -- for anonymous users
    status ENUM('waiting', 'active', 'ended') DEFAULT 'waiting',
    assigned_agent_id INT,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    feedback TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_agent_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Chat messages
CREATE TABLE chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) NOT NULL,
    sender_type ENUM('customer', 'agent', 'system') NOT NULL,
    sender_id INT,
    message TEXT NOT NULL,
    message_type ENUM('text', 'image', 'file', 'system') DEFAULT 'text',
    attachments JSON,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session_id (session_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- NOTIFICATION SYSTEM
-- =====================================================

-- Notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON, -- additional data
    channels JSON, -- ['email', 'sms', 'push', 'in_app']
    status ENUM('pending', 'sent', 'read', 'failed') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at)
);

-- =====================================================
-- ANALYTICS & REPORTING
-- =====================================================

-- Website analytics
CREATE TABLE analytics_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    session_id VARCHAR(255),
    event_type VARCHAR(100) NOT NULL,
    event_data JSON,
    page_url VARCHAR(500),
    referrer VARCHAR(500),
    user_agent TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
);

-- Business metrics (daily aggregated data)
CREATE TABLE daily_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL UNIQUE,
    total_sales DECIMAL(15, 2) DEFAULT 0,
    total_orders INT DEFAULT 0,
    new_customers INT DEFAULT 0,
    returning_customers INT DEFAULT 0,
    website_visitors INT DEFAULT 0,
    conversion_rate DECIMAL(5, 4) DEFAULT 0,
    average_order_value DECIMAL(10, 2) DEFAULT 0,
    service_requests INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (date)
);

-- =====================================================
-- SYSTEM CONFIGURATION
-- =====================================================

-- System settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE, -- can be accessed by frontend
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_setting_key (setting_key),
    INDEX idx_public (is_public)
);

-- Activity logs
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(100),
    resource_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data JSON, -- additional context data
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- INITIAL DATA SETUP
-- =====================================================

-- Insert default admin user
INSERT INTO users (email, password_hash, first_name, last_name, role, status, email_verified) VALUES
('admin@purefitbd.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'super_admin', 'active', TRUE);

-- Insert default categories
INSERT INTO categories (name, slug, description) VALUES
('Water Purifiers', 'water-purifiers', 'High-quality water purification systems'),
('RO Systems', 'ro-systems', 'Reverse Osmosis water purification systems'),
('UV Purifiers', 'uv-purifiers', 'UV light water purification systems'),
('Alkaline Systems', 'alkaline-systems', 'Alkaline water purification systems'),
('Spare Parts', 'spare-parts', 'Replacement parts and filters'),
('Accessories', 'accessories', 'Water purifier accessories and add-ons');

-- Insert default brands
INSERT INTO brands (name, slug, description) VALUES
('PureFit', 'purefit', 'Premium water purification solutions'),
('AquaGuard', 'aquaguard', 'Trusted water purifier brand'),
('Kent', 'kent', 'Advanced RO water purifiers'),
('Pureit', 'pureit', 'Affordable water purification'),
('Blue Star', 'blue-star', 'Professional water treatment systems');

-- Insert service types
INSERT INTO service_types (name, description, base_price, estimated_duration) VALUES
('Installation', 'Professional water purifier installation', 1500.00, 120),
('Maintenance', 'Regular maintenance and cleaning', 800.00, 90),
('Filter Replacement', 'Replace filters and cartridges', 500.00, 60),
('Repair', 'Fix water purifier issues', 1000.00, 150),
('Annual Service', 'Comprehensive annual maintenance', 2000.00, 180);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'PureFit Bangladesh', 'string', 'Website name', TRUE),
('site_email', 'info@purefitbd.com', 'string', 'Main contact email', TRUE),
('site_phone', '+880-1700-000000', 'string', 'Main contact phone', TRUE),
('currency', 'BDT', 'string', 'Default currency', TRUE),
('tax_rate', '0.00', 'number', 'Default tax rate', FALSE),
('shipping_rate', '100.00', 'number', 'Default shipping cost', TRUE),
('min_order_amount', '1000.00', 'number', 'Minimum order amount', TRUE),
('free_shipping_threshold', '5000.00', 'number', 'Free shipping threshold', TRUE),
('business_hours', '{"monday": "9:00-18:00", "tuesday": "9:00-18:00", "wednesday": "9:00-18:00", "thursday": "9:00-18:00", "friday": "9:00-18:00", "saturday": "9:00-16:00", "sunday": "closed"}', 'json', 'Business operating hours', TRUE),
('social_media', '{"facebook": "https://facebook.com/purefitbd", "instagram": "https://instagram.com/purefitbd", "youtube": "https://youtube.com/purefitbd"}', 'json', 'Social media links', TRUE);

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- CREATE INDEXES FOR PERFORMANCE
-- =====================================================

-- Additional performance indexes
CREATE INDEX idx_products_category_status ON products(category_id, status);
CREATE INDEX idx_products_featured_active ON products(is_featured, is_active);
CREATE INDEX idx_orders_user_status ON orders(user_id, status);
CREATE INDEX idx_orders_date_range ON orders(created_at, status);
CREATE INDEX idx_inventory_product_date ON inventory_movements(product_id, created_at);
CREATE INDEX idx_notifications_user_status ON notifications(user_id, status);
CREATE INDEX idx_analytics_date_type ON analytics_events(created_at, event_type);

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- Product summary view
CREATE VIEW product_summary AS
SELECT 
    p.id,
    p.name,
    p.slug,
    p.sku,
    p.price,
    p.sale_price,
    p.stock_quantity,
    p.is_featured,
    p.status,
    c.name as category_name,
    b.name as brand_name,
    COALESCE(AVG(pr.rating), 0) as average_rating,
    COUNT(pr.id) as review_count,
    p.created_at
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
LEFT JOIN brands b ON p.brand_id = b.id
LEFT JOIN product_reviews pr ON p.id = pr.product_id AND pr.is_approved = TRUE
WHERE p.is_active = TRUE
GROUP BY p.id;

-- Order summary view
CREATE VIEW order_summary AS
SELECT 
    o.id,
    o.order_number,
    o.user_id,
    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
    u.email as customer_email,
    u.phone as customer_phone,
    o.status,
    o.payment_status,
    o.total_amount,
    o.created_at,
    o.updated_at,
    COUNT(oi.id) as total_items
FROM orders o
JOIN users u ON o.user_id = u.id
LEFT JOIN order_items oi ON o.id = oi.order_id
GROUP BY o.id;

-- Customer analytics view
CREATE VIEW customer_analytics AS
SELECT 
    u.id,
    u.email,
    CONCAT(u.first_name, ' ', u.last_name) as full_name,
    u.created_at as registration_date,
    COUNT(DISTINCT o.id) as total_orders,
    COALESCE(SUM(o.total_amount), 0) as total_spent,
    COALESCE(AVG(o.total_amount), 0) as average_order_value,
    MAX(o.created_at) as last_order_date,
    COUNT(DISTINCT sr.id) as service_requests
FROM users u
LEFT JOIN orders o ON u.id = o.user_id AND o.status != 'cancelled'
LEFT JOIN service_requests sr ON u.id = sr.user_id
WHERE u.role = 'customer'
GROUP BY u.id;

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

DELIMITER //

-- Update product stock
CREATE PROCEDURE UpdateProductStock(
    IN p_product_id INT,
    IN p_variant_id INT,
    IN p_quantity INT,
    IN p_movement_type VARCHAR(20),
    IN p_reference_type VARCHAR(20),
    IN p_reference_id INT,
    IN p_performed_by INT,
    IN p_notes TEXT
)
BEGIN
    DECLARE current_stock INT DEFAULT 0;
    
    START TRANSACTION;
    
    -- Get current stock
    IF p_variant_id IS NOT NULL THEN
        SELECT stock_quantity INTO current_stock FROM product_variants WHERE id = p_variant_id;
        
        -- Update variant stock
        IF p_movement_type = 'in' THEN
            UPDATE product_variants SET stock_quantity = stock_quantity + p_quantity WHERE id = p_variant_id;
        ELSE
            UPDATE product_variants SET stock_quantity = stock_quantity - p_quantity WHERE id = p_variant_id;
        END IF;
    ELSE
        SELECT stock_quantity INTO current_stock FROM products WHERE id = p_product_id;
        
        -- Update product stock
        IF p_movement_type = 'in' THEN
            UPDATE products SET stock_quantity = stock_quantity + p_quantity WHERE id = p_product_id;
        ELSE
            UPDATE products SET stock_quantity = stock_quantity - p_quantity WHERE id = p_product_id;
        END IF;
    END IF;
    
    -- Record inventory movement
    INSERT INTO inventory_movements (
        product_id, variant_id, movement_type, quantity, 
        reference_type, reference_id, notes, performed_by
    ) VALUES (
        p_product_id, p_variant_id, p_movement_type, p_quantity,
        p_reference_type, p_reference_id, p_notes, p_performed_by
    );
    
    COMMIT;
END //

-- Generate order number
CREATE FUNCTION GenerateOrderNumber() RETURNS VARCHAR(50)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE order_count INT;
    DECLARE order_number VARCHAR(50);
    
    SELECT COUNT(*) INTO order_count FROM orders WHERE DATE(created_at) = CURDATE();
    SET order_number = CONCAT('PF', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(order_count + 1, 4, '0'));
    
    RETURN order_number;
END //

DELIMITER ;

-- =====================================================
-- TRIGGERS
-- =====================================================

-- Auto-generate order numbers
DELIMITER //
CREATE TRIGGER before_order_insert 
BEFORE INSERT ON orders 
FOR EACH ROW 
BEGIN 
    IF NEW.order_number IS NULL OR NEW.order_number = '' THEN
        SET NEW.order_number = GenerateOrderNumber();
    END IF;
END //
DELIMITER ;

-- Update product status based on stock
DELIMITER //
CREATE TRIGGER after_inventory_update 
AFTER UPDATE ON products 
FOR EACH ROW 
BEGIN 
    IF NEW.stock_quantity <= 0 AND OLD.stock_quantity > 0 THEN
        UPDATE products SET status = 'out_of_stock' WHERE id = NEW.id;
    ELSEIF NEW.stock_quantity > 0 AND OLD.stock_quantity <= 0 THEN
        UPDATE products SET status = 'active' WHERE id = NEW.id;
    END IF;
END //
DELIMITER ;

-- =====================================================
-- SAMPLE DATA FOR TESTING
-- =====================================================

-- Sample products (Real water purifier products for Bangladesh market)
INSERT INTO products (name, slug, sku, category_id, brand_id, description, short_description, price, sale_price, cost_price, stock_quantity, warranty_period, is_featured, specifications, features) VALUES
('PureFit RO+UV+UF 7 Stage Water Purifier', 'purefit-ro-uv-uf-7-stage', 'PF-RO-001', 1, 1, 'Advanced 7-stage water purification system with RO+UV+UF technology. Perfect for Bangladesh water conditions with TDS controller and mineral enhancement.', 'Advanced 7-stage purification with RO+UV+UF technology', 25000.00, 22000.00, 18000.00, 50, 24, TRUE, '{"capacity": "10L/hr", "stages": 7, "technology": "RO+UV+UF", "tds_range": "50-2000", "power": "24V", "dimensions": "385x265x525mm"}', '["7-stage purification", "TDS controller", "UV sterilization", "Mineral enhancement", "Auto-flush", "Filter change indicator"]'),

('Kent Grand Plus RO Water Purifier', 'kent-grand-plus-ro', 'KENT-001', 2, 3, 'Kent Grand Plus with patented Mineral RO technology that retains essential minerals while removing harmful contaminants. Ideal for Dhaka municipal water.', 'Mineral RO technology with essential mineral retention', 18500.00, 16500.00, 14000.00, 30, 12, TRUE, '{"capacity": "8L/hr", "stages": 6, "technology": "Mineral RO", "tds_range": "50-2000", "power": "24V", "storage": "8L"}', '["Mineral RO technology", "8L storage tank", "Multiple purification", "Smart indicators", "Zero water wastage"]'),

('Aquaguard Enhance UV+UF Water Purifier', 'aquaguard-enhance-uv-uf', 'AG-UV-001', 3, 2, 'Aquaguard Enhance with UV+UF technology for comprehensive water purification. No electricity required for UF filtration, perfect for areas with power cuts.', 'UV+UF technology with gravity-based filtration option', 12000.00, 10500.00, 8500.00, 75, 12, FALSE, '{"capacity": "6L/hr", "stages": 4, "technology": "UV+UF", "power": "11W", "storage": "7L"}', '["UV sterilization", "UF filtration", "Gravity option", "LED indicators", "Compact design"]'),

('PureFit Alkaline Water Ionizer', 'purefit-alkaline-ionizer', 'PF-ALK-001', 4, 1, 'Advanced alkaline water ionizer that produces pH balanced alkaline water with antioxidant properties. Perfect for health-conscious families in Bangladesh.', 'Advanced alkaline water ionizer with pH control', 45000.00, 40000.00, 32000.00, 15, 36, TRUE, '{"ph_range": "3.0-11.0", "orp": "-800 to +1200", "plates": 7, "power": "120W", "flow_rate": "3L/min"}', '["7 platinum plates", "pH control", "ORP adjustment", "Self-cleaning", "Voice prompts", "Touch panel"]');

-- Sample filter products
INSERT INTO products (name, slug, sku, category_id, brand_id, description, short_description, price, sale_price, cost_price, stock_quantity, warranty_period, specifications, features) VALUES
('Pre-Filter Cartridge 10 inch', 'pre-filter-cartridge-10inch', 'FILTER-PRE-001', 5, 1, 'High-quality pre-filter cartridge for removing sediments and particles. Compatible with most water purifier systems.', '10-inch pre-filter for sediment removal', 350.00, 300.00, 200.00, 200, 6, '{"size": "10inch", "micron": "5", "material": "PP", "flow_rate": "2GPM"}', '["5 micron filtration", "High dirt holding", "Food grade material", "Easy installation"]'),

('Carbon Block Filter', 'carbon-block-filter', 'FILTER-CB-001', 5, 1, 'Activated carbon block filter for removing chlorine, odor, and taste. Essential for improving water quality and taste.', 'Carbon block filter for chlorine and odor removal', 450.00, 400.00, 280.00, 150, 6, '{"size": "10inch", "material": "Activated Carbon", "capacity": "2000L", "flow_rate": "1.5GPM"}', '["Chlorine removal", "Odor elimination", "Taste improvement", "High capacity"]');

-- Insert system settings for Bangladesh business
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('company_name', 'PureFit Bangladesh Ltd.', 'string', 'Official company name', TRUE),
('company_address', 'House 123, Road 456, Dhanmondi, Dhaka-1205, Bangladesh', 'string', 'Company address', TRUE),
('company_phone', '+880-1700-123456', 'string', 'Company phone number', TRUE),
('company_email', 'info@purefitbd.com', 'string', 'Company email', TRUE),
('whatsapp_number', '+8801700123456', 'string', 'WhatsApp business number', TRUE),
('facebook_page', 'https://facebook.com/purefitbd', 'string', 'Facebook page URL', TRUE),
('google_maps_api_key', '', 'string', 'Google Maps API key for delivery tracking', FALSE),
('sms_api_key', '', 'string', 'SMS gateway API key', FALSE),
('email_smtp_host', 'smtp.gmail.com', 'string', 'SMTP host for emails', FALSE),
('email_smtp_port', '587', 'string', 'SMTP port', FALSE),
('bkash_merchant_id', '', 'string', 'bKash merchant ID', FALSE),
('nagad_merchant_id', '', 'string', 'Nagad merchant ID', FALSE),
('maintenance_mode', 'false', 'boolean', 'Enable maintenance mode', FALSE);

COMMIT;