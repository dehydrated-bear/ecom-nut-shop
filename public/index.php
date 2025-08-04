
<?php
ob_start();  
session_start();

$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "nut_shop_ecom";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

require_once __DIR__ . '/../vendor/autoload.php';


$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/views');
$twig = new \Twig\Environment($loader);


$twig->addGlobal('user_name', $_SESSION['user_name'] ?? null);


$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');





if ($uri === '' || $uri === '/' || $uri === '/home') {
    showAllProducts($conn,$twig);
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

// elseif ($uri === '/product') {
//     showProductById($conn, $twig,1);
// }


elseif (preg_match('#^/product/(\d+)$#', $uri, $matches)) {
    $productId = $matches[1];
    showProductById($conn, $twig, $productId);
}
elseif ($uri === '/logout') {
    session_start();
    session_destroy();
    header("Location: /home");
    exit();
}


else {
    http_response_code(404);
    echo $twig->render('404.twig', ['path' => $uri]);
}


function showProductById($conn, $twig, $id) {
    $stmt = $conn->prepare("SELECT * FROM Product WHERE id = ?");
    $stmt->bind_param("i", $id); // i = integer
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

function showAllProducts($conn,$twig){
    $stmt=$conn->prepare("SELECT * FROM Product");
    $stmt->execute();

    $result = $stmt->get_result();
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }


    if ($products) {
        echo $twig->render('index.twig', ['products' => $products]);
    } else {
        http_response_code(404);
        echo $twig->render('404.twig', ['path' => "/home"]);
    }
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

    // Insert user into database
    $stmt = $conn->prepare("INSERT INTO User (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $hashedPassword);

    if ($stmt->execute()) {
        // Optional: Start session and log user in
        session_start();
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['email'] = $email;
        $_SESSION['name'] = $name;

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
        // Redirect to home or dashboard
        header("Location: /home");
        exit;
    } else {
        echo $twig->render('login.twig', ['error' => 'Invalid email or password.']);
    }
}



