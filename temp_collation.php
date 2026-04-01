<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.tables WHERE table_schema = 'hello_pet_shop'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
