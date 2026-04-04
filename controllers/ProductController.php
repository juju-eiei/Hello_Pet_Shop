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

    public function show() {
        $id = $_GET['id'] ?? null;
        if(!$id) {
            Response::json(400, "Missing product_id");
            return;
        }
        $data = $this->product->getById($id);
        if($data) {
            Response::json(200, "Success", $data);
        } else {
            Response::json(404, "Product not found");
        }
    }

    public function top() {
        $query = "SELECT p.product_id, p.product_name, p.image_url, p.selling_price as price, COALESCE(SUM(od.quantity), 0) as sales
                  FROM products p
                  LEFT JOIN order_details od ON p.product_id = od.product_id
                  LEFT JOIN orders o ON od.order_id = o.order_id AND o.status != 5
                  GROUP BY p.product_id
                  ORDER BY sales DESC
                  LIMIT 5";
        $stmt = $this->db->query($query);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::json(200, "Success", $products);
    }

    public function update() {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only"); return;
        }

        // Handle both JSON and FormData
        $data = $_POST;
        if(empty($data)) {
            $data = json_decode(file_get_contents("php://input"), true);
        }

        $id = $data['id'] ?? null;
        if(!$id) {
            Response::json(400, "Missing product_id"); return;
        }

        // Handle File Upload
        if(isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploaded_url = $this->uploadImage($_FILES['image']);
            if($uploaded_url) {
                $data['image_url'] = $uploaded_url;
            }
        }

        // Default status and other fields if NOT in $data
        $data['status'] = $data['status'] ?? 'active';
        $data['weight'] = $data['weight'] ?? null;
        $data['image_url'] = $data['image_url'] ?? null;
        $data['barcode'] = $data['barcode'] ?? null;
        $data['description'] = $data['description'] ?? null;
        $data['cost_price'] = $data['cost_price'] ?? 0;

        if($this->product->update($id, $data)) {
            Response::json(200, "Product updated successfully");
        } else {
            Response::json(500, "Failed to update product");
        }
    }

    public function delete() {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only"); return;
        }
        $id = $_GET['id'] ?? null;
        if(!$id) {
            Response::json(400, "Missing product_id"); return;
        }
        if($this->product->delete($id)) {
            Response::json(200, "Product deleted");
        } else {
            Response::json(500, "Failed to delete");
        }
    }

    private function uploadImage($file) {
        $target_dir = __DIR__ . "/../assets/img/products/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        $new_filename = uniqid() . "." . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return "assets/img/products/" . $new_filename;
        }
        return false;
    }

    public function create() {
        if (!$this->isAdmin()) {
            Response::json(403, "Access Forbidden: Admins only");
            return;
        }

        $data = $_POST;
        if(empty($data)) {
            $data = json_decode(file_get_contents("php://input"), true);
        }
        
        if(empty($data['name']) || empty($data['price']) || empty($data['category_id'])) {
            Response::json(400, "Bad Request: Missing required fields");
            return;
        }

        // Handle File Upload for Create
        if(isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploaded_url = $this->uploadImage($_FILES['image']);
            if($uploaded_url) {
                $data['image_url'] = $uploaded_url;
            }
        }

        $data['status'] = $data['status'] ?? 'active';
        $data['weight'] = $data['weight'] ?? null;
        $data['image_url'] = $data['image_url'] ?? null;
        $data['barcode'] = $data['barcode'] ?? null;
        $data['description'] = $data['description'] ?? null;
        $data['cost_price'] = $data['cost_price'] ?? 0;
        $data['stock_quantity'] = $data['stock_quantity'] ?? 0;

        $id = $this->product->create($data);
        if($id) {
            Response::json(201, "Product created successfully", ["product_id" => $id]);
        } else {
            Response::json(500, "Failed to create product");
        }
    }
}
?>
