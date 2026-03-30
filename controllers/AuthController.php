<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';

class AuthController {
    private $db;
    private $userModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->userModel = new User($this->db);
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['username']) || empty($data['password'])) {
            Response::json(400, "กรุณากรอก Username และ Password");
            return;
        }

        $user = $this->userModel->findByUsername($data['username']);

        if ($user && password_verify($data['password'], $user['password'])) {
            // Remove password from response
            unset($user['password']);
            
            // In a real app, you'd start a session or generate a JWT here
            session_start();
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role_name'];

            Response::json(200, "เข้าสู่ระบบสำเร็จ", $user);
        } else {
            Response::json(401, "Username หรือ Password ไม่ถูกต้อง");
        }
    }

    public function register() {
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
            Response::json(201, "สมัครสมาชิกสำเร็จ", ["user_id" => $userId]);
        } else {
            Response::json(500, "เกิดข้อผิดพลาดในการสมัครสมาชิก");
        }
    }
}
?>
