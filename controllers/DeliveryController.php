<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

class DeliveryController
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    private function isAdmin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
    }

    public function getCompanies()
    {
        try {
            $stmt = $this->db->query("SELECT * FROM delivery_companies ORDER BY company_id ASC");
            $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Response::json(200, "Success", $companies);
        } catch (Exception $e) {
            Response::json(500, "Error fetching shipping companies: " . $e->getMessage());
        }
    }

    public function createCompany()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['company_name'])) {
            Response::json(400, "Missing company_name");
            return;
        }

        $base_rate = isset($data['base_rate']) ? (float)$data['base_rate'] : 0.00;
        $rate_per_kg = isset($data['rate_per_kg']) ? (float)$data['rate_per_kg'] : 0.00;

        try {
            $stmt = $this->db->prepare("INSERT INTO delivery_companies (company_name, base_rate, rate_per_kg) VALUES (?, ?, ?)");
            $stmt->execute([$data['company_name'], $base_rate, $rate_per_kg]);
            $company_id = $this->db->lastInsertId();
            Response::json(201, "Shipping company created", ["company_id" => $company_id]);
        } catch (Exception $e) {
            Response::json(500, "Failed to create shipping company: " . $e->getMessage());
        }
    }

    public function updateCompany()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['company_id']) || empty($data['company_name'])) {
            Response::json(400, "Missing company_id or company_name");
            return;
        }

        $base_rate = isset($data['base_rate']) ? (float)$data['base_rate'] : 0.00;
        $rate_per_kg = isset($data['rate_per_kg']) ? (float)$data['rate_per_kg'] : 0.00;

        try {
            $stmt = $this->db->prepare("UPDATE delivery_companies SET company_name = ?, base_rate = ?, rate_per_kg = ? WHERE company_id = ?");
            $stmt->execute([$data['company_name'], $base_rate, $rate_per_kg, $data['company_id']]);
            Response::json(200, "Shipping company updated successfully");
        } catch (Exception $e) {
            Response::json(500, "Failed to update shipping company: " . $e->getMessage());
        }
    }

    public function deleteCompany()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $company_id = isset($data['company_id']) ? $data['company_id'] : (isset($_GET['company_id']) ? $_GET['company_id'] : null);

        if (!$company_id) {
            Response::json(400, "Missing company_id");
            return;
        }

        try {
            // Check if there are deliveries associated with this company
            $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM deliveries WHERE company_id = ?");
            $checkStmt->execute([$company_id]);
            $count = $checkStmt->fetchColumn();

            if ($count > 0) {
                Response::json(400, "Cannot delete company. It is currently associated with active deliveries.");
                return;
            }

            $stmt = $this->db->prepare("DELETE FROM delivery_companies WHERE company_id = ?");
            $stmt->execute([$company_id]);
            Response::json(200, "Shipping company deleted successfully");
        } catch (Exception $e) {
            Response::json(500, "Failed to delete shipping company: " . $e->getMessage());
        }
    }

    public function getRates()
    {
        $company_id = isset($_GET['company_id']) ? $_GET['company_id'] : null;
        if (!$company_id) {
            Response::json(400, "Missing company_id");
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM delivery_weight_rates WHERE company_id = ? ORDER BY min_weight ASC");
            $stmt->execute([$company_id]);
            $rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Response::json(200, "Success", $rates);
        } catch (Exception $e) {
            Response::json(500, "Error fetching shipping rates: " . $e->getMessage());
        }
    }

    public function saveRate()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['company_id']) || !isset($data['min_weight']) || !isset($data['max_weight']) || !isset($data['price'])) {
            Response::json(400, "Missing required fields (company_id, min_weight, max_weight, price)");
            return;
        }

        $rate_id = isset($data['rate_id']) ? $data['rate_id'] : null;
        $company_id = $data['company_id'];
        $min_weight = (float)$data['min_weight'];
        $max_weight = (float)$data['max_weight'];
        $price = (float)$data['price'];

        try {
            if ($rate_id) {
                $stmt = $this->db->prepare("UPDATE delivery_weight_rates SET min_weight = ?, max_weight = ?, price = ? WHERE rate_id = ? AND company_id = ?");
                $stmt->execute([$min_weight, $max_weight, $price, $rate_id, $company_id]);
                Response::json(200, "Shipping rate updated successfully");
            } else {
                $stmt = $this->db->prepare("INSERT INTO delivery_weight_rates (company_id, min_weight, max_weight, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$company_id, $min_weight, $max_weight, $price]);
                $new_rate_id = $this->db->lastInsertId();
                Response::json(201, "Shipping rate created successfully", ["rate_id" => $new_rate_id]);
            }
        } catch (Exception $e) {
            Response::json(500, "Failed to save shipping rate: " . $e->getMessage());
        }
    }

    public function deleteRate()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $rate_id = isset($data['rate_id']) ? $data['rate_id'] : (isset($_GET['rate_id']) ? $_GET['rate_id'] : null);

        if (!$rate_id) {
            Response::json(400, "Missing rate_id");
            return;
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM delivery_weight_rates WHERE rate_id = ?");
            $stmt->execute([$rate_id]);
            Response::json(200, "Shipping rate deleted successfully");
        } catch (Exception $e) {
            Response::json(500, "Failed to delete shipping rate: " . $e->getMessage());
        }
    }
}
?>
