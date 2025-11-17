<?php
// Database configuration
$host = 'localhost';
$dbname = 'smartwash_db';
$username = 'root';
$password = '';

// Create database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_staff':
            try {
                $stmt = $pdo->prepare("INSERT INTO staff (first_name, last_name, email, phone, position, hourly_rate, hire_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['position'],
                    $_POST['hourly_rate'],
                    $_POST['hire_date'],
                    $_POST['status']
                ]);
                echo json_encode(['success' => true, 'message' => 'Staff member added successfully']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_staff':
            try {
                $stmt = $pdo->prepare("UPDATE staff SET first_name=?, last_name=?, email=?, phone=?, position=?, hourly_rate=?, status=? WHERE id=?");
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['position'],
                    $_POST['hourly_rate'],
                    $_POST['status'],
                    $_POST['id']
                ]);
                echo json_encode(['success' => true, 'message' => 'Staff member updated successfully']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_staff':
            try {
                $stmt = $pdo->prepare("DELETE FROM staff WHERE id=?");
                $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => true, 'message' => 'Staff member deleted successfully']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_staff':
            try {
                $stmt = $pdo->prepare("SELECT * FROM staff WHERE id=?");
                $stmt->execute([$_POST['id']]);
                $staff = $stmt->fetch();
                echo json_encode(['success' => true, 'data' => $staff]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Fetch all staff members with statistics
$query = "SELECT s.*, 
          COUNT(DISTINCT b.id) as total_bookings,
          COALESCE(SUM(b.total_amount), 0) as total_revenue,
          COALESCE(AVG(r.rating), 0) as avg_rating
          FROM staff s
          LEFT JOIN bookings b ON s.id = b.staff_id AND b.status = 'completed'
          LEFT JOIN reviews r ON s.id = r.staff_id
          GROUP BY s.id
          ORDER BY s.id DESC";
$staff_members = $pdo->query($query)->fetchAll();

// Get summary statistics
$stats_query = "SELECT 
    COUNT(*) as total_staff,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_staff,
    SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as on_leave,
    AVG(hourly_rate) as avg_hourly_rate
    FROM staff";
$stats = $pdo->query($stats_query)->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Staff Management</title>
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

        .btn-primary {
            padding: 0.8rem 1.8rem;
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

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            font-size: 2rem;
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

        .search-filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 0.8rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 1rem;
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

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-on_leave {
            background: #fff3cd;
            color: #856404;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .staff-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
            margin-right: 0.5rem;
            vertical-align: middle;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
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
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
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
            font-weight: bold;
            color: #333;
        }

        .close-btn {
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            background: none;
            border: none;
            padding: 0.5rem;
            line-height: 1;
        }

        .close-btn:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 2px solid #f0f0f0;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 999;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-filter-bar {
                flex-direction: column;
            }

            .search-input {
                width: 100%;
            }

            .mobile-menu-btn {
                display: block;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .action-buttons {
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
                <h1>Staff Management</h1>
                <p style="color: #666; margin-top: 0.3rem;">Manage your team members and track performance</p>
            </div>
            <button class="btn-primary" onclick="openAddModal()">+ Add New Staff</button>
        </div>

        <div id="alertContainer"></div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">👥</div>
                </div>
                <div class="stat-value"><?php echo $stats['total_staff']; ?></div>
                <div class="stat-label">Total Staff</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">✅</div>
                </div>
                <div class="stat-value"><?php echo $stats['active_staff']; ?></div>
                <div class="stat-label">Active Staff</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">🏖️</div>
                </div>
                <div class="stat-value"><?php echo $stats['on_leave']; ?></div>
                <div class="stat-label">On Leave</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">💰</div>
                </div>
                <div class="stat-value">₱<?php echo number_format($stats['avg_hourly_rate'], 0); ?></div>
                <div class="stat-label">Avg. Hourly Rate</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Staff Members</h2>
            </div>

            <div class="search-filter-bar">
                <input type="text" class="search-input" id="searchInput" placeholder="Search staff members..." onkeyup="filterTable()">
                <select class="filter-select" id="statusFilter" onchange="filterTable()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="on_leave">On Leave</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select class="filter-select" id="positionFilter" onchange="filterTable()">
                    <option value="">All Positions</option>
                    <option value="Wash Specialist">Wash Specialist</option>
                    <option value="Detailing Expert">Detailing Expert</option>
                    <option value="Service Manager">Service Manager</option>
                    <option value="Customer Service">Customer Service</option>
                    <option value="Technician">Technician</option>
                </select>
            </div>

            <div class="table-container">
                <table id="staffTable">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Position</th>
                            <th>Contact</th>
                            <th>Hourly Rate</th>
                            <th>Bookings</th>
                            <th>Revenue</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_members as $staff): ?>
                        <tr>
                            <td>
                                <div class="staff-avatar">
                                    <?php echo strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)); ?>
                                </div>
                                <strong><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($staff['position']); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($staff['email']); ?></div>
                                <div style="font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($staff['phone']); ?></div>
                            </td>
                            <td>₱<?php echo number_format($staff['hourly_rate'], 2); ?>/hr</td>
                            <td><?php echo $staff['total_bookings']; ?></td>
                            <td>₱<?php echo number_format($staff['total_revenue'], 2); ?></td>
                            <td>⭐ <?php echo number_format($staff['avg_rating'], 1); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $staff['status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $staff['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-secondary" onclick="editStaff(<?php echo $staff['id']; ?>)">Edit</button>
                                    <button class="btn-danger" onclick="deleteStaff(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>')">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add/Edit Staff Modal -->
    <div class="modal" id="staffModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add New Staff</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="staffForm" onsubmit="saveStaff(event)">
                <input type="hidden" id="staffId" name="id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-input" id="firstName" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-input" id="lastName" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" class="form-input" id="phone" name="phone" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <select class="form-select" id="position" name="position" required>
                            <option value="">Select Position</option>
                            <option value="Wash Specialist">Wash Specialist</option>
                            <option value="Detailing Expert">Detailing Expert</option>
                            <option value="Service Manager">Service Manager</option>
                            <option value="Customer Service">Customer Service</option>
                            <option value="Technician">Technician</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Hourly Rate (₱)</label>
                        <input type="number" class="form-input" id="hourlyRate" name="hourly_rate" step="0.01" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Hire Date</label>
                        <input type="date" class="form-input" id="hireDate" name="hire_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="on_leave">On Leave</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Staff</button>
                </div>
            </form>
        </div>
    </div>

    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Staff';
            document.getElementById('staffForm').reset();
            document.getElementById('staffId').value = '';
            document.getElementById('hireDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('staffModal').classList.add('active');
        }

        function editStaff(id) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_staff&id=' + id
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const staff = result.data;
                    document.getElementById('modalTitle').textContent = 'Edit Staff';
                    document.getElementById('staffId').value = staff.id;
                    document.getElementById('firstName').value = staff.first_name;
                    document.getElementById('lastName').value = staff.last_name;
                    document.getElementById('email').value = staff.email;
                    document.getElementById('phone').value = staff.phone;
                    document.getElementById('position').value = staff.position;
                    document.getElementById('hourlyRate').value = staff.hourly_rate;
                    document.getElementById('hireDate').value = staff.hire_date;
                    document.getElementById('status').value = staff.status;
                    document.getElementById('staffModal').classList.add('active');
                }
            });
        }

        function closeModal() {
            document.getElementById('staffModal').classList.remove('active');
        }

        function saveStaff(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const staffId = document.getElementById('staffId').value;
            formData.append('action', staffId ? 'update_staff' : 'add_staff');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showAlert(result.message, 'success');
                    closeModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(result.message, 'error');
                }
            })
            .catch(error => {
                showAlert('An error occurred: ' + error.message, 'error');
            });
        }

        function deleteStaff(id, name) {
            if (confirm(`Are you sure you want to delete ${name}? This action cannot be undone.`)) {
                const formData = new FormData();
                formData.append('action', 'delete_staff');
                formData.append('id', id);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        showAlert(result.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert(result.message, 'error');
                    }
                })
                .catch(error => {
                    showAlert('An error occurred: ' + error.message, 'error');
                });
            }
        }

        function filterTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const positionFilter = document.getElementById('positionFilter').value.toLowerCase();
            const table = document.getElementById('staffTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const name = row.cells[0].textContent.toLowerCase();
                const position = row.cells[1].textContent.toLowerCase();
                const contact = row.cells[2].textContent.toLowerCase();
                const statusText = row.cells[7].textContent.toLowerCase();

                let showRow = true;

                if (searchInput && !name.includes(searchInput) && !contact.includes(searchInput)) {
                    showRow = false;
                }

                if (statusFilter && !statusText.includes(statusFilter)) {
                    showRow = false;
                }

                if (positionFilter && !position.includes(positionFilter)) {
                    showRow = false;
                }

                row.style.display = showRow ? '' : 'none';
            }
        }

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} show`;
            alertDiv.textContent = message;
            alertContainer.innerHTML = '';
            alertContainer.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.remove(), 300);
            }, 5000);
        }

        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            const modal = document.getElementById('staffModal');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
            
            if (event.target === modal) {
                closeModal();
            }
        });
    </script>
</body>
</html>