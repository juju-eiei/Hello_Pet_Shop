<?php
require_once __DIR__ . '/../config/database.php';

class PromotionBanner {
    private $db;
    private $table = 'promotion_banners';

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->ensureTableExists();
    }

    private function ensureTableExists() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            image_url VARCHAR(255) NOT NULL,
            link_url VARCHAR(255) NULL,
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $this->db->exec($sql);

        // Seed default banners if empty
        $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->table}");
        if ($stmt->fetchColumn() == 0) {
            $seeds = [
                ['title' => 'โปรโมชั่น New Arrivals สินค้าใหม่ประจำเดือน', 'image_url' => '/images/promotions/promo1.png', 'link_url' => '', 'display_order' => 1, 'is_active' => 1],
                ['title' => 'ส่วนลดพิเศษสูงสุด 30% สำหรับอาหารสัตว์เลี้ยง', 'image_url' => '/images/promotions/promo2.png', 'link_url' => '', 'display_order' => 2, 'is_active' => 1],
                ['title' => 'โปรโมชั่นของแถมพรีเมียมต้อนรับสมาชิกใหม่', 'image_url' => '/images/promotions/promo3.png', 'link_url' => '', 'display_order' => 3, 'is_active' => 1]
            ];
            $insert = $this->db->prepare("INSERT INTO {$this->table} (title, image_url, link_url, display_order, is_active) VALUES (:title, :image_url, :link_url, :display_order, :is_active)");
            foreach ($seeds as $s) {
                $insert->execute($s);
            }
        }
    }

    public function getAll($onlyActive = false) {
        $sql = "SELECT * FROM {$this->table}";
        if ($onlyActive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY display_order ASC, id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO {$this->table} (title, image_url, link_url, display_order, is_active) 
                VALUES (:title, :image_url, :link_url, :display_order, :is_active)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':image_url' => $data['image_url'],
            ':link_url' => $data['link_url'] ?? null,
            ':display_order' => $data['display_order'] ?? 0,
            ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['title'])) {
            $fields[] = "title = :title";
            $params[':title'] = $data['title'];
        }
        if (isset($data['image_url'])) {
            $fields[] = "image_url = :image_url";
            $params[':image_url'] = $data['image_url'];
        }
        if (array_key_exists('link_url', $data)) {
            $fields[] = "link_url = :link_url";
            $params[':link_url'] = $data['link_url'];
        }
        if (isset($data['display_order'])) {
            $fields[] = "display_order = :display_order";
            $params[':display_order'] = (int)$data['display_order'];
        }
        if (isset($data['is_active'])) {
            $fields[] = "is_active = :is_active";
            $params[':is_active'] = (int)$data['is_active'];
        }

        if (empty($fields)) return true;

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function toggleStatus($id) {
        $sql = "UPDATE {$this->table} SET is_active = NOT is_active WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
?>
