<?php
require_once __DIR__ . '/../../config/autoload.php';

$sessionService = new SessionService();
if ($sessionService->isLoggedIn()) {
    header('Location: ../../dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LYNQ</title>
    <link rel="icon" type="image/png" href="../../assets/fav.png">
    <link rel="shortcut icon" type="image/png" href="../../assets/fav.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="../../app.webmanifest">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .login-bg {
            background: radial-gradient(at 0% 0%, rgba(219, 234, 254, 0.4) 0, transparent 50%), 
                        radial-gradient(at 100% 100%, rgba(224, 231, 255, 0.4) 0, transparent 50%), 
                        #f8fafc;
            position: relative;
            overflow: hidden;
        }
        .login-bg::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.05) 0%, transparent 60%);
            animation: pulse 15s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.02);
        }
        .input-field {
            transition: all 0.2s ease-in-out;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            color: #334155;
        }
        .input-field:focus {
            border-color: #4f46e5;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            transition: all 0.2s ease-in-out;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);
        }
        .btn-primary:active { transform: translateY(0); }
    </style>
</head>
<body class="login-bg min-h-screen flex items-center justify-center p-4 md:p-8">
    <div class="w-full max-w-md md:max-w-5xl relative z-10 flex flex-col items-center justify-center space-y-6 md:space-y-8">
        
        <!-- Advantage Logo (Top) -->
        <div class="text-center">
            <img src="../../assets/logo.png" alt="Advantage" class="h-9 md:h-11 object-contain mx-auto">
            <p class="text-xs font-semibold tracking-wider text-indigo-600 uppercase mt-2">Enterprise Resource Planning</p>
        </div>
        
        <!-- Split Container -->
        <div class="flex flex-col-reverse md:flex-row items-center justify-center gap-8 md:gap-12 w-full">
            
            <!-- LYNQ Full Image (Left on Desktop, Bottom on Mobile) -->
            <div class="w-full max-w-[320px] md:max-w-[380px] flex items-center justify-center bg-white p-6 rounded-2xl border border-slate-100 shadow-[0_10px_30px_rgba(0,0,0,0.02)] transition-all duration-300 hover:shadow-[0_15px_40px_rgba(0,0,0,0.04)]">
                <img src="../../assets/image.png" alt="LYNQ Product Identity" class="w-full h-auto object-contain">
            </div>
            
            <!-- Login Card (Right on Desktop, Top on Mobile) -->
            <div class="glass-card rounded-2xl overflow-hidden w-full max-w-md">
                <!-- Login Card Header -->
                <div class="px-8 pt-8 pb-4 text-center">
                    <h1 class="text-2xl font-bold text-slate-800">Welcome back</h1>
                    <p class="text-sm text-slate-500 mt-1">Sign in to continue to your dashboard</p>
                </div>
                
                <!-- Form -->
                <form id="loginForm" class="px-8 pb-8">
                    <!-- Alerts -->
                    <div id="errorAlert" class="hidden bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-3 text-red-500"></i>
                            <span id="errorMessage" class="text-sm"></span>
                        </div>
                    </div>
                    
                    <div id="successAlert" class="hidden bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-3 text-green-500"></i>
                            <span id="successMessage" class="text-sm"></span>
                        </div>
                    </div>
                    
                    <!-- Username -->
                    <div class="mb-5">
                        <label class="block text-slate-700 text-sm font-medium mb-2" for="username">Username</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" id="username" name="username" required autocomplete="username"
                                class="input-field w-full pl-11 pr-4 py-3 rounded-xl focus:outline-none text-sm"
                                placeholder="Enter your username">
                        </div>
                        <span id="usernameError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    
                    <!-- Password -->
                    <div class="mb-5">
                        <label class="block text-slate-700 text-sm font-medium mb-2" for="password">Password</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" id="password" name="password" required autocomplete="current-password"
                                class="input-field w-full pl-11 pr-12 py-3 rounded-xl focus:outline-none text-sm"
                                placeholder="Enter your password">
                            <button type="button" onclick="togglePassword()" 
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                        <span id="passwordError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    
                    <!-- Remember & Forgot -->
                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-slate-600">Remember me</span>
                        </label>
                        <a href="forgot-password.php" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">Forgot password?</a>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" id="submitBtn"
                        class="btn-primary w-full text-white py-3.5 rounded-xl font-semibold flex items-center justify-center">
                        <span id="submitText">Sign In</span>
                        <i class="fas fa-arrow-right ml-2" id="submitIcon"></i>
                    </button>
                </form>
            </div>
            
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-slate-400 text-sm">&copy; <?php echo date('Y'); ?> LYNQ. All rights reserved.</p>
        </div>
    </div>
    
    <script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    
    function hideAlerts() {
        document.getElementById('errorAlert').classList.add('hidden');
        document.getElementById('successAlert').classList.add('hidden');
        document.getElementById('usernameError').classList.add('hidden');
        document.getElementById('passwordError').classList.add('hidden');
        document.getElementById('username').classList.remove('border-red-500');
        document.getElementById('password').classList.remove('border-red-500');
    }
    
    function showError(message) {
        document.getElementById('errorMessage').textContent = message;
        document.getElementById('errorAlert').classList.remove('hidden');
    }
    
    function showSuccess(message) {
        document.getElementById('successMessage').textContent = message;
        document.getElementById('successAlert').classList.remove('hidden');
    }
    
    function setLoading(loading) {
        const btn = document.getElementById('submitBtn');
        const icon = document.getElementById('submitIcon');
        const text = document.getElementById('submitText');
        
        if (loading) {
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            icon.className = 'fas fa-spinner fa-spin ml-2';
            text.textContent = 'Signing in...';
        } else {
            btn.disabled = false;
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
            icon.className = 'fas fa-arrow-right ml-2';
            text.textContent = 'Sign In';
        }
    }
    
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        hideAlerts();
        
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        
        let hasError = false;
        if (!username) {
            document.getElementById('usernameError').textContent = 'Username is required';
            document.getElementById('usernameError').classList.remove('hidden');
            document.getElementById('username').classList.add('border-red-500');
            hasError = true;
        }
        if (!password) {
            document.getElementById('passwordError').textContent = 'Password is required';
            document.getElementById('passwordError').classList.remove('hidden');
            document.getElementById('password').classList.add('border-red-500');
            hasError = true;
        }
        
        if (hasError) return;
        
        setLoading(true);
        
        try {
            const response = await fetch('../../api/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ username, password })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showSuccess(data.message + ' Redirecting...');
                setTimeout(() => {
                    window.location.href = data.data?.redirect || '../../dashboard.php';
                }, 800);
            } else {
                showError(data.error || data.message || 'Login failed');
                setLoading(false);
            }
        } catch (error) {
            showError('Network error. Please check your connection.');
            setLoading(false);
        }
    });
    
    // Focus username on load
    document.getElementById('username').focus();
    </script>

    
  <script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(registrations => {
      for (const registration of registrations) {
        registration.unregister();
        console.log('[PWA] Service Worker unregistered');
      }
    });
  }
</script>
</body>
</html>
