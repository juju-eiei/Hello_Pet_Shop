<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table for Pay Right Settings (สิทธิ์การจ่ายเงิน)
    $db->exec("CREATE TABLE IF NOT EXISTS pay_right_settings (
        pay_frequency ENUM('Monthly', 'Weekly', 'Daily') NOT NULL PRIMARY KEY,
        right_name VARCHAR(100) NOT NULL,
        default_rate DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        leave_deduction_per_day DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        absence_deduction_per_day DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Insert default rows if empty
    $stmt = $db->query("SELECT COUNT(*) FROM pay_right_settings");
    if ((int)$stmt->fetchColumn() === 0) {
        $db->exec("INSERT INTO pay_right_settings (pay_frequency, right_name, default_rate, leave_deduction_per_day, absence_deduction_per_day) VALUES
            ('Monthly', 'สิทธิ์รายเดือน', 15000.00, 500.00, 500.00),
            ('Weekly', 'สิทธิ์รายสัปดาห์', 800.00, 150.00, 150.00),
            ('Daily', 'สิทธิ์รายวัน', 300.00, 50.00, 50.00)
        ");
        echo "Default pay right settings inserted successfully.\n";
    } else {
        echo "pay_right_settings table already exists and has data.\n";
    }

    echo "Pay right settings table is ready.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
