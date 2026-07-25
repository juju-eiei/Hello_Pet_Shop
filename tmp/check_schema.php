<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();

echo "=== USERS TABLE ===\n";
$stmt = $db->query('DESCRIBE users');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo implode(' | ', $row) . "\n";
}

echo "\n=== ROLES TABLE ===\n";
$stmt = $db->query('DESCRIBE roles');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo implode(' | ', $row) . "\n";
}

echo "\n=== SAMPLE USERS ===\n";
$stmt = $db->query('SELECT * FROM users LIMIT 5');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Hide password but show column name
    $cols = array_keys($row);
    echo "Columns: " . implode(', ', $cols) . "\n";
    foreach($row as $k => $v) {
        if (stripos($k, 'pass') !== false) $v = '[HIDDEN]';
        echo "  $k: $v\n";
    }
    echo "---\n";
    break; // Just first row
}
