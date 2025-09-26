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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $message = $input['message'] ?? '';
    $context = $input['context'] ?? 'general';
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message is required']);
        exit();
    }
    
    // Process AI request based on context and message
    $response = processAIRequest($message, $context, $_SESSION['user_role']);
    
    // Log AI interaction
    logActivity($_SESSION['user_id'], 'ai_chat', "AI Query: " . $message);
    
    echo json_encode([
        'success' => true,
        'message' => $response
    ]);
    
} catch (Exception $e) {
    error_log('AI chat error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

function processAIRequest($message, $context, $userRole) {
    $db = Database::getInstance();
    
    // Convert message to lowercase for easier processing
    $lowerMessage = strtolower($message);
    
    // Handle different types of queries
    if (strpos($lowerMessage, 'dashboard') !== false || strpos($lowerMessage, 'stats') !== false) {
        return getDashboardInsights($db, $userRole);
    }
    
    if (strpos($lowerMessage, 'customer') !== false) {
        return getCustomerInsights($db, $userRole);
    }
    
    if (strpos($lowerMessage, 'service') !== false || strpos($lowerMessage, 'task') !== false) {
        return getServiceInsights($db, $userRole);
    }
    
    if (strpos($lowerMessage, 'revenue') !== false || strpos($lowerMessage, 'sales') !== false) {
        return getRevenueInsights($db, $userRole);
    }
    
    if (strpos($lowerMessage, 'help') !== false) {
        return getHelpMessage($userRole);
    }
    
    if (strpos($lowerMessage, 'weather') !== false) {
        return getWeatherInfo();
    }
    
    if (strpos($lowerMessage, 'time') !== false || strpos($lowerMessage, 'date') !== false) {
        return getTimeInfo();
    }
    
    // Default response with suggestions
    return getDefaultResponse($userRole);
}

function getDashboardInsights($db, $userRole) {
    $insights = [];
    
    if ($userRole === 'admin') {
        // Admin dashboard insights
        $totalCustomers = $db->fetchOne("SELECT COUNT(*) as count FROM customers")['count'];
        $pendingServices = $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE status = 'pending'")['count'];
        $monthlyRevenue = $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM invoices WHERE status = 'paid' AND MONTH(created_at) = MONTH(CURDATE())")['revenue'];
        
        $insights[] = "You have {$totalCustomers} total customers in your system.";
        $insights[] = "There are {$pendingServices} pending service requests that need attention.";
        $insights[] = "Monthly revenue so far: ₹" . number_format($monthlyRevenue);
        
        if ($pendingServices > 5) {
            $insights[] = "⚠️ High number of pending services. Consider assigning more technicians.";
        }
        
        if ($monthlyRevenue > 100000) {
            $insights[] = "🎉 Great revenue this month! Keep up the excellent work.";
        }
        
    } elseif ($userRole === 'customer') {
        // Customer dashboard insights
        $customerId = $_SESSION['customer_id'] ?? null;
        if ($customerId) {
            $totalServices = $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE customer_id = ?", [$customerId])['count'];
            $pendingServices = $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE customer_id = ? AND status IN ('pending', 'assigned', 'in_progress')", [$customerId])['count'];
            
            $insights[] = "You have {$totalServices} total service requests.";
            $insights[] = "You have {$pendingServices} active service requests.";
            
            if ($pendingServices === 0) {
                $insights[] = "✅ All your services are completed! Request a new service if needed.";
            }
        }
        
    } elseif ($userRole === 'technician') {
        // Technician dashboard insights
        $technicianId = $_SESSION['technician_id'] ?? null;
        if ($technicianId) {
            $todayTasks = $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE assigned_technician_id = ? AND DATE(created_at) = CURDATE()", [$technicianId])['count'];
            $pendingTasks = $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE assigned_technician_id = ? AND status IN ('assigned', 'in_progress')", [$technicianId])['count'];
            $avgRating = $db->fetchOne("SELECT AVG(customer_rating) as rating FROM service_requests WHERE assigned_technician_id = ? AND customer_rating IS NOT NULL", [$technicianId])['rating'] ?? 0;
            
            $insights[] = "You have {$todayTasks} tasks assigned today.";
            $insights[] = "You have {$pendingTasks} pending tasks to complete.";
            $insights[] = "Your average rating: " . number_format($avgRating, 1) . "/5";
            
            if ($avgRating >= 4.5) {
                $insights[] = "🌟 Excellent rating! Keep up the great work.";
            }
        }
    }
    
    return implode("\n\n", $insights);
}

function getCustomerInsights($db, $userRole) {
    if ($userRole !== 'admin') {
        return "Customer insights are only available to administrators.";
    }
    
    $insights = [];
    
    // Top customers by revenue
    $topCustomers = $db->fetchAll("
        SELECT c.company_name, c.contact_person, COALESCE(SUM(i.total_amount), 0) as total_revenue
        FROM customers c 
        LEFT JOIN invoices i ON c.id = i.customer_id AND i.status = 'paid'
        GROUP BY c.id 
        ORDER BY total_revenue DESC 
        LIMIT 3
    ");
    
    $insights[] = "Top customers by revenue:";
    foreach ($topCustomers as $customer) {
        $insights[] = "• " . ($customer['company_name'] ?: $customer['contact_person']) . " - ₹" . number_format($customer['total_revenue']);
    }
    
    // Customer satisfaction
    $avgRating = $db->fetchOne("SELECT AVG(customer_rating) as rating FROM service_requests WHERE customer_rating IS NOT NULL")['rating'] ?? 0;
    $insights[] = "\nAverage customer satisfaction: " . number_format($avgRating, 1) . "/5";
    
    return implode("\n", $insights);
}

function getServiceInsights($db, $userRole) {
    $insights = [];
    
    if ($userRole === 'admin') {
        $totalServices = $db->fetchOne("SELECT COUNT(*) as count FROM service_requests")['count'];
        $completedServices = $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE status = 'completed'")['count'];
        $completionRate = $totalServices > 0 ? round(($completedServices / $totalServices) * 100, 1) : 0;
        
        $insights[] = "Total services: {$totalServices}";
        $insights[] = "Completed services: {$completedServices}";
        $insights[] = "Completion rate: {$completionRate}%";
        
        if ($completionRate < 80) {
            $insights[] = "⚠️ Service completion rate is below 80%. Consider reviewing processes.";
        }
        
    } elseif ($userRole === 'technician') {
        $technicianId = $_SESSION['technician_id'] ?? null;
        if ($technicianId) {
            $monthlyCompleted = $db->fetchOne("
                SELECT COUNT(*) as count 
                FROM service_requests 
                WHERE assigned_technician_id = ? 
                AND status = 'completed' 
                AND MONTH(created_at) = MONTH(CURDATE())
            ", [$technicianId])['count'];
            
            $insights[] = "Services completed this month: {$monthlyCompleted}";
            
            if ($monthlyCompleted >= 20) {
                $insights[] = "🎉 Great performance this month!";
            }
        }
    }
    
    return implode("\n", $insights);
}

function getRevenueInsights($db, $userRole) {
    if ($userRole !== 'admin') {
        return "Revenue insights are only available to administrators.";
    }
    
    $insights = [];
    
    $monthlyRevenue = $db->fetchOne("
        SELECT COALESCE(SUM(total_amount), 0) as revenue 
        FROM invoices 
        WHERE status = 'paid' AND MONTH(created_at) = MONTH(CURDATE())
    ")['revenue'];
    
    $lastMonthRevenue = $db->fetchOne("
        SELECT COALESCE(SUM(total_amount), 0) as revenue 
        FROM invoices 
        WHERE status = 'paid' AND MONTH(created_at) = MONTH(CURDATE()) - 1
    ")['revenue'];
    
    $insights[] = "Current month revenue: ₹" . number_format($monthlyRevenue);
    $insights[] = "Last month revenue: ₹" . number_format($lastMonthRevenue);
    
    if ($lastMonthRevenue > 0) {
        $growth = (($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100;
        $insights[] = "Growth: " . ($growth >= 0 ? "+" : "") . number_format($growth, 1) . "%";
    }
    
    return implode("\n", $insights);
}

function getHelpMessage($userRole) {
    $helpMessages = [
        'admin' => "I can help you with:\n• Dashboard insights and statistics\n• Customer information and analytics\n• Service request management\n• Revenue and sales data\n• System performance metrics\n\nJust ask me about any of these topics!",
        'customer' => "I can help you with:\n• Your service request status\n• Account information\n• Service history\n• Payment details\n• General inquiries\n\nWhat would you like to know?",
        'technician' => "I can help you with:\n• Your task assignments\n• Performance metrics\n• Customer information\n• Service guidelines\n• Technical support\n\nHow can I assist you today?"
    ];
    
    return $helpMessages[$userRole] ?? "I'm here to help! Ask me about your dashboard, services, or any other questions.";
}

function getWeatherInfo() {
    // Simple weather response (in production, integrate with weather API)
    return "I don't have access to real-time weather data, but I recommend checking your local weather app for current conditions. Weather can affect service delivery, so plan accordingly!";
}

function getTimeInfo() {
    $currentTime = date('d M Y, H:i:s');
    $timezone = date_default_timezone_get();
    
    return "Current time: {$currentTime} ({$timezone})";
}

function getDefaultResponse($userRole) {
    $responses = [
        'admin' => "I'm your AI assistant for the Water Purifier ERP system. I can help you with dashboard insights, customer analytics, service management, and revenue reports. What would you like to know?",
        'customer' => "I'm here to help you with your water purifier services. I can provide information about your service requests, account details, and answer general questions. How can I assist you?",
        'technician' => "I'm your AI assistant for service management. I can help you with task information, customer details, performance metrics, and service guidelines. What do you need help with?"
    ];
    
    return $responses[$userRole] ?? "I'm here to help! Ask me anything about the system or your work.";
}
?>