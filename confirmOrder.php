<?php
require_once 'session_helper.php';
start_session_if_not_started();

if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['userid'];
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smartcart";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch cart items
$cart_items = $conn->query("SELECT c.product_id, c.quantity, p.name, p.image, p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = $user_id");
$items = [];
$total = 0;
while ($item = $cart_items->fetch_assoc()) {
    $item_total = $item['quantity'] * $item['price'];
    $total += $item_total;
    $items[] = $item + ['item_total' => $item_total];
}

// Coupon logic
$applied_coupon = isset($_POST['coupon']) ? strtoupper(trim($_POST['coupon'])) : '';
$discount = 0;
$discount_desc = '';
if ($applied_coupon === 'SAVE10' && $total >= 500) {
    $discount = $total * 0.10;
    $discount_desc = '10% off';
} elseif ($applied_coupon === 'FREESHIP' && $total >= 300) {
    $discount = 0; // For demo, no shipping fee logic
    $discount_desc = 'Free shipping';
} elseif ($applied_coupon === 'WELCOME50') {
    $discount = 50;
    $discount_desc = 'Flat ₹50 off';
}
$final_total = $total - $discount;
if ($final_total < 0) $final_total = 0;

// Place order if cart is not empty
$order_placed = false;
if (count($items) > 0) {
    // Insert order
    $stmt = $conn->prepare("INSERT INTO orders (user_id, order_date, total_amount, status) VALUES (?, NOW(), ?, 'placed')");
    $stmt->bind_param("id", $user_id, $final_total);
    $stmt->execute();
    $order_id = $stmt->insert_id;
    $stmt->close();
    // Insert order items
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($items as $item) {
        $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
        $stmt->execute();
    }
    $stmt->close();
    // Clear cart
    $conn->query("DELETE FROM cart WHERE user_id = $user_id");
    $order_placed = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation | SmartCart</title>
    <link rel="stylesheet" href="style.css">
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
            <a href="userUpdate.php">Account</a>
            <a href="logout.php" id="logout-link">Logout</a>
        </nav>
    </header>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var logoutLink = document.getElementById('logout-link');
        if (logoutLink) {
            logoutLink.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            });
        }
    });
    </script>
    <main style="max-width:900px;margin:2rem auto;">
        <?php if ($order_placed): ?>
        <div class="product-card" style="text-align:center;">
            <h2 style="color:var(--primary);margin-bottom:1rem;">Order Placed Successfully!</h2>
            <p style="font-size:1.1rem;">Thank you for your purchase. Your order ID is <b>#<?= $order_id ?></b>.</p>
            <a href="orders.php" class="dsa-btn" style="margin-top:1.5rem;">View My Orders</a>
        </div>
        <div class="product-card" style="margin-top:2rem;">
            <h3>Order Summary</h3>
            <ul style="list-style:disc inside;">
                <?php foreach ($items as $item): ?>
                <li><?= htmlspecialchars($item['name']) ?> (x<?= $item['quantity'] ?>) - ₹<?= number_format($item['item_total'], 2) ?></li>
                <?php endforeach; ?>
            </ul>
            <div style="margin-top:1rem;">Subtotal: ₹<?= number_format($total, 2) ?></div>
            <?php if ($discount > 0): ?>
            <div style="color:var(--accent);">Coupon Applied (<?= htmlspecialchars($applied_coupon) ?>): -₹<?= number_format($discount, 2) ?> (<?= $discount_desc ?>)</div>
            <?php endif; ?>
            <div style="font-weight:bold;">Total Paid: ₹<?= number_format($final_total, 2) ?></div>
        </div>
        <?php else: ?>
        <div class="product-card" style="text-align:center;">
            <h2 style="color:var(--primary);margin-bottom:1rem;">Order could not be placed</h2>
            <p>Your cart is empty or there was an error. <a href="cart.php">Go back to cart</a></p>
        </div>
        <?php endif; ?>
    </main>
    <footer class="footer">&copy; <?php echo date('Y'); ?> SmartCart. All rights reserved.</footer>
</body>
</html>
<?php $conn->close(); ?>
