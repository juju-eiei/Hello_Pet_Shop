<?php
require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting salary_payments table migration...\n";

    $createTableSql = "
    CREATE TABLE IF NOT EXISTS salary_payments (
        payment_id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        pay_period VARCHAR(20) NOT NULL COMMENT 'Format YYYY-MM',
        base_salary DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        bonus DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        deductions DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        net_paid DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        payment_date DATE NOT NULL,
        payment_method ENUM('transfer', 'cash', 'cheque') DEFAULT 'transfer',
        notes TEXT NULL,
        transaction_id INT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
        FOREIGN KEY (transaction_id) REFERENCES financial_transactions(transaction_id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
        UNIQUE KEY idx_emp_period (employee_id, pay_period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $db->exec($createTableSql);
    echo "Table 'salary_payments' created or already exists.\n";

    echo "Database migration for salary payments completed successfully.\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
