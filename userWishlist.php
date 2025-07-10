<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smartcart";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$user_id = $_SESSION['userid'];

if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $remove_id = intval($_GET['remove']);
    $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $remove_id);
    $stmt->execute();
    header("Location: userWishlist.php");
    exit();
}

if (isset($_GET['add_to_cart']) && is_numeric($_GET['add_to_cart'])) {
    $product_id = intval($_GET['add_to_cart']);
    $stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();

    if ($cart_result->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
    }

    header("Location: userWishlist.php");
    exit();
}

$stmt = $conn->prepare("SELECT p.id, p.name, p.price, p.image
FROM wishlist w
JOIN products p ON w.product_id = p.id

WHERE w.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wishlist = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCart | Wishlist</title>
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
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    <main style="max-width:1100px;margin:2rem auto;">
    <h1 class="form-title">Your Wishlist</h1>
    <?php if ($wishlist->num_rows > 0): ?>
        <div class="product-grid">
            <?php while ($item = $wishlist->fetch_assoc()): ?>
                <div class="product-card fade-in" style="position:relative;">
                    <form method="POST" action="userWishlist.php?remove=<?= $item['id'] ?>" style="position:absolute;top:1rem;right:1rem;z-index:2;">
                        <button type="submit" onclick="return confirm('Are you sure you want to remove this item?')" class="wishlist-heart" title="Remove from Wishlist">&hearts;</button>
                    </form>
                    <a href="productDetails.php?id=<?= $item['id'] ?>" style="display:block;">
                        <img src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    </a>
                    <div style="width:100%;text-align:center;margin-top:1rem;">
                        <h4 style="color:var(--primary);font-size:1.1rem;font-weight:600;margin-bottom:0.5rem;"><?= htmlspecialchars($item['name']) ?></h4>
                        <div style="color:var(--accent);font-size:1.1rem;font-weight:700;">₹<?= number_format($item['price'], 2) ?></div>
                    </div>
                    <form method="POST" action="userWishlist.php?add_to_cart=<?= $item['id'] ?>" style="margin-top:1rem;width:100%;">
                        <button type="submit" class="dsa-btn" style="width:100%;">Add to Cart</button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="form-card" style="text-align:center;">
            <div style="font-size:2.5rem;color:#e5e7eb;">&#9825;</div>
            <h3 style="color:var(--primary);margin-top:1rem;">Your wishlist is empty</h3>
            <p style="color:var(--gray-text);margin:0.5rem 0 1.5rem 0;">Start adding items you love!</p>
            <a href="categories.php" class="dsa-btn" style="padding:0.7rem 2rem;">Browse Products</a>
        </div>
    <?php endif; ?>
    </main>
    <footer class="footer">&copy; <?php echo date('Y'); ?> SmartCart. All rights reserved.</footer>
</body>
</html>