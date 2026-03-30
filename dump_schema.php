<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

function dumpTable($db, $table) {
    echo "<h3>Table: $table</h3>";
    $query = $db->query("DESCRIBE $table");
    echo "<table border='1'>";
    while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>" . implode("</td><td>", $row) . "</td></tr>";
    }
    echo "</table>";
}

dumpTable($db, 'users');
dumpTable($db, 'roles');
dumpTable($db, 'customers');
?>
