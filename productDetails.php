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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid product ID!";
    exit;
}
$product_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product_result = $stmt->get_result();

if ($product_result->num_rows === 0) {
    echo "Product not found!";
    exit;
}

$product = $product_result->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$image_result = $stmt->get_result();
$category_stmt = $conn->prepare("
    SELECT c.name 
    FROM categories c
    INNER JOIN product_categories pc ON c.id = pc.category_id
    WHERE pc.product_id = ?
");
$category_stmt->bind_param("i", $product_id);
$category_stmt->execute();
$category_result = $category_stmt->get_result();

$categories = [];
while ($row = $category_result->fetch_assoc()) {
    $categories[] = $row['name'];
}

$category_stmt->close();



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
            $_SESSION['cart_message'] = "Failed to add product to cart.";
            $_SESSION['cart_status'] = "failure";
        }
    } else {
        $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        if ($stmt->execute()) {
            $_SESSION['cart_message'] = "Product quantity increased in cart.";
            $_SESSION['cart_status'] = "success";
        } else {
            $_SESSION['cart_message'] = "Failed to update cart.";
            $_SESSION['cart_status'] = "failure";
        }
    }

    $stmt->close();
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}




$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Product Details - <?php echo htmlspecialchars($product['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-image: 
                url('https://www.transparenttextures.com/patterns/food.png'),
                linear-gradient(135deg, #e6fffa, #f0fff4, #fffaf0);
            background-repeat: repeat, no-repeat;
            background-size: auto, cover;
            background-attachment: fixed;
            position: relative;
            overflow-x: hidden;
        }
    </style>
</head>
<body class="bg-gray-50">
<div class="bg-green-600 text-white px-6 py-4 flex justify-between items-center shadow-md">
    <h2 class="text-lg font-semibold">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>

    <form method="GET" action="searchResults.php" class="flex items-center space-x-2">
        <input type="text" name="q" placeholder="Search products..." class="px-4 py-2 rounded-md text-gray-800 focus:outline-none focus:ring-2 focus:ring-green-500 w-64" />
        <input type="submit" value="Search" class="bg-green-700 text-white px-6 py-2 rounded-md hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 cursor-pointer" />
    </form>

    <ul class="flex space-x-6">
        <li><a href="categories.php" class="hover:bg-green-700 px-3 py-1 rounded transition">Categories</a></li>
        <li><a href="userWishlist.php" class="hover:bg-green-700 px-3 py-1 rounded transition">My Wishlist</a></li>
        <li><a href="cart.php" class="hover:bg-green-700 px-3 py-1 rounded transition">My Cart</a></li>
        <li><a href="orders.php" class="hover:bg-green-700 px-3 py-1 rounded transition">My Orders</a></li>
        <li><a href="userUpdate.php" class="hover:bg-green-700 px-3 py-1 rounded transition">My Account</a></li>
        <li><a href="logout.php" class="hover:bg-green-700 px-3 py-1 rounded transition">Logout</a></li>
    </ul>
</div>



<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-md overflow-hidden border border-green-200">
        <div class="md:flex">
            <div class="md:w-1/2 p-6">
                <div class="relative h-96 mb-4">
                    <img id="mainImage" src="uploads/<?= htmlspecialchars($product['image']) ?>" 
                        alt="<?= htmlspecialchars($product['name']) ?>" 
                        style="position: absolute; top: 50%; left: 50%; height: 100%; object-fit: cover; transform: translate(-50%, -50%); border-radius: 0.5rem;" 
                        class="h-full object-cover">
                    
                    <?php if ($product['status'] == 'sale'): ?>
                    <div class="absolute top-4 right-4 bg-green-600 text-white px-3 py-1 rounded-full text-sm font-medium">
                        SALE
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-4 gap-2">
                    <?php if (isset($image_result) && $image_result->num_rows > 0): ?>
                        <?php while ($image = $image_result->fetch_assoc()): ?>
                            <img src="uploads/<?= htmlspecialchars($image['image']) ?>" 
                                onclick="document.getElementById('mainImage').src = this.src"
                                alt="Thumbnail" class="h-20 object-cover rounded cursor-pointer hover:border-2 hover:border-green-500">
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="md:w-1/2 p-6">
                <div class="mb-4">
                    <?php if (!empty($product['category'])): ?>
                        <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">
                            <?= htmlspecialchars($product['category']) ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ((int)$product['stock_count'] > 10): ?>
                        <span class="bg-green-100 text-green-800 text-xs font-medium ml-2 px-2.5 py-0.5 rounded">In Stock</span>
                    <?php elseif ((int)$product['stock_count'] > 0): ?>
                        <span class="bg-yellow-100 text-yellow-800 text-xs font-medium ml-2 px-2.5 py-0.5 rounded">Low Stock</span>
                    <?php else: ?>
                        <span class="bg-red-100 text-red-800 text-xs font-medium ml-2 px-2.5 py-0.5 rounded">Out of Stock</span>
                    <?php endif; ?>
                </div>
                
                <h1 class="text-3xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($product['name']) ?></h1>
                
                <div class="mb-6">
    <?php if ($product['discount'] > 0): ?>
        <?php
            $discount = floatval($product['discount']);
            $originalPrice = floatval($product['price']);
            $discountedPrice = $originalPrice - ($originalPrice * $discount / 100);
        ?>
        <div class="flex items-center">
            <span class="text-3xl font-bold text-green-600">
                ₹<?= number_format($discountedPrice, 2) ?>
            </span>
            <span class="ml-2 text-lg text-gray-500 line-through">
                ₹<?= number_format($originalPrice, 2) ?>
            </span>
            <span class="ml-2 text-sm bg-green-600 text-white px-2 py-1 rounded">
                <?= number_format($discount, 0) ?>% OFF
            </span>
        </div>
    <?php else: ?>
        <span class="text-3xl font-bold text-green-600">
            ₹<?= number_format($product['price'], 2) ?>
        </span>
    <?php endif; ?>
</div>

                
                <form method="POST" action="productDetails.php?id=<?= $product['id'] ?>" class="mb-6">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">

                    <div class="flex items-center space-x-3 mb-4">
                        <label for="quantity" class="font-medium">Quantity:</label>
                        <div class="flex items-center border border-green-300 rounded">
                            <button type="button" onclick="adjustQuantity(-1)" class="w-10 h-10 leading-10 text-gray-600 transition hover:opacity-75">−</button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?= (int)$product['stock_count'] ?>" class="h-10 w-16 border-transparent text-center sm:text-sm" />
                            <button type="button" onclick="adjustQuantity(1)" class="w-10 h-10 leading-10 text-gray-600 transition hover:opacity-75">+</button>
                        </div>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" name="add_to_cart" class="bg-green-600 text-white px-6 py-2 rounded-full hover:bg-green-700 transition">
                            Add to Cart
                        </button>
                        
                        <button type="button" onclick="addToWishlist(<?= $product['id'] ?>)" class="flex items-center justify-center w-12 h-12 text-gray-600 border border-green-300 rounded-lg hover:bg-green-50 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                        </button>
                    </div>
                </form>
                
                <div class="border-t border-green-200 pt-4">
                    <h3 class="font-medium text-gray-900 mb-2">Product Details</h3>
                    <ul class="space-y-2 text-sm">
                        <?php if (!empty($product['sku'])): ?>
                        <li class="flex justify-between">
                            <span class="text-gray-500">SKU:</span>
                            <span class="font-medium"><?= htmlspecialchars($product['sku']) ?></span>
                        </li>
                        <?php endif; ?>
                        <li class="flex justify-between">
                            <span class="text-gray-500">Weight:</span>
                            <span class="font-medium"><?= htmlspecialchars($product['weight'] ?? '0.5') ?> kg</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-gray-500">Dimensions:</span>
                            <span class="font-medium"><?= htmlspecialchars($product['dimensions'] ?? '10 x 10 x 10') ?> cm</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-gray-500">Availability:</span>
                            <span class="font-medium"><?= (int)$product['stock_count'] > 0 ? 'In stock' : 'Out of stock' ?></span>
                        </li>
                        <li class="flex justify-between">
                            <span class="text-gray-500">Category:</span>
                            <span class="font-medium"><?= !empty($categories) ? htmlspecialchars(implode(', ', $categories)) : 'None' ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="bg-green-600 text-white py-8 mt-12">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
                <h3 class="text-lg font-bold mb-4">EcoShop</h3>
                <p class="text-sm">Your one-stop shop for sustainable and eco-friendly products.</p>
            </div>
            <div>
                <h3 class="text-lg font-bold mb-4">Quick Links</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="#" class="hover:text-yellow-300">About Us</a></li>
                    <li><a href="#" class="hover:text-yellow-300">Contact</a></li>
                    <li><a href="#" class="hover:text-yellow-300">FAQs</a></li>
                    <li><a href="#" class="hover:text-yellow-300">Privacy Policy</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-lg font-bold mb-4">Categories</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="#" class="hover:text-yellow-300">Electronics</a></li>
                    <li><a href="#" class="hover:text-yellow-300">Clothing</a></li>
                    <li><a href="#" class="hover:text-yellow-300">Home & Garden</a></li>
                    <li><a href="#" class="hover:text-yellow-300">Books</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-lg font-bold mb-4">Newsletter</h3>
                <p class="text-sm mb-2">Subscribe to get updates on new products and special offers.</p>
                <form class="flex">
                    <input type="email" placeholder="Your email" class="px-4 py-2 w-full rounded-l-lg text-gray-800">
                    <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 px-4 py-2 rounded-r-lg transition duration-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
        <div class="border-t border-green-500 mt-8 pt-6 text-center">
            <p>© 2025 EcoShop. All rights reserved.</p>
        </div>
    </div>
</footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const thumbnails = document.querySelectorAll('.cursor-pointer');
            const mainImage = document.getElementById('mainImage');
            
            thumbnails.forEach(thumb => {
                thumb.addEventListener('click', function() {
                    thumbnails.forEach(t => t.classList.remove('border-2', 'border-green-500'));
                    
                    this.classList.add('border-2', 'border-green-500');
                    
                    mainImage.src = this.src;
                });
            });
            
            const quantityInput = document.getElementById('quantity');
            const minusBtn = quantityInput.previousElementSibling;
            const plusBtn = quantityInput.nextElementSibling;
            
            minusBtn.addEventListener('click', function() {
                const currentValue = parseInt(quantityInput.value);
                if (currentValue > 1) {
                    quantityInput.value = currentValue - 1;
                }
            });
            
            plusBtn.addEventListener('click', function() {
                const currentValue = parseInt(quantityInput.value);
                const maxValue = parseInt(quantityInput.getAttribute('max'));
                if (currentValue < maxValue) {
                    quantityInput.value = currentValue + 1;
                }
            });
        });
    </script>
</body>
</html>