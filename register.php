<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smartcart";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = htmlspecialchars(trim($_POST['username']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);
    $role = "user"; 
    if (empty($username) || empty($email) || empty($password)) {
        $message = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format!";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $message = "Username must be 3-20 characters and contain only letters, numbers, and underscores.";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $message = "Username or email already taken!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, created) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
            if ($stmt->execute()) {
                header("Location: login.php?register=success");
                exit;
            } else {
                $message = "Registration failed: " . $stmt->error;
            }
        }
        $stmt->close();
    }
    $conn->close();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCart | Register</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-card { background: var(--white); border-radius: 12px; box-shadow: 0 2px 16px rgba(0,0,0,0.07); padding: 2.2rem 2.2rem 1.5rem 2.2rem; margin-top: 2rem; max-width: 340px; margin-left: auto; margin-right: auto; }
        .auth-card h2 { color: var(--primary); margin-bottom: 1.2rem; text-align: left; width: 100%; padding-left: 0.2rem; font-size: 1.5rem; font-weight: 700; }
        .form-group { margin-bottom: 1.1rem; display: flex; flex-direction: column; align-items: stretch; }
        .form-group label { display: block; margin-bottom: 0.4rem; color: var(--primary); font-weight: 500; text-align: left; padding-left: 0.1rem; font-size: 1rem; }
        .form-group input { width: 100%; max-width: 260px; text-align: left; font-size: 1.05rem; padding: 0.6rem 0.8rem; margin: 0 auto; }
        .btn-primary { font-size: 1.08rem; padding: 0.8rem 0; width: 100%; max-width: 260px; display: block; margin: 0 auto; border-radius: 8px; }
        .error-msg { color: #b91c1c; background: #fee2e2; border: 1px solid #fca5a5; padding: 0.7rem 1rem; border-radius: 6px; margin-bottom: 1rem; text-align: center; }
        .success-msg { color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; padding: 0.7rem 1rem; border-radius: 6px; margin-bottom: 1rem; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <span class="logo" style="display:flex;align-items:center;text-decoration:none;gap:0.5rem;">
            <svg width="38" height="38" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;"><rect width="38" height="38" rx="8" fill="#fff"/><g><rect x="7" y="13" width="24" height="13" rx="4" fill="#ff9800"/><rect x="10" y="16" width="18" height="7" rx="2" fill="#fff"/><circle cx="13.5" cy="29.5" r="2.5" fill="#ff9800"/><circle cx="24.5" cy="29.5" r="2.5" fill="#ff9800"/></g></svg>
            <span style="font-size:1.7rem;font-weight:700;color:#fff;letter-spacing:1px;text-shadow:0 2px 8px #ff9800,0 1px 0 #ff9800;">SmartCart</span>
        </span>
        <nav>
            <a href="dashboard.php">Home</a>
            <a href="categories.php">Categories</a>
            <a href="userWishlist.php">Wishlist</a>
            <a href="cart.php">Cart</a>
            <a href="orders.php">Orders</a>
        </nav>
    </header>
    <main style="max-width:400px;margin:2rem auto;">
        <div class="auth-card">
            <h2>Create an Account</h2>
            <?php if (!empty($message)): ?>
                <div class="error-msg"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" onsubmit="return checkPasswordsMatch();">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <div id="password-match-msg" style="color:#b91c1c;font-size:0.97rem;margin-bottom:0.7rem;display:none;text-align:left;"></div>
                <button class="btn-primary" type="submit">Register</button>
            </form>
            <p style="margin-top:1rem;">Already have an account? <a href="login.php" style="color:var(--accent);">Login</a></p>
        </div>
    </main>
    <footer class="footer">&copy; <?php echo date('Y'); ?> SmartCart. All rights reserved.</footer>
    <script>
function checkPasswordsMatch() {
    var pwd = document.getElementById('password').value;
    var cpwd = document.getElementById('confirm_password').value;
    var msg = document.getElementById('password-match-msg');
    if (pwd !== cpwd) {
        msg.textContent = 'Passwords do not match!';
        msg.style.display = 'block';
        return false;
    } else {
        msg.textContent = '';
        msg.style.display = 'none';
        return true;
    }
}
document.getElementById('confirm_password')?.addEventListener('input', function() {
    var pwd = document.getElementById('password').value;
    var cpwd = this.value;
    var msg = document.getElementById('password-match-msg');
    if (pwd !== cpwd) {
        msg.textContent = 'Passwords do not match!';
        msg.style.display = 'block';
    } else {
        msg.textContent = '';
        msg.style.display = 'none';
    }
});
</script>
</body>
</html>
