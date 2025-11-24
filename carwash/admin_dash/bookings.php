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

// Detect customers table columns
$customerEmailCol = null;
$customerPhoneCol = null;
$customerNameCol = 'name';

$customerColsQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                      WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'customers'";
$customerColsResult = $conn->query($customerColsQuery);

$availableCustomerCols = [];
if ($customerColsResult && $customerColsResult->num_rows > 0) {
    while ($col = $customerColsResult->fetch_assoc()) {
        $colName = $col['COLUMN_NAME'];
        $availableCustomerCols[] = $colName;
        $lower = strtolower($colName);
        
        // Detect email column
        if (strpos($lower, 'email') !== false) {
            $customerEmailCol = $colName;
        }
        
        // Detect phone column
        if (strpos($lower, 'phone') !== false || strpos($lower, 'contact') !== false || strpos($lower, 'mobile') !== false) {
            $customerPhoneCol = $colName;
        }
        
        // Detect name column
        if (in_array($lower, ['name', 'full_name', 'customer_name'])) {
            $customerNameCol = $colName;
        }
    }
    
    // Handle concatenated name columns
    if (in_array('first_name', $availableCustomerCols) && in_array('last_name', $availableCustomerCols)) {
        $customerNameCol = "CONCAT(first_name, ' ', last_name)";
    }
}

// Detect bookings table columns
$bookingIdCol = 'booking_id';
$bookingServiceCol = 'service_id';
$bookingCustomerCol = 'customer_id';
$bookingVehicleCol = 'vehicle_id';

$bookingCols = $conn->query("SELECT COLUMN_NAME, COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS 
                              WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'bookings'");
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
$serviceCols = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                              WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'services'");
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

// Build dynamic SELECT for customers
$customerSelectCols = "customer_id, $customerNameCol AS customer_name";
if ($customerEmailCol) {
    $customerSelectCols .= ", `$customerEmailCol` AS email";
}
if ($customerPhoneCol) {
    $customerSelectCols .= ", `$customerPhoneCol` AS phone";
}

// Fetch all customers for dropdown
$customersQuery = "SELECT $customerSelectCols FROM customers ORDER BY customer_name";
$customersResult = $conn->query($customersQuery);
$customers = [];
if ($customersResult) {
    while ($row = $customersResult->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Fetch all services for dropdown
$servicesQuery = "SELECT service_id, `$serviceNameCol` AS service_name, `$servicePriceCol` AS price FROM services ORDER BY service_name";
$servicesResult = $conn->query($servicesQuery);
$services = [];
if ($servicesResult) {
    while ($row = $servicesResult->fetch_assoc()) {
        $services[] = $row;
    }
}

// Fetch all bookings with details
$bookingsQuery = "SELECT b.*, 
    s.`$serviceNameCol` AS service_name,
    s.`$servicePriceCol` AS service_price,
    c.`$customerNameCol` AS customer_name
    FROM bookings b
    LEFT JOIN services s ON b.`$bookingServiceCol` = s.service_id
    LEFT JOIN customers c ON b.`$bookingCustomerCol` = c.customer_id
    ORDER BY b.booking_date DESC, b.booking_time DESC";
$bookingsResult = $conn->query($bookingsQuery);

$bookings = [];
if ($bookingsResult) {
    while ($row = $bookingsResult->fetch_assoc()) {
        // Get vehicle info if exists
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
}

// Get customer details if viewing specific customer
$selectedCustomer = null;
if (isset($_GET['customer_id'])) {
    $custId = intval($_GET['customer_id']);
    $custQuery = "SELECT $customerSelectCols FROM customers WHERE customer_id = ?";
    $custStmt = $conn->prepare($custQuery);
    $custStmt->bind_param("i", $custId);
    $custStmt->execute();
    $custResult = $custStmt->get_result();
    if ($custResult && $custResult->num_rows > 0) {
        $selectedCustomer = $custResult->fetch_assoc();
    }
}
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

        .status-in-progress, .status-in_progress {
            background: #e7f3ff;
            color: #0056b3;
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

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-input {
            padding: 0.6rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: #667eea;
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
        }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <a href="index.php" class="menu-item">Dashboard</a>
            <a href="bookings.php" class="menu-item active">Bookings</a>
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
                <h1>Bookings Management</h1>
                <p style="color: #666; margin-top: 0.3rem;">Manage all bookings</p>
            </div>
            <div class="admin-info">
                <div>
                    <p style="font-weight: 600;"><?php echo htmlspecialchars($adminName); ?></p>
                    <p style="font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($adminEmail); ?></p>
                </div>
                <div class="admin-avatar">👤</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">All Bookings (<?php echo count($bookings); ?>)</h2>
                <a href="add_booking.php" class="btn-primary">+ New Booking</a>
            </div>

            <div class="filters">
                <input type="text" class="filter-input" id="searchInput" placeholder="Search by customer name...">
                <select class="filter-input" id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="in-progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <input type="date" class="filter-input" id="dateFilter">
            </div>

            <div class="table-container">
                <table id="bookingsTable">
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
                                    <td>
                                        <?php echo htmlspecialchars($booking['vehicle_info']); ?>
                                        <?php if ($booking['plate_number'] !== 'N/A'): ?>
                                            <br><small style="color: #666;"><?php echo htmlspecialchars($booking['plate_number']); ?></small>
                                        <?php endif; ?>
                                    </td>
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
                                            <button class="btn-secondary" onclick="editBooking(<?php echo $booking[$bookingIdCol]; ?>)">Edit</button>
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
        function editBooking(bookingId) {
            showNotification('Opening booking editor...', 'info');
            setTimeout(() => {
                window.location.href = `edit_booking.php?id=${bookingId}`;
            }, 800);
        }

        // Delete Booking
        function deleteBooking(bookingId, customerName) {
            if (confirm(`Are you sure you want to delete the booking for ${customerName}?\n\nThis action cannot be undone.`)) {
                showNotification(`Deleting booking...`, 'warning');
                
                const formData = new FormData();
                formData.append('action', 'delete_booking');
                formData.append('booking_id', bookingId);
                
                fetch('booking_actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message || 'Booking deleted successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification(data.message || 'Failed to delete booking', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while deleting the booking', 'danger');
                });
            }
        }

        // Filter functionality
        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        document.getElementById('dateFilter').addEventListener('change', filterTable);

        function filterTable() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const statusValue = document.getElementById('statusFilter').value.toLowerCase();
            const dateValue = document.getElementById('dateFilter').value;
            
            const rows = document.querySelectorAll('#bookingsTable tbody tr');
            
            rows.forEach(row => {
                const customerCell = row.cells[1]?.textContent.toLowerCase() || '';
                const statusCell = row.cells[6]?.textContent.toLowerCase() || '';
                const dateCell = row.cells[4]?.textContent || '';
                
                const matchesSearch = customerCell.includes(searchValue);
                const matchesStatus = !statusValue || statusCell.includes(statusValue);
                const matchesDate = !dateValue || dateCell.includes(formatDate(dateValue));
                
                if (matchesSearch && matchesStatus && matchesDate) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>