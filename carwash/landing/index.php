<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Professional Car Wash System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
        }

        .navbar {
            position: fixed;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .logo {
            font-size: 2rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: #667eea;
        }

        .profile-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .profile-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 150px 5% 100px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><path d="M0,300 Q300,100 600,300 T1200,300 L1200,600 L0,600 Z" fill="rgba(255,255,255,0.1)"/></svg>') no-repeat bottom;
            background-size: cover;
            animation: wave 20s ease-in-out infinite;
        }

        @keyframes wave {
            0%, 100% { transform: translateX(0) translateY(0); }
            50% { transform: translateX(-50px) translateY(-20px); }
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            animation: fadeInUp 1s ease;
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            animation: fadeInUp 1s ease 0.2s backwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .cta-button {
            display: inline-block;
            padding: 1rem 3rem;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 1s ease 0.4s backwards;
        }

        .cta-button:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        .features {
            padding: 100px 5%;
            background: #f8f9fa;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: #333;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            text-align: center;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #667eea;
        }

        .pricing {
            padding: 100px 5%;
            background: white;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .pricing-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .pricing-card:hover {
            border-color: #667eea;
            transform: scale(1.05);
            box-shadow: 0 20px 50px rgba(102, 126, 234, 0.2);
        }

        .pricing-card.featured {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            transform: scale(1.05);
        }

        .price {
            font-size: 3rem;
            font-weight: bold;
            margin: 1rem 0;
        }

        .price-features {
            list-style: none;
            margin: 2rem 0;
        }

        .price-features li {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .pricing-card.featured .price-features li {
            border-bottom-color: rgba(255, 255, 255, 0.2);
        }

        .contact {
            padding: 100px 5%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .contact-form {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
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
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        footer {
            background: #1a1a1a;
            color: white;
            text-align: center;
            padding: 2rem 5%;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .nav-links {
                display: none;
            }

            .feature-grid,
            .pricing-grid {
                grid-template-columns: 1fr;
            }
        }

        .success-message {
            display: none;
            background: #4caf50;
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">SmartWash</div>
        <div class="nav-right">
            <ul class="nav-links">
                <li><a href="#features">Features</a></li>
                <li><a href="#pricing">Pricing</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <a href="login/login.php" class="profile-icon" title="User Profile" aria-label="Login">
                👤
            </a>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <h1>Welcome to SmartWash</h1>
            <p>The Future of Professional Car Washing Technology</p>
            <a href="#contact" class="cta-button">Get Started Today</a>
        </div>
    </section>

    <section id="features" class="features">
        <h2 class="section-title">Why Choose SmartWash?</h2>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon">💧</div>
                <h3>Eco-Friendly</h3>
                <p>Our advanced water recycling system reduces water usage by 70% while delivering superior cleaning results.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Lightning Fast</h3>
                <p>Complete car wash in under 5 minutes with our automated smart technology and optimized workflow.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🤖</div>
                <h3>Smart Technology</h3>
                <p>AI-powered sensors detect your vehicle type and apply the perfect wash program automatically.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Mobile App</h3>
                <p>Book, pay, and track your wash from your smartphone. Loyalty rewards included.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">✨</div>
                <h3>Premium Quality</h3>
                <p>Professional-grade soaps and waxes that protect your vehicle's paint and finish.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Safe & Secure</h3>
                <p>Touchless options available with soft foam brushes that won't scratch your vehicle.</p>
            </div>
        </div>
    </section>

    <section id="pricing" class="pricing">
        <h2 class="section-title">Choose Your Plan</h2>
        <div class="pricing-grid">
            <div class="pricing-card">
                <h3>Basic Wash</h3>
                <div class="price">₱250</div>
                <ul class="price-features">
                    <li>Exterior Wash</li>
                    <li>Wheel Cleaning</li>
                    <li>Basic Dry</li>
                    <li>Air Freshener</li>
                </ul>
                <a href="#contact" class="cta-button">Select Plan</a>
            </div>
            <div class="pricing-card featured">
                <h3>Premium Wash</h3>
                <div class="price">₱450</div>
                <ul class="price-features">
                    <li>Everything in Basic</li>
                    <li>Interior Vacuum</li>
                    <li>Tire Shine</li>
                    <li>Wax Protection</li>
                    <li>Dashboard Polish</li>
                </ul>
                <a href="#contact" class="cta-button">Select Plan</a>
            </div>
            <div class="pricing-card">
                <h3>Ultimate Wash</h3>
                <div class="price">₱750</div>
                <ul class="price-features">
                    <li>Everything in Premium</li>
                    <li>Engine Bay Cleaning</li>
                    <li>Leather Treatment</li>
                    <li>Ceramic Coating</li>
                    <li>Headlight Restoration</li>
                </ul>
                <a href="#contact" class="cta-button">Select Plan</a>
            </div>
        </div>
    </section>

    <section id="contact" class="contact">
        <h2 class="section-title">Get In Touch</h2>
        <div class="contact-form">
            <div id="successMessage" class="success-message">
                Thank you! We'll contact you shortly.
            </div>
            <form id="contactForm">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="4" required></textarea>
                </div>
                <button type="submit" class="submit-btn">Send Message</button>
            </form>
        </div>
    </section>

    <footer>
        <p>&copy; 2025 SmartWash. All rights reserved.</p>
    </footer>

    <script>
        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Form submission
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(this);
            
            // For PHP integration, uncomment this and point to your PHP file:
            /*
            fetch('process_form.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('successMessage').style.display = 'block';
                this.reset();
            })
            .catch(error => console.error('Error:', error));
            */
            
            // Temporary demo - show success message
            document.getElementById('successMessage').style.display = 'block';
            this.reset();
            setTimeout(() => {
                document.getElementById('successMessage').style.display = 'none';
            }, 5000);
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.padding = '0.5rem 5%';
            } else {
                navbar.style.padding = '1rem 5%';
            }
        });
    </script>
</body>
</html>