<?php
ob_start();
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
        '/' => 'products.html',
        '/home' => 'products.html',
        '/login' => 'login.html',
        '/register' => 'register.html',
        '/forgot-password' => 'forgot_password.html',
        '/reset-password' => 'reset_password.html',
        '/products' => 'products.html',
        '/profile' => 'profile.html',
        '/cart' => 'cart.html',
        '/checkout' => 'checkout.html',
        '/order-history' => 'order-history.html',
        '/my-pets' => 'my-pets.html',
        '/contact' => 'contact.html',
        '/admin/stock' => 'admin_stock.html',
        '/admin/products' => 'admin_product_management.html',
        '/admin/products/edit' => 'admin_product_edit.html',
        '/admin/promotions' => 'admin_promotions.html',
        '/admin/delivery' => 'admin_delivery.html',
        '/staff/profile' => 'staff_profile.html'
    ];

    $path = rtrim($request_uri, '/');
    if ($path === '') $path = '/';

    function serveHtmlWithSecurity($filePath) {
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo json_encode(["message" => "File not found"]);
            exit;
        }
        $html = file_get_contents($filePath);
        
        $csrf_script = '
        <!-- Global Security & CSRF Interceptor -->
        <script>
            (function() {
                if (window.__csrfInterceptorInstalled) return;
                window.__csrfInterceptorInstalled = true;
                const originalFetch = window.fetch;
                window.fetch = async function(url, options) {
                    options = options || {};
                    const method = (options.method || "GET").toUpperCase();
                    
                    if (["POST", "PUT", "DELETE"].includes(method)) {
                        options.headers = options.headers || {};
                        const token = localStorage.getItem("csrf_token");
                        if (token) {
                            if (options.headers instanceof Headers) {
                                options.headers.set("X-CSRF-Token", token);
                            } else if (Array.isArray(options.headers)) {
                                const exists = options.headers.some(h => h[0].toLowerCase() === "x-csrf-token");
                                if (!exists) options.headers.push(["X-CSRF-Token", token]);
                            } else {
                                options.headers["X-CSRF-Token"] = token;
                            }
                        }
                    }
                    
                    try {
                        const response = await originalFetch(url, options);
                        
                        if (response.ok && (url.includes("/api/login") || url.includes("/api/auth/me"))) {
                            const clone = response.clone();
                            clone.json().then(result => {
                                if (result && result.data && result.data.csrf_token) {
                                    localStorage.setItem("csrf_token", result.data.csrf_token);
                                }
                            }).catch(err => console.error("Error parsing auth token:", err));
                        }
                        
                        if (response.status === 401 && !url.includes("/api/login")) {
                            localStorage.removeItem("user");
                            localStorage.removeItem("csrf_token");
                            window.location.href = "/login.html";
                        }
                        return response;
                    } catch (err) {
                        throw err;
                    }
                };
            })();
        </script>
        ';
        
        if (strpos($html, '</head>') !== false) {
            $html = str_replace('</head>', $csrf_script . '</head>', $html);
        } else {
            $html = $csrf_script . $html;
        }
        echo $html;
    }

    if (isset($routes[$path])) {
        serveHtmlWithSecurity(__DIR__ . '/' . $routes[$path]);
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
            // Fallback for nested relative HTML links from deep URLs
            $basename = basename($request_uri);
            $root_file_path = __DIR__ . '/' . $basename;
            if (preg_match('/\.html$/', $basename) && file_exists($root_file_path)) {
                serveHtmlWithSecurity($root_file_path);
                exit;
            }

            http_response_code(404);
            echo json_encode(["message" => "Route not found: " . $request_uri]);
        }
    }
}
?>
