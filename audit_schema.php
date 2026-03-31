<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$table = 'products';
$required_columns = [
    'product_id',
    'category_id',
    'product_name',
    'description',
    'selling_price',
    'cost_price',
    'stock_qty',
    'barcode',
    'weight',
    'image_url',
    'is_active'
];

$stmt = $db->query("DESCRIBE $table");
$actual_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Actual columns in '$table':\n";
foreach ($actual_columns as $col) {
    echo "- $col\n";
}

echo "\nMissing columns:\n";
foreach ($required_columns as $col) {
    if (!in_array($col, $actual_columns)) {
        echo "- $col (MISSING)\n";
    }
}
?>
