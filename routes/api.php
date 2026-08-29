<?php
require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/PasswordResetController.php';
require_once __DIR__ . '/../controllers/CategoryController.php';
require_once __DIR__ . '/../controllers/PromotionController.php';
require_once __DIR__ . '/../controllers/RestockController.php';
require_once __DIR__ . '/../controllers/DeliveryController.php';
require_once __DIR__ . '/../controllers/RewardController.php';
require_once __DIR__ . '/../controllers/RoleController.php';
require_once __DIR__ . '/../controllers/NotificationController.php';
require_once __DIR__ . '/../controllers/TransactionController.php';
require_once __DIR__ . '/../controllers/SalaryController.php';
require_once __DIR__ . '/../controllers/ScheduleController.php';
require_once __DIR__ . '/../controllers/BannerController.php';
require_once __DIR__ . '/../controllers/PaymentSettingController.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';

$request_method = $_SERVER["REQUEST_METHOD"];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('/\/api\/products$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'GET') {
        if(isset($_GET['id'])) $controller->show();
        else $controller->index();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkPermission('products_manage');
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
        AuthMiddleware::checkPermission('products_manage');
        $controller->update();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/products\/delete$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'DELETE') {
        AuthMiddleware::checkPermission('products_manage');
        $controller->delete();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/products\/delete-bulk$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('products_manage');
        $controller->deleteBulk();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/categories$/', $path)) {
    $controller = new CategoryController();
    if ($request_method === 'GET') {
        $controller->index();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkPermission('products_manage');
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/categories\/update$/', $path)) {
    $controller = new CategoryController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('products_manage');
        $controller->update();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/categories\/delete$/', $path)) {
    $controller = new CategoryController();
    if ($request_method === 'DELETE' || $request_method === 'POST') {
        AuthMiddleware::checkPermission('products_manage');
        $controller->delete();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/categories\/delete-bulk$/', $path)) {
    $controller = new CategoryController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('products_manage');
        $controller->deleteBulk();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/products\/update-stock$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('stock_manage');
        $controller->updateStock();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/products\/update-barcode$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('products_manage');
        $controller->updateBarcode();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/products\/lots$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkAnyPermission(['stock_view', 'stock_manage', 'products_manage', 'pos_access']);
        $controller->getLots();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/stock\/history$/', $path)) {
    $controller = new ProductController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('stock_manage');
        $controller->stockHistory();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/orders(\/my)?$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'POST') {
        $controller->createOnlineOrder();
    } elseif ($request_method === 'GET') {
        AuthMiddleware::authenticate();
        $controller->index();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/orders\/pos$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkAnyPermission(['pos_access', 'orders_manage']);
        $controller->createPOSOrder();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/orders\/details$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'GET') {
        AuthMiddleware::authenticate();
        if(isset($_GET['id'])) {
            $controller->show();
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Order ID required"]);
        }
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/orders\/update-status$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->updateStatus();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/orders\/upload-slip$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->uploadSlip();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/orders\/verify-slip$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('orders_manage');
        $controller->verifySlip();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/deliver(y|ies)\/companies$/', $path)) {
    $controller = new DeliveryController();
    if ($request_method === 'GET') {
        $controller->getCompanies();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkPermission('delivery_manage');
        $controller->createCompany();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/deliver(y|ies)\/companies\/update$/', $path)) {
    $controller = new DeliveryController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('delivery_manage');
        $controller->updateCompany();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/deliver(y|ies)\/companies\/delete$/', $path)) {
    $controller = new DeliveryController();
    if ($request_method === 'DELETE' || $request_method === 'POST') {
        AuthMiddleware::checkPermission('delivery_manage');
        $controller->deleteCompany();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/deliveries\/rates$/', $path)) {
    $controller = new DeliveryController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('delivery_view');
        $controller->getRates();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/deliveries\/rates\/save$/', $path)) {
    $controller = new DeliveryController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('delivery_manage');
        $controller->saveRate();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/deliveries\/rates\/delete$/', $path)) {
    $controller = new DeliveryController();
    if ($request_method === 'DELETE' || $request_method === 'POST') {
        AuthMiddleware::checkPermission('delivery_manage');
        $controller->deleteRate();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/customers$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkAnyPermission(['customers_view', 'pos_access', 'orders_view']);
        $controller->index();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/customers\/details$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'GET') {
        AuthMiddleware::authenticate();
        $controller->show();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/customers\/update$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->update();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/pets\/save$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->savePet();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/pets\/delete$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
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
} elseif (preg_match('/\/api\/auth\/forgot-password$/', $path)) {
    $controller = new PasswordResetController();
    if ($request_method === 'POST') {
        $controller->requestReset();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/auth\/reset-password$/', $path)) {
    $controller = new PasswordResetController();
    if ($request_method === 'POST') {
        $controller->resetPassword();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/auth\/me$/', $path)) {
    $controller = new AuthController();
    if ($request_method === 'GET') {
        $controller->me();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/logout$/', $path) || preg_match('/\/api\/auth\/logout$/', $path)) {
    $controller = new AuthController();
    if ($request_method === 'POST' || $request_method === 'GET') {
        $controller->logout();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/roles\/permissions$/', $path)) {
    $controller = new RoleController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->getRolePermissions();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/roles\/permissions\/save$/', $path)) {
    $controller = new RoleController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->saveRolePermissions();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/promotions$/', $path)) {
    $controller = new PromotionController();
    if ($request_method === 'GET') {
        if (isset($_GET['id'])) $controller->show();
        else $controller->index();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkAnyPermission(['promotions_manage', 'promotions_view', 'rewards_manage']);
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/promotions\/update$/', $path)) {
    $controller = new PromotionController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkAnyPermission(['promotions_manage', 'promotions_view', 'rewards_manage']);
        $controller->update();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/promotions\/delete$/', $path)) {
    $controller = new PromotionController();
    if ($request_method === 'DELETE' || $request_method === 'POST') {
        AuthMiddleware::checkAnyPermission(['promotions_manage', 'promotions_view', 'rewards_manage']);
        $controller->delete();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/banners$/', $path)) {
    $controller = new BannerController();
    if ($request_method === 'GET') {
        $controller->index();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkAnyPermission(['promotions_manage', 'promotions_view', 'rewards_manage']);
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/banners\/update$/', $path)) {
    $controller = new BannerController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkAnyPermission(['promotions_manage', 'promotions_view', 'rewards_manage']);
        $controller->update();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/banners\/toggle$/', $path)) {
    $controller = new BannerController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkAnyPermission(['promotions_manage', 'promotions_view', 'rewards_manage']);
        $controller->toggle();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/banners\/delete$/', $path)) {
    $controller = new BannerController();
    if ($request_method === 'DELETE' || $request_method === 'POST') {
        AuthMiddleware::checkAnyPermission(['promotions_manage', 'promotions_view', 'rewards_manage']);
        $controller->delete();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'GET') {
        if (isset($_GET['id'])) {
            // Single profile view logic
            $user = AuthMiddleware::authenticate();
            if (strtolower($user['role'] ?? '') !== 'admin') {
                AuthMiddleware::checkPermission('staff_manage');
            }
            $controller->show();
        } else {
            AuthMiddleware::checkPermission('staff_manage');
            $controller->index();
        }
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff\/me$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'GET') {
        $user = AuthMiddleware::authenticate();
        $req_id = $_GET['user_id'] ?? null;
        if (strtolower($user['role'] ?? '') !== 'admin' && $user['user_id'] != $req_id) {
            AuthMiddleware::checkPermission('staff_manage');
        }
        $controller->me();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff\/create$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff\/update$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'POST') {
        $user = AuthMiddleware::authenticate();
        if (strtolower($user['role'] ?? '') !== 'admin') {
            AuthMiddleware::checkPermission('staff_profile_manage');
            
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT * FROM employees WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user['user_id']]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $json = file_get_contents('php://input');
            $data = json_decode($json, true) ?: [];
            $id = $_GET['id'] ?? $data['id'] ?? null;
            
            if (!$emp || $emp['employee_id'] != $id) {
                Response::json(403, "Forbidden: You can only update your own profile.");
                exit;
            }

            // Lock sensitive staff fields (payment_frequency, position, base_salary) so non-admin employees cannot edit them
            $_POST['_override_data'] = array_merge($data, [
                'payment_frequency' => $emp['payment_frequency'],
                'position'          => $emp['position'],
                'base_salary'       => $emp['base_salary']
            ]);
        }
        $controller->update();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff\/delete$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'DELETE') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->delete();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff\/roles$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'GET') {
        AuthMiddleware::authenticate();
        $controller->roles();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/restock$/', $path)) {
    $controller = new RestockController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('stock_manage');
        $controller->index();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkPermission('stock_manage');
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/restock\/receive$/', $path)) {
    $controller = new RestockController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('stock_manage');
        $controller->receive();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/restock\/details$/', $path)) {
    $controller = new RestockController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('stock_manage');
        $controller->show();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/dashboard\/financials$/', $path)) {
    require_once __DIR__ . '/../controllers/DashboardController.php';
    $controller = new DashboardController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('dashboard_view');
        $controller->getFinancials();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/attendance\/checkin$/', $path)) {
    require_once __DIR__ . '/../controllers/AttendanceController.php';
    $controller = new AttendanceController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->checkIn();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/attendance\/checkout$/', $path)) {
    require_once __DIR__ . '/../controllers/AttendanceController.php';
    $controller = new AttendanceController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->checkOut();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/attendance$/', $path)) {
    require_once __DIR__ . '/../controllers/AttendanceController.php';
    $controller = new AttendanceController();
    if ($request_method === 'GET') {
        $user = AuthMiddleware::authenticate();
        // Employees can check their own logs. If checking someone else's logs, require staff_manage.
        $req_emp_id = $_GET['employee_id'] ?? null;
        if (strtolower($user['role'] ?? '') !== 'admin' && $req_emp_id) {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare("SELECT employee_id FROM employees WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user['user_id']]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$emp || $emp['employee_id'] != $req_emp_id) {
                AuthMiddleware::checkPermission('staff_manage');
            }
        }
        $controller->getAttendance();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/rewards\/settings$/', $path)) {
    $controller = new RewardController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkAnyPermission(['rewards_view', 'rewards_manage', 'pos_access', 'promotions_view']);
        $controller->getSettings();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkPermission('rewards_manage');
        $controller->saveSettings();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/rewards\/gifts$/', $path)) {
    $controller = new RewardController();
    if ($request_method === 'GET') {
        // Staff and Admin can view gifts
        AuthMiddleware::checkAnyPermission(['rewards_view', 'promotions_view', 'pos_access']);
        $controller->getGiftRules();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/rewards\/gifts\/save$/', $path)) {
    $controller = new RewardController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('rewards_manage');
        $controller->saveGiftRule();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/rewards\/gifts\/delete$/', $path)) {
    $controller = new RewardController();
    if ($request_method === 'POST' || $request_method === 'DELETE') {
        AuthMiddleware::checkPermission('rewards_manage');
        $controller->deleteGiftRule();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/payroll\/rights$/', $path)) {
    $controller = new SalaryController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->settings();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->updateRights();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/payroll$/', $path) || preg_match('/\/api\/payroll\/pay$/', $path)) {
    $controller = new SalaryController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->index();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->pay();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/payroll\/update-base-salary$/', $path)) {
    $controller = new SalaryController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->updateBaseSalary();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/payroll\/settings$/', $path)) {
    $controller = new SalaryController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->settings();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->updateSettings();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/payroll\/history$/', $path)) {
    $controller = new SalaryController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->history();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/payroll\/delete$/', $path)) {
    $controller = new SalaryController();
    if ($request_method === 'DELETE' || $request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->delete();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/admin\/attendance\/overview$/', $path)) {
    require_once __DIR__ . '/../controllers/AttendanceController.php';
    $controller = new AttendanceController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->adminOverview();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/admin\/attendance\/details$/', $path) || preg_match('/\/api\/attendance\/employee$/', $path)) {
    require_once __DIR__ . '/../controllers/AttendanceController.php';
    $controller = new AttendanceController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->adminEmployeeDetails();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/admin\/attendance\/verify$/', $path)) {
    require_once __DIR__ . '/../controllers/AttendanceController.php';
    $controller = new AttendanceController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->verify();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/schedule\/month$/', $path)) {
    $controller = new ScheduleController();
    if ($request_method === 'GET') {
        AuthMiddleware::authenticate();
        $controller->getMonthlySchedules();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/schedule\/date$/', $path)) {
    $controller = new ScheduleController();
    if ($request_method === 'GET') {
        AuthMiddleware::authenticate();
        $controller->getDateDetails();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/schedule\/book$/', $path)) {
    $controller = new ScheduleController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->bookSchedule();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/schedule\/approve-month$/', $path)) {
    $controller = new ScheduleController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->approveMonthSchedules();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/schedule\/closed-days$/', $path)) {
    $controller = new ScheduleController();
    if ($request_method === 'GET') {
        AuthMiddleware::authenticate();
        $controller->getClosedDays();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->toggleClosedDay();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/schedule\/approve$/', $path)) {
    $controller = new ScheduleController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->approveBooking();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/schedule\/reject$/', $path)) {
    $controller = new ScheduleController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->rejectBooking();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/schedule\/verify$/', $path)) {
    $controller = new ScheduleController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->verifyAttendance();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/schedule\/assign$/', $path)) {
    $controller = new ScheduleController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('staff_manage');
        $controller->adminAssignSchedule();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/schedule\/delete$/', $path)) {
    $controller = new ScheduleController();
    if ($request_method === 'DELETE' || $request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->deleteSchedule();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/transactions\/sync$/', $path)) {
    $controller = new TransactionController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('dashboard_view');
        $controller->syncSystemTransactions();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/transactions\/delete$/', $path)) {
    $controller = new TransactionController();
    if ($request_method === 'DELETE' || $request_method === 'POST') {
        AuthMiddleware::checkPermission('dashboard_view');
        $controller->delete();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/transactions$/', $path)) {
    $controller = new TransactionController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('dashboard_view');
        $controller->index();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkPermission('dashboard_view');
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/notifications\/alerts$/', $path)) {
    $controller = new NotificationController();
    if ($request_method === 'GET') {
        AuthMiddleware::authenticate();
        $controller->getAlerts();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/notifications\/send-line$/', $path)) {
    $controller = new NotificationController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->sendLineNotification();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/notifications\/test-line$/', $path)) {
    $controller = new NotificationController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->testLineConnection();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/notifications\/line\/test-purchase$/', $path)) {
    $controller = new NotificationController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->testPurchaseAlert();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/notifications\/line\/test-payment$/', $path)) {
    $controller = new NotificationController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->testPaymentAlert();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/notifications\/line\/test-cancel$/', $path)) {
    $controller = new NotificationController();
    if ($request_method === 'POST') {
        AuthMiddleware::authenticate();
        $controller->testCancelAlert();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/payment\/settings$/', $path)) {
    $controller = new PaymentSettingController();
    if ($request_method === 'GET') {
        $controller->getSettings();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkAnyPermission(['dashboard_view', 'orders_manage', 'promotions_manage']);
        $controller->updateSettings();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/line\/webhook$/', $path)) {
    if ($request_method === 'POST') {
        require_once __DIR__ . '/../utils/LineService.php';
        $body = file_get_contents('php://input');
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        $signature = $headers['X-Line-Signature'] ?? ($headers['x-line-signature'] ?? ($_SERVER['HTTP_X_LINE_SIGNATURE'] ?? null));
        $res = LineService::handleWebhook($body, null, $signature);
        if (isset($res['code']) && $res['code'] !== 200) {
            http_response_code($res['code']);
            echo json_encode($res);
        } else {
            http_response_code(200);
            echo json_encode(["status" => "ok"]);
        }
    } else {
        http_response_code(200);
        echo "LINE Webhook Endpoint is ready";
    }
} else {
    http_response_code(404);
    echo json_encode(["message" => "Endpoint not found"]);
}
?>
