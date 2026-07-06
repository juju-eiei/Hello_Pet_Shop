<?php
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->getConnection();

function checkTable($db, $tableName) {
    echo "--- Table: $tableName ---\n";
    try {
        $stmt = $db->query("DESCRIBE $tableName");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "{$col['Field']} - {$col['Type']}\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

checkTable($db, 'users');
checkTable($db, 'employees');
checkTable($db, 'roles');
?>
