<?php
require __DIR__.'/../config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT e.first_name, e.last_name, u.username, u.user_id FROM employees e JOIN users u ON e.user_id = u.user_id WHERE e.first_name LIKE '%จุฬาลักษณ์%' OR e.last_name LIKE '%จุฬาลักษณ์%'");
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if ($emp) {
    $upd = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $upd->execute([password_hash('123456', PASSWORD_DEFAULT), $emp['user_id']]);
    echo 'Username: ' . $emp['username'] . "\nPassword reset to: 123456\n";
} else {
    echo 'Not found';
}
?>
