<?php
class Product {
    private $conn;
    private $table = 'products';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($keyword = "", $filter = "all") {
        $query = "SELECT p.*, c.category_name 
                  FROM " . $this->table . " p 
                  LEFT JOIN product_categories c ON p.category_id = c.category_id ";
        
        $conditions = [];
        $params = [];

        if (!empty($keyword)) {
            $conditions[] = "p.product_name LIKE :keyword";
            $params[':keyword'] = "%{$keyword}%";
        }

        if ($filter === "low_stock") {
            $conditions[] = "p.stock_qty > 0 AND p.stock_qty < 5";
        } elseif ($filter === "out_of_stock") {
            $conditions[] = "p.stock_qty = 0";
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY p.product_id DESC";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        
        $stmt->execute();
        return $stmt;
    }

    public function updateStock($id, $newQuantity) {
        $query = "UPDATE " . $this->table . " SET stock_qty = :stock WHERE product_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':stock', $newQuantity);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                 (category_id, product_name, description, cost_price, selling_price, stock_qty, barcode) 
                 VALUES (:category_id, :name, :description, :cost_price, :price, :stock_quantity, :barcode)";
        
        $stmt = $this->conn->prepare($query);
        
        $cost_price = $data['cost_price'] ?? 0;
        $stock_quantity = $data['stock_quantity'] ?? 0;
        $barcode = $data['barcode'] ?? null;
        $description = $data['description'] ?? null;

        $stmt->bindParam(':category_id', $data['category_id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':cost_price', $cost_price);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':stock_quantity', $stock_quantity);
        $stmt->bindParam(':barcode', $barcode);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
}
?>
