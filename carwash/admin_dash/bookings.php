<?php
session_start();

if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'admin') {
    header('Location: ../landing/login/login.php');
    exit;
}

// Include database connection
$dbPath = __DIR__ . '/../database/database.php';
if (file_exists($dbPath)) {
    include $dbPath;
    if (!isset($conn) || !$conn || $conn->connect_error) {
        die("Database connection failed. Please check your database.php file.");
    }
} else {
    // Fallback: Create a basic database connection if file doesn't exist
    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "smartwash";
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Handle booking actions (add, edit, delete, update status)
$message = '';
$messageType = '';

// Handle AJAX requests for real-time sync
if (isset($_GET['action']) && $_GET['action'] === 'get_bookings_json') {
    header('Content-Type: application/json');
    
    $bookingsQuery = "SELECT b.*, 
                      s.name AS service_name, 
                      s.price AS service_price,
                      c.name AS customer_name,
                      c.email AS customer_email,
                      c.phone AS customer_phone
                      FROM bookings b
                      LEFT JOIN services s ON b.service_id = s.id
                      LEFT JOIN customers c ON b.customer_id = c.id
                      ORDER BY b.booking_date DESC, b.booking_time DESC";
    
    $result = $conn->query($bookingsQuery);
    $bookings = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bookings[] = [
                'id' => $row['id'],
                'service' => $row['service_name'],
                'date' => date('F j, Y', strtotime($row['booking_date'])),
                'vehicle' => $row['vehicle_model'],
                'status' => ucfirst($row['status']),
                'time' => date('g:i A', strtotime($row['booking_time'])),
                'rawDate' => $row['booking_date'],
                'price' => $row['service_price'],
                'customer_name' => $row['customer_name'],
                'customer_email' => $row['customer_email'],
                'customer_phone' => $row['customer_phone']
            ];
        }
    }
    
    echo json_encode($bookings);
    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_booking') {
        $customer_id = $conn->real_escape_string($_POST['customer_id']);
        $service_id = $conn->real_escape_string($_POST['service_id']);
        $vehicle_model = $conn->real_escape_string($_POST['vehicle_model']);
        $booking_date = $conn->real_escape_string($_POST['booking_date']);
        $booking_time = $conn->real_escape_string($_POST['booking_time']);
        $status = 'pending';
        
        $sql = "INSERT INTO bookings (customer_id, service_id, vehicle_model, booking_date, booking_time, status, created_at) 
                VALUES ('$customer_id', '$service_id', '$vehicle_model', '$booking_date', '$booking_time', '$status', NOW())";
        
        if ($conn->query($sql)) {
            $message = 'Booking added successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error adding booking: ' . $conn->error;
            $messageType = 'error';
        }
    }
    
    if ($action === 'update_status') {
        $booking_id = $conn->real_escape_string($_POST['booking_id']);
        $status = $conn->real_escape_string($_POST['status']);
        
        $sql = "UPDATE bookings SET status = '$status' WHERE id = '$booking_id'";
        
        if ($conn->query($sql)) {
            $message = 'Booking status updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error updating status: ' . $conn->error;
            $messageType = 'error';
        }
    }
    
    if ($action === 'delete_booking') {
        $booking_id = $conn->real_escape_string($_POST['booking_id']);
        
        $sql = "DELETE FROM bookings WHERE id = '$booking_id'";
        
        if ($conn->query($sql)) {
            $message = 'Booking deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error deleting booking: ' . $conn->error;
            $messageType = 'error';
        }
    }
}

// Detect column names dynamically
$serviceNameCol = null;
$servicePriceCol = null;
$servicePkCol = null;

$svcColsRes = $conn->query("SELECT COLUMN_NAME, COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services'");
if ($svcColsRes) {
    $svcCols = [];
    while ($c = $svcColsRes->fetch_assoc()) { $svcCols[] = $c; }
    foreach ($svcCols as $colInfo) {
        $col = $colInfo['COLUMN_NAME'];
        $lower = strtolower($col);
        if ($serviceNameCol === null && in_array($lower, ['name', 'service_name', 'title', 'service', 'service_title'])) {
            $serviceNameCol = $col;
        }
        if ($servicePriceCol === null && in_array($lower, ['price', 'service_price', 'cost', 'amount'])) {
            $servicePriceCol = $col;
        }
        if ($servicePkCol === null && isset($colInfo['COLUMN_KEY']) && strtoupper($colInfo['COLUMN_KEY']) === 'PRI') {
            $servicePkCol = $col;
        }
    }
}

if (!$servicePkCol) $servicePkCol = 'id';
if (!$serviceNameCol) $serviceNameCol = 'name';
if (!$servicePriceCol) $servicePriceCol = 'price';

$bookingServiceCol = 'service_id';

$serviceSelectParts = ["s.`$serviceNameCol` AS service_name", "s.`$servicePriceCol` AS service_price"];
$bookingsQuery = "SELECT b.*, " . implode(', ', $serviceSelectParts) . " FROM bookings b LEFT JOIN services s ON b.`$bookingServiceCol` = s.`$servicePkCol` ORDER BY b.booking_date DESC, b.booking_time DESC";
$bookingsResult = $conn->query($bookingsQuery);

$bookings = [];
if ($bookingsResult) {
    while ($r = $bookingsResult->fetch_assoc()) {
        $bookings[] = $r;
    }
}

// Detect customer columns
$nameExpr = null;
$emailCol = null;
$phoneCol = null;
$customerPkCol = null;

$colsRes = $conn->query("SELECT COLUMN_NAME, COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'");
$cols = [];
if ($colsRes) {
    while ($c = $colsRes->fetch_assoc()) {
        $cols[] = $c['COLUMN_NAME'];
        if ($customerPkCol === null && isset($c['COLUMN_KEY']) && strtoupper($c['COLUMN_KEY']) === 'PRI') {
            $customerPkCol = $c['COLUMN_NAME'];
        }
    }
}

if (!$customerPkCol) $customerPkCol = 'id';

if (in_array('name', $cols)) {
    $nameExpr = 'name';
} elseif (in_array('full_name', $cols)) {
    $nameExpr = 'full_name';
} elseif (in_array('first_name', $cols) && in_array('last_name', $cols)) {
    $nameExpr = "CONCAT(first_name, ' ', last_name)";
} else {
    $nameExpr = 'name';
}

foreach (['email', 'email_address', 'customer_email'] as $cand) {
    if (in_array($cand, $cols)) { $emailCol = $cand; break; }
}
if (!$emailCol) $emailCol = 'email';

foreach (['phone', 'phone_number', 'contact', 'mobile'] as $cand) {
    if (in_array($cand, $cols)) { $phoneCol = $cand; break; }
}
if (!$phoneCol) $phoneCol = 'phone';

$customersQuery = "SELECT `$customerPkCol` AS id, $nameExpr AS name, `$emailCol` AS email, `$phoneCol` AS phone FROM customers ORDER BY name";
$customersResult = $conn->query($customersQuery);

$servicesQuery = "SELECT `$servicePkCol` AS id, `$serviceNameCol` AS name, `$servicePriceCol` AS price FROM services ORDER BY name";
$servicesResult = $conn->query($servicesQuery);

$customersMap = [];
if ($customersResult) {
    while ($cust = $customersResult->fetch_assoc()) {
        if (isset($cust['id']) && $cust['id'] !== null) {
            $customersMap[$cust['id']] = $cust;
        }
    }
}

foreach ($bookings as &$b) {
    $cid = $b['customer_id'] ?? null;
    if ($cid && isset($customersMap[$cid])) {
        $b['customer_name'] = $customersMap[$cid]['name'] ?? null;
        $b['customer_email'] = $customersMap[$cid]['email'] ?? null;
        $b['customer_phone'] = $customersMap[$cid]['phone'] ?? null;
    } else {
        $b['customer_name'] = $b['customer_name'] ?? null;
        $b['customer_email'] = $b['customer_email'] ?? null;
        $b['customer_phone'] = $b['customer_phone'] ?? null;
    }
}

$statsQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN DATE(booking_date) = CURDATE() THEN 1 ELSE 0 END) as today
                FROM bookings";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Bookings Management</title>
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

        .sync-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #d4edda;
            color: #155724;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .sync-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #28a745;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.3rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
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
            padding: 0.5rem 1rem;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .btn-danger {
            padding: 0.5rem 1rem;
            background: white;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .btn-danger:hover {
            background: #e74c3c;
            color: white;
        }

        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .search-input {
            flex: 1;
            padding: 0.8rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-select {
            padding: 0.8rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 1rem;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed, .status-upcoming {
            background: #cce5ff;
            color: #004085;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-in {
            background: #e7f3ff;
            color: #0056b3;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
        }

        .close-btn {
            font-size: 2rem;
            color: #999;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-btn:hover {
            color: #333;
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
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
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

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .notification {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            z-index: 2000;
            display: none;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease;
            border-left: 4px solid #667eea;
        }

        .notification.active {
            display: flex;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-bar {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <div class="menu-item active" onclick="window.location.href='index.php'">
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
            <div class="menu-item" onclick="window.location.href='settings.php'">
                <span>Settings</span>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="header">
            <div>
                <h1>Bookings Management</h1>
                <p style="color: #666; margin-top: 0.3rem;">Manage all customer bookings</p>
            </div>
            <div class="sync-indicator">
                <div class="sync-dot"></div>
                <span>Real-time Sync Active</span>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="totalCount"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="pendingCount"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="confirmedCount"><?php echo $stats['confirmed']; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="completedCount"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="cancelledCount"><?php echo $stats['cancelled']; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="todayCount"><?php echo $stats['today']; ?></div>
                <div class="stat-label">Today's Bookings</div>
            </div>
        </div>

        <div class="search-bar">
            <input type="text" class="search-input" id="searchInput" placeholder="Search by customer name, vehicle, or service..." onkeyup="filterTable()">
            <select class="filter-select" id="statusFilter" onchange="filterTable()">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <button class="btn-primary" onclick="syncWithLocalStorage()">🔄 Sync with Dashboards</button>
            <button class="btn-primary" onclick="openAddModal()">+ New Booking</button>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">All Bookings</h2>
            </div>
            <div class="table-container">
                <table id="bookingsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Service</th>
                            <th>Vehicle</th>
                            <th>Date & Time</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($bookings)): ?>
                            <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>#<?php echo $booking['id']; ?></td>
                                <td><?php echo htmlspecialchars($booking['customer_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($booking['customer_email'] ?? 'N/A'); ?><br>
                                    <small><?php echo htmlspecialchars($booking['customer_phone'] ?? ''); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($booking['service_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($booking['vehicle_model'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?><br>
                                    <small><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></small>
                                </td>
                                <td>₱<?php echo number_format($booking['service_price'] ?? 0, 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-secondary" onclick="openStatusModal(<?php echo $booking['id']; ?>, '<?php echo $booking['status']; ?>')">Update</button>
                                        <button class="btn-danger" onclick="deleteBooking(<?php echo $booking['id']; ?>)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 2rem; color: #999;">
                                    No bookings found. Click "New Booking" to add one.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Booking Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">New Booking</h2>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_booking">
                
                <div class="form-group">
                    <label for="customer_id">Customer</label>
                    <select name="customer_id" id="customer_id" required>
                        <option value="">Select Customer</option>
                        <?php 
                        if ($customersResult && $customersResult->num_rows > 0):
                            mysqli_data_seek($customersResult, 0);
                            while ($customer = $customersResult->fetch_assoc()): 
                                if (isset($customer['id']) && $customer['id'] !== null):
                        ?>
                            <option value="<?php echo htmlspecialchars($customer['id']); ?>">
                                <?php
                                    $dispName = $customer['name'] ?? ('Customer #' . $customer['id']);
                                    $dispEmail = $customer['email'] ?? '';
                                    echo htmlspecialchars($dispName) . ($dispEmail ? ' - ' . htmlspecialchars($dispEmail) : '');
                                ?>
                            </option>
                        <?php 
                                endif;
                            endwhile; 
                        endif; 
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="service_id">Service</label>
                    <select name="service_id" id="service_id" required>
                        <option value="">Select Service</option>
                        <?php 
                        if ($servicesResult && $servicesResult->num_rows > 0):
                            mysqli_data_seek($servicesResult, 0);
                            while ($service = $servicesResult->fetch_assoc()): 
                                if (isset($service['id']) && $service['id'] !== null):
                        ?>
                            <option value="<?php echo htmlspecialchars($service['id']); ?>">
                                <?php echo htmlspecialchars($service['name'] ?? 'Service') . ' - ₱' . number_format($service['price'] ?? 0, 2); ?>
                            </option>
                        <?php 
                                endif;
                            endwhile; 
                        endif; 
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="vehicle_model">Vehicle Model</label>
                    <input type="text" name="vehicle_model" id="vehicle_model" placeholder="e.g., Honda Civic (ABC 1234)" required>
                </div>

                <div class="form-group">
                    <label for="booking_date">Booking Date</label>
                    <input type="date" name="booking_date" id="booking_date" required>
                </div>

                <div class="form-group">
                    <label for="booking_time">Booking Time</label>
                    <input type="time" name="booking_time" id="booking_time" required>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Add Booking</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Update Booking Status</h2>
                <button class="close-btn" onclick="closeStatusModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="booking_id" id="status_booking_id">
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" required>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <div class="notification" id="notification">
        <span id="notificationText"></span>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function openStatusModal(bookingId, currentStatus) {
            document.getElementById('status_booking_id').value = bookingId;
            document.getElementById('status').value = currentStatus;
            document.getElementById('statusModal').classList.add('active');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('active');
        }

        function deleteBooking(bookingId) {
            if (confirm('Are you sure you want to delete this booking? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_booking">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function filterTable() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const statusValue = document.getElementById('statusFilter').value.toLowerCase();
            const table = document.getElementById('bookingsTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                const statusCell = row.cells[7];
                const status = statusCell ? statusCell.textContent.toLowerCase() : '';

                const matchesSearch = text.includes(searchValue);
                const matchesStatus = !statusValue || status.includes(statusValue);

                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            }
        }

        function showNotification(message) {
            const notification = document.getElementById('notification');
            const notificationText = document.getElementById('notificationText');
            
            notification.className = 'notification active';
            notificationText.textContent = message;
            
            setTimeout(() => {
                notification.classList.remove('active');
            }, 4000);
        }

        // Sync with localStorage (User and Employee dashboards)
        function syncWithLocalStorage() {
            showNotification('Syncing with user and employee dashboards...');
            
            // Fetch current bookings from server
            fetch('?action=get_bookings_json')
                .then(response => response.json())
                .then(bookings => {
                    // Store in localStorage for user and employee dashboards
                    localStorage.setItem('smartwash_bookings', JSON.stringify(bookings));
                    
                    showNotification(`Successfully synced ${bookings.length} bookings!`);
                    
                    // Update stats
                    updateStats(bookings);
                })
                .catch(error => {
                    showNotification('Error syncing bookings: ' + error.message);
                });
        }

        function updateStats(bookings) {
            const total = bookings.length;
            const pending = bookings.filter(b => b.status.toLowerCase() === 'pending').length;
            const confirmed = bookings.filter(b => b.status.toLowerCase() === 'confirmed').length;
            const completed = bookings.filter(b => b.status.toLowerCase() === 'completed').length;
            const cancelled = bookings.filter(b => b.status.toLowerCase() === 'cancelled').length;
            
            const today = new Date().toISOString().split('T')[0];
            const todayBookings = bookings.filter(b => b.rawDate === today).length;
            
            document.getElementById('totalCount').textContent = total;
            document.getElementById('pendingCount').textContent = pending;
            document.getElementById('confirmedCount').textContent = confirmed;
            document.getElementById('completedCount').textContent = completed;
            document.getElementById('cancelledCount').textContent = cancelled;
            document.getElementById('todayCount').textContent = todayBookings;
        }

        // Load bookings from localStorage and push to database
        function loadFromLocalStorage() {
            const storedBookings = JSON.parse(localStorage.getItem('smartwash_bookings') || '[]');
            
            if (storedBookings.length > 0) {
                showNotification(`Found ${storedBookings.length} bookings in local storage`);
            }
        }

        // Auto-sync every 5 seconds
        setInterval(() => {
            syncWithLocalStorage();
        }, 5000);

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        // Set minimum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('booking_date');
            const today = new Date().toISOString().split('T')[0];
            dateInput.setAttribute('min', today);
            
            // Initial sync
            syncWithLocalStorage();
            
            // Load from localStorage
            loadFromLocalStorage();
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>