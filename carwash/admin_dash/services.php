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

// Set current page for sidebar
$current_page = 'services';

include __DIR__ . '/../database/database.php';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_service') {
        $name = $conn->real_escape_string($_POST['name']);
        $desc = $conn->real_escape_string($_POST['description']);
        $price = floatval($_POST['price']);
        $duration = intval($_POST['duration']);
        $type = $conn->real_escape_string($_POST['type']);
        $status = $_POST['status'] === 'active' ? 1 : 0;
        $icon = $conn->real_escape_string($_POST['icon'] ?? '🚗');
        
        // Check for features (if you have them in the form)
        $features = isset($_POST['features']) ? json_encode(explode(',', $_POST['features'])) : '[]';

        $sql = "INSERT INTO services (service_name, description, base_price, duration_minutes, service_type, is_active) 
                VALUES ('$name', '$desc', $price, $duration, '$type', $status)";
        
        if ($conn->query($sql)) {
            header('Location: services.php');
            exit;
        } else {
            $error = "Error adding service: " . $conn->error;
        }
    }
    
    if ($action === 'update_service') {
        $id = intval($_POST['service_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $desc = $conn->real_escape_string($_POST['description']);
        $price = floatval($_POST['price']);
        $duration = intval($_POST['duration']);
        $type = $conn->real_escape_string($_POST['type']);
        $status = $_POST['status'] === 'active' ? 1 : 0;
        
        $sql = "UPDATE services SET service_name='$name', description='$desc', base_price=$price, 
                duration_minutes=$duration, service_type='$type', is_active=$status 
                WHERE service_id=$id";
                
        if ($conn->query($sql)) {
            header('Location: services.php');
            exit;
        }
    }

    if ($action === 'delete_service') {
        $id = intval($_POST['service_id']);
        $conn->query("DELETE FROM services WHERE service_id=$id");
        header('Location: services.php');
        exit;
    }
}

// Fetch Services
$servicesResult = $conn->query("SELECT * FROM services ORDER BY service_id ASC");
$services = [];
while ($row = $servicesResult->fetch_assoc()) {
    // Determine icon based on type if not stored
    $row['icon'] = '🚗'; 
    if(strpos($row['service_name'], 'Premium') !== false) $row['icon'] = '✨';
    if(strpos($row['service_name'], 'Ultimate') !== false) $row['icon'] = '💎';
    $services[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Services Management</title>
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
            gap: 1rem;
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
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle:hover { transform: scale(1.05); }

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

        /* MAIN CONTENT */
        .main-content { margin-left: 260px; flex: 1; padding: 2rem; width: calc(100% - 260px); transition: margin-left 0.3s ease; }
        
        .header { background: white; padding: 1.5rem 2rem; border-radius: 15px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
        .header h1 { font-size: 1.8rem; color: #333; }
        
        .btn-primary { padding: 0.8rem 1.8rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 25px; cursor: pointer; font-weight: 500; transition: all 0.3s ease; font-size: 1rem; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        
        .btn-secondary { padding: 0.5rem 1rem; background: white; color: #667eea; border: 2px solid #667eea; border-radius: 20px; cursor: pointer; font-weight: 500; transition: all 0.3s ease; font-size: 0.85rem; }
        .btn-secondary:hover { background: #667eea; color: white; }
        
        .btn-danger { padding: 0.5rem 1rem; background: white; color: #e74c3c; border: 2px solid #e74c3c; border-radius: 20px; cursor: pointer; font-weight: 500; transition: all 0.3s ease; font-size: 0.85rem; }
        .btn-danger:hover { background: #e74c3c; color: white; }
        
        .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .service-card { background: white; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); overflow: hidden; transition: all 0.3s ease; position: relative; }
        .service-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15); }
        .service-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; text-align: center; }
        .service-icon { font-size: 3rem; margin-bottom: 0.5rem; }
        .service-name { font-size: 1.4rem; font-weight: bold; margin-bottom: 0.5rem; }
        .service-price { font-size: 2rem; font-weight: bold; }
        .service-body { padding: 1.5rem; }
        .service-description { color: #666; font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.6; min-height: 50px; }
        .service-stats { display: flex; justify-content: space-around; padding: 1rem 0; border-top: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0; margin-bottom: 1rem; }
        .stat-item { text-align: center; }
        .stat-value { font-size: 1.2rem; font-weight: bold; color: #667eea; }
        .stat-label { font-size: 0.75rem; color: #666; margin-top: 0.2rem; }
        .service-actions { display: flex; gap: 0.5rem; }
        .status-badge { position: absolute; top: 1rem; right: 1rem; padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 2rem; border-radius: 15px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0; }
        .modal-title { font-size: 1.5rem; font-weight: bold; color: #333; }
        .close-btn { font-size: 1.5rem; cursor: pointer; color: #666; background: none; border: none; padding: 0.5rem; line-height: 1; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333; }
        .form-input, .form-textarea, .form-select { width: 100%; padding: 0.8rem; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 1rem; transition: border-color 0.3s ease; }
        .form-input:focus, .form-textarea:focus, .form-select:focus { outline: none; border-color: #667eea; }
        .modal-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 1rem; border-top: 2px solid #f0f0f0; }
        
        @media (max-width: 1024px) { .services-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; } }
        @media (max-width: 768px) { 
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.active { transform: translateX(0); }
            .mobile-menu-toggle { display: flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0; width: 100%; padding: 1rem; }
            .header { padding: 1rem; }
            .header h1 { font-size: 1.5rem; }
            .services-grid { grid-template-columns: 1fr; gap: 1rem; }
            .card { padding: 1rem; }
        }
    </style>
</head>
<body>
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <a href="index.php" class="menu-item <?php echo ($current_page === 'index') ? 'active' : ''; ?>">
                <span class="menu-label">Dashboard</span>
            </a>
            <a href="bookings.php" class="menu-item <?php echo ($current_page === 'bookings') ? 'active' : ''; ?>">
                <span class="menu-label">Bookings</span>
            </a>
            <a href="customers.php" class="menu-item <?php echo ($current_page === 'customers') ? 'active' : ''; ?>">
                <span class="menu-label">Customers</span>
            </a>
            <a href="services.php" class="menu-item <?php echo ($current_page === 'services') ? 'active' : ''; ?>">
                <span class="menu-label">Services</span>
            </a>
            <a href="staff.php" class="menu-item <?php echo ($current_page === 'staff') ? 'active' : ''; ?>">
                <span class="menu-label">Staff</span>
            </a>
            <a href="reports.php" class="menu-item <?php echo ($current_page === 'reports') ? 'active' : ''; ?>">
                <span class="menu-label">Reports</span>
            </a>
            <a href="settings.php" class="menu-item <?php echo ($current_page === 'settings') ? 'active' : ''; ?>">
                <span class="menu-label">Settings</span>
            </a>
        </nav>
    </aside>

    <button class="mobile-menu-toggle" id="mobileMenuToggle" onclick="toggleSidebar()">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <main class="main-content">
        <div class="header">
            <div>
                <h1>Services Management</h1>
                <p style="color: #666; margin-top: 0.3rem;">Manage your car wash services and pricing</p>
            </div>
            <button class="btn-primary" onclick="openAddModal()">+ Add New Service</button>
        </div>

        <div class="services-grid" id="servicesGrid">
            <?php foreach ($services as $svc): ?>
                <div class="service-card">
                    <span class="status-badge status-<?php echo $svc['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $svc['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <div class="service-header">
                        <div class="service-icon"><?php echo $svc['icon']; ?></div>
                        <div class="service-name"><?php echo htmlspecialchars($svc['service_name']); ?></div>
                        <div class="service-price">₱<?php echo number_format($svc['base_price'], 2); ?></div>
                    </div>
                    <div class="service-body">
                        <p class="service-description"><?php echo htmlspecialchars($svc['description']); ?></p>
                        <div class="service-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $svc['duration_minutes']; ?></div>
                                <div class="stat-label">Minutes</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $svc['service_type']; ?></div>
                                <div class="stat-label">Type</div>
                            </div>
                        </div>
                        <div class="service-actions">
                            <button class="btn-secondary" style="flex: 1" onclick='editService(<?php echo json_encode($svc); ?>)'>Edit</button>
                            <form method="POST" onsubmit="return confirm('Delete this service?');" style="display:inline;">
                                <input type="hidden" name="action" value="delete_service">
                                <input type="hidden" name="service_id" value="<?php echo $svc['service_id']; ?>">
                                <button type="submit" class="btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div class="modal" id="serviceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add New Service</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="serviceForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="add_service">
                <input type="hidden" name="service_id" id="serviceId">
                
                <div class="form-group">
                    <label class="form-label">Service Name</label>
                    <input type="text" class="form-input" name="name" id="serviceName" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Price (₱)</label>
                    <input type="number" class="form-input" name="price" id="servicePrice" required min="0" step="0.01">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Duration (minutes)</label>
                    <input type="number" class="form-input" name="duration" id="serviceDuration" required min="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="type" id="serviceType">
                        <option value="Basic">Basic</option>
                        <option value="Premium">Premium</option>
                        <option value="Ultimate">Ultimate</option>
                        <option value="Custom">Custom</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-textarea" name="description" id="serviceDescription" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Icon (Emoji)</label>
                    <input type="text" class="form-input" name="icon" id="serviceIcon" placeholder="🚗">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" id="serviceStatus" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Service</button>
                </div>
            </form>
        </div>
    </div>

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

        // Close sidebar on mobile when clicking outside or resizing
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('adminSidebar');
            const menuBtn = document.getElementById('mobileMenuToggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                    closeSidebar();
                }
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        function openAddModal() {
            document.getElementById('serviceForm').reset();
            document.getElementById('formAction').value = 'add_service';
            document.getElementById('modalTitle').textContent = 'Add New Service';
            document.getElementById('serviceModal').classList.add('active');
        }

        function editService(service) {
            document.getElementById('formAction').value = 'update_service';
            document.getElementById('serviceId').value = service.service_id;
            document.getElementById('serviceName').value = service.service_name;
            document.getElementById('servicePrice').value = service.base_price;
            document.getElementById('serviceDuration').value = service.duration_minutes;
            document.getElementById('serviceDescription').value = service.description;
            document.getElementById('serviceType').value = service.service_type;
            document.getElementById('serviceStatus').value = service.is_active == 1 ? 'active' : 'inactive';
            
            document.getElementById('modalTitle').textContent = 'Edit Service';
            document.getElementById('serviceModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('serviceModal').classList.remove('active');
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('serviceModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>