<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
$query = "SELECT product_id, product_name, image_url FROM products";
$stmt = $db->prepare($query);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT);
?>
