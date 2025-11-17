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
        const responses = {
            'book': 'To book a car wash, simply go to your dashboard and click the "New Booking" button. Select your vehicle, choose a service, pick a date and time, and confirm! You can also call us at (062) 123-4567.',
            'hours': 'We are open Monday to Sunday, 7:00 AM to 7:00 PM. We accept bookings from 8:00 AM to 6:00 PM to ensure quality service.',
            'cancel': 'You can cancel your booking from your dashboard by going to "Booking History" and clicking the cancel button. Please cancel at least 2 hours before your scheduled time to avoid cancellation fees.',
            'payment': 'We accept cash, credit/debit cards (Visa, Mastercard), GCash, PayMaya, and bank transfers. Payment can be made on-site or through our online booking system.',
            'loyalty': 'Yes! We have a loyalty rewards program. Earn points with every wash: Basic (10 pts), Premium (20 pts), Ultimate (35 pts). Redeem points for discounts and free services!',
            'price': 'Our prices are: Basic Wash - ₱250, Premium Wash - ₱450, Ultimate Wash - ₱750. Larger vehicles (SUVs, vans) have a small surcharge.',
            'location': 'We are located at 123 Main Street, Zamboanga City, near the city center. Look for our purple and blue signage!',
            'time': 'Service duration: Basic Wash (15-20 min), Premium Wash (30-40 min), Ultimate Wash (60-90 min). Times may vary based on vehicle condition.',
            'default': 'Thank you for your question! Let me help you with that. Could you please provide more details so I can assist you better? Or you can call our hotline at (062) 123-4567 for immediate assistance.'
        };

        function getCurrentTime() {
            const now = new Date();
            return now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (message === '') return;

            addMessage(message, 'user');
            input.value = '';
            
            showTyping();
            
            setTimeout(() => {
                const response = getResponse(message);
                hideTyping();
                addMessage(response, 'agent');
            }, 1500 + Math.random() * 1000);
        }

        function addMessage(text, sender) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;
            
            const avatar = sender === 'user' ? '👤' : '👨‍💼';
            
            messageDiv.innerHTML = `
                <div class="message-avatar">${avatar}</div>
                <div class="message-content">
                    <div class="message-bubble">${text}</div>
                    <div class="message-time">${getCurrentTime()}</div>
                </div>
            `;
            
            const typingIndicator = messagesContainer.querySelector('.message.agent:last-child');
            messagesContainer.insertBefore(messageDiv, typingIndicator);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function getResponse(message) {
            const msg = message.toLowerCase();
            
            if (msg.includes('book') || msg.includes('appointment') || msg.includes('schedule')) {
                return responses.book;
            } else if (msg.includes('hour') || msg.includes('time') || msg.includes('open') || msg.includes('close')) {
                return responses.hours;
            } else if (msg.includes('cancel')) {
                return responses.cancel;
            } else if (msg.includes('payment') || msg.includes('pay') || msg.includes('gcash') || msg.includes('card')) {
                return responses.payment;
            } else if (msg.includes('loyalty') || msg.includes('reward') || msg.includes('point')) {
                return responses.loyalty;
            } else if (msg.includes('price') || msg.includes('cost') || msg.includes('much') || msg.includes('fee')) {
                return responses.price;
            } else if (msg.includes('location') || msg.includes('where') || msg.includes('address')) {
                return responses.location;
            } else if (msg.includes('long') || msg.includes('duration')) {
                return responses.time;
            } else if (msg.includes('hello') || msg.includes('hi') || msg.includes('hey')) {
                return 'Hello! How can I help you today? Feel free to ask about bookings, services, pricing, or anything else!';
            } else if (msg.includes('thank')) {
                return 'You\'re welcome! Is there anything else I can help you with? 😊';
            } else {
                return responses.default;
            }
        }

        function sendQuickQuestion(question) {
            document.getElementById('messageInput').value = question;
            sendMessage();
        }

        function showTyping() {
            document.getElementById('typingIndicator').classList.add('active');
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function hideTyping() {
            document.getElementById('typingIndicator').classList.remove('active');
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

        // Auto-resize textarea
        const textarea = document.getElementById('messageInput');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Initial greeting animation
        window.addEventListener('load', () => {
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        });
    </script>
</body>
</html>