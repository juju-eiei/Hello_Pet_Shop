<?php
require 'config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Add received_by column to restock_orders table
    try {
        $db->exec("ALTER TABLE restock_orders ADD COLUMN received_by INT NULL");
        echo "Column 'received_by' added to 'restock_orders' table.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false || $e->getCode() == '42S21') {
            echo "Column 'received_by' already exists in 'restock_orders' table.\n";
        } else {
            throw $e;
        }
    }

    // Add foreign key constraint for received_by
    try {
        $db->exec("ALTER TABLE restock_orders ADD CONSTRAINT fk_restock_orders_received_by FOREIGN KEY (received_by) REFERENCES employees(employee_id) ON DELETE SET NULL");
        echo "Foreign key constraint fk_restock_orders_received_by added.\n";
    } catch (PDOException $e) {
        echo "Foreign key constraint could not be added or already exists.\n";
    }

    echo "Database migrations completed successfully.\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
