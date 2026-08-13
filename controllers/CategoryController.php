<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../utils/Response.php';

class CategoryController
{
    private $db;
    private $category;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->category = new Category($this->db);
    }

    private function isAdmin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
    }

    public function index()
    {
        $stmt = $this->category->getAll();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::json(200, "Success", $categories);
    }

    public function create()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['name'])) {
            Response::json(400, "Missing name");
            return;
        }
        $requires_expiration = isset($data['requires_expiration']) ? (int)$data['requires_expiration'] : 1;
        $id = $this->category->create($data['name'], $requires_expiration);
        if ($id) {
            Response::json(201, "Category created", ["id" => $id]);
        } else {
            Response::json(500, "Failed to create category");
        }
    }

    public function update()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['id']) || empty($data['name'])) {
            Response::json(400, "Missing id or name");
            return;
        }
        $requires_expiration = isset($data['requires_expiration']) ? (int)$data['requires_expiration'] : 1;
        if ($this->category->update($data['id'], $data['name'], $requires_expiration)) {
            Response::json(200, "Category updated successfully");
        } else {
            Response::json(500, "Failed to update category");
        }
    }

    public function delete()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['id'])) {
            Response::json(400, "Missing id");
            return;
        }
        
        if ($this->category->delete($data['id'])) {
            Response::json(200, "Category deleted successfully");
        } else {
            Response::json(500, "Failed to delete category (it may be in use)");
        }
    }

    public function deleteBulk()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        $ids = $data['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            Response::json(400, "Missing or invalid ids");
            return;
        }
        
        $deleted_count = 0;
        $failed_ids = [];
        foreach ($ids as $id) {
            try {
                if ($this->category->delete((int)$id)) {
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
                Response::json(200, "ลบประเภทสินค้าสำเร็จทั้งหมด จำนวน {$deleted_count} รายการ");
            } else {
                Response::json(200, "ลบประเภทสินค้าสำเร็จ {$deleted_count} รายการ (ไม่สามารถลบได้ " . count($failed_ids) . " รายการเนื่องจากถูกใช้งานอยู่)");
            }
        } else {
            Response::json(400, "ไม่สามารถลบประเภทสินค้าที่เลือกได้ เนื่องจากมีสินค้าใช้งานอยู่ภายใต้หมวดหมู่ดังกล่าว");
        }
    }
}
?>