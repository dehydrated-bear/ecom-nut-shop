
<?php

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


$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');





if ($uri === '' || $uri === '/' || $uri === '/home') {
    showAllProducts($conn,$twig);
}

elseif ($uri === '/signup') {
    echo $twig->render('signup.twig');
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
