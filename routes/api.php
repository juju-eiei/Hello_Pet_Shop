<?php
require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/CategoryController.php';
require_once __DIR__ . '/../controllers/PromotionController.php';

$request_method = $_SERVER["REQUEST_METHOD"];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('/\/api\/products$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'GET') {
        if(isset($_GET['id'])) $controller->show();
        else $controller->index();
    } elseif ($request_method === 'POST') {
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/products\/top$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'GET') {
        $controller->top();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/products\/update$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'POST') {
        $controller->update();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/products\/delete$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'DELETE') {
        $controller->delete();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/categories$/', $path)) {
    $controller = new CategoryController();
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
    } elseif ($request_method === 'GET') {
        $controller->index();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/orders\/details$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'GET') {
        if(isset($_GET['id'])) $controller->show();
        else { http_response_code(400); echo json_encode(["message" => "Order ID required"]); }
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/orders\/update-status$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'POST') {
        $controller->updateStatus();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/deliveries\/companies$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'GET') {
        $controller->getDeliveryCompanies();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/customers$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'GET') {
        $controller->index();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/customers\/details$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'GET') {
        $controller->show();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/pets\/save$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'POST') {
        $controller->savePet();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/pets\/delete$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'POST') {
        $controller->deletePet();
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
} elseif (preg_match('/\/api\/promotions$/', $path)) {
    $controller = new PromotionController();
    if ($request_method === 'GET') {
        if (isset($_GET['id'])) $controller->show();
        else $controller->index();
    } elseif ($request_method === 'POST') {
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/promotions\/update$/', $path)) {
    $controller = new PromotionController();
    if ($request_method === 'POST') {
        $controller->update();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/promotions\/delete$/', $path)) {
    $controller = new PromotionController();
    if ($request_method === 'DELETE') {
        $controller->delete();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'GET') {
        if (isset($_GET['id'])) $controller->show();
        else $controller->index();
    } elseif ($request_method === 'POST') {
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff\/me$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'GET') {
        $controller->me();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff\/create$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'POST') {
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff\/update$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'POST') {
        $controller->update();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff\/delete$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'DELETE') {
        $controller->delete();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff\/roles$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'GET') {
        $controller->roles();
    } else {
        http_response_code(405);
    }
} else {
    http_response_code(404);
    echo json_encode(["message" => "Endpoint not found"]);
}
?>
