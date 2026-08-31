<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

class RewardController
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

    public function getSettings()
    {
        try {
            $stmt = $this->db->query("SELECT point_earning_baht, point_earning_qty, point_redemption_rate, point_min_redeem, line_oa_token, line_target_id FROM store_settings LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$settings) {
                // Return defaults if somehow row not created
                $settings = [
                    'point_earning_baht' => 100.00,
                    'point_earning_qty' => 1,
                    'point_redemption_rate' => 1.00,
                    'point_min_redeem' => 10,
                    'line_oa_token' => '',
                    'line_target_id' => ''
                ];
            } else {
                $settings['point_min_redeem'] = isset($settings['point_min_redeem']) ? (int)$settings['point_min_redeem'] : 10;
            }
            Response::json(200, "Success", $settings);
        } catch (Exception $e) {
            Response::json(500, "Error fetching settings: " . $e->getMessage());
        }
    }

    public function saveSettings()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $baht = isset($data['point_earning_baht']) ? (float)$data['point_earning_baht'] : 100.00;
        $qty = isset($data['point_earning_qty']) ? (int)$data['point_earning_qty'] : 1;
        $redeem = isset($data['point_redemption_rate']) ? (float)$data['point_redemption_rate'] : 1.00;
        $minRedeem = isset($data['point_min_redeem']) ? max(1, (int)$data['point_min_redeem']) : 10;
        $lineToken = isset($data['line_oa_token']) ? trim($data['line_oa_token']) : '';
        $lineTarget = isset($data['line_target_id']) ? trim($data['line_target_id']) : '';

        if ($baht <= 0 || $qty <= 0) {
            Response::json(400, "Invalid values for points earning rate.");
            return;
        }

        try {
            // Check if settings exists
            $stmtCount = $this->db->query("SELECT COUNT(*) FROM store_settings");
            if ($stmtCount->fetchColumn() == 0) {
                $stmt = $this->db->prepare("INSERT INTO store_settings (point_earning_baht, point_earning_qty, point_redemption_rate, point_min_redeem, line_oa_token, line_target_id, updated_by) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$baht, $qty, $redeem, $minRedeem, $lineToken, $lineTarget]);
            } else {
                $stmt = $this->db->prepare("UPDATE store_settings SET point_earning_baht = ?, point_earning_qty = ?, point_redemption_rate = ?, point_min_redeem = ?, line_oa_token = ?, line_target_id = ? WHERE setting_id = 1");
                $stmt->execute([$baht, $qty, $redeem, $minRedeem, $lineToken, $lineTarget]);
            }
            Response::json(200, "Settings saved successfully");
        } catch (Exception $e) {
            Response::json(500, "Failed to save settings: " . $e->getMessage());
        }
    }

    public function getGiftRules()
    {
        try {
            $stmt = $this->db->query("SELECT * FROM gift_rules ORDER BY min_spend ASC");
            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Response::json(200, "Success", $rules);
        } catch (Exception $e) {
            Response::json(500, "Error fetching gift rules: " . $e->getMessage());
        }
    }

    public function saveGiftRule()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['min_spend']) || empty($data['gift_name'])) {
            Response::json(400, "Missing min_spend or gift_name");
            return;
        }

        $min_spend = (float)$data['min_spend'];
        $gift_name = trim($data['gift_name']);
        $gift_qty = isset($data['gift_qty']) ? (int)$data['gift_qty'] : 1;
        $rule_id = isset($data['rule_id']) ? (int)$data['rule_id'] : null;

        if ($min_spend < 0) {
            Response::json(400, "Minimum spend cannot be negative.");
            return;
        }

        try {
            // Check uniqueness of min_spend if new, or if min_spend changed for existing
            if ($rule_id) {
                $stmtCheck = $this->db->prepare("SELECT COUNT(*) FROM gift_rules WHERE min_spend = ? AND rule_id != ?");
                $stmtCheck->execute([$min_spend, $rule_id]);
            } else {
                $stmtCheck = $this->db->prepare("SELECT COUNT(*) FROM gift_rules WHERE min_spend = ?");
                $stmtCheck->execute([$min_spend]);
            }

            if ($stmtCheck->fetchColumn() > 0) {
                Response::json(400, "A free gift level for this minimum spend (" . number_format($min_spend, 2) . " Baht) already exists.");
                return;
            }

            if ($rule_id) {
                $stmt = $this->db->prepare("UPDATE gift_rules SET min_spend = ?, gift_name = ?, gift_qty = ? WHERE rule_id = ?");
                $stmt->execute([$min_spend, $gift_name, $gift_qty, $rule_id]);
                Response::json(200, "Gift rule updated successfully");
            } else {
                $stmt = $this->db->prepare("INSERT INTO gift_rules (min_spend, gift_name, gift_qty) VALUES (?, ?, ?)");
                $stmt->execute([$min_spend, $gift_name, $gift_qty]);
                Response::json(201, "Gift rule created successfully", ["rule_id" => $this->db->lastInsertId()]);
            }
        } catch (Exception $e) {
            Response::json(500, "Failed to save gift rule: " . $e->getMessage());
        }
    }

    public function deleteGiftRule()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $rule_id = isset($data['rule_id']) ? (int)$data['rule_id'] : null;

        if (!$rule_id) {
            Response::json(400, "Missing rule_id");
            return;
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM gift_rules WHERE rule_id = ?");
            $stmt->execute([$rule_id]);
            Response::json(200, "Gift rule deleted successfully");
        } catch (Exception $e) {
            Response::json(500, "Failed to delete gift rule: " . $e->getMessage());
        }
    }
}
