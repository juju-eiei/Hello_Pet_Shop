<?php
require_once __DIR__ . '/../config/database.php';

class Promotion {
    private $db;
    private $table = 'promotions';

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function getAll($keyword = '') {
        $sql = "SELECT * FROM " . $this->table;
        if (!empty($keyword)) {
            $sql .= " WHERE promo_name LIKE :keyword";
        }
        $sql .= " ORDER BY promo_id DESC";
        
        $stmt = $this->db->prepare($sql);
        if (!empty($keyword)) {
            $stmt->bindValue(':keyword', '%' . $keyword . '%');
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT * FROM " . $this->table . " WHERE promo_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO " . $this->table . " 
                (promo_name, discount_type, discount_value, start_date, end_date, is_active, employee_id) 
                VALUES (:code, :discount_type, :discount_value, :start_date, :end_date, :is_active, 1)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':code', $data['code']);
        $stmt->bindParam(':discount_type', $data['discount_type']);
        $stmt->bindParam(':discount_value', $data['discount_value']);
        $stmt->bindParam(':start_date', $data['start_date']);
        $stmt->bindParam(':end_date', $data['end_date']);
        $stmt->bindValue(':is_active', $data['is_active'] ?? 1);
        
        return $stmt->execute();
    }

    public function update($id, $data) {
        $sql = "UPDATE " . $this->table . " 
                SET promo_name = :code, discount_type = :discount_type, 
                    discount_value = :discount_value, start_date = :start_date, 
                    end_date = :end_date, is_active = :is_active 
                WHERE promo_id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':code', $data['code']);
        $stmt->bindParam(':discount_type', $data['discount_type']);
        $stmt->bindParam(':discount_value', $data['discount_value']);
        $stmt->bindParam(':start_date', $data['start_date']);
        $stmt->bindParam(':end_date', $data['end_date']);
        $stmt->bindParam(':is_active', $data['is_active']);
        
        return $stmt->execute();
    }

    public function delete($id) {
        $sql = "DELETE FROM " . $this->table . " WHERE promo_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
