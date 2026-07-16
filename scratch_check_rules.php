<?php
require 'config/database.php';
$db = (new Database())->getConnection();
echo "--- GIFT RULES ---\n";
$stmt = $db->query("SELECT * FROM gift_rules");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "--- STORE SETTINGS ---\n";
$stmt2 = $db->query("SELECT * FROM store_settings");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
