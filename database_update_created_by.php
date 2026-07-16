<?php
require 'config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Add created_by column to products
    try {
        $db->exec("ALTER TABLE products ADD COLUMN created_by INT NULL AFTER is_active");
        echo "Added 'created_by' column to products table.\n";
    } catch (PDOException $e) {
        echo "'created_by' column already exists or error: " . $e->getMessage() . "\n";
    }

    // 2. Add foreign key constraint
    try {
        $db->exec("ALTER TABLE products ADD CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES employees(employee_id) ON DELETE SET NULL");
        echo "Added foreign key constraint fk_products_created_by.\n";
    } catch (PDOException $e) {
        echo "Foreign key constraint already exists or error: " . $e->getMessage() . "\n";
    }

    // 3. Set existing products to be created by the first employee (Super Admin, ID 1)
    try {
        $db->exec("UPDATE products SET created_by = 1 WHERE created_by IS NULL");
        echo "Initialized existing products created_by to 1 (Super Admin).\n";
    } catch (PDOException $e) {
        echo "Failed to initialize existing products created_by: " . $e->getMessage() . "\n";
    }

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
