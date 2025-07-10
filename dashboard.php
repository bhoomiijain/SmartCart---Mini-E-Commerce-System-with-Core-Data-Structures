<?php
session_start();

if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "smartcart";
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$categories = $conn->query("SELECT * FROM categories")->fetch_all(MYSQLI_ASSOC);
$brands = $conn->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != ''")->fetch_all(MYSQLI_ASSOC);

$categoryFilter = $_GET['category'] ?? [];
$brandFilter = $_GET['brand'] ?? [];
$priceMin = $_GET['price_min'] ?? '';
$priceMax = $_GET['price_max'] ?? '';
$sort = $_GET['sort'] ?? '';

$query = "SELECT * FROM products";
$conditions = [];
$params = [];
$types = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_wishlist'])) {
    $user_id = $_SESSION['userid'];
    $product_id = (int)$_POST['product_id'];

    $stmt = $conn->prepare("SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $product_id);
        if ($stmt->execute()) {
            $_SESSION['wishlist_message'] = "Product added to wishlist!";
            $_SESSION['wishlist_status'] = "success";
        } else {
            $_SESSION['wishlist_message'] = "Failed to add product to wishlist. Please try again.";
            $_SESSION['wishlist_status'] = "failure";
        }
        $stmt->close();
    } else {
        $_SESSION['wishlist_message'] = "Product is already in your wishlist.";
        $_SESSION['wishlist_status'] = "failure";
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}



if (!empty($categoryFilter)) {
    $placeholders = implode(',', array_fill(0, count($categoryFilter), '?'));
    
    $conditions[] = "id IN (
        SELECT product_id 
        FROM product_categories 
        WHERE category_id IN (
            SELECT id 
            FROM categories 
            WHERE name IN ($placeholders)
        )
    )";
    
    $params = array_merge($params, $categoryFilter);
    
    $types .= str_repeat("s", count($categoryFilter));
}

if (!empty($brandFilter)) {
    $placeholders = implode(',', array_fill(0, count($brandFilter), '?'));
    $conditions[] = "brand IN ($placeholders)";
    $params = array_merge($params, $brandFilter);
    $types .= str_repeat("s", count($brandFilter));
}
if ($priceMin !== '') {
    $conditions[] = "price >= ?";
    $params[] = $priceMin;
    $types .= "d";
}
if ($priceMax !== '') {
    $conditions[] = "price <= ?";
    $params[] = $priceMax;
    $types .= "d";
}
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

switch ($sort) {
    case "low":
        $query .= " ORDER BY price ASC";
        break;
    case "high":
        $query .= " ORDER BY price DESC";
        break;
    case "discount":
        $query .= " ORDER BY discount DESC";
        break;
    default:
        break;
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


$searchQuery = "";
$results = [];

if ($_SERVER["REQUEST_METHOD"] == "GET" && !empty($_GET['q'])) {
    $searchQuery = trim($_GET['q']);
    $likeQuery = "%" . $searchQuery . "%";

    $stmt = $conn->prepare("
        SELECT DISTINCT p.* 
        FROM products p
        LEFT JOIN product_categories pc ON p.id = pc.product_id
        WHERE p.status = 'active' AND p.name LIKE ?
    ");
    $stmt->bind_param("s", $likeQuery);
    $stmt->execute();
    $results = $stmt->get_result();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
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

// Cart count for header (only for logged-in users)
$cart_count = 0;
$user_id = $_SESSION['userid'];
$res = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id");
if ($res && $row = $res->fetch_assoc()) {
    $cart_count = intval($row['total']);
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCart Dashboard</title>
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
        <section class="product-card" style="background:linear-gradient(90deg,#f8fafc 60%,#e3f2fd 100%);margin-bottom:2.5rem;padding:3.5rem 2.5rem 2.5rem 2.5rem;display:flex;align-items:center;gap:3.5rem;box-shadow:0 2px 16px #f3f3f3;position:relative;overflow:hidden;min-height:260px;min-width:0;">
            <div style="z-index:2;">
                <h1 style="font-size:2.5rem;color:var(--primary);font-weight:800;margin-bottom:0.9rem;">Welcome to SmartCart!</h1>
                <p style="font-size:1.22rem;color:var(--gray-text);margin-bottom:0.9rem;">Your one-stop shop for groceries, fresh produce, and daily essentials. Enjoy exclusive offers, fast delivery, and a seamless shopping experience.</p>
                <div style="height:2.2rem;"></div>
                <div style="margin-top:0;">
                    <a href="categories.php" class="dsa-btn" style="font-size:1.18rem;padding:0.85rem 2.6rem;margin-right:1.2rem;">Shop Now</a>
                    <a href="orders.php" class="dsa-btn order-btn-secondary" style="font-size:1.18rem;padding:0.85rem 2.6rem;background:#fff;color:var(--primary);border:1.5px solid var(--primary);">My Orders</a>
                </div>
            </div>
            <img src="https://s3-us-west-2.amazonaws.com/issuewireassets/primg/106838/order-farm-fresh-fruits-and-vegetables-in-dubai.jpg" alt="Background" style="position:absolute;right:0;top:0;height:100%;width:auto;max-width:100%;object-fit:contain;opacity:0.22;z-index:1;pointer-events:none;">
        </section>
        <h2 style="margin-bottom:1.5rem;">Products</h2>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <div class="card product-card">
                    <img src="uploads/<?= htmlspecialchars($product['image'] ?? 'default.jpg') ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <div style="padding:1rem 0;">
                        <h3 style="margin:0 0 0.5rem 0; font-size:1.2rem; color:var(--primary);"> <?= htmlspecialchars($product['name']) ?> </h3>
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
    </main>
    <footer class="footer" style="position:relative;min-height:120px;background:linear-gradient(90deg,#f8fafc 60%,#e3f2fd 100%);box-shadow:0 -2px 16px #f3f3f3;display:flex;align-items:center;justify-content:center;flex-direction:column;padding:2.5rem 1rem 2.5rem 1rem;overflow:hidden;">
        <div style="z-index:2;position:relative;">
            <nav style="margin-bottom:1.5rem;">
                <a href="dashboard.php" style="margin:0 1.2rem;color:var(--primary);font-weight:500;text-decoration:none;">Home</a>
                <a href="categories.php" style="margin:0 1.2rem;color:var(--primary);font-weight:500;text-decoration:none;">Categories</a>
                <a href="userWishlist.php" style="margin:0 1.2rem;color:var(--primary);font-weight:500;text-decoration:none;">Wishlist</a>
                <a href="orders.php" style="margin:0 1.2rem;color:var(--primary);font-weight:500;text-decoration:none;">My Orders</a>
                <a href="cart.php" style="margin:0 1.2rem;color:var(--primary);font-weight:500;text-decoration:none;">Cart</a>
            </nav>
            <div style="color:var(--gray-text);font-size:1.05rem;">&copy; <?php echo date('Y'); ?> SmartCart. All rights reserved.</div>
        </div>
        <img src="https://img.freepik.com/free-vector/hand-drawn-flat-design-grocery-background_23-2149342942.jpg?w=1200" alt="Grocery Footer Background" style="position:absolute;left:0;bottom:0;width:100%;height:100%;object-fit:cover;opacity:0.13;z-index:1;pointer-events:none;">
    </footer>
</body>
</html>