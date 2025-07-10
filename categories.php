<?php
require_once 'session_helper.php';
start_session_if_not_started();

$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "smartcart";
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$query = "
    SELECT c.*, COUNT(pc.product_id) as product_count 
    FROM categories c
    LEFT JOIN product_categories pc ON c.id = pc.category_id
    GROUP BY c.id
    ORDER BY c.name ASC
";
$categories = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Category display name to DB name mapping
$category_name_map = [
    'Butter and Ghee' => 'Butter & Ghee',
    'Honey and Sweeteners' => 'Honey & Sweeteners',
    'Juices and Beverages' => 'Juices & Beverages',
    'Yogurt and Curd' => 'Yogurt & Curd',
    // Add more mappings as needed
];

// Helper: get DB name from display name
function get_db_category_name($display_name, $map) {
    return $map[$display_name] ?? $display_name;
}

$selected_category = isset($_GET['category']) ? htmlspecialchars(trim($_GET['category'])) : null;
$products = [];
if ($selected_category) {
    $db_category = get_db_category_name($selected_category, $category_name_map);
    $stmt = $conn->prepare("
        SELECT p.* 
        FROM products p
        JOIN product_categories pc ON p.id = pc.product_id
        JOIN categories c ON pc.category_id = c.id
        WHERE c.name = ? AND p.status = 'active'
    ");
    $stmt->bind_param("s", $db_category);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Cart count for header (only for logged-in users)
$cart_count = 0;
if (isset($_SESSION['userid'])) {
    $user_id = $_SESSION['userid'];
    $res = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id");
    if ($res && $row = $res->fetch_assoc()) {
        $cart_count = intval($row['total']);
    }
}

// Handle Add to Cart (same as dashboard)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['userid'])) {
        header("Location: login.php");
        exit;
    }
    $user_id = $_SESSION['userid'];
    $product_id = (int)$_POST['product_id'];

    $stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
        $stmt->bind_param("ii", $user_id, $product_id);
        if ($stmt->execute()) {
            $_SESSION['cart_message'] = "Product added to cart!";
            $_SESSION['cart_status'] = "success";
        } else {
            $_SESSION['cart_message'] = "Failed to add product to cart. Please try again.";
            $_SESSION['cart_status'] = "failure";
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        if ($stmt->execute()) {
            $_SESSION['cart_message'] = "Product quantity updated in cart.";
            $_SESSION['cart_status'] = "success";
        } else {
            $_SESSION['cart_message'] = "Failed to update cart. Please try again.";
            $_SESSION['cart_status'] = "failure";
        }
        $stmt->close();
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCart | Categories</title>
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
            <a href="cart.php" style="position:relative;">
                Cart
                <?php if (isset($cart_count) && $cart_count > 0 && basename($_SERVER['PHP_SELF']) !== 'cart.php'): ?>
                    <span style="position:absolute;top:-8px;right:-12px;background:var(--accent);color:#fff;font-size:0.7em;padding:1px 5px;border-radius:50%;font-weight:700;min-width:16px;text-align:center;line-height:1.3;z-index:2;">
                        <?= $cart_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="orders.php">Orders</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    <main style="max-width:1100px;margin:2rem auto;">
        <h2 style="margin-bottom:1.5rem;">Categories</h2>
        <div style="display:flex;gap:2.5rem;align-items:flex-start;">
            <!-- Sidebar: Category List -->
            <aside style="flex:0 0 220px;min-width:180px;">
                <div style="background:var(--white);border:1px solid var(--border);border-radius:10px;padding:1.2rem 1rem;box-shadow:0 2px 12px rgba(35,47,62,0.06);">
                    <h4 style="margin:0 0 1rem 0;color:var(--primary);font-size:1.1rem;">All Categories</h4>
                    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:0.5rem;">
                    <?php foreach ($categories as $cat): ?>
                        <?php if ($cat['product_count'] > 0): ?>
                            <?php 
                                $catName = str_replace('&', 'and', $cat['name']);
                                // Use display name for link, but DB name for query
                            ?>
                            <li>
                                <a href="categories.php?category=<?= urlencode($catName) ?>" style="display:block;padding:0.6rem 1rem;border-radius:6px;color:<?= ($selected_category === $catName) ? 'var(--white)' : 'var(--primary)' ?>;background:<?= ($selected_category === $catName) ? 'var(--accent)' : 'transparent' ?>;font-weight:500;text-decoration:none;transition:background 0.2s;">
                                    <?= htmlspecialchars($catName) ?> <span style="color:var(--gray-text);font-size:0.95em;">(<?= $cat['product_count'] ?>)</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </ul>
                </div>
            </aside>
            <!-- Main: Products Grid -->
            <section style="flex:1;">
                <?php if ($selected_category): ?>
                    <h3 style="margin-bottom:1rem;">Products in "<?= htmlspecialchars($selected_category) ?>"</h3>
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="card product-card">
                                <img src="uploads/<?= htmlspecialchars($product['image'] ?? 'default.jpg') ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                <div style="padding:1rem 0;">
                                    <h4 style="margin:0 0 0.5rem 0; font-size:1.1rem; color:var(--primary);"> <?= htmlspecialchars($product['name']) ?> </h4>
                                    <div style="margin-bottom:0.5rem; color:var(--gray-text);">Brand: <?= htmlspecialchars($product['brand']) ?></div>
                                    <div style="font-weight:bold; color:var(--accent); font-size:1.1rem;">₹<?= htmlspecialchars($product['price']) ?></div>
                                </div>
                                <form method="POST" style="margin:0;display:inline-block;">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button class="btn-primary" name="add_to_cart" type="submit">Add to Cart</button>
                                </form>
                                <form method="POST" style="margin:0;display:inline-block;">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button class="btn-primary" name="add_to_wishlist" type="submit">Wishlist</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="color:var(--gray-text);font-size:1.1rem;margin-top:2rem;">Select a category to view products.</div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <footer class="footer">&copy; <?php echo date('Y'); ?> SmartCart. All rights reserved.</footer>
</body>
</html>