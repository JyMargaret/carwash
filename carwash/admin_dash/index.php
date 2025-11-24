<?php
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 7, 
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Check if user is logged in and is admin
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
    die("Database configuration file not found.");
}

$adminEmail = $_SESSION['userEmail'];
$adminName = $_SESSION['userName'] ?? 'Admin';

// Get database name
$dbNameQuery = "SELECT DATABASE() AS dbname";
$dbNameResult = $conn->query($dbNameQuery);
$dbName = $dbNameResult ? $dbNameResult->fetch_assoc()['dbname'] : 'smartwash_db';

// Detect column names for bookings table
$bookingIdCol = 'booking_id';
$bookingServiceCol = 'service_id';
$bookingCustomerCol = 'customer_id';
$bookingVehicleCol = 'vehicle_id';

$bookingCols = $conn->query("SELECT COLUMN_NAME, COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'bookings'");
if ($bookingCols && $bookingCols->num_rows > 0) {
    while ($col = $bookingCols->fetch_assoc()) {
        $colName = $col['COLUMN_NAME'];
        $lower = strtolower($colName);
        
        if ($col['COLUMN_KEY'] === 'PRI') {
            $bookingIdCol = $colName;
        }
        if (strpos($lower, 'service') !== false && strpos($lower, 'id') !== false) {
            $bookingServiceCol = $colName;
        }
        if (strpos($lower, 'customer') !== false && strpos($lower, 'id') !== false) {
            $bookingCustomerCol = $colName;
        }
        if (strpos($lower, 'vehicle') !== false && strpos($lower, 'id') !== false) {
            $bookingVehicleCol = $colName;
        }
    }
}

// Detect services table columns
$serviceNameCol = 'service_name';
$servicePriceCol = 'price';
$serviceCols = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'services'");
if ($serviceCols && $serviceCols->num_rows > 0) {
    while ($col = $serviceCols->fetch_assoc()) {
        $colName = $col['COLUMN_NAME'];
        $lower = strtolower($colName);
        if (in_array($lower, ['name', 'service_name'])) {
            $serviceNameCol = $colName;
        }
        if (in_array($lower, ['price', 'base_price', 'total_amount'])) {
            $servicePriceCol = $colName;
        }
    }
}

// Detect customers table columns
$customerNameCol = 'name';
$customerCols = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'customers'");
if ($customerCols && $customerCols->num_rows > 0) {
    $cols = [];
    while ($col = $customerCols->fetch_assoc()) {
        $cols[] = $col['COLUMN_NAME'];
    }
    
    if (in_array('name', $cols)) {
        $customerNameCol = 'name';
    } elseif (in_array('full_name', $cols)) {
        $customerNameCol = 'full_name';
    } elseif (in_array('first_name', $cols) && in_array('last_name', $cols)) {
        $customerNameCol = "CONCAT(first_name, ' ', last_name)";
    }
}

// Get today's date
$today = date('Y-m-d');

// Fetch statistics
$statsQuery = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN DATE(booking_date) = '$today' THEN 1 ELSE 0 END) as today_bookings,
    SUM(CASE WHEN status = 'completed' AND DATE(booking_date) = '$today' THEN 1 ELSE 0 END) as completed_today,
    SUM(CASE WHEN status IN ('pending', 'confirmed') THEN 1 ELSE 0 END) as upcoming,
    SUM(CASE WHEN status = 'In Progress' OR status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Get revenue statistics
$revenueQuery = "SELECT 
    SUM(CASE WHEN status = 'completed' AND DATE(booking_date) = '$today' THEN `$servicePriceCol` ELSE 0 END) as today_revenue,
    SUM(CASE WHEN status = 'completed' THEN `$servicePriceCol` ELSE 0 END) as total_revenue
    FROM bookings b
    LEFT JOIN services s ON b.`$bookingServiceCol` = s.service_id";
$revenueResult = $conn->query($revenueQuery);
$revenue = $revenueResult->fetch_assoc();

// Get total customers
$customerQuery = "SELECT COUNT(*) as total_customers FROM customers";
$customerResult = $conn->query($customerQuery);
$customerCount = $customerResult->fetch_assoc();

// Fetch recent bookings with details
$bookingsQuery = "SELECT b.*, 
    s.`$serviceNameCol` AS service_name,
    s.`$servicePriceCol` AS service_price,
    c.`$customerNameCol` AS customer_name
    FROM bookings b
    LEFT JOIN services s ON b.`$bookingServiceCol` = s.service_id
    LEFT JOIN customers c ON b.`$bookingCustomerCol` = c.customer_id
    ORDER BY b.booking_date DESC, b.booking_time DESC
    LIMIT 10";
$bookingsResult = $conn->query($bookingsQuery);

$bookings = [];
while ($row = $bookingsResult->fetch_assoc()) {
    if (isset($row[$bookingVehicleCol]) && $row[$bookingVehicleCol]) {
        $vehicleQuery = "SELECT * FROM vehicles WHERE vehicle_id = ? LIMIT 1";
        $vStmt = $conn->prepare($vehicleQuery);
        $vStmt->bind_param("i", $row[$bookingVehicleCol]);
        $vStmt->execute();
        $vResult = $vStmt->get_result();
        
        if ($vResult && $vResult->num_rows > 0) {
            $vehicle = $vResult->fetch_assoc();
            $row['vehicle_info'] = trim(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
            $row['plate_number'] = $vehicle['plate_number'] ?? '';
        } else {
            $row['vehicle_info'] = 'N/A';
            $row['plate_number'] = 'N/A';
        }
    } else {
        $row['vehicle_info'] = 'N/A';
        $row['plate_number'] = 'N/A';
    }
    
    $bookings[] = $row;
}

// Get service performance
$serviceStatsQuery = "SELECT 
    s.`$serviceNameCol` AS service_name,
    COUNT(b.`$bookingIdCol`) as booking_count,
    SUM(CASE WHEN b.status = 'completed' THEN s.`$servicePriceCol` ELSE 0 END) as revenue
    FROM services s
    LEFT JOIN bookings b ON s.service_id = b.`$bookingServiceCol`
    GROUP BY s.service_id
    ORDER BY booking_count DESC
    LIMIT 3";
$serviceStatsResult = $conn->query($serviceStatsQuery);

$serviceStats = [];
while ($row = $serviceStatsResult->fetch_assoc()) {
    $serviceStats[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Admin Dashboard</title>
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

        .admin-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            cursor: pointer;
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

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.3rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
            padding: 0.6rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            padding: 0.4rem 0.8rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #764ba2;
            transform: translateY(-1px);
        }

        .btn-danger {
            padding: 0.4rem 0.8rem;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-1px);
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

        .quick-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .quick-stat-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            text-align: center;
        }

        .quick-stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }

        .quick-stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.3rem;
        }

        .service-stat-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .service-stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .service-name {
            font-weight: 600;
            color: #333;
        }

        .service-revenue {
            font-weight: bold;
            color: #667eea;
        }

        .service-bookings {
            font-size: 0.85rem;
            color: #666;
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

        .notification.success {
            border-left-color: #28a745;
        }

        .notification.danger {
            border-left-color: #e74c3c;
        }

        .notification.warning {
            border-left-color: #f39c12;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
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
        }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <a href="index.php" class="menu-item active">Dashboard</a>
            <a href="bookings.php" class="menu-item">Bookings</a>
            <a href="customers.php" class="menu-item">Customers</a>
            <a href="services.php" class="menu-item">Services</a>
            <a href="staff.php" class="menu-item">Staff</a>
            <a href="reports.php" class="menu-item">Reports</a>
            <a href="settings.php" class="menu-item">Settings</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="header">
            <div>
                <h1>Dashboard Overview</h1>
                <p style="color: #666; margin-top: 0.3rem;" id="currentDate"><?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="admin-info">
                <div>
                    <p style="font-weight: 600;"><?php echo htmlspecialchars($adminName); ?></p>
                    <p style="font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($adminEmail); ?></p>
                </div>
                <div class="admin-avatar">👤</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card" onclick="showNotification('Viewing revenue details...', 'info')">
                <div class="stat-icon">💰</div>
                <div class="stat-value">₱<?php echo number_format($revenue['today_revenue'] ?? 0, 0); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>

            <div class="stat-card" onclick="showNotification('Viewing bookings...', 'info')">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?php echo $stats['today_bookings']; ?></div>
                <div class="stat-label">Today's Bookings</div>
            </div>

            <div class="stat-card" onclick="showNotification('Viewing customers...', 'info')">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $customerCount['total_customers']; ?></div>
                <div class="stat-label">Total Customers</div>
            </div>

            <div class="stat-card" onclick="showNotification('Viewing ratings...', 'info')">
                <div class="stat-icon">⭐</div>
                <div class="stat-value">4.8</div>
                <div class="stat-label">Average Rating</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Bookings</h2>
                    <a href="bookings.php" class="btn-primary">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Vehicle</th>
                                <th>Date & Time</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 2rem; color: #999;">
                                        No bookings found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>#<?php echo $booking[$bookingIdCol]; ?></td>
                                        <td><?php echo htmlspecialchars($booking['customer_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['service_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['vehicle_info']); ?></td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?><br>
                                            <small><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></small>
                                        </td>
                                        <td>₱<?php echo number_format($booking['service_price'] ?? 0, 2); ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = strtolower(str_replace(' ', '-', $booking['status']));
                                            ?>
                                            <span class="status-badge status-<?php echo $statusClass; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-secondary" onclick="editBooking(<?php echo $booking[$bookingIdCol]; ?>, '<?php echo htmlspecialchars($booking['customer_name']); ?>')">Edit</button>
                                                <button class="btn-danger" onclick="deleteBooking(<?php echo $booking[$bookingIdCol]; ?>, '<?php echo htmlspecialchars($booking['customer_name']); ?>')">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Today's Summary</h2>
                    </div>
                    <div class="quick-stats">
                        <div class="quick-stat-item">
                            <div class="quick-stat-value"><?php echo $stats['completed_today']; ?></div>
                            <div class="quick-stat-label">Completed</div>
                        </div>
                        <div class="quick-stat-item">
                            <div class="quick-stat-value"><?php echo $stats['upcoming']; ?></div>
                            <div class="quick-stat-label">Upcoming</div>
                        </div>
                        <div class="quick-stat-item">
                            <div class="quick-stat-value"><?php echo $stats['in_progress']; ?></div>
                            <div class="quick-stat-label">In Progress</div>
                        </div>
                        <div class="quick-stat-item">
                            <div class="quick-stat-value"><?php echo $stats['cancelled']; ?></div>
                            <div class="quick-stat-label">Cancelled</div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h2 class="card-title">Top Services</h2>
                    </div>
                    <?php if (empty($serviceStats)): ?>
                        <p style="text-align: center; padding: 2rem; color: #999;">No service data available</p>
                    <?php else: ?>
                        <?php foreach ($serviceStats as $service): ?>
                            <div class="service-stat-item">
                                <div class="service-stat-header">
                                    <span class="service-name"><?php echo htmlspecialchars($service['service_name']); ?></span>
                                    <span class="service-revenue">₱<?php echo number_format($service['revenue'], 0); ?></span>
                                </div>
                                <div class="service-bookings"><?php echo $service['booking_count']; ?> bookings</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Notification Container -->
    <div class="notification" id="notification">
        <span id="notificationIcon"></span>
        <span id="notificationText"></span>
    </div>

    <script>
        // Notification Function
        function showNotification(message, type = 'success', duration = 4000) {
            const notification = document.getElementById('notification');
            const notificationText = document.getElementById('notificationText');
            const notificationIcon = document.getElementById('notificationIcon');
            
            const icons = {
                success: '✓',
                danger: '✕',
                warning: '⚠',
                info: 'ℹ'
            };
            
            notification.className = `notification ${type} active`;
            notificationIcon.textContent = icons[type] || 'ℹ';
            notificationText.textContent = message;
            
            setTimeout(() => {
                notification.classList.remove('active');
            }, duration);
        }

        // Edit Booking
        function editBooking(bookingId, customerName) {
            showNotification(`Editing booking for ${customerName}...`, 'info');
            setTimeout(() => {
                window.location.href = `bookings.php?edit=${bookingId}`;
            }, 1000);
        }

        // Delete Booking
        function deleteBooking(bookingId, customerName) {
            if (confirm(`Are you sure you want to delete the booking for ${customerName}?`)) {
                showNotification(`Deleting booking...`, 'warning');
                
                const formData = new FormData();
                formData.append('action', 'delete_booking');
                formData.append('booking_id', bookingId);
                
                fetch('bookings.php', {
                    method: 'POST',
                    body: formData
                })
                .then(() => {
                    showNotification(`Booking deleted successfully!`, 'success');
                    setTimeout(() => location.reload(), 1500);
                })
                .catch(error => {
                    showNotification(`Error: ${error}`, 'danger');
                });
            }
        }

    </script>
</body>
</html>
<?php
$conn->close();
?>