<?php
session_start();
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'admin') {
    header('Location: ../landing/login/login.php');
    exit;
}
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
        
        // Check for features
        $features = isset($_POST['features']) ? json_encode(explode(',', $_POST['features'])) : '[]';

        $sql = "INSERT INTO services (service_name, description, base_price, duration_minutes, service_type, is_active) 
                VALUES ('$name', '$desc', $price, $duration, '$type', $status)";
        
        if ($conn->query($sql)) {
            // Note: If you have a separate features table or column, handle it here. 
            // For now we just insert the main service data based on your schema.
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
        /* Keeping original styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; color: #333; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem 0; position: fixed; height: 100vh; overflow-y: auto; transition: transform 0.3s ease; }
        .logo { font-size: 1.8rem; font-weight: bold; padding: 0 1.5rem; margin-bottom: 2rem; }
        .menu-item { padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.3s ease; border-left: 4px solid transparent; text-decoration: none; color: white; }
        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.2); border-left-color: white; }
        .main-content { margin-left: 260px; flex: 1; padding: 2rem; width: calc(100% - 260px); }
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
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; width: 260px; } 
            .main-content { margin-left: 0; width: 100%; padding: 1rem; }
            .header { padding: 1rem; }
            .header h1 { font-size: 1.5rem; }
            .services-grid { grid-template-columns: 1fr; gap: 1rem; }
            .card { padding: 1rem; }
            table { font-size: 0.8rem; }
            th, td { padding: 0.5rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.5rem; }
            .header h1 { font-size: 1.2rem; }
            .card { padding: 0.75rem; }
            table { font-size: 0.7rem; }
            th, td { padding: 0.4rem; }
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
            <a href="services.php" class="menu-item active">Services</a>
            <a href="staff.php" class="menu-item">Staff</a>
            <a href="reports.php" class="menu-item">Reports</a>
            <a href="settings.php" class="menu-item">Settings</a>
        </nav>
    </aside>

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