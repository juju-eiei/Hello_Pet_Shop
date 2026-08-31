<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/RateLimiter.php';

class AuthController {
    private $db;
    private $userModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->userModel = new User($this->db);
    }

    public function login() {
        $ip = RateLimiter::getClientIp();
        $rateCheck = RateLimiter::check('login:' . $ip, 5, 900);
        if (!$rateCheck['allowed']) {
            $minutes = ceil($rateCheck['retry_after'] / 60);
            Response::json(429, "คุณพยายามเข้าสู่ระบบผิดพลาดหลายครั้งเกินไป กรุณารออีก {$minutes} นาทีแล้วลองใหม่อีกครั้ง");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['username']) || empty($data['password'])) {
            Response::json(400, "กรุณากรอก Username และ Password");
            return;
        }

        $user = $this->userModel->findByUsername($data['username']);

        if ($user && password_verify($data['password'], $user['password'])) {
            // Clear rate limits on successful login
            RateLimiter::clear('login:' . $ip);

            // Remove password from response
            unset($user['password']);
            
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_regenerate_id(true); // Regenerate session ID to prevent Session Fixation
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role_name'];

            // Generate CSRF Token on successful login
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            $user['csrf_token'] = $_SESSION['csrf_token'];

            if (strtolower($user['role_name']) === 'customer') {
                $stmtCust = $this->db->prepare("SELECT customer_id, first_name, last_name, 
                             COALESCE(NULLIF(phone, ''), (SELECT a.phone FROM addresses a WHERE a.customer_id = customers.customer_id AND a.phone IS NOT NULL AND a.phone != '' ORDER BY a.is_default DESC, a.address_id DESC LIMIT 1)) as phone
                             FROM customers WHERE user_id = ?");
                $stmtCust->execute([$user['user_id']]);
                $cust = $stmtCust->fetch(PDO::FETCH_ASSOC);
                if ($cust) {
                    $user['customer_id'] = $cust['customer_id'];
                    $user['first_name'] = $cust['first_name'];
                    $user['last_name'] = $cust['last_name'];
                    $user['phone'] = $cust['phone'] ?: '';
                }
            } elseif (in_array(strtolower($user['role_name']), ['admin', 'employee'])) {
                $stmtEmp = $this->db->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
                $stmtEmp->execute([$user['user_id']]);
                $emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);
                if ($emp) {
                    $user['employee_id'] = $emp['employee_id'];
                }
            }

            Response::json(200, "เข้าสู่ระบบสำเร็จ", $user);
        } else {
            RateLimiter::hit('login:' . $ip, 900);
            Response::json(401, "Username หรือ Password ไม่ถูกต้อง");
        }
    }

    public function register() {
        $ip = RateLimiter::getClientIp();
        $rateCheck = RateLimiter::check('register:' . $ip, 5, 600);
        if (!$rateCheck['allowed']) {
            $minutes = ceil($rateCheck['retry_after'] / 60);
            Response::json(429, "คุณทำรายการสมัครสมาชิกบ่อยเกินไป กรุณารออีก {$minutes} นาทีแล้วลองใหม่อีกครั้ง");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['full_name']) || empty($data['email']) || empty($data['password']) || empty($data['username'])) {
            Response::json(400, "กรุณากรอกข้อมูลให้ครบถ้วน");
            return;
        }

        // Check if username already exists
        if ($this->userModel->findByUsername($data['username'])) {
            Response::json(400, "Username นี้ถูกใช้งานแล้ว");
            return;
        }

        // Check if email already exists
        if ($this->userModel->findByEmail($data['email'])) {
            Response::json(400, "Email นี้ถูกใช้งานแล้ว");
            return;
        }

        $userId = $this->userModel->create($data);

        if ($userId) {
            RateLimiter::hit('register:' . $ip, 600);
            Response::json(201, "สมัครสมาชิกสำเร็จ", ["user_id" => $userId]);
        } else {
            Response::json(500, "เกิดข้อผิดพลาดในการสมัครสมาชิก");
        }
    }

    public function me() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["message" => "Unauthorized: Please log in first"]);
            return;
        }

        $query = "SELECT u.user_id, u.username, u.email, u.permissions AS user_permissions, r.role_name, r.permissions AS role_permissions
                  FROM users u
                  JOIN roles r ON u.role_id = r.role_id
                  WHERE u.user_id = :user_id
                  LIMIT 0,1";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(["message" => "User not found"]);
            return;
        }

        // Generate CSRF Token if not exists
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $userPerms = json_decode($user['user_permissions'] ?? '[]', true) ?: [];
        $rolePerms = json_decode($user['role_permissions'] ?? '[]', true) ?: [];
        
        $roleNameLower = strtolower($user['role_name'] ?? '');
        if ($roleNameLower === 'admin') {
            $allPerms = [
                "dashboard_view",
                "products_manage",
                "stock_manage",
                "orders_manage",
                "customers_manage",
                "promotions_manage",
                "delivery_manage",
                "rewards_manage",
                "staff_manage"
            ];
        } else {
            $allPerms = array_unique(array_merge($userPerms, $rolePerms));
        }

        $customerId = null;
        $employeeId = null;
        $firstName = null;
        $lastName = null;
        $phone = null;
        if ($roleNameLower === 'customer') {
            $stmtCust = $this->db->prepare("SELECT customer_id, first_name, last_name, 
                         COALESCE(NULLIF(phone, ''), (SELECT a.phone FROM addresses a WHERE a.customer_id = customers.customer_id AND a.phone IS NOT NULL AND a.phone != '' ORDER BY a.is_default DESC, a.address_id DESC LIMIT 1)) as phone 
                         FROM customers WHERE user_id = ?");
            $stmtCust->execute([$user['user_id']]);
            $cust = $stmtCust->fetch(PDO::FETCH_ASSOC);
            if ($cust) {
                $customerId = $cust['customer_id'];
                $firstName = $cust['first_name'];
                $lastName = $cust['last_name'];
                $phone = $cust['phone'] ?: '';
            }
        } else {
            $stmtEmp = $this->db->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
            $stmtEmp->execute([$user['user_id']]);
            $emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);
            if ($emp) {
                $employeeId = $emp['employee_id'];
            }
        }

        $response_data = [
            "user_id" => $user['user_id'],
            "username" => $user['username'],
            "first_name" => $firstName,
            "last_name" => $lastName,
            "phone" => $phone,
            "email" => $user['email'],
            "role_name" => $user['role_name'],
            "customer_id" => $customerId,
            "employee_id" => $employeeId,
            "permissions" => array_values($allPerms),
            "csrf_token" => $_SESSION['csrf_token']
        ];

        Response::json(200, "Successfully retrieved user state", $response_data);
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        Response::json(200, "ออกจากระบบสำเร็จ");
    }
}
?>
