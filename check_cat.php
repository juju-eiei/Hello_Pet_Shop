<?php
require_once __DIR__ . '/config/database.php';
try {
    $db = (new Database())->getConnection();
    echo "<h1>Category Table Schema</h1>";
    $stmt = $db->query("DESCRIBE product_categories");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
