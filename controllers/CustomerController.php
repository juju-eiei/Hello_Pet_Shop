<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';

class CustomerController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function index() {
        try {
            $query = "SELECT c.customer_id, c.first_name, c.last_name, c.phone, c.points, u.email,
                             COUNT(p.pet_id) as pet_count
                      FROM customers c
                      JOIN users u ON c.user_id = u.user_id
                      LEFT JOIN pets p ON c.customer_id = p.customer_id
                      GROUP BY c.customer_id
                      ORDER BY c.customer_id DESC";
            $stmt = $this->db->query($query);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch a summary of how many pets by type
            $qPets = "SELECT c.customer_id, GROUP_CONCAT(p.pet_type SEPARATOR ', ') as pet_types
                      FROM customers c
                      JOIN pets p ON c.customer_id = p.customer_id
                      GROUP BY c.customer_id";
            $stmtPets = $this->db->query($qPets);
            $petTypesMap = [];
            while ($row = $stmtPets->fetch(PDO::FETCH_ASSOC)) {
                $petTypesMap[$row['customer_id']] = $row['pet_types'];
            }

            foreach ($customers as &$c) {
                $cId = $c['customer_id'];
                $c['name'] = trim($c['first_name'] . ' ' . $c['last_name']);
                $c['pet_types'] = isset($petTypesMap[$cId]) ? implode(", ", array_unique(explode(", ", $petTypesMap[$cId]))) : '-';
            }

            Response::json(200, "Customers retrieved successfully", $customers);
        } catch (Exception $e) {
            Response::json(500, "Failed to retrieve customers", ["error" => $e->getMessage()]);
        }
    }

    public function show() {
        try {
            $user = AuthMiddleware::authenticate();

            if (!isset($_GET['id'])) {
                Response::json(400, "Customer ID is required");
                return;
            }
            $customerId = (int)$_GET['id'];

            $roleLower = strtolower($user['role'] ?? '');
            $isStaffOrAdmin = in_array($roleLower, ['admin', 'employee', 'staff', 'manager']);

            if (!$isStaffOrAdmin) {
                // Verify customer ownership
                $stmtCust = $this->db->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
                $stmtCust->execute([$user['user_id']]);
                $cRow = $stmtCust->fetch(PDO::FETCH_ASSOC);
                $cid = $cRow ? (int)$cRow['customer_id'] : -1;

                if ($customerId !== $cid) {
                    Response::json(403, "Forbidden: You do not have permission to view this customer");
                    return;
                }
            } else {
                AuthMiddleware::checkAnyPermission(['customers_view', 'pos_access', 'orders_view']);
            }

            // Customer Info
            $qCust = "SELECT c.customer_id, c.first_name, c.last_name, c.phone, c.points, 
                             u.username, u.email, u.created_at
                      FROM customers c
                      JOIN users u ON c.user_id = u.user_id
                      WHERE c.customer_id = ?";
            $stmtC = $this->db->prepare($qCust);
            $stmtC->execute([$customerId]);
            $customer = $stmtC->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                Response::json(404, "Customer not found");
                return;
            }

            // Pets Info
            $qPets = "SELECT * FROM pets WHERE customer_id = ? ORDER BY pet_id DESC";
            $stmtP = $this->db->prepare($qPets);
            $stmtP->execute([$customerId]);
            $pets = $stmtP->fetchAll(PDO::FETCH_ASSOC);

            // Total Orders Count (Optional bonus info)
            $qOrders = "SELECT COUNT(*) as total_orders, SUM(net_total) as total_spent FROM orders WHERE customer_id = ?";
            $stmtO = $this->db->prepare($qOrders);
            $stmtO->execute([$customerId]);
            $orderStats = $stmtO->fetch(PDO::FETCH_ASSOC);

            $data = [
                'id' => $customer['customer_id'],
                'name' => trim($customer['first_name'] . ' ' . $customer['last_name']),
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
                'username' => $customer['username'] ? $customer['username'] : '',
                'email' => $customer['email'] ? $customer['email'] : '',
                'phone' => $customer['phone'] ? $customer['phone'] : '',
                'points' => (int)$customer['points'],
                'joined_date' => date('d M Y', strtotime($customer['created_at'])),
                'total_orders' => (int)$orderStats['total_orders'],
                'total_spent' => (float)$orderStats['total_spent'],
                'pets' => $pets
            ];

            Response::json(200, "Customer details loaded", $data);
        } catch (Exception $e) {
            Response::json(500, "Error loading customer details", ["error" => $e->getMessage()]);
        }
    }

    public function update() {
        try {
            $user = AuthMiddleware::authenticate();

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data)) {
                $data = $_POST;
            }

            if (empty($data['first_name'])) {
                Response::json(400, "กรุณากรอกชื่อลูกค้า");
                return;
            }

            $roleLower = strtolower($user['role'] ?? '');
            $isStaffOrAdmin = in_array($roleLower, ['admin', 'employee', 'staff', 'manager']);

            if ($isStaffOrAdmin) {
                AuthMiddleware::checkPermission('customers_manage');
                if (empty($data['customer_id'])) {
                    Response::json(400, "กรุณาระบุรหัสลูกค้า");
                    return;
                }
                $customerId = (int)$data['customer_id'];
                $points = isset($data['points']) ? (int)$data['points'] : 0;
            } else {
                // Customer can only update their own profile and cannot modify points
                $stmtOwnCust = $this->db->prepare("SELECT customer_id, points FROM customers WHERE user_id = ?");
                $stmtOwnCust->execute([$user['user_id']]);
                $ownCust = $stmtOwnCust->fetch(PDO::FETCH_ASSOC);

                if (!$ownCust) {
                    Response::json(404, "ไม่พบข้อมูลลูกค้าในระบบ");
                    return;
                }
                $customerId = (int)$ownCust['customer_id'];
                $points = (int)$ownCust['points']; // Preserve existing points
            }

            $firstName = trim($data['first_name']);
            $lastName = isset($data['last_name']) ? trim($data['last_name']) : '';
            $username = isset($data['username']) ? trim($data['username']) : null;
            $phone = isset($data['phone']) ? trim($data['phone']) : null;
            $email = isset($data['email']) ? trim($data['email']) : null;

            // Check if customer exists
            $stmtC = $this->db->prepare("SELECT user_id FROM customers WHERE customer_id = ?");
            $stmtC->execute([$customerId]);
            $cust = $stmtC->fetch(PDO::FETCH_ASSOC);

            if (!$cust) {
                Response::json(404, "ไม่พบข้อมูลลูกค้าในระบบ");
                return;
            }

            $userId = $cust['user_id'];

            // Validate username unique if updated
            if (!empty($username)) {
                $stmtCheckUser = $this->db->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
                $stmtCheckUser->execute([$username, $userId]);
                if ($stmtCheckUser->fetch()) {
                    Response::json(400, "ชื่อผู้ใช้ (Username) นี้ถูกใช้งานแล้วในระบบ");
                    return;
                }
            }

            // Validate email unique if updated
            if (!empty($email)) {
                $stmtCheckEmail = $this->db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $stmtCheckEmail->execute([$email, $userId]);
                if ($stmtCheckEmail->fetch()) {
                    Response::json(400, "อีเมลนี้ถูกใช้งานแล้วในระบบ");
                    return;
                }
            }

            // Update customers table
            $qUpCust = "UPDATE customers SET first_name = ?, last_name = ?, phone = ?, points = ? WHERE customer_id = ?";
            $stmtUpCust = $this->db->prepare($qUpCust);
            $stmtUpCust->execute([$firstName, $lastName, $phone, $points, $customerId]);

            // Update users table (username & email) if provided
            if ($username !== null || $email !== null) {
                $qUpUser = "UPDATE users SET username = COALESCE(?, username), email = COALESCE(?, email) WHERE user_id = ?";
                $stmtUpUser = $this->db->prepare($qUpUser);
                $stmtUpUser->execute([$username, $email, $userId]);
            }

            Response::json(200, "อัปเดตข้อมูลลูกค้าเรียบร้อยแล้ว");
        } catch (Exception $e) {
            error_log("Error updating customer: " . $e->getMessage());
            Response::json(500, "เกิดข้อผิดพลาดในการอัปเดตข้อมูลลูกค้า: " . $e->getMessage());
        }
    }

    private function uploadPetImage($file) {
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
        $target_dir = __DIR__ . "/../assets/img/pets/";
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
            return "assets/img/pets/" . $new_filename;
        }
        return false;
    }

    public function savePet() {
        try {
            $user = AuthMiddleware::authenticate();

            $data = $_POST;
            if (empty($data)) {
                $data = json_decode(file_get_contents('php://input'), true);
            }
            
            if (empty($data['pet_name']) || empty($data['pet_type'])) {
                Response::json(400, "Missing required pet information");
                return;
            }

            $roleLower = strtolower($user['role'] ?? '');
            $isStaffOrAdmin = in_array($roleLower, ['admin', 'employee', 'staff', 'manager']);

            if ($isStaffOrAdmin) {
                AuthMiddleware::checkAnyPermission(['pets_manage', 'customers_manage']);
                $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : 0;
            } else {
                $stmtCust = $this->db->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
                $stmtCust->execute([$user['user_id']]);
                $cRow = $stmtCust->fetch(PDO::FETCH_ASSOC);
                $customerId = $cRow ? (int)$cRow['customer_id'] : 0;
            }

            if ($customerId <= 0) {
                Response::json(400, "Invalid customer ID");
                return;
            }

            $petId = isset($data['pet_id']) ? (int)$data['pet_id'] : 0;
            $name = trim($data['pet_name']);
            $type = trim($data['pet_type']);
            $breed = isset($data['breed']) ? trim($data['breed']) : null;
            $birthdate = !empty($data['birthdate']) ? trim($data['birthdate']) : null;
            $weight = isset($data['weight']) && $data['weight'] !== '' ? (float)$data['weight'] : null;
            $allergy = isset($data['allergy_info']) ? trim($data['allergy_info']) : null;
            $notes = isset($data['notes']) ? trim($data['notes']) : null;
            $imageUrl = isset($data['image_url']) ? trim($data['image_url']) : null;

            // Enforce input lengths
            if (mb_strlen($name, 'UTF-8') > 255) {
                Response::json(400, "ชื่อสัตว์เลี้ยงต้องไม่เกิน 255 ตัวอักษร"); return;
            }
            if (mb_strlen($type, 'UTF-8') > 100) {
                Response::json(400, "ประเภทสัตว์เลี้ยงต้องไม่เกิน 100 ตัวอักษร"); return;
            }
            if ($breed && mb_strlen($breed, 'UTF-8') > 100) {
                Response::json(400, "สายพันธุ์ต้องไม่เกิน 100 ตัวอักษร"); return;
            }
            if ($weight !== null && $weight < 0) {
                Response::json(400, "น้ำหนักต้องไม่ต่ำกว่า 0 กิโลกรัม"); return;
            }
            if ($allergy && mb_strlen($allergy, 'UTF-8') > 1000) {
                Response::json(400, "ข้อมูลการแพ้ยาและอาหารต้องไม่เกิน 1000 ตัวอักษร"); return;
            }
            if ($notes && mb_strlen($notes, 'UTF-8') > 1000) {
                Response::json(400, "บันทึกเพิ่มเติมต้องไม่เกิน 1000 ตัวอักษร"); return;
            }

            // Handle Image Upload
            if (isset($_FILES['pet_image']) && $_FILES['pet_image']['error'] === UPLOAD_ERR_OK) {
                $uploadedUrl = $this->uploadPetImage($_FILES['pet_image']);
                if ($uploadedUrl) {
                    $imageUrl = $uploadedUrl;
                }
            } else if (isset($data['remove_image']) && $data['remove_image'] === 'true') {
                $imageUrl = null;
            }

            if ($petId > 0) {
                // If the user did not choose a new image, keep the existing one (unless removed)
                if ($imageUrl === null && (!isset($data['remove_image']) || $data['remove_image'] !== 'true')) {
                    // Fetch existing image_url
                    $qGetImg = "SELECT image_url FROM pets WHERE pet_id = ? AND customer_id = ?";
                    $stmtImg = $this->db->prepare($qGetImg);
                    $stmtImg->execute([$petId, $customerId]);
                    $existing = $stmtImg->fetch(PDO::FETCH_ASSOC);
                    if ($existing) {
                        $imageUrl = $existing['image_url'];
                    }
                }

                // UPDATE
                $qUpdate = "UPDATE pets SET pet_name=?, pet_type=?, breed=?, birthdate=?, weight=?, allergy_info=?, notes=?, image_url=? WHERE pet_id=? AND customer_id=?";
                $stmt = $this->db->prepare($qUpdate);
                $stmt->execute([$name, $type, $breed, $birthdate, $weight, $allergy, $notes, $imageUrl, $petId, $customerId]);
                Response::json(200, "Pet updated successfully");
            } else {
                // INSERT
                $qInsert = "INSERT INTO pets (customer_id, pet_name, pet_type, breed, birthdate, weight, allergy_info, notes, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($qInsert);
                $stmt->execute([$customerId, $name, $type, $breed, $birthdate, $weight, $allergy, $notes, $imageUrl]);
                Response::json(201, "Pet added successfully");
            }

        } catch (Exception $e) {
            error_log("Error saving pet: " . $e->getMessage());
            Response::json(500, "เกิดข้อผิดพลาดในการบันทึกข้อมูลสัตว์เลี้ยง กรุณาลองใหม่อีกครั้ง", ["error" => $e->getMessage()]);
        }
    }

    public function deletePet() {
        try {
            $user = AuthMiddleware::authenticate();

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['pet_id'])) {
                Response::json(400, "Missing required info");
                return;
            }
            
            $petId = (int)$data['pet_id'];
            $roleLower = strtolower($user['role'] ?? '');
            $isStaffOrAdmin = in_array($roleLower, ['admin', 'employee', 'staff', 'manager']);

            if ($isStaffOrAdmin) {
                AuthMiddleware::checkAnyPermission(['pets_manage', 'customers_manage']);
                $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : 0;
            } else {
                $stmtCust = $this->db->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
                $stmtCust->execute([$user['user_id']]);
                $cRow = $stmtCust->fetch(PDO::FETCH_ASSOC);
                $customerId = $cRow ? (int)$cRow['customer_id'] : 0;
            }

            if ($petId <= 0 || $customerId <= 0) {
                Response::json(400, "Invalid pet ID or customer ID");
                return;
            }

            $qDel = "DELETE FROM pets WHERE pet_id = ? AND customer_id = ?";
            $stmt = $this->db->prepare($qDel);
            $stmt->execute([$petId, $customerId]);

            Response::json(200, "Pet deleted successfully");
        } catch (Exception $e) {
            error_log("Error deleting pet: " . $e->getMessage());
            Response::json(500, "เกิดข้อผิดพลาดในการลบสัตว์เลี้ยง");
        }
    }
}
?>
