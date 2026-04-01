<?php
$db = new PDO('mysql:host=localhost;dbname=hello_pet_shop', 'root', '');
echo "CUSTOMERS:\n";
print_r($db->query('DESCRIBE customers')->fetchAll(PDO::FETCH_ASSOC));
echo "\nPETS:\n";
print_r($db->query('DESCRIBE pets')->fetchAll(PDO::FETCH_ASSOC));
?>
