<?php
require_once __DIR__ . '/../config/database.php';

class PaymentSetting {
    private $db;
    private $table = 'shop_payment_settings';

    public function __construct($db = null) {
        if ($db) {
            $this->db = $db;
        } else {
            $database = new Database();
            $this->db = $database->getConnection();
        }
        $this->ensureTableExists();
    }

    private function ensureTableExists() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bank_name VARCHAR(100) NOT NULL DEFAULT 'ธนาคารกสิกรไทย (KBank)',
            account_number VARCHAR(50) NOT NULL DEFAULT 'xxx-x-x1507-x',
            account_name VARCHAR(150) NOT NULL DEFAULT 'น.ส. จุฬาลักษณ์ วงค์ม่าน',
            promptpay_id VARCHAR(50) DEFAULT '',
            qr_image_url VARCHAR(255) NOT NULL DEFAULT '/image/promptpay_qr.png',
            instructions TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $this->db->exec($sql);

        // Check if default row exists
        $check = $this->db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
        if ($check == 0) {
            $insert = "INSERT INTO {$this->table} (bank_name, account_number, account_name, promptpay_id, qr_image_url, instructions) 
                       VALUES ('ธนาคารกสิกรไทย (KBank)', 'xxx-x-x1507-x', 'น.ส. จุฬาลักษณ์ วงค์ม่าน', '081-xxx-xxxx', '/image/promptpay_qr.png', 'กรุณาโอนเงินตามยอดที่ระบุ และแนบสลิปเพื่อความรวดเร็วในการตรวจสอบ')";
            $this->db->exec($insert);
        }
    }

    public function getSettings() {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateSettings($data) {
        $existing = $this->getSettings();
        if (!$existing) {
            $this->ensureTableExists();
            $existing = $this->getSettings();
        }

        $id = $existing['id'] ?? 1;

        $query = "UPDATE {$this->table} SET 
                    bank_name = :bank_name,
                    account_number = :account_number,
                    account_name = :account_name,
                    promptpay_id = :promptpay_id,
                    instructions = :instructions";

        if (!empty($data['qr_image_url'])) {
            $query .= ", qr_image_url = :qr_image_url";
        }

        $query .= " WHERE id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':bank_name', $data['bank_name']);
        $stmt->bindParam(':account_number', $data['account_number']);
        $stmt->bindParam(':account_name', $data['account_name']);
        $stmt->bindParam(':promptpay_id', $data['promptpay_id']);
        $stmt->bindParam(':instructions', $data['instructions']);
        $stmt->bindParam(':id', $id);

        if (!empty($data['qr_image_url'])) {
            $stmt->bindParam(':qr_image_url', $data['qr_image_url']);
        }

        return $stmt->execute();
    }
}
