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

// --- Ensure session stacks are always initialized ---
if (!isset($_SESSION['cart_undo_stack']) || !is_array($_SESSION['cart_undo_stack'])) $_SESSION['cart_undo_stack'] = [];
if (!isset($_SESSION['cart_redo_stack']) || !is_array($_SESSION['cart_redo_stack'])) $_SESSION['cart_redo_stack'] = [];
if (!isset($_SESSION['save_for_later_stack']) || !is_array($_SESSION['save_for_later_stack'])) $_SESSION['save_for_later_stack'] = [];

// --- Undo/Redo logic (basic, per product) ---
if (isset($_POST['undo_cart_action'])) {
    if (!empty($_SESSION['cart_undo_stack'])) {
        $last = array_pop($_SESSION['cart_undo_stack']);
        $_SESSION['cart_redo_stack'][] = $last;
        $pid = isset($last['product_id']) ? intval($last['product_id']) : 0;
        $prev_qty = isset($last['prev_qty']) ? intval($last['prev_qty']) : 0;
        if ($pid > 0) {
            if ($prev_qty === 0) {
                $conn->query("DELETE FROM cart WHERE user_id=$user_id AND product_id=$pid");
            } else {
                $check = $conn->query("SELECT id FROM products WHERE id=$pid");
                if ($check && $check->num_rows > 0) {
                    $conn->query("INSERT INTO cart (user_id, product_id, quantity) VALUES ($user_id, $pid, $prev_qty) ON DUPLICATE KEY UPDATE quantity = $prev_qty");
                }
            }
        }
    }
    header('Location: cart.php');
    exit;
}
if (isset($_POST['redo_cart_action'])) {
    if (!empty($_SESSION['cart_redo_stack'])) {
        $last = array_pop($_SESSION['cart_redo_stack']);
        $_SESSION['cart_undo_stack'][] = $last;
        $pid = isset($last['product_id']) ? intval($last['product_id']) : 0;
        $new_qty = isset($last['new_qty']) ? intval($last['new_qty']) : 0;
        if ($pid > 0) {
            if ($new_qty === 0) {
                $conn->query("DELETE FROM cart WHERE user_id=$user_id AND product_id=$pid");
            } else {
                $check = $conn->query("SELECT id FROM products WHERE id=$pid");
                if ($check && $check->num_rows > 0) {
                    $conn->query("INSERT INTO cart (user_id, product_id, quantity) VALUES ($user_id, $pid, $new_qty) ON DUPLICATE KEY UPDATE quantity = $new_qty");
                }
            }
        }
    }
    header('Location: cart.php');
    exit;
}
// --- Remove from cart ---
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $pid = intval($_GET['remove']);
    $qres = $conn->query("SELECT quantity FROM cart WHERE user_id=$user_id AND product_id=$pid");
    $prev_qty = 0;
    if ($qres && $row = $qres->fetch_assoc()) $prev_qty = intval($row['quantity']);
    $_SESSION['cart_undo_stack'][] = ['action' => 'remove', 'product_id' => $pid, 'prev_qty' => $prev_qty, 'new_qty' => 0];
    $_SESSION['cart_redo_stack'] = [];
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $pid);
    $stmt->execute();
    header("Location: cart.php");
    exit();
}
// --- Save for Later ---
if (isset($_POST['save_for_later']) && isset($_POST['cart_id'])) {
    $cart_id = intval($_POST['cart_id']);
    $result = $conn->query("SELECT c.*, p.name, p.price, p.image FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = $cart_id AND c.user_id = $user_id");
    if ($row = $result->fetch_assoc()) {
        $save_item = [
            'product_id' => $row['product_id'],
            'name' => $row['name'],
            'price' => $row['price'],
            'image' => $row['image'],
            'quantity' => $row['quantity'],
            'id' => $row['id']
        ];
        array_push($_SESSION['save_for_later_stack'], $save_item);
        $conn->query("DELETE FROM cart WHERE id = $cart_id");
    }
    header('Location: cart.php');
    exit;
}
// --- Move to Cart ---
if (isset($_POST['move_to_cart']) && !empty($_SESSION['save_for_later_stack'])) {
    $item = array_pop($_SESSION['save_for_later_stack']);
    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
    $stmt->bind_param("iiii", $user_id, $item['product_id'], $item['quantity'], $item['quantity']);
    $stmt->execute();
    header('Location: cart.php');
    exit;
}
// --- Add to Wishlist ---
if (isset($_POST['add_to_wishlist']) && isset($_POST['product_id'])) {
    $pid = intval($_POST['product_id']);
    $conn->query("DELETE FROM cart WHERE user_id=$user_id AND product_id=$pid");
    $check = $conn->query("SELECT id FROM products WHERE id=$pid");
    if ($check && $check->num_rows > 0) {
        $exists = $conn->query("SELECT * FROM wishlist WHERE user_id=$user_id AND product_id=$pid");
        if ($exists->num_rows === 0) {
            $conn->query("INSERT INTO wishlist (user_id, product_id) VALUES ($user_id, $pid)");
        }
    }
    header('Location: cart.php');
    exit;
}

// --- Move to Cart from Recently Viewed ---
if (isset($_POST['move_to_cart_recent']) && isset($_POST['product_id'])) {
    $pid = intval($_POST['product_id']);
    // Always add 1 quantity (or update if already in cart)
    $check = $conn->query("SELECT id, quantity FROM cart WHERE user_id=$user_id AND product_id=$pid");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $new_quantity = $row['quantity'] + 1;
        $conn->query("UPDATE cart SET quantity = $new_quantity WHERE id = {$row['id']}");
    } else {
        $conn->query("INSERT INTO cart (user_id, product_id, quantity) VALUES ($user_id, $pid, 1)");
    }
    header('Location: cart.php');
    exit;
}

// --- Change quantity in cart (for both logged-in and guest users) ---
if (isset($_GET['change_qty']) && isset($_GET['product_id'])) {
    $pid = intval($_GET['product_id']);
    $action = $_GET['change_qty'];
    $qres = $conn->query("SELECT quantity FROM cart WHERE user_id=$user_id AND product_id=$pid");
    if ($qres && $row = $qres->fetch_assoc()) {
        $qty = intval($row['quantity']);
        if ($action === 'inc') {
            $qty++;
        } elseif ($action === 'dec' && $qty > 1) {
            $qty--;
        }
        $conn->query("UPDATE cart SET quantity=$qty WHERE user_id=$user_id AND product_id=$pid");
    }
    header('Location: cart.php');
    exit;
}

// Cart count for header (only for logged-in users)
$cart_count = 0;
$res = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id");
if ($res && $row = $res->fetch_assoc()) {
    $cart_count = intval($row['total']);
}

// Fetch cart items for the current user only (no guest cart)
$cart_items = $conn->query("SELECT c.*, p.name, p.price, p.image FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = $user_id");

// Assign save for later stack
$save_for_later = isset($_SESSION['save_for_later_stack']) ? $_SESSION['save_for_later_stack'] : [];

// Build product info map for JS (recently viewed)
$allProducts = $conn->query("SELECT id, name, image FROM products");
$productInfoMap = [];
while($p = $allProducts->fetch_assoc()) {
    $productInfoMap[$p['id']] = [
        'name' => $p['name'],
        'image' => $p['image']
    ];
}

// Ensure session stack is always initialized
if (!isset($_SESSION['save_for_later_stack']) || !is_array($_SESSION['save_for_later_stack'])) {
    $_SESSION['save_for_later_stack'] = [];
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCart | Cart</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: var(--gray-bg); }
        .cart-main-flex { display: flex; flex-direction: column; gap: 2.5rem; max-width: 1200px; margin: 2rem auto; }
        .cart-section { background: var(--white); border-radius: 14px; box-shadow: 0 2px 16px rgba(35,47,62,0.08); padding: 2.5rem 2rem; margin-bottom: 2.5rem; }
        .cart-table, .save-table { width: 100%; border-collapse: separate; border-spacing: 0 1.2rem; }
        .cart-table th, .save-table th { background: var(--gray-bg); color: var(--primary); font-weight: 700; padding: 1rem; border-radius: 10px 10px 0 0; font-size: 1.1rem; }
        .cart-table td, .save-table td { background: var(--white); padding: 1.2rem; border-radius: 10px; box-shadow: 0 1px 4px rgba(35,47,62,0.04); vertical-align: middle; }
        .cart-table img, .save-table img { width: 130px; height: 130px; object-fit: contain; border-radius: 10px; box-shadow: 0 1px 4px rgba(35,47,62,0.04); background: #f8fafc; }
        .cart-table .product-info, .save-table .product-info { display: flex; align-items: center; gap: 24px; }
        .cart-table .product-name, .save-table .product-name { font-weight: 700; font-size: 1.15rem; color: var(--primary); }
        .cart-table .product-id, .save-table .product-id { font-size: 0.95em; color: #888; }
        .cart-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-primary { background: var(--primary); color: var(--white); border: none; border-radius: 6px; padding: 0.5rem 1.1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; font-size: 1rem; }
        .btn-primary:hover { background: var(--accent); color: var(--primary); }
        .undo-redo-row { display: flex; justify-content: center; gap: 1.5rem; margin: 2.5rem 0 1.5rem 0; }
        .undo-redo-row button { min-width: 120px; font-size: 1.1rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(255,153,0,0.10); }
        .undo-redo-row button:disabled { background: #e5e7eb; color: #aaa; cursor: not-allowed; }
        .recently-row { display: flex; gap: 1.5rem; overflow-x: auto; padding: 1.5rem 0; }
        .recently-card { min-width: 180px; background: var(--white); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 2px 12px rgba(35,47,62,0.06); display: flex; flex-direction: column; align-items: center; padding: 1.2rem; transition: box-shadow 0.2s; }
        .recently-card img { width: 90px; height: 90px; object-fit: contain; border-radius: 8px; background: #f8fafc; box-shadow: 0 1px 4px rgba(35,47,62,0.04); }
        .recently-card .recently-name { margin-top: 0.7rem; color: var(--primary); font-weight: 600; text-align: center; font-size: 1.05rem; }
        @media (max-width: 900px) { .cart-main-flex { padding: 0 0.5rem; } .cart-section { padding: 1.2rem 0.5rem; } }
        @media (max-width: 700px) { .cart-table img, .save-table img { width: 60px; height: 60px; } .recently-card { min-width: 120px; padding: 0.7rem; } }
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
            <a href="cart.php" style="position:relative;">
                Cart
                <?php if (
                    isset(
                        $cart_count
                    ) && $cart_count > 0): ?>
                    <span style="position:absolute;top:-8px;right:-12px;background:var(--accent);color:#fff;font-size:0.85em;padding:2px 7px;border-radius:50%;font-weight:700;min-width:22px;text-align:center;line-height:1.5;z-index:2;">
                        <?= $cart_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="orders.php">Orders</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    <main>
        <div class="cart-main-flex">
            <section class="cart-section">
                <h2 style="margin-bottom:2rem;">Your Cart</h2>
                <table class="cart-table">
                    <tr><th>Product</th><th>Price</th><th>Qty</th><th>Action</th></tr>
                    <?php
                    // Support both mysqli_result (logged-in) and array (guest)
                    if ((is_object($cart_items) && $cart_items instanceof mysqli_result && $cart_items->num_rows > 0) || (is_array($cart_items) && count($cart_items) > 0)):
                        if (is_object($cart_items)) {
                            while($item = $cart_items->fetch_assoc()): ?>
                    <tr>
                        <td class="product-info">
                            <img src="uploads/<?= htmlspecialchars($item['image'] ?? '') ?>" alt="<?= htmlspecialchars($item['name'] ?? 'N/A') ?>">
                            <div>
                                <div class="product-name"> <?= htmlspecialchars($item['name'] ?? 'N/A') ?> </div>
                                <div class="product-id">ID: <?= htmlspecialchars($item['product_id'] ?? '') ?></div>
                            </div>
                        </td>
                        <td style="font-weight:700;color:var(--accent);">₹<?= htmlspecialchars($item['price'] ?? '0') ?></td>
                        <td>
                            <form method="GET" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?? '' ?>">
                                <button name="change_qty" value="dec" class="btn-primary" style="padding:0 8px;">-</button>
                            </form>
                            <?= htmlspecialchars($item['quantity'] ?? '1') ?>
                            <form method="GET" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?? '' ?>">
                                <button name="change_qty" value="inc" class="btn-primary" style="padding:0 8px;">+</button>
                            </form>
                        </td>
                        <td class="cart-actions">
                            <form method="GET" style="display:inline;">
                                <input type="hidden" name="remove" value="<?= $item['product_id'] ?? '' ?>">
                                <button class="btn-primary" type="submit">Remove</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="cart_id" value="<?= $item['id'] ?? '' ?>">
                                <button class="btn-primary" name="save_for_later" type="submit">Save for Later</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="add_to_wishlist" value="1">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?? '' ?>">
                                <button class="btn-primary" type="submit">Wishlist</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; }
                        else { foreach($cart_items as $item): ?>
                    <tr>
                        <td class="product-info">
                            <img src="uploads/<?= htmlspecialchars($item['image'] ?? '') ?>" alt="<?= htmlspecialchars($item['name'] ?? 'N/A') ?>">
                            <div>
                                <div class="product-name"> <?= htmlspecialchars($item['name'] ?? 'N/A') ?> </div>
                                <div class="product-id">ID: <?= htmlspecialchars($item['product_id'] ?? '') ?></div>
                            </div>
                        </td>
                        <td style="font-weight:700;color:var(--accent);">₹<?= htmlspecialchars($item['price'] ?? '0') ?></td>
                        <td>
                            <form method="GET" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?? '' ?>">
                                <button name="change_qty" value="dec" class="btn-primary" style="padding:0 8px;">-</button>
                            </form>
                            <?= htmlspecialchars($item['quantity'] ?? '1') ?>
                            <form method="GET" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?? '' ?>">
                                <button name="change_qty" value="inc" class="btn-primary" style="padding:0 8px;">+</button>
                            </form>
                        </td>
                        <td class="cart-actions">
                            <form method="GET" style="display:inline;">
                                <input type="hidden" name="remove" value="<?= $item['product_id'] ?? '' ?>">
                                <button class="btn-primary" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; }
                    else: ?>
                    <tr><td colspan="4" style="text-align:center;color:#888;">Your cart is empty.</td></tr>
                    <?php endif; ?>
                </table>
                <!-- Undo/Redo button just above Save for Later -->
                <div style="margin:2.5rem 0 1.5rem 0;text-align:center;">
                    <form method="POST" style="display:inline;">
                        <button class="btn-primary" name="undo_cart_action" type="submit" <?= empty($_SESSION['cart_undo_stack']) ? 'disabled' : '' ?>>Undo</button>
                    </form>
                    <form method="POST" style="display:inline; margin-left:1rem;">
                        <button class="btn-primary" name="redo_cart_action" type="submit" <?= empty($_SESSION['cart_redo_stack']) ? 'disabled' : '' ?>>Redo</button>
                    </form>
                </div>
                <div style="margin:2rem 0 2rem 0;text-align:center;">
                    <form action="placeOrder.php" method="get">
                        <button type="submit" class="dsa-btn" style="padding:0.8rem 2.5rem;font-size:1.1rem;">Place Order</button>
                    </form>
                </div>
            </section>
            <section class="cart-section">
                <h2>Save for Later</h2>
                <table class="save-table">
                    <tr><th>Product</th><th>Price</th><th>Qty</th><th>Action</th></tr>
                    <?php if (!empty($save_for_later)): ?>
                    <?php foreach($save_for_later as $item): ?>
                    <tr>
                        <td class="product-info">
                            <img src="uploads/<?= htmlspecialchars($item['image'] ?? '') ?>" alt="<?= htmlspecialchars($item['name'] ?? 'N/A') ?>">
                            <div>
                                <div class="product-name"> <?= htmlspecialchars($item['name'] ?? 'N/A') ?> </div>
                                <div class="product-id">ID: <?= htmlspecialchars($item['product_id'] ?? '') ?></div>
                            </div>
                        </td>
                        <td style="font-weight:700;color:var(--accent);">₹<?= htmlspecialchars($item['price'] ?? '0') ?></td>
                        <td><?= htmlspecialchars($item['quantity'] ?? '1') ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <button class="btn-primary" name="move_to_cart" type="submit">Move to Cart</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;color:#888;">No items saved for later.</td></tr>
                    <?php endif; ?>
                </table>
            </section>
            <!-- Recently Viewed below Save for Later, horizontal scroll -->
            <section class="cart-section">
                <h2>Recently Viewed</h2>
                <div id="recentlyViewedRow" class="recently-row"></div>
            </section>
        </div>
    </main>
    <footer class="footer">&copy; <?php echo date('Y'); ?> SmartCart. All rights reserved.</footer>
    <script>
    // Product info map for recently viewed
    const productInfoMap = <?php echo json_encode($productInfoMap); ?>;
    let recentlyViewed = JSON.parse(localStorage.getItem('recentlyViewed')) || [];
    recentlyViewed = recentlyViewed.filter(id => productInfoMap[id]); // Filter out invalid IDs
    const recentlyViewedRow = document.getElementById('recentlyViewedRow');
    recentlyViewed.forEach(id => {
        const product = productInfoMap[id];
        const card = document.createElement('div');
        card.className = 'recently-card';
        card.innerHTML = `
            <img src="uploads/${product.image}" alt="${product.name}">
            <div class="recently-name">${product.name}</div>
            <form method="POST" action="cart.php" style="margin-top:0.7rem;width:100%;text-align:center;">
                <input type="hidden" name="move_to_cart_recent" value="1">
                <input type="hidden" name="product_id" value="${id}">
                <button type="submit" class="btn-primary" style="width:90%;margin:0 auto;">Move to Cart</button>
            </form>
        `;
        card.addEventListener('click', (e) => {
            if (e.target.tagName === 'BUTTON') return;
            window.location.href = `product.php?id=${id}`;
        });
        recentlyViewedRow.appendChild(card);
    });
    </script>
</body>
</html>