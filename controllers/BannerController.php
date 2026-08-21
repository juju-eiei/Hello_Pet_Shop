<?php
require_once __DIR__ . '/../models/PromotionBanner.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';

class BannerController {
    private $model;

    public function __construct() {
        $this->model = new PromotionBanner();
    }

    public function index() {
        $all = isset($_GET['all']) && $_GET['all'] === 'true';
        if ($all) {
            // For admin view
            $banners = $this->model->getAll(false);
        } else {
            // For customer view
            $banners = $this->model->getAll(true);
        }
        echo json_encode(["status" => 200, "data" => $banners]);
    }

    public function show() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => 400, "message" => "Banner ID is required"]);
            return;
        }

        $banner = $this->model->getById($id);
        if ($banner) {
            echo json_encode(["status" => 200, "data" => $banner]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => 404, "message" => "Banner not found"]);
        }
    }

    public function create() {
        try {
            $title = trim($_POST['title'] ?? '');
            $linkUrl = trim($_POST['link_url'] ?? '');
            $displayOrder = (int)($_POST['display_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

            if (empty($title)) {
                http_response_code(400);
                echo json_encode(["status" => 400, "message" => "กรุณาระบุชื่อโปรโมชั่น/แบนเนอร์"]);
                return;
            }

            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(["status" => 400, "message" => "กรุณาเลือกไฟล์รูปภาพแบนเนอร์"]);
                return;
            }

            $imageUrl = $this->uploadImage($_FILES['image']);
            if (!$imageUrl) {
                return; // Error already outputted by uploadImage
            }

            $id = $this->model->create([
                'title' => $title,
                'image_url' => $imageUrl,
                'link_url' => $linkUrl,
                'display_order' => $displayOrder,
                'is_active' => $isActive
            ]);

            echo json_encode([
                "status" => 201,
                "message" => "เพิ่มแบนเนอร์โปรโมชั่นเรียบร้อยแล้ว",
                "data" => ["id" => $id, "image_url" => $imageUrl]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => 500, "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
        }
    }

    public function update() {
        try {
            $id = $_POST['id'] ?? ($_GET['id'] ?? null);
            if (!$id) {
                // Check if json
                $json = file_get_contents('php://input');
                $jsonData = json_decode($json, true);
                if ($jsonData) {
                    $id = $jsonData['id'] ?? null;
                    $_POST = array_merge($_POST, $jsonData);
                }
            }

            if (!$id) {
                http_response_code(400);
                echo json_encode(["status" => 400, "message" => "Banner ID is required"]);
                return;
            }

            $existing = $this->model->getById($id);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(["status" => 404, "message" => "ไม่พบแบนเนอร์ที่ต้องการแก้ไข"]);
                return;
            }

            $data = [];
            if (isset($_POST['title'])) $data['title'] = trim($_POST['title']);
            if (isset($_POST['link_url'])) $data['link_url'] = trim($_POST['link_url']);
            if (isset($_POST['display_order'])) $data['display_order'] = (int)$_POST['display_order'];
            if (isset($_POST['is_active'])) $data['is_active'] = (int)$_POST['is_active'];

            // Check if new image uploaded
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imageUrl = $this->uploadImage($_FILES['image']);
                if (!$imageUrl) return;
                $data['image_url'] = $imageUrl;
            }

            $this->model->update($id, $data);
            echo json_encode(["status" => 200, "message" => "อัปเดตแบนเนอร์เรียบร้อยแล้ว"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => 500, "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
        }
    }

    public function toggle() {
        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            $id = $data['id'] ?? ($_POST['id'] ?? null);

            if (!$id) {
                http_response_code(400);
                echo json_encode(["status" => 400, "message" => "Banner ID is required"]);
                return;
            }

            $this->model->toggleStatus($id);
            echo json_encode(["status" => 200, "message" => "เปลี่ยนสถานะการแสดงผลเรียบร้อยแล้ว"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => 500, "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
        }
    }

    public function delete() {
        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            $id = $data['id'] ?? ($_GET['id'] ?? ($_POST['id'] ?? null));

            if (!$id) {
                http_response_code(400);
                echo json_encode(["status" => 400, "message" => "Banner ID is required"]);
                return;
            }

            $existing = $this->model->getById($id);
            if ($existing) {
                $this->model->delete($id);
                // Optionally delete custom uploaded file
                if (!empty($existing['image_url']) && strpos($existing['image_url'], '/images/promotions/banner_') !== false) {
                    $filePath = __DIR__ . '/../public' . $existing['image_url'];
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
            }

            echo json_encode(["status" => 200, "message" => "ลบแบนเนอร์เรียบร้อยแล้ว"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => 500, "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
        }
    }

    private function uploadImage($file) {


        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            http_response_code(400);
            echo json_encode(["status" => 400, "message" => "รองรับเฉพาะไฟล์รูปภาพ .jpg, .jpeg, .png, .webp เท่านั้น"]);
            return null;
        }

        $targetDir = __DIR__ . '/../public/images/promotions/';
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $filename = 'banner_' . uniqid() . '.' . $ext;
        $targetFile = $targetDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            return '/images/promotions/' . $filename;
        } else {
            http_response_code(500);
            echo json_encode(["status" => 500, "message" => "ไม่สามารถบันทึกไฟล์รูปภาพได้"]);
            return null;
        }
    }
}
?>
