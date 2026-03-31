<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
$stmt = $db->query("DESCRIBE products");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
