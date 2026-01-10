<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Chat - {{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Instrument Sans', sans-serif;
        }
        #messages {
            height: calc(100vh - 200px);
            overflow-y: auto;
            padding: 1rem;
        }
        .message {
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 0.5rem;
        }
        .message-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }
        .message-content {
            word-wrap: break-word;
        }
        .message-time {
            font-size: 0.75rem;
            color: #6b7280;
        }
        #message-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            resize: none;
        }
        #send-button {
            padding: 0.75rem 1.5rem;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
        }
        #send-button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        .status-connected {
            background: #10b981;
        }
        .status-disconnected {
            background: #ef4444;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto max-w-4xl p-4">
        <!-- Header -->
        <header class="bg-white rounded-lg shadow-sm p-4 mb-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    @auth
                        <img src="{{ auth()->user()->github_avatar_url ?? 'https://via.placeholder.com/40' }}" 
                             alt="{{ auth()->user()->username }}" 
                             class="w-10 h-10 rounded-full">
                        <div>
                            <div class="font-medium">{{ auth()->user()->username }}</div>
                            <div class="text-sm text-gray-500 flex items-center">
                                <span class="status-indicator" id="connection-status"></span>
                                <span id="status-text">Connecting...</span>
                            </div>
                        </div>
                    @endauth
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                        Logout
                    </button>
                </form>
            </div>
        </header>

        <!-- Messages Container -->
        <div id="messages" class="bg-white rounded-lg shadow-sm">
            <!-- Messages will be loaded here -->
        </div>

        <!-- Message Input -->
        <div class="bg-white rounded-lg shadow-sm p-4 mt-4">
            <form id="message-form" class="flex gap-2">
                <textarea 
                    id="message-input" 
                    rows="2" 
                    placeholder="Type your message..." 
                    required
                    maxlength="5000"></textarea>
                <button type="submit" id="send-button">Send</button>
            </form>
        </div>
    </div>

    <script>
        // CSRF Token for AJAX requests
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        // SSE Connection
        let eventSource = null;
        let isConnected = false;

        // Initialize SSE connection
        function connectSSE() {
            if (eventSource) {
                eventSource.close();
            }

            eventSource = new EventSource('/api/events');

            eventSource.onopen = function() {
                isConnected = true;
                updateConnectionStatus(true);
            };

            eventSource.addEventListener('connected', function(e) {
                console.log('SSE Connected');
                isConnected = true;
                updateConnectionStatus(true);
            });

            eventSource.addEventListener('message', function(e) {
                const message = JSON.parse(e.data);
                addMessageToUI(message);
            });

            eventSource.addEventListener('keepalive', function(e) {
                // Keep-alive received, connection is still alive
                console.log('Keep-alive received');
            });

            eventSource.onerror = function(e) {
                console.error('SSE Error:', e);
                isConnected = false;
                updateConnectionStatus(false);
                
                // Try to reconnect after 3 seconds
                setTimeout(connectSSE, 3000);
            };
        }

        // Update connection status indicator
        function updateConnectionStatus(connected) {
            const statusIndicator = document.getElementById('connection-status');
            const statusText = document.getElementById('status-text');
            
            if (connected) {
                statusIndicator.className = 'status-indicator status-connected';
                statusText.textContent = 'Connected';
            } else {
                statusIndicator.className = 'status-indicator status-disconnected';
                statusText.textContent = 'Disconnected';
            }
        }

        // Load chat history
        async function loadChatHistory() {
            try {
                const response = await fetch('/api/messages');
                const data = await response.json();
                
                const messagesContainer = document.getElementById('messages');
                messagesContainer.innerHTML = '';
                
                data.messages.forEach(message => {
                    addMessageToUI(message);
                });
                
                scrollToBottom();
            } catch (error) {
                console.error('Error loading chat history:', error);
            }
        }

        // Add message to UI
        function addMessageToUI(message) {
            const messagesContainer = document.getElementById('messages');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message';
            
            const date = new Date(message.created_at);
            const timeString = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            messageDiv.innerHTML = `
                <div class="message-header">
                    <img src="${message.avatar_url || 'https://via.placeholder.com/32'}" 
                         alt="${message.username}" 
                         class="message-avatar">
                    <span class="font-medium">${message.username}</span>
                    <span class="message-time">${timeString}</span>
                </div>
                <div class="message-content">${message.content}</div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }

        // Scroll to bottom of messages
        function scrollToBottom() {
            const messagesContainer = document.getElementById('messages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Send message
        document.getElementById('message-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const input = document.getElementById('message-input');
            const button = document.getElementById('send-button');
            const content = input.value.trim();
            
            if (!content) {
                return;
            }
            
            // Disable button while sending
            button.disabled = true;
            button.textContent = 'Sending...';
            
            try {
                const response = await fetch('/api/messages', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ content: content })
                });
                
                if (response.ok) {
                    const data = await response.json();
                    // Message will be added via SSE, but we can add it immediately for better UX
                    addMessageToUI(data.message);
                    input.value = '';
                } else {
                    const error = await response.json();
                    alert('Error sending message: ' + (error.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error sending message:', error);
                alert('Error sending message. Please try again.');
            } finally {
                button.disabled = false;
                button.textContent = 'Send';
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadChatHistory();
            connectSSE();
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (eventSource) {
                eventSource.close();
            }
        });
    </script>
</body>
</html>

