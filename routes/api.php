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
} elseif (preg_match('/\/api\/categories\/delete$/', $path)) {
    $controller = new CategoryController();
    if ($request_method === 'DELETE') {
        AuthMiddleware::checkPermission('products_manage');
        $controller->delete();
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
} elseif (preg_match('/\/api\/orders$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'POST') {
        // Online customer orders are public
        $controller->createOnlineOrder();
    } elseif ($request_method === 'GET') {
        AuthMiddleware::checkPermission('orders_manage');
        $controller->index();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/orders\/pos$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('pos_access');
        $controller->createPOSOrder();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/orders\/details$/', $path)) {
    $controller = new OrderController();
    if ($request_method === 'GET') {
        if(isset($_GET['id'])) {
            // Note: Customers might want to view their own, but since these are admin/staff routes,
            // we enforce orders_manage. Online customers check orders via customer-specific endpoints if any.
            AuthMiddleware::checkPermission('orders_manage');
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
        // Can be updated by order manager or delivery manager
        $user = AuthMiddleware::authenticate();
        if (strtolower($user['role'] ?? '') !== 'admin') {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("SELECT permissions FROM users WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user['user_id']]);
            $uData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt2 = $db->prepare("SELECT r.permissions FROM roles r JOIN users u ON u.role_id = r.role_id WHERE u.user_id = :user_id");
            $stmt2->execute([':user_id' => $user['user_id']]);
            $rData = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            $uPerms = json_decode($uData['permissions'] ?? '[]', true) ?: [];
            $rPerms = json_decode($rData['permissions'] ?? '[]', true) ?: [];
            $allPerms = array_merge($uPerms, $rPerms);
            
            if (!in_array('orders_manage', $allPerms) && !in_array('delivery_manage', $allPerms)) {
                Response::json(403, "Forbidden: Lacking orders_manage or delivery_manage permission.");
                exit;
            }
        }
        $controller->updateStatus();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/deliveries\/companies$/', $path)) {
    $controller = new DeliveryController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('delivery_view');
        $controller->getCompanies();
    } elseif ($request_method === 'POST') {
        AuthMiddleware::checkPermission('delivery_manage');
        $controller->createCompany();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/deliveries\/companies\/update$/', $path)) {
    $controller = new DeliveryController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('delivery_manage');
        $controller->updateCompany();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/deliveries\/companies\/delete$/', $path)) {
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
        AuthMiddleware::checkPermission('customers_view');
        $controller->index();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/customers\/details$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'GET') {
        AuthMiddleware::checkPermission('customers_view');
        $controller->show();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/customers\/update$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'POST') {
        $controller->update();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/pets\/save$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('pets_manage');
        $controller->savePet();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/pets\/delete$/', $path)) {
    require_once __DIR__ . '/../controllers/CustomerController.php';
    $controller = new CustomerController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('pets_manage');
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
        AuthMiddleware::checkPermission('promotions_manage');
        $controller->create();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/promotions\/update$/', $path)) {
    $controller = new PromotionController();
    if ($request_method === 'POST') {
        AuthMiddleware::checkPermission('promotions_manage');
        $controller->update();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/promotions\/delete$/', $path)) {
    $controller = new PromotionController();
    if ($request_method === 'DELETE') {
        AuthMiddleware::checkPermission('promotions_manage');
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
} elseif (preg_match('/\/api\/payroll$/', $path)) {
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
} elseif (preg_match('/\/api\/admin\/attendance\/details$/', $path)) {
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
} elseif (preg_match('/\/api\/staff\/me$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'GET') {
        AuthMiddleware::authenticate();
        $controller->me();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff\/details$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'GET') {
        AuthMiddleware::authenticate();
        $controller->show();
    } else {
        http_response_code(405);
    }
} elseif (preg_match('/\/api\/staff$/', $path)) {
    require_once __DIR__ . '/../controllers/StaffController.php';
    $controller = new StaffController();
    if ($request_method === 'GET') {
        AuthMiddleware::authenticate();
        $controller->index();
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
} else {
    http_response_code(404);
    echo json_encode(["message" => "Endpoint not found"]);
}
?>
