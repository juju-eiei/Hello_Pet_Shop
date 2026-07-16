<?php
class RestockOrder {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($keyword = '') {
        $query = "SELECT r.*, 
                  CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                  CONCAT(e2.first_name, ' ', e2.last_name) as receiver_name,
                  (SELECT COUNT(*) FROM restock_details rd WHERE rd.restock_id = r.restock_id) as total_items
                  FROM restock_orders r
                  LEFT JOIN employees e ON r.employee_id = e.employee_id
                  LEFT JOIN employees e2 ON r.received_by = e2.employee_id";
        
        if (!empty($keyword)) {
            $query .= " WHERE r.supplier_name LIKE :keyword 
                       OR e.first_name LIKE :keyword 
                       OR e.last_name LIKE :keyword 
                       OR e2.first_name LIKE :keyword 
                       OR e2.last_name LIKE :keyword";
        }
        
        $query .= " ORDER BY r.restock_id DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!empty($keyword)) {
            $stmt->bindValue(':keyword', "%{$keyword}%");
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        // Get order
        $query = "SELECT r.*, 
                  CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                  CONCAT(e2.first_name, ' ', e2.last_name) as receiver_name
                  FROM restock_orders r
                  LEFT JOIN employees e ON r.employee_id = e.employee_id
                  LEFT JOIN employees e2 ON r.received_by = e2.employee_id
                  WHERE r.restock_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) return null;

        // Get details
        $queryDetails = "SELECT rd.*, p.product_name, p.barcode
                         FROM restock_details rd
                         JOIN products p ON rd.product_id = p.product_id
                         WHERE rd.restock_id = :id";
        $stmtDetails = $this->conn->prepare($queryDetails);
        $stmtDetails->execute([':id' => $id]);
        $order['items'] = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);
        
        return $order;
    }

    public function create($data) {
        try {
            $this->conn->beginTransaction();

            $employee_id = $data['employee_id'] ?? 1; // Fallback to 1 (Super Admin) if not provided
            $supplier_name = $data['supplier_name'] ?? 'General Supplier';
            $status = 2; // Default to Completed
            if (isset($data['status'])) {
                $s = strtolower(trim($data['status']));
                if ($s === 'pending' || $s === '1') {
                    $status = 1;
                } else if ($s === 'received' || $s === 'completed' || $s === '2') {
                    $status = 2;
                }
            }

            // 1. Calculate total cost
            $total_cost = 0;
            foreach ($data['items'] as $item) {
                $total_cost += $item['quantity'] * $item['unit_cost'];
            }

            // 2. Insert into restock_orders
            $queryOrder = "INSERT INTO restock_orders (employee_id, supplier_name, total_cost, status) 
                           VALUES (:employee_id, :supplier_name, :total_cost, :status)";
            $stmtOrder = $this->conn->prepare($queryOrder);
            $stmtOrder->execute([
                ':employee_id' => $employee_id,
                ':supplier_name' => $supplier_name,
                ':total_cost' => $total_cost,
                ':status' => $status
            ]);
            $restock_id = $this->conn->lastInsertId();

            // Prepared statements for details, stock, and logs
            $stmtDetail = $this->conn->prepare("INSERT INTO restock_details (restock_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)");
            $stmtUpdateStock = $this->conn->prepare("UPDATE products SET stock_qty = stock_qty + ?, cost_price = ? WHERE product_id = ?");
            $stmtLog = $this->conn->prepare("INSERT INTO inventory_logs (product_id, employee_id, reference_id, quantity, movement_type, unit_cost) VALUES (?, ?, ?, ?, 1, ?)");
            $stmtGetProduct = $this->conn->prepare("SELECT stock_qty, cost_price FROM products WHERE product_id = ? FOR UPDATE");

            // 3. Insert details and update stock
            foreach ($data['items'] as $item) {
                $qty = (int)$item['quantity'];
                $cost = (float)$item['unit_cost'];
                $p_id = (int)$item['product_id'];

                // Insert detail row
                $stmtDetail->execute([$restock_id, $p_id, $qty, $cost]);

                if ($status === 2) {
                    // Fetch current stock and cost for WAC calculation
                    $stmtGetProduct->execute([$p_id]);
                    $product = $stmtGetProduct->fetch(PDO::FETCH_ASSOC);
                    $currentQty = $product ? (int)$product['stock_qty'] : 0;
                    $currentCost = $product ? (float)$product['cost_price'] : 0.0;

                    // Weighted Average Cost (WAC) formula
                    $newQty = $currentQty + $qty;
                    if ($currentQty > 0 && $newQty > 0) {
                        $newCost = (($currentQty * $currentCost) + ($qty * $cost)) / $newQty;
                    } else {
                        $newCost = $cost;
                    }

                    // Update products stock and average cost price
                    $stmtUpdateStock->execute([$qty, $newCost, $p_id]);

                    // Log movement
                    $stmtLog->execute([$p_id, $employee_id, $restock_id, $qty, $cost]);
                }
            }

            $this->conn->commit();
            return $restock_id;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Restock Create Error: " . $e->getMessage());
            return false;
        }
    }

    public function receive($id, $employee_id) {
        try {
            $this->conn->beginTransaction();

            // 1. Fetch order details to check if it exists and is pending
            $queryOrder = "SELECT * FROM restock_orders WHERE restock_id = :id FOR UPDATE";
            $stmtOrder = $this->conn->prepare($queryOrder);
            $stmtOrder->execute([':id' => $id]);
            $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception("Restock order not found");
            }
            if ((int)$order['status'] !== 1) {
                throw new Exception("Restock order is not in Pending status");
            }

            // 2. Fetch items for this order
            $queryDetails = "SELECT * FROM restock_details WHERE restock_id = :id";
            $stmtDetails = $this->conn->prepare($queryDetails);
            $stmtDetails->execute([':id' => $id]);
            $items = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

            $stmtUpdateStock = $this->conn->prepare("UPDATE products SET stock_qty = stock_qty + ?, cost_price = ? WHERE product_id = ?");
            $stmtLog = $this->conn->prepare("INSERT INTO inventory_logs (product_id, employee_id, reference_id, quantity, movement_type, unit_cost) VALUES (?, ?, ?, ?, 1, ?)");
            $stmtGetProduct = $this->conn->prepare("SELECT stock_qty, cost_price FROM products WHERE product_id = ? FOR UPDATE");

            // 3. Update stock, calculate WAC, and log movement for each item
            foreach ($items as $item) {
                $qty = (int)$item['quantity'];
                $cost = (float)$item['unit_cost'];
                $p_id = (int)$item['product_id'];

                // Fetch current stock and cost for WAC calculation
                $stmtGetProduct->execute([$p_id]);
                $product = $stmtGetProduct->fetch(PDO::FETCH_ASSOC);
                $currentQty = $product ? (int)$product['stock_qty'] : 0;
                $currentCost = $product ? (float)$product['cost_price'] : 0.0;

                // Weighted Average Cost (WAC) formula
                $newQty = $currentQty + $qty;
                if ($currentQty > 0 && $newQty > 0) {
                    $newCost = (($currentQty * $currentCost) + ($qty * $cost)) / $newQty;
                } else {
                    $newCost = $cost;
                }

                // Update products stock and average cost price
                $stmtUpdateStock->execute([$qty, $newCost, $p_id]);

                // Log movement
                $stmtLog->execute([$p_id, $employee_id, $id, $qty, $cost]);
            }

            // 4. Update order status to Received (2) and set received_by
            $queryUpdateOrder = "UPDATE restock_orders SET status = 2, received_by = :received_by WHERE restock_id = :id";
            $stmtUpdateOrder = $this->conn->prepare($queryUpdateOrder);
            $stmtUpdateOrder->execute([
                ':received_by' => $employee_id,
                ':id' => $id
            ]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Restock Receive Error: " . $e->getMessage());
            return false;
        }
    }
}
