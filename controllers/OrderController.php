<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/LineService.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';

class OrderController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Safely process slip image: if Base64 string, write to uploads/slips/ and return relative URL path
     */
    private function saveSlipImage($slipData, $orderId) {
        if (empty($slipData)) return null;
        if (strpos($slipData, '/uploads/') === 0 || strpos($slipData, 'http://') === 0 || strpos($slipData, 'https://') === 0) {
            return $slipData;
        }
        if (preg_match('/^data:image\/(\w+);base64,/', $slipData, $matches)) {
            $imgData = substr($slipData, strpos($slipData, ',') + 1);
            $ext = strtolower($matches[1]);
            if ($ext === 'jpeg') $ext = 'jpg';
            $decoded = base64_decode($imgData);
            if ($decoded !== false) {
                $dir = dirname(__DIR__) . '/uploads/slips/';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                $filename = 'slip_' . (int)$orderId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (file_put_contents($dir . $filename, $decoded)) {
                    return '/uploads/slips/' . $filename;
                }
            }
        }
        return $slipData;
    }

    public function createOnlineOrder() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $data = json_decode(file_get_contents("php://input"), true);
        
        $this->db->beginTransaction();
        
        try {
            if (empty($data['items']) || !is_array($data['items'])) {
                throw new Exception("ไม่พบรายการสินค้าในคำสั่งซื้อ");
            }

            // 1. Resolve customer_id securely
            $customerId = null;
            if (!empty($_SESSION['user_id'])) {
                $stmtCust = $this->db->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
                $stmtCust->execute([$_SESSION['user_id']]);
                $cRow = $stmtCust->fetch(PDO::FETCH_ASSOC);
                if ($cRow) {
                    $customerId = (int)$cRow['customer_id'];
                }
            } else {
                // Try token
                $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
                $authHeader = $headers['Authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);
                if ($authHeader && defined('JWT_SECRET') && JWT_SECRET !== '') {
                    try {
                        $token = str_replace('Bearer ', '', $authHeader);
                        $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key(JWT_SECRET, 'HS256'));
                        $tokenUserId = $decoded->data->user_id ?? null;
                        if ($tokenUserId) {
                            $stmtCust = $this->db->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
                            $stmtCust->execute([$tokenUserId]);
                            $cRow = $stmtCust->fetch(PDO::FETCH_ASSOC);
                            if ($cRow) {
                                $customerId = (int)$cRow['customer_id'];
                            }
                        }
                    } catch(Exception $e) {}
                }
            }

            // Fallback to customer_id from data only if not logged in (e.g. guest order), or fallback to first customer
            if (!$customerId && !empty($data['customer_id'])) {
                $customerId = (int)$data['customer_id'];
            }
            if (!$customerId) {
                $stmtFirstCust = $this->db->query("SELECT customer_id FROM customers ORDER BY customer_id ASC LIMIT 1");
                $firstCust = $stmtFirstCust->fetch(PDO::FETCH_ASSOC);
                $customerId = $firstCust ? (int)$firstCust['customer_id'] : 1;
            }

            // 2. Resolve address_id
            $addressId = !empty($data['address_id']) ? (int)$data['address_id'] : null;
            if (!$addressId && !empty($data['shipping_address'])) {
                $sa = $data['shipping_address'];
                $rName = !empty($sa['fullName']) ? trim($sa['fullName']) : 'Customer';
                $rPhone = !empty($sa['phone']) ? trim($sa['phone']) : '0000000000';
                $rAddr = !empty($sa['address']) ? trim($sa['address']) : '-';
                $rProv = !empty($sa['province']) ? trim($sa['province']) : '-';
                $rZip = !empty($sa['zipcode']) ? trim($sa['zipcode']) : '10000';

                $stmtInsertAddr = $this->db->prepare("INSERT INTO addresses (customer_id, recipient_name, phone, address_detail, province, zip_code, is_default) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmtInsertAddr->execute([$customerId, $rName, $rPhone, $rAddr, $rProv, $rZip]);
                $addressId = (int)$this->db->lastInsertId();

                if (!empty($rPhone)) {
                    $this->db->prepare("UPDATE customers SET phone = ? WHERE customer_id = ? AND (phone IS NULL OR phone = '')")
                        ->execute([$rPhone, $customerId]);
                }
            }

            if (!$addressId) {
                $stmtAddr = $this->db->prepare("SELECT address_id FROM addresses WHERE customer_id = ? LIMIT 1");
                $stmtAddr->execute([$customerId]);
                $addr = $stmtAddr->fetch(PDO::FETCH_ASSOC);
                if ($addr) {
                    $addressId = (int)$addr['address_id'];
                } else {
                    $stmtAnyAddr = $this->db->query("SELECT address_id FROM addresses LIMIT 1");
                    $anyAddr = $stmtAnyAddr->fetch(PDO::FETCH_ASSOC);
                    $addressId = $anyAddr ? (int)$anyAddr['address_id'] : 1;
                }
            }
            
            $subtotal = 0;
            $order_details = [];
            
            foreach ($data['items'] as $item) {
                $pid = (int)($item['product_id'] ?? 0);
                $qty = (int)($item['quantity'] ?? 0);
                if ($pid <= 0 || $qty <= 0) continue;

                // Check stock using correct MySQL columns (product_id, selling_price, cost_price, stock_qty)
                $stmt = $this->db->prepare("SELECT product_id, product_name, selling_price as price, cost_price, stock_qty FROM products WHERE product_id = ? FOR UPDATE");
                $stmt->execute([$pid]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    throw new Exception("ไม่พบสินค้ารหัส #{$pid} ในระบบ");
                }

                if ($product['stock_qty'] < $qty) {
                    throw new Exception("สินค้า '{$product['product_name']}' มีจำนวนไม่เพียงพอในสต็อก (คงเหลือ {$product['stock_qty']} ชิ้น)");
                }
                
                $total_price = (float)$product['price'] * $qty;
                $subtotal += $total_price;
                
                $order_details[] = [
                    'product_id' => $pid,
                    'quantity' => $qty,
                    'unit_cost' => (float)$product['cost_price'],
                    'unit_price' => (float)$product['price']
                ];
            }

            if (empty($order_details)) {
                throw new Exception("ไม่มีรายการสินค้าที่สามารถสั่งซื้อได้");
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
                        $discount = (float)$promo['discount_value'];
                    }
                }
            }
            
            // Points redemption logic (10 points = 10 Baht, minimum 10 points in multiples of 10)
            $pointsUsed = isset($data['points_used']) ? (int)$data['points_used'] : 0;
            $pointsDiscount = 0.0;
            if ($customerId && $pointsUsed > 0) {
                if ($pointsUsed < 10 || ($pointsUsed % 10) !== 0) {
                    $this->db->rollBack();
                    Response::json(400, "การใช้แต้มสะสมต้องใช้ขั้นต่ำ 10 แต้ม และเพิ่มขึ้นทีละ 10 แต้ม");
                    return;
                }

                $stmtCustPoints = $this->db->prepare("SELECT points FROM customers WHERE customer_id = ?");
                $stmtCustPoints->execute([$customerId]);
                $custRow = $stmtCustPoints->fetch(PDO::FETCH_ASSOC);
                $curPoints = $custRow ? (int)$custRow['points'] : 0;

                if ($curPoints < $pointsUsed) {
                    $this->db->rollBack();
                    Response::json(400, "แต้มสะสมของคุณไม่เพียงพอ (คุณมี $curPoints แต้ม)");
                    return;
                }

                $pointsDiscount = (float)(($pointsUsed / 10) * 10.0);
                if (($discount + $pointsDiscount) > $subtotal) {
                    $pointsDiscount = max(0, $subtotal - $discount);
                    $pointsUsed = (int)(floor($pointsDiscount / 10) * 10);
                    $pointsDiscount = (float)$pointsUsed;
                }
                $discount += $pointsDiscount;
            }

            // Cap discount
            if ($discount > $subtotal) {
                $discount = $subtotal;
            }
            
            // Calculate total weight from order items
            $totalWeight = 0;
            foreach ($order_details as $od) {
                $pStmt = $this->db->prepare("SELECT weight, weight_value, weight_unit FROM products WHERE product_id = ?");
                $pStmt->execute([$od['product_id']]);
                $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
                $w = 0.0;
                if ($pRow) {
                    $w = isset($pRow['weight']) && $pRow['weight'] !== null ? (float)$pRow['weight'] : (float)($pRow['weight_value'] ?? 0.0);
                    $u = strtolower(trim($pRow['weight_unit'] ?? 'kg'));
                    if ($u === 'g' || $u === 'ml' || $u === 'กรัม' || $u === 'มิลลิลิตร') {
                        $w = $w / 1000.0;
                    }
                }
                $totalWeight += ($w * (int)$od['quantity']);
            }

            // Resolve company_id
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

            $stmtCompRate = $this->db->prepare("SELECT base_rate, rate_per_kg FROM delivery_companies WHERE company_id = ?");
            $stmtCompRate->execute([$company_id ?: 1]);
            $compRate = $stmtCompRate->fetch(PDO::FETCH_ASSOC);
            $baseRate = $compRate ? (float)$compRate['base_rate'] : 40.00;
            $ratePerKg = $compRate ? (float)$compRate['rate_per_kg'] : 0.00;

            $extraKg = $totalWeight > 1.0 ? ceil($totalWeight - 1.0) : 0;
            $shipping_fee = $baseRate + ($extraKg * $ratePerKg);
            if ($shipping_fee <= 0) $shipping_fee = $baseRate > 0 ? $baseRate : 40.00;

            $net_total = ($subtotal - $discount) + $shipping_fee;
            if ($net_total < 0) $net_total = 0;

            // Calculate Tiered Gift
            $freeGift = null;
            try {
                $stmtGift = $this->db->prepare("SELECT gift_name FROM gift_rules WHERE min_spend <= ? ORDER BY min_spend DESC LIMIT 1");
                $stmtGift->execute([$net_total]);
                $giftRow = $stmtGift->fetch(PDO::FETCH_ASSOC);
                if ($giftRow) {
                    $freeGift = $giftRow['gift_name'];
                }
            } catch (Exception $eGift) {}
            
            // Calculate reward points
            $pointsEarned = 0;
            if ($customerId) {
                try {
                    $stmtSettings = $this->db->query("SELECT point_earning_baht, point_earning_qty FROM store_settings LIMIT 1");
                    $settings = $stmtSettings->fetch(PDO::FETCH_ASSOC);
                    $peBaht = $settings && isset($settings['point_earning_baht']) ? (float)$settings['point_earning_baht'] : 100.00;
                    $peQty = $settings && isset($settings['point_earning_qty']) ? (int)$settings['point_earning_qty'] : 1;

                    if ($peBaht > 0 && $peQty > 0) {
                        $pointsEarned = (int)(floor($net_total / $peBaht) * $peQty);
                    }
                } catch (Exception $ePts) {}
            }
            
            // 3. Insert into orders (including points_used)
            $stmtOrder = $this->db->prepare("INSERT INTO orders (customer_id, address_id, promo_id, subtotal, discount_amount, shipping_fee, net_total, points_used, status, order_type, free_gift, points_earned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?)");
            $promo_id = !empty($data['promo_id']) ? $data['promo_id'] : null;
            $stmtOrder->execute([$customerId, $addressId, $promo_id, $subtotal, $discount, $shipping_fee, $net_total, $pointsUsed, $freeGift, $pointsEarned]);
            $order_id = (int)$this->db->lastInsertId();

            // Insert into deliveries table
            if ($company_id) {
                try {
                    $stmtDel = $this->db->prepare("INSERT INTO deliveries (order_id, company_id, tracking_number, status) VALUES (?, ?, ?, 1)");
                    $stmtDel->execute([$order_id, $company_id, $data['tracking_number'] ?? null]);
                } catch(Exception $exDel) {}
            }

            // Insert payment record if slip or payment method provided
            try {
                $payMethod = 1; // Transfer / PromptPay
                $slipImg = !empty($data['slip_image']) ? $this->saveSlipImage($data['slip_image'], $order_id) : null;
                $stmtPay = $this->db->prepare("INSERT INTO payments (order_id, payment_method, amount, slip_image, status) VALUES (?, ?, ?, ?, 0)");
                $stmtPay->execute([$order_id, $payMethod, $net_total, $slipImg]);
            } catch (Exception $exPay) {}
            
            // 4. Insert order_details, deduct stock, insert inventory log
            $stmtDetail = $this->db->prepare("INSERT INTO order_details (order_id, product_id, quantity, unit_price, unit_cost) VALUES (?, ?, ?, ?, ?)");
            $stmtUpdateStock = $this->db->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ? AND stock_qty >= ?");
            $stmtLog = $this->db->prepare("INSERT INTO inventory_logs (product_id, employee_id, reference_id, quantity, movement_type, unit_cost) VALUES (?, 1, ?, ?, 2, ?)");
            
            foreach ($order_details as $detail) {
                $stmtDetail->execute([$order_id, $detail['product_id'], $detail['quantity'], $detail['unit_price'], $detail['unit_cost']]);
                
                // Update stock with safety condition
                $stmtUpdateStock->execute([$detail['quantity'], $detail['product_id'], $detail['quantity']]);
                if ($stmtUpdateStock->rowCount() === 0) {
                    throw new Exception("ไม่สามารถตัดสต็อกสินค้ารหัส #{$detail['product_id']} ได้ เนื่องจากสินค้าไม่เพียงพอ");
                }
                
                // Log inventory movement
                $stmtLog->execute([$detail['product_id'], $order_id, -$detail['quantity'], $detail['unit_cost']]); 
            }
            
            // Deduct points redeemed if any
            if ($customerId && $pointsUsed > 0) {
                try {
                    $stmtDeductPoints = $this->db->prepare("UPDATE customers SET points = GREATEST(0, points - ?) WHERE customer_id = ?");
                    $stmtDeductPoints->execute([$pointsUsed, $customerId]);

                    $orderNum = "ORD-" . date('Y') . "-" . str_pad($order_id, 3, '0', STR_PAD_LEFT);
                    $stmtLogDeduct = $this->db->prepare("INSERT INTO reward_point_logs (customer_id, order_id, points_change, description) VALUES (?, ?, ?, ?)");
                    $stmtLogDeduct->execute([$customerId, $order_id, -$pointsUsed, "ใช้แต้มสะสม {$pointsUsed} แต้มเป็นส่วนลด ฿" . number_format($pointsDiscount, 2) . " สำหรับคำสั่งซื้อ #{$orderNum}"]);
                } catch (Exception $exDeduct) {
                    error_log("Failed to deduct points on order #$order_id: " . $exDeduct->getMessage());
                }
            }

            // Award reward points to customer
            if ($customerId && $pointsEarned > 0) {
                try {
                    $stmtUpdatePoints = $this->db->prepare("UPDATE customers SET points = points + ? WHERE customer_id = ?");
                    $stmtUpdatePoints->execute([$pointsEarned, $customerId]);

                    $orderNum = "ORD-" . date('Y') . "-" . str_pad($order_id, 3, '0', STR_PAD_LEFT);
                    $stmtLogPoints = $this->db->prepare("INSERT INTO reward_point_logs (customer_id, order_id, points_change, description) VALUES (?, ?, ?, ?)");
                    $stmtLogPoints->execute([$customerId, $order_id, $pointsEarned, "ได้รับแต้มสะสมจากรายการสั่งซื้อออนไลน์ #$orderNum"]);
                } catch (Exception $exLogPts) {}
            }
            
            $this->db->commit();

            // Trigger Automatic LINE Notifications (Fail-safe, non-blocking)
            try {
                if (!empty($data['slip_image'])) {
                    LineService::sendPaymentAlert($order_id, 'submitted', $this->db);
                } else {
                    LineService::sendNewOrderAlert($order_id, $this->db);
                }
                $affectedProductIds = array_column($order_details, 'product_id');
                LineService::checkAndNotifyLowStock($affectedProductIds, $this->db);
            } catch (Exception $exLine) {
                error_log("LINE Auto-Notification failed on order #$order_id: " . $exLine->getMessage());
            }
            
            Response::json(201, "Order created successfully", [
                "order_id" => $order_id, 
                "shipping_fee" => $shipping_fee,
                "net_total" => $net_total,
                "free_gift" => $freeGift,
                "points_earned" => $pointsEarned,
                "points_used" => $pointsUsed,
                "points_discount" => $pointsDiscount
            ]);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(400, "Failed to create order: " . $e->getMessage(), ["error" => $e->getMessage()]);
        }
    }

    public function createPOSOrder() {
        AuthMiddleware::checkAnyPermission(['pos_access', 'orders_manage']);

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
            // 1. Resolve customer_id and member status
            $isMember = !empty($data['customer_id']);
            $memberCustomerId = $isMember ? (int)$data['customer_id'] : null;
            $customerId = $memberCustomerId;
            if (!$customerId) {
                // Default to the first customer in the DB strictly to satisfy DB foreign key constraint if non-nullable
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
                if ($isMember && $memberCustomerId) {
                    $stmtAddr = $this->db->prepare("SELECT address_id FROM addresses WHERE customer_id = ? LIMIT 1");
                    $stmtAddr->execute([$memberCustomerId]);
                    $addr = $stmtAddr->fetch(PDO::FETCH_ASSOC);
                    if ($addr && isset($addr['address_id'])) {
                        $addressId = (int)$addr['address_id'];
                    }
                }
                if (!$addressId) {
                    $stmtCustInfo = $this->db->prepare("SELECT first_name, last_name, phone FROM customers WHERE customer_id = ?");
                    $stmtCustInfo->execute([$customerId]);
                    $cInfo = $stmtCustInfo->fetch(PDO::FETCH_ASSOC);
                    $rName = ($isMember && $cInfo) ? trim($cInfo['first_name'] . ' ' . $cInfo['last_name']) : 'ลูกค้าทั่วไป (Walk-in)';
                    $rPhone = ($isMember && $cInfo && !empty($cInfo['phone'])) ? $cInfo['phone'] : '0000000000';

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

            // Points redemption logic for POS (10 points = 10 Baht, minimum 10 points in multiples of 10) - ONLY FOR MEMBERS
            $pointsUsed = ($isMember && isset($data['points_used'])) ? (int)$data['points_used'] : 0;
            $pointsDiscount = 0.0;
            $curCustomerPoints = 0;
            if ($isMember && $memberCustomerId && $pointsUsed > 0) {
                if ($pointsUsed < 10 || ($pointsUsed % 10) !== 0) {
                    $this->db->rollBack();
                    Response::json(400, "การใช้แต้มสะสมต้องใช้ขั้นต่ำ 10 แต้ม และเพิ่มขึ้นทีละ 10 แต้ม");
                    return;
                }

                $stmtCustPoints = $this->db->prepare("SELECT points FROM customers WHERE customer_id = ?");
                $stmtCustPoints->execute([$memberCustomerId]);
                $custRow = $stmtCustPoints->fetch(PDO::FETCH_ASSOC);
                $curCustomerPoints = $custRow ? (int)$custRow['points'] : 0;

                if ($curCustomerPoints < $pointsUsed) {
                    $this->db->rollBack();
                    Response::json(400, "แต้มสะสมของลูกค้ามีไม่เพียงพอ (มี $curCustomerPoints แต้ม แต่เลือกใช้ $pointsUsed แต้ม)");
                    return;
                }

                $pointsDiscount = (float)(($pointsUsed / 10) * 10.0);
                if (($discount + $pointsDiscount) > $subtotal) {
                    $pointsDiscount = max(0, $subtotal - $discount);
                    $pointsUsed = (int)(floor($pointsDiscount / 10) * 10);
                    $pointsDiscount = (float)$pointsUsed;
                }
                $discount += $pointsDiscount;
            } else if ($isMember && $memberCustomerId) {
                $stmtCustPoints = $this->db->prepare("SELECT points FROM customers WHERE customer_id = ?");
                $stmtCustPoints->execute([$memberCustomerId]);
                $custRow = $stmtCustPoints->fetch(PDO::FETCH_ASSOC);
                $curCustomerPoints = $custRow ? (int)$custRow['points'] : 0;
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

            // Calculate reward points (from net_total after points discount) - STRICTLY ONLY FOR MEMBERS!
            $pointsEarned = 0;
            if ($isMember && $memberCustomerId) {
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

            // 5. Insert into orders (status = 4 for Completed, order_type = 2 for POS, with points_used)
            $stmtOrder = $this->db->prepare("INSERT INTO orders (customer_id, employee_id, address_id, promo_id, subtotal, discount_amount, shipping_fee, net_total, points_used, status, order_type, free_gift, points_earned, cash_received) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 4, 2, ?, ?, ?)");
            $stmtOrder->execute([$customerId, $employeeId, $addressId, $promoId ? $promoId : null, $subtotal, $discount, $net_total, $pointsUsed, $freeGift, $pointsEarned, $cashReceived]);
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

            // Deduct points redeemed if any (STRICTLY ONLY FOR MEMBERS)
            if ($isMember && $memberCustomerId && $pointsUsed > 0) {
                try {
                    $stmtDeductPoints = $this->db->prepare("UPDATE customers SET points = GREATEST(0, points - ?) WHERE customer_id = ?");
                    $stmtDeductPoints->execute([$pointsUsed, $memberCustomerId]);

                    $orderNum = "ORD-POS-" . date('Y') . "-" . str_pad($orderId, 3, '0', STR_PAD_LEFT);
                    $stmtLogDeduct = $this->db->prepare("INSERT INTO reward_point_logs (customer_id, order_id, points_change, description) VALUES (?, ?, ?, ?)");
                    $stmtLogDeduct->execute([$memberCustomerId, $orderId, -$pointsUsed, "ใช้แต้มสะสม {$pointsUsed} แต้มเป็นส่วนลด ฿" . number_format($pointsDiscount, 2) . " สำหรับบิล POS #{$orderNum}"]);
                } catch (Exception $exDeduct) {
                    error_log("Failed to deduct points on POS order #$orderId: " . $exDeduct->getMessage());
                }
            }

            // Award reward points to customer (STRICTLY ONLY FOR MEMBERS)
            if ($isMember && $memberCustomerId && $pointsEarned > 0) {
                try {
                    // Update points in customers
                    $stmtUpdatePoints = $this->db->prepare("UPDATE customers SET points = points + ? WHERE customer_id = ?");
                    $stmtUpdatePoints->execute([$pointsEarned, $memberCustomerId]);

                    // Insert log
                    $orderNum = "ORD-POS-" . date('Y') . "-" . str_pad($orderId, 3, '0', STR_PAD_LEFT);
                    $stmtLogPoints = $this->db->prepare("INSERT INTO reward_point_logs (customer_id, order_id, points_change, description) VALUES (?, ?, ?, ?)");
                    $stmtLogPoints->execute([$memberCustomerId, $orderId, $pointsEarned, "ได้รับแต้มสะสมจากรายการสั่งซื้อ POS #$orderNum"]);
                } catch (Exception $exLog) {
                    error_log("Failed to award points on POS order #$orderId: " . $exLog->getMessage());
                }
            }

            // Retrieve employee name for cashier on receipt
            $stmtEmpName = $this->db->prepare("SELECT first_name, last_name FROM employees WHERE employee_id = ?");
            $stmtEmpName->execute([$employeeId]);
            $empName = $stmtEmpName->fetch(PDO::FETCH_ASSOC);
            $cashierName = $empName ? trim($empName['first_name'] . ' ' . $empName['last_name']) : 'System';

            // Retrieve customer name for receipt
            if ($isMember && $memberCustomerId) {
                $stmtCustName = $this->db->prepare("SELECT first_name, last_name FROM customers WHERE customer_id = ?");
                $stmtCustName->execute([$memberCustomerId]);
                $custName = $stmtCustName->fetch(PDO::FETCH_ASSOC);
                $customerName = $custName ? trim($custName['first_name'] . ' ' . $custName['last_name']) : 'ลูกค้าสมาชิก';
            } else {
                $customerName = 'ลูกค้าทั่วไป (Walk-in)';
            }

            $this->db->commit();

            // Trigger Automatic LINE Purchase & Low Stock Check (Fail-safe)
            try {
                LineService::sendNewOrderAlert($orderId, $this->db);
                $affectedProductIds = array_column($order_details, 'product_id');
                LineService::checkAndNotifyLowStock($affectedProductIds, $this->db);
            } catch (Exception $exLine) {
                error_log("LINE Auto-Notification failed on POS order #$orderId: " . $exLine->getMessage());
            }

            $remainingPoints = $isMember ? max(0, $curCustomerPoints - $pointsUsed + $pointsEarned) : null;

            Response::json(201, "POS Order completed successfully", [
                "order_id" => $orderId,
                "order_number" => "ORD-POS-" . date('Y') . "-" . str_pad($orderId, 3, '0', STR_PAD_LEFT),
                "date" => date('Y-m-d H:i:s'),
                "subtotal" => $subtotal,
                "discount" => $discount,
                "points_used" => $pointsUsed,
                "points_discount" => $pointsDiscount,
                "net_total" => $net_total,
                "cash_received" => $cashReceived,
                "change" => $changeAmount,
                "payment_method" => $paymentMethod,
                "cashier_name" => $cashierName,
                "customer_name" => $customerName,
                "customer_id" => $isMember ? $memberCustomerId : null,
                "free_gift" => $freeGift,
                "points_earned" => $isMember ? $pointsEarned : 0,
                "remaining_points" => $remainingPoints
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(400, "Failed to complete POS order", ["error" => $e->getMessage()]);
        }
    }

    public function index() {
        try {
            $user = AuthMiddleware::authenticate();
            $roleLower = strtolower($user['role'] ?? '');
            $isStaffOrAdmin = in_array($roleLower, ['admin', 'employee', 'staff', 'manager']);

            $baseQuery = "SELECT o.order_id as id, 
                             o.order_id,
                             o.order_date as date, 
                             o.order_date,
                             o.customer_id,
                             CASE WHEN o.order_type = 2 THEN CONCAT('ORD-POS-', DATE_FORMAT(o.order_date, '%Y'), '-', LPAD(o.order_id, 3, '0'))
                                  ELSE CONCAT('ORD-', DATE_FORMAT(o.order_date, '%Y'), '-', LPAD(o.order_id, 3, '0')) END as number, 
                             o.subtotal,
                             o.shipping_fee,
                             o.discount_amount,
                             o.points_used,
                             o.points_earned,
                             o.net_total as amount, 
                             o.net_total as total_amount,
                             o.status,
                             o.order_type,
                             c.first_name,
                             c.last_name,
                             c.phone as customer_phone,
                             u.email as customer_email,
                             a.recipient_name,
                             a.phone as recipient_phone,
                             a.address_detail,
                             a.province,
                             a.zip_code,
                             del.company_id,
                             del.company_name,
                             del.tracking_number,
                             p.slip_image,
                             p.payment_method,
                             p.status as payment_status
                      FROM orders o 
                      LEFT JOIN customers c ON o.customer_id = c.customer_id
                      LEFT JOIN users u ON c.user_id = u.user_id
                      LEFT JOIN addresses a ON o.address_id = a.address_id
                      LEFT JOIN (
                          SELECT d1.order_id, d1.company_id, d1.tracking_number, dc1.company_name
                          FROM deliveries d1
                          LEFT JOIN delivery_companies dc1 ON d1.company_id = dc1.company_id
                      ) del ON o.order_id = del.order_id
                      LEFT JOIN (
                          SELECT p1.order_id, p1.slip_image, p1.payment_method, p1.status
                          FROM payments p1
                          INNER JOIN (
                              SELECT order_id, MAX(payment_id) as max_payment_id
                              FROM payments
                              GROUP BY order_id
                          ) p2 ON p1.order_id = p2.order_id AND p1.payment_id = p2.max_payment_id
                      ) p ON o.order_id = p.order_id";

            if ($isStaffOrAdmin) {
                $query = $baseQuery . " ORDER BY o.order_date DESC";
                $stmt = $this->db->query($query);
            } else {
                $stmtCust = $this->db->prepare("SELECT customer_id FROM customers WHERE user_id = ? OR customer_id = ?");
                $stmtCust->execute([$user['user_id'], $user['user_id']]);
                $cIds = $stmtCust->fetchAll(PDO::FETCH_COLUMN);
                $targetIds = array_values(array_unique(array_filter(array_merge($cIds, [(int)$user['user_id']]))));
                $inClause = implode(',', array_fill(0, count($targetIds), '?'));

                $query = $baseQuery . " WHERE o.customer_id IN ($inClause) ORDER BY o.order_date DESC";
                $stmt = $this->db->prepare($query);
                $stmt->execute($targetIds);
            }

            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch items for all loaded orders
            $itemsByOrder = [];
            if (!empty($orders)) {
                $orderIds = array_column($orders, 'id');
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                $stmtItems = $this->db->prepare("SELECT od.order_id, od.product_id, od.quantity, od.unit_price, 
                                                         p.product_name, p.image_url
                                                  FROM order_details od
                                                  JOIN products p ON od.product_id = p.product_id
                                                  WHERE od.order_id IN ($placeholders)");
                $stmtItems->execute($orderIds);
                $allDetails = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                foreach ($allDetails as $row) {
                    $oid = $row['order_id'];
                    if (!isset($itemsByOrder[$oid])) {
                        $itemsByOrder[$oid] = [];
                    }
                    $itemsByOrder[$oid][] = [
                        'product_id' => (int)$row['product_id'],
                        'product_name' => $row['product_name'],
                        'name' => $row['product_name'],
                        'quantity' => (int)$row['quantity'],
                        'qty' => (int)$row['quantity'],
                        'unit_price' => (float)$row['unit_price'],
                        'price' => (float)$row['unit_price'],
                        'image_url' => $row['image_url'] ?: '/image/713815-00-allonline-hg.jpg',
                        'image' => $row['image_url'] ?: '/image/713815-00-allonline-hg.jpg'
                    ];
                }
            }

            $statusMap = [
                1 => 'Pending',
                2 => 'Processing',
                3 => 'In Transit',
                4 => 'Completed',
                5 => 'Cancelled'
            ];

            foreach ($orders as &$order) {
                $order['items'] = $itemsByOrder[$order['id']] ?? [];
                $order['amount'] = (float)$order['amount'];
                $order['total_amount'] = (float)$order['amount'];
                $order['total'] = (float)$order['amount'];
                $order['subtotal'] = (float)($order['subtotal'] ?? 0);
                $order['shipping_fee'] = (float)($order['shipping_fee'] ?? 0);
                $order['shipping'] = (float)($order['shipping_fee'] ?? 0);
                $order['discount_amount'] = (float)($order['discount_amount'] ?? 0);
                $order['points_used'] = (int)($order['points_used'] ?? 0);
                $order['points_discount'] = (float)($order['points_used'] > 0 ? ($order['points_used'] / 10) * 10.0 : 0.0);
                $order['points_earned'] = (int)($order['points_earned'] ?? 0);
                $sId = (int)$order['status'];
                $order['status_id'] = $sId;
                $order['status'] = isset($statusMap[$sId]) ? $statusMap[$sId] : 'Pending';
                $order['status_label'] = [
                    1 => 'รอดำเนินการ',
                    2 => 'กำลังแพ็คสินค้า',
                    3 => 'กำลังจัดส่ง',
                    4 => 'จัดส่งสำเร็จ',
                    5 => 'ยกเลิกแล้ว'
                ][$sId] ?? 'รอดำเนินการ';
                $order['date'] = date('Y-m-d H:i:s', strtotime($order['date']));
                $order['order_date'] = $order['date'];
                $order['order_type'] = (int)($order['order_type'] ?? 1);
                $order['order_type_label'] = ($order['order_type'] == 2) ? 'ขายหน้าร้าน (POS)' : 'ออนไลน์';
                $order['has_slip'] = !empty($order['slip_image']);
                $order['slip_image'] = $order['slip_image'] ?? null;
                $order['payment_status'] = $order['payment_status'] !== null ? (int)$order['payment_status'] : null;

                $cName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
                if (empty($cName)) {
                    $cName = !empty($order['recipient_name']) ? $order['recipient_name'] : 'ลูกค้าออนไลน์';
                }
                $cPhone = !empty($order['recipient_phone']) ? $order['recipient_phone'] : (!empty($order['customer_phone']) ? $order['customer_phone'] : '-');
                $addressParts = array_filter([$order['address_detail'] ?? '', $order['province'] ?? '', $order['zip_code'] ?? '']);
                $cAddress = count($addressParts) > 0 ? implode(' ', $addressParts) : '-';

                $order['customer_name'] = $cName;
                $order['customer_phone'] = $cPhone;
                $order['customer'] = [
                    'name' => $cName,
                    'email' => !empty($order['customer_email']) ? $order['customer_email'] : '-',
                    'phone' => $cPhone,
                    'address' => $cAddress
                ];
                $order['shippingAddress'] = [
                    'fullName' => !empty($order['recipient_name']) ? $order['recipient_name'] : $cName,
                    'phone' => $cPhone,
                    'address' => $order['address_detail'] ?? '',
                    'province' => $order['province'] ?? '',
                    'zipcode' => $order['zip_code'] ?? ''
                ];
                $order['company_name'] = $order['company_name'] ?: 'ขนส่งเอกชน';
                $order['deliveryMethod'] = $order['company_name'];
                $order['shipping_provider'] = $order['company_name'];
                $order['tracking_number'] = $order['tracking_number'] ?? null;
            }

            Response::json(200, "Orders retrieved successfully", $orders);
        } catch (Exception $e) {
            Response::json(500, "Failed to retrieve orders", ["error" => $e->getMessage()]);
        }
    }

    public function show() {
        try {
            $user = AuthMiddleware::authenticate();

            if (!isset($_GET['id'])) {
                Response::json(400, "Order ID is required");
                return;
            }
            $orderId = (int)$_GET['id'];

            $qOrder = "SELECT o.order_id, o.customer_id, o.order_date, 
                              CASE WHEN o.order_type = 2 THEN CONCAT('ORD-POS-', DATE_FORMAT(o.order_date, '%Y'), '-', LPAD(o.order_id, 3, '0'))
                                   ELSE CONCAT('ORD-', DATE_FORMAT(o.order_date, '%Y'), '-', LPAD(o.order_id, 3, '0')) END as number, 
                              o.status, o.subtotal, o.shipping_fee, o.discount_amount, o.points_used, o.net_total, o.order_type, o.free_gift, o.points_earned, o.cash_received,
                              c.first_name, c.last_name, c.phone, u.email,
                              a.address_detail, a.province, a.zip_code, a.recipient_name, a.phone as recipient_phone,
                              e.first_name as employee_first_name, e.last_name as employee_last_name
                       FROM orders o
                       LEFT JOIN customers c ON o.customer_id = c.customer_id
                       LEFT JOIN users u ON c.user_id = u.user_id
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

            $roleLower = strtolower($user['role'] ?? '');
            $isStaffOrAdmin = in_array($roleLower, ['admin', 'employee', 'staff', 'manager']);
            if (!$isStaffOrAdmin) {
                $stmtCust = $this->db->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
                $stmtCust->execute([$user['user_id']]);
                $cRow = $stmtCust->fetch(PDO::FETCH_ASSOC);
                $cid = $cRow ? (int)$cRow['customer_id'] : -1;

                if ((int)$order['customer_id'] !== $cid) {
                    Response::json(403, "Forbidden: You do not have permission to view this order");
                    return;
                }
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

            // Fetch payment method and slip details
            $payment_method = 'transfer';
            $slip_image = null;
            $payment_status = null;
            $payment_date = null;
            try {
                $qPay = "SELECT payment_method, slip_image, status, payment_date FROM payments WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1";
                $stmtPay = $this->db->prepare($qPay);
                $stmtPay->execute([$orderId]);
                $pay = $stmtPay->fetch(PDO::FETCH_ASSOC);
                if ($pay) {
                    $slip_image = $pay['slip_image'];
                    $payment_status = $pay['status'];
                    $payment_date = $pay['payment_date'];
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

            $cName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
            if (empty($cName)) {
                $cName = !empty($order['recipient_name']) ? $order['recipient_name'] : 'ลูกค้าทั่วไป';
            }
            $cPhone = !empty($order['phone']) ? $order['phone'] : (!empty($order['recipient_phone']) ? $order['recipient_phone'] : '-');
            $addressParts = array_filter([$order['address_detail'] ?? '', $order['province'] ?? '', $order['zip_code'] ?? '']);
            $cAddress = count($addressParts) > 0 ? implode(' ', $addressParts) : '-';

            $pointsUsedVal = (int)($order['points_used'] ?? 0);
            $pointsDiscountVal = $pointsUsedVal > 0 ? (float)(($pointsUsedVal / 10) * 10.0) : 0.0;

            $data = [
                'id' => $order['order_id'],
                'date' => date('Y-m-d H:i:s', strtotime($order['order_date'])),
                'number' => $order['number'],
                'status' => $statusStr,
                'status_id' => $sId,
                'status_label' => [
                    1 => 'รอดำเนินการ',
                    2 => 'กำลังแพ็คสินค้า',
                    3 => 'กำลังจัดส่ง',
                    4 => 'จัดส่งสำเร็จ',
                    5 => 'ยกเลิกแล้ว'
                ][$sId] ?? 'รอดำเนินการ',
                'order_type' => (int)($order['order_type'] ?? 1),
                'order_type_label' => ((int)($order['order_type'] ?? 1) == 2) ? 'ขายหน้าร้าน (POS)' : 'ออนไลน์',
                'tracking_number' => $tracking_number,
                'company_id' => $company_id,
                'company_name' => $company_name,
                'shipping_provider' => $company_name,
                'payment_method' => $payment_method,
                'slip_image' => $slip_image,
                'has_slip' => !empty($slip_image),
                'payment_status' => $payment_status,
                'payment_date' => $payment_date ? date('Y-m-d H:i:s', strtotime($payment_date)) : null,
                'free_gift' => $order['free_gift'],
                'points_earned' => (int)($order['points_earned'] ?? 0),
                'points_used' => $pointsUsedVal,
                'points_discount' => $pointsDiscountVal,
                'cash_received' => $order['cash_received'] !== null ? (float)$order['cash_received'] : null,
                'change' => $order['cash_received'] !== null ? max(0, (float)$order['cash_received'] - (float)$order['net_total']) : 0.00,
                'cashier_name' => $order['employee_first_name'] ? trim($order['employee_first_name'] . ' ' . $order['employee_last_name']) : 'System',
                'customer' => [
                    'name' => $cName,
                    'email' => !empty($order['email']) ? $order['email'] : '-',
                    'phone' => $cPhone,
                    'address' => $cAddress
                ],
                'items' => $items,
                'summary' => [
                    'subtotal' => (float)$order['subtotal'],
                    'shipping' => (float)$order['shipping_fee'],
                    'discount' => (float)$order['discount_amount'],
                    'points_used' => $pointsUsedVal,
                    'points_discount' => $pointsDiscountVal,
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
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['order_id']) || !isset($data['status'])) {
                Response::json(400, "Missing parameters");
                return;
            }

            $orderId = (int)$data['order_id'];
            $statusStr = $data['status'];
            $tracking = isset($data['tracking_number']) ? trim($data['tracking_number']) : null;
            $companyIdReq = isset($data['company_id']) && $data['company_id'] != '' ? (int)$data['company_id'] : null;

            $s = trim(strval($statusStr));
            if ($s === '1' || strcasecmp($s, 'Pending') === 0 || $s === 'รอดำเนินการ' || $s === 'Pending Payment' || $s === 'ที่ต้องชำระ') {
                $sId = 1;
            } elseif ($s === '2' || strcasecmp($s, 'Processing') === 0 || strcasecmp($s, 'Preparing') === 0 || $s === 'กำลังแพ็คสินค้า' || $s === 'ที่ต้องจัดส่ง' || $s === 'กำลังดำเนินการ') {
                $sId = 2;
            } elseif ($s === '3' || strcasecmp($s, 'In Transit') === 0 || strcasecmp($s, 'Shipping') === 0 || $s === 'กำลังจัดส่ง' || $s === 'ที่ต้องได้รับ' || $s === 'ส่งแล้ว') {
                $sId = 3;
            } elseif ($s === '4' || strcasecmp($s, 'Completed') === 0 || $s === 'จัดส่งสำเร็จ' || $s === 'สำเร็จแล้ว' || $s === 'สำเร็จ' || $s === 'ลูกค้าได้รับสินค้า') {
                $sId = 4;
            } elseif ($s === '5' || strcasecmp($s, 'Cancelled') === 0 || $s === 'ยกเลิกแล้ว' || $s === 'ยกเลิก') {
                $sId = 5;
            } else {
                $sId = 1;
            }

            $roleLower = strtolower($user['role'] ?? '');
            $isStaffOrAdmin = in_array($roleLower, ['admin', 'employee', 'staff', 'manager']);

            $stmtOrderCheck = $this->db->prepare("SELECT status, customer_id, net_total FROM orders WHERE order_id = ?");
            $stmtOrderCheck->execute([$orderId]);
            $orderRow = $stmtOrderCheck->fetch(PDO::FETCH_ASSOC);
            $currentStatus = $orderRow ? (int)$orderRow['status'] : 1;

            $stmtPayCheck = $this->db->prepare("SELECT payment_id, slip_image, status FROM payments WHERE order_id = ? AND (((slip_image IS NOT NULL AND slip_image != '') AND status != 2) OR status = 1)");
            $stmtPayCheck->execute([$orderId]);
            $payCheck = $stmtPayCheck->fetch(PDO::FETCH_ASSOC);
            $wasPaid = !empty($payCheck) || $currentStatus >= 2;

            if (!$isStaffOrAdmin) {
                // Verify customer ownership
                $stmtCust = $this->db->prepare("SELECT customer_id FROM customers WHERE user_id = ? OR customer_id = ?");
                $stmtCust->execute([(int)($user['user_id'] ?? 0), (int)($user['user_id'] ?? 0)]);
                $custIds = $stmtCust->fetchAll(PDO::FETCH_COLUMN);
                $reqCustId = isset($data['customer_id']) ? (int)$data['customer_id'] : -1;

                $isOwner = $orderRow && (
                    in_array((int)$orderRow['customer_id'], array_map('intval', $custIds)) || 
                    (int)$orderRow['customer_id'] === (int)($user['user_id'] ?? -1) ||
                    ($reqCustId > 0 && (int)$orderRow['customer_id'] === $reqCustId)
                );
                if (!$isOwner) {
                    Response::json(403, "Forbidden: You do not have permission to modify this order");
                    return;
                }

                // Allowed transitions for customer:
                // 1. Confirm receive: status -> Completed (4) from In Transit (3) or Processing (2) or Pending (1)
                // 2. Cancel: status -> Cancelled (5) ONLY if not paid yet and status is Pending (1)
                if ($sId === 5) {
                    if ($wasPaid) {
                        Response::json(400, "คำสั่งซื้อนี้ได้รับการชำระเงินแล้ว ไม่สามารถยกเลิกคำสั่งซื้อได้ด้วยตนเอง หากต้องการยกเลิกคำสั่งซื้อ กรุณาติดต่อทางร้านผ่านช่องทาง LINE");
                        return;
                    }
                    if ($currentStatus !== 1) {
                        Response::json(400, "ไม่สามารถยกเลิกคำสั่งซื้อในสถานะนี้ได้");
                        return;
                    }
                } elseif ($sId === 4) {
                    if ($currentStatus === 4) {
                        Response::json(200, "Order is already completed", [
                            "order_id" => $orderId,
                            "status" => 4
                        ]);
                        return;
                    }
                    if ($currentStatus < 1 || $currentStatus > 3) {
                        Response::json(403, "Forbidden: Invalid status transition for customer");
                        return;
                    }
                } else {
                    Response::json(403, "Forbidden: Invalid status transition for customer");
                    return;
                }

                $companyIdReq = null;
                $tracking = null;
            } else {
                AuthMiddleware::checkAnyPermission(['orders_manage', 'delivery_manage']);
            }

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

                    $wStmt = $this->db->prepare("SELECT od.quantity, p.weight, p.weight_value, p.weight_unit FROM order_details od JOIN products p ON od.product_id = p.product_id WHERE od.order_id = ?");
                    $wStmt->execute([$orderId]);
                    $odRows = $wStmt->fetchAll(PDO::FETCH_ASSOC);
                    $totalWeight = 0;
                    foreach ($odRows as $odR) {
                        $w = isset($odR['weight']) && $odR['weight'] !== null ? (float)$odR['weight'] : (float)($odR['weight_value'] ?? 0.0);
                        $u = strtolower(trim($odR['weight_unit'] ?? 'kg'));
                        if ($u === 'g' || $u === 'ml' || $u === 'กรัม' || $u === 'มิลลิลิตร') {
                            $w = $w / 1000.0;
                        }
                        $totalWeight += ($w * (int)$odR['quantity']);
                    }

                    $extraKg = $totalWeight > 1.0 ? ceil($totalWeight - 1.0) : 0;
                    $calcShippingFee = $baseRate + ($extraKg * $ratePerKg);
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

            // Retrieve previous status for comparison
            $prevStmt = $this->db->prepare("SELECT status, customer_id, points_earned, points_used FROM orders WHERE order_id = ?");
            $prevStmt->execute([$orderId]);
            $prevOrder = $prevStmt->fetch(PDO::FETCH_ASSOC);
            $prevStatus = $prevOrder ? (int)$prevOrder['status'] : 1;

            if ($updatedShippingFee !== null && $updatedNetTotal !== null) {
                $stmt = $this->db->prepare("UPDATE orders SET status = ?, shipping_fee = ?, net_total = ? WHERE order_id = ?");
                $executed = $stmt->execute([$sId, $updatedShippingFee, $updatedNetTotal, $orderId]);
            } else {
                $stmt = $this->db->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
                $executed = $stmt->execute([$sId, $orderId]);
            }

            if ($executed) {
                if ($sId == 2 || $sId == 4) {
                    $this->db->prepare("UPDATE payments SET status = 1 WHERE order_id = ?")->execute([$orderId]);
                    if ($prevStatus == 1) {
                        try {
                            $approverName = $isStaffOrAdmin ? ($user['username'] ?? 'เจ้าหน้าที่ร้าน') : 'ระบบอัตโนมัติ';
                            LineService::sendPaymentAlert($orderId, 'verified', $this->db, ['approver' => $approverName]);
                        } catch (Exception $exLine) {
                            error_log("LINE sendPaymentAlert error on updateStatus: " . $exLine->getMessage());
                        }
                    }
                } else if ($sId == 5) {
                    $this->db->prepare("UPDATE payments SET status = 2 WHERE order_id = ?")->execute([$orderId]);

                    // Restock inventory and reverse points if order was not previously cancelled
                    if ($prevStatus !== 5) {
                        try {
                            $qItems = "SELECT product_id, quantity, unit_cost FROM order_details WHERE order_id = ?";
                            $stmtItems = $this->db->prepare($qItems);
                            $stmtItems->execute([$orderId]);
                            $details = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                            $uStock = $this->db->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE product_id = ?");
                            $iLog = $this->db->prepare("INSERT INTO inventory_logs (product_id, employee_id, reference_id, quantity, movement_type, unit_cost) VALUES (?, 1, ?, ?, 1, ?)");

                            foreach ($details as $det) {
                                $uStock->execute([$det['quantity'], $det['product_id']]);
                                $iLog->execute([$det['product_id'], $orderId, $det['quantity'], $det['unit_cost']]);
                            }

                            // Reverse points if earned
                            if (!empty($prevOrder['customer_id']) && !empty($prevOrder['points_earned']) && (int)$prevOrder['points_earned'] > 0) {
                                $pts = (int)$prevOrder['points_earned'];
                                $cid = (int)$prevOrder['customer_id'];
                                $this->db->prepare("UPDATE customers SET points = GREATEST(0, points - ?) WHERE customer_id = ?")->execute([$pts, $cid]);
                                $this->db->prepare("INSERT INTO reward_point_logs (customer_id, order_id, points_change, description) VALUES (?, ?, ?, ?)")
                                         ->execute([$cid, $orderId, -$pts, "ดึงแต้มคืนเนื่องจากคำสั่งซื้อ #$orderId ถูกยกเลิก"]);
                            }

                            // Refund points used back to customer if any
                            if (!empty($prevOrder['customer_id']) && !empty($prevOrder['points_used']) && (int)$prevOrder['points_used'] > 0) {
                                $ptsUsed = (int)$prevOrder['points_used'];
                                $cid = (int)$prevOrder['customer_id'];
                                $this->db->prepare("UPDATE customers SET points = points + ? WHERE customer_id = ?")->execute([$ptsUsed, $cid]);
                                $this->db->prepare("INSERT INTO reward_point_logs (customer_id, order_id, points_change, description) VALUES (?, ?, ?, ?)")
                                         ->execute([$cid, $orderId, $ptsUsed, "คืนแต้มสะสม {$ptsUsed} แต้ม เนื่องจากคำสั่งซื้อ #$orderId ถูกยกเลิก"]);
                            }
                        } catch (Exception $exRestock) {
                            error_log("Restock on cancel order #$orderId error: " . $exRestock->getMessage());
                        }

                        // Trigger LINE Order Cancelled Notification
                        try {
                            $reason = !empty($data['cancel_reason']) ? $data['cancel_reason'] : (!empty($data['reason']) ? $data['reason'] : ($isStaffOrAdmin ? 'เจ้าหน้าที่ยกเลิกคำสั่งซื้อ' : 'ลูกค้ายกเลิกคำสั่งซื้อ'));
                            $cancelledBy = $isStaffOrAdmin ? ('เจ้าหน้าที่ (' . ($user['username'] ?? 'Staff') . ')') : 'ลูกค้า';
                            LineService::sendOrderCancelledAlert($orderId, $reason, $cancelledBy, $this->db);
                        } catch (Exception $exLine) {
                            error_log("LINE sendOrderCancelledAlert error on updateStatus: " . $exLine->getMessage());
                        }
                    }
                }

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
                    "net_total" => $updatedNetTotal,
                    "was_paid" => $wasPaid,
                    "require_refund_notice" => ($sId == 5 && $wasPaid),
                    "refund_message" => ($sId == 5 && $wasPaid) ? "ให้แคปรูปภาพข้อความนี้เพื่อเป็นหลักฐานในการโอนเงินคืนผ่านทาง LINE โดยให้ลูกค้าส่งข้อความมาทาง LINE ร้าน" : null
                ]);
            } else {
                Response::json(500, "Failed to update status");
            }
        } catch (Exception $e) {
            Response::json(500, "Error updating status", ["error" => $e->getMessage()]);
        }
    }

    public function uploadSlip() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = isset($data['order_id']) ? (int)$data['order_id'] : null;
            $slipImage = isset($data['slip_image']) ? $data['slip_image'] : null;

            if (!$orderId || empty($slipImage)) {
                Response::json(400, "Order ID and slip image are required");
                return;
            }

            $roleLower = strtolower($user['role'] ?? '');
            $isStaffOrAdmin = in_array($roleLower, ['admin', 'employee', 'staff', 'manager']);
            if (!$isStaffOrAdmin) {
                // Verify order belongs to customer
                $stmtCust = $this->db->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
                $stmtCust->execute([$user['user_id']]);
                $cRow = $stmtCust->fetch(PDO::FETCH_ASSOC);
                $cid = $cRow ? (int)$cRow['customer_id'] : -1;

                $stmtCheck = $this->db->prepare("SELECT customer_id FROM orders WHERE order_id = ?");
                $stmtCheck->execute([$orderId]);
                $orderRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$orderRow || (int)$orderRow['customer_id'] !== $cid) {
                    Response::json(403, "Forbidden: You do not have permission to upload slip for this order");
                    return;
                }
            }

            // Save slip image to disk safely
            $savedSlipUrl = $this->saveSlipImage($slipImage, $orderId);

            // Check if payment record exists
            $chkStmt = $this->db->prepare("SELECT payment_id FROM payments WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1");
            $chkStmt->execute([$orderId]);
            $paymentRow = $chkStmt->fetch(PDO::FETCH_ASSOC);

            if ($paymentRow) {
                $uStmt = $this->db->prepare("UPDATE payments SET slip_image = ?, status = 0, payment_date = NOW() WHERE payment_id = ?");
                $uStmt->execute([$savedSlipUrl, $paymentRow['payment_id']]);
            } else {
                // Fetch net_total from order
                $oStmt = $this->db->prepare("SELECT net_total FROM orders WHERE order_id = ?");
                $oStmt->execute([$orderId]);
                $oRow = $oStmt->fetch(PDO::FETCH_ASSOC);
                $netTotal = $oRow ? (float)$oRow['net_total'] : 0;

                $iStmt = $this->db->prepare("INSERT INTO payments (order_id, payment_method, amount, slip_image, status, payment_date) VALUES (?, 1, ?, ?, 0, NOW())");
                $iStmt->execute([$orderId, $netTotal, $savedSlipUrl]);
            }

            // Ensure order status is Pending (1) awaiting admin/staff verification
            $this->db->prepare("UPDATE orders SET status = 1 WHERE order_id = ?")->execute([$orderId]);

            // Trigger Automatic LINE Payment Notification (Slip Submitted)
            try {
                LineService::sendPaymentAlert($orderId, 'submitted', $this->db);
            } catch (Exception $exLine) {
                error_log("LINE sendPaymentAlert on uploadSlip error: " . $exLine->getMessage());
            }

            Response::json(200, "Slip uploaded and updated successfully", [
                "order_id" => $orderId,
                "has_slip" => true,
                "slip_image" => $savedSlipUrl,
                "payment_status" => 0
            ]);
        } catch (Exception $e) {
            Response::json(500, "Error uploading slip", ["error" => $e->getMessage()]);
        }
    }

    public function verifySlip() {
        try {
            AuthMiddleware::checkPermission('orders_manage');
            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = isset($data['order_id']) ? (int)$data['order_id'] : null;
            $action = isset($data['action']) ? trim($data['action']) : '';
            $reason = isset($data['reason']) ? trim($data['reason']) : '';

            if (!$orderId || !in_array($action, ['approve', 'reject'])) {
                Response::json(400, "Valid order ID and action ('approve' or 'reject') required");
                return;
            }

            if ($action === 'approve') {
                // 1. Update order status to 2 (Processing / กำลังแพ็คสินค้า)
                $uOrder = $this->db->prepare("UPDATE orders SET status = 2 WHERE order_id = ?");
                $uOrder->execute([$orderId]);

                // 2. Update payment status to 1 (Paid / Verified)
                $uPay = $this->db->prepare("UPDATE payments SET status = 1 WHERE order_id = ?");
                $uPay->execute([$orderId]);

                // Trigger Automatic LINE Payment Verified Notification
                try {
                    $approverName = 'เจ้าหน้าที่ร้าน Hello Pet Shop';
                    if (session_status() === PHP_SESSION_NONE) session_start();
                    if (!empty($_SESSION['username'])) {
                        $approverName = 'คุณ ' . $_SESSION['username'];
                    }
                    LineService::sendPaymentAlert($orderId, 'verified', $this->db, ['approver' => $approverName]);
                } catch (Exception $exLine) {
                    error_log("LINE sendPaymentAlert verified error: " . $exLine->getMessage());
                }

                Response::json(200, "สลิปถูกต้อง อนุมัติคำสั่งซื้อเรียบร้อยแล้ว (สถานะ: กำลังแพ็คสินค้า)", [
                    "order_id" => $orderId,
                    "status" => "Processing",
                    "status_id" => 2,
                    "payment_status" => 1
                ]);
            } else {
                // Reject slip action:
                // 1. Update payment status to 2 (Rejected / Invalid Slip)
                $uPay = $this->db->prepare("UPDATE payments SET status = 2 WHERE order_id = ?");
                $uPay->execute([$orderId]);

                // 2. Keep order in status 1 (Pending Payment) so customer can upload a new valid slip
                $uOrder = $this->db->prepare("UPDATE orders SET status = 1 WHERE order_id = ?");
                $uOrder->execute([$orderId]);

                // Trigger Automatic LINE Notification (Slip Rejected, Re-upload needed)
                try {
                    LineService::sendPaymentAlert($orderId, 'rejected', $this->db, ['reason' => $reason ?: 'สลิปการโอนเงินไม่ถูกต้อง']);
                } catch (Exception $exLine) {
                    error_log("LINE sendPaymentAlert rejected error: " . $exLine->getMessage());
                }

                Response::json(200, "ปฏิเสธสลิปเรียบร้อยแล้ว (สถานะ: ชำระเงินไม่สำเร็จ / รอลูกค้าแนบสลิปใหม่)", [
                    "order_id" => $orderId,
                    "status" => "Pending",
                    "status_id" => 1,
                    "payment_status" => 2,
                    "reason" => $reason ?: 'สลิปการโอนเงินไม่ถูกต้อง'
                ]);
            }
        } catch (Exception $e) {
            Response::json(500, "Error verifying slip", ["error" => $e->getMessage()]);
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

    public function getRefundOrders() {
        try {
            $user = AuthMiddleware::authenticate();

            $query = "SELECT o.order_id,
                             o.order_date,
                             CASE WHEN o.order_type = 2 THEN CONCAT('ORD-POS-', DATE_FORMAT(o.order_date, '%Y'), '-', LPAD(o.order_id, 3, '0'))
                                  ELSE CONCAT('ORD-', DATE_FORMAT(o.order_date, '%Y'), '-', LPAD(o.order_id, 3, '0')) END as number,
                             o.net_total as amount,
                             o.status,
                             o.refund_status,
                             o.refund_date,
                             o.refund_notes,
                             c.customer_id,
                             c.first_name,
                             c.last_name,
                             c.phone,
                             p.slip_image,
                             p.payment_method,
                             p.payment_date
                      FROM orders o
                      LEFT JOIN customers c ON o.customer_id = c.customer_id
                      LEFT JOIN (
                          SELECT p1.order_id, p1.slip_image, p1.payment_method, p1.payment_date
                          FROM payments p1
                          INNER JOIN (
                              SELECT order_id, MAX(payment_id) as max_payment_id
                              FROM payments
                              GROUP BY order_id
                          ) p2 ON p1.order_id = p2.order_id AND p1.payment_id = p2.max_payment_id
                      ) p ON o.order_id = p.order_id
                      WHERE o.status = 5 OR (p.slip_image IS NOT NULL AND p.slip_image != '') OR o.refund_status > 0
                      ORDER BY o.order_date DESC";

            $stmt = $this->db->query($query);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($orders as &$order) {
                $order['order_id'] = (int)$order['order_id'];
                $order['amount'] = (float)$order['amount'];
                $order['refund_status'] = (int)($order['refund_status'] ?? 0);
                $order['refund_status_label'] = $order['refund_status'] === 1 ? 'คืนเงินแล้ว' : 'ยังไม่ได้คืนเงิน';
                $custName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
                $order['customer_name'] = !empty($custName) ? $custName : 'ลูกค้า';
                $order['phone'] = !empty($order['phone']) ? $order['phone'] : '-';
                $order['has_slip'] = !empty($order['slip_image']);
            }

            Response::json(200, "Refund orders loaded successfully", $orders);
        } catch (Exception $e) {
            Response::json(500, "Failed to load refund orders", ["error" => $e->getMessage()]);
        }
    }

    public function updateRefundStatus() {
        try {
            $user = AuthMiddleware::authenticate();

            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['order_id']) || !isset($data['refund_status'])) {
                Response::json(400, "Missing order_id or refund_status");
                return;
            }

            $orderId = (int)$data['order_id'];
            $refundStatus = (int)$data['refund_status']; // 0: Pending, 1: Refunded
            $refundNotes = isset($data['refund_notes']) ? trim($data['refund_notes']) : null;
            $refundDate = ($refundStatus === 1) ? date('Y-m-d H:i:s') : null;

            $stmt = $this->db->prepare("UPDATE orders SET refund_status = ?, refund_date = ?, refund_notes = ? WHERE order_id = ?");
            $stmt->execute([$refundStatus, $refundDate, $refundNotes, $orderId]);

            Response::json(200, "อัปเดตสถานะการคืนเงินเรียบร้อยแล้ว", [
                "order_id" => $orderId,
                "refund_status" => $refundStatus,
                "refund_status_label" => $refundStatus === 1 ? "คืนเงินแล้ว" : "ยังไม่ได้คืนเงิน",
                "refund_date" => $refundDate
            ]);
        } catch (Exception $e) {
            Response::json(500, "Failed to update refund status", ["error" => $e->getMessage()]);
        }
    }
}
?>
