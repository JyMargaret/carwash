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

// Detect column names
$bookingIdCol = 'booking_id';
$bookingServiceCol = 'service_id';
$bookingCustomerCol = 'customer_id';
$bookingVehicleCol = 'vehicle_id';
$bookingEmployeeCol = 'employee_id';

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
        if (strpos($lower, 'employee') !== false || strpos($lower, 'staff') !== false) {
            $bookingEmployeeCol = $colName;
        }
    }
}

// Detect service columns
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

// Detect customer name column
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_booking') {
    $bookingId = intval($_POST['booking_id']);
    $serviceId = intval($_POST['service_id']);
    $bookingDate = $conn->real_escape_string($_POST['booking_date']);
    $bookingTime = $conn->real_escape_string($_POST['booking_time']);
    $status = $conn->real_escape_string($_POST['status']);
    $paymentStatus = $conn->real_escape_string($_POST['payment_status']);
    $bayNumber = $conn->real_escape_string($_POST['bay_number']);
    $notes = $conn->real_escape_string($_POST['notes']);
    $employeeId = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    
    // Get service price
    $priceQuery = "SELECT `$servicePriceCol` AS price FROM services WHERE service_id = ?";
    $priceStmt = $conn->prepare($priceQuery);
    $priceStmt->bind_param("i", $serviceId);
    $priceStmt->execute();
    $priceResult = $priceStmt->get_result();
    $servicePrice = $priceResult->fetch_assoc()['price'] ?? 0;
    
    // Update booking
    $updateQuery = "UPDATE bookings SET 
                    `$bookingServiceCol` = ?,
                    booking_date = ?,
                    booking_time = ?,
                    status = ?,
                    payment_status = ?,
                    bay_number = ?,
                    total_amount = ?,
                    final_amount = ?,
                    notes = ?";
    
    $params = [$serviceId, $bookingDate, $bookingTime, $status, $paymentStatus, $bayNumber, $servicePrice, $servicePrice, $notes];
    $types = "isssssdds";
    
    if ($employeeId !== null) {
        $updateQuery .= ", `$bookingEmployeeCol` = ?";
        $params[] = $employeeId;
        $types .= "i";
    }
    
    $updateQuery .= " WHERE `$bookingIdCol` = ?";
    $params[] = $bookingId;
    $types .= "i";
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param($types, ...$params);
    
    if ($updateStmt->execute()) {
        $_SESSION['success'] = 'Booking updated successfully!';
        header('Location: bookings.php');
        exit;
    } else {
        $error = 'Failed to update booking: ' . $updateStmt->error;
    }
}

// Get booking ID from URL
$bookingId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($bookingId <= 0) {
    header('Location: bookings.php');
    exit;
}

// Fetch booking details
$bookingQuery = "SELECT b.*, 
                 s.`$serviceNameCol` AS service_name,
                 s.`$servicePriceCol` AS service_price,
                 c.`$customerNameCol` AS customer_name
                 FROM bookings b
                 LEFT JOIN services s ON b.`$bookingServiceCol` = s.service_id
                 LEFT JOIN customers c ON b.`$bookingCustomerCol` = c.customer_id
                 WHERE b.`$bookingIdCol` = ?";
$bookingStmt = $conn->prepare($bookingQuery);
$bookingStmt->bind_param("i", $bookingId);
$bookingStmt->execute();
$bookingResult = $bookingStmt->get_result();
$booking = $bookingResult->fetch_assoc();

if (!$booking) {
    header('Location: bookings.php');
    exit;
}

// Get vehicle info
if (isset($booking[$bookingVehicleCol]) && $booking[$bookingVehicleCol]) {
    $vehicleQuery = "SELECT * FROM vehicles WHERE vehicle_id = ?";
    $vStmt = $conn->prepare($vehicleQuery);
    $vStmt->bind_param("i", $booking[$bookingVehicleCol]);
    $vStmt->execute();
    $vResult = $vStmt->get_result();
    $vehicle = $vResult->fetch_assoc();
}

// Fetch all services
$servicesQuery = "SELECT service_id, `$serviceNameCol` AS service_name, `$servicePriceCol` AS price FROM services ORDER BY service_name";
$servicesResult = $conn->query($servicesQuery);
$services = [];
while ($row = $servicesResult->fetch_assoc()) {
    $services[] = $row;
}

// Fetch all employees
$employeesQuery = "SELECT employee_id, name FROM employees WHERE is_active = 1 ORDER BY name";
$employeesResult = $conn->query($employeesQuery);
$employees = [];
while ($row = $employeesResult->fetch_assoc()) {
    $employees[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Edit Booking</title>
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

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
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

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn-primary {
            padding: 0.8rem 2rem;
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
            padding: 0.8rem 2rem;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .info-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
        }

        .info-box h3 {
            margin-bottom: 0.5rem;
            color: #333;
        }

        .info-box p {
            color: #666;
            margin-bottom: 0.3rem;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid #f5c6cb;
        }

        .paid-warning {
            background: #fff3cd;
            color: #856404;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid #ffeeba;
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

            .form-row {
                grid-template-columns: 1fr;
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
                <h1>Edit Booking #<?php echo $booking[$bookingIdCol]; ?></h1>
                <p style="color: #666; margin-top: 0.3rem;">Modify booking details</p>
            </div>
            <div class="admin-info">
                <div>
                    <p style="font-weight: 600;"><?php echo htmlspecialchars($adminName); ?></p>
                    <p style="font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($adminEmail); ?></p>
                </div>
                <div class="admin-avatar">👤</div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($booking['payment_status'] === 'Paid'): ?>
            <div class="paid-warning">
                ⚠️ <strong>Warning:</strong> This booking has been marked as paid. Any changes will be reflected in the system records.
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="info-box">
                <h3>Customer Information</h3>
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($booking['customer_name'] ?? 'N/A'); ?></p>
                <p><strong>Vehicle:</strong> <?php echo htmlspecialchars(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? 'N/A')); ?></p>
                <p><strong>Plate Number:</strong> <?php echo htmlspecialchars($vehicle['plate_number'] ?? 'N/A'); ?></p>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update_booking">
                <input type="hidden" name="booking_id" value="<?php echo $booking[$bookingIdCol]; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="service_id">Service *</label>
                        <select name="service_id" id="service_id" required>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['service_id']; ?>" 
                                        <?php echo ($service['service_id'] == $booking[$bookingServiceCol]) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service['service_name']); ?> - ₱<?php echo number_format($service['price'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select name="status" id="status" required>
                            <option value="Pending" <?php echo ($booking['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Confirmed" <?php echo ($booking['status'] === 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="In Progress" <?php echo ($booking['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo ($booking['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo ($booking['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="booking_date">Booking Date *</label>
                        <input type="date" name="booking_date" id="booking_date" 
                               value="<?php echo $booking['booking_date']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="booking_time">Booking Time *</label>
                        <input type="time" name="booking_time" id="booking_time" 
                               value="<?php echo $booking['booking_time']; ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_status">Payment Status *</label>
                        <select name="payment_status" id="payment_status" required>
                            <option value="Pending" <?php echo ($booking['payment_status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Paid" <?php echo ($booking['payment_status'] === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                            <option value="Refunded" <?php echo ($booking['payment_status'] === 'Refunded') ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="bay_number">Bay Number</label>
                        <select name="bay_number" id="bay_number">
                            <option value="">Unassigned</option>
                            <option value="Bay 1" <?php echo ($booking['bay_number'] === 'Bay 1') ? 'selected' : ''; ?>>Bay 1</option>
                            <option value="Bay 2" <?php echo ($booking['bay_number'] === 'Bay 2') ? 'selected' : ''; ?>>Bay 2</option>
                            <option value="Bay 3" <?php echo ($booking['bay_number'] === 'Bay 3') ? 'selected' : ''; ?>>Bay 3</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="employee_id">Assigned Employee (Optional)</label>
                    <select name="employee_id" id="employee_id">
                        <option value="">Unassigned</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['employee_id']; ?>"
                                    <?php echo (isset($booking[$bookingEmployeeCol]) && $booking[$bookingEmployeeCol] == $employee['employee_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($employee['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes"><?php echo htmlspecialchars($booking['notes'] ?? ''); ?></textarea>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary">Save Changes</button>
                    <a href="bookings.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
<?php
$conn->close();
?>