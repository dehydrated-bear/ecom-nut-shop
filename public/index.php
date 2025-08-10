<?php
ob_start();
session_start();

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "nut_shop_ecom";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

require_once __DIR__ . '/../vendor/autoload.php';

// Twig setup
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/views');
$twig = new \Twig\Environment($loader);
$twig->addGlobal('user_name', $_SESSION['user_name'] ?? null);

// ROUTING
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

if ($uri === '' || $uri === '/' || $uri === '/home') {
    $tag = $_GET['tag'] ?? null;
    showAllProducts($conn, $twig, $tag);
}
elseif (preg_match('#^/tag/([\w-]+)$#', $uri, $matches)) {
    $tagName = urldecode($matches[1]);
    showProductsByTag($conn, $twig, $tagName);
}
elseif ($uri === '/signup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handleSignup($conn, $twig);
}
elseif ($uri === '/signup') {
    echo $twig->render('signup.twig');
}
elseif ($uri === '/signin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handleSignin($conn, $twig);
}
elseif ($uri === '/signin') {
    echo $twig->render('login.twig');
}
elseif (preg_match('#^/product/(\d+)$#', $uri, $matches)) {
    $productId = (int)$matches[1];
    showProductById($conn, $twig, $productId);
}
elseif ($uri === '/logout') {
    session_unset();
    session_destroy();
    header("Location: /home");
    exit();
}
elseif ($uri === '/add-to-cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handleAddToCart($conn);
    exit;
}
elseif ($uri === '/cart-items' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    handleCartItems($conn);
    exit;
}
elseif ($uri === '/cart' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    showCartPage($conn, $twig);
}
elseif ($uri === '/checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handleCheckout($conn, $twig);
    exit;
}
elseif ($uri === '/checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handleCheckout($conn, $twig);
    exit;
}
elseif ($uri === '/order_confirmation') {
    $orderId = $_GET['order_id'] ?? null;
    if ($orderId) {
        echo $twig->render('order_confirmation.twig', ['order_id' => $orderId]);
    } else {
        // If there's no order_id, redirect back to the home page
        header('Location: /home');
        exit;
    }
}
else {
    http_response_code(404);
    echo $twig->render('404.twig', ['path' => $uri]);
}
/*
 * HELPERS / HANDLERS
 */

function showProductById($conn, $twig, $id) {
    $stmt = $conn->prepare("SELECT * FROM Product WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if ($product) {
        echo $twig->render('product.twig', ['product' => $product]);
    } else {
        http_response_code(404);
        echo $twig->render('404.twig', ['path' => "/product/$id"]);
    }
}

function showAllProducts($conn, $twig, $tag = null) {
    if ($tag) {
        $tagLike = "%" . $tag . "%";
        $stmt = $conn->prepare("SELECT * FROM Product WHERE tags LIKE ?");
        $stmt->bind_param("s", $tagLike);
    } else {
        $stmt = $conn->prepare("SELECT * FROM Product");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    // tags sample image
    $tags = [];
    $tagQuery = $conn->query("SELECT tags, image FROM Product");
    while ($row = $tagQuery->fetch_assoc()) {
        if (!empty($row['tags'])) {
            $splitTags = array_map('trim', explode(',', $row['tags']));
            foreach ($splitTags as $t) {
                if (!isset($tags[$t])) {
                    $tags[$t] = $row['image'];
                }
            }
        }
    }

    echo $twig->render('index.twig', [
        'products' => $products,
        'tags' => $tags,
        'selected_tag' => $tag
    ]);
}

function showProductsByTag($conn, $twig, $tag) {
    $stmt = $conn->prepare("SELECT * FROM Product WHERE tags = ?");
    $stmt->bind_param("s", $tag);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) $products[] = $row;

    echo $twig->render('tags.twig', [
        'tag' => $tag,
        'products' => $products
    ]);
}

function handleSignup($conn, $twig) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$email || !$password || !$confirm) {
        echo $twig->render('signup.twig', ['error' => 'Email and password are required.']);
        return;
    }
    if ($password !== $confirm) {
        echo $twig->render('signup.twig', ['error' => 'Passwords do not match.']);
        return;
    }
    $stmt = $conn->prepare("SELECT id FROM User WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    if ($existing) {
        echo $twig->render('signup.twig', ['error' => 'Email is already registered.']);
        return;
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO User (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $hashedPassword);
    if ($stmt->execute()) {
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['user_name'] = $name;
        header("Location: /home");
        exit();
    } else {
        echo $twig->render('signup.twig', ['error' => 'Signup failed. Try again.']);
    }
}

function handleSignin($conn, $twig) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) {
        echo $twig->render('login.twig', ['error' => 'Email and password are required.']);
        return;
    }
    $stmt = $conn->prepare("SELECT * FROM User WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header("Location: /home");
        exit;
    } else {
        echo $twig->render('login.twig', ['error' => 'Invalid email or password.']);
    }
}

/* -------------------------
   CART HELPERS & HANDLERS
   ------------------------- */

/**
 * Does Cart table have a checked_out column?
 */
function cartHasCheckedOutColumn($conn) {
    $res = $conn->query("SHOW COLUMNS FROM `Cart` LIKE 'checked_out'");
    if ($res === false) return false;
    return $res->num_rows > 0;
}

/**
 * Add product to cart (AJAX POST expects JSON body)
 * Endpoint: POST /add-to-cart
 * Body: { product_id: number, quantity: number }
 */
function handleAddToCart($conn) {
    // Always respond JSON here
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Login required']);
        return;
    }

    $userId = (int)$_SESSION['user_id'];

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
        return;
    }

    $productId = intval($input['product_id'] ?? 0);
    $quantity  = intval($input['quantity'] ?? 1);
    if ($productId <= 0 || $quantity <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid product or quantity']);
        return;
    }

    // 1) find active cart for the user (prefer checked_out = 0 if column exists)
    if (cartHasCheckedOutColumn($conn)) {
        $stmt = $conn->prepare("SELECT id FROM `Cart` WHERE user_id = ? AND checked_out = 0 LIMIT 1");
    } else {
        $stmt = $conn->prepare("SELECT id FROM `Cart` WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    }
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Cart lookup failed: ' . $stmt->error]);
        return;
    }
    $cartRow = $stmt->get_result()->fetch_assoc();

    // 2) create cart if none
    if (!$cartRow) {
        if (cartHasCheckedOutColumn($conn)) {
            $stmt = $conn->prepare("INSERT INTO `Cart` (user_id, created_at, checked_out) VALUES (?, NOW(), 0)");
        } else {
            $stmt = $conn->prepare("INSERT INTO `Cart` (user_id, created_at) VALUES (?, NOW())");
        }
        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create cart: ' . $stmt->error]);
            return;
        }
        $cartId = $conn->insert_id;
    } else {
        $cartId = (int)$cartRow['id'];
    }

    // 3) ensure Cartitems table exists with expected case — your DB uses Cartitems
    // Try selecting; if fails, return a clear JSON error.
    $check = $conn->query("SHOW TABLES LIKE 'Cartitems'");
    if ($check === false || $check->num_rows === 0) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Table `Cartitems` not found in database. Create table `Cartitems`."]);
        return;
    }

    // 4) check if product already in cart -> update quantity, else insert
    $stmt = $conn->prepare("SELECT id, quantity FROM `Cartitems` WHERE cart_id = ? AND product_id = ? LIMIT 1");
    $stmt->bind_param("ii", $cartId, $productId);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Cartitems select failed: ' . $stmt->error]);
        return;
    }
    $item = $stmt->get_result()->fetch_assoc();

    if ($item) {
        $newQty = (int)$item['quantity'] + $quantity;
        $stmt = $conn->prepare("UPDATE `Cartitems` SET quantity = ? WHERE id = ?");
        $stmt->bind_param("ii", $newQty, $item['id']);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update cart item: ' . $stmt->error]);
            return;
        }
    } else {
        // Optionally record product snapshot (name/price) — adapt to your table schema
        // Basic insert:
        $stmt = $conn->prepare("INSERT INTO `Cartitems` (cart_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $cartId, $productId, $quantity);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to insert cart item: ' . $stmt->error]);
            return;
        }
    }

    echo json_encode(['success' => true, 'cart_id' => $cartId]);
}

/**
 * GET /cart
 * Renders the cart page
 */
function showCartPage($conn, $twig) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /signin');
        exit;
    }

    $userId = $_SESSION['user_id'];

    $sql = "SELECT p.id, p.name, p.price, p.image, ci.quantity
            FROM Cartitems ci
            JOIN Cart c ON ci.cart_id = c.id
            JOIN Product p ON ci.product_id = p.id
            WHERE c.user_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    echo $twig->render('cart.twig', ['items' => $items]);
}

/**
 * GET /cart-items
 * returns { items: [ { product_id, quantity, name, image, price } ... ] }
 */
function handleCartItems($conn) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $userId = $_SESSION['user_id'];

    // Get active cart items for the user
    $sql = "SELECT p.id, p.name, p.price, ci.quantity
            FROM Cartitems ci
            JOIN Cart c ON ci.cart_id = c.id
            JOIN Product p ON ci.product_id = p.id
            WHERE c.user_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // handle prepare error
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $conn->error]);
        return;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    echo json_encode(['success' => true, 'items' => $items]);
}

/**
 * POST /checkout
 * Creates a new order from the user's current cart.
 */
function handleCheckout($conn, $twig) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /signin');
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    // Start a transaction for data integrity
    $conn->begin_transaction();

    try {
        // Get the user's active cart and its total price
        $sqlCart = "SELECT ci.product_id, ci.quantity, p.price
                    FROM Cartitems ci
                    JOIN Cart c ON ci.cart_id = c.id
                    JOIN Product p ON ci.product_id = p.id
                    WHERE c.user_id = ? AND c.checked_out = 0";
        $stmtCart = $conn->prepare($sqlCart);
        if (!$stmtCart) {
            throw new Exception("Failed to prepare cart query: " . $conn->error);
        }
        $stmtCart->bind_param("i", $userId);
        $stmtCart->execute();
        $cartItems = $stmtCart->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($cartItems)) {
            // No items to checkout, redirect to cart page
            $conn->rollback();
            header('Location: /cart');
            exit;
        }

        // Calculate total price
        $totalPrice = 0;
        foreach ($cartItems as $item) {
            $totalPrice += $item['price'] * $item['quantity'];
        }

        // 1. Create a new entry in the Orders table
        $stmtOrder = $conn->prepare("INSERT INTO Orders (user_id, total_price) VALUES (?, ?)");
        if (!$stmtOrder) {
            throw new Exception("Failed to prepare order insert: " . $conn->error);
        }
        $stmtOrder->bind_param("id", $userId, $totalPrice);
        if (!$stmtOrder->execute()) {
            throw new Exception("Failed to insert into Orders: " . $stmtOrder->error);
        }
        $orderId = $conn->insert_id;

        // 2. Insert items into Orderitem table
        $stmtOrderItem = $conn->prepare("INSERT INTO Orderitem (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        if (!$stmtOrderItem) {
            throw new Exception("Failed to prepare order item insert: " . $conn->error);
        }
        foreach ($cartItems as $item) {
            $stmtOrderItem->bind_param("iiid", $orderId, $item['product_id'], $item['quantity'], $item['price']);
            if (!$stmtOrderItem->execute()) {
                throw new Exception("Failed to insert into Orderitem: " . $stmtOrderItem->error);
            }
        }

        // 3. Mark the current cart as checked out
        $stmtUpdateCart = $conn->prepare("UPDATE Cart SET checked_out = 1 WHERE user_id = ? AND checked_out = 0");
        if (!$stmtUpdateCart) {
            throw new Exception("Failed to prepare cart update: " . $conn->error);
        }
        $stmtUpdateCart->bind_param("i", $userId);
        if (!$stmtUpdateCart->execute()) {
            throw new Exception("Failed to update cart: " . $stmtUpdateCart->error);
        }

        // Commit the transaction
        $conn->commit();

        // Redirect to a confirmation page
        header("Location: /order_confirmation?order_id=$orderId");
        exit;

    } catch (Exception $e) {
    $conn->rollback();
    // This will display the actual database error message
    echo $twig->render('error.twig', ['error' => 'Checkout failed: ' . $e->getMessage()]);
}
}