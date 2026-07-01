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
    <title>Forgot Password - LYNQ</title>
    <link rel="icon" type="image/png" href="../../assets/fav.png">
    <link rel="shortcut icon" type="image/png" href="../../assets/fav.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
    <div class="w-full max-w-5xl relative z-10 flex flex-col items-center justify-center space-y-6 md:space-y-8">
        
        <!-- Advantage Logo (Top) -->
        <div class="text-center">
            <a href="login.php">
                <img src="../../assets/logo.png" alt="Advantage" class="h-9 md:h-11 object-contain mx-auto animate-fade-in">
            </a>
            <p class="text-xs font-semibold tracking-wider text-indigo-600 uppercase mt-2">Enterprise Resource Planning</p>
        </div>
        
        <!-- Split Container -->
        <div class="flex flex-col-reverse md:flex-row items-center justify-center gap-8 md:gap-12 w-full">
            
            <!-- LYNQ Full Image (Left on Desktop, Bottom on Mobile) -->
            <div class="w-full max-w-[320px] md:max-w-[380px] flex items-center justify-center bg-white p-6 rounded-2xl border border-slate-100 shadow-[0_10px_30px_rgba(0,0,0,0.02)] transition-all duration-300 hover:shadow-[0_15px_40px_rgba(0,0,0,0.04)]">
                <img src="../../assets/image.png" alt="LYNQ Product Identity" class="w-full h-auto object-contain">
            </div>
            
            <!-- Forgot Password Card (Right on Desktop, Top on Mobile) -->
            <div class="glass-card rounded-2xl overflow-hidden w-full max-w-md">
                <!-- Forgot Password Card Header -->
                <div class="px-8 pt-8 pb-4 text-center">
                    <h1 class="text-2xl font-bold text-slate-800">Forgot Password?</h1>
                    <p class="text-sm text-slate-500 mt-1">Enter your email to receive reset instructions</p>
                </div>
                
                <!-- Form -->
                <form id="forgotForm" class="px-8 pb-8">
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
                    
                    <!-- Email -->
                    <div class="mb-6">
                        <label class="block text-slate-700 text-sm font-medium mb-2" for="email">Email Address</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" id="email" name="email" required autocomplete="email"
                                class="input-field w-full pl-11 pr-4 py-3 rounded-xl focus:outline-none text-sm"
                                placeholder="Enter your email address">
                        </div>
                        <span id="emailError" class="text-red-500 text-xs mt-1 hidden"></span>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" id="submitBtn"
                        class="btn-primary w-full text-white py-3.5 rounded-xl font-semibold flex items-center justify-center mb-4">
                        <span id="submitText">Send Reset Link</span>
                        <i class="fas fa-paper-plane ml-2" id="submitIcon"></i>
                    </button>
                    
                    <!-- Back to Login -->
                    <div class="text-center">
                        <a href="login.php" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Login
                        </a>
                    </div>
                </form>
            </div>
            
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-slate-400 text-sm">&copy; <?php echo date('Y'); ?> LYNQ. All rights reserved.</p>
        </div>
    </div>
    
    <script>
    function hideAlerts() {
        document.getElementById('errorAlert').classList.add('hidden');
        document.getElementById('successAlert').classList.add('hidden');
        document.getElementById('emailError').classList.add('hidden');
        document.getElementById('email').classList.remove('border-red-500');
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
            text.textContent = 'Sending...';
        } else {
            btn.disabled = false;
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
            icon.className = 'fas fa-paper-plane ml-2';
            text.textContent = 'Send Reset Link';
        }
    }
    
    document.getElementById('forgotForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        hideAlerts();
        
        const email = document.getElementById('email').value.trim();
        
        if (!email) {
            document.getElementById('emailError').textContent = 'Email is required';
            document.getElementById('emailError').classList.remove('hidden');
            document.getElementById('email').classList.add('border-red-500');
            return;
        }
        
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            document.getElementById('emailError').textContent = 'Please enter a valid email address';
            document.getElementById('emailError').classList.remove('hidden');
            document.getElementById('email').classList.add('border-red-500');
            return;
        }
        
        setLoading(true);
        
        // Simulate API call - replace with actual API when available
        setTimeout(() => {
            showSuccess('If an account exists with this email, you will receive password reset instructions shortly.');
            setLoading(false);
            document.getElementById('email').value = '';
        }, 1500);
    });
    
    document.getElementById('email').focus();
    </script>
</body>
</html>
