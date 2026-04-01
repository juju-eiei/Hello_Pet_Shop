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

    public function index() {
        try {
            $query = "SELECT order_id as id, 
                             order_date as date, 
                             CONCAT('ORD-', DATE_FORMAT(order_date, '%Y'), '-', LPAD(order_id, 3, '0')) as number, 
                             net_total as amount, 
                             status 
                      FROM orders 
                      ORDER BY order_date DESC";
            $stmt = $this->db->query($query);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $statusMap = [
                1 => 'Pending',
                2 => 'Processing',
                3 => 'In Transit',
                4 => 'Completed',
                5 => 'Cancelled'
            ];

            foreach ($orders as &$order) {
                $order['amount'] = (float)$order['amount'];
                $sId = (int)$order['status'];
                $order['status'] = isset($statusMap[$sId]) ? $statusMap[$sId] : 'Pending';
                $order['date'] = date('Y-m-d', strtotime($order['date']));
            }

            Response::json(200, "Orders retrieved successfully", $orders);
        } catch (Exception $e) {
            Response::json(500, "Failed to retrieve orders", ["error" => $e->getMessage()]);
        }
    }

    public function show() {
        try {
            if (!isset($_GET['id'])) {
                Response::json(400, "Order ID is required");
                return;
            }
            $orderId = (int)$_GET['id'];

            $qOrder = "SELECT o.order_id, o.order_date, CONCAT('ORD-', DATE_FORMAT(o.order_date, '%Y'), '-', LPAD(o.order_id, 3, '0')) as number, o.status, o.subtotal, o.shipping_fee, o.discount_amount, o.net_total, 
                              c.first_name, c.last_name, c.phone, u.email,
                              a.address_detail, a.province, a.zip_code
                       FROM orders o
                       JOIN customers c ON o.customer_id = c.customer_id
                       JOIN users u ON c.user_id = u.user_id
                       LEFT JOIN addresses a ON o.address_id = a.address_id
                       WHERE o.order_id = ?";
            $stmt = $this->db->prepare($qOrder);
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                Response::json(404, "Order not found");
                return;
            }

            $qItems = "SELECT od.quantity as qty, od.unit_price as price, p.product_name as name, p.image_url as image
                       FROM order_details od
                       JOIN products p ON od.product_id = p.product_id
                       WHERE od.order_id = ?";
            $stmtI = $this->db->prepare($qItems);
            $stmtI->execute([$orderId]);
            $items = $stmtI->fetchAll(PDO::FETCH_ASSOC);

            // Fetch delivery details
            $tracking_number = null; 
            $company_id = null;
            try {
                $qDel = "SELECT tracking_number, company_id FROM deliveries WHERE order_id = ?";
                $stmtD = $this->db->prepare($qDel);
                $stmtD->execute([$orderId]);
                $d = $stmtD->fetch(PDO::FETCH_ASSOC);
                if ($d) {
                    $tracking_number = $d['tracking_number'];
                    $company_id = $d['company_id'];
                }
            } catch(Exception $ex) {}

            $statusMap = [1 => 'Pending', 2 => 'Processing', 3 => 'In Transit', 4 => 'Completed', 5 => 'Cancelled'];
            $sId = (int)$order['status'];
            $statusStr = isset($statusMap[$sId]) ? $statusMap[$sId] : 'Pending';

            $data = [
                'id' => $order['order_id'],
                'date' => date('Y-m-d H:i', strtotime($order['order_date'])),
                'number' => $order['number'],
                'status' => $statusStr,
                'tracking_number' => $tracking_number,
                'company_id' => $company_id,
                'customer' => [
                    'name' => trim($order['first_name'] . ' ' . $order['last_name']),
                    'email' => $order['email'] ? $order['email'] : '-',
                    'phone' => $order['phone'] ? $order['phone'] : '-',
                    'address' => trim($order['address_detail'] . ' ' . $order['province'] . ' ' . $order['zip_code'])
                ],
                'items' => $items,
                'summary' => [
                    'subtotal' => (float)$order['subtotal'],
                    'shipping' => (float)$order['shipping_fee'],
                    'discount' => (float)$order['discount_amount'],
                    'total' => (float)$order['net_total']
                ]
            ];

            Response::json(200, "Order loaded", $data);
        } catch (Exception $e) {
            Response::json(500, "Error loading order", ["error" => $e->getMessage()]);
        }
    }

    public function updateStatus() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['order_id']) || !isset($data['status'])) {
                Response::json(400, "Missing parameters");
                return;
            }

            $orderId = (int)$data['order_id'];
            $statusStr = $data['status'];
            $tracking = isset($data['tracking_number']) ? trim($data['tracking_number']) : null;
            $companyIdReq = isset($data['company_id']) && $data['company_id'] != '' ? (int)$data['company_id'] : null;

            $map = [
                'Pending' => 1,
                'Processing' => 2,
                'In Transit' => 3,
                'Completed' => 4,
                'Cancelled' => 5
            ];

            $sId = isset($map[$statusStr]) ? $map[$statusStr] : 1;

            $stmt = $this->db->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            if ($stmt->execute([$sId, $orderId])) {
                
                // Allow delivery table insertion even if tracking is blank
                if ($sId == 3 || $sId == 4) {
                    $cStmt = $this->db->prepare("SELECT delivery_id FROM deliveries WHERE order_id = ?");
                    $cStmt->execute([$orderId]);
                    if ($cStmt->rowCount() > 0) {
                        $updateParams = [];
                        $updateQuery = "UPDATE deliveries SET status = ?";
                        $updateParams[] = ($sId == 4 ? 3 : 2);
                        
                        if ($tracking !== null) {
                            $updateQuery .= ", tracking_number = ?";
                            $updateParams[] = $tracking;
                        }
                        if ($companyIdReq !== null) {
                            $updateQuery .= ", company_id = ?";
                            $updateParams[] = $companyIdReq;
                        }
                        
                        $updateQuery .= " WHERE order_id = ?";
                        $updateParams[] = $orderId;
                        
                        $uStmt = $this->db->prepare($updateQuery);
                        $uStmt->execute($updateParams);
                    } else {
                        // Use requested company, or fallback to first company, or 1
                        if ($companyIdReq !== null) {
                            $companyId = $companyIdReq;
                        } else {
                            $chk = $this->db->query("SELECT * FROM delivery_companies LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                            $companyId = $chk ? (isset($chk['company_id']) ? $chk['company_id'] : current($chk)) : 1;
                        }
                        
                        // Insert using company_id
                        $iStmt = $this->db->prepare("INSERT INTO deliveries (order_id, company_id, tracking_number, status) VALUES (?, ?, ?, ?)");
                        $iStmt->execute([$orderId, $companyId, $tracking, $sId == 4 ? 3 : 2]);
                    }
                }

                Response::json(200, "Order status updated");
            } else {
                Response::json(500, "Failed to update status");
            }
        } catch (Exception $e) {
            Response::json(500, "Error updating status", ["error" => $e->getMessage()]);
        }
    }

    public function getDeliveryCompanies() {
        try {
            $stmt = $this->db->query("SELECT * FROM delivery_companies");
            $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Response::json(200, "Success", $companies);
        } catch (Exception $e) {
            Response::json(500, "Error loading companies", ["error" => $e->getMessage()]);
        }
    }
}
?>
