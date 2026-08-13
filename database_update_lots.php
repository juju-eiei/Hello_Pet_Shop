<?php
require 'config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting database migration...\n";

    // 1. Create product_lots table
    $createLotsTableSql = "
    CREATE TABLE IF NOT EXISTS product_lots (
        lot_id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        lot_number VARCHAR(100) NULL,
        quantity INT NOT NULL DEFAULT 0,
        expiry_date DATE NULL,
        cost_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
    );";
    
    $db->exec($createLotsTableSql);
    echo "Table 'product_lots' created or already exists.\n";

    // 2. Add lot_number to restock_details
    try {
        $db->exec("ALTER TABLE restock_details ADD COLUMN lot_number VARCHAR(100) NULL");
        echo "Column 'lot_number' added to 'restock_details' table.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false || $e->getCode() == '42S21') {
            echo "Column 'lot_number' already exists in 'restock_details' table.\n";
        } else {
            throw $e;
        }
    }

    // 3. Add expiry_date to restock_details
    try {
        $db->exec("ALTER TABLE restock_details ADD COLUMN expiry_date DATE NULL");
        echo "Column 'expiry_date' added to 'restock_details' table.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false || $e->getCode() == '42S21') {
            echo "Column 'expiry_date' already exists in 'restock_details' table.\n";
        } else {
            throw $e;
        }
    }

    // 4. Migrate existing stock as initial lots
    $stmt = $db->query("SELECT product_id, stock_qty, expiry_date, cost_price FROM products WHERE stock_qty > 0");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtInsertLot = $db->prepare("INSERT INTO product_lots (product_id, lot_number, quantity, expiry_date, cost_price) VALUES (?, 'INITIAL', ?, ?, ?)");
    $migratedCount = 0;

    foreach ($products as $p) {
        $chk = $db->prepare("SELECT COUNT(*) FROM product_lots WHERE product_id = ?");
        $chk->execute([$p['product_id']]);
        if ($chk->fetchColumn() == 0) {
            $stmtInsertLot->execute([
                $p['product_id'],
                $p['stock_qty'],
                !empty($p['expiry_date']) ? $p['expiry_date'] : null,
                $p['cost_price']
            ]);
            $migratedCount++;
        }
    }

    echo "Stock migration completed: {$migratedCount} products migrated to initial lots.\n";
    echo "Database migration completed successfully.\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
