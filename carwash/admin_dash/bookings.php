<?php
session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 7, 'path' => '/', 'domain' => '', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'admin') { header('Location: ../landing/login/login.php'); exit; }
include __DIR__ . '/../database/database.php';

$current_page = 'bookings';

// Detect employees table name (staff or employees)
$empTable = 'employees'; 
$empIdCol = 'employee_id';
$res = $conn->query("SHOW TABLES LIKE 'employees'");
if($res->num_rows == 0) { $empTable = 'staff'; $empIdCol = 'id'; }

// Fetch Bookings
$bookingsQuery = "SELECT b.*, 
    s.service_name, 
    c.name as customer_name,
    v.make, v.model, v.plate_number,
    e.name as employee_name
    FROM bookings b
    LEFT JOIN services s ON b.service_id = s.service_id
    LEFT JOIN customers c ON b.customer_id = c.customer_id
    LEFT JOIN vehicles v ON b.vehicle_id = v.vehicle_id
    LEFT JOIN $empTable e ON b.employee_id = e.$empIdCol
    ORDER BY b.booking_date DESC, b.booking_time DESC";
$bookingsResult = $conn->query($bookingsQuery);
$bookings = [];
while($row = $bookingsResult->fetch_assoc()) { $bookings[] = $row; }

// Handle delete action
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $bookingId = intval($_POST['booking_id']);
    $deleteQuery = "DELETE FROM bookings WHERE booking_id = $bookingId";
    if($conn->query($deleteQuery)) {
        header('Location: bookings.php?success=Booking deleted successfully');
        exit;
    } else {
        header('Location: bookings.php?error=Failed to delete booking');
        exit;
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; color: #333; display: flex; min-height: 100vh; }
        
        /* SIDEBAR STYLES */
        .admin-sidebar {
            width: 260px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 999;
            left: 0;
            top: 0;
        }

        .admin-sidebar .logo {
            font-size: 1.8rem;
            font-weight: bold;
            padding: 0 1.5rem;
            margin-bottom: 2rem;
            letter-spacing: 0.5px;
        }

        .admin-sidebar nav { display: flex; flex-direction: column; }

        .admin-sidebar .menu-item {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            text-decoration: none;
            color: white;
            font-size: 1rem;
            position: relative;
        }

        .admin-sidebar .menu-item:hover {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: rgba(255, 255, 255, 0.5);
        }

        .admin-sidebar .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left-color: white;
            font-weight: 600;
        }

        .admin-sidebar .menu-item.active::after {
            content: '';
            position: absolute;
            right: 1rem;
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            font-size: 1.5rem;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active { display: block; opacity: 1; }

        .admin-sidebar::-webkit-scrollbar { width: 6px; }
        .admin-sidebar::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.1); }
        .admin-sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.3); border-radius: 3px; }

        .main-content { margin-left: 260px; flex: 1; padding: 2rem; width: calc(100% - 260px); }
        .header { background: white; padding: 1.5rem 2rem; border-radius: 15px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .header h1 { font-size: 1.8rem; color: #333; }
        .card { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); margin-bottom: 2rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0; }
        .card-title { font-size: 1.3rem; font-weight: 600; color: #333; }
        .btn-primary { padding: 0.6rem 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 25px; cursor: pointer; font-weight: 500; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8f9fa; }
        th { padding: 1rem; text-align: left; font-weight: 600; color: #666; font-size: 0.9rem; }
        td { padding: 1rem; border-bottom: 1px solid #f0f0f0; }
        .status-badge { padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; display: inline-block; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #cce5ff; color: #004085; }
        .status-in-progress { background: #e7f3ff; color: #0056b3; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .btn-secondary { padding: 0.4rem 0.8rem; background: #667eea; color: white; border: none; border-radius: 15px; cursor: pointer; font-size: 0.8rem; }
        .btn-danger { padding: 0.4rem 0.8rem; background: #e74c3c; color: white; border: none; border-radius: 15px; cursor: pointer; font-size: 0.8rem; }
        
        @media (max-width: 768px) { 
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.active { transform: translateX(0); }
            .mobile-menu-toggle { display: flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0 !important; width: 100% !important; }
        }
    </style>
</head>
<body>
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <a href="index.php" class="menu-item <?php echo ($current_page === 'index') ? 'active' : ''; ?>">Dashboard</a>
            <a href="bookings.php" class="menu-item <?php echo ($current_page === 'bookings') ? 'active' : ''; ?>">Bookings</a>
            <a href="customers.php" class="menu-item <?php echo ($current_page === 'customers') ? 'active' : ''; ?>">Customers</a>
            <a href="services.php" class="menu-item <?php echo ($current_page === 'services') ? 'active' : ''; ?>">Services</a>
            <a href="staff.php" class="menu-item <?php echo ($current_page === 'staff') ? 'active' : ''; ?>">Staff</a>
            <a href="reports.php" class="menu-item <?php echo ($current_page === 'reports') ? 'active' : ''; ?>">Reports</a>
            <a href="settings.php" class="menu-item <?php echo ($current_page === 'settings') ? 'active' : ''; ?>">Settings</a>
        </nav>
    </aside>

    <button class="mobile-menu-toggle" id="mobileMenuToggle" onclick="toggleSidebar()">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <main class="main-content">
        <div class="header">
            <div>
                <h1>Bookings Management</h1>
                <p style="color: #666;">Manage all bookings</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">All Bookings</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Vehicle</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Assigned Staff</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($bookings)): ?>
                            <tr><td colspan="9" style="text-align:center">No bookings found</td></tr>
                        <?php else: ?>
                            <?php foreach($bookings as $b): ?>
                            <tr>
                                <td>#<?php echo $b['booking_id']; ?></td>
                                <td><?php echo htmlspecialchars($b['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($b['service_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($b['make'] . ' ' . $b['model']); ?>
                                    <br><small><?php echo htmlspecialchars($b['plate_number']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($b['booking_date'])); ?></td>
                                <td style="font-weight:bold; color:#667eea;">₱<?php echo number_format($b['final_amount'] ?? $b['total_amount'], 2); ?></td>
                                <td><?php echo $b['employee_name'] ? htmlspecialchars($b['employee_name']) : '<span style="color:#999">Unassigned</span>'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $b['status'])); ?>">
                                        <?php echo $b['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit_booking.php?id=<?php echo $b['booking_id']; ?>" class="btn-secondary" style="padding: 0.4rem 0.8rem; background: #667eea; color: white; border: none; border-radius: 15px; cursor: pointer; font-size: 0.8rem; text-decoration: none; display: inline-block;">Edit</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this booking?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['booking_id']; ?>">
                                        <button type="submit" class="btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggle = document.getElementById('mobileMenuToggle');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            toggle.innerHTML = sidebar.classList.contains('active') ? '✕' : '☰';
        }

        function closeSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggle = document.getElementById('mobileMenuToggle');
            
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            toggle.innerHTML = '☰';
        }

        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });
    </script>
</body>
</html>