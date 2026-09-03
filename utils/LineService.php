<?php
require_once __DIR__ . '/../config/database.php';

class LineService {

    /**
     * Get LINE credentials from store_settings
     */
    private static function getCredentials($db = null) {
        try {
            if (!$db) {
                $database = new Database();
                $db = $database->getConnection();
            }
            $stmt = $db->query("SELECT line_oa_token, line_target_id FROM store_settings LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($settings && !empty($settings['line_oa_token']) && !empty($settings['line_target_id'])) {
                return [
                    'token' => trim($settings['line_oa_token']),
                    'target_id' => trim($settings['line_target_id'])
                ];
            }
        } catch (Exception $e) {
            error_log("LineService getCredentials error: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Send raw push message to LINE Messaging API
     */
    public static function sendPushMessage($message, $customToken = null, $customTargetId = null) {
        try {
            $token = $customToken;
            $targetId = $customTargetId;

            if (empty($token) || empty($targetId)) {
                $creds = self::getCredentials();
                if (!$creds) {
                    return [
                        'success' => false,
                        'message' => 'LINE Token หรือ Target ID ยังไม่ได้ตั้งค่า'
                    ];
                }
                $token = $creds['token'];
                $targetId = $creds['target_id'];
            }

            $lineUrl = "https://api.line.me/v2/bot/message/push";
            $headers = [
                "Content-Type: application/json",
                "Authorization: Bearer " . $token
            ];
            $payload = [
                'to' => $targetId,
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => trim($message)
                    ]
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $lineUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Fast timeout to avoid blocking checkout workflow
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200) {
                return [
                    'success' => true,
                    'message' => 'ส่งข้อความเข้า LINE เรียบร้อยแล้ว'
                ];
            } else {
                $resData = json_decode($result, true);
                $errorMsg = $resData['message'] ?? ($curlError ?: 'เกิดข้อผิดพลาดในการเชื่อมต่อ LINE Messaging API (HTTP ' . $httpCode . ')');
                if (!empty($resData['details'])) {
                    $subErrors = [];
                    foreach ($resData['details'] as $det) {
                        if (isset($det['message'])) {
                            $subErrors[] = $det['message'];
                        }
                    }
                    if (count($subErrors) > 0) {
                        $errorMsg .= " (" . implode(", ", $subErrors) . ")";
                    }
                }
                error_log("LineService push failed: " . $errorMsg . " | Raw: " . $result);
                return [
                    'success' => false,
                    'message' => $errorMsg,
                    'raw' => $result
                ];
            }
        } catch (Exception $e) {
            error_log("LineService exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send notification for a new purchase (Online Order or POS Store Sale)
     */
    public static function sendNewOrderAlert($orderId, $db = null) {
        try {
            if (!$db) {
                $database = new Database();
                $db = $database->getConnection();
            }

            // 1. Fetch Order Details
            $qOrder = "SELECT o.order_id, o.order_date, o.subtotal, o.shipping_fee, o.discount_amount, o.points_used, o.net_total, o.free_gift, o.order_type, o.cash_received,
                              c.first_name, c.last_name, c.phone,
                              a.address_detail, a.province, a.zip_code, a.recipient_name, a.phone as recipient_phone,
                              dc.company_name,
                              e.first_name as emp_first_name, e.last_name as emp_last_name
                       FROM orders o
                       LEFT JOIN customers c ON o.customer_id = c.customer_id
                       LEFT JOIN addresses a ON o.address_id = a.address_id
                       LEFT JOIN deliveries d ON o.order_id = d.order_id
                       LEFT JOIN delivery_companies dc ON d.company_id = dc.company_id
                       LEFT JOIN employees e ON o.employee_id = e.employee_id
                       WHERE o.order_id = ?";
            $stmt = $db->prepare($qOrder);
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) return false;

            // 2. Fetch Order Items
            $qItems = "SELECT od.quantity, od.unit_price, p.product_name 
                       FROM order_details od
                       JOIN products p ON od.product_id = p.product_id
                       WHERE od.order_id = ?";
            $stmtItems = $db->prepare($qItems);
            $stmtItems->execute([$orderId]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            $isPos = (int)($order['order_type'] ?? 1) === 2;
            $orderPrefix = $isPos ? "ORD-POS-" : "ORD-";
            $orderNumber = $orderPrefix . date('Y', strtotime($order['order_date'] ?? 'now')) . "-" . str_pad($orderId, 3, '0', STR_PAD_LEFT);
            
            $customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
            if (empty($customerName)) {
                $customerName = $order['recipient_name'] ?? ($isPos ? 'ลูกค้าทั่วไป (Walk-in)' : 'ลูกค้าทั่วไป');
            }
            $customerPhone = !empty($order['phone']) ? $order['phone'] : ($order['recipient_phone'] ?? '-');

            $formattedTime = date('d/m/Y H:i', strtotime($order['order_date'] ?? 'now')) . " น.";

            // 3. Build message
            if ($isPos) {
                $cashierName = trim(($order['emp_first_name'] ?? '') . ' ' . ($order['emp_last_name'] ?? ''));
                if (empty($cashierName)) $cashierName = 'พนักงานประจำสาขา';

                $msg = "🛒 มีรายการซื้อสินค้าหน้าร้าน (POS)!\n";
                $msg .= "━━━━━━━━━━━━━━━━━━\n";
                $msg .= "📋 รหัสการขาย: #{$orderNumber}\n";
                $msg .= "👤 ลูกค้า: คุณ {$customerName}\n";
                $msg .= "💼 แคชเชียร์: {$cashierName}\n";
                $msg .= "📦 รายการสินค้าที่ซื้อ:\n";

                foreach ($items as $item) {
                    $itemTotal = number_format($item['unit_price'] * $item['quantity'], 2);
                    $msg .= "   • {$item['product_name']} x {$item['quantity']} (฿{$itemTotal})\n";
                }

                if (!empty($order['free_gift'])) {
                    $msg .= "🎁 ของแถมพิเศษ: {$order['free_gift']}\n";
                }

                if (!empty($order['points_used']) && (int)$order['points_used'] > 0) {
                    $ptsDiscount = ((int)$order['points_used'] / 10) * 10;
                    $msg .= "🪙 ใช้แต้มสะสม: {$order['points_used']} แต้ม (-฿" . number_format($ptsDiscount, 2) . ")\n";
                }

                if ((float)$order['discount_amount'] > 0) {
                    $msg .= "🏷️ ส่วนลดรวม: -฿" . number_format((float)$order['discount_amount'], 2) . "\n";
                }

                $msg .= "💰 ยอดรวมทั้งสิ้น: ฿" . number_format((float)$order['net_total'], 2) . "\n";
                
                $cashReceived = (float)($order['cash_received'] ?? 0);
                if ($cashReceived > 0) {
                    $change = max(0, $cashReceived - (float)$order['net_total']);
                    $msg .= "💵 ชำระเงินสด: รับเงิน ฿" . number_format($cashReceived, 2) . " (เงินทอน ฿" . number_format($change, 2) . ")\n";
                } else {
                    $msg .= "💳 ชำระเงิน: ชำระเงินเรียบร้อยแล้ว\n";
                }

                $msg .= "━━━━━━━━━━━━━━━━━━\n";
                $msg .= "🕒 เวลา: {$formattedTime}\n";
                $msg .= "👉 ตรวจสอบบิลขายได้ที่ระบบ Hello Pet Shop POS";
            } else {
                // Online Order
                $addressParts = array_filter([$order['address_detail'] ?? '', $order['province'] ?? '', $order['zip_code'] ?? '']);
                $fullAddress = count($addressParts) > 0 ? implode(' ', $addressParts) : 'จัดส่งตามที่อยู่ลูกค้า';
                $carrier = !empty($order['company_name']) ? $order['company_name'] : 'ขนส่งพาร์ทเนอร์';

                $msg = "🛍️ มีคำสั่งซื้อออนไลน์ใหม่เข้ามา!\n";
                $msg .= "━━━━━━━━━━━━━━━━━━\n";
                $msg .= "📋 รหัสสั่งซื้อ: #{$orderNumber}\n";
                $msg .= "👤 ลูกค้า: คุณ {$customerName}\n";
                $msg .= "📞 เบอร์ติดต่อ: {$customerPhone}\n";
                $msg .= "📦 รายการสินค้า:\n";

                foreach ($items as $item) {
                    $itemTotal = number_format($item['unit_price'] * $item['quantity'], 2);
                    $msg .= "   • {$item['product_name']} x {$item['quantity']} (฿{$itemTotal})\n";
                }

                if (!empty($order['free_gift'])) {
                    $msg .= "🎁 ของแถมพิเศษ: {$order['free_gift']}\n";
                }

                if (!empty($order['points_used']) && (int)$order['points_used'] > 0) {
                    $ptsDiscount = ((int)$order['points_used'] / 10) * 10;
                    $msg .= "🪙 ใช้แต้มสะสม: {$order['points_used']} แต้ม (-฿" . number_format($ptsDiscount, 2) . ")\n";
                }

                if ((float)$order['discount_amount'] > 0) {
                    $msg .= "🏷️ ส่วนลดรวม: -฿" . number_format((float)$order['discount_amount'], 2) . "\n";
                }

                $shippingFee = (float)($order['shipping_fee'] ?? 0);
                if ($shippingFee > 0) {
                    $msg .= "🚚 ขนส่ง: {$carrier} (฿" . number_format($shippingFee, 2) . ")\n";
                } else {
                    $msg .= "🚚 ขนส่ง: {$carrier} (ส่งฟรี)\n";
                }

                $msg .= "📍 ที่อยู่จัดส่ง: {$fullAddress}\n";
                $msg .= "💰 ยอดชำระสุทธิ: ฿" . number_format((float)$order['net_total'], 2) . "\n";
                $msg .= "━━━━━━━━━━━━━━━━━━\n";
                $msg .= "🕒 เวลา: {$formattedTime}\n";
                $msg .= "👉 ตรวจสอบและจัดส่งได้ที่ระบบ Hello Pet Shop";
            }

            return self::sendPushMessage($msg);
        } catch (Exception $e) {
            error_log("LineService sendNewOrderAlert error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification for payment events (Slip Submitted / Payment Verified)
     * $action: 'submitted' (customer uploaded slip) or 'verified' (staff approved slip / paid)
     */
    public static function sendPaymentAlert($orderId, $action = 'submitted', $db = null, $extra = []) {
        try {
            if (!$db) {
                $database = new Database();
                $db = $database->getConnection();
            }

            $qOrder = "SELECT o.order_id, o.order_date, o.net_total, o.order_type,
                              c.first_name, c.last_name, c.phone,
                              a.recipient_name, a.phone as recipient_phone,
                              p.payment_method, p.amount as pay_amount, p.payment_date
                       FROM orders o
                       LEFT JOIN customers c ON o.customer_id = c.customer_id
                       LEFT JOIN addresses a ON o.address_id = a.address_id
                       LEFT JOIN (
                           SELECT p1.order_id, p1.payment_method, p1.amount, p1.payment_date
                           FROM payments p1
                           INNER JOIN (
                               SELECT order_id, MAX(payment_id) as max_pid
                               FROM payments
                               GROUP BY order_id
                           ) p2 ON p1.order_id = p2.order_id AND p1.payment_id = p2.max_pid
                       ) p ON o.order_id = p.order_id
                       WHERE o.order_id = ?";
            $stmt = $db->prepare($qOrder);
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) return false;

            $isPos = (int)($order['order_type'] ?? 1) === 2;
            $orderPrefix = $isPos ? "ORD-POS-" : "ORD-";
            $orderNumber = $orderPrefix . date('Y', strtotime($order['order_date'] ?? 'now')) . "-" . str_pad($orderId, 3, '0', STR_PAD_LEFT);

            $customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
            if (empty($customerName)) {
                $customerName = $order['recipient_name'] ?? ($isPos ? 'ลูกค้าทั่วไป (Walk-in)' : 'ลูกค้าทั่วไป');
            }
            $customerPhone = !empty($order['phone']) ? $order['phone'] : ($order['recipient_phone'] ?? '-');

            $amount = (float)($order['pay_amount'] ?: $order['net_total']);
            $formattedAmount = "฿" . number_format($amount, 2);
            $formattedTime = date('d/m/Y H:i') . " น.";

            if ($action === 'submitted') {
                $msg = "💵 ได้รับแจ้งชำระเงินใหม่! (แนบสลิปแล้ว)\n";
                $msg .= "━━━━━━━━━━━━━━━━━━\n";
                $msg .= "📋 รหัสสั่งซื้อ: #{$orderNumber}\n";
                $msg .= "👤 ลูกค้า: คุณ {$customerName}\n";
                $msg .= "📞 เบอร์ติดต่อ: {$customerPhone}\n";
                $msg .= "💰 ยอดเงินที่แจ้งชำระ: {$formattedAmount}\n";
                $msg .= "💳 วิธีการชำระเงิน: โอนผ่านธนาคาร / พร้อมเพย์\n";
                $msg .= "📎 หลักฐาน: แนบสลิปเรียบร้อยแล้ว (รอตรวจสอบ)\n";
                $msg .= "━━━━━━━━━━━━━━━━━━\n";
                $msg .= "🕒 เวลาแจ้ง: {$formattedTime}\n";
                $msg .= "👉 กรุณาตรวจสอบสลิปและกดยืนยันในระบบหลังร้าน Hello Pet Shop";
            } else {
                // Verified / Approved
                $approver = $extra['approver'] ?? 'เจ้าหน้าที่ร้าน Hello Pet Shop';

                $msg = "✅ ยืนยันการชำระเงินเรียบร้อยแล้ว!\n";
                $msg .= "━━━━━━━━━━━━━━━━━━\n";
                $msg .= "📋 รหัสสั่งซื้อ: #{$orderNumber}\n";
                $msg .= "👤 ลูกค้า: คุณ {$customerName}\n";
                $msg .= "💰 ยอดชำระสุทธิ: {$formattedAmount}\n";
                $msg .= "📦 สถานะ: ตรวจสอบการชำระเงินผ่านแล้ว\n";
                $msg .= "👨‍💼 ผู้ตรวจสอบ: {$approver}\n";
                $msg .= "━━━━━━━━━━━━━━━━━━\n";
                $msg .= "🕒 เวลาตรวจสอบ: {$formattedTime}\n";
                $msg .= "👉 กำลังดำเนินการแพ็คสินค้าและเตรียมส่งมอบให้ขนส่ง";
            }

            return self::sendPushMessage($msg);
        } catch (Exception $e) {
            error_log("LineService sendPaymentAlert error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification for order / item cancellation
     */
    public static function sendOrderCancelledAlert($orderId, $reason = '', $cancelledBy = '', $db = null) {
        try {
            if (!$db) {
                $database = new Database();
                $db = $database->getConnection();
            }

            // 1. Fetch Order Details
            $qOrder = "SELECT o.order_id, o.order_date, o.net_total, o.order_type,
                              c.first_name, c.last_name, c.phone,
                              a.recipient_name, a.phone as recipient_phone
                       FROM orders o
                       LEFT JOIN customers c ON o.customer_id = c.customer_id
                       LEFT JOIN addresses a ON o.address_id = a.address_id
                       WHERE o.order_id = ?";
            $stmt = $db->prepare($qOrder);
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) return false;

            // 2. Fetch Order Items
            $qItems = "SELECT od.quantity, od.unit_price, p.product_name 
                       FROM order_details od
                       JOIN products p ON od.product_id = p.product_id
                       WHERE od.order_id = ?";
            $stmtItems = $db->prepare($qItems);
            $stmtItems->execute([$orderId]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            $isPos = (int)($order['order_type'] ?? 1) === 2;
            $orderPrefix = $isPos ? "ORD-POS-" : "ORD-";
            $orderNumber = $orderPrefix . date('Y', strtotime($order['order_date'] ?? 'now')) . "-" . str_pad($orderId, 3, '0', STR_PAD_LEFT);

            $customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
            if (empty($customerName)) {
                $customerName = $order['recipient_name'] ?? 'ลูกค้าทั่วไป';
            }
            $customerPhone = !empty($order['phone']) ? $order['phone'] : ($order['recipient_phone'] ?? '-');

            $reasonText = !empty($reason) ? trim($reason) : 'ลูกค้ายกเลิกคำสั่งซื้อ / ยกเลิกเนื่องจากสลิปไม่ถูกต้อง';
            $operator = !empty($cancelledBy) ? trim($cancelledBy) : 'เจ้าหน้าที่ / ลูกค้า';
            $formattedTime = date('d/m/Y H:i') . " น.";

            $msg = "❌ แจ้งเตือนการยกเลิกคำสั่งซื้อ!\n";
            $msg .= "━━━━━━━━━━━━━━━━━━\n";
            $msg .= "📋 รหัสคำสั่งซื้อ: #{$orderNumber}\n";
            $msg .= "👤 ลูกค้า: คุณ {$customerName}\n";
            $msg .= "📞 เบอร์ติดต่อ: {$customerPhone}\n";
            $msg .= "📦 รายการสินค้าที่ถูกยกเลิก:\n";

            foreach ($items as $item) {
                $itemTotal = number_format($item['unit_price'] * $item['quantity'], 2);
                $msg .= "   • {$item['product_name']} x {$item['quantity']} (฿{$itemTotal})\n";
            }

            $msg .= "💰 มูลค่าที่ยกเลิก: ฿" . number_format((float)$order['net_total'], 2) . "\n";
            $msg .= "⚠️ สาเหตุ: {$reasonText}\n";
            $msg .= "👤 ดำเนินการโดย: {$operator}\n";
            $msg .= "🔄 สต็อกสินค้า: คืนจำนวนเข้าคลังเรียบร้อยแล้ว\n";
            $msg .= "━━━━━━━━━━━━━━━━━━\n";
            $msg .= "🕒 เวลา: {$formattedTime}\n";
            $msg .= "👉 ตรวจสอบสถานะได้ที่ระบบหลังร้าน Hello Pet Shop";

            return self::sendPushMessage($msg);
        } catch (Exception $e) {
            error_log("LineService sendOrderCancelledAlert error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if specific products reached low stock / out of stock and send real-time alert
     */
    public static function checkAndNotifyLowStock($productIds, $db = null) {
        try {
            if (empty($productIds)) return false;
            if (!is_array($productIds)) $productIds = [$productIds];

            if (!$db) {
                $database = new Database();
                $db = $database->getConnection();
            }

            // Sanitize & build placeholders
            $intIds = array_map('intval', $productIds);
            if (empty($intIds)) return false;
            $placeholders = implode(',', array_fill(0, count($intIds), '?'));

            $query = "SELECT product_id, product_name, stock_qty, min_stock_level 
                      FROM products 
                      WHERE product_id IN ($placeholders) 
                        AND is_active = 1
                        AND (
                          (min_stock_level IS NULL OR min_stock_level = 0) AND stock_qty <= 5
                          OR
                          (min_stock_level IS NOT NULL AND min_stock_level > 0 AND stock_qty <= min_stock_level)
                        )
                      ORDER BY stock_qty ASC";

            $stmt = $db->prepare($query);
            $stmt->execute($intIds);
            $alertItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($alertItems)) return false;

            $msg = "🚨 แจ้งเตือนด่วน: สินค้าใกล้หมดสต็อก / หมดสต็อก!\n";
            $msg .= "━━━━━━━━━━━━━━━━━━\n";
            $msg .= "📦 รายการสินค้าที่ต้องเติมสต็อก:\n";

            foreach ($alertItems as $item) {
                $qty = (int)$item['stock_qty'];
                $min = (int)($item['min_stock_level'] > 0 ? $item['min_stock_level'] : 5);

                if ($qty <= 0) {
                    $msg .= "   • {$item['product_name']} (เหลือ 0 ชิ้น - ❌ สินค้าหมดสต็อก!)\n";
                } else {
                    $msg .= "   • {$item['product_name']} (เหลือ {$qty} ชิ้น - ⚠️ เกณฑ์ขั้นต่ำ {$min} ชิ้น)\n";
                }
            }

            $msg .= "━━━━━━━━━━━━━━━━━━\n";
            $msg .= "🕒 เวลา: " . date('d/m/Y H:i') . " น.\n";
            $msg .= "👉 กรุณาวางแผนสั่งซื้อสินค้าเข้าคลังสต็อก";

            return self::sendPushMessage($msg);
        } catch (Exception $e) {
            error_log("LineService checkAndNotifyLowStock error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send stock summary alert (low stock & near expiry)
     */
    public static function sendStockSummaryAlert($db = null) {
        try {
            if (!$db) {
                $database = new Database();
                $db = $database->getConnection();
            }

            // 1. Low stock
            $qLow = "SELECT product_name, stock_qty, min_stock_level FROM products 
                     WHERE is_active = 1 
                       AND (
                         (min_stock_level IS NULL OR min_stock_level = 0) AND stock_qty <= 5
                         OR
                         (min_stock_level IS NOT NULL AND min_stock_level > 0 AND stock_qty <= min_stock_level)
                       )
                     ORDER BY stock_qty ASC";
            $lowStock = $db->query($qLow)->fetchAll(PDO::FETCH_ASSOC);

            // 2. Near expiry / Expired
            $qExp = "SELECT p.product_name, l.quantity as stock_qty, l.expiry_date 
                     FROM product_lots l
                     JOIN products p ON l.product_id = p.product_id
                     WHERE p.is_active = 1 
                       AND l.expiry_date IS NOT NULL 
                       AND l.expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
                       AND l.quantity > 0
                     UNION
                     SELECT p.product_name, p.stock_qty, p.expiry_date
                     FROM products p
                     WHERE p.is_active = 1
                       AND p.expiry_date IS NOT NULL
                       AND p.expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
                       AND p.stock_qty > 0
                       AND p.product_id NOT IN (SELECT DISTINCT product_id FROM product_lots WHERE quantity > 0 AND expiry_date IS NOT NULL)
                     ORDER BY expiry_date ASC";
            $nearExpiry = $db->query($qExp)->fetchAll(PDO::FETCH_ASSOC);

            if (count($lowStock) === 0 && count($nearExpiry) === 0) {
                return [
                    'success' => true,
                    'message' => 'ไม่มีข้อมูลสินค้าใกล้หมดหรือหมดอายุที่ต้องแจ้งเตือนในขณะนี้'
                ];
            }

            $message = "📢 รายงานสรุปสถานะสินค้า Hello Pet Shop\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n";
            
            if (count($nearExpiry) > 0) {
                $message .= "🚨 สินค้าใกล้หมดอายุ / หมดอายุแล้ว:\n";
                foreach ($nearExpiry as $item) {
                    $formattedDate = date('d/m/Y', strtotime($item['expiry_date']));
                    $message .= "   • {$item['product_name']} (หมดอายุ: {$formattedDate}) เหลือ {$item['stock_qty']} ชิ้น\n";
                }
                $message .= "\n";
            }

            if (count($lowStock) > 0) {
                $message .= "📦 สินค้าใกล้หมดสต็อก / หมดสต็อก:\n";
                foreach ($lowStock as $item) {
                    $min = $item['min_stock_level'] > 0 ? $item['min_stock_level'] : 5;
                    $message .= "   • {$item['product_name']} (เหลือ {$item['stock_qty']} ชิ้น - ขั้นต่ำ {$min} ชิ้น)\n";
                }
            }

            $message .= "━━━━━━━━━━━━━━━━━━\n";
            $message .= "🕒 ข้อมูล ณ วันที่: " . date('d/m/Y H:i') . " น.";

            return self::sendPushMessage($message);
        } catch (Exception $e) {
            error_log("LineService sendStockSummaryAlert error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reply to a message using replyToken
     */
    public static function replyMessage($replyToken, $message, $db = null) {
        try {
            $creds = self::getCredentials($db);
            if (!$creds) return false;

            $token = $creds['token'];
            $lineUrl = "https://api.line.me/v2/bot/message/reply";
            $headers = [
                "Content-Type: application/json",
                "Authorization: Bearer " . $token
            ];
            $payload = [
                'replyToken' => $replyToken,
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => trim($message)
                    ]
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $lineUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            
            $result = curl_exec($ch);
            curl_close($ch);
            return true;
        } catch (Exception $e) {
            error_log("LineService replyMessage error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify LINE Webhook Signature
     */
    public static function verifySignature($body, $signature, $channelSecret = null) {
        $secret = $channelSecret ?: (defined('LINE_CHANNEL_SECRET') ? LINE_CHANNEL_SECRET : (getenv('LINE_CHANNEL_SECRET') ?: ''));
        if (empty($secret)) {
            return true; // If secret is not yet configured, allow to proceed
        }
        if (empty($signature)) {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $body, $secret, true));
        return hash_equals($expected, $signature);
    }

    /**
     * Handle incoming Webhook from LINE Developers
     */
    public static function handleWebhook($payloadJson, $db = null, $signature = null) {
        try {
            if ($signature !== null && !self::verifySignature($payloadJson, $signature)) {
                error_log("LineService Webhook signature verification failed");
                return ['success' => false, 'error' => 'Invalid signature', 'code' => 401];
            }

            $data = json_decode($payloadJson, true);
            if (!$data || empty($data['events'])) {
                return ['success' => true, 'message' => 'No events'];
            }

            foreach ($data['events'] as $event) {
                $replyToken = $event['replyToken'] ?? null;
                $sourceType = $event['source']['type'] ?? '';
                $groupId = $event['source']['groupId'] ?? null;
                $userId = $event['source']['userId'] ?? null;
                $eventType = $event['type'] ?? '';

                // When bot is invited/joins group OR user asks in group
                if ($groupId) {
                    $shouldReply = false;
                    if ($eventType === 'join') {
                        $shouldReply = true;
                    } elseif ($eventType === 'message' && ($event['message']['type'] ?? '') === 'text') {
                        $text = strtolower(trim($event['message']['text'] ?? ''));
                        if (in_array($text, ['id', 'groupid', 'group_id', 'group id', 'รหัสกลุ่ม', 'เช็ครหัส', 'bot', 'hello'])) {
                            $shouldReply = true;
                        }
                    }

                    if ($shouldReply && $replyToken) {
                        $msg = "🎉 สวัสดีครับ! บอท Hello Pet Shop พร้อมทำงานแล้ว\n";
                        $msg .= "━━━━━━━━━━━━━━━━━━\n";
                        $msg .= "📋 รหัส Group ID ของกลุ่มนี้คือ:\n";
                        $msg .= "{$groupId}\n\n";
                        $msg .= "👉 คัดลอกรหัสข้างต้น (ขึ้นต้นด้วย C) ไปใส่ในช่อง 'LINE Target User ID / Group ID' ในหน้าตั้งค่าร้านค้าได้เลยครับ";

                        self::replyMessage($replyToken, $msg, $db);
                    }
                }
            }

            return ['success' => true];
        } catch (Exception $e) {
            error_log("LineService handleWebhook error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>
