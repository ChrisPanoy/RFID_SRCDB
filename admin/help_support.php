<?php
// help_support.php - Professional Help/Support Chat Page UI
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(120deg, #e0eafc 0%, #cfdef3 100%);
            margin: 0;
            padding: 0;
        }
        .support-container {
            max-width: 520px;
            margin: 48px auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(99,102,241,0.10);
            padding: 0 0 24px 0;
        }
        .support-header {
            background: linear-gradient(90deg, #4f8cff 0%, #38e7ff 100%);
            color: #fff;
            border-radius: 18px 18px 0 0;
            padding: 28px 32px 18px 32px;
            text-align: left;
        }
        .support-header h2 {
            margin: 0 0 6px 0;
            font-size: 2rem;
            font-weight: 700;
        }
        .support-header p {
            margin: 0;
            font-size: 1.1rem;
            opacity: 0.95;
        }
        .chat-window {
            background: #f8fafc;
            border-radius: 12px;
            margin: 24px 24px 0 24px;
            padding: 18px 16px 12px 16px;
            min-height: 260px;
            max-height: 340px;
            overflow-y: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .chat-message {
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
        }
        .chat-message.user .bubble {
            background: #6366f1;
            color: #fff;
            margin-left: auto;
        }
        .chat-message.bot .bubble {
            background: #e0eafc;
            color: #222;
            margin-right: auto;
        }
        .bubble {
            padding: 12px 18px;
            border-radius: 16px;
            max-width: 80%;
            font-size: 1.05rem;
            box-shadow: 0 1px 4px rgba(99,102,241,0.07);
        }
        .chat-input-area {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 18px 24px 0 24px;
        }
        .chat-input-area input[type="text"] {
            flex: 1;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 1.08rem;
        }
        .chat-input-area button {
            background: linear-gradient(90deg, #4f8cff 0%, #38e7ff 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 28px;
            font-size: 1.08rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .chat-input-area button:hover {
            background: linear-gradient(90deg, #38e7ff 0%, #4f8cff 100%);
        }
        .faq-section {
            margin: 32px 24px 0 24px;
            padding: 18px 18px 10px 18px;
            background: #f3f4f6;
            border-radius: 12px;
        }
        .faq-section h3 {
            margin-top: 0;
            color: #1976d2;
            font-size: 1.1rem;
        }
        .faq-list {
            margin: 0;
            padding: 0 0 0 18px;
        }
        .faq-list li {
            margin-bottom: 8px;
            font-size: 1rem;
        }
        @media (max-width: 600px) {
            .support-container { max-width: 98vw; }
            .support-header { padding: 18px 10px 12px 10px; }
            .chat-window, .chat-input-area, .faq-section { margin-left: 6px; margin-right: 6px; }
        }
    </style>
</head>
<body>
    <div class="support-container">
        <div class="support-header">
            <h2><i class="fas fa-comments"></i> Help & Support</h2>
            <p>Ask anything about the system. Our smart assistant will help you!</p>
        </div>
        <div class="chat-window" id="chat-window">
            <div class="chat-message bot">
                <div class="bubble">Hello! 👋 How can I assist you with the system today?</div>
            </div>
            <!-- More chat messages will appear here -->
        </div>
        <form class="chat-input-area" onsubmit="event.preventDefault(); sendMessage();">
            <input type="text" id="chat-input" placeholder="Type your question here..." autocomplete="off" />
            <button type="submit"><i class="fas fa-paper-plane"></i> Send</button>
        </form>
        <div class="faq-section">
            <h3><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3>
            <ul class="faq-list">
                <li>How do I record attendance?</li>
                <li>How can I view my students' attendance history?</li>
                <li>What should I do if I forgot my password?</li>
                <li>How do I update my profile?</li>
                <li>Who do I contact for technical support?</li>
            </ul>
        </div>
        <div style="text-align:center; margin-top: 24px;">
            <a href="mailto:christophermadeja7@gmail.com?subject=Support%20Request%20from%20Teacher" 
               style="display:inline-block;background:linear-gradient(90deg,#4f8cff 0%,#38e7ff 100%);color:#fff;font-weight:600;padding:12px 32px;border-radius:8px;box-shadow:0 2px 8px rgba(99,102,241,0.10);text-decoration:none;font-size:1.08rem;transition:background 0.2s;">
               <i class="fas fa-envelope"></i> Contact Admin
            </a>
        </div>
        <div class="contact-form-section" style="margin:36px 24px 0 24px; background:#f8fafc; border-radius:12px; box-shadow:0 2px 8px rgba(99,102,241,0.04); padding:24px 18px;">
            <h3 style="color:#1976d2; margin-top:0;">Or send a message directly to the admin:</h3>
            <form id="contactForm" style="display:flex;flex-direction:column;gap:14px;max-width:400px;margin:0 auto;">
                <input type="text" name="name" placeholder="Your Name" required style="padding:10px 14px;border-radius:6px;border:1px solid #ccc;font-size:1rem;">
                <input type="email" name="email" placeholder="Your Email" required style="padding:10px 14px;border-radius:6px;border:1px solid #ccc;font-size:1rem;">
                <textarea name="message" placeholder="Type your message here..." required rows="4" style="padding:10px 14px;border-radius:6px;border:1px solid #ccc;font-size:1rem;"></textarea>
                <button type="submit" style="background:linear-gradient(90deg,#4f8cff 0%,#38e7ff 100%);color:#fff;font-weight:600;padding:10px 0;border-radius:6px;font-size:1.08rem;border:none;cursor:pointer;">Send Message</button>
                <div id="contactStatus" style="margin-top:8px;font-size:1rem;"></div>
            </form>
        </div>
    </div>
</body>
<script>
const chatWindow = document.getElementById('chat-window');
const chatInput = document.getElementById('chat-input');

function appendMessage(text, sender) {
    const msgDiv = document.createElement('div');
    msgDiv.className = 'chat-message ' + sender;
    const bubble = document.createElement('div');
    bubble.className = 'bubble';
    bubble.innerText = text;
    msgDiv.appendChild(bubble);
    chatWindow.appendChild(msgDiv);
    chatWindow.scrollTop = chatWindow.scrollHeight;
}

function setLoading(isLoading) {
    let loadingDiv = document.getElementById('loading-msg');
    if (isLoading) {
        if (!loadingDiv) {
            loadingDiv = document.createElement('div');
            loadingDiv.className = 'chat-message bot';
            loadingDiv.id = 'loading-msg';
            const bubble = document.createElement('div');
            bubble.className = 'bubble';
            bubble.innerText = 'Thinking...';
            loadingDiv.appendChild(bubble);
            chatWindow.appendChild(loadingDiv);
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }
    } else {
        if (loadingDiv) loadingDiv.remove();
    }
}

function sendMessage() {
    const message = chatInput.value.trim();
    if (!message) return;
    appendMessage(message, 'user');
    chatInput.value = '';
    setLoading(true);
    fetch('gemini_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message })
    })
    .then(res => res.json())
    .then(data => {
        setLoading(false);
        if (data.reply) {
            appendMessage(data.reply, 'bot');
        } else if (data.error) {
            appendMessage('Error: ' + data.error, 'bot');
        } else {
            appendMessage('Sorry, I did not understand that.', 'bot');
        }
    })
    .catch(() => {
        setLoading(false);
        appendMessage('Network error. Please try again.', 'bot');
    });
}

chatInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        sendMessage();
    }
});

// Contact form AJAX
const contactForm = document.getElementById('contactForm');
const contactStatus = document.getElementById('contactStatus');
if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();
        contactStatus.textContent = 'Sending...';
        contactStatus.style.color = '#6366f1';
        fetch('send_contact.php', {
            method: 'POST',
            body: new FormData(contactForm)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                contactStatus.textContent = 'Message sent successfully!';
                contactStatus.style.color = '#059669';
                contactForm.reset();
            } else {
                contactStatus.textContent = data.error || 'Failed to send message.';
                contactStatus.style.color = '#b91c1c';
            }
        })
        .catch(() => {
            contactStatus.textContent = 'Network error. Please try again.';
            contactStatus.style.color = '#b91c1c';
        });
    });
}
</script>
</html> 



