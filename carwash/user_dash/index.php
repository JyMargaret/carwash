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

// Check if user is logged in
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'customer') {
    header('Location: ../landing/login/login.php');
    exit;
}

// Include database connection
include __DIR__ . '/../database/database.php';

// Get user information
$userEmail = $_SESSION['userEmail'];
$userId = $_SESSION['userId'] ?? null;

// Find user in customers table
$userQuery = "SELECT c.*, u.email, u.first_name, u.last_name 
              FROM customers c 
              LEFT JOIN users u ON c.user_id = u.user_id 
              WHERE u.email = ? LIMIT 1";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("s", $userEmail);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userData = $userResult->fetch_assoc();

if (!$userData) {
    // Try to find in users table only
    $userQuery2 = "SELECT user_id, email, first_name, last_name FROM users WHERE email = ? LIMIT 1";
    $userStmt2 = $conn->prepare($userQuery2);
    $userStmt2->bind_param("s", $userEmail);
    $userStmt2->execute();
    $userResult2 = $userStmt2->get_result();
    $userDataOnly = $userResult2->fetch_assoc();
    
    if ($userDataOnly) {
        $_SESSION['userId'] = $userDataOnly['user_id'];
        $userId = $userDataOnly['user_id'];
        $userName = trim(($userDataOnly['first_name'] ?? '') . ' ' . ($userDataOnly['last_name'] ?? ''));
        $loyaltyPoints = 0;
        $membershipTier = 'Bronze';
        $totalSpent = 0;
        $customerId = null;
    } else {
        session_destroy();
        header('Location: ../landing/login/login.php');
        exit;
    }
} else {
    $userId = $userData['user_id'];
    $customerId = $userData['customer_id'];
    $_SESSION['userId'] = $userId;
    $userName = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
    if (empty($userName)) {
        $userName = $userData['name'] ?? 'User';
    }
    $loyaltyPoints = $userData['loyalty_points'] ?? 0;
    $membershipTier = $userData['membership_tier'] ?? 'Bronze';
    $totalSpent = $userData['total_spent'] ?? 0;
}

// Get user's vehicles
$vehiclesQuery = "SELECT * FROM vehicles WHERE customer_id = ? ORDER BY vehicle_id DESC";
$vehiclesStmt = $conn->prepare($vehiclesQuery);
$vehiclesStmt->bind_param("i", $customerId);
$vehiclesStmt->execute();
$vehiclesResult = $vehiclesStmt->get_result();
$vehicles = [];
while ($row = $vehiclesResult->fetch_assoc()) {
    $vehicles[] = $row;
}

// Get user's bookings
$bookingsQuery = "SELECT b.*, s.service_name, s.base_price, v.make, v.model, v.plate_number
                  FROM bookings b
                  LEFT JOIN services s ON b.service_id = s.service_id
                  LEFT JOIN vehicles v ON b.vehicle_id = v.vehicle_id
                  WHERE b.customer_id = ?
                  ORDER BY b.booking_date DESC, b.booking_time DESC
                  LIMIT 20";
$bookingsStmt = $conn->prepare($bookingsQuery);
$bookingsStmt->bind_param("i", $customerId);
$bookingsStmt->execute();
$bookingsResult = $bookingsStmt->get_result();
$bookings = [];
while ($row = $bookingsResult->fetch_assoc()) {
    $bookings[] = $row;
}

// Calculate statistics
$totalWashes = count(array_filter($bookings, function($b) { 
    return $b['status'] === 'Completed'; 
}));
$upcomingBookings = count(array_filter($bookings, function($b) { 
    return in_array($b['status'], ['Pending', 'Confirmed']); 
}));

// Get services for booking form
$servicesQuery = "SELECT * FROM services WHERE is_active = 1 ORDER BY base_price";
$servicesResult = $conn->query($servicesQuery);
$services = [];
while ($row = $servicesResult->fetch_assoc()) {
    $services[] = $row;
}

// Find next upcoming booking
$nextBooking = null;
foreach ($bookings as $booking) {
    if (in_array($booking['status'], ['Pending', 'Confirmed'])) {
        $nextBooking = $booking;
        break;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Dashboard</title>
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
        }

        .navbar {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
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

        .logout-btn {
            padding: 0.5rem 1.5rem;
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #ff3838;
            transform: translateY(-2px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .welcome-section h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
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
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
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

        .dashboard-grid {
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
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .booking-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #eee;
            transition: background 0.3s ease;
        }

        .booking-item:hover {
            background: #f8f9fa;
        }

        .booking-info h4 {
            font-size: 1rem;
            margin-bottom: 0.3rem;
            color: #333;
        }

        .booking-info p {
            font-size: 0.85rem;
            color: #666;
        }

        .booking-status {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #cce5ff;
            color: #004085;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .membership-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1rem;
        }

        .membership-tier {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .points-display {
            font-size: 2rem;
            font-weight: bold;
            margin: 1rem 0;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .quick-action-btn {
            padding: 1rem;
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: center;
        }

        .quick-action-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
        }

        .vehicle-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .vehicle-icon {
            font-size: 2rem;
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
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .close-btn {
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">SmartWash</div>
        <div class="user-info">
            <span><?php echo htmlspecialchars($userName); ?></span>
            <div class="user-avatar">👤</div>
            <button class="logout-btn" onclick="window.location.href='../landing/logout.php'">Logout</button>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars($userName); ?>! 👋</h1>
            <p>
                <?php if ($nextBooking): ?>
                    Your next wash is scheduled for <?php echo date('F d, Y', strtotime($nextBooking['booking_date'])); ?> at <?php echo date('g:i A', strtotime($nextBooking['booking_time'])); ?>
                <?php else: ?>
                    You have no upcoming bookings. Book your next wash today!
                <?php endif; ?>
            </p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🚗</div>
                <div class="stat-value"><?php echo $totalWashes; ?></div>
                <div class="stat-label">Total Washes</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value"><?php echo $loyaltyPoints; ?></div>
                <div class="stat-label">Loyalty Points</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">₱<?php echo number_format($totalSpent, 0); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?php echo $upcomingBookings; ?></div>
                <div class="stat-label">Upcoming Bookings</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Booking History</h2>
                    <button class="btn-primary" onclick="openBookingModal()">New Booking</button>
                </div>
                <div class="booking-history">
                    <?php if (empty($bookings)): ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">No bookings yet. Book your first wash!</p>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <div class="booking-item">
                                <div class="booking-info">
                                    <h4><?php echo htmlspecialchars($booking['service_name'] ?? 'Car Wash'); ?></h4>
                                    <p><?php echo date('F d, Y', strtotime($booking['booking_date'])); ?> - <?php echo htmlspecialchars($booking['make'] . ' ' . $booking['model']); ?></p>
                                </div>
                                <span class="booking-status status-<?php echo strtolower($booking['status']); ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="membership-card">
                        <div class="membership-tier">🏆 <?php echo $membershipTier; ?> Member</div>
                        <p>You're doing great!</p>
                        <div class="points-display"><?php echo $loyaltyPoints; ?> pts</div>
                    </div>
                    <div class="quick-actions">
                        <button class="quick-action-btn" onclick="openBookingModal()">📅 Book Now</button>
                        <button class="quick-action-btn" onclick="alert('Rewards coming soon!')">🎁 Rewards</button>
                        <button class="quick-action-btn" onclick="alert('History displayed above')">📊 History</button>
                        <button class="quick-action-btn" onclick="window.location.href='./support/chat.php'">💬 Support</button>
                    </div>
                </div>

                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h2 class="card-title">My Vehicles</h2>
                        <button class="btn-primary" onclick="openVehicleModal()">+ Add</button>
                    </div>
                    <div class="vehicle-list">
                        <?php if (empty($vehicles)): ?>
                            <p style="text-align: center; color: #666; padding: 1rem;">No vehicles added yet</p>
                        <?php else: ?>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <div class="vehicle-item">
                                    <div class="vehicle-icon">🚗</div>
                                    <div>
                                        <h4><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h4>
                                        <p><?php echo htmlspecialchars($vehicle['plate_number']); ?> • <?php echo htmlspecialchars($vehicle['vehicle_type']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="card-title">New Booking</h2>
                <span class="close-btn" onclick="closeBookingModal()">✕</span>
            </div>
            <form action="create_booking.php" method="POST">
                <div class="form-group">
                    <label for="vehicle">Select Vehicle</label>
                    <select name="vehicle_id" id="vehicle" required>
                        <option value="">Choose a vehicle</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle['vehicle_id']; ?>">
                                <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="service">Select Service</label>
                    <select name="service_type" id="service" required>
                        <option value="">Choose a service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo strtolower(str_replace(' ', '_', $service['service_type'])); ?>">
                                <?php echo htmlspecialchars($service['service_name']); ?> - ₱<?php echo number_format($service['base_price'], 0); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" name="booking_date" id="date" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="time">Time</label>
                    <input type="time" name="booking_time" id="time" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; padding: 1rem;">Confirm Booking</button>
            </form>
        </div>
    </div>

    <!-- Vehicle Modal -->
    <div id="vehicleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="card-title">Add Vehicle</h2>
                <span class="close-btn" onclick="closeVehicleModal()">✕</span>
            </div>
            <form action="add_vehicle_form.php" method="POST">
                <div class="form-group">
                    <label for="make">Make</label>
                    <input type="text" name="make" id="make" placeholder="e.g., Honda" required>
                </div>
                <div class="form-group">
                    <label for="model">Model</label>
                    <input type="text" name="model" id="model" placeholder="e.g., Civic" required>
                </div>
                <div class="form-group">
                    <label for="plate">Plate Number</label>
                    <input type="text" name="plate_number" id="plate" placeholder="e.g., ABC 1234" required>
                </div>
                <div class="form-group">
                    <label for="type">Vehicle Type</label>
                    <select name="vehicle_type" id="type" required>
                        <option value="">Select type</option>
                        <option value="Sedan">Sedan</option>
                        <option value="SUV">SUV</option>
                        <option value="Truck">Truck</option>
                        <option value="Van">Van</option>
                        <option value="Motorcycle">Motorcycle</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="color">Color</label>
                    <input type="text" name="color" id="color" placeholder="e.g., White" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; padding: 1rem;">Add Vehicle</button>
            </form>
        </div>
    </div>

    <script>
        function openBookingModal() {
            <?php if (empty($vehicles)): ?>
                alert('Please add a vehicle first before booking!');
                openVehicleModal();
                return;
            <?php endif; ?>
            document.getElementById('bookingModal').classList.add('active');
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.remove('active');
        }

        function openVehicleModal() {
            document.getElementById('vehicleModal').classList.add('active');
        }

        function closeVehicleModal() {
            document.getElementById('vehicleModal').classList.remove('active');
        }

        window.onclick = function(event) {
            const bookingModal = document.getElementById('bookingModal');
            const vehicleModal = document.getElementById('vehicleModal');
            if (event.target === bookingModal) {
                closeBookingModal();
            }
            if (event.target === vehicleModal) {
                closeVehicleModal();
            }
        }

        // Show success/error messages
        <?php if (isset($_SESSION['success'])): ?>
            alert('<?php echo $_SESSION['success']; ?>');
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            alert('<?php echo $_SESSION['error']; ?>');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
</body>
</html>