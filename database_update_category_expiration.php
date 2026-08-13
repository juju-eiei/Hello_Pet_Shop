<?php
require 'config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting database migration...\n";

    // Add requires_expiration to product_categories table
    try {
        $db->exec("ALTER TABLE product_categories ADD COLUMN requires_expiration TINYINT(1) NOT NULL DEFAULT 1");
        echo "Column 'requires_expiration' added to 'product_categories' table successfully.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false || $e->getCode() == '42S21') {
            echo "Column 'requires_expiration' already exists in 'product_categories' table.\n";
        } else {
            throw $e;
        }
    }

    echo "Database migration completed successfully.\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
