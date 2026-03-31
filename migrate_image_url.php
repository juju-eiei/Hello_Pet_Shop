<?php
require_once 'config/database.php';

function migrate() {
    $database = new Database();
    $db = $database->getConnection();
    
    $table = 'products';
    
    // Check current columns
    $stmt = $db->query("DESCRIBE $table");
    $actual_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('image_url', $actual_columns)) {
        try {
            $db->exec("ALTER TABLE $table ADD COLUMN image_url VARCHAR(255) NULL AFTER weight");
            $msg = "Successfully added 'image_url' column to products table.\n";
            echo $msg;
            file_put_contents('migration_result.txt', $msg);
        } catch (PDOException $e) {
            $msg = "Failed to add 'image_url' column: " . $e->getMessage() . "\n";
            echo $msg;
            file_put_contents('migration_result.txt', $msg);
        }
    } else {
        $msg = "'image_url' column already exists.\n";
        echo $msg;
        file_put_contents('migration_result.txt', $msg);
    }
}

migrate();
?>
