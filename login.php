<?php
session_start();

$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "smartcart";

$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = htmlspecialchars(trim($_POST["username"]));
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $message = "All fields are required!";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION["userid"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];

                if ($user["role"] === "admin") {
                    $_SESSION['admin_logged_in'] = true;
                    header("Location: admin.php");
                } else {
                    $_SESSION['user_logged_in'] = true;
                    header("Location: dashboard.php");
                }
                exit;
            } else {
                $message = "Invalid password!";
            }
        } else {
            $message = "User not found!";
        }

        $stmt->close();
    }
    $conn->close();
}

$registerSuccess = isset($_GET['register']) && $_GET['register'] === 'success';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCart | Login</title>
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
    <main style="max-width:700px;margin:0 auto;min-height:calc(100vh - 180px);display:flex;align-items:center;justify-content:center;">
        <div class="auth-card">
            <h2>Sign in to your account</h2>
            <?php if ($registerSuccess): ?>
                <div class="success-msg">Registration successful! Please log in.</div>
            <?php endif; ?>
            <?php if (!empty($message)): ?>
                <div class="error-msg"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button class="btn-primary" type="submit">Login</button>
            </form>
            <p style="margin-top:1rem;text-align:center;">Don't have an account? <a href="register.php" style="color:var(--accent);">Register</a></p>
        </div>
    </main>
    <footer class="footer">&copy; <?php echo date('Y'); ?> SmartCart. All rights reserved.</footer>
</body>
</html>