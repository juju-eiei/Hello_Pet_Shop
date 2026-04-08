<?php
require __DIR__.'/../config/database.php';
$db = (new Database())->getConnection();
$upd = $db->prepare('UPDATE users SET password = ? WHERE username = ?');
$upd->execute([password_hash('password', PASSWORD_DEFAULT), 'employee']);
echo "Password for 'employee' has been reset to 'password'.";
?>
