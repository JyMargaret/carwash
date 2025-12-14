<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Customer Support</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

        .back-btn {
            padding: 0.5rem 1.5rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .back-btn:hover {
            background: #764ba2;
            transform: translateY(-2px);
        }

        .chat-container {
            flex: 1;
            display: flex;
            max-width: 1200px;
            width: 100%;
            margin: 2rem auto;
            gap: 1rem;
            padding: 0 1rem;
            overflow: hidden;
        }

        .sidebar {
            width: 300px;
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .sidebar h3 {
            margin-bottom: 1rem;
            color: #333;
            font-size: 1.2rem;
        }

        .quick-questions {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .quick-question-btn {
            padding: 0.8rem;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            text-align: left;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            color: #333;
        }

        .quick-question-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateX(5px);
        }

        .faq-section {
            margin-top: 2rem;
        }

        .faq-item {
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            background: #e9ecef;
        }

        .faq-question {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .faq-answer {
            color: #666;
            font-size: 0.9rem;
            display: none;
        }

        .faq-item.active .faq-answer {
            display: block;
        }

        .chat-main {
            flex: 1;
            background: white;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .chat-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .agent-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .agent-info h3 {
            font-size: 1.2rem;
            margin-bottom: 0.2rem;
        }

        .agent-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4ade80;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .chat-messages {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            display: flex;
            gap: 1rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .message.user .message-avatar {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .message-content {
            max-width: 70%;
        }

        .message-bubble {
            padding: 1rem 1.5rem;
            border-radius: 20px;
            background: #f8f9fa;
            color: #333;
            word-wrap: break-word;
        }

        .message.user .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .message-time {
            font-size: 0.75rem;
            color: #999;
            margin-top: 0.3rem;
            padding: 0 0.5rem;
        }

        .typing-indicator {
            display: none;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-radius: 20px;
            width: fit-content;
        }

        .typing-indicator.active {
            display: flex;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #999;
            animation: typing 1.4s infinite;
        }

        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
            }
            30% {
                transform: translateY(-10px);
            }
        }

        .chat-input-area {
            padding: 1.5rem;
            background: white;
            border-top: 2px solid #f0f0f0;
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .attachment-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .attachment-btn:hover {
            background: #e9ecef;
            transform: scale(1.1);
        }

        .chat-input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 1rem;
            resize: none;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .chat-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .send-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .send-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .mode-toggle {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .mode-btn {
            padding: 0.4rem 1rem;
            border: 2px solid white;
            background: transparent;
            color: white;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .mode-btn.active {
            background: white;
            color: #667eea;
        }

        .mode-btn:hover {
            transform: scale(1.05);
        }

        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
                margin: 1rem auto;
            }

            .sidebar {
                width: 100%;
                max-height: 200px;
            }

            .message-content {
                max-width: 85%;
            }

            .navbar {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">SmartWash Support</div>
        <a href="../index.php" class="back-btn">← Back to Dashboard</a>
    </nav>

    <div class="chat-container">
        <div class="sidebar">
            <h3>Quick Questions</h3>
            <div class="quick-questions">
                <button class="quick-question-btn" onclick="sendQuickQuestion('How do I book a car wash?')">
                    📅 How do I book a car wash?
                </button>
                <button class="quick-question-btn" onclick="sendQuickQuestion('What are your operating hours?')">
                    🕐 What are your operating hours?
                </button>
                <button class="quick-question-btn" onclick="sendQuickQuestion('How can I cancel my booking?')">
                    ❌ Cancel my booking
                </button>
                <button class="quick-question-btn" onclick="sendQuickQuestion('What payment methods do you accept?')">
                    💳 Payment methods
                </button>
                <button class="quick-question-btn" onclick="sendQuickQuestion('Do you offer loyalty rewards?')">
                    ⭐ Loyalty rewards
                </button>
            </div>

            <div class="faq-section">
                <h3>FAQ</h3>
                <div class="faq-item" onclick="toggleFAQ(this)">
                    <div class="faq-question">📍 Where are you located?</div>
                    <div class="faq-answer">We're located at 123 Main Street, Zamboanga City. You can find us near the city center.</div>
                </div>
                <div class="faq-item" onclick="toggleFAQ(this)">
                    <div class="faq-question">⏱️ How long does a wash take?</div>
                    <div class="faq-answer">Basic wash takes 15-20 minutes, Premium 30-40 minutes, and Ultimate wash 60-90 minutes.</div>
                </div>
                <div class="faq-item" onclick="toggleFAQ(this)">
                    <div class="faq-question">💰 What are your prices?</div>
                    <div class="faq-answer">Basic Wash: ₱250, Premium Wash: ₱450, Ultimate Wash: ₱750. Prices may vary by vehicle size.</div>
                </div>
            </div>
        </div>

        <div class="chat-main">
            <div class="chat-header">
                <div class="agent-avatar">👨‍💼</div>
                <div class="agent-info">
                    <h3>Support Agent</h3>
                    <div class="agent-status">
                        <span class="status-dot"></span>
                        <span>Online - Average response time: 2 min</span>
                    </div>
                </div>
                <div class="mode-toggle" style="margin-left: auto;">
                    <button class="mode-btn active" id="aiModeBtn" onclick="switchMode('ai')">🤖 AI Support</button>
                    <button class="mode-btn" id="staffModeBtn" onclick="switchMode('staff')">👨‍💼 Staff Chat</button>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages">
                <div class="message agent">
                    <div class="message-avatar">👨‍💼</div>
                    <div class="message-content">
                        <div class="message-bubble">
                            Hello! Welcome to SmartWash Customer Support. 👋<br><br>
                            I'm here to help you with any questions about our car wash services, bookings, or account. How can I assist you today?
                        </div>
                        <div class="message-time">Just now</div>
                    </div>
                </div>

                <div class="message agent">
                    <div class="message-avatar">👨‍💼</div>
                    <div class="message-content">
                        <div class="typing-indicator" id="typingIndicator">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chat-input-area">
                <button class="attachment-btn" onclick="alert('File attachment feature coming soon!')">📎</button>
                <textarea 
                    class="chat-input" 
                    id="messageInput" 
                    placeholder="Type your message here..." 
                    rows="1"
                    onkeypress="handleKeyPress(event)"
                ></textarea>
                <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                    ➤
                </button>
            </div>
        </div>
    </div>

    <script>
        const aiResponses = {
            booking: {
                keywords: ['book', 'booking', 'appointment', 'schedule', 'reserve', 'reserve a slot'],
                answers: [
                    'To book a car wash, simply go to your dashboard and click the "New Booking" button. Select your vehicle, choose a service, pick a date and time, and confirm! You can also call us at (062) 123-4567.',
                    'Booking is easy! Navigate to the booking section, choose your preferred service (Basic, Premium, or Ultimate), select your vehicle and preferred time slot, and confirm. You\'ll receive a confirmation email with all the details.',
                    'You can book through your SmartWash dashboard. Click "New Booking", pick your service type and vehicle, choose your desired date and time, and we\'ll have it reserved for you!',
                ]
            },
            hours: {
                keywords: ['hour', 'time', 'open', 'close', 'schedule', 'available', 'when', 'timing'],
                answers: [
                    'We are open Monday to Sunday, 7:00 AM to 7:00 PM. We accept bookings from 8:00 AM to 6:00 PM to ensure quality service.',
                    'Our operating hours are 7:00 AM to 7:00 PM daily, 7 days a week! Booking slots are available from 8:00 AM to 6:00 PM.',
                    'We\'re open from 7 AM to 7 PM every day of the week. You can book anytime between 8:00 AM and 6:00 PM for your convenience.',
                ]
            },
            cancel: {
                keywords: ['cancel', 'cancellation', 'change', 'modify', 'reschedule'],
                answers: [
                    'You can cancel your booking from your dashboard by going to "Booking History" and clicking the cancel button. Please cancel at least 2 hours before your scheduled time to avoid cancellation fees.',
                    'To cancel a booking, log in to your account, go to "Booking History", find your booking, and click "Cancel". Remember to cancel 2+ hours before your appointment time!',
                    'Cancellations can be done through your dashboard under "Booking History". We ask that you cancel at least 2 hours before your booking to avoid any charges.',
                ]
            },
            payment: {
                keywords: ['payment', 'pay', 'gcash', 'paypal', 'card', 'credit', 'debit', 'cash', 'method'],
                answers: [
                    'We accept cash, credit/debit cards (Visa, Mastercard), GCash, PayMaya, and bank transfers. Payment can be made on-site or through our online booking system.',
                    'Multiple payment options available: Cash, Visa, Mastercard, GCash, PayMaya, and bank transfers. Choose what\'s most convenient for you!',
                    'You can pay using: cash at our location, credit/debit cards, GCash, PayMaya, or direct bank transfer. All options are secure and convenient.',
                ]
            },
            loyalty: {
                keywords: ['loyalty', 'reward', 'point', 'membership', 'benefits', 'discount'],
                answers: [
                    'Yes! We have a loyalty rewards program. Earn points with every wash: Basic (10 pts), Premium (20 pts), Ultimate (35 pts). Redeem points for discounts and free services!',
                    'Our rewards program gives you points with each service: 10 points for Basic, 20 for Premium, and 35 for Ultimate washes. Accumulate points and redeem for amazing rewards!',
                    'Join our loyalty rewards program! Earn points on every service and redeem them for exclusive discounts, free washes, and special offers.',
                ]
            },
            price: {
                keywords: ['price', 'cost', 'expensive', 'affordable', 'much', 'rate', 'fee', 'charge'],
                answers: [
                    'Our prices are: Basic Wash - ₱250, Premium Wash - ₱450, Ultimate Wash - ₱750. Larger vehicles (SUVs, vans) have a small surcharge.',
                    'Pricing: Basic Wash ₱250 | Premium Wash ₱450 | Ultimate Wash ₱750. Add ₱50-100 for larger vehicles. Great value for quality service!',
                    'We offer competitive prices: Basic ₱250, Premium ₱450, Ultimate ₱750. No hidden fees, transparent pricing for all services.',
                ]
            },
            location: {
                keywords: ['location', 'where', 'address', 'find', 'situated', 'located'],
                answers: [
                    'We are located at 123 Main Street, Zamboanga City, near the city center. Look for our purple and blue signage!',
                    'Find us at 123 Main Street, Zamboanga City - right in the heart of the city. Easy to spot with our distinctive purple and blue branding!',
                    'Our location: 123 Main Street, Zamboanga City. We\'re centrally located and easy to access.',
                ]
            },
            duration: {
                keywords: ['long', 'duration', 'how long', 'time taken', 'minutes'],
                answers: [
                    'Service duration: Basic Wash (15-20 min), Premium Wash (30-40 min), Ultimate Wash (60-90 min). Times may vary based on vehicle condition.',
                    'Basic takes 15-20 minutes, Premium takes 30-40 minutes, and Ultimate takes 60-90 minutes. Exact time depends on your vehicle\'s condition.',
                    'Our typical service times: Basic 15-20 min, Premium 30-40 min, Ultimate 60-90 min. We ensure quality without unnecessary delays.',
                ]
            },
            greeting: {
                keywords: ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'greetings'],
                answers: [
                    'Hello! Welcome to SmartWash! 👋 How can I help you today?',
                    'Hi there! 👋 Great to have you here. What can I assist you with?',
                    'Hey! Welcome to SmartWash! 😊 What questions do you have?',
                ]
            },
            thanks: {
                keywords: ['thank', 'thanks', 'appreciate', 'grateful'],
                answers: [
                    'You\'re welcome! Is there anything else I can help you with? 😊',
                    'Happy to help! Feel free to ask if you have more questions.',
                    'Thank you for choosing SmartWash! Let me know if you need anything else.',
                ]
            },
            vehicle: {
                keywords: ['vehicle', 'car', 'vehicle type', 'motorcycle', 'truck', 'suv'],
                answers: [
                    'We service most vehicle types: sedans, SUVs, trucks, vans, and motorcycles. Pricing adjusts for larger vehicles. What\'s your vehicle type?',
                    'All vehicle types welcome! From compact cars to large SUVs and trucks. Check our pricing page for specific rates for your vehicle.',
                    'We wash all kinds of vehicles! Cars, SUVs, trucks, vans, motorcycles - you name it. Let us know what you\'re driving!',
                ]
            },
            service: {
                keywords: ['service', 'wash', 'cleaning', 'detail', 'package', 'what do you offer'],
                answers: [
                    'We offer three service packages: Basic (exterior wash), Premium (exterior + interior vacuum), and Ultimate (full detail with wax and polish). Choose what suits your needs!',
                    'Three amazing options: Basic for quick clean, Premium for deeper clean, and Ultimate for complete detailing. All prices and details available in your dashboard!',
                    'Our services: Basic Wash (exterior), Premium Wash (exterior + interior), and Ultimate Detail (complete package with wax). Pick the best for your vehicle!',
                ]
            },
            help: {
                keywords: ['help', 'support', 'issue', 'problem', 'error', 'assist'],
                answers: [
                    'I\'m here to help! Tell me what you\'re experiencing and I\'ll do my best to assist. If it\'s urgent, you can also call us at (062) 123-4567.',
                    'Happy to help! Describe your issue and I\'ll find a solution for you. For immediate assistance, our team is available by phone too.',
                    'What seems to be the issue? I\'m ready to help! Or contact our support team directly at (062) 123-4567.',
                ]
            },
        };

        let currentEmployeeId = null;
        let chatMode = 'ai'; // 'ai' or 'staff'

        function getCurrentTime() {
            const now = new Date();
            return now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }

        function getRandomResponse(category) {
            const answers = aiResponses[category]?.answers || [];
            if (answers.length === 0) return getDefaultResponse();
            return answers[Math.floor(Math.random() * answers.length)];
        }

        function getDefaultResponse() {
            const defaults = [
                'Thank you for your question! I\'m here to help. Could you provide more details or rephrase your question?',
                'I\'m not entirely sure about that. Can you tell me more? You can also call our team at (062) 123-4567 for detailed assistance.',
                'That\'s a great question! While I might not have all the details, our support team can help. Feel free to chat with a staff member or call us.',
                'I want to give you accurate information! If you need specific details about that, please contact our team at (062) 123-4567.',
            ];
            return defaults[Math.floor(Math.random() * defaults.length)];
        }

        function getResponse(message) {
            const msg = message.toLowerCase().trim();
            
            // Check each category for keyword matches
            for (const [category, data] of Object.entries(aiResponses)) {
                for (const keyword of data.keywords) {
                    if (msg.includes(keyword)) {
                        return getRandomResponse(category);
                    }
                }
            }
            
            // If no keywords match, return a default response
            return getDefaultResponse();
        }

        function switchMode(mode) {
            chatMode = mode;
            
            // Update button styles
            document.getElementById('aiModeBtn').classList.toggle('active', mode === 'ai');
            document.getElementById('staffModeBtn').classList.toggle('active', mode === 'staff');
            
            // Clear messages and show appropriate greeting
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.innerHTML = '';
            
            if (mode === 'ai') {
                messagesContainer.innerHTML = `
                    <div class="message agent">
                        <div class="message-avatar">🤖</div>
                        <div class="message-content">
                            <div class="message-bubble">
                                Hello! I'm the SmartWash AI Assistant. 🤖<br><br>
                                I can help you with quick answers about our services, bookings, pricing, and more. How can I assist you?
                            </div>
                            <div class="message-time">Just now</div>
                        </div>
                    </div>
                    <div class="message agent">
                        <div class="message-avatar">🤖</div>
                        <div class="message-content">
                            <div class="typing-indicator" id="typingIndicator">
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                messagesContainer.innerHTML = `
                    <div class="message agent">
                        <div class="message-avatar">👨‍💼</div>
                        <div class="message-content">
                            <div class="message-bubble">
                                Hi! You're now chatting with our support team. 👋<br><br>
                                A staff member will respond to your messages shortly. How can we help you today?
                            </div>
                            <div class="message-time">Just now</div>
                        </div>
                    </div>
                `;
                // Load existing staff messages
                loadMessages();
            }
            
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (message === '') return;

            addMessage(message, 'user');
            input.value = '';
            
            if (chatMode === 'ai') {
                // AI mode - auto-respond
                showTyping();
                const delay = 800 + Math.random() * 1200; // 800-2000ms delay
                setTimeout(() => {
                    const response = getResponse(message);
                    hideTyping();
                    addMessage(response, 'agent');
                }, delay);
            } else {
                // Staff chat mode - send to backend
                const formData = new FormData();
                formData.append('message', message);
                if (currentEmployeeId) {
                    formData.append('employee_id', currentEmployeeId);
                }

                fetch('send_message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Message sent successfully');
                        loadMessages();
                    } else {
                        console.error('Error sending message:', data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }

        function loadMessages() {
            const empId = currentEmployeeId ? '?employee_id=' + currentEmployeeId : '';
            
            fetch('get_messages.php' + empId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages) {
                    displayMessages(data.messages);
                }
            })
            .catch(error => console.error('Error loading messages:', error));
        }

        function displayMessages(messages) {
            const messagesContainer = document.getElementById('chatMessages');
            // Clear only user and employee messages, keep greeting
            const messageElements = messagesContainer.querySelectorAll('.message');
            messageElements.forEach((el, index) => {
                if (index > 0) {
                    el.remove();
                }
            });

            // Add loaded messages
            messages.forEach(msg => {
                if (msg.sender_type === 'user') {
                    addMessage(msg.message, 'user', msg.created_at);
                } else if (msg.sender_type === 'employee') {
                    addMessage(msg.message, 'agent', msg.created_at);
                }
            });
        }

        function addMessage(text, sender, timestamp = null) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;
            
            const avatar = sender === 'user' ? '👤' : (chatMode === 'ai' ? '🤖' : '👨‍💼');
            const time = timestamp ? new Date(timestamp).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : getCurrentTime();
            
            messageDiv.innerHTML = `
                <div class="message-avatar">${avatar}</div>
                <div class="message-content">
                    <div class="message-bubble">${text}</div>
                    <div class="message-time">${time}</div>
                </div>
            `;
            
            const typingIndicator = messagesContainer.querySelector('.typing-indicator');
            if (typingIndicator && typingIndicator.parentElement.parentElement) {
                messagesContainer.insertBefore(messageDiv, typingIndicator.parentElement.parentElement);
            } else {
                messagesContainer.appendChild(messageDiv);
            }
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function showTyping() {
            const typingIndicator = document.getElementById('typingIndicator');
            if (typingIndicator) {
                typingIndicator.classList.add('active');
            }
        }

        function hideTyping() {
            const typingIndicator = document.getElementById('typingIndicator');
            if (typingIndicator) {
                typingIndicator.classList.remove('active');
            }
        }

        function toggleFAQ(element) {
            element.classList.toggle('active');
        }

        function handleKeyPress(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        }

        function sendQuickQuestion(question) {
            document.getElementById('messageInput').value = question;
            sendMessage();
        }

        // Auto-resize textarea
        const textarea = document.getElementById('messageInput');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Load existing messages on page load
        window.addEventListener('load', () => {
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        });
    </script>
</body>
</html>