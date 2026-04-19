<?php
require_once __DIR__ . '/config/database.php';
$db = (new Database())->getConnection();
foreach(['customers', 'products', 'orders', 'order_details', 'addresses'] as $t) {
    try {
        $cols = $db->query("DESCRIBE $t")->fetchAll(PDO::FETCH_COLUMN);
        echo "$t: " . implode(', ', $cols) . "\n";
    } catch (Exception $e) { }
}
