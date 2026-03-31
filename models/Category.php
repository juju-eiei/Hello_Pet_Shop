<?php
class Category {
    private $conn;
    private $table = 'product_categories';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY category_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function create($name) {
        $query = "INSERT INTO " . $this->table . " (category_name) VALUES (:name)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
}
?>
