<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'admin') {
    header('Location: ../landing/login/login.php');
    exit;
}

// Include database connection
include __DIR__ . '/../database/database.php';

// Handle settings updates
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Business Information Update
    if ($action === 'update_business_info') {
        $businessName = $conn->real_escape_string($_POST['business_name']);
        $businessEmail = $conn->real_escape_string($_POST['business_email']);
        $businessPhone = $conn->real_escape_string($_POST['business_phone']);
        $businessAddress = $conn->real_escape_string($_POST['business_address']);
        
        $settings = [
            'business_name' => $businessName,
            'business_email' => $businessEmail,
            'business_phone' => $businessPhone,
            'business_address' => $businessAddress
        ];
        
        foreach ($settings as $key => $value) {
            // FIXED: Using 'system_settings' table instead of 'settings'
            $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES ('$key', '$value')
                    ON DUPLICATE KEY UPDATE setting_value = '$value'";
            $conn->query($sql);
        }
        
        $message = 'Business information updated successfully!';
        $messageType = 'success';
    }
    
    // Change Password
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        $email = $_SESSION['userEmail'];
        // FIXED: Using 'password_hash' column
        $sql = "SELECT password_hash FROM users WHERE email = '$email' LIMIT 1";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // FIXED: Verifying against 'password_hash'
            if (password_verify($currentPassword, $user['password_hash'])) {
                if ($newPassword === $confirmPassword) {
                    if (strlen($newPassword) >= 6) {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        // FIXED: Updating 'password_hash'
                        $updateSql = "UPDATE users SET password_hash = '$hashedPassword' WHERE email = '$email'";
                        
                        if ($conn->query($updateSql)) {
                            $message = 'Password changed successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Error updating password!';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'Password must be at least 6 characters long!';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'New passwords do not match!';
                    $messageType = 'error';
                }
            } else {
                $message = 'Current password is incorrect!';
                $messageType = 'error';
            }
        }
    }
    
    // Operating Hours Update
    if ($action === 'update_hours') {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($days as $day) {
            $open = $conn->real_escape_string($_POST[$day . '_open'] ?? '');
            $close = $conn->real_escape_string($_POST[$day . '_close'] ?? '');
            $closed = isset($_POST[$day . '_closed']) ? 1 : 0;
            
            $settings = [
                $day . '_open' => $open,
                $day . '_close' => $close,
                $day . '_closed' => $closed
            ];
            
            foreach ($settings as $key => $value) {
                // FIXED: Using 'system_settings'
                $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES ('$key', '$value')
                        ON DUPLICATE KEY UPDATE setting_value = '$value'";
                $conn->query($sql);
            }
        }
        
        $message = 'Operating hours updated successfully!';
        $messageType = 'success';
    }
    
    // Notification Settings
    if ($action === 'update_notifications') {
        $notifSettings = [
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
            'booking_confirm' => isset($_POST['booking_confirm']) ? 1 : 0,
            'booking_reminder' => isset($_POST['booking_reminder']) ? 1 : 0,
            'payment_confirm' => isset($_POST['payment_confirm']) ? 1 : 0
        ];
        
        foreach ($notifSettings as $key => $value) {
            // FIXED: Using 'system_settings'
            $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES ('$key', '$value')
                    ON DUPLICATE KEY UPDATE setting_value = '$value'";
            $conn->query($sql);
        }
        
        $message = 'Notification settings updated successfully!';
        $messageType = 'success';
    }
    
    // System Settings
    if ($action === 'update_system') {
        $systemSettings = [
            'timezone' => $conn->real_escape_string($_POST['timezone']),
            'currency' => $conn->real_escape_string($_POST['currency']),
            'date_format' => $conn->real_escape_string($_POST['date_format']),
            'booking_buffer' => $conn->real_escape_string($_POST['booking_buffer'])
        ];
        
        foreach ($systemSettings as $key => $value) {
            // FIXED: Using 'system_settings'
            $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES ('$key', '$value')
                    ON DUPLICATE KEY UPDATE setting_value = '$value'";
            $conn->query($sql);
        }
        
        $message = 'System settings updated successfully!';
        $messageType = 'success';
    }

    // Export all data
    if ($action === 'export_data') {
        // FIXED: Added 'employees' and 'system_settings', removed incorrect names
        $exportTables = ['bookings', 'customers', 'services', 'employees', 'users', 'system_settings', 'reviews', 'payments'];
        $export = [];
        
        // Get DB name dynamically
        $dbRes = $conn->query("SELECT DATABASE() AS dbname");
        $dbName = $dbRes->fetch_assoc()['dbname'];

        foreach ($exportTables as $t) {
            // Check if table exists
            $tblRes = $conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$t'");
            if ($tblRes && $tblRes->num_rows > 0) {
                $rows = [];
                $res = $conn->query("SELECT * FROM `$t`");
                if ($res) {
                    while ($r = $res->fetch_assoc()) {
                        $rows[] = $r;
                    }
                }
                $export[$t] = $rows;
            }
        }

        // Send JSON download
        $filename = 'smartwash_export_' . date('Ymd_His') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($export, JSON_PRETTY_PRINT);
        exit;
    }

    // Deactivate account
    if ($action === 'deactivate_account') {
        $email = $_SESSION['userEmail'] ?? '';
        if ($email) {
            // FIXED: Using 'status' column based on your schema
            $upd = "UPDATE users SET status = 'inactive' WHERE email = '$email'";
            if ($conn->query($upd)) {
                session_unset();
                session_destroy();
                header('Location: ../landing/index.php');
                exit;
            } else {
                $message = 'Error deactivating account: ' . $conn->error;
                $messageType = 'error';
            }
        }
    }
}

// Fetch current settings (Helper function fixed)
function getSetting($conn, $key, $default = '') {
    // FIXED: Using 'system_settings'
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = '$key' LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    return $default;
}

// Get current admin info
$adminEmail = $_SESSION['userEmail'];
$adminQuery = "SELECT * FROM users WHERE email = '$adminEmail' LIMIT 1";
$adminResult = $conn->query($adminQuery);
$adminInfo = $adminResult ? $adminResult->fetch_assoc() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Settings</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 999;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            padding: 0 1.5rem;
            margin-bottom: 2rem;
        }

        .menu-item {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            text-decoration: none;
            color: white;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left-color: white;
        }

        .menu-icon {
            font-size: 1.5rem;
        }

        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 2rem;
            width: calc(100% - 260px);
        }

        .header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 1.8rem;
            color: #333;
        }

        .settings-nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding: 0.5rem 0;
        }

        .settings-tab {
            padding: 0.8rem 1.5rem;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .settings-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        .settings-tab:hover {
            border-color: #667eea;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: none;
        }

        .card.active {
            display: block;
        }

        .card-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }

        .card-subtitle {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.3rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
        }

        .btn-primary {
            padding: 0.8rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            padding: 0.8rem 1.5rem;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .btn-danger {
            padding: 0.8rem 1.5rem;
            background: white;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-danger:hover {
            background: #e74c3c;
            color: white;
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            animation: slideIn 0.3s ease;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .hours-grid {
            display: grid;
            gap: 1rem;
        }

        .hours-row {
            display: grid;
            grid-template-columns: 150px 1fr 1fr 100px;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .day-label {
            font-weight: 600;
            color: #333;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }

        .info-box {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            margin-bottom: 1.5rem;
        }

        .info-box-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.3rem;
        }

        .info-box-text {
            font-size: 0.9rem;
            color: #666;
        }

        .mobile-menu-btn {
            display: none;
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            z-index: 1000;
        }

        @media (max-width: 1024px) {
            .form-row { grid-template-columns: 1fr; }
            .hours-row { grid-template-columns: 1fr; }
            table { font-size: 0.9rem; }
            th, td { padding: 0.75rem; }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1001;
                width: 260px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }

            .header { padding: 1rem; }
            .header h1 { font-size: 1.5rem; }
            .form-row {
                grid-template-columns: 1fr;
            }

            .hours-row {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .settings-nav {
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .mobile-menu-btn {
                display: block;
            }

            .card { padding: 1rem; }
            table { font-size: 0.8rem; }
            th, td { padding: 0.5rem; }
            .btn-primary { padding: 0.5rem 1rem; font-size: 0.9rem; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 0.5rem; }
            .header h1 { font-size: 1.2rem; }
            .card { padding: 0.75rem; }
            table { font-size: 0.7rem; }
            th, td { padding: 0.4rem; }
            .settings-nav { gap: 0.25rem; }
        }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <div class="menu-item" onclick="window.location.href='index.php'">
                <span>Dashboard</span>
            </div>
            <div class="menu-item" onclick="window.location.href='bookings.php'">
                <span>Bookings</span>
            </div>
            <div class="menu-item" onclick="window.location.href='customers.php'">
                <span>Customers</span>
            </div>
            <div class="menu-item" onclick="window.location.href='services.php'">
                <span>Services</span>
            </div>
            <div class="menu-item" onclick="window.location.href='staff.php'">
                <span>Staff</span>
            </div>
            <div class="menu-item" onclick="window.location.href='reports.php'">
                <span>Reports</span>
            </div>
            <div class="menu-item active" onclick="window.location.href='settings.php'">
                <span>Settings</span>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="header">
            <div>
                <h1>Settings</h1>
                <p style="color: #666; margin-top: 0.3rem;">Manage your system preferences and configuration</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>" id="alertMessage">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="settings-nav">
            <div class="settings-tab active" onclick="showTab(event,'profile')">👤 Profile</div>
            <div class="settings-tab" onclick="showTab(event,'business')">🏢 Business Info</div>
            <div class="settings-tab" onclick="showTab(event,'hours')">🕒 Operating Hours</div>
            <div class="settings-tab" onclick="showTab(event,'notifications')">🔔 Notifications</div>
            <div class="settings-tab" onclick="showTab(event,'system')">⚙️ System</div>
            <div class="settings-tab" onclick="showTab(event,'security')">🔒 Security</div>
        </div>

        <div class="card active" id="profile">
            <div class="card-header">
                <h2 class="card-title">Profile Settings</h2>
                <p class="card-subtitle">Manage your account information</p>
            </div>
            
            <div class="profile-avatar">
                <?php echo strtoupper(substr($adminInfo['first_name'] ?? 'A', 0, 1)); ?>
            </div>
            
            <div class="info-box">
                <div class="info-box-title">Administrator Account</div>
                <div class="info-box-text">
                    <strong>Name:</strong> <?php echo htmlspecialchars(($adminInfo['first_name'] ?? '') . ' ' . ($adminInfo['last_name'] ?? 'Admin')); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($adminInfo['email'] ?? $_SESSION['userEmail']); ?><br>
                    <strong>Role:</strong> <?php echo ucfirst($_SESSION['userRole']); ?><br>
                    <strong>Account Created:</strong> <?php echo date('F d, Y', strtotime($adminInfo['created_at'] ?? 'now')); ?>
                </div>
            </div>
        </div>

        <div class="card" id="business">
            <div class="card-header">
                <h2 class="card-title">Business Information</h2>
                <p class="card-subtitle">Update your business details</p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_business_info">
                
                <div class="form-group">
                    <label for="business_name">Business Name</label>
                    <input type="text" name="business_name" id="business_name" 
                           value="<?php echo htmlspecialchars(getSetting($conn, 'business_name', 'SmartWash Car Wash')); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="business_email">Business Email</label>
                        <input type="email" name="business_email" id="business_email" 
                               value="<?php echo htmlspecialchars(getSetting($conn, 'business_email', 'info@smartwash.com')); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="business_phone">Business Phone</label>
                        <input type="tel" name="business_phone" id="business_phone" 
                               value="<?php echo htmlspecialchars(getSetting($conn, 'business_phone', '+63 912 345 6789')); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="business_address">Business Address</label>
                    <textarea name="business_address" id="business_address" required><?php echo htmlspecialchars(getSetting($conn, 'business_address', '123 Main Street, Zamboanga City')); ?></textarea>
                </div>
                
                <button type="submit" class="btn-primary">Save Changes</button>
            </form>
        </div>

        <div class="card" id="hours">
            <div class="card-header">
                <h2 class="card-title">Operating Hours</h2>
                <p class="card-subtitle">Set your business hours for each day</p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_hours">
                
                <div class="hours-grid">
                    <?php
                    $days = [
                        'monday' => 'Monday',
                        'tuesday' => 'Tuesday',
                        'wednesday' => 'Wednesday',
                        'thursday' => 'Thursday',
                        'friday' => 'Friday',
                        'saturday' => 'Saturday',
                        'sunday' => 'Sunday'
                    ];
                    
                    foreach ($days as $day => $label):
                        $open = getSetting($conn, $day . '_open', '08:00');
                        $close = getSetting($conn, $day . '_close', '18:00');
                        $closed = getSetting($conn, $day . '_closed', 0);
                    ?>
                    <div class="hours-row">
                        <div class="day-label"><?php echo $label; ?></div>
                        <input type="time" name="<?php echo $day; ?>_open" id="<?php echo $day; ?>_open" value="<?php echo $open; ?>" 
                               <?php echo $closed ? 'disabled' : ''; ?>>
                        <input type="time" name="<?php echo $day; ?>_close" id="<?php echo $day; ?>_close" value="<?php echo $close; ?>" 
                               <?php echo $closed ? 'disabled' : ''; ?>>
                        <div class="checkbox-group">
                            <input type="checkbox" name="<?php echo $day; ?>_closed" id="<?php echo $day; ?>_closed" 
                                   <?php echo $closed ? 'checked' : ''; ?> 
                                   onchange="toggleHours('<?php echo $day; ?>')">
                            <label for="<?php echo $day; ?>_closed">Closed</label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" class="btn-primary" style="margin-top: 1.5rem;">Save Operating Hours</button>
            </form>
        </div>

        <div class="card" id="notifications">
            <div class="card-header">
                <h2 class="card-title">Notification Settings</h2>
                <p class="card-subtitle">Configure how you receive notifications</p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_notifications">
                
                <div class="info-box">
                    <div class="info-box-title">📧 Email Notifications</div>
                    <div class="info-box-text">Receive notifications via email</div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="email_notifications" id="email_notifications" 
                           <?php echo getSetting($conn, 'email_notifications', 1) ? 'checked' : ''; ?>>
                    <label for="email_notifications">Enable email notifications</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="booking_confirm" id="booking_confirm" 
                           <?php echo getSetting($conn, 'booking_confirm', 1) ? 'checked' : ''; ?>>
                    <label for="booking_confirm">New booking confirmations</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="booking_reminder" id="booking_reminder" 
                           <?php echo getSetting($conn, 'booking_reminder', 1) ? 'checked' : ''; ?>>
                    <label for="booking_reminder">Booking reminders (24 hours before)</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="payment_confirm" id="payment_confirm" 
                           <?php echo getSetting($conn, 'payment_confirm', 1) ? 'checked' : ''; ?>>
                    <label for="payment_confirm">Payment confirmations</label>
                </div>
                
                <div class="info-box" style="margin-top: 2rem;">
                    <div class="info-box-title">📱 SMS Notifications</div>
                    <div class="info-box-text">Receive notifications via SMS (Premium feature)</div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="sms_notifications" id="sms_notifications" 
                           <?php echo getSetting($conn, 'sms_notifications', 0) ? 'checked' : ''; ?>>
                    <label for="sms_notifications">Enable SMS notifications</label>
                </div>
                
                <button type="submit" class="btn-primary" style="margin-top: 1.5rem;">Save Notification Settings</button>
            </form>
        </div>

        <div class="card" id="system">
            <div class="card-header">
                <h2 class="card-title">System Settings</h2>
                <p class="card-subtitle">Configure system preferences</p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_system">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="timezone">Timezone</label>
                        <select name="timezone" id="timezone">
                            <option value="Asia/Manila" <?php echo getSetting($conn, 'timezone', 'Asia/Manila') === 'Asia/Manila' ? 'selected' : ''; ?>>Asia/Manila (PHT)</option>
                            <option value="Asia/Tokyo" <?php echo getSetting($conn, 'timezone') === 'Asia/Tokyo' ? 'selected' : ''; ?>>Asia/Tokyo (JST)</option>
                            <option value="America/New_York" <?php echo getSetting($conn, 'timezone') === 'America/New_York' ? 'selected' : ''; ?>>America/New York (EST)</option>
                            <option value="Europe/London" <?php echo getSetting($conn, 'timezone') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London (GMT)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="currency">Currency</label>
                        <select name="currency" id="currency">
                            <option value="PHP" <?php echo getSetting($conn, 'currency', 'PHP') === 'PHP' ? 'selected' : ''; ?>>PHP (₱)</option>
                            <option value="USD" <?php echo getSetting($conn, 'currency') === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                            <option value="EUR" <?php echo getSetting($conn, 'currency') === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                            <option value="JPY" <?php echo getSetting($conn, 'currency') === 'JPY' ? 'selected' : ''; ?>>JPY (¥)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_format">Date Format</label>
                        <select name="date_format" id="date_format">
                            <option value="m/d/Y" <?php echo getSetting($conn, 'date_format', 'm/d/Y') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                            <option value="d/m/Y" <?php echo getSetting($conn, 'date_format') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                            <option value="Y-m-d" <?php echo getSetting($conn, 'date_format') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking_buffer">Booking Buffer (minutes)</label>
                        <input type="number" name="booking_buffer" id="booking_buffer" 
                               value="<?php echo getSetting($conn, 'booking_buffer', '15'); ?>" min="0" max="60" required>
                        <small style="color: #666; font-size: 0.85rem;">Time buffer between bookings</small>
                    </div>
                </div>
                
                <div class="info-box">
                    <div class="info-box-title">ℹ️ System Information</div>
                    <div class="info-box-text">
                        <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
                        <strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?><br>
                        <strong>Database:</strong> MySQL <?php echo $conn->server_info ?? 'Unknown'; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">Save System Settings</button>
            </form>
        </div>

        <div class="card" id="security">
            <div class="card-header">
                <h2 class="card-title">Security Settings</h2>
                <p class="card-subtitle">Manage your account security</p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                
                <div class="info-box">
                    <div class="info-box-title">🔒 Change Password</div>
                    <div class="info-box-text">Use a strong password with at least 6 characters</div>
                </div>
                
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" name="current_password" id="current_password" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" name="new_password" id="new_password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required minlength="6">
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">Change Password</button>
            </form>
            
            <div class="info-box" style="margin-top: 2rem;">
                <div class="info-box-title">🛡️ Security Tips</div>
                <div class="info-box-text">
                    <ul style="margin-left: 1.5rem; line-height: 1.8;">
                        <li>Use a unique password for your admin account</li>
                        <li>Enable two-factor authentication (coming soon)</li>
                        <li>Log out when using shared computers</li>
                        <li>Regularly review your account activity</li>
                        <li>Keep your system and browser up to date</li>
                    </ul>
                </div>
            </div>
            
            <div class="info-box" style="margin-top: 1rem; border-left-color: #e74c3c;">
                <div class="info-box-title" style="color: #e74c3c;">⚠️ Danger Zone</div>
                <div class="info-box-text">
                    <p style="margin-bottom: 1rem;">These actions are irreversible. Please be careful.</p>
                        <button type="button" class="btn-secondary" onclick="confirmDataExport()" style="margin-right: 1rem;">Export All Data</button>
                        <button type="button" id="deactivateBtn" class="btn-danger" onclick="confirmAccountDeactivation()">Deactivate Account</button>
                </div>
            </div>
        </div>

            <form id="exportForm" method="POST" action="" style="display:none;">
                <input type="hidden" name="action" value="export_data">
            </form>

            <form id="deactivateForm" method="POST" action="" style="display:none;">
                <input type="hidden" name="action" value="deactivate_account">
            </form>
    </main>

    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>

    <script>
        function showTab(e, tabName) {
            // Allow calling without event (backwards compatibility)
            var evt = e;
            if (typeof tabName === 'undefined') {
                // caller used showTab(tabName)
                tabName = e;
                evt = null;
            }

            // Hide all cards
            document.querySelectorAll('.card').forEach(card => {
                card.classList.remove('active');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected card
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked tab if event available
            try {
                if (evt && evt.target) evt.target.classList.add('active');
            } catch (err) { /* ignore */ }

            // Close sidebar on mobile
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        }

        function toggleHours(day) {
            const checkbox = document.getElementById(day + '_closed');
            const openInput = document.querySelector(`input[name="${day}_open"]`);
            const closeInput = document.querySelector(`input[name="${day}_close"]`);
            
            if (checkbox.checked) {
                openInput.disabled = true;
                closeInput.disabled = true;
            } else {
                openInput.disabled = false;
                closeInput.disabled = false;
            }
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function confirmDataExport() {
            if (confirm('This will export all your system data as a JSON file. Continue?')) {
                // submit hidden form to trigger server-side export
                var f = document.getElementById('exportForm');
                if (f) f.submit();
            }
        }

        function confirmAccountDeactivation() {
            if (confirm('⚠️ WARNING: This will deactivate your admin account!\n\nAre you sure you want to continue?')) {
                if (confirm('This action is IRREVERSIBLE. Do you want to proceed and deactivate your account now?')) {
                    // submit hidden form to deactivate
                    var f = document.getElementById('deactivateForm');
                    if (f) {
                        // disable button to prevent double submit
                        var btn = document.querySelector('#deactivateBtn');
                        if (btn) btn.disabled = true;
                        f.submit();
                    }
                }
            }
        }

        // Auto-hide success/error messages after 5 seconds
        <?php if (!empty($message)): ?>
        setTimeout(function() {
            const alertMessage = document.getElementById('alertMessage');
            if (alertMessage) {
                alertMessage.style.opacity = '0';
                alertMessage.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alertMessage.remove(), 500);
            }
        }, 5000);
        <?php endif; ?>

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Password match validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>