<?php
session_start();

// ✅ show errors instead of blank page
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once "../config/db.php";
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        // check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $error = "⚠️ Email already registered!";
        } else {
            // insert as guest by default
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'guest')");
            $stmt->execute([$name, $email, $hashedPassword]);

            $_SESSION["user_id"] = $pdo->lastInsertId();
            $_SESSION["name"] = $name;
            $_SESSION["role"] = "guest";

            header("Location: guest_dashboard.php");
            exit;
        }
    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Hotel Booking System</title>
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

        .register-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .register-left {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .register-right {
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

        .register-form {
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

        .btn-register {
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

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
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

        .password-strength {
            margin-top: 5px;
            font-size: 0.85em;
            color: #666;
        }

        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }

        @media (max-width: 768px) {
            .register-container {
                flex-direction: column;
                max-width: 400px;
            }
            
            .register-left {
                padding: 30px;
                text-align: center;
            }
            
            .register-right {
                padding: 30px;
            }
            
            .welcome-text {
                font-size: 1.8em;
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

        .register-right {
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
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Left Side - Welcome Message -->
        <div class="register-left">
            <div class="logo">
                <i class="fas fa-hotel"></i>
            </div>
            <h1 class="welcome-text">Join Our Hotel Family</h1>
            <p class="subtitle">Create your account and start booking your perfect stay with ease and comfort.</p>
            
            <ul class="features-list">
                <li><i class="fas fa-check-circle"></i> Easy room booking</li>
                <li><i class="fas fa-check-circle"></i> Best price guarantee</li>
                <li><i class="fas fa-check-circle"></i> Instant confirmation</li>
                <li><i class="fas fa-check-circle"></i> 24/7 customer support</li>
                <li><i class="fas fa-check-circle"></i> Secure payment</li>
            </ul>
        </div>

        <!-- Right Side - Registration Form -->
        <div class="register-right">
            <div class="register-form">
                <h2 class="form-title">Create Account</h2>
                <p class="form-subtitle">Sign up to book your perfect stay</p>

                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="registrationForm">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-input" required 
                               placeholder="Enter your full name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" required 
                               placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" class="form-input" required 
                                   placeholder="Create a password" minlength="6">
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>

                    <button type="submit" class="btn-register" id="registerBtn">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>

                <div class="login-link">
                    Already have an account? <a href="login.php">Sign in here</a>
                </div>
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

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthElement = document.getElementById('passwordStrength');
            let strength = '';
            let className = '';
            
            if (password.length === 0) {
                strength = '';
            } else if (password.length < 6) {
                strength = 'Weak - too short';
                className = 'strength-weak';
            } else if (password.length < 8) {
                strength = 'Medium';
                className = 'strength-medium';
            } else {
                // Check for complexity
                const hasUpperCase = /[A-Z]/.test(password);
                const hasLowerCase = /[a-z]/.test(password);
                const hasNumbers = /\d/.test(password);
                const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                
                const complexity = [hasUpperCase, hasLowerCase, hasNumbers, hasSpecial].filter(Boolean).length;
                
                if (complexity >= 3) {
                    strength = 'Strong';
                    className = 'strength-strong';
                } else {
                    strength = 'Medium - add more character types';
                    className = 'strength-medium';
                }
            }
            
            strengthElement.textContent = strength;
            strengthElement.className = 'password-strength ' + className;
        });

        // Form submission loading state
        document.getElementById('registrationForm').addEventListener('submit', function() {
            const btn = document.getElementById('registerBtn');
            btn.classList.add('btn-loading');
            btn.disabled = true;
            setTimeout(() => {
                btn.classList.remove('btn-loading');
                btn.disabled = false;
            }, 3000);
        });

        // Add some interactive animations
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
    </script>
</body>
</html>