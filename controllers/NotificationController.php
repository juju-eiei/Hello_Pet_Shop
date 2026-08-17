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
}
?>
