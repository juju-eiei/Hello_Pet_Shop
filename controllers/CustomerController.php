<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

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
            if (!isset($_GET['id'])) {
                Response::json(400, "Customer ID is required");
                return;
            }
            $customerId = (int)$_GET['id'];

            // Customer Info
            $qCust = "SELECT c.customer_id, c.first_name, c.last_name, c.phone, c.points, 
                             u.email, u.created_at
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
                'email' => $customer['email'] ? $customer['email'] : 'N/A',
                'phone' => $customer['phone'] ? $customer['phone'] : 'N/A',
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

    private function uploadPetImage($file) {
        $target_dir = __DIR__ . "/../assets/img/pets/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        $new_filename = uniqid() . "." . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return "assets/img/pets/" . $new_filename;
        }
        return false;
    }

    public function savePet() {
        try {
            $data = $_POST;
            if (empty($data)) {
                $data = json_decode(file_get_contents('php://input'), true);
            }
            
            if (empty($data['customer_id']) || empty($data['pet_name']) || empty($data['pet_type'])) {
                Response::json(400, "Missing required pet information");
                return;
            }

            $customerId = (int)$data['customer_id'];
            $petId = isset($data['pet_id']) ? (int)$data['pet_id'] : 0;
            $name = trim($data['pet_name']);
            $type = trim($data['pet_type']);
            $breed = isset($data['breed']) ? trim($data['breed']) : null;
            $birthdate = !empty($data['birthdate']) ? trim($data['birthdate']) : null;
            $weight = isset($data['weight']) && $data['weight'] !== '' ? (float)$data['weight'] : null;
            $allergy = isset($data['allergy_info']) ? trim($data['allergy_info']) : null;
            $imageUrl = isset($data['image_url']) ? trim($data['image_url']) : null;

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
                $qUpdate = "UPDATE pets SET pet_name=?, pet_type=?, breed=?, birthdate=?, weight=?, allergy_info=?, image_url=? WHERE pet_id=? AND customer_id=?";
                $stmt = $this->db->prepare($qUpdate);
                $stmt->execute([$name, $type, $breed, $birthdate, $weight, $allergy, $imageUrl, $petId, $customerId]);
                Response::json(200, "Pet updated successfully");
            } else {
                // INSERT
                $qInsert = "INSERT INTO pets (customer_id, pet_name, pet_type, breed, birthdate, weight, allergy_info, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($qInsert);
                $stmt->execute([$customerId, $name, $type, $breed, $birthdate, $weight, $allergy, $imageUrl]);
                Response::json(201, "Pet added successfully");
            }

        } catch (Exception $e) {
            Response::json(500, "Error saving pet", ["error" => $e->getMessage()]);
        }
    }

    public function deletePet() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['pet_id']) || empty($data['customer_id'])) {
                Response::json(400, "Missing required info");
                return;
            }
            
            $qDel = "DELETE FROM pets WHERE pet_id = ? AND customer_id = ?";
            $stmt = $this->db->prepare($qDel);
            $stmt->execute([(int)$data['pet_id'], (int)$data['customer_id']]);

            Response::json(200, "Pet deleted successfully");
        } catch (Exception $e) {
            Response::json(500, "Error deleting pet", ["error" => $e->getMessage()]);
        }
    }
}
?>
