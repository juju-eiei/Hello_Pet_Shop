<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();

$stmt = $db->query('SELECT user_id, username, password, role_id FROM users LIMIT 5');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "User #{$row['user_id']}: {$row['username']} (role_id: {$row['role_id']})\n";
    echo "  Hash: {$row['password']}\n";
    
    // Test common passwords
    $passwords = ['password', 'admin', '123456', 'admin123', 'Password1', '12345678', 'password123'];
    foreach ($passwords as $pw) {
        if (password_verify($pw, $row['password'])) {
            echo "  >>> MATCH: password = '$pw'\n";
            break;
        }
    }
    echo "\n";
}
