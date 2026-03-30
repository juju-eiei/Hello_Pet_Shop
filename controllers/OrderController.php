<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

class OrderController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function createOnlineOrder() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $this->db->beginTransaction();
        
        try {
            if(empty($data['customer_id']) || empty($data['address_id']) || empty($data['items'])) {
                throw new Exception("Missing required order information");
            }

            // 0. validate address ownership
            $stmtAddress = $this->db->prepare("SELECT id FROM addresses WHERE id = ? AND customer_id = ?");
            $stmtAddress->execute([$data['address_id'], $data['customer_id']]);
            if (!$stmtAddress->fetch()) {
                throw new Exception("Invalid address or address does not belong to the customer");
            }
            
            $subtotal = 0;
            $order_details = [];
            
            foreach ($data['items'] as $item) {
                // 2. check stock
                $stmt = $this->db->prepare("SELECT price, cost_price, stock_quantity FROM products WHERE id = ? FOR UPDATE");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product || $product['stock_quantity'] < $item['quantity']) {
                    throw new Exception("Product ID {$item['product_id']} is out of stock or insufficient quantity");
                }
                
                $total_price = $product['price'] * $item['quantity'];
                $subtotal += $total_price;
                
                $order_details[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $product['cost_price'],
                    'unit_price' => $product['price'],
                    'total_price' => $total_price
                ];
            }
            
            $discount = 0;
            if (!empty($data['promo_id'])) {
                $stmtPromo = $this->db->prepare("SELECT discount_type, discount_value FROM promotions WHERE id = ? AND is_active = 1 AND start_date <= CURDATE() AND end_date >= CURDATE()");
                $stmtPromo->execute([$data['promo_id']]);
                $promo = $stmtPromo->fetch(PDO::FETCH_ASSOC);
                
                if ($promo) {
                    if ($promo['discount_type'] === 'percent') {
                        $discount = ($subtotal * $promo['discount_value']) / 100;
                    } else {
                        $discount = $promo['discount_value'];
                    }
                }
            }
            
            // Cap discount
            if ($discount > $subtotal) {
                $discount = $subtotal;
            }
            
            $shipping_fee = $data['shipping_fee'] ?? 0;
            $net_total = ($subtotal - $discount) + $shipping_fee;
            if ($net_total < 0) $net_total = 0;
            
            // 3. insert into orders
            $payment_method = $data['payment_method'] ?? 'transfer';
            $stmtOrder = $this->db->prepare("INSERT INTO orders (customer_id, address_id, promo_id, subtotal, discount, shipping_fee, net_total, status, payment_method, order_type) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'online')");
            $promo_id = !empty($data['promo_id']) ? $data['promo_id'] : null;
            $stmtOrder->execute([$data['customer_id'], $data['address_id'], $promo_id, $subtotal, $discount, $shipping_fee, $net_total, $payment_method]);
            $order_id = $this->db->lastInsertId();
            
            $stmtDetail = $this->db->prepare("INSERT INTO order_details (order_id, product_id, quantity, unit_cost, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtUpdateStock = $this->db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?");
            $stmtLog = $this->db->prepare("INSERT INTO inventory_logs (product_id, reference_id, quantity_change, type, reason) VALUES (?, ?, ?, 'sale', ?)");
            
            foreach ($order_details as $detail) {
                // 4. order_details
                $stmtDetail->execute([$order_id, $detail['product_id'], $detail['quantity'], $detail['unit_cost'], $detail['unit_price'], $detail['total_price']]);
                
                // 5. Update stock with safety condition
                $stmtUpdateStock->execute([$detail['quantity'], $detail['product_id'], $detail['quantity']]);
                if ($stmtUpdateStock->rowCount() === 0) {
                    throw new Exception("Safety Error: Cannot deduct stock for Product ID {$detail['product_id']}");
                }
                
                // 6. log
                $reason = "Sale from Order #" . $order_id;
                $stmtLog->execute([$detail['product_id'], $order_id, -$detail['quantity'], $reason]); 
            }
            
            $this->db->commit();
            
            Response::json(201, "Order created successfully", ["order_id" => $order_id, "net_total" => $net_total]);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(400, "Failed to create order", ["error" => $e->getMessage()]);
        }
    }
}
?>
