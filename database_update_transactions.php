<?php
require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting financial_transactions table migration...\n";

    $createTableSql = "
    CREATE TABLE IF NOT EXISTS financial_transactions (
        transaction_id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('income', 'expense') NOT NULL,
        category VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL,
        amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        description TEXT NULL,
        transaction_date DATE NOT NULL,
        reference_type ENUM('manual', 'order', 'restock', 'salary') DEFAULT 'manual',
        reference_id INT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $db->exec($createTableSql);
    echo "Table 'financial_transactions' created or already exists.\n";

    // Add indexes for fast querying
    try {
        $db->exec("CREATE INDEX idx_trans_date ON financial_transactions(transaction_date)");
        $db->exec("CREATE INDEX idx_trans_type ON financial_transactions(type)");
    } catch (PDOException $e) {
        // Indexes might already exist
    }

    echo "Database migration for financial transactions completed successfully.\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
