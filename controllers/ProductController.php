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

    private function getEmployeeId() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $query = "SELECT employee_id FROM employees WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['employee_id'] : null;
    }

    public function stockHistory() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'employee'])) {
            Response::json(403, "Access Forbidden");
            return;
        }

        $keyword = $_GET['keyword'] ?? '';

        $query = "SELECT 
                    l.log_id,
                    l.product_id,
                    p.product_name,
                    l.employee_id,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    l.reference_id,
                    l.movement_type,
                    l.quantity,
                    l.unit_cost,
                    l.created_at
                  FROM inventory_logs l
                  LEFT JOIN products p ON l.product_id = p.product_id
                  LEFT JOIN employees e ON l.employee_id = e.employee_id";
        
        $params = [];
        if (!empty($keyword)) {
            $query .= " WHERE p.product_name LIKE :keyword OR CONCAT(e.first_name, ' ', e.last_name) LIKE :keyword";
            $params[':keyword'] = "%{$keyword}%";
        }

        $query .= " ORDER BY l.log_id DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json(200, "Success", $logs);
    }

    public function index() {
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : "";
        $filter = isset($_GET['filter']) ? trim($_GET['filter']) : "all";
        
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

        $productId = (int)$data['product_id'];

        $this->db->beginTransaction();
        try {
            // Get old product quantity first
            $old_product = $this->product->getById($productId);
            if (!$old_product) {
                Response::json(404, "Product not found");
                $this->db->rollBack();
                return;
            }
            $old_qty = (int)$old_product['stock_qty'];
            $new_qty = (int)$data['new_quantity'];
            $qty_change = $new_qty - $old_qty;

            if ($qty_change !== 0) {
                // Adjust lots
                if ($qty_change < 0) {
                    $deductQty = -$qty_change;
                    $stmtLots = $this->db->prepare("SELECT * FROM product_lots WHERE product_id = ? AND quantity > 0 ORDER BY (expiry_date IS NULL) ASC, expiry_date ASC, lot_id ASC FOR UPDATE");
                    $stmtLots->execute([$productId]);
                    $lots = $stmtLots->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($lots as $lot) {
                        if ($deductQty <= 0) break;
                        $deduct = min($deductQty, (int)$lot['quantity']);
                        $newLotQty = (int)$lot['quantity'] - $deduct;
                        $stmtUpdateLot = $this->db->prepare("UPDATE product_lots SET quantity = ? WHERE lot_id = ?");
                        $stmtUpdateLot->execute([$newLotQty, $lot['lot_id']]);
                        $deductQty -= $deduct;
                    }
                } else {
                    // Try to find the latest lot for this product to add to, otherwise create a new ADJUSTMENT lot
                    $stmtLatestLot = $this->db->prepare("SELECT * FROM product_lots WHERE product_id = ? ORDER BY lot_id DESC LIMIT 1 FOR UPDATE");
                    $stmtLatestLot->execute([$productId]);
                    $latestLot = $stmtLatestLot->fetch(PDO::FETCH_ASSOC);

                    if ($latestLot) {
                        $newLotQty = (int)$latestLot['quantity'] + $qty_change;
                        $stmtUpdateLot = $this->db->prepare("UPDATE product_lots SET quantity = ? WHERE lot_id = ?");
                        $stmtUpdateLot->execute([$newLotQty, $latestLot['lot_id']]);
                    } else {
                        $stmtInsertLot = $this->db->prepare("INSERT INTO product_lots (product_id, lot_number, quantity, expiry_date, cost_price) VALUES (?, 'ADJUSTMENT', ?, NULL, ?)");
                        $stmtInsertLot->execute([$productId, $qty_change, (float)($old_product['cost_price'] ?? 0.0)]);
                    }
                }

                // Sync products table stock_qty with total lots
                $stmtSync = $this->db->prepare("UPDATE products SET stock_qty = (SELECT COALESCE(SUM(quantity), 0) FROM product_lots WHERE product_id = ?) WHERE product_id = ?");
                $stmtSync->execute([$productId, $productId]);

                // Log movement
                $employee_id = $this->getEmployeeId();
                if ($employee_id) {
                    $stmtLog = $this->db->prepare("INSERT INTO inventory_logs (product_id, employee_id, quantity, movement_type, unit_cost) VALUES (?, ?, ?, 3, 0)");
                    $stmtLog->execute([$productId, $employee_id, $qty_change]);
                }
            }

            $this->db->commit();
            Response::json(200, "Stock updated successfully");
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(500, "Failed to update stock: " . $e->getMessage());
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
        $month = $_GET['month'] ?? null;
        $params = [];
        $where = "o.status != 5";
        
        if ($month) {
            $where .= " AND o.order_date LIKE :month";
            $params[':month'] = $month . '%';
        }
        
        $query = "SELECT p.product_id, p.product_name, p.image_url, p.selling_price as price, COALESCE(SUM(od.quantity), 0) as sales
                  FROM products p
                  LEFT JOIN order_details od ON p.product_id = od.product_id
                  LEFT JOIN orders o ON od.order_id = o.order_id AND $where
                  GROUP BY p.product_id
                  ORDER BY sales DESC
                  LIMIT 5";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
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

        $id = isset($data['id']) ? (int)$data['id'] : null;
        if(!$id || $id <= 0) {
            Response::json(400, "Missing or invalid product_id"); return;
        }

        // Input validations
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
            if (empty($data['name']) || mb_strlen($data['name'], 'UTF-8') > 255) {
                Response::json(400, "ชื่อสินค้าต้องไม่ว่างและไม่เกิน 255 ตัวอักษร"); return;
            }
        }

        if (isset($data['price'])) {
            $price = (float)$data['price'];
            if ($price < 0) {
                Response::json(400, "ราคาสินค้าต้องไม่ต่ำกว่า 0 บาท"); return;
            }
        }

        if (isset($data['cost_price'])) {
            $cost_price = (float)$data['cost_price'];
            if ($cost_price < 0) {
                Response::json(400, "ราคาทุนสินค้าต้องไม่ต่ำกว่า 0 บาท"); return;
            }
        }

        if (isset($data['status'])) {
            $data['status'] = trim($data['status']);
            if (!in_array($data['status'], ['active', 'inactive'])) {
                Response::json(400, "สถานะสินค้าต้องเป็น active หรือ inactive เท่านั้น"); return;
            }
        }

        if (isset($data['barcode'])) {
            $data['barcode'] = trim($data['barcode']);
            if (mb_strlen($data['barcode'], 'UTF-8') > 100) {
                Response::json(400, "บาร์โค้ดต้องไม่เกิน 100 ตัวอักษร"); return;
            }
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
        $data['weight_value'] = isset($data['weight_value']) && $data['weight_value'] !== '' ? (float)$data['weight_value'] : null;
        $data['weight_unit'] = $data['weight_unit'] ?? null;
        $data['image_url'] = $data['image_url'] ?? null;
        $data['barcode'] = $data['barcode'] ?? null;
        $data['description'] = $data['description'] ?? null;
        $data['cost_price'] = $data['cost_price'] ?? 0;

        try {
            if($this->product->update($id, $data)) {
                Response::json(200, "Product updated successfully");
            } else {
                Response::json(500, "Failed to update product");
            }
        } catch (Exception $e) {
            error_log("Failed to update product ID {$id}: " . $e->getMessage());
            Response::json(500, "เกิดข้อผิดพลาดในการแก้ไขข้อมูลสินค้า กรุณาลองใหม่อีกครั้ง");
        }
    }

    public function delete() {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only"); return;
        }
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if(!$id || $id <= 0) {
            Response::json(400, "Missing or invalid product_id"); return;
        }
        try {
            if($this->product->delete($id)) {
                Response::json(200, "Product deleted");
            } else {
                Response::json(500, "Failed to delete");
            }
        } catch (Exception $e) {
            error_log("Failed to delete product ID {$id}: " . $e->getMessage());
            Response::json(500, "เกิดข้อผิดพลาดในการลบสินค้า กรุณาลองใหม่อีกครั้ง");
        }
    }

    public function deleteBulk() {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only"); return;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        $ids = $data['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            Response::json(400, "Missing or invalid ids"); return;
        }

        try {
            $deleted_count = 0;
            $failed_ids = [];
            foreach ($ids as $id) {
                try {
                    if ($this->product->delete((int)$id)) {
                        $deleted_count++;
                    } else {
                        $failed_ids[] = $id;
                    }
                } catch (Exception $e) {
                    $failed_ids[] = $id;
                }
            }
            if ($deleted_count > 0) {
                if (empty($failed_ids)) {
                    Response::json(200, "ลบสินค้าที่เลือกสำเร็จทั้งหมด จำนวน {$deleted_count} รายการ");
                } else {
                    Response::json(200, "ลบสินค้าสำเร็จ {$deleted_count} รายการ (ไม่สามารถลบได้ " . count($failed_ids) . " รายการเนื่องจากถูกใช้งานอยู่ในคำสั่งซื้อหรือการจัดซื้อ)");
                }
            } else {
                Response::json(400, "ไม่สามารถลบสินค้าที่เลือกได้ เนื่องจากสินค้าเหล่านี้ถูกใช้งานอยู่ในคำสั่งซื้อหรือการจัดซื้อ");
            }
        } catch (Exception $e) {
            error_log("Failed to bulk delete products: " . $e->getMessage());
            Response::json(500, "เกิดข้อผิดพลาดในการลบสินค้า กรุณาลองใหม่อีกครั้ง");
        }
    }

    private function uploadImage($file) {
        // 1. Limit size <= 5MB
        $max_size = 5 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            Response::json(400, "ขนาดไฟล์ภาพต้องไม่เกิน 5MB");
            return false;
        }

        // 2. Extension validation
        $file_ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($file_ext, $allowed_exts)) {
            Response::json(400, "อนุญาตเฉพาะไฟล์ภาพนามสกุล .jpg, .jpeg, .png, .webp เท่านั้น");
            return false;
        }

        // 3. MIME type validation with finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime_type, $allowed_mimes)) {
            Response::json(400, "ประเภทไฟล์ไม่ถูกต้อง (Invalid MIME Type)");
            return false;
        }

        // 4. Secure random filename
        $target_dir = __DIR__ . "/../assets/img/products/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        try {
            $random_name = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $random_name = uniqid();
        }
        $new_filename = $random_name . "." . $file_ext;
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

        // Input validations
        $data['name'] = trim($data['name']);
        if (mb_strlen($data['name'], 'UTF-8') > 255) {
            Response::json(400, "ชื่อสินค้าต้องไม่เกิน 255 ตัวอักษร"); return;
        }

        $data['price'] = (float)$data['price'];
        if ($data['price'] < 0) {
            Response::json(400, "ราคาสินค้าต้องไม่ต่ำกว่า 0 บาท"); return;
        }

        if (isset($data['cost_price'])) {
            $data['cost_price'] = (float)$data['cost_price'];
            if ($data['cost_price'] < 0) {
                Response::json(400, "ราคาทุนสินค้าต้องไม่ต่ำกว่า 0 บาท"); return;
            }
        }

        if (isset($data['barcode'])) {
            $data['barcode'] = trim($data['barcode']);
            if (mb_strlen($data['barcode'], 'UTF-8') > 100) {
                Response::json(400, "บาร์โค้ดต้องไม่เกิน 100 ตัวอักษร"); return;
            }
        }

        if (isset($data['status'])) {
            $data['status'] = trim($data['status']);
            if (!in_array($data['status'], ['active', 'inactive'])) {
                Response::json(400, "สถานะสินค้าต้องเป็น active หรือ inactive เท่านั้น"); return;
            }
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
        $data['weight_value'] = isset($data['weight_value']) && $data['weight_value'] !== '' ? (float)$data['weight_value'] : null;
        $data['weight_unit'] = $data['weight_unit'] ?? null;
        $data['image_url'] = $data['image_url'] ?? null;
        $data['barcode'] = $data['barcode'] ?? null;
        $data['description'] = $data['description'] ?? null;
        $data['cost_price'] = $data['cost_price'] ?? 0;
        $data['stock_quantity'] = $data['stock_quantity'] ?? 0;
        $data['created_by'] = $this->getEmployeeId();

        try {
            $id = $this->product->create($data);
            if($id) {
                $employee_id = $this->getEmployeeId();
                if ($employee_id) {
                    $initial_qty = (int)($data['stock_quantity'] ?? 0);
                    $cost_price = (float)($data['cost_price'] ?? 0);
                    $stmtLog = $this->db->prepare("INSERT INTO inventory_logs (product_id, employee_id, quantity, movement_type, unit_cost) VALUES (?, ?, ?, 4, ?)");
                    $stmtLog->execute([$id, $employee_id, $initial_qty, $cost_price]);
                }
                Response::json(201, "Product created successfully", ["product_id" => $id]);
            } else {
                Response::json(500, "Failed to create product");
            }
        } catch (Exception $e) {
            error_log("Failed to create product: " . $e->getMessage());
            Response::json(500, "เกิดข้อผิดพลาดในการสร้างสินค้าใหม่ กรุณาลองใหม่อีกครั้ง");
        }
    }

    public function updateBarcode() {
        if (!$this->isAdmin()) {
            Response::json(403, "Access Forbidden: Admins only");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['product_id']) || !isset($data['barcode'])) {
            Response::json(400, "Bad Request: Missing product_id or barcode");
            return;
        }

        $productId = (int)$data['product_id'];
        $barcode = trim($data['barcode']);
        if (mb_strlen($barcode, 'UTF-8') > 100) {
            Response::json(400, "บาร์โค้ดต้องไม่เกิน 100 ตัวอักษร");
            return;
        }

        try {
            $status = $this->product->updateBarcode($productId, $barcode);
            if ($status) {
                Response::json(200, "Barcode updated successfully");
            } else {
                Response::json(500, "Failed to update barcode");
            }
        } catch (Exception $e) {
            error_log("Failed to update barcode for product ID {$productId}: " . $e->getMessage());
            Response::json(500, "เกิดข้อผิดพลาดในการอัปเดตบาร์โค้ด");
        }
    }

    public function getLots() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), ['admin', 'employee'])) {
            Response::json(403, "Access Forbidden");
            return;
        }

        $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
        if (!$productId) {
            Response::json(400, "Missing product_id");
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM product_lots WHERE product_id = ? AND quantity > 0 ORDER BY (expiry_date IS NULL) ASC, expiry_date ASC, lot_id ASC");
            $stmt->execute([$productId]);
            $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Response::json(200, "Success", $lots);
        } catch (Exception $e) {
            Response::json(500, "Error loading lots: " . $e->getMessage());
        }
    }
}
?>
