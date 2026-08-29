<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/LineService.php';

class NotificationController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Retrieve active stock alerts (low stock & near expiry)
     */
    public function getAlerts() {
        try {
            // 1. Fetch low stock products (stock_qty <= min_stock_level, defaulting to 5 if min_stock_level is null or 0)
            $qLowStock = "SELECT product_id, product_name, stock_qty, min_stock_level, image_url 
                          FROM products 
                          WHERE is_active = 1 
                            AND (
                              (min_stock_level IS NULL OR min_stock_level = 0) AND stock_qty <= 5
                              OR
                              (min_stock_level IS NOT NULL AND min_stock_level > 0 AND stock_qty <= min_stock_level)
                            )
                          ORDER BY stock_qty ASC";
            
            $stmt = $this->db->query($qLowStock);
            $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Fetch products near expiry / expired (expiry_date <= 30 days from today and stock_qty > 0)
            $qNearExpiry = "SELECT p.product_id, p.product_name, l.quantity as stock_qty, l.expiry_date, p.image_url 
                            FROM product_lots l
                            JOIN products p ON l.product_id = p.product_id
                            WHERE p.is_active = 1 
                              AND l.expiry_date IS NOT NULL 
                              AND l.expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
                              AND l.quantity > 0
                            UNION
                            SELECT p.product_id, p.product_name, p.stock_qty, p.expiry_date, p.image_url
                            FROM products p
                            WHERE p.is_active = 1
                              AND p.expiry_date IS NOT NULL
                              AND p.expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
                              AND p.stock_qty > 0
                              AND p.product_id NOT IN (SELECT DISTINCT product_id FROM product_lots WHERE quantity > 0 AND expiry_date IS NOT NULL)
                            ORDER BY expiry_date ASC";
            
            $stmt2 = $this->db->query($qNearExpiry);
            $nearExpiryItems = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'low_stock' => $lowStockItems,
                'near_expiry' => $nearExpiryItems,
                'total_alerts' => count($lowStockItems) + count($nearExpiryItems)
            ];

            Response::json(200, "Success", $response);
        } catch (Exception $e) {
            Response::json(500, "Error fetching alerts: " . $e->getMessage());
        }
    }

    /**
     * Send alert report to LINE Messaging API channel
     */
    public function sendLineNotification() {
        try {
            $result = LineService::sendStockSummaryAlert($this->db);
            if ($result['success']) {
                Response::json(200, $result['message']);
            } else {
                Response::json(400, $result['message'], $result);
            }
        } catch (Exception $e) {
            Response::json(500, "Error sending LINE notification: " . $e->getMessage());
        }
    }

    /**
     * Test LINE Connection with provided or saved token & target ID
     */
    public function testLineConnection() {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            $token = !empty($data['token']) ? trim($data['token']) : null;
            $targetId = !empty($data['target_id']) ? trim($data['target_id']) : null;

            $testMsg = "🔔 ทดสอบการเชื่อมต่อ LINE Messaging API สำเร็จ!\n";
            $testMsg .= "━━━━━━━━━━━━━━━━━━\n";
            $testMsg .= "ระบบ Hello Pet Shop สามารถส่งการแจ้งเตือนเข้าห้องแชทนี้ได้แล้ว 🎉\n";
            $testMsg .= "🕒 เวลาทดสอบ: " . date('d/m/Y H:i:s') . " น.";

            $result = LineService::sendPushMessage($testMsg, $token, $targetId);
            if ($result['success']) {
                Response::json(200, "เชื่อมต่อและส่งข้อความทดสอบสำเร็จแล้ว! กรุณาเช็คในแอป LINE", $result);
            } else {
                Response::json(400, "ทดสอบไม่สำเร็จ: " . $result['message'], $result);
            }
        } catch (Exception $e) {
            Response::json(500, "Error testing LINE connection: " . $e->getMessage());
        }
    }

    /**
     * Test LINE Purchase Notification
     */
    public function testPurchaseAlert() {
        try {
            // Find latest order or fallback to demo
            $stmt = $this->db->query("SELECT order_id FROM orders ORDER BY order_id DESC LIMIT 1");
            $lastOrder = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lastOrder && !empty($lastOrder['order_id'])) {
                $result = LineService::sendNewOrderAlert($lastOrder['order_id'], $this->db);
            } else {
                $sampleMsg = "🛍️ [ทดสอบระบบ] มีคำสั่งซื้อใหม่เข้ามา!\n";
                $sampleMsg .= "━━━━━━━━━━━━━━━━━━\n";
                $sampleMsg .= "📋 รหัสสั่งซื้อ: #ORD-TEST-999\n";
                $sampleMsg .= "👤 ลูกค้า: คุณ ลูกค้าทดสอบระบบ\n";
                $sampleMsg .= "📞 เบอร์ติดต่อ: 081-234-5678\n";
                $sampleMsg .= "📦 รายการสินค้า:\n";
                $sampleMsg .= "   • อาหารสุนัขพรีเมียม 1.5kg x 2 (฿700.00)\n";
                $sampleMsg .= "   • ของเล่นเชือกกัด x 1 (฿120.00)\n";
                $sampleMsg .= "🎁 ของแถมพิเศษ: ขนมขบเคี้ยวสัตว์เลี้ยง\n";
                $sampleMsg .= "🚚 ขนส่ง: Kerry Express (ส่งฟรี)\n";
                $sampleMsg .= "📍 ที่อยู่จัดส่ง: กรุงเทพมหานคร 10400\n";
                $sampleMsg .= "💰 ยอดชำระสุทธิ: ฿820.00\n";
                $sampleMsg .= "━━━━━━━━━━━━━━━━━━\n";
                $sampleMsg .= "🕒 เวลา: " . date('d/m/Y H:i') . " น.\n";
                $sampleMsg .= "👉 นี่คือข้อความทดสอบการแจ้งเตือนการซื้อสินค้า";
                $result = LineService::sendPushMessage($sampleMsg);
            }

            if ($result && (is_array($result) ? $result['success'] : true)) {
                Response::json(200, "ส่งข้อความทดสอบแจ้งเตือนการซื้อสินค้าเข้า LINE เรียบร้อยแล้ว");
            } else {
                $err = is_array($result) ? ($result['message'] ?? 'เกิดข้อผิดพลาด') : 'ไม่สามารถส่งข้อความได้';
                Response::json(400, "ส่งทดสอบไม่สำเร็จ: " . $err);
            }
        } catch (Exception $e) {
            Response::json(500, "Error testing purchase alert: " . $e->getMessage());
        }
    }

    /**
     * Test LINE Payment Notification
     */
    public function testPaymentAlert() {
        try {
            $stmt = $this->db->query("SELECT order_id FROM payments WHERE slip_image IS NOT NULL ORDER BY payment_id DESC LIMIT 1");
            $lastPay = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($lastPay && !empty($lastPay['order_id'])) {
                $result = LineService::sendPaymentAlert($lastPay['order_id'], 'submitted', $this->db);
            } else {
                $sampleMsg = "💵 [ทดสอบระบบ] ได้รับแจ้งชำระเงินใหม่! (แนบสลิปแล้ว)\n";
                $sampleMsg .= "━━━━━━━━━━━━━━━━━━\n";
                $sampleMsg .= "📋 รหัสสั่งซื้อ: #ORD-TEST-999\n";
                $sampleMsg .= "👤 ลูกค้า: คุณ ลูกค้าทดสอบระบบ\n";
                $sampleMsg .= "📞 เบอร์ติดต่อ: 081-234-5678\n";
                $sampleMsg .= "💰 ยอดเงินที่แจ้งชำระ: ฿820.00\n";
                $sampleMsg .= "💳 วิธีการชำระเงิน: โอนผ่านธนาคาร / พร้อมเพย์\n";
                $sampleMsg .= "📎 หลักฐาน: แนบสลิปเรียบร้อยแล้ว (รอตรวจสอบ)\n";
                $sampleMsg .= "━━━━━━━━━━━━━━━━━━\n";
                $sampleMsg .= "🕒 เวลาแจ้ง: " . date('d/m/Y H:i') . " น.\n";
                $sampleMsg .= "👉 นี่คือข้อความทดสอบการแจ้งเตือนการชำระเงิน";
                $result = LineService::sendPushMessage($sampleMsg);
            }

            if ($result && (is_array($result) ? $result['success'] : true)) {
                Response::json(200, "ส่งข้อความทดสอบแจ้งเตือนการชำระเงินเข้า LINE เรียบร้อยแล้ว");
            } else {
                $err = is_array($result) ? ($result['message'] ?? 'เกิดข้อผิดพลาด') : 'ไม่สามารถส่งข้อความได้';
                Response::json(400, "ส่งทดสอบไม่สำเร็จ: " . $err);
            }
        } catch (Exception $e) {
            Response::json(500, "Error testing payment alert: " . $e->getMessage());
        }
    }

    /**
     * Test LINE Cancellation Notification
     */
    public function testCancelAlert() {
        try {
            $stmt = $this->db->query("SELECT order_id FROM orders WHERE status = 5 ORDER BY order_id DESC LIMIT 1");
            $lastCancel = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($lastCancel && !empty($lastCancel['order_id'])) {
                $result = LineService::sendOrderCancelledAlert($lastCancel['order_id'], 'ทดสอบระบบการยกเลิกคำสั่งซื้อ', 'เจ้าหน้าที่ทดสอบระบบ', $this->db);
            } else {
                $sampleMsg = "❌ [ทดสอบระบบ] แจ้งเตือนการยกเลิกคำสั่งซื้อ!\n";
                $sampleMsg .= "━━━━━━━━━━━━━━━━━━\n";
                $sampleMsg .= "📋 รหัสคำสั่งซื้อ: #ORD-TEST-999\n";
                $sampleMsg .= "👤 ลูกค้า: คุณ ลูกค้าทดสอบระบบ\n";
                $sampleMsg .= "📞 เบอร์ติดต่อ: 081-234-5678\n";
                $sampleMsg .= "📦 รายการสินค้าที่ถูกยกเลิก:\n";
                $sampleMsg .= "   • อาหารสุนัขพรีเมียม 1.5kg x 2 (฿700.00)\n";
                $sampleMsg .= "   • ของเล่นเชือกกัด x 1 (฿120.00)\n";
                $sampleMsg .= "💰 มูลค่าที่ยกเลิก: ฿820.00\n";
                $sampleMsg .= "⚠️ สาเหตุ: ทดสอบระบบแจ้งเตือนการยกเลิกสินค้า\n";
                $sampleMsg .= "👤 ดำเนินการโดย: ผู้ดูแลระบบ\n";
                $sampleMsg .= "🔄 สต็อกสินค้า: คืนจำนวนเข้าคลังเรียบร้อยแล้ว\n";
                $sampleMsg .= "━━━━━━━━━━━━━━━━━━\n";
                $sampleMsg .= "🕒 เวลา: " . date('d/m/Y H:i') . " น.\n";
                $sampleMsg .= "👉 นี่คือข้อความทดสอบการแจ้งเตือนการยกเลิกคำสั่งซื้อ";
                $result = LineService::sendPushMessage($sampleMsg);
            }

            if ($result && (is_array($result) ? $result['success'] : true)) {
                Response::json(200, "ส่งข้อความทดสอบแจ้งเตือนการยกเลิกสินค้าเข้า LINE เรียบร้อยแล้ว");
            } else {
                $err = is_array($result) ? ($result['message'] ?? 'เกิดข้อผิดพลาด') : 'ไม่สามารถส่งข้อความได้';
                Response::json(400, "ส่งทดสอบไม่สำเร็จ: " . $err);
            }
        } catch (Exception $e) {
            Response::json(500, "Error testing cancel alert: " . $e->getMessage());
        }
    }
}
?>
