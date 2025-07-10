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

$stmt = $conn->prepare("SELECT address_line1, address_line2, city, state, postal_code, country FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($line1, $line2, $city, $state, $postal, $country);
$stmt->fetch();
$stmt->close();

$sql = "SELECT o.id, o.order_date, o.total_amount, o.status
        FROM orders o
        WHERE o.user_id = ?
        ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | SmartCart</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <a href="dashboard.php" class="logo" style="display:flex;align-items:center;text-decoration:none;gap:0.5rem;">
            <svg width="38" height="38" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;"><rect width="38" height="38" rx="8" fill="#fff"/><g><rect x="7" y="13" width="24" height="13" rx="4" fill="#ff9800"/><rect x="10" y="16" width="18" height="7" rx="2" fill="#fff"/><circle cx="13.5" cy="29.5" r="2.5" fill="#ff9800"/><circle cx="24.5" cy="29.5" r="2.5" fill="#ff9800"/></g></svg>
            <span style="font-size:1.7rem;font-weight:700;color:#fff;letter-spacing:1px;text-shadow:0 2px 8px #ff9800,0 1px 0 #ff9800;">SmartCart</span>
        </a>
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
    <main style="max-width:1100px;margin:2rem auto;">
        <h2 style="margin-bottom:1.5rem;">My Orders</h2>
        <section class="product-card" style="margin-bottom:2rem;">
            <h3 style="margin-bottom:0.5rem; color:var(--primary);">Shipping Address</h3>
            <address style="color:var(--gray-text);">
                <?= htmlspecialchars($line1) ?><br>
                <?= htmlspecialchars($line2) ?><br>
                <?= htmlspecialchars($city) ?>, <?= htmlspecialchars($state) ?> - <?= htmlspecialchars($postal) ?><br>
                <?= htmlspecialchars($country) ?>
            </address>
            <a href="userUpdate.php" style="color:var(--accent);font-size:0.98rem;">Update Address</a>
        </section>
        <section class="product-card" style="margin-bottom:2rem;">
            <h3 style="margin-bottom:1rem; color:var(--primary);">Available Coupons</h3>
            <ul style="list-style:disc inside;">
                <li style="margin-bottom:0.7rem;">
                    <span class="coupon-code" style="font-weight:bold; color:var(--accent);background:#fff3e0;padding:0.2rem 0.7rem;border-radius:5px;">SAVE10</span>
                    <span style="margin-left:0.7rem; color:var(--gray-text);">Get 10% off on orders above ₹500</span>
                    <div style="font-size:0.97rem;color:#888;margin-left:2.2rem;">Condition: Applicable only if your total order amount is ₹500 or more.</div>
                </li>
                <li style="margin-bottom:0.7rem;">
                    <span class="coupon-code" style="font-weight:bold; color:var(--accent);background:#fff3e0;padding:0.2rem 0.7rem;border-radius:5px;">FREESHIP</span>
                    <span style="margin-left:0.7rem; color:var(--gray-text);">Free shipping on orders above ₹300</span>
                    <div style="font-size:0.97rem;color:#888;margin-left:2.2rem;">Condition: Applicable only if your total order amount is ₹300 or more.</div>
                </li>
                <li style="margin-bottom:0.7rem;">
                    <span class="coupon-code" style="font-weight:bold; color:var(--accent);background:#fff3e0;padding:0.2rem 0.7rem;border-radius:5px;">WELCOME50</span>
                    <span style="margin-left:0.7rem; color:var(--gray-text);">Flat ₹50 off for new users</span>
                    <div style="font-size:0.97rem;color:#888;margin-left:2.2rem;">Condition: Applicable for your first order only.</div>
                </li>
            </ul>
        </section>
        <?php if ($result->num_rows > 0): ?>
            <div class="orders-list">
                <?php while ($row = $result->fetch_assoc()):
                    // Fetch order items for this order
                    $order_id = $row['id'];
                    $items = $conn->query("SELECT oi.quantity, p.name, p.image, p.price FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id");
                ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <span class="order-id">Order #<?= htmlspecialchars($row['id']) ?></span>
                            <span class="order-status <?php echo htmlspecialchars($row['status']); ?>">
                                <?= ucfirst(htmlspecialchars($row['status'])) ?>
                            </span>
                        </div>
                        <div class="order-date">Ordered on <?= date('F j, Y', strtotime($row['order_date'])) ?></div>
                    </div>
                    <div class="order-products-row">
                        <?php while($item = $items->fetch_assoc()): ?>
                        <div class="order-product">
                            <img src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            <div class="order-product-info">
                                <div class="order-product-name"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="order-product-qty">Qty: <?= $item['quantity'] ?></div>
                                <div class="order-product-price">₹<?= number_format($item['price'], 2) ?></div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="order-footer">
                        <div class="order-total">Total: <span>₹<?= number_format($row['total_amount'], 2) ?></span></div>
                        <div class="order-actions">
                            <a href="orderDetails.php?id=<?= htmlspecialchars($row['id']) ?>" class="order-btn">View Details</a>
                            <a href="#" class="order-btn order-btn-secondary">Track</a>
                            <a href="#" class="order-btn order-btn-secondary">Reorder</a>
                        </div>
                        <div class="order-delivery">Estimated Delivery: <?= date('F j, Y', strtotime($row['order_date'] . ' + 5 days')) ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="product-card" style="text-align:center;">
                <h3 style="color:var(--primary);margin-bottom:0.5rem;">No orders found</h3>
                <p style="color:var(--gray-text);">Your order history is currently empty</p>
                <a href="categories.php" class="dsa-btn" style="margin-top:1rem;">Start Shopping</a>
            </div>
        <?php endif; ?>
    </main>
    <footer class="footer">&copy; <?php echo date('Y'); ?> SmartCart. All rights reserved.</footer>
</body>
</html>
<?php $conn->close(); ?>
