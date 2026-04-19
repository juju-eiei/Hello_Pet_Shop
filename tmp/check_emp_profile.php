<?php
require __DIR__.'/../config/database.php';
$db = (new Database())->getConnection();
$s = $db->query("SELECT * FROM employees WHERE user_id=11");
print_r($s->fetch(PDO::FETCH_ASSOC));
?>
