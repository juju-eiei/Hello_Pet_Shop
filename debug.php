<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Product.php';

try {
    echo "<h1>Debug Info</h1>";
    
    echo "<h2>1. Testing Database Connection...</h2>";
    $db = (new Database())->getConnection();
    echo "Connection Success! <br>";

    echo "<h2>2. Checking Table Schema...</h2>";
    $stmt = $db->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_weight = false;
    foreach($columns as $col) {
        if($col['Field'] == 'weight') $has_weight = true;
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
    }

    if(!$has_weight) {
        echo "<h3 style='color:red;'>MISSING 'weight' COLUMN! Attempting to fix...</h3>";
        $db->exec("ALTER TABLE products ADD COLUMN weight VARCHAR(50) AFTER barcode");
        echo "Fixed! <br>";
    } else {
        echo "Schema looks OK. <br>";
    }

    echo "<h2>3. Testing Product Query...</h2>";
    $product = new Product($db);
    $all = $product->getAll();
    $data = $all->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($data) . " products. <br>";
    
    echo "<h2>4. Testing Show Route...</h2>";
    if(count($data) > 0) {
        $first_id = $data[0]['id'];
        $single = $product->getById($first_id);
        echo "getById($first_id) returned: " . ($single ? "Object" : "NULL") . "<br>";
    }

    echo "<h2 style='color:green;'>All checks PASSED.</h2>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>ERROR: " . $e->getMessage() . "</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
