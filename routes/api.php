<?php
require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$request_method = $_SERVER["REQUEST_METHOD"];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('/\/api\/products$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'GET') {
        $controller->index();
    } elseif ($request_method === 'POST') {
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/products\/update-stock$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'POST') {
        $controller->updateStock();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/orders$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'POST') {
        $controller->createOnlineOrder();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/login$/', $path)) {
    $controller = new AuthController();
    if ($request_method === 'POST') {
        $controller->login();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/register$/', $path)) {
    $controller = new AuthController();
    if ($request_method === 'POST') {
        $controller->register();
    } else {
        http_response_code(405);
    }
} else {
    http_response_code(404);
    echo json_encode(["message" => "Endpoint not found"]);
}
?>
