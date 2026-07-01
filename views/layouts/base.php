<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'ADV CRM'; ?></title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="<?php echo $baseUrl ?? ''; ?>/app.webmanifest">
    <meta name="theme-color" content="#4a90e2">
    <meta name="background-color" content="#ffffff">
    <meta name="display" content="standalone">
    <meta name="orientation" content="portrait-primary">
    
    <!-- Apple PWA Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Clarity CRM">
    <link rel="apple-touch-icon" href="<?php echo $baseUrl ?? ''; ?>/assets/icons/icon-192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo $baseUrl ?? ''; ?>/assets/icons/icon-152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $baseUrl ?? ''; ?>/assets/icons/icon-192.png">
    
    <!-- Microsoft PWA Meta Tags -->
    <meta name="msapplication-TileColor" content="#4a90e2">
    <meta name="msapplication-TileImage" content="<?php echo $baseUrl ?? ''; ?>/assets/icons/icon-144.png">
    <meta name="msapplication-config" content="<?php echo $baseUrl ?? ''; ?>/browserconfig.xml">
    
    <!-- Standard Icons -->
    <link rel="icon" type="image/png" href="<?php echo $baseUrl ?? ''; ?>/assets/fav.png">
    <link rel="shortcut icon" type="image/png" href="<?php echo $baseUrl ?? ''; ?>/assets/fav.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $baseUrl ?? ''; ?>/assets/css/offline.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#8b5cf6',
                        dark: {
                            900: '#0f0f23',
                            800: '#1a1a2e',
                            700: '#25253d',
                            600: '#2d2d4a',
                            500: 'rgb(188, 188, 204)'
                        },
                        accent: '#22d3ee'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    },
                    fontSize: {
                        'xs': ['0.7rem', { lineHeight: '1rem' }],
                        'sm': ['0.8rem', { lineHeight: '1.15rem' }],
                        'base': ['0.875rem', { lineHeight: '1.25rem' }],
                        'lg': ['0.95rem', { lineHeight: '1.4rem' }],
                        'xl': ['1.05rem', { lineHeight: '1.5rem' }],
                        '2xl': ['1.2rem', { lineHeight: '1.6rem' }],
                        '3xl': ['1.5rem', { lineHeight: '2rem' }],
                        '4xl': ['1.8rem', { lineHeight: '2.25rem' }]
                    }
                }
            }
        }
    </script>
    <style>
        html { font-size: 14px; }
        body { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease, width 0.3s ease; }
        .sidebar-overlay { transition: opacity 0.3s ease; }
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link:hover { background: rgba(99, 102, 241, 0.1); }
        .sidebar-link.active { background: linear-gradient(90deg, rgba(99, 102, 241, 0.2) 0%, transparent 100%); border-left: 2px solid #6366f1; }
        /* Collapsible section styles */
        .collapsible-toggle { transition: all 0.2s ease; }
        .collapsible-toggle:hover { background: rgba(99, 102, 241, 0.05); }
        .collapsible-toggle i.fa-chevron-right { transition: transform 0.2s ease; }
        .collapsible-toggle i.fa-chevron-right.rotate-90 { transform: rotate(90deg); }
        [data-section-content] { transition: all 0.2s ease; }
        /* Active section header highlight */
        .collapsible-toggle.section-active { color: #a5b4fc; }
        .card { background: rgba(255,255,255,0.02); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.05); }
        .glass { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); }
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
        }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: #1a1a2e; }
        ::-webkit-scrollbar-thumb { background: #3d3d5c; border-radius: 2px; }
        /* Sidebar specific ultra-thin scrollbar - almost invisible */
        .sidebar nav { scrollbar-width: thin; scrollbar-color: transparent transparent; }
        .sidebar nav::-webkit-scrollbar { width: 2px; }
        .sidebar nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar nav::-webkit-scrollbar-thumb { background: transparent; border-radius: 2px; }
        .sidebar nav:hover::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); }
        .sidebar nav:hover { scrollbar-color: rgba(255, 255, 255, 0.15) transparent; }
        
        /* PWA Integration Styles */
        .offline-disabled {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .offline-mode {
            position: relative;
        }
        
        .offline-mode::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 193, 7, 0.1);
            border: 2px dashed #ffc107;
            border-radius: 0.375rem;
            pointer-events: none;
            z-index: 1;
        }
        
        .connection-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }
        
        .connection-status.online {
            background: #d1fae5;
            color: #065f46;
        }
        
        .connection-status.offline {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .offline-only {
            display: none;
        }
        
        .online-only {
            display: block;
        }
        
        /* PWA Install Button */
        #pwa-install-btn {
            display: none;
        }
        
        /* Sync Indicator Animation */
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        
        .animate-spin {
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen font-sans">
    <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
        <!-- Mobile Menu Button -->
        <button id="menuBtn" class="lg:hidden fixed top-4 left-4 z-50 p-2 rounded-lg bg-dark-800 text-white shadow-lg">
            <i class="fas fa-bars text-xl"></i>
        </button>
        
        <!-- Sidebar Overlay -->
        <div id="sidebarOverlay" class="sidebar-overlay fixed inset-0 bg-black/50 z-40 lg:hidden hidden"></div>
        
        <?php include __DIR__ . '/../components/sidebar.php'; ?>
        
        <div class="main-content lg:ml-64 min-h-screen transition-all duration-300">
            <?php include __DIR__ . '/../components/header.php'; ?>
            <main class="p-4 md:p-6">
                <?php include __DIR__ . '/../components/alerts.php'; ?>
                <?php echo $content ?? ''; ?>
            </main>
        </div>
    <?php else: ?>
        <main class="min-h-screen">
            <?php echo $content ?? ''; ?>
        </main>
    <?php endif; ?>
    
    <?php include __DIR__ . '/../components/modal.php'; ?>
    <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
        <?php include __DIR__ . '/../components/global-tools.php'; ?>
        <?php include __DIR__ . '/../components/notes-popup.php'; ?>
    <?php endif; ?>
    
    <script>
        // Mobile sidebar toggle
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (menuBtn) {
            menuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('hidden');
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                overlay.classList.add('hidden');
            });
        }
    </script>
    <script src="<?php echo $baseUrl ?? ''; ?>/assets/js/app.js"></script>
    <script src="<?php echo $baseUrl ?? ''; ?>/assets/js/pwa-manager.js"></script>
    <script src="<?php echo $baseUrl ?? ''; ?>/assets/js/performance-monitor.js"></script>
    <script src="<?php echo $baseUrl ?? ''; ?>/assets/js/cache-optimizer.js"></script>
    <script src="<?php echo $baseUrl ?? ''; ?>/assets/js/offline-utils.js"></script>
    <script src="<?php echo $baseUrl ?? ''; ?>/assets/js/offline-data-manager.js"></script>
    <script src="<?php echo $baseUrl ?? ''; ?>/assets/js/network-status.js"></script>
    <script src="<?php echo $baseUrl ?? ''; ?>/assets/js/offline-form-handler.js"></script>
    
    <!-- PWA Integration Script -->
    <script>
        // PWA Integration and Service Worker Registration
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize PWA features
            initializePWA();
            
            // Set up offline state handling for navigation
            setupOfflineNavigation();
            
            // Initialize data caching for current page
            initializePageCaching();
            
            // Set up PWA event listeners
            setupPWAEventListeners();
        });
        
        /**
         * Initialize PWA features
         */
        function initializePWA() {
            // Unregister service worker if exists
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(registrations => {
                    for (const registration of registrations) {
                        registration.unregister();
                        console.log('[PWA] Service Worker unregistered');
                    }
                });
            }
        }    
            // Handle install prompt
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                console.log('[PWA] Install prompt available');
                
                // Store the event for later use
                window.deferredPrompt = e;
                
                // Show custom install button if available
                const installBtn = document.getElementById('pwa-install-btn');
                if (installBtn) {
                    installBtn.style.display = 'block';
                }
            });
            
            // Handle successful installation
            window.addEventListener('appinstalled', () => {
                console.log('[PWA] App installed successfully');
                window.deferredPrompt = null;
                
                // Hide install button
                const installBtn = document.getElementById('pwa-install-btn');
                if (installBtn) {
                    installBtn.style.display = 'none';
                }
            });
        }
        
        /**
         * Set up offline navigation handling
         */
        function setupOfflineNavigation() {
            // Add offline indicators to navigation links
            const navLinks = document.querySelectorAll('a[href]');
            
            navLinks.forEach(link => {
                // Skip external links and javascript: links
                if (link.href.startsWith('javascript:') || 
                    link.href.startsWith('mailto:') || 
                    link.href.startsWith('tel:') ||
                    link.hostname !== window.location.hostname) {
                    return;
                }
                
                // Add offline handling
                link.addEventListener('click', function(e) {
                    if (!navigator.onLine) {
                        // Check if target page is cached
                        const targetUrl = new URL(this.href);
                        
                        // For now, allow navigation but show offline indicator
                        console.log('[PWA] Offline navigation to:', targetUrl.pathname);
                    }
                });
            });
            
            // Update navigation state based on connection
            window.addEventListener('online', updateNavigationState);
            window.addEventListener('offline', updateNavigationState);
            
            // Initial state
            updateNavigationState();
        }
        
        /**
         * Update navigation state based on connection
         */
        function updateNavigationState() {
            const navElements = document.querySelectorAll('.nav-item, .sidebar-link');
            
            navElements.forEach(element => {
                if (navigator.onLine) {
                    element.classList.remove('offline-disabled');
                    element.removeAttribute('title');
                } else {
                    element.classList.add('offline-disabled');
                    element.setAttribute('title', 'Limited functionality while offline');
                }
            });
        }
        
        /**
         * Initialize page-specific data caching
         */
        function initializePageCaching() {
            // Cache current page data if online
            if (navigator.onLine && window.offlineDataManager) {
                window.offlineDataManager.cacheCurrentPageData();
            }
            
            // Set up periodic caching
            setInterval(() => {
                if (navigator.onLine && window.offlineDataManager) {
                    window.offlineDataManager.cacheCurrentPageData();
                }
            }, 5 * 60 * 1000); // Every 5 minutes
        }
        
        /**
         * Set up PWA-specific event listeners
         */
        function setupPWAEventListeners() {
            // Listen for PWA-specific events
            window.addEventListener('pwa-connection-change', (event) => {
                const isOnline = event.detail.isOnline;
                console.log('[PWA] Connection changed:', isOnline ? 'online' : 'offline');
                
                // Update UI elements
                updateConnectionDependentUI(isOnline);
                
                // Show/hide offline features
                toggleOfflineFeatures(!isOnline);
            });
            
            // Listen for sync events
            window.addEventListener('sync-start', () => {
                showSyncIndicator(true);
            });
            
            window.addEventListener('sync-complete', () => {
                showSyncIndicator(false);
            });
            
            // Listen for cache updates
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data && event.data.type === 'CACHE_UPDATED') {
                        console.log('[PWA] Cache updated for:', event.data.url);
                    }
                });
            }
        }
        
        /**
         * Update connection-dependent UI elements
         */
        function updateConnectionDependentUI(isOnline) {
            // Update forms
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                if (isOnline) {
                    form.classList.remove('offline-mode');
                } else {
                    form.classList.add('offline-mode');
                }
            });
            
            // Update buttons that require network
            const networkButtons = document.querySelectorAll('[data-requires-network]');
            networkButtons.forEach(button => {
                button.disabled = !isOnline;
                if (!isOnline) {
                    button.setAttribute('title', 'Requires internet connection');
                } else {
                    button.removeAttribute('title');
                }
            });
            
            // Update status indicators
            const statusElements = document.querySelectorAll('.connection-status');
            statusElements.forEach(element => {
                element.textContent = isOnline ? 'Online' : 'Offline';
                element.className = `connection-status ${isOnline ? 'online' : 'offline'}`;
            });
        }
        
        /**
         * Toggle offline-specific features
         */
        function toggleOfflineFeatures(showOffline) {
            const offlineElements = document.querySelectorAll('.offline-only');
            const onlineElements = document.querySelectorAll('.online-only');
            
            offlineElements.forEach(element => {
                element.style.display = showOffline ? 'block' : 'none';
            });
            
            onlineElements.forEach(element => {
                element.style.display = showOffline ? 'none' : 'block';
            });
        }
        
        /**
         * Show/hide sync indicator
         */
        function showSyncIndicator(show) {
            let indicator = document.getElementById('sync-indicator');
            
            if (show && !indicator) {
                indicator = document.createElement('div');
                indicator.id = 'sync-indicator';
                indicator.className = 'fixed top-4 right-4 bg-blue-600 text-white px-3 py-2 rounded-lg shadow-lg z-50';
                indicator.innerHTML = `
                    <div class="flex items-center space-x-2">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                        <span>Syncing...</span>
                    </div>
                `;
                document.body.appendChild(indicator);
            } else if (!show && indicator) {
                indicator.remove();
            }
        }
        
        /**
         * Manual PWA install function
         */
        function installPWA() {
            if (window.deferredPrompt) {
                window.deferredPrompt.prompt();
                
                window.deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('[PWA] User accepted the install prompt');
                    } else {
                        console.log('[PWA] User dismissed the install prompt');
                    }
                    window.deferredPrompt = null;
                });
            }
        }
        
        // Make install function globally available
        window.installPWA = installPWA;
    </script>
    <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
    <script src="<?php echo $baseUrl ?? ''; ?>/assets/js/global-tools.js"></script>
    <script src="<?php echo $baseUrl ?? ''; ?>/assets/js/notes.js"></script>
    <script>
        // Set base URL for global tools and notes
        window.baseUrl = '<?php echo $baseUrl ?? ''; ?>';
    </script>
    <?php endif; ?>
    
    <!-- PWA Integration Test (Development Only) -->
    <?php if (defined('PWA_TESTING') && PWA_TESTING): ?>
    <script src="<?php echo $baseUrl ?? ''; ?>/assets/js/pwa-integration-test.js"></script>
    <?php endif; ?>
</body>
</html>
