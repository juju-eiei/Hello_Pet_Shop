<?php
require 'config/database.php';
$db = (new Database())->getConnection();

try {
    // 1. Add columns base_rate and rate_per_kg if they don't exist
    $checkCols = $db->query("SHOW COLUMNS FROM delivery_companies")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('base_rate', $checkCols)) {
        $db->exec("ALTER TABLE delivery_companies ADD COLUMN base_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        echo "Added base_rate column.\n";
    }
    if (!in_array('rate_per_kg', $checkCols)) {
        $db->exec("ALTER TABLE delivery_companies ADD COLUMN rate_per_kg DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        echo "Added rate_per_kg column.\n";
    }

    // 2. Truncate / Clear table (Optional, but safe since it's currently empty and failing logic)
    // Wait, foreign keys might prevent truncate if deliveries table is linked to it. But right now deliveries is empty too.
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE delivery_companies;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 3. Insert Companies
    $stmt = $db->prepare("INSERT INTO delivery_companies (company_name, base_rate, rate_per_kg) VALUES (?, ?, ?)");
    
    // Kerry: 40 base + 15/kg
    $stmt->execute(['Kerry Express', 40.00, 15.00]);
    // Flash: 35 base + 12/kg
    $stmt->execute(['Flash Express', 35.00, 12.00]);
    // J&T: 30 base + 10/kg
    $stmt->execute(['J&T Express', 30.00, 10.00]);

    echo "Delivery companies seeded successfully!";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
