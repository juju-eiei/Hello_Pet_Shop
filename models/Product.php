<?php
class Product {
    private $conn;
    private $table = 'products';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($keyword = "", $filter = "all") {
        $query = "SELECT p.*, c.category_name, CONCAT(e.first_name, ' ', e.last_name) AS creator_name,
                  (SELECT MIN(expiry_date) FROM product_lots WHERE product_id = p.product_id AND quantity > 0 AND expiry_date IS NOT NULL) AS min_lot_expiry
                  FROM " . $this->table . " p 
                  LEFT JOIN product_categories c ON p.category_id = c.category_id 
                  LEFT JOIN employees e ON p.created_by = e.employee_id ";
        
        $conditions = [];
        $params = [];

        $keyword = trim($keyword);
        if (!empty($keyword)) {
            $conditions[] = "p.product_name LIKE :keyword";
            $params[':keyword'] = "%{$keyword}%";
        }

        if ($filter === "low_stock") {
            $conditions[] = "p.stock_qty > 0 AND p.stock_qty <= 5";
        } elseif ($filter === "out_of_stock") {
            $conditions[] = "p.stock_qty = 0";
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY 
                    CASE 
                        WHEN p.stock_qty <= 0 THEN 1
                        WHEN (p.min_stock_level IS NOT NULL AND p.min_stock_level > 0 AND p.stock_qty <= p.min_stock_level) OR ((p.min_stock_level IS NULL OR p.min_stock_level = 0) AND p.stock_qty <= 5) THEN 2
                        ELSE 3
                    END ASC,
                    p.product_id DESC";
        
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

    public function getById($id) {
        $query = "SELECT p.*, c.category_name, CONCAT(e.first_name, ' ', e.last_name) AS creator_name 
                  FROM " . $this->table . " p 
                  LEFT JOIN product_categories c ON p.category_id = c.category_id 
                  LEFT JOIN employees e ON p.created_by = e.employee_id 
                  WHERE p.product_id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " SET 
                  category_id = :category_id,
                  product_name = :name,
                  description = :description,
                  selling_price = :price,
                  cost_price = :cost_price,
                  stock_qty = :stock_quantity,
                  barcode = :barcode,
                  weight = :weight,
                  weight_value = :weight_value,
                  weight_unit = :weight_unit,
                  image_url = :image_url,
                  is_active = :status
                  WHERE product_id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $status = ($data['status'] === 'active' || $data['status'] == 1) ? 1 : 0;

        $stmt->bindParam(':category_id', $data['category_id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':cost_price', $data['cost_price']);
        $stmt->bindParam(':stock_quantity', $data['stock_quantity']);
        $stmt->bindParam(':barcode', $data['barcode']);
        $stmt->bindParam(':weight', $data['weight']);
        $stmt->bindParam(':weight_value', $data['weight_value']);
        $stmt->bindParam(':weight_unit', $data['weight_unit']);
        $stmt->bindParam(':image_url', $data['image_url']);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    public function delete($id) {
        $id = (int)$id;
        $inTransaction = $this->conn->inTransaction();
        if (!$inTransaction) {
            $this->conn->beginTransaction();
        }

        try {
            // Find all orders containing this product
            $stmtOrders = $this->conn->prepare("SELECT DISTINCT order_id FROM order_details WHERE product_id = :id");
            $stmtOrders->execute([':id' => $id]);
            $orderIds = $stmtOrders->fetchAll(PDO::FETCH_COLUMN);

            foreach ($orderIds as $orderId) {
                // Check if this order contains only this product or others
                $stmtCount = $this->conn->prepare("SELECT COUNT(*) FROM order_details WHERE order_id = :order_id AND product_id != :id");
                $stmtCount->execute([':order_id' => $orderId, ':id' => $id]);
                $otherCount = (int)$stmtCount->fetchColumn();

                if ($otherCount === 0) {
                    // This order only had this product, delete the whole order and related records
                    $this->conn->prepare("DELETE FROM reward_point_logs WHERE order_id = :order_id")->execute([':order_id' => $orderId]);
                    $this->conn->prepare("DELETE FROM deliveries WHERE order_id = :order_id")->execute([':order_id' => $orderId]);
                    $this->conn->prepare("DELETE FROM payments WHERE order_id = :order_id")->execute([':order_id' => $orderId]);
                    $this->conn->prepare("DELETE FROM financial_transactions WHERE reference_type = 'order' AND reference_id = :order_id")->execute([':order_id' => $orderId]);
                    $this->conn->prepare("DELETE FROM order_details WHERE order_id = :order_id")->execute([':order_id' => $orderId]);
                    $this->conn->prepare("DELETE FROM orders WHERE order_id = :order_id")->execute([':order_id' => $orderId]);
                } else {
                    // Delete just this product's line item from order_details
                    $this->conn->prepare("DELETE FROM order_details WHERE order_id = :order_id AND product_id = :id")->execute([':order_id' => $orderId, ':id' => $id]);
                    // Recalculate order subtotal and net_total
                    $stmtSum = $this->conn->prepare("SELECT COALESCE(SUM(quantity * unit_price), 0) FROM order_details WHERE order_id = :order_id");
                    $stmtSum->execute([':order_id' => $orderId]);
                    $newSubtotal = (float)$stmtSum->fetchColumn();
                    $this->conn->prepare("UPDATE orders SET subtotal = :subtotal, net_total = GREATEST(0, :subtotal + shipping_fee - discount_amount) WHERE order_id = :order_id")
                        ->execute([':subtotal' => $newSubtotal, ':order_id' => $orderId]);
                }
            }

            // Delete from dependent product tables
            $this->conn->prepare("DELETE FROM order_details WHERE product_id = :id")->execute([':id' => $id]);
            $this->conn->prepare("DELETE FROM cart_items WHERE product_id = :id")->execute([':id' => $id]);
            $this->conn->prepare("DELETE FROM inventory_logs WHERE product_id = :id")->execute([':id' => $id]);
            $this->conn->prepare("DELETE FROM product_lots WHERE product_id = :id")->execute([':id' => $id]);
            $this->conn->prepare("DELETE FROM restock_details WHERE product_id = :id")->execute([':id' => $id]);

            // Delete product
            $stmt = $this->conn->prepare("DELETE FROM " . $this->table . " WHERE product_id = :id");
            $res = $stmt->execute([':id' => $id]);

            if (!$inTransaction) {
                $this->conn->commit();
            }
            return $res;
        } catch (Exception $e) {
            if (!$inTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                 (category_id, product_name, description, cost_price, selling_price, stock_qty, barcode, weight, weight_value, weight_unit, image_url, is_active, created_by) 
                 VALUES (:category_id, :name, :description, :cost_price, :price, :stock_quantity, :barcode, :weight, :weight_value, :weight_unit, :image_url, :status, :created_by)";
        
        $stmt = $this->conn->prepare($query);
        
        $status = ($data['status'] === 'active' || $data['status'] == 1) ? 1 : 0;

        $stmt->bindParam(':category_id', $data['category_id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':cost_price', $data['cost_price']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':stock_quantity', $data['stock_quantity']);
        $stmt->bindParam(':barcode', $data['barcode']);
        $stmt->bindParam(':weight', $data['weight']);
        $stmt->bindParam(':weight_value', $data['weight_value']);
        $stmt->bindParam(':weight_unit', $data['weight_unit']);
        $stmt->bindParam(':image_url', $data['image_url']);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':created_by', $data['created_by']);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function updateBarcode($id, $barcode) {
        $query = "UPDATE " . $this->table . " SET barcode = :barcode WHERE product_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':barcode', $barcode !== '' ? $barcode : null);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
