<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smartcart";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$searchQuery = "";
$results = [];

if ($_SERVER["REQUEST_METHOD"] == "GET" && !empty($_GET['q'])) {
    $searchQuery = htmlspecialchars(trim($_GET['q']));
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
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results | SmartCart</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <span class="logo">SmartCart</span>
        <nav>
            <a href="dashboard.php">Home</a>
            <a href="categories.php">Categories</a>
            <a href="userWishlist.php">Wishlist</a>
            <a href="cart.php">Cart</a>
            <a href="orders.php">Orders</a>
            <a href="userUpdate.php">Account</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    <main style="max-width:1100px;margin:2rem auto;">
        <form method="GET" action="searchResults.php" style="margin-bottom:2rem;display:flex;max-width:420px;">
            <input type="text" name="q" placeholder="Search products..." value="<?= htmlspecialchars($searchQuery) ?>" style="flex:1;padding:0.6rem 1rem;border-radius:4px 0 0 4px;border:1px solid var(--border);font-size:1rem;outline:none;">
            <button type="submit" class="dsa-btn" style="border-radius:0 4px 4px 0;">Search</button>
        </form>
        <?php if (!empty($searchQuery)): ?>
            <?php if ($results && $results->num_rows > 0): ?>
                <h2 style="color:var(--primary);margin-bottom:1rem;">Search Results for "<?= htmlspecialchars($searchQuery) ?>"</h2>
                <div class="product-grid">
                    <?php while ($row = $results->fetch_assoc()): ?>
                        <div class="product-card">
                            <img src="uploads/<?= htmlspecialchars($row['image'] ?? 'default.jpg') ?>" alt="<?= htmlspecialchars($row['name']) ?>">
                            <div style="padding:1rem 0;">
                                <h4 style="margin:0 0 0.5rem 0; font-size:1.1rem; color:var(--primary);"> <?= htmlspecialchars($row['name']) ?> </h4>
                                <div style="margin-bottom:0.5rem; color:var(--gray-text);">Brand: <?= htmlspecialchars($row['brand'] ?? '') ?></div>
                                <div style="font-weight:bold; color:var(--accent); font-size:1.1rem;">
                                    ₹<?= htmlspecialchars($row['price']) ?>
                                </div>
                            </div>
                            <form method="POST" style="margin:0;display:inline-block;">
                                <input type="hidden" name="product_id" value="<?= $row['id'] ?>">
                                <button type="submit" name="add_to_cart" class="dsa-btn">Add to Cart</button>
                            </form>
                            <form method="POST" style="margin:0;display:inline-block;">
                                <input type="hidden" name="product_id" value="<?= $row['id'] ?>">
                                <button type="submit" name="add_to_wishlist" class="dsa-btn" style="background:var(--accent);color:var(--white);">Wishlist</button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="product-card" style="text-align:center;">
                    <h3 style="color:var(--primary);margin-bottom:0.5rem;">No results found for "<?= htmlspecialchars($searchQuery) ?>"</h3>
                    <p style="color:var(--gray-text);">Try different keywords or browse our categories</p>
                    <a href="categories.php" class="dsa-btn" style="margin-top:1rem;">Browse Products</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="product-card" style="text-align:center;">
                <h3 style="color:var(--primary);margin-bottom:0.5rem;">Enter a search term</h3>
                <p style="color:var(--gray-text);">Search for products using the search bar above</p>
            </div>
        <?php endif; ?>
        <div style="margin-top:2rem;text-align:center;">
            <a href="dashboard.php" class="dsa-btn">Back to Dashboard</a>
        </div>
    </main>
    <footer class="footer">&copy; <?php echo date('Y'); ?> SmartCart. All rights reserved.</footer>
</body>
</html>
<?php if (isset($stmt)) $stmt->close(); $conn->close(); ?>
