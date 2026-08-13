<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

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
            // 1. Fetch LINE settings
            $stmtToken = $this->db->query("SELECT line_oa_token, line_target_id FROM store_settings LIMIT 1");
            $setting = $stmtToken->fetch(PDO::FETCH_ASSOC);
            $token = $setting['line_oa_token'] ?? '';
            $targetId = $setting['line_target_id'] ?? '';

            if (empty($token) || empty($targetId)) {
                Response::json(400, "กรุณาตั้งค่า LINE Channel Access Token และ Target ID ในหน้าตั้งค่าร้านค้าก่อนใช้งาน");
                return;
            }

            // 2. Fetch alerting items
            // Low stock
            $qLow = "SELECT product_name, stock_qty, min_stock_level FROM products 
                     WHERE is_active = 1 
                       AND (
                         (min_stock_level IS NULL OR min_stock_level = 0) AND stock_qty <= 5
                         OR
                         (min_stock_level IS NOT NULL AND min_stock_level > 0 AND stock_qty <= min_stock_level)
                       )";
            $lowStock = $this->db->query($qLow)->fetchAll(PDO::FETCH_ASSOC);

            // Near expiry
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
            $nearExpiry = $this->db->query($qExp)->fetchAll(PDO::FETCH_ASSOC);

            if (count($lowStock) === 0 && count($nearExpiry) === 0) {
                Response::json(200, "ไม่มีข้อมูลสินค้าใกล้หมดหรือหมดอายุที่ต้องแจ้งเตือนในขณะนี้");
                return;
            }

            // 3. Format message in Thai
            $message = "📢 แจ้งเตือนสถานะสินค้า Hello Pet Shop ประจำวัน\n";
            
            if (count($nearExpiry) > 0) {
                $message .= "\n🚨 สินค้าใกล้หมดอายุ / หมดอายุแล้ว:\n";
                foreach ($nearExpiry as $item) {
                    $formattedDate = date('d/m/Y', strtotime($item['expiry_date']));
                    $message .= "- {$item['product_name']} (หมดอายุ: {$formattedDate}) เหลือ {$item['stock_qty']} ชิ้น\n";
                }
            }

            if (count($lowStock) > 0) {
                $message .= "\n📦 สินค้าใกล้หมดสต็อก / หมดสต็อก:\n";
                foreach ($lowStock as $item) {
                    $min = $item['min_stock_level'] > 0 ? $item['min_stock_level'] : 5;
                    $message .= "- {$item['product_name']} (เหลือ {$item['stock_qty']} ชิ้น - ขั้นต่ำ {$min} ชิ้น)\n";
                }
            }

            // 4. Post request to LINE Messaging API
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
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                Response::json(200, "ส่งรายงานไปที่ LINE เรียบร้อยแล้ว");
            } else {
                $resData = json_decode($result, true);
                $errorMsg = $resData['message'] ?? 'เกิดข้อผิดพลาดในการเชื่อมต่อ LINE Messaging API';
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
                Response::json($httpCode, "ส่งข้อความไม่สำเร็จ: " . $errorMsg, ["raw" => $result]);
            }

        } catch (Exception $e) {
            Response::json(500, "Error sending LINE notification: " . $e->getMessage());
        }
    }
}
?>
