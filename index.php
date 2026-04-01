<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strpos($request_uri, '/api/') !== false) {
    require_once __DIR__ . '/routes/api.php';
} else {
    // Map clean URLs to HTML files
    $routes = [
        '/' => 'login.html', // Default to login for now
        '/login' => 'login.html',
        '/register' => 'register.html',
        '/products' => 'products.html',
        '/admin/stock' => 'admin_stock.html',
        '/admin/products' => 'admin_product_management.html',
        '/admin/products/edit' => 'admin_product_edit.html',
        '/admin/promotions' => 'admin_promotions.html'
    ];

    $path = rtrim($request_uri, '/');
    if ($path === '') $path = '/';

    if (isset($routes[$path])) {
        readfile(__DIR__ . '/' . $routes[$path]);
    } else {
        // Fallback for real static files (images, build assets)
        $file_path = __DIR__ . $request_uri;
        if (file_exists($file_path) && is_file($file_path)) {
            // Setting correct content type for common files
            $ext = pathinfo($file_path, PATHINFO_EXTENSION);
            $mime_types = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'svg' => 'image/svg+xml'
            ];
            if (isset($mime_types[$ext])) {
                header("Content-Type: " . $mime_types[$ext]);
            }
            readfile($file_path);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Route not found: " . $request_uri]);
        }
    }
}
?>
