<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $message = $_POST['message'] ?? '';
    $context = $_POST['context'] ?? '';
    $userRole = $_SESSION['user_role'];
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message is required']);
        exit();
    }
    
    $db = Database::getInstance();
    
    // Process the AI chat request
    $response = processAIChat($db, $message, $context, $userRole);
    
    // Log the conversation
    $db->insert('ai_conversations', [
        'user_id' => $_SESSION['user_id'],
        'user_message' => $message,
        'ai_response' => $response['message'],
        'context' => $context,
        'user_role' => $userRole,
        'response_type' => $response['type']
    ]);
    
    echo json_encode([
        'success' => true,
        'data' => $response
    ]);
    
} catch (Exception $e) {
    error_log('AI Chat error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

function processAIChat($db, $message, $context, $userRole) {
    $message = strtolower(trim($message));
    
    // Greeting responses
    if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening)/', $message)) {
        return [
            'message' => getGreetingResponse($userRole),
            'type' => 'greeting',
            'suggestions' => getContextualSuggestions($userRole)
        ];
    }
    
    // Help requests
    if (preg_match('/help|assist|support/', $message)) {
        return [
            'message' => getHelpResponse($userRole),
            'type' => 'help',
            'suggestions' => getHelpSuggestions($userRole)
        ];
    }
    
    // Dashboard queries
    if (preg_match('/dashboard|overview|summary/', $message)) {
        return getDashboardInfo($db, $userRole);
    }
    
    // Sales queries
    if (preg_match('/sales|revenue|income|profit/', $message)) {
        return getSalesInfo($db, $userRole);
    }
    
    // Service queries
    if (preg_match('/service|request|technician|customer/', $message)) {
        return getServiceInfo($db, $userRole);
    }
    
    // Inventory queries
    if (preg_match('/inventory|stock|product/', $message)) {
        return getInventoryInfo($db, $userRole);
    }
    
    // Financial queries
    if (preg_match('/financial|expense|cost|budget/', $message)) {
        return getFinancialInfo($db, $userRole);
    }
    
    // Report queries
    if (preg_match('/report|analytics|chart/', $message)) {
        return getReportInfo($db, $userRole);
    }
    
    // Default response
    return [
        'message' => "I understand you're asking about: '$message'. Could you please be more specific? I can help you with dashboard information, sales data, service requests, inventory management, and financial reports.",
        'type' => 'general',
        'suggestions' => getContextualSuggestions($userRole)
    ];
}

function getGreetingResponse($userRole) {
    $greetings = [
        'admin' => "Hello! I'm your AI assistant for the Water Purifier ERP system. I can help you with dashboard analytics, sales reports, customer management, and system administration. What would you like to know?",
        'customer' => "Hi there! I'm here to help you with your water purifier services. I can assist you with service requests, tracking your technician, billing information, and account management. How can I help you today?",
        'technician' => "Hello! I'm your AI assistant for field operations. I can help you with task management, route optimization, billing, expense tracking, and performance analytics. What do you need assistance with?"
    ];
    
    return $greetings[$userRole] ?? "Hello! I'm your AI assistant. How can I help you today?";
}

function getContextualSuggestions($userRole) {
    $suggestions = [
        'admin' => [
            'Show me today\'s sales summary',
            'How many service requests are pending?',
            'What\'s the inventory status?',
            'Generate a financial report',
            'Show customer analytics'
        ],
        'customer' => [
            'Check my service status',
            'Request a new service',
            'View my service history',
            'Track my technician',
            'Update my profile'
        ],
        'technician' => [
            'Show my assigned tasks',
            'Update task status',
            'Create a new bill',
            'Add expense to cashbook',
            'View my performance'
        ]
    ];
    
    return $suggestions[$userRole] ?? ['How can I help you?'];
}

function getHelpResponse($userRole) {
    $helpMessages = [
        'admin' => "As an admin, I can help you with:\n• Dashboard analytics and KPIs\n• Sales and revenue reports\n• Customer and technician management\n• Inventory and stock management\n• Financial reports and analytics\n• System configuration and settings\n\nWhat specific area would you like help with?",
        'customer' => "As a customer, I can help you with:\n• Service request management\n• Real-time service tracking\n• Billing and payment information\n• Service history and ratings\n• Profile and account management\n• Contact and support\n\nWhat do you need assistance with?",
        'technician' => "As a technician, I can help you with:\n• Task and service management\n• Route optimization and navigation\n• Billing and invoice creation\n• Expense tracking and cashbook\n• Performance analytics\n• Customer communication\n\nHow can I assist you today?"
    ];
    
    return $helpMessages[$userRole] ?? "I can help you with various aspects of the system. What would you like to know?";
}

function getHelpSuggestions($userRole) {
    return [
        'Show me the main dashboard',
        'How do I create a new service request?',
        'Where can I find my reports?',
        'How do I update my profile?'
    ];
}

function getDashboardInfo($db, $userRole) {
    $today = date('Y-m-d');
    
    if ($userRole === 'admin') {
        $stats = [
            'total_customers' => $db->fetchOne("SELECT COUNT(*) as count FROM customers")['count'],
            'total_technicians' => $db->fetchOne("SELECT COUNT(*) as count FROM technicians")['count'],
            'pending_services' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE status IN ('pending', 'assigned')")['count'],
            'today_revenue' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM invoices WHERE DATE(created_at) = ?", [$today])['total']
        ];
        
        return [
            'message' => "Here's your dashboard summary:\n• Total Customers: {$stats['total_customers']}\n• Total Technicians: {$stats['total_technicians']}\n• Pending Services: {$stats['pending_services']}\n• Today's Revenue: ₹" . number_format($stats['today_revenue']),
            'type' => 'dashboard',
            'data' => $stats
        ];
    } elseif ($userRole === 'customer') {
        $customer = $db->fetchOne("SELECT id FROM customers WHERE user_id = ?", [$_SESSION['user_id']]);
        if ($customer) {
            $stats = [
                'total_services' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE customer_id = ?", [$customer['id']])['count'],
                'pending_services' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE customer_id = ? AND status IN ('pending', 'assigned', 'in_progress')", [$customer['id']])['count'],
                'completed_services' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE customer_id = ? AND status = 'completed'", [$customer['id']])['count']
            ];
            
            return [
                'message' => "Here's your service summary:\n• Total Services: {$stats['total_services']}\n• Pending Services: {$stats['pending_services']}\n• Completed Services: {$stats['completed_services']}",
                'type' => 'dashboard',
                'data' => $stats
            ];
        }
    } elseif ($userRole === 'technician') {
        $technician = $db->fetchOne("SELECT id FROM technicians WHERE user_id = ?", [$_SESSION['user_id']]);
        if ($technician) {
            $stats = [
                'assigned_tasks' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE assigned_technician_id = ? AND status IN ('assigned', 'in_progress')", [$technician['id']])['count'],
                'completed_tasks' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE assigned_technician_id = ? AND status = 'completed'", [$technician['id']])['count'],
                'today_income' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM daily_cashbook WHERE user_id = ? AND transaction_type = 'income' AND DATE(transaction_date) = ?", [$_SESSION['user_id'], $today])['total']
            ];
            
            return [
                'message' => "Here's your task summary:\n• Assigned Tasks: {$stats['assigned_tasks']}\n• Completed Tasks: {$stats['completed_tasks']}\n• Today's Income: ₹" . number_format($stats['today_income']),
                'type' => 'dashboard',
                'data' => $stats
            ];
        }
    }
    
    return [
        'message' => "I couldn't retrieve your dashboard information at the moment. Please try again later.",
        'type' => 'error'
    ];
}

function getSalesInfo($db, $userRole) {
    if ($userRole !== 'admin') {
        return [
            'message' => "Sales information is only available to administrators.",
            'type' => 'restricted'
        ];
    }
    
    $today = date('Y-m-d');
    $thisMonth = date('Y-m');
    
    $sales = [
        'today_sales' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM invoices WHERE DATE(created_at) = ?", [$today])['total'],
        'monthly_sales' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM invoices WHERE DATE_FORMAT(created_at, '%Y-%m') = ?", [$thisMonth])['total'],
        'total_invoices' => $db->fetchOne("SELECT COUNT(*) as count FROM invoices WHERE DATE_FORMAT(created_at, '%Y-%m') = ?", [$thisMonth])['count']
    ];
    
    return [
        'message' => "Sales Summary:\n• Today's Sales: ₹" . number_format($sales['today_sales']) . "\n• Monthly Sales: ₹" . number_format($sales['monthly_sales']) . "\n• Total Invoices: {$sales['total_invoices']}",
        'type' => 'sales',
        'data' => $sales
    ];
}

function getServiceInfo($db, $userRole) {
    $today = date('Y-m-d');
    
    if ($userRole === 'admin') {
        $services = [
            'pending' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE status = 'pending'")['count'],
            'assigned' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE status = 'assigned'")['count'],
            'in_progress' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE status = 'in_progress'")['count'],
            'completed_today' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE status = 'completed' AND DATE(completed_at) = ?", [$today])['count']
        ];
        
        return [
            'message' => "Service Status:\n• Pending: {$services['pending']}\n• Assigned: {$services['assigned']}\n• In Progress: {$services['in_progress']}\n• Completed Today: {$services['completed_today']}",
            'type' => 'services',
            'data' => $services
        ];
    } elseif ($userRole === 'customer') {
        $customer = $db->fetchOne("SELECT id FROM customers WHERE user_id = ?", [$_SESSION['user_id']]);
        if ($customer) {
            $services = [
                'pending' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE customer_id = ? AND status = 'pending'", [$customer['id']])['count'],
                'in_progress' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE customer_id = ? AND status IN ('assigned', 'in_progress')", [$customer['id']])['count'],
                'completed' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE customer_id = ? AND status = 'completed'", [$customer['id']])['count']
            ];
            
            return [
                'message' => "Your Service Status:\n• Pending: {$services['pending']}\n• In Progress: {$services['in_progress']}\n• Completed: {$services['completed']}",
                'type' => 'services',
                'data' => $services
            ];
        }
    } elseif ($userRole === 'technician') {
        $technician = $db->fetchOne("SELECT id FROM technicians WHERE user_id = ?", [$_SESSION['user_id']]);
        if ($technician) {
            $services = [
                'assigned' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE assigned_technician_id = ? AND status = 'assigned'", [$technician['id']])['count'],
                'in_progress' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE assigned_technician_id = ? AND status = 'in_progress'", [$technician['id']])['count'],
                'completed_today' => $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE assigned_technician_id = ? AND status = 'completed' AND DATE(completed_at) = ?", [$technician['id'], $today])['count']
            ];
            
            return [
                'message' => "Your Task Status:\n• Assigned: {$services['assigned']}\n• In Progress: {$services['in_progress']}\n• Completed Today: {$services['completed_today']}",
                'type' => 'services',
                'data' => $services
            ];
        }
    }
    
    return [
        'message' => "I couldn't retrieve service information at the moment. Please try again later.",
        'type' => 'error'
    ];
}

function getInventoryInfo($db, $userRole) {
    if ($userRole !== 'admin') {
        return [
            'message' => "Inventory information is only available to administrators.",
            'type' => 'restricted'
        ];
    }
    
    $inventory = [
        'total_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products")['count'],
        'low_stock' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level")['count'],
        'out_of_stock' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE stock_quantity = 0")['count']
    ];
    
    return [
        'message' => "Inventory Status:\n• Total Products: {$inventory['total_products']}\n• Low Stock Items: {$inventory['low_stock']}\n• Out of Stock: {$inventory['out_of_stock']}",
        'type' => 'inventory',
        'data' => $inventory
    ];
}

function getFinancialInfo($db, $userRole) {
    if ($userRole === 'admin') {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m');
        
        $financial = [
            'today_revenue' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM invoices WHERE DATE(created_at) = ?", [$today])['total'],
            'monthly_revenue' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM invoices WHERE DATE_FORMAT(created_at, '%Y-%m') = ?", [$thisMonth])['total'],
            'pending_payments' => $db->fetchOne("SELECT COALESCE(SUM(balance_amount), 0) as total FROM invoices WHERE status != 'paid'")['total']
        ];
        
        return [
            'message' => "Financial Summary:\n• Today's Revenue: ₹" . number_format($financial['today_revenue']) . "\n• Monthly Revenue: ₹" . number_format($financial['monthly_revenue']) . "\n• Pending Payments: ₹" . number_format($financial['pending_payments']),
            'type' => 'financial',
            'data' => $financial
        ];
    } elseif ($userRole === 'technician') {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m');
        
        $financial = [
            'today_income' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM daily_cashbook WHERE user_id = ? AND transaction_type = 'income' AND DATE(transaction_date) = ?", [$_SESSION['user_id'], $today])['total'],
            'monthly_income' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM daily_cashbook WHERE user_id = ? AND transaction_type = 'income' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$_SESSION['user_id'], $thisMonth])['total'],
            'today_expenses' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM daily_cashbook WHERE user_id = ? AND transaction_type = 'expense' AND DATE(transaction_date) = ?", [$_SESSION['user_id'], $today])['total']
        ];
        
        return [
            'message' => "Your Financial Summary:\n• Today's Income: ₹" . number_format($financial['today_income']) . "\n• Monthly Income: ₹" . number_format($financial['monthly_income']) . "\n• Today's Expenses: ₹" . number_format($financial['today_expenses']),
            'type' => 'financial',
            'data' => $financial
        ];
    }
    
    return [
        'message' => "Financial information is not available for your role.",
        'type' => 'restricted'
    ];
}

function getReportInfo($db, $userRole) {
    if ($userRole !== 'admin') {
        return [
            'message' => "Reports are only available to administrators.",
            'type' => 'restricted'
        ];
    }
    
    return [
        'message' => "Available Reports:\n• Sales Reports - Revenue and invoice analytics\n• Financial Reports - P&L and cash flow\n• Service Reports - Service performance metrics\n• Customer Reports - Customer analytics\n• Inventory Reports - Stock and product analysis\n\nYou can access these reports from the Reports section in your admin panel.",
        'type' => 'reports',
        'suggestions' => [
            'Generate sales report',
            'Show financial summary',
            'View service analytics',
            'Export customer data'
        ]
    ];
}
?>