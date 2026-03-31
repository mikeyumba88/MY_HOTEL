<?php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";

// Show all PHP errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // ✅ Save session data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];

                // ✅ Log the successful login - MOVED INSIDE LOGIN BLOCK
                $auditIntegration->logAuthAction(
                    'login', 
                    $user['id'], // Use $user['id'] instead of undefined $user_id
                    "User logged in successfully"
                );

                // ✅ Redirect based on role - FIXED VERSION
                if ($user['role'] === "admin") {
                    header("Location: ../admin/admin_dashboard.php");
                    exit;
                } elseif ($user['role'] === "receptionist") {
                    header("Location: ../reception/reception_dashboard.php");
                    exit;
                } else {
                    header("Location: guest_dashboard.php");
                    exit;
                }
            } else {
                $error = "❌ Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "❌ Database error: " . $e->getMessage();
        }
    } else {
        $error = "⚠️ Please enter both email and password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hotel Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-right {
            flex: 1;
            padding: 50px;
        }

        .logo {
            font-size: 2.5em;
            margin-bottom: 20px;
        }

        .welcome-text {
            font-size: 2.2em;
            font-weight: 300;
            margin-bottom: 15px;
            line-height: 1.2;
        }

        .subtitle {
            font-size: 1.1em;
            opacity: 0.9;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .features-list {
            list-style: none;
            margin-top: 30px;
        }

        .features-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            font-size: 1em;
        }

        .features-list i {
            margin-right: 12px;
            font-size: 1.2em;
            color: #a8c0ff;
        }

        .login-form {
            max-width: 400px;
            margin: 0 auto;
        }

        .form-title {
            font-size: 2em;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .form-subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1em;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.95em;
        }

        .form-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input:hover {
            border-color: #c1c8d0;
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1.1em;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message {
            background: #efe;
            color: #363;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #363;
            font-size: 0.95em;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            color: #666;
        }

        .forgot-password {
            font-size: 0.9em;
            color: #667eea;
            text-decoration: none;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 400px;
            }
            
            .login-left {
                padding: 30px;
                text-align: center;
            }
            
            .login-right {
                padding: 30px;
            }
            
            .welcome-text {
                font-size: 1.8em;
            }
            
            .remember-forgot {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }

        /* Animation */
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

        .login-right {
            animation: fadeInUp 0.6s ease;
        }

        /* Loading animation */
        .btn-loading {
            position: relative;
            color: transparent;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Shake animation for errors */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .shake {
            animation: shake 0.5s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Side - Welcome Message -->
        <div class="login-left">
            <div class="logo">
                <i class="fas fa-hotel"></i>
            </div>
            <h1 class="welcome-text">Welcome Back!</h1>
            <p class="subtitle">Sign in to your account and continue your journey with us. Manage your bookings and discover new experiences.</p>
            
            <ul class="features-list">
                <li><i class="fas fa-key"></i> Secure login</li>
                <li><i class="fas fa-bolt"></i> Quick access to bookings</li>
                <li><i class="fas fa-shield-alt"></i> Your data is protected</li>
                <li><i class="fas fa-clock"></i> 24/7 access to your account</li>
                <li><i class="fas fa-headset"></i> Instant support when needed</li>
            </ul>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-form">
                <h2 class="form-title">Sign In</h2>
                <p class="form-subtitle">Welcome back! Please enter your details</p>

                <?php if (!empty($error)): ?>
                    <div class="error-message" id="errorMessage">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" required 
                               placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" class="form-input" required 
                                   placeholder="Enter your password">
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="remember-forgot">
                        <label class="remember-me">
                            <input type="checkbox" name="remember" id="remember">
                            Remember me
                        </label>
                        <a href="forgot_password.php" class="forgot-password">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-login" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>

                <div class="register-link">
                    Don't have an account? <a href="register.php">Create one here</a>
                </div>

                >
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        // Form submission loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('btn-loading');
            btn.disabled = true;
            
            // Re-enable after 3 seconds (in case of error)
            setTimeout(() => {
                btn.classList.remove('btn-loading');
                btn.disabled = false;
            }, 3000);
        });

        // Add shake animation to error message
        <?php if (!empty($error)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const errorMessage = document.getElementById('errorMessage');
                if (errorMessage) {
                    errorMessage.classList.add('shake');
                }
            });
        <?php endif; ?>

        // Remember me functionality
        document.addEventListener('DOMContentLoaded', function() {
            const rememberCheckbox = document.getElementById('remember');
            const emailInput = document.querySelector('input[name="email"]');
            
            // Check if we have saved credentials
            const savedEmail = localStorage.getItem('rememberedEmail');
            if (savedEmail) {
                emailInput.value = savedEmail;
                rememberCheckbox.checked = true;
            }
            
            // Save on form submit if remember me is checked
            document.getElementById('loginForm').addEventListener('submit', function() {
                if (rememberCheckbox.checked) {
                    localStorage.setItem('rememberedEmail', emailInput.value);
                } else {
                    localStorage.removeItem('rememberedEmail');
                }
            });
        });

        // Add interactive animations
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
            });
        });

        // Auto-focus on email field
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="email"]').focus();
        });
    </script>
</body>
</html>
