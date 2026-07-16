<?php
require 'config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Create gift_rules table
    $createTableSql = "CREATE TABLE IF NOT EXISTS gift_rules (
        rule_id INT AUTO_INCREMENT PRIMARY KEY,
        min_spend DECIMAL(10, 2) UNIQUE NOT NULL,
        gift_name VARCHAR(255) NOT NULL,
        gift_qty INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($createTableSql);
    echo "Table 'gift_rules' created or already exists.\n";

    // 2. Add free_gift column to orders table
    try {
        $db->exec("ALTER TABLE orders ADD COLUMN free_gift VARCHAR(255) DEFAULT NULL");
        echo "Column 'free_gift' added to 'orders' table.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false || $e->getCode() == '42S21') {
            echo "Column 'free_gift' already exists in 'orders' table.\n";
        } else {
            throw $e;
        }
    }

    // 3. Add point_earning_baht and point_earning_qty columns to store_settings
    $columnsToAdd = [
        'point_earning_baht' => "DECIMAL(10, 2) DEFAULT 100.00",
        'point_earning_qty' => "INT DEFAULT 1"
    ];

    foreach ($columnsToAdd as $col => $def) {
        try {
            $db->exec("ALTER TABLE store_settings ADD COLUMN $col $def");
            echo "Added column '$col' to 'store_settings' table.\n";
        } catch (PDOException $e) {
            // Column might already exist
            echo "Column '$col' already exists or error: " . $e->getMessage() . "\n";
        }
    }

    // 4. Check if store_settings has at least one row, if not insert default
    $count = $db->query("SELECT COUNT(*) FROM store_settings")->fetchColumn();
    if ($count == 0) {
        $db->exec("INSERT INTO store_settings (setting_id, updated_by, point_earning_rate, point_redemption_rate, point_earning_baht, point_earning_qty) VALUES (1, 1, 100.00, 1.00, 100.00, 1)");
        echo "Inserted default settings row into 'store_settings'.\n";
    } else {
        echo "Settings row already exists in 'store_settings'.\n";
    }

    echo "Database migrations completed successfully.\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
