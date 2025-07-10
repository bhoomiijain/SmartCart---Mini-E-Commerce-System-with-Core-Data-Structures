<?php
require_once 'session_helper.php';
start_session_if_not_started();

if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smartcart";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['userid'];
$msg = "";
$msgType = "";

$stmt = $conn->prepare("SELECT username, email, address_line1, address_line2, city, state, postal_code, country FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newEmail = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $address1 = htmlspecialchars(trim($_POST["address1"]));
    $address2 = htmlspecialchars(trim($_POST["address2"]));
    $city     = htmlspecialchars(trim($_POST["city"]));
    $state    = htmlspecialchars(trim($_POST["state"]));
    $postal   = htmlspecialchars(trim($_POST["postal"]));
    $country  = htmlspecialchars(trim($_POST["country"]));

    if (!empty($newEmail) && filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        if (!empty($postal) && !preg_match('/^[0-9A-Za-z\- ]{3,12}$/', $postal)) {
            $msg = "Invalid postal code format.";
            $msgType = "error";
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ? WHERE id = ?");
            $stmt->bind_param("sssssssi", $newEmail, $address1, $address2, $city, $state, $postal, $country, $user_id);

            if ($stmt->execute()) {
                $msg = "Profile updated successfully!";
                $msgType = "success";
                $user['email'] = $newEmail;
                $user['address_line1'] = $address1;
                $user['address_line2'] = $address2;
                $user['city'] = $city;
                $user['state'] = $state;
                $user['postal_code'] = $postal;
                $user['country'] = $country;
            } else {
                $msg = "Error updating profile.";
                $msgType = "error";
            }
            $stmt->close();
        }
    } else {
        $msg = "Please fill in all required fields with valid data.";
        $msgType = "error";
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCart | Update Profile</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="background: #f8fafc url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1200&q=80') no-repeat center top fixed; background-size: cover; min-height: 100vh;">
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
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    <main style="max-width:500px;margin:3.5rem auto 2rem auto;background:rgba(255,255,255,0.92);border-radius:18px;box-shadow:0 4px 32px #e3e3e3;padding:2.5rem 2rem;backdrop-filter:blur(2px);">
        <h2 style="margin-bottom:1.5rem;text-align:center;color:var(--primary);font-size:2rem;font-weight:700;">Account Details</h2>
        <div style="text-align:center;margin-bottom:2rem;">
            <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="User Avatar" width="72" height="72" style="border-radius:50%;box-shadow:0 2px 12px #e3e3e3;background:#fff;">
            <div style="font-size:1.15rem;font-weight:600;color:var(--primary);margin-top:0.7rem;">@<?= htmlspecialchars($user['username']) ?></div>
        </div>
        <?php if ($msg): ?>
            <div style="background:<?= $msgType==='success'?'#d1fae5':'#fee2e2' ?>;color:<?= $msgType==='success'?'#065f46':'#b91c1c' ?>;padding:1rem;border-radius:6px;margin-bottom:1rem;">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        <form method="POST" class="card" style="padding:2rem;background:transparent;box-shadow:none;">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            <div class="form-group">
                <label for="address1">Address Line 1</label>
                <input type="text" id="address1" name="address1" value="<?= htmlspecialchars($user['address_line1']) ?>">
            </div>
            <div class="form-group">
                <label for="address2">Address Line 2</label>
                <input type="text" id="address2" name="address2" value="<?= htmlspecialchars($user['address_line2']) ?>">
            </div>
            <div class="form-group">
                <label for="city">City</label>
                <input type="text" id="city" name="city" value="<?= htmlspecialchars($user['city']) ?>">
            </div>
            <div class="form-group">
                <label for="state">State</label>
                <input type="text" id="state" name="state" value="<?= htmlspecialchars($user['state']) ?>">
            </div>
            <div class="form-group">
                <label for="postal">Postal Code</label>
                <input type="text" id="postal" name="postal" value="<?= htmlspecialchars($user['postal_code']) ?>">
            </div>
            <div class="form-group">
                <label for="country">Country</label>
                <input type="text" id="country" name="country" value="<?= htmlspecialchars($user['country']) ?>">
            </div>
            <button class="btn-primary" type="submit">Update Profile</button>
        </form>
    </main>
    <footer class="footer">&copy; <?php echo date('Y'); ?> SmartCart. All rights reserved.</footer>
</body>
</html>
<?php $conn->close(); ?>
