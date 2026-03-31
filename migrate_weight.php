<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    
    // Check if column already exists
    $stmt = $db->query("SHOW COLUMNS FROM products LIKE 'weight'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $db->exec("ALTER TABLE products ADD COLUMN weight VARCHAR(50) AFTER barcode");
        echo "Successfully added 'weight' column to products table.\n";
    } else {
        echo "Column 'weight' already exists.\n";
    }
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
