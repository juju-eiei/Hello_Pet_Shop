<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../utils/Response.php';

class CategoryController {
    private $db;
    private $category;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->category = new Category($this->db);
    }

    private function isAdmin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
    }

    public function index() {
        $stmt = $this->category->getAll();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::json(200, "Success", $categories);
    }

    public function create() {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only"); return;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        if(empty($data['name'])) {
            Response::json(400, "Missing name"); return;
        }
        $id = $this->category->create($data['name']);
        if($id) {
            Response::json(201, "Category created", ["id" => $id]);
        } else {
            Response::json(500, "Failed to create category");
        }
    }
}
?>
