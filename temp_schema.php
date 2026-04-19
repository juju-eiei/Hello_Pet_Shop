<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$schema = [];
foreach ($tables as $table) {
    if (in_array($table, ['employees', 'roles', 'permissions'])) {
        $stmt = $db->query("DESCRIBE $table");
        $schema[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
print_r($schema);
?>
