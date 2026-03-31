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
            $sql .= " WHERE code LIKE :keyword OR description LIKE :keyword";
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        if (!empty($keyword)) {
            $stmt->bindValue(':keyword', '%' . $keyword . '%');
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT * FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO " . $this->table . " 
                (code, description, discount_type, discount_value, start_date, end_date, is_active) 
                VALUES (:code, :description, :discount_type, :discount_value, :start_date, :end_date, :is_active)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':code', $data['code']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':discount_type', $data['discount_type']);
        $stmt->bindParam(':discount_value', $data['discount_value']);
        $stmt->bindParam(':start_date', $data['start_date']);
        $stmt->bindParam(':end_date', $data['end_date']);
        $stmt->bindValue(':is_active', $data['is_active'] ?? 1);
        
        return $stmt->execute();
    }

    public function update($id, $data) {
        $sql = "UPDATE " . $this->table . " 
                SET code = :code, description = :description, 
                    discount_type = :discount_type, discount_value = :discount_value, 
                    start_date = :start_date, end_date = :end_date, is_active = :is_active 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':code', $data['code']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':discount_type', $data['discount_type']);
        $stmt->bindParam(':discount_value', $data['discount_value']);
        $stmt->bindParam(':start_date', $data['start_date']);
        $stmt->bindParam(':end_date', $data['end_date']);
        $stmt->bindParam(':is_active', $data['is_active']);
        
        return $stmt->execute();
    }

    public function delete($id) {
        $sql = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
