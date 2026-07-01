<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Push Notifications Test - ADV Clarity</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold mb-6">Push Notifications Test</h1>
        
        <div class="space-y-4">
            <div class="p-4 bg-blue-50 rounded-lg">
                <h3 class="font-semibold mb-2">Notification Support</h3>
                <p id="support-status" class="text-gray-600">Checking...</p>
            </div>
            
            <div class="p-4 bg-green-50 rounded-lg">
                <h3 class="font-semibold mb-2">Permission Status</h3>
                <p id="permission-status" class="text-gray-600">Checking...</p>
            </div>
            
            <div class="p-4 bg-yellow-50 rounded-lg">
                <h3 class="font-semibold mb-2">Subscription Status</h3>
                <p id="subscription-status" class="text-gray-600">Checking...</p>
            </div>
            
            <div class="flex space-x-4">
                <button id="request-permission" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Request Permission
                </button>
                <button id="subscribe-push" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                    Subscribe to Push
                </button>
                <button id="test-notification" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700">
                    Test Notification
                </button>
                <button id="unsubscribe-push" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                    Unsubscribe
                </button>
            </div>
            
            <div id="log" class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="font-semibold mb-2">Log</h3>
                <div id="log-content" class="text-sm text-gray-600 space-y-1"></div>
            </div>
        </div>
    </div>

    <script src="assets/js/pwa-manager.js"></script>
    <script>
        let pwaManager;
        
        document.addEventListener('DOMContentLoaded', async () => {
            // Initialize PWA Manager
            pwaManager = new PWAManager();
            
            // Wait a bit for initialization
            setTimeout(checkStatus, 1000);
            
            // Set up event listeners
            document.getElementById('request-permission').addEventListener('click', requestPermission);
            document.getElementById('subscribe-push').addEventListener('click', subscribeToPush);
            document.getElementById('test-notification').addEventListener('click', testNotification);
            document.getElementById('unsubscribe-push').addEventListener('click', unsubscribePush);
        });
        
        async function checkStatus() {
            // Check support
            const supported = 'Notification' in window && 'PushManager' in window && 'serviceWorker' in navigator;
            document.getElementById('support-status').textContent = supported ? 
                'Push notifications are supported' : 'Push notifications are not supported';
            
            // Check permission
            const permission = Notification.permission;
            document.getElementById('permission-status').textContent = `Permission: ${permission}`;
            
            // Check subscription
            if (pwaManager) {
                const subscribed = await pwaManager.checkPushSubscription();
                document.getElementById('subscription-status').textContent = subscribed ? 
                    'Subscribed to push notifications' : 'Not subscribed to push notifications';
            }
            
            log('Status checked');
        }
        
        async function requestPermission() {
            try {
                const granted = await pwaManager.requestNotificationPermission();
                log(`Permission request result: ${granted ? 'granted' : 'denied'}`);
                checkStatus();
            } catch (error) {
                log(`Permission request error: ${error.message}`);
            }
        }
        
        async function subscribeToPush() {
            try {
                const result = await pwaManager.setupPushNotifications();
                log(`Push subscription result: ${result ? 'success' : 'failed'}`);
                checkStatus();
            } catch (error) {
                log(`Push subscription error: ${error.message}`);
            }
        }
        
        async function testNotification() {
            try {
                pwaManager.showNotification('Test Notification', {
                    body: 'This is a test notification from the PWA test page',
                    icon: '/assets/icons/icon-192.png',
                    tag: 'test'
                });
                log('Test notification sent');
            } catch (error) {
                log(`Test notification error: ${error.message}`);
            }
        }
        
        async function unsubscribePush() {
            try {
                const result = await pwaManager.unsubscribeFromPush();
                log(`Unsubscribe result: ${result ? 'success' : 'failed'}`);
                checkStatus();
            } catch (error) {
                log(`Unsubscribe error: ${error.message}`);
            }
        }
        
        function log(message) {
            const logContent = document.getElementById('log-content');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.textContent = `[${timestamp}] ${message}`;
            logContent.appendChild(logEntry);
            logContent.scrollTop = logContent.scrollHeight;
        }
        
        // Listen for service worker messages
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                log(`Service worker message: ${JSON.stringify(event.data)}`);
            });
        }
    </script>
</body>
</html>