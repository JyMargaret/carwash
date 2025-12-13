<?php
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
        die("Database connection failed.");
    }
} else {
    die("Database configuration file not found.");
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_staff') {
        try {
            $firstName = $conn->real_escape_string($_POST['first_name']);
            $lastName = $conn->real_escape_string($_POST['last_name']);
            $email = $conn->real_escape_string($_POST['email']);
            $phone = $conn->real_escape_string($_POST['phone']);
            $position = $conn->real_escape_string($_POST['position']);
            $hourlyRate = floatval($_POST['hourly_rate']);
            $hireDate = $conn->real_escape_string($_POST['hire_date']);
            $password = password_hash('password123', PASSWORD_DEFAULT);
            
            // Create user first
            $userSql = "INSERT INTO users (email, password_hash, first_name, last_name, phone, user_type, status) 
                       VALUES ('$email', '$password', '$firstName', '$lastName', '$phone', 'employee', 'active')";
            
            if ($conn->query($userSql)) {
                $userId = $conn->insert_id;
                $empCode = 'EMP' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                $fullName = $firstName . ' ' . $lastName;
                
                $empSql = "INSERT INTO employees (user_id, employee_code, name, position, hire_date, hourly_rate, is_active) 
                          VALUES ($userId, '$empCode', '$fullName', '$position', '$hireDate', $hourlyRate, 1)";
                
                if ($conn->query($empSql)) {
                    echo json_encode(['success' => true, 'message' => 'Staff member added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => $conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
        } catch(Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'update_staff') {
        try {
            $empId = intval($_POST['id']);
            $firstName = $conn->real_escape_string($_POST['first_name']);
            $lastName = $conn->real_escape_string($_POST['last_name']);
            $email = $conn->real_escape_string($_POST['email']);
            $phone = $conn->real_escape_string($_POST['phone']);
            $position = $conn->real_escape_string($_POST['position']);
            $hourlyRate = floatval($_POST['hourly_rate']);
            $status = $_POST['status'] === 'active' ? 1 : 0;
            $fullName = $firstName . ' ' . $lastName;
            
            $empSql = "UPDATE employees SET name='$fullName', position='$position', hourly_rate=$hourlyRate, is_active=$status WHERE employee_id=$empId";
            
            if ($conn->query($empSql)) {
                $userSql = "UPDATE users u 
                           INNER JOIN employees e ON u.user_id = e.user_id 
                           SET u.email='$email', u.phone='$phone', u.first_name='$firstName', u.last_name='$lastName' 
                           WHERE e.employee_id=$empId";
                $conn->query($userSql);
                
                echo json_encode(['success' => true, 'message' => 'Staff member updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
        } catch(Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'delete_staff') {
        try {
            $empId = intval($_POST['id']);
            $sql = "DELETE FROM employees WHERE employee_id=$empId";
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => 'Staff member deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => $conn->error]);
            }
        } catch(Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'get_staff') {
        try {
            $empId = intval($_POST['id']);
            $sql = "SELECT e.*, u.email, u.phone, u.first_name, u.last_name 
                   FROM employees e 
                   LEFT JOIN users u ON e.user_id = u.user_id 
                   WHERE e.employee_id=$empId";
            $result = $conn->query($sql);
            if ($result && $result->num_rows > 0) {
                $staff = $result->fetch_assoc();
                echo json_encode(['success' => true, 'data' => $staff]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Staff not found']);
            }
        } catch(Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Fetch all staff members
$query = "SELECT e.employee_id as id, 
          e.name as full_name,
          e.position,
          e.hourly_rate,
          e.rating,
          e.is_active,
          e.hire_date,
          u.email,
          u.phone,
          CASE WHEN e.is_active = 1 THEN 'active' ELSE 'inactive' END as status,
          COUNT(DISTINCT b.booking_id) as total_bookings,
          COALESCE(SUM(CASE WHEN b.status = 'Completed' THEN b.total_amount ELSE 0 END), 0) as total_revenue,
          COALESCE(AVG(r.rating), 0) as avg_rating
          FROM employees e
          LEFT JOIN users u ON e.user_id = u.user_id
          LEFT JOIN bookings b ON e.employee_id = b.employee_id AND b.status = 'Completed'
          LEFT JOIN reviews r ON e.employee_id = r.employee_id
          GROUP BY e.employee_id
          ORDER BY e.employee_id DESC";
$result = $conn->query($query);
$staff_members = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staff_members[] = $row;
    }
}

// Get summary statistics
$stats_query = "SELECT 
    COUNT(*) as total_staff,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_staff,
    0 as on_leave,
    AVG(hourly_rate) as avg_hourly_rate
    FROM employees";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total_staff' => 0, 'active_staff' => 0, 'on_leave' => 0, 'avg_hourly_rate' => 0];
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
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            padding: 0 1.5rem;
            margin-bottom: 2rem;
        }

        .menu-item {
            padding: 1rem 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            text-decoration: none;
            color: white;
            display: block;
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
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
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

        .btn-primary {
            padding: 0.8rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #666;
        }

        .btn-secondary {
            padding: 0.5rem 1rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            margin-right: 0.5rem;
        }

        .btn-danger {
            padding: 0.5rem 1rem;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 15px;
            cursor: pointer;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .close-btn {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .alert {
            padding: 1rem;
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
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <a href="index.php" class="menu-item">Dashboard</a>
            <a href="bookings.php" class="menu-item">Bookings</a>
            <a href="customers.php" class="menu-item">Customers</a>
            <a href="services.php" class="menu-item">Services</a>
            <a href="staff.php" class="menu-item active">Staff</a>
            <a href="reports.php" class="menu-item">Reports</a>
            <a href="settings.php" class="menu-item">Settings</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="header">
            <h1>Staff Management</h1>
            <p style="color: #666; margin-top: 0.3rem;">Manage your team members</p>
        </div>

        <div id="alertContainer"></div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_staff']; ?></div>
                <div class="stat-label">Total Staff</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_staff']; ?></div>
                <div class="stat-label">Active Staff</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['on_leave']; ?></div>
                <div class="stat-label">On Leave</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₱<?php echo number_format($stats['avg_hourly_rate'], 0); ?></div>
                <div class="stat-label">Avg. Hourly Rate</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Staff Members</h2>
                <button class="btn-primary" onclick="openAddModal()">+ Add Staff</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Contact</th>
                        <th>Hourly Rate</th>
                        <th>Bookings</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_members as $staff): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($staff['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($staff['position']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($staff['email'] ?? 'N/A'); ?><br>
                            <small><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></small>
                        </td>
                        <td>₱<?php echo number_format($staff['hourly_rate'], 2); ?></td>
                        <td><?php echo $staff['total_bookings']; ?></td>
                        <td><?php echo ucfirst($staff['status']); ?></td>
                        <td>
                            <button class="btn-secondary" onclick='editStaff(<?php echo $staff['id']; ?>)'>Edit</button>
                            <button class="btn-danger" onclick="deleteStaff(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars($staff['full_name']); ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div class="modal" id="staffModal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Add Staff</h2>
            <form id="staffForm" onsubmit="saveStaff(event)">
                <input type="hidden" id="staffId" name="id">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" id="firstName" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" id="lastName" name="last_name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Position</label>
                        <select id="position" name="position" required>
                            <option value="">Select Position</option>
                            <option value="Wash Specialist">Wash Specialist</option>
                            <option value="Detailing Expert">Detailing Expert</option>
                            <option value="Service Manager">Service Manager</option>
                            <option value="Customer Service">Customer Service</option>
                            <option value="Technician">Technician</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Hourly Rate (₱)</label>
                        <input type="number" id="hourlyRate" name="hourly_rate" step="0.01" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Hire Date</label>
                        <input type="date" id="hireDate" name="hire_date" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Save</button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Staff';
            document.getElementById('staffForm').reset();
            document.getElementById('staffId').value = '';
            document.getElementById('hireDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('staffModal').classList.add('active');
        }

        function editStaff(id) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_staff&id=' + id
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const staff = result.data;
                    document.getElementById('modalTitle').textContent = 'Edit Staff';
                    document.getElementById('staffId').value = staff.employee_id;
                    document.getElementById('firstName').value = staff.first_name || '';
                    document.getElementById('lastName').value = staff.last_name || '';
                    document.getElementById('email').value = staff.email || '';
                    document.getElementById('phone').value = staff.phone || '';
                    document.getElementById('position').value = staff.position;
                    document.getElementById('hourlyRate').value = staff.hourly_rate;
                    document.getElementById('hireDate').value = staff.hire_date;
                    document.getElementById('status').value = staff.is_active == 1 ? 'active' : 'inactive';
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
                showAlert(result.message, result.success ? 'success' : 'error');
                if (result.success) {
                    closeModal();
                    setTimeout(() => location.reload(), 1500);
                }
            });
        }

        function deleteStaff(id, name) {
            if (confirm(`Delete ${name}?`)) {
                const formData = new FormData();
                formData.append('action', 'delete_staff');
                formData.append('id', id);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    showAlert(result.message, result.success ? 'success' : 'error');
                    if (result.success) {
                        setTimeout(() => location.reload(), 1500);
                    }
                });
            }
        }

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = `<div class="alert alert-${type} show">${message}</div>`;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>