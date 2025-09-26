<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$db = Database::getInstance();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_general_settings':
            $result = updateGeneralSettings($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'update_email_settings':
            $result = updateEmailSettings($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'update_sms_settings':
            $result = updateSMSSettings($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'update_firebase_settings':
            $result = updateFirebaseSettings($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'test_email':
            $result = testEmail($_POST);
            echo json_encode($result);
            exit();
            
        case 'test_sms':
            $result = testSMS($_POST);
            echo json_encode($result);
            exit();
    }
}

// Get current settings
$settings = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
$settingsArray = [];
foreach ($settings as $setting) {
    $settingsArray[$setting['setting_key']] = $setting['setting_value'];
}

function updateGeneralSettings($db, $data) {
    try {
        $settings = [
            'company_name' => $data['company_name'],
            'company_address' => $data['company_address'],
            'company_phone' => $data['company_phone'],
            'company_email' => $data['company_email'],
            'company_website' => $data['company_website'],
            'timezone' => $data['timezone'],
            'currency' => $data['currency'],
            'tax_rate' => $data['tax_rate'],
            'invoice_prefix' => $data['invoice_prefix'],
            'invoice_number_start' => $data['invoice_number_start'],
            'google_maps_api_key' => $data['google_maps_api_key'] ?? ''
        ];
        
        foreach ($settings as $key => $value) {
            $db->execute("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ", [$key, $value, $value]);
        }
        
        logActivity($_SESSION['user_id'], 'settings_updated', 'Updated general settings');
        
        return ['success' => true, 'message' => 'General settings updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating settings: ' . $e->getMessage()];
    }
}

function updateEmailSettings($db, $data) {
    try {
        $settings = [
            'smtp_host' => $data['smtp_host'],
            'smtp_port' => $data['smtp_port'],
            'smtp_username' => $data['smtp_username'],
            'smtp_password' => $data['smtp_password'],
            'smtp_encryption' => $data['smtp_encryption'],
            'from_email' => $data['from_email'],
            'from_name' => $data['from_name']
        ];
        
        foreach ($settings as $key => $value) {
            $db->execute("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ", [$key, $value, $value]);
        }
        
        logActivity($_SESSION['user_id'], 'settings_updated', 'Updated email settings');
        
        return ['success' => true, 'message' => 'Email settings updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating email settings: ' . $e->getMessage()];
    }
}

function updateSMSSettings($db, $data) {
    try {
        $settings = [
            'sms_provider' => $data['sms_provider'],
            'sms_api_key' => $data['sms_api_key'],
            'sms_api_secret' => $data['sms_api_secret'],
            'sms_sender_id' => $data['sms_sender_id']
        ];
        
        foreach ($settings as $key => $value) {
            $db->execute("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ", [$key, $value, $value]);
        }
        
        logActivity($_SESSION['user_id'], 'settings_updated', 'Updated SMS settings');
        
        return ['success' => true, 'message' => 'SMS settings updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating SMS settings: ' . $e->getMessage()];
    }
}

function updateFirebaseSettings($db, $data) {
    try {
        $settings = [
            'firebase_api_key' => $data['firebase_api_key'],
            'firebase_auth_domain' => $data['firebase_auth_domain'],
            'firebase_database_url' => $data['firebase_database_url'],
            'firebase_project_id' => $data['firebase_project_id'],
            'firebase_storage_bucket' => $data['firebase_storage_bucket'],
            'firebase_messaging_sender_id' => $data['firebase_messaging_sender_id'],
            'firebase_app_id' => $data['firebase_app_id']
        ];
        
        foreach ($settings as $key => $value) {
            $db->execute("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ", [$key, $value, $value]);
        }
        
        logActivity($_SESSION['user_id'], 'settings_updated', 'Updated Firebase settings');
        
        return ['success' => true, 'message' => 'Firebase settings updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating Firebase settings: ' . $e->getMessage()];
    }
}

function testEmail($data) {
    try {
        $to = $data['test_email'];
        $subject = "Test Email - Water Purifier ERP";
        $message = "This is a test email from Water Purifier ERP System. If you receive this email, your email configuration is working correctly.";
        
        $emailSent = sendEmail($to, $subject, $message);
        
        if ($emailSent) {
            return ['success' => true, 'message' => 'Test email sent successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to send test email'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error sending test email: ' . $e->getMessage()];
    }
}

function testSMS($data) {
    try {
        $phone = $data['test_phone'];
        $message = "Test SMS from Water Purifier ERP System. Your SMS configuration is working correctly.";
        
        $smsSent = sendSMS($phone, $message);
        
        if ($smsSent) {
            return ['success' => true, 'message' => 'Test SMS sent successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to send test SMS'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error sending test SMS: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Water Purifier ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tint me-2"></i>Water Purifier ERP
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-cog me-2"></i>System Settings
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Settings Tabs -->
                        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                                    <i class="fas fa-building me-1"></i>General
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab">
                                    <i class="fas fa-envelope me-1"></i>Email
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="sms-tab" data-bs-toggle="tab" data-bs-target="#sms" type="button" role="tab">
                                    <i class="fas fa-sms me-1"></i>SMS
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="firebase-tab" data-bs-toggle="tab" data-bs-target="#firebase" type="button" role="tab">
                                    <i class="fas fa-fire me-1"></i>Firebase
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content mt-4" id="settingsTabContent">
                            <!-- General Settings -->
                            <div class="tab-pane fade show active" id="general" role="tabpanel">
                                <form id="generalSettingsForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="company_name" class="form-label">Company Name *</label>
                                            <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($settingsArray['company_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="company_phone" class="form-label">Company Phone</label>
                                            <input type="tel" class="form-control" id="company_phone" name="company_phone" value="<?php echo htmlspecialchars($settingsArray['company_phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="company_address" class="form-label">Company Address</label>
                                        <textarea class="form-control" id="company_address" name="company_address" rows="3"><?php echo htmlspecialchars($settingsArray['company_address'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="company_email" class="form-label">Company Email</label>
                                            <input type="email" class="form-control" id="company_email" name="company_email" value="<?php echo htmlspecialchars($settingsArray['company_email'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="company_website" class="form-label">Company Website</label>
                                            <input type="url" class="form-control" id="company_website" name="company_website" value="<?php echo htmlspecialchars($settingsArray['company_website'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="timezone" class="form-label">Timezone</label>
                                            <select class="form-select" id="timezone" name="timezone">
                                                <option value="Asia/Kolkata" <?php echo ($settingsArray['timezone'] ?? '') === 'Asia/Kolkata' ? 'selected' : ''; ?>>Asia/Kolkata</option>
                                                <option value="UTC" <?php echo ($settingsArray['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="currency" class="form-label">Currency</label>
                                            <select class="form-select" id="currency" name="currency">
                                                <option value="INR" <?php echo ($settingsArray['currency'] ?? '') === 'INR' ? 'selected' : ''; ?>>INR (₹)</option>
                                                <option value="USD" <?php echo ($settingsArray['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                            <input type="number" class="form-control" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars($settingsArray['tax_rate'] ?? '18'); ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="invoice_prefix" class="form-label">Invoice Prefix</label>
                                            <input type="text" class="form-control" id="invoice_prefix" name="invoice_prefix" value="<?php echo htmlspecialchars($settingsArray['invoice_prefix'] ?? 'INV'); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="invoice_number_start" class="form-label">Invoice Number Start</label>
                                            <input type="number" class="form-control" id="invoice_number_start" name="invoice_number_start" min="1" value="<?php echo htmlspecialchars($settingsArray['invoice_number_start'] ?? '1001'); ?>">
                                        </div>
                                    </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="google_maps_api_key" class="form-label">Google Maps API Key</label>
                                    <input type="text" class="form-control" id="google_maps_api_key" name="google_maps_api_key" value="<?php echo htmlspecialchars($settingsArray['google_maps_api_key'] ?? ''); ?>" placeholder="AIza...">
                                </div>
                            </div>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Save General Settings
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Email Settings -->
                            <div class="tab-pane fade" id="email" role="tabpanel">
                                <form id="emailSettingsForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_host" class="form-label">SMTP Host *</label>
                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settingsArray['smtp_host'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_port" class="form-label">SMTP Port *</label>
                                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($settingsArray['smtp_port'] ?? '587'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_username" class="form-label">SMTP Username *</label>
                                            <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($settingsArray['smtp_username'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_password" class="form-label">SMTP Password *</label>
                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($settingsArray['smtp_password'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_encryption" class="form-label">SMTP Encryption</label>
                                            <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                                <option value="tls" <?php echo ($settingsArray['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                <option value="ssl" <?php echo ($settingsArray['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                <option value="none" <?php echo ($settingsArray['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="from_email" class="form-label">From Email *</label>
                                            <input type="email" class="form-control" id="from_email" name="from_email" value="<?php echo htmlspecialchars($settingsArray['from_email'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="from_name" class="form-label">From Name</label>
                                        <input type="text" class="form-control" id="from_name" name="from_name" value="<?php echo htmlspecialchars($settingsArray['from_name'] ?? 'Water Purifier ERP'); ?>">
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i>Save Email Settings
                                            </button>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <button type="button" class="btn btn-outline-info" onclick="testEmailConnection()">
                                                <i class="fas fa-paper-plane me-1"></i>Test Email
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- SMS Settings -->
                            <div class="tab-pane fade" id="sms" role="tabpanel">
                                <form id="smsSettingsForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="sms_provider" class="form-label">SMS Provider</label>
                                            <select class="form-select" id="sms_provider" name="sms_provider">
                                                <option value="twilio" <?php echo ($settingsArray['sms_provider'] ?? '') === 'twilio' ? 'selected' : ''; ?>>Twilio</option>
                                                <option value="textlocal" <?php echo ($settingsArray['sms_provider'] ?? '') === 'textlocal' ? 'selected' : ''; ?>>TextLocal</option>
                                                <option value="msg91" <?php echo ($settingsArray['sms_provider'] ?? '') === 'msg91' ? 'selected' : ''; ?>>MSG91</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="sms_sender_id" class="form-label">Sender ID</label>
                                            <input type="text" class="form-control" id="sms_sender_id" name="sms_sender_id" value="<?php echo htmlspecialchars($settingsArray['sms_sender_id'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="sms_api_key" class="form-label">API Key</label>
                                            <input type="text" class="form-control" id="sms_api_key" name="sms_api_key" value="<?php echo htmlspecialchars($settingsArray['sms_api_key'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="sms_api_secret" class="form-label">API Secret</label>
                                            <input type="password" class="form-control" id="sms_api_secret" name="sms_api_secret" value="<?php echo htmlspecialchars($settingsArray['sms_api_secret'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i>Save SMS Settings
                                            </button>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <button type="button" class="btn btn-outline-info" onclick="testSMSConnection()">
                                                <i class="fas fa-sms me-1"></i>Test SMS
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Firebase Settings -->
                            <div class="tab-pane fade" id="firebase" role="tabpanel">
                                <form id="firebaseSettingsForm">
                                    <div class="mb-3">
                                        <label for="firebase_api_key" class="form-label">Firebase API Key</label>
                                        <input type="text" class="form-control" id="firebase_api_key" name="firebase_api_key" value="<?php echo htmlspecialchars($settingsArray['firebase_api_key'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="firebase_auth_domain" class="form-label">Auth Domain</label>
                                        <input type="text" class="form-control" id="firebase_auth_domain" name="firebase_auth_domain" value="<?php echo htmlspecialchars($settingsArray['firebase_auth_domain'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="firebase_database_url" class="form-label">Database URL</label>
                                        <input type="url" class="form-control" id="firebase_database_url" name="firebase_database_url" value="<?php echo htmlspecialchars($settingsArray['firebase_database_url'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="firebase_project_id" class="form-label">Project ID</label>
                                        <input type="text" class="form-control" id="firebase_project_id" name="firebase_project_id" value="<?php echo htmlspecialchars($settingsArray['firebase_project_id'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="firebase_storage_bucket" class="form-label">Storage Bucket</label>
                                        <input type="text" class="form-control" id="firebase_storage_bucket" name="firebase_storage_bucket" value="<?php echo htmlspecialchars($settingsArray['firebase_storage_bucket'] ?? ''); ?>">
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="firebase_messaging_sender_id" class="form-label">Messaging Sender ID</label>
                                            <input type="text" class="form-control" id="firebase_messaging_sender_id" name="firebase_messaging_sender_id" value="<?php echo htmlspecialchars($settingsArray['firebase_messaging_sender_id'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="firebase_app_id" class="form-label">App ID</label>
                                            <input type="text" class="form-control" id="firebase_app_id" name="firebase_app_id" value="<?php echo htmlspecialchars($settingsArray['firebase_app_id'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Save Firebase Settings
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

    <!-- Test Email Modal -->
    <div class="modal fade" id="testEmailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-envelope me-2"></i>Test Email
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="testEmailForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="test_email" class="form-label">Test Email Address</label>
                            <input type="email" class="form-control" id="test_email" name="test_email" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-paper-plane me-1"></i>Send Test Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Test SMS Modal -->
    <div class="modal fade" id="testSMSModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-sms me-2"></i>Test SMS
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="testSMSForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="test_phone" class="form-label">Test Phone Number</label>
                            <input type="tel" class="form-control" id="test_phone" name="test_phone" placeholder="+91XXXXXXXXXX" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-sms me-1"></i>Send Test SMS
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/settings.js"></script>
</body>
</html>