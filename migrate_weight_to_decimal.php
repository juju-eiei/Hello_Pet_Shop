<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    
    echo "Starting weight column migration...\n";

    // 1. Add new columns weight_value and weight_unit if they do not exist
    $stmt = $db->query("SHOW COLUMNS FROM products LIKE 'weight_value'");
    $existsValue = $stmt->fetch();
    if (!$existsValue) {
        $db->exec("ALTER TABLE products ADD COLUMN weight_value DECIMAL(10,3) DEFAULT NULL AFTER weight");
        echo "Added 'weight_value' column.\n";
    }

    $stmt = $db->query("SHOW COLUMNS FROM products LIKE 'weight_unit'");
    $existsUnit = $stmt->fetch();
    if (!$existsUnit) {
        $db->exec("ALTER TABLE products ADD COLUMN weight_unit VARCHAR(20) DEFAULT NULL AFTER weight_value");
        echo "Added 'weight_unit' column.\n";
    }

    // 2. Fetch all products to parse and migrate their weights
    $stmt = $db->query("SELECT product_id, weight FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Parsing and migrating " . count($products) . " products...\n";

    $updateStmt = $db->prepare("UPDATE products SET weight = :weight, weight_value = :weight_value, weight_unit = :weight_unit WHERE product_id = :id");

    foreach ($products as $p) {
        $parsed = parseWeightString($p['weight']);
        
        $updateStmt->execute([
            ':weight' => $parsed['kg'],
            ':weight_value' => $parsed['val'],
            ':weight_unit' => $parsed['unit'],
            ':id' => $p['product_id']
        ]);
    }
    
    echo "Updated product records with parsed weights.\n";

    // 3. Alter weight column type to DECIMAL(10,3)
    $db->exec("ALTER TABLE products MODIFY COLUMN weight DECIMAL(10,3) DEFAULT NULL");
    echo "Successfully altered 'weight' column type to DECIMAL(10,3).\n";

    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}

function parseWeightString($str) {
    if ($str === null || trim($str) === '') {
        return ['val' => null, 'unit' => null, 'kg' => null];
    }
    $str = trim($str);
    
    // Check if it ends with kg (case-insensitive)
    if (preg_match('/^([0-9.]+)\s*kg$/i', $str, $matches)) {
        $val = (float)$matches[1];
        return ['val' => $val, 'unit' => 'kg', 'kg' => $val];
    }
    // Check if it ends with g (case-insensitive)
    if (preg_match('/^([0-9.]+)\s*g$/i', $str, $matches)) {
        $val = (float)$matches[1];
        return ['val' => $val, 'unit' => 'g', 'kg' => $val / 1000];
    }
    // Check if it ends with ml (case-insensitive)
    if (preg_match('/^([0-9.]+)\s*ml$/i', $str, $matches)) {
        $val = (float)$matches[1];
        return ['val' => $val, 'unit' => 'ml', 'kg' => $val / 1000];
    }
    // Check if it ends with l (case-insensitive)
    if (preg_match('/^([0-9.]+)\s*l$/i', $str, $matches)) {
        $val = (float)$matches[1];
        return ['val' => $val, 'unit' => 'L', 'kg' => $val];
    }
    // Check if it contains "แผ่น"
    if (preg_match('/^([0-9.]+)\s*แผ่น$/u', $str, $matches)) {
        $val = (float)$matches[1];
        // 80 sheets = 160g (0.160 kg) so weight_value is 160, unit is g, and kg is 0.16
        $calculated_grams = $val * 2; // 2g per sheet
        return ['val' => $calculated_grams, 'unit' => 'g', 'kg' => $calculated_grams / 1000];
    }
    // Check if it contains "ชิ้น"
    if (preg_match('/^([0-9.]+)\s*ชิ้น$/u', $str, $matches)) {
        $val = (float)$matches[1];
        // 1 piece = 100g (0.100 kg) so weight_value is 100, unit is g, and kg is 0.10
        $calculated_grams = $val * 100; // 100g per piece
        return ['val' => $calculated_grams, 'unit' => 'g', 'kg' => $calculated_grams / 1000];
    }
    
    // If it is just a pure number
    if (is_numeric($str)) {
        $val = (float)$str;
        return ['val' => $val, 'unit' => 'kg', 'kg' => $val];
    }
    
    // Otherwise fallback
    return ['val' => null, 'unit' => 'kg', 'kg' => null];
}
?>
