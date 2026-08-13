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
                $stmtPromo = $this->db->prepare("SELECT discount_type, discount_value FROM promotions WHERE promo_id = ? AND is_active = 1 AND start_date <= CURDATE() AND end_date >= CURDATE()");
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

            // Calculate Tiered Gift
            $freeGift = null;
            $stmtGift = $this->db->prepare("SELECT gift_name FROM gift_rules WHERE min_spend <= ? ORDER BY min_spend DESC LIMIT 1");
            $stmtGift->execute([$net_total]);
            $giftRow = $stmtGift->fetch(PDO::FETCH_ASSOC);
            if ($giftRow) {
                $freeGift = $giftRow['gift_name'];
            }
            
            // Calculate reward points first
            $pointsEarned = 0;
            $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
            if ($customerId) {
                $stmtSettings = $this->db->query("SELECT point_earning_baht, point_earning_qty FROM store_settings LIMIT 1");
                $settings = $stmtSettings->fetch(PDO::FETCH_ASSOC);
                $peBaht = $settings && isset($settings['point_earning_baht']) ? (float)$settings['point_earning_baht'] : 100.00;
                $peQty = $settings && isset($settings['point_earning_qty']) ? (int)$settings['point_earning_qty'] : 1;

                if ($peBaht > 0 && $peQty > 0) {
                    $pointsEarned = (int)(floor($net_total / $peBaht) * $peQty);
                }
            }
            
            // 3. insert into orders
            $stmtOrder = $this->db->prepare("INSERT INTO orders (customer_id, address_id, promo_id, subtotal, discount_amount, shipping_fee, net_total, status, order_type, free_gift, points_earned) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?)");
            $promo_id = !empty($data['promo_id']) ? $data['promo_id'] : null;
            $stmtOrder->execute([$data['customer_id'], $data['address_id'], $promo_id, $subtotal, $discount, $shipping_fee, $net_total, $freeGift, $pointsEarned]);
            $order_id = (int)$this->db->lastInsertId();

            // Insert into deliveries table
            $company_id = !empty($data['company_id']) ? (int)$data['company_id'] : (!empty($data['delivery_company_id']) ? (int)$data['delivery_company_id'] : null);
            if (!$company_id) {
                try {
                    $stmtFirstComp = $this->db->query("SELECT company_id FROM delivery_companies ORDER BY company_id ASC LIMIT 1");
                    $firstComp = $stmtFirstComp->fetch(PDO::FETCH_ASSOC);
                    if ($firstComp) {
                        $company_id = (int)$firstComp['company_id'];
                    }
                } catch(Exception $exComp) {}
            }

            if ($company_id) {
                try {
                    $stmtDel = $this->db->prepare("INSERT INTO deliveries (order_id, company_id, tracking_number, status) VALUES (?, ?, ?, 1)");
                    $stmtDel->execute([$order_id, $company_id, $data['tracking_number'] ?? null]);
                } catch(Exception $exDel) {}
            }
            
            $stmtDetail = $this->db->prepare("INSERT INTO order_details (order_id, product_id, quantity, unit_cost, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtUpdateStock = $this->db->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ? AND stock_qty >= ?");
            $stmtLog = $this->db->prepare("INSERT INTO inventory_logs (product_id, employee_id, reference_id, quantity, movement_type, unit_cost) VALUES (?, 1, ?, ?, 2, ?)");
            
            foreach ($order_details as $detail) {
                // 4. order_details
                $stmtDetail->execute([$order_id, $detail['product_id'], $detail['quantity'], $detail['unit_cost'], $detail['unit_price'], $detail['total_price']]);
                
                // 5. Update stock with safety condition
                $stmtUpdateStock->execute([$detail['quantity'], $detail['product_id'], $detail['quantity']]);
                if ($stmtUpdateStock->rowCount() === 0) {
                    throw new Exception("Safety Error: Cannot deduct stock for Product ID {$detail['product_id']}");
                }
                
                // 6. log
                $stmtLog->execute([$detail['product_id'], $order_id, -$detail['quantity'], $detail['unit_cost']]); 
            }
            
            // Award reward points to customer
            if ($customerId && $pointsEarned > 0) {
                // Update points in customers
                $stmtUpdatePoints = $this->db->prepare("UPDATE customers SET points = points + ? WHERE customer_id = ?");
                $stmtUpdatePoints->execute([$pointsEarned, $customerId]);

                // Insert log
                $orderNum = "ORD-" . date('Y') . "-" . str_pad($order_id, 3, '0', STR_PAD_LEFT);
                $stmtLogPoints = $this->db->prepare("INSERT INTO reward_point_logs (customer_id, order_id, points_change, description) VALUES (?, ?, ?, ?)");
                $stmtLogPoints->execute([$customerId, $order_id, $pointsEarned, "ได้รับแต้มสะสมจากรายการสั่งซื้อออนไลน์ #$orderNum"]);
            }
            
            $this->db->commit();
            
            Response::json(201, "Order created successfully", [
                "order_id" => $order_id, 
                "net_total" => $net_total,
                "free_gift" => $freeGift,
                "points_earned" => $pointsEarned
            ]);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(400, "Failed to create order", ["error" => $e->getMessage()]);
        }
    }

    public function createPOSOrder() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['items'])) {
            Response::json(400, "Bad Request: Missing items");
            return;
        }

        $this->db->beginTransaction();

        try {
            // 1. Resolve customer_id
            $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
            if (!$customerId) {
                // Default to the first customer in the DB
                $stmtCust = $this->db->query("SELECT customer_id FROM customers ORDER BY customer_id ASC LIMIT 1");
                $cust = $stmtCust->fetch(PDO::FETCH_ASSOC);
                $customerId = $cust ? (int)$cust['customer_id'] : 1;
            }

            // 2. Resolve employee_id from session or payload, fallback to 1
            $employeeId = 1;
            $userId = $_SESSION['user_id'] ?? null;
            if ($userId) {
                $stmtEmp = $this->db->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
                $stmtEmp->execute([$userId]);
                $emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);
                if ($emp) {
                    $employeeId = (int)$emp['employee_id'];
                }
            } else if (!empty($data['employee_id'])) {
                $employeeId = (int)$data['employee_id'];
            }

            // 3. Resolve address_id (since it is required by DB relations)
            $addressId = null;
            try {
                $stmtAddr = $this->db->prepare("SELECT address_id FROM addresses WHERE customer_id = ? LIMIT 1");
                $stmtAddr->execute([$customerId]);
                $addr = $stmtAddr->fetch(PDO::FETCH_ASSOC);
                
                if ($addr && isset($addr['address_id'])) {
                    $addressId = (int)$addr['address_id'];
                } else {
                    $stmtCustInfo = $this->db->prepare("SELECT first_name, last_name, phone FROM customers WHERE customer_id = ?");
                    $stmtCustInfo->execute([$customerId]);
                    $cInfo = $stmtCustInfo->fetch(PDO::FETCH_ASSOC);
                    $rName = $cInfo ? trim($cInfo['first_name'] . ' ' . $cInfo['last_name']) : 'Walk-in Customer';
                    $rPhone = ($cInfo && !empty($cInfo['phone'])) ? $cInfo['phone'] : '0000000000';

                    $stmtInsertAddr = $this->db->prepare("INSERT INTO addresses (customer_id, recipient_name, phone, address_detail, province, zip_code, is_default) VALUES (?, ?, ?, 'POS Store', 'Bangkok', '10400', 1)");
                    $stmtInsertAddr->execute([$customerId, $rName, $rPhone]);
                    $addressId = (int)$this->db->lastInsertId();
                }
            } catch (Exception $eAddrGeneral) {
                $stmtAnyAddr = $this->db->query("SELECT address_id FROM addresses LIMIT 1");
                $anyAddr = $stmtAnyAddr->fetch(PDO::FETCH_ASSOC);
                $addressId = $anyAddr ? (int)$anyAddr['address_id'] : 1;
            }

            $subtotal = 0;
            $order_details = [];

            foreach ($data['items'] as $item) {
                // Check stock
                $stmt = $this->db->prepare("SELECT selling_price as price, cost_price, stock_qty FROM products WHERE product_id = ? FOR UPDATE");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new Exception("Product ID {$item['product_id']} not found");
                }
                
                if ($product['stock_qty'] < $item['quantity']) {
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

            // 4. Calculate discount
            $discount = 0;
            $promoId = !empty($data['promo_id']) ? (int)$data['promo_id'] : null;
            if ($promoId) {
                $stmtPromo = $this->db->prepare("SELECT discount_type, discount_value FROM promotions WHERE promo_id = ? AND is_active = 1 AND start_date <= CURDATE() AND end_date >= CURDATE()");
                $stmtPromo->execute([$promoId]);
                $promo = $stmtPromo->fetch(PDO::FETCH_ASSOC);

                if ($promo) {
                    if ($promo['discount_type'] === 'percent') {
                        $discount = ($subtotal * $promo['discount_value']) / 100;
                    } else {
                        $discount = $promo['discount_value'];
                    }
                }
            }

            if ($discount > $subtotal) {
                $discount = $subtotal;
            }

            $net_total = $subtotal - $discount;
            if ($net_total < 0) $net_total = 0;

            $paymentMethod = $data['payment_method'] ?? 'cash';

            // Calculate Tiered Gift
            $freeGift = null;
            $stmtGift = $this->db->prepare("SELECT gift_name FROM gift_rules WHERE min_spend <= ? ORDER BY min_spend DESC LIMIT 1");
            $stmtGift->execute([$net_total]);
            $giftRow = $stmtGift->fetch(PDO::FETCH_ASSOC);
            if ($giftRow) {
                $freeGift = $giftRow['gift_name'];
            }

            // Calculate reward points first
            $pointsEarned = 0;
            if ($customerId) {
                $stmtSettings = $this->db->query("SELECT point_earning_baht, point_earning_qty FROM store_settings LIMIT 1");
                $settings = $stmtSettings->fetch(PDO::FETCH_ASSOC);
                $peBaht = $settings && isset($settings['point_earning_baht']) ? (float)$settings['point_earning_baht'] : 100.00;
                $peQty = $settings && isset($settings['point_earning_qty']) ? (int)$settings['point_earning_qty'] : 1;

                if ($peBaht > 0 && $peQty > 0) {
                    $pointsEarned = (int)(floor($net_total / $peBaht) * $peQty);
                }
            }

            // 7. Record cash received and change if cash payment
            $cashReceived = isset($data['cash_received']) ? (float)$data['cash_received'] : $net_total;
            $changeAmount = max(0, $cashReceived - $net_total);

            // 5. Insert into orders (status = 4 for Completed, order_type = 2 for POS)
            $stmtOrder = $this->db->prepare("INSERT INTO orders (customer_id, employee_id, address_id, promo_id, subtotal, discount_amount, shipping_fee, net_total, status, order_type, free_gift, points_earned, cash_received) VALUES (?, ?, ?, ?, ?, ?, 0, ?, 4, 2, ?, ?, ?)");
            $stmtOrder->execute([$customerId, $employeeId, $addressId, $promoId ? $promoId : null, $subtotal, $discount, $net_total, $freeGift, $pointsEarned, $cashReceived]);
            $orderId = (int)$this->db->lastInsertId();

            // Insert into payments table
            try {
                $paymentMethodMap = [
                    'transfer' => 1,
                    'cash' => 2,
                    'credit_card' => 3
                ];
                $payMethodInt = $paymentMethodMap[$paymentMethod] ?? 2;
                $stmtPayment = $this->db->prepare("INSERT INTO payments (order_id, payment_method, amount, status) VALUES (?, ?, ?, 2)");
                $stmtPayment->execute([$orderId, $payMethodInt, $net_total]);
            } catch (Exception $exPay) {
                // Ignore payment insert failure if any constraint
            }

            // 6. Insert order details, deduct stock, log inventory
            $stmtDetail = $this->db->prepare("INSERT INTO order_details (order_id, product_id, quantity, unit_price, unit_cost) VALUES (?, ?, ?, ?, ?)");
            $stmtUpdateStock = $this->db->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ? AND stock_qty >= ?");
            $stmtLog = $this->db->prepare("INSERT INTO inventory_logs (product_id, employee_id, reference_id, quantity, movement_type, unit_cost) VALUES (?, ?, ?, ?, 2, ?)");

            foreach ($order_details as $detail) {
                $stmtDetail->execute([$orderId, $detail['product_id'], $detail['quantity'], $detail['unit_price'], $detail['unit_cost']]);

                $stmtUpdateStock->execute([$detail['quantity'], $detail['product_id'], $detail['quantity']]);
                if ($stmtUpdateStock->rowCount() === 0) {
                    throw new Exception("Safety Error: Cannot deduct stock for Product ID {$detail['product_id']}");
                }

                $stmtLog->execute([$detail['product_id'], $employeeId, $orderId, -$detail['quantity'], $detail['unit_cost']]);
            }

            // Award reward points to customer
            if ($customerId && $pointsEarned > 0) {
                // Update points in customers
                $stmtUpdatePoints = $this->db->prepare("UPDATE customers SET points = points + ? WHERE customer_id = ?");
                $stmtUpdatePoints->execute([$pointsEarned, $customerId]);

                // Insert log
                $orderNum = "ORD-POS-" . date('Y') . "-" . str_pad($orderId, 3, '0', STR_PAD_LEFT);
                $stmtLogPoints = $this->db->prepare("INSERT INTO reward_point_logs (customer_id, order_id, points_change, description) VALUES (?, ?, ?, ?)");
                $stmtLogPoints->execute([$customerId, $orderId, $pointsEarned, "ได้รับแต้มสะสมจากรายการสั่งซื้อ POS #$orderNum"]);
            }

            // Retrieve employee name for cashier on receipt
            $stmtEmpName = $this->db->prepare("SELECT first_name, last_name FROM employees WHERE employee_id = ?");
            $stmtEmpName->execute([$employeeId]);
            $empName = $stmtEmpName->fetch(PDO::FETCH_ASSOC);
            $cashierName = $empName ? trim($empName['first_name'] . ' ' . $empName['last_name']) : 'System';

            // Retrieve customer name for receipt
            $stmtCustName = $this->db->prepare("SELECT first_name, last_name FROM customers WHERE customer_id = ?");
            $stmtCustName->execute([$customerId]);
            $custName = $stmtCustName->fetch(PDO::FETCH_ASSOC);
            $customerName = $custName ? trim($custName['first_name'] . ' ' . $custName['last_name']) : 'Walk-in Customer';

            $this->db->commit();

            Response::json(201, "POS Order completed successfully", [
                "order_id" => $orderId,
                "order_number" => "ORD-POS-" . date('Y') . "-" . str_pad($orderId, 3, '0', STR_PAD_LEFT),
                "date" => date('Y-m-d H:i:s'),
                "subtotal" => $subtotal,
                "discount" => $discount,
                "net_total" => $net_total,
                "cash_received" => $cashReceived,
                "change" => $changeAmount,
                "payment_method" => $paymentMethod,
                "cashier_name" => $cashierName,
                "customer_name" => $customerName,
                "free_gift" => $freeGift,
                "points_earned" => $pointsEarned
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(400, "Failed to complete POS order", ["error" => $e->getMessage()]);
        }
    }

    public function index() {
        try {
            $query = "SELECT order_id as id, 
                             order_date as date, 
                             CASE WHEN order_type = 2 THEN CONCAT('ORD-POS-', DATE_FORMAT(order_date, '%Y'), '-', LPAD(order_id, 3, '0'))
                                  ELSE CONCAT('ORD-', DATE_FORMAT(order_date, '%Y'), '-', LPAD(order_id, 3, '0')) END as number, 
                             net_total as amount, 
                             status,
                             order_type
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
                $order['date'] = date('Y-m-d H:i:s', strtotime($order['date']));
                $order['order_type'] = (int)($order['order_type'] ?? 1);
                $order['order_type_label'] = ($order['order_type'] == 2) ? 'ขายหน้าร้าน (POS)' : 'ออนไลน์';
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

            $qOrder = "SELECT o.order_id, o.order_date, 
                              CASE WHEN o.order_type = 2 THEN CONCAT('ORD-POS-', DATE_FORMAT(o.order_date, '%Y'), '-', LPAD(o.order_id, 3, '0'))
                                   ELSE CONCAT('ORD-', DATE_FORMAT(o.order_date, '%Y'), '-', LPAD(o.order_id, 3, '0')) END as number, 
                              o.status, o.subtotal, o.shipping_fee, o.discount_amount, o.net_total, o.order_type, o.free_gift, o.points_earned, o.cash_received,
                              c.first_name, c.last_name, c.phone, u.email,
                              a.address_detail, a.province, a.zip_code,
                              e.first_name as employee_first_name, e.last_name as employee_last_name
                       FROM orders o
                       JOIN customers c ON o.customer_id = c.customer_id
                       JOIN users u ON c.user_id = u.user_id
                       LEFT JOIN addresses a ON o.address_id = a.address_id
                       LEFT JOIN employees e ON o.employee_id = e.employee_id
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
            $company_name = null;
            try {
                $qDel = "SELECT d.tracking_number, d.company_id, dc.company_name 
                         FROM deliveries d 
                         LEFT JOIN delivery_companies dc ON d.company_id = dc.company_id 
                         WHERE d.order_id = ?";
                $stmtD = $this->db->prepare($qDel);
                $stmtD->execute([$orderId]);
                $d = $stmtD->fetch(PDO::FETCH_ASSOC);
                if ($d) {
                    $tracking_number = $d['tracking_number'];
                    $company_id = $d['company_id'];
                    $company_name = $d['company_name'];
                }
            } catch(Exception $ex) {}

            if (!$company_name && (int)($order['order_type'] ?? 1) === 1) {
                try {
                    $stmtFirstComp = $this->db->query("SELECT company_name FROM delivery_companies ORDER BY company_id ASC LIMIT 1");
                    $firstComp = $stmtFirstComp->fetch(PDO::FETCH_ASSOC);
                    $company_name = $firstComp ? $firstComp['company_name'] : 'ขนส่งเอกชน';
                } catch(Exception $exComp) {
                    $company_name = 'ขนส่งเอกชน';
                }
            }

            // Fetch payment method
            $payment_method = 'transfer';
            try {
                $qPay = "SELECT payment_method FROM payments WHERE order_id = ? LIMIT 1";
                $stmtPay = $this->db->prepare($qPay);
                $stmtPay->execute([$orderId]);
                $pay = $stmtPay->fetch(PDO::FETCH_ASSOC);
                if ($pay) {
                    if ((int)$pay['payment_method'] === 2) {
                        $payment_method = 'cash';
                    } elseif ((int)$pay['payment_method'] === 3) {
                        $payment_method = 'credit_card';
                    } else {
                        $payment_method = 'transfer';
                    }
                }
            } catch (Exception $exPay) {}

            if ($order['order_type'] == 2 && $payment_method === 'transfer') {
                $payment_method = 'cash';
            }

            $statusMap = [1 => 'Pending', 2 => 'Processing', 3 => 'In Transit', 4 => 'Completed', 5 => 'Cancelled'];
            $sId = (int)$order['status'];
            $statusStr = isset($statusMap[$sId]) ? $statusMap[$sId] : 'Pending';

            $data = [
                'id' => $order['order_id'],
                'date' => date('Y-m-d H:i:s', strtotime($order['order_date'])),
                'number' => $order['number'],
                'status' => $statusStr,
                'order_type' => (int)($order['order_type'] ?? 1),
                'order_type_label' => ((int)($order['order_type'] ?? 1) == 2) ? 'ขายหน้าร้าน (POS)' : 'ออนไลน์',
                'tracking_number' => $tracking_number,
                'company_id' => $company_id,
                'company_name' => $company_name,
                'shipping_provider' => $company_name,
                'payment_method' => $payment_method,
                'free_gift' => $order['free_gift'],
                'points_earned' => (int)($order['points_earned'] ?? 0),
                'cash_received' => $order['cash_received'] !== null ? (float)$order['cash_received'] : null,
                'change' => $order['cash_received'] !== null ? max(0, (float)$order['cash_received'] - (float)$order['net_total']) : 0.00,
                'cashier_name' => $order['employee_first_name'] ? trim($order['employee_first_name'] . ' ' . $order['employee_last_name']) : 'System',
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

            $updatedShippingFee = null;
            $updatedNetTotal = null;
            $updatedCompanyName = null;

            if ($companyIdReq !== null) {
                $compStmt = $this->db->prepare("SELECT * FROM delivery_companies WHERE company_id = ?");
                $compStmt->execute([$companyIdReq]);
                $comp = $compStmt->fetch(PDO::FETCH_ASSOC);

                if ($comp) {
                    $updatedCompanyName = $comp['company_name'];
                    $baseRate = (float)($comp['base_rate'] ?? 0);
                    $ratePerKg = (float)($comp['rate_per_kg'] ?? 0);

                    $wStmt = $this->db->prepare("SELECT SUM(od.quantity * COALESCE(p.weight, 0)) as total_weight FROM order_details od JOIN products p ON od.product_id = p.product_id WHERE od.order_id = ?");
                    $wStmt->execute([$orderId]);
                    $wRow = $wStmt->fetch(PDO::FETCH_ASSOC);
                    $totalWeight = (float)($wRow['total_weight'] ?? 0);

                    $calcShippingFee = $baseRate + ceil($totalWeight) * $ratePerKg;
                    if ($calcShippingFee <= 0) {
                        $calcShippingFee = $baseRate > 0 ? $baseRate : 40.00;
                    }
                    $updatedShippingFee = $calcShippingFee;

                    $oStmt = $this->db->prepare("SELECT subtotal, discount_amount FROM orders WHERE order_id = ?");
                    $oStmt->execute([$orderId]);
                    $oRow = $oStmt->fetch(PDO::FETCH_ASSOC);
                    $subtotal = (float)($oRow['subtotal'] ?? 0);
                    $discount = (float)($oRow['discount_amount'] ?? 0);

                    $updatedNetTotal = max(0, $subtotal + $updatedShippingFee - $discount);
                }
            }

            if ($updatedShippingFee !== null && $updatedNetTotal !== null) {
                $stmt = $this->db->prepare("UPDATE orders SET status = ?, shipping_fee = ?, net_total = ? WHERE order_id = ?");
                $executed = $stmt->execute([$sId, $updatedShippingFee, $updatedNetTotal, $orderId]);
            } else {
                $stmt = $this->db->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
                $executed = $stmt->execute([$sId, $orderId]);
            }

            if ($executed) {
                // Allow delivery table insertion even if tracking is blank
                if ($sId == 3 || $sId == 4 || $companyIdReq !== null) {
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

                Response::json(200, "Order status updated", [
                    "company_id" => $companyIdReq,
                    "company_name" => $updatedCompanyName,
                    "shipping_fee" => $updatedShippingFee,
                    "net_total" => $updatedNetTotal
                ]);
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
