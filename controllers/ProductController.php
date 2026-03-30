<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../utils/Response.php';

class ProductController {
    private $db;
    private $product;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->product = new Product($this->db);
    }

    private function isAdmin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
    }

    public function index() {
        $keyword = isset($_GET['keyword']) ? $_GET['keyword'] : "";
        $filter = isset($_GET['filter']) ? $_GET['filter'] : "all";
        
        $stmt = $this->product->getAll($keyword, $filter);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        Response::json(200, "Success", $products);
    }

    public function updateStock() {
        if (!$this->isAdmin()) {
            Response::json(403, "Access Forbidden: Admins only");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['product_id']) || !isset($data['new_quantity'])) {
            Response::json(400, "Bad Request: Missing product_id or new_quantity");
            return;
        }

        $status = $this->product->updateStock($data['product_id'], $data['new_quantity']);
        
        if ($status) {
            Response::json(200, "Stock updated successfully");
        } else {
            Response::json(500, "Failed to update stock");
        }
    }

    public function create() {
        if (!$this->isAdmin()) {
            Response::json(403, "Access Forbidden: Admins only");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        
        if(empty($data['name']) || empty($data['price']) || empty($data['category_id'])) {
            Response::json(400, "Bad Request: Missing required fields");
            return;
        }

        $id = $this->product->create($data);
        if($id) {
            Response::json(201, "Product created successfully", ["product_id" => $id]);
        } else {
            Response::json(500, "Failed to create product");
        }
    }
}
?>
