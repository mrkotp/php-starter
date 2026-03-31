<?php
// Database configuration
define('DB_HOST', 'db.fr-pari1.bengt.wasmernet.com');
define('DB_PORT', '10272');
define('DB_NAME', 'Website');
define('DB_USER', 'b9cc5c6670b680009007389106e2');
define('DB_PASS', '069cb9cc-5c66-719a-8000-fc70189cfa');

// Start session
session_start();

// Theme configuration
$available_themes = ['light', 'dark', 'blue', 'green'];
$default_theme = 'light';

// Set theme from session or cookie
if (isset($_GET['theme']) && in_array($_GET['theme'], $available_themes)) {
    $_SESSION['theme'] = $_GET['theme'];
    setcookie('theme', $_GET['theme'], time() + (86400 * 30), "/");
} elseif (isset($_SESSION['theme'])) {
    $current_theme = $_SESSION['theme'];
} elseif (isset($_COOKIE['theme'])) {
    $current_theme = $_COOKIE['theme'];
    $_SESSION['theme'] = $current_theme;
} else {
    $current_theme = $default_theme;
}

// Database connection
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $db = new PDO($dsn, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    return $db;
}

// Create tables if they don't exist
function initDatabase() {
    $db = getDB();
    
    // Users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        balance DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Recharge transactions table
    $db->exec("CREATE TABLE IF NOT EXISTS recharges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50),
        transaction_id VARCHAR(100) UNIQUE,
        status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

// Initialize database
initDatabase();

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function getUserBalance($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result['balance'] : 0;
}

function updateUserBalance($user_id, $amount) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    return $stmt->execute([$amount, $user_id]);
}

function generateTransactionId() {
    return uniqid('txn_') . '_' . bin2hex(random_bytes(8));
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('index.php');
}

// Handle form submissions
$message = '';
$message_type = '';

// Registration
if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $message = "Passwords do not match!";
        $message_type = "error";
    } else {
        $db = getDB();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hashed_password]);
            $message = "Registration successful! Please login.";
            $message_type = "success";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $message = "Username or email already exists!";
            } else {
                $message = "Registration failed: " . $e->getMessage();
            }
            $message_type = "error";
        }
    }
}

// Login
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['balance'] = $user['balance'];
        redirect('index.php?page=dashboard');
    } else {
        $message = "Invalid username or password!";
        $message_type = "error";
    }
}

// Recharge
if (isset($_POST['recharge']) && isLoggedIn()) {
    $amount = floatval($_POST['amount']);
    $payment_method = $_POST['payment_method'];
    
    if ($amount <= 0) {
        $message = "Invalid amount!";
        $message_type = "error";
    } else {
        $db = getDB();
        $transaction_id = generateTransactionId();
        
        try {
            $stmt = $db->prepare("INSERT INTO recharges (user_id, amount, payment_method, transaction_id, status) VALUES (?, ?, ?, ?, 'completed')");
            $stmt->execute([$_SESSION['user_id'], $amount, $payment_method, $transaction_id]);
            
            // Update user balance
            if (updateUserBalance($_SESSION['user_id'], $amount)) {
                $_SESSION['balance'] = getUserBalance($_SESSION['user_id']);
                $message = "Recharge successful! Amount: $" . number_format($amount, 2);
                $message_type = "success";
            } else {
                $message = "Failed to update balance!";
                $message_type = "error";
            }
        } catch (PDOException $e) {
            $message = "Recharge failed: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get current page
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website - <?php echo ucfirst($page); ?></title>
    <style>
        /* Base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            transition: background-color 0.3s, color 0.3s;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Navigation */
        .navbar {
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .nav-links a {
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .nav-links a:hover {
            opacity: 0.8;
        }
        
        .theme-selector {
            display: flex;
            gap: 0.5rem;
        }
        
        .theme-btn {
            padding: 0.25rem 0.5rem;
            border: 1px solid;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        /* Main content */
        .main-content {
            min-height: calc(100vh - 140px);
            padding: 2rem 0;
        }
        
        /* Cards */
        .card {
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Balance display */
        .balance {
            font-size: 1.25rem;
            font-weight: bold;
            padding: 0.5rem 1rem;
            border-radius: 4px;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 1rem 0;
            margin-top: 2rem;
        }
        
        /* Dashboard stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 1.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }
        
        /* Recharge methods */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .payment-method {
            padding: 1rem;
            text-align: center;
            border: 2px solid;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-method.selected {
            border-color: #007bff;
            background-color: rgba(0,123,255,0.1);
        }
        
        /* Theme styles */
        body.theme-light {
            background-color: #f8f9fa;
            color: #212529;
        }
        
        body.theme-light .navbar {
            background-color: #fff;
        }
        
        body.theme-light .logo {
            color: #007bff;
        }
        
        body.theme-light .nav-links a {
            color: #212529;
        }
        
        body.theme-light .card {
            background-color: #fff;
            border: 1px solid #dee2e6;
        }
        
        body.theme-light .balance {
            background-color: #e9ecef;
            color: #28a745;
        }
        
        body.theme-light .footer {
            background-color: #fff;
            border-top: 1px solid #dee2e6;
        }
        
        body.theme-dark {
            background-color: #1a1a2e;
            color: #e0e0e0;
        }
        
        body.theme-dark .navbar {
            background-color: #16213e;
        }
        
        body.theme-dark .logo {
            color: #0f3460;
        }
        
        body.theme-dark .nav-links a {
            color: #e0e0e0;
        }
        
        body.theme-dark .card {
            background-color: #16213e;
            border: 1px solid #0f3460;
        }
        
        body.theme-dark .balance {
            background-color: #0f3460;
            color: #4caf50;
        }
        
        body.theme-dark .footer {
            background-color: #16213e;
            border-top: 1px solid #0f3460;
        }
        
        body.theme-blue {
            background-color: #e3f2fd;
            color: #01579b;
        }
        
        body.theme-blue .navbar {
            background-color: #01579b;
        }
        
        body.theme-blue .logo {
            color: #fff;
        }
        
        body.theme-blue .nav-links a {
            color: #fff;
        }
        
        body.theme-blue .card {
            background-color: #fff;
            border: 1px solid #0288d1;
        }
        
        body.theme-blue .balance {
            background-color: #0288d1;
            color: #fff;
        }
        
        body.theme-green {
            background-color: #e8f5e9;
            color: #1b5e20;
        }
        
        body.theme-green .navbar {
            background-color: #2e7d32;
        }
        
        body.theme-green .logo {
            color: #fff;
        }
        
        body.theme-green .nav-links a {
            color: #fff;
        }
        
        body.theme-green .card {
            background-color: #fff;
            border: 1px solid #4caf50;
        }
        
        body.theme-green .balance {
            background-color: #4caf50;
            color: #fff;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .navbar .container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="theme-<?php echo $current_theme; ?>">
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">🎮 Website</a>
            <div class="nav-links">
                <a href="index.php?page=home">Home</a>
                <?php if (isLoggedIn()): ?>
                    <a href="index.php?page=dashboard">Dashboard</a>
                    <a href="index.php?page=recharge">Recharge</a>
                    <span class="balance">💰 $<?php echo number_format($_SESSION['balance'], 2); ?></span>
                    <a href="index.php?logout=1" class="btn btn-danger">Logout</a>
                <?php else: ?>
                    <a href="index.php?page=login">Login</a>
                    <a href="index.php?page=register">Register</a>
                <?php endif; ?>
                <div class="theme-selector">
                    <?php foreach ($available_themes as $theme): ?>
                        <a href="?theme=<?php echo $theme; ?>&page=<?php echo $page; ?>" class="theme-btn"><?php echo ucfirst($theme); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </nav>
    
    <main class="main-content">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($page === 'home'): ?>
                <div class="card">
                    <h1>Welcome to Our Platform</h1>
                    <p>Your one-stop destination for all your needs. Register now and start exploring!</p>
                    <?php if (!isLoggedIn()): ?>
                        <div style="margin-top: 1rem;">
                            <a href="index.php?page=register" class="btn btn-primary">Get Started</a>
                            <a href="index.php?page=login" class="btn" style="margin-left: 0.5rem;">Login</a>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 1rem;">
                            <a href="index.php?page=dashboard" class="btn btn-primary">Go to Dashboard</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="stats-grid">
                    <div class="card stat-card">
                        <h3>Secure Platform</h3>
                        <p>Your data is safe with us</p>
                    </div>
                    <div class="card stat-card">
                        <h3>24/7 Support</h3>
                        <p>We're here to help</p>
                    </div>
                    <div class="card stat-card">
                        <h3>Instant Recharge</h3>
                        <p>Quick and easy payments</p>
                    </div>
                </div>
            
            <?php elseif ($page === 'register' && !isLoggedIn()): ?>
                <div class="card" style="max-width: 500px; margin: 0 auto;">
                    <h2>Create Account</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="register" class="btn btn-primary">Register</button>
                    </form>
                    <p style="margin-top: 1rem;">Already have an account? <a href="index.php?page=login">Login here</a></p>
                </div>
            
            <?php elseif ($page === 'login' && !isLoggedIn()): ?>
                <div class="card" style="max-width: 500px; margin: 0 auto;">
                    <h2>Login</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Username or Email</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary">Login</button>
                    </form>
                    <p style="margin-top: 1rem;">Don't have an account? <a href="index.php?page=register">Register here</a></p>
                </div>
            
            <?php elseif ($page === 'dashboard' && isLoggedIn()): ?>
                <div class="card">
                    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>Current Balance</h3>
                            <div class="stat-value">$<?php echo number_format($_SESSION['balance'], 2); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Member Since</h3>
                            <div class="stat-value"><?php echo date('Y'); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <h3>Quick Actions</h3>
                    <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                        <a href="index.php?page=recharge" class="btn btn-success">Recharge Now</a>
                    </div>
                </div>
            
            <?php elseif ($page === 'recharge' && isLoggedIn()): ?>
                <div class="card" style="max-width: 600px; margin: 0 auto;">
                    <h2>Recharge Your Account</h2>
                    <p>Current Balance: <strong>$<?php echo number_format($_SESSION['balance'], 2); ?></strong></p>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Amount ($)</label>
                            <input type="number" name="amount" step="0.01" min="1" required placeholder="Enter amount">
                        </div>
                        
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" required>
                                <option value="credit_card">Credit Card</option>
                                <option value="paypal">PayPal</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="crypto">Cryptocurrency</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="recharge" class="btn btn-success">Process Recharge</button>
                    </form>
                    
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #dee2e6;">
                        <h4>Recent Transactions</h4>
                        <?php
                        $db = getDB();
                        $stmt = $db->prepare("SELECT * FROM recharges WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                        $stmt->execute([$_SESSION['user_id']]);
                        $transactions = $stmt->fetchAll();
                        ?>
                        <?php if (count($transactions) > 0): ?>
                            <table style="width: 100%; margin-top: 1rem; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                        <th style="padding: 0.5rem; text-align: left;">Date</th>
                                        <th style="padding: 0.5rem; text-align: left;">Amount</th>
                                        <th style="padding: 0.5rem; text-align: left;">Method</th>
                                        <th style="padding: 0.5rem; text-align: left;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr style="border-bottom: 1px solid #dee2e6;">
                                            <td style="padding: 0.5rem;"><?php echo date('Y-m-d', strtotime($tx['created_at'])); ?></td>
                                            <td style="padding: 0.5rem;">$<?php echo number_format($tx['amount'], 2); ?></td>
                                            <td style="padding: 0.5rem;"><?php echo ucfirst(str_replace('_', ' ', $tx['payment_method'])); ?></td>
                                            <td style="padding: 0.5rem;">
                                                <span style="color: <?php echo $tx['status'] === 'completed' ? '#28a745' : '#ffc107'; ?>">
                                                    <?php echo ucfirst($tx['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No transactions yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Website. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
