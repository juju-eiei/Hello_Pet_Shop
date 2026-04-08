<?php
require __DIR__.'/../config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.username='employee'");
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
