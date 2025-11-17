<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Services Management</title>
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

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .service-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .service-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .service-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .service-name {
            font-size: 1.4rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .service-price {
            font-size: 2rem;
            font-weight: bold;
        }

        .service-body {
            padding: 1.5rem;
        }

        .service-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .service-features {
            list-style: none;
            margin-bottom: 1.5rem;
        }

        .service-features li {
            padding: 0.5rem 0;
            color: #555;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .service-features li::before {
            content: "✓";
            color: #27ae60;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .service-stats {
            display: flex;
            justify-content: space-around;
            padding: 1rem 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 1rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #666;
            margin-top: 0.2rem;
        }

        .service-actions {
            display: flex;
            gap: 0.5rem;
        }

        .status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
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
        .form-textarea,
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
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .features-input-group {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .features-list {
            list-style: none;
            margin-top: 0.5rem;
        }

        .feature-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .remove-feature {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0 0.5rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 2px solid #f0f0f0;
        }

        .search-filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 0.8rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 1rem;
        }

        .filter-select {
            padding: 0.8rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 1rem;
            cursor: pointer;
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

            .services-grid {
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

            .service-actions {
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
                <h1>Services Management</h1>
                <p style="color: #666; margin-top: 0.3rem;">Manage your car wash services and pricing</p>
            </div>
            <button class="btn-primary" onclick="openAddModal()">+ Add New Service</button>
        </div>

        <div class="search-filter-bar">
            <input type="text" class="search-input" placeholder="Search services..." id="searchInput" onkeyup="filterServices()">
            <select class="filter-select" id="statusFilter" onchange="filterServices()">
                <option value="all">All Services</option>
                <option value="active">Active Only</option>
                <option value="inactive">Inactive Only</option>
            </select>
            <select class="filter-select" id="priceFilter" onchange="filterServices()">
                <option value="all">All Prices</option>
                <option value="low">₱0 - ₱300</option>
                <option value="medium">₱301 - ₱600</option>
                <option value="high">₱601+</option>
            </select>
        </div>

        <div class="services-grid" id="servicesGrid">
            <!-- Service cards will be dynamically generated here -->
        </div>
    </main>

    <!-- Add/Edit Service Modal -->
    <div class="modal" id="serviceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add New Service</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="serviceForm" onsubmit="saveService(event)">
                <div class="form-group">
                    <label class="form-label">Service Name</label>
                    <input type="text" class="form-input" id="serviceName" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Price (₱)</label>
                    <input type="number" class="form-input" id="servicePrice" required min="0" step="0.01">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Duration (minutes)</label>
                    <input type="number" class="form-input" id="serviceDuration" required min="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-textarea" id="serviceDescription" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Icon (Emoji)</label>
                    <input type="text" class="form-input" id="serviceIcon" maxlength="2" placeholder="🚗">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Features</label>
                    <div class="features-input-group">
                        <input type="text" class="form-input" id="featureInput" placeholder="Enter a feature">
                        <button type="button" class="btn-secondary" onclick="addFeature()">Add</button>
                    </div>
                    <ul class="features-list" id="featuresList"></ul>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="serviceStatus" required>
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

    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>

    <script>
        let services = [
            {
                id: 1,
                name: 'Basic Wash',
                price: 250,
                duration: 30,
                description: 'Essential exterior wash with soap and water, perfect for regular maintenance.',
                icon: '🚿',
                features: ['Exterior wash', 'Tire cleaning', 'Window cleaning', 'Quick dry'],
                bookings: 203,
                revenue: 50750,
                rating: 4.7,
                status: 'active'
            },
            {
                id: 2,
                name: 'Premium Wash',
                price: 450,
                duration: 45,
                description: 'Complete wash package with interior cleaning and premium products.',
                icon: '✨',
                features: ['Full exterior wash', 'Interior vacuuming', 'Dashboard cleaning', 'Tire shine', 'Air freshener'],
                bookings: 145,
                revenue: 65250,
                rating: 4.9,
                status: 'active'
            },
            {
                id: 3,
                name: 'Ultimate Wash',
                price: 750,
                duration: 90,
                description: 'Our most comprehensive package with detailing and protection services.',
                icon: '💎',
                features: ['Premium exterior wash', 'Deep interior cleaning', 'Leather conditioning', 'Engine bay cleaning', 'Wax protection', 'Headlight restoration'],
                bookings: 89,
                revenue: 66750,
                rating: 4.8,
                status: 'active'
            },
            {
                id: 4,
                name: 'Express Wash',
                price: 150,
                duration: 15,
                description: 'Quick wash for those in a hurry, maintaining your vehicle\'s cleanliness.',
                icon: '⚡',
                features: ['Rapid exterior wash', 'Quick rinse', 'Spot-free dry'],
                bookings: 156,
                revenue: 23400,
                rating: 4.5,
                status: 'active'
            },
            {
                id: 5,
                name: 'Interior Detailing',
                price: 600,
                duration: 120,
                description: 'Deep cleaning and restoration of your vehicle\'s interior.',
                icon: '🧹',
                features: ['Deep vacuum', 'Seat shampooing', 'Carpet cleaning', 'Door panel cleaning', 'Odor removal'],
                bookings: 67,
                revenue: 40200,
                rating: 4.9,
                status: 'active'
            },
            {
                id: 6,
                name: 'Ceramic Coating',
                price: 3500,
                duration: 240,
                description: 'Professional-grade ceramic coating for long-lasting protection and shine.',
                icon: '🛡️',
                features: ['Paint correction', 'Ceramic coating application', 'UV protection', '2-year warranty', 'Hydrophobic finish'],
                bookings: 23,
                revenue: 80500,
                rating: 5.0,
                status: 'active'
            }
        ];

        let currentService = null;
        let currentFeatures = [];

        function renderServices() {
            const grid = document.getElementById('servicesGrid');
            grid.innerHTML = '';
            
            services.forEach(service => {
                const card = document.createElement('div');
                card.className = 'service-card';
                card.innerHTML = `
                    <span class="status-badge status-${service.status}">${service.status === 'active' ? 'Active' : 'Inactive'}</span>
                    <div class="service-header">
                        <div class="service-icon">${service.icon}</div>
                        <div class="service-name">${service.name}</div>
                        <div class="service-price">₱${service.price.toLocaleString()}</div>
                    </div>
                    <div class="service-body">
                        <p class="service-description">${service.description}</p>
                        <ul class="service-features">
                            ${service.features.map(f => `<li>${f}</li>`).join('')}
                        </ul>
                        <div class="service-stats">
                            <div class="stat-item">
                                <div class="stat-value">${service.bookings}</div>
                                <div class="stat-label">Bookings</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">₱${(service.revenue / 1000).toFixed(1)}k</div>
                                <div class="stat-label">Revenue</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${service.rating}</div>
                                <div class="stat-label">Rating</div>
                            </div>
                        </div>
                        <div class="service-actions">
                            <button class="btn-secondary" onclick="editService(${service.id})" style="flex: 1">Edit</button>
                            <button class="btn-secondary" onclick="toggleServiceStatus(${service.id})" style="flex: 1">
                                ${service.status === 'active' ? 'Deactivate' : 'Activate'}
                            </button>
                            <button class="btn-danger" onclick="deleteService(${service.id})">Delete</button>
                        </div>
                    </div>
                `;
                grid.appendChild(card);
            });
        }

        function openAddModal() {
            currentService = null;
            currentFeatures = [];
            document.getElementById('modalTitle').textContent = 'Add New Service';
            document.getElementById('serviceForm').reset();
            document.getElementById('featuresList').innerHTML = '';
            document.getElementById('serviceModal').classList.add('active');
        }

        function editService(id) {
            currentService = services.find(s => s.id === id);
            if (!currentService) return;
            
            currentFeatures = [...currentService.features];
            document.getElementById('modalTitle').textContent = 'Edit Service';
            document.getElementById('serviceName').value = currentService.name;
            document.getElementById('servicePrice').value = currentService.price;
            document.getElementById('serviceDuration').value = currentService.duration;
            document.getElementById('serviceDescription').value = currentService.description;
            document.getElementById('serviceIcon').value = currentService.icon;
            document.getElementById('serviceStatus').value = currentService.status;
            
            renderFeatures();
            document.getElementById('serviceModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('serviceModal').classList.remove('active');
        }

        function addFeature() {
            const input = document.getElementById('featureInput');
            const feature = input.value.trim();
            
            if (feature) {
                currentFeatures.push(feature);
                input.value = '';
                renderFeatures();
            }
        }

        function removeFeature(index) {
            currentFeatures.splice(index, 1);
            renderFeatures();
        }

        function renderFeatures() {
            const list = document.getElementById('featuresList');
            list.innerHTML = currentFeatures.map((feature, index) => `
                <li class="feature-item">
                    <span>${feature}</span>
                    <button type="button" class="remove-feature" onclick="removeFeature(${index})">×</button>
                </li>
            `).join('');
        }

        function saveService(event) {
            event.preventDefault();
            
            const serviceData = {
                name: document.getElementById('serviceName').value,
                price: parseFloat(document.getElementById('servicePrice').value),
                duration: parseInt(document.getElementById('serviceDuration').value),
                description: document.getElementById('serviceDescription').value,
                icon: document.getElementById('serviceIcon').value || '🚗',
                features: currentFeatures,
                status: document.getElementById('serviceStatus').value
            };
            
            if (currentService) {
                Object.assign(currentService, serviceData);
            } else {
                serviceData.id = Math.max(...services.map(s => s.id)) + 1;
                serviceData.bookings = 0;
                serviceData.revenue = 0;
                serviceData.rating = 0;
                services.push(serviceData);
            }
            
            renderServices();
            closeModal();
            alert(`Service ${currentService ? 'updated' : 'added'} successfully!`);
        }

        function toggleServiceStatus(id) {
            const service = services.find(s => s.id === id);
            if (service) {
                service.status = service.status === 'active' ? 'inactive' : 'active';
                renderServices();
            }
        }

        function deleteService(id) {
            if (confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
                services = services.filter(s => s.id !== id);
                renderServices();
                alert('Service deleted successfully!');
            }
        }

        function filterServices() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const priceFilter = document.getElementById('priceFilter').value;
            
            const cards = document.querySelectorAll('.service-card');
            
            services.forEach((service, index) => {
                const card = cards[index];
                if (!card) return;
                
                let show = true;
                
                if (searchTerm && !service.name.toLowerCase().includes(searchTerm) && 
                    !service.description.toLowerCase().includes(searchTerm)) {
                    show = false;
                }
                
                if (statusFilter !== 'all' && service.status !== statusFilter) {
                    show = false;
                }
                
                if (priceFilter !== 'all') {
                    if (priceFilter === 'low' && service.price > 300) show = false;
                    if (priceFilter === 'medium' && (service.price <= 300 || service.price > 600)) show = false;
                    if (priceFilter === 'high' && service.price <= 600) show = false;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            const modal = document.getElementById('serviceModal');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
            
            if (event.target === modal) {
                closeModal();
            }
        });

        document.getElementById('featureInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addFeature();
            }
        });

        renderServices();
    </script>
</body>
</html>