<?php
ob_start();
require_once __DIR__ . '/config/config.php';

// Dynamic CORS configuration
$allowedOrigins = defined('CORS_ALLOWED_ORIGINS') && CORS_ALLOWED_ORIGINS !== ''
    ? array_map('trim', explode(',', CORS_ALLOWED_ORIGINS))
    : ['http://localhost', 'http://127.0.0.1', 'http://hello_pet_shop.test'];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    if (in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
    } else {
        $originHost = parse_url($origin, PHP_URL_HOST);
        if ($originHost === 'localhost' || $originHost === '127.0.0.1' || strpos($originHost, 'hello_pet_shop') !== false) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Credentials: true");
        }
    }
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize script base directory if running in a subdirectory (e.g. /Hello_Pet_Shop/ or Laragon)
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
if ($scriptDir !== '/' && $scriptDir !== '\\' && $scriptDir !== '.') {
    $scriptDir = str_replace('\\', '/', $scriptDir);
    if (strpos($request_uri, $scriptDir) === 0) {
        $request_uri = substr($request_uri, strlen($scriptDir));
    }
}
if (empty($request_uri)) {
    $request_uri = '/';
}

// Block direct access to hidden files and sensitive extensions
$basename = basename($request_uri);
if (strpos($basename, '.') === 0 || preg_match('/\.(env|sql|log|ini|sh|bak|md|json|lock)$/i', $basename)) {
    http_response_code(403);
    echo json_encode(["message" => "Access denied"]);
    exit;
}

// Block direct access to sensitive PHP scripts in web root
if (preg_match('/\.php$/i', $basename) && $basename !== 'index.php') {
    http_response_code(403);
    echo json_encode(["message" => "Direct PHP script execution is forbidden"]);
    exit;
}

if (strpos($request_uri, '/api/') !== false) {
    require_once __DIR__ . '/routes/api.php';
} else {
    // Map clean URLs to HTML files
    $routes = [
        '/' => 'products.html',
        '/home' => 'products.html',
        '/products' => 'products.html',
        '/login' => 'login.html',
        '/register' => 'register.html',
        '/forgot-password' => 'forgot_password.html',
        '/reset-password' => 'reset_password.html',
        '/cart' => 'cart.html',
        '/checkout' => 'checkout.html',
        '/orders' => 'order-history.html',
        '/order-history' => 'order-history.html',
        '/my-pets' => 'my-pets.html',
        '/profile' => 'profile.html',
        '/contact' => 'contact.html',
        '/pos' => 'pos.html',

        // Admin Clean Routes
        '/admin' => 'admin_orders.html',
        '/admin/dashboard' => 'admin_dashboard.html',
        '/admin/products' => 'admin_product_management.html',
        '/admin/products/edit' => 'admin_product_edit.html',
        '/admin/stock' => 'admin_stock.html',
        '/admin/categories' => 'admin_categories.html',
        '/admin/promotions' => 'admin_promotions.html',
        '/admin/orders' => 'admin_orders.html',
        '/admin/orders/details' => 'admin_order_details.html',
        '/admin/customers' => 'admin_customers.html',
        '/admin/customers/details' => 'admin_customer_details.html',
        '/admin/delivery' => 'admin_delivery.html',
        '/admin/rewards' => 'admin_reward_management.html',
        '/admin/staff' => 'admin_staff.html',
        '/admin/schedule' => 'admin_schedule.html',
        '/admin/attendance' => 'admin_attendance.html',
        '/admin/payroll' => 'admin_payroll.html',
        '/admin/payroll/settings' => 'admin_pay_settings.html',
        '/admin/transactions' => 'admin_transactions.html',

        // Staff Clean Routes
        '/staff' => 'staff_profile.html',
        '/staff/profile' => 'staff_profile.html',
        '/staff/stock' => 'staff_stock.html',
        '/staff/orders' => 'staff_orders.html',
        '/staff/orders/details' => 'staff_order_details.html',
        '/staff/customers' => 'staff_customers.html',
        '/staff/customers/details' => 'staff_customer_details.html',
        '/staff/promotions' => 'staff_promotions.html',
        '/staff/schedule' => 'staff_schedule.html'
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
            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            $mime_types = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                'gif' => 'image/gif',
                'ico' => 'image/x-icon',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf'
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
