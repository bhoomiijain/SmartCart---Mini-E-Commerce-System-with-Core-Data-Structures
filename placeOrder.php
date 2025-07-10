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

// Fetch cart items for the user
$cart_items = $conn->query("SELECT c.quantity, p.name, p.image, p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = $user_id");

// Calculate total
$total = 0;
$items = [];
while ($item = $cart_items->fetch_assoc()) {
    $item_total = $item['quantity'] * $item['price'];
    $total += $item_total;
    $items[] = $item + ['item_total' => $item_total];
}

// Example coupons
$coupons = [
    ['code' => 'SAVE10', 'desc' => 'Get 10% off on orders above ₹500', 'discount' => 0.10, 'min' => 500],
    ['code' => 'FREESHIP', 'desc' => 'Free shipping on orders above ₹300', 'discount' => 0, 'min' => 300],
    ['code' => 'WELCOME50', 'desc' => 'Flat ₹50 off for new users', 'discount' => 50, 'min' => 0],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Summary | SmartCart</title>
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
        <h2 style="margin-bottom:1.5rem;">Order Summary</h2>
        <?php if (count($items) > 0): ?>
        <div class="product-card" style="margin-bottom:2rem;">
            <h3 style="margin-bottom:1rem;">Your Cart Items</h3>
            <div class="order-products-row">
                <?php foreach ($items as $item): ?>
                <div class="order-product">
                    <img src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <div class="order-product-info">
                        <div class="order-product-name"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="order-product-qty">Qty: <?= $item['quantity'] ?></div>
                        <div class="order-product-price">₹<?= number_format($item['price'], 2) ?></div>
                        <div class="order-product-total">Subtotal: ₹<?= number_format($item['item_total'], 2) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="order-total" style="margin-top:1.5rem;font-size:1.2rem;">Total: <span>₹<?= number_format($total, 2) ?></span></div>
        </div>
        <div class="product-card" style="margin-bottom:2rem;">
            <h3 style="margin-bottom:1rem;">Available Coupons</h3>
            <ul style="list-style:disc inside;">
                <?php foreach ($coupons as $coupon): ?>
                <li style="margin-bottom:0.7rem;">
                    <span class="coupon-code" style="font-weight:bold; color:var(--accent);background:#fff3e0;padding:0.2rem 0.7rem;border-radius:5px;"> <?= htmlspecialchars($coupon['code']) ?> </span>
                    <span style="margin-left:0.7rem; color:var(--gray-text);"> <?= htmlspecialchars($coupon['desc']) ?> </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <form action="confirmOrder.php" method="post" class="product-card order-coupon-form" style="text-align:center;max-width:500px;margin:2rem auto 0 auto;box-shadow:0 2px 12px #f3f3f3;">
            <div style="display:flex;flex-direction:column;align-items:center;gap:1rem;">
                <div style="width:100%;display:flex;align-items:center;gap:0.5rem;">
                    <input type="text" name="coupon" id="coupon" placeholder="Enter coupon code" style="flex:1;padding:0.7rem 1rem;border-radius:6px;border:1px solid #ddd;font-size:1.1rem;" autocomplete="off">
                    <button type="submit" class="dsa-btn" style="padding:0.7rem 2.2rem;font-size:1.1rem;">Apply & Confirm Order</button>
                </div>
                <div style="font-size:0.98rem;color:var(--gray-text);">Available: <span style="color:var(--accent);font-weight:500;">SAVE10</span>, <span style="color:var(--accent);font-weight:500;">FREESHIP</span>, <span style="color:var(--accent);font-weight:500;">WELCOME50</span></div>
            </div>
        </form>
        <?php else: ?>
        <div class="product-card" style="text-align:center;">
            <h3 style="color:var(--primary);margin-bottom:0.5rem;">Your cart is empty</h3>
            <a href="categories.php" class="dsa-btn" style="margin-top:1rem;">Start Shopping</a>
        </div>
        <?php endif; ?>
    </main>
    <footer class="footer">&copy; <?php echo date('Y'); ?> SmartCart. All rights reserved.</footer>
</body>
</html>
<?php $conn->close(); ?>
