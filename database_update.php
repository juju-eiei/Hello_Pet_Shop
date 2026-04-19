<?php
require 'config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // employees columns
    $employeeColumns = [
        'address' => 'TEXT NULL',
        'base_salary' => 'DECIMAL(10,2) NULL',
        'payment_frequency' => 'VARCHAR(50) NULL',
        'bank_account_details' => 'VARCHAR(255) NULL'
    ];

    foreach ($employeeColumns as $col => $def) {
        try {
            $db->exec("ALTER TABLE employees ADD COLUMN $col $def");
            echo "Added $col to employees.\n";
        } catch (PDOException $e) {
            // Column might already exist
            echo "Column $col already exists or error: " . $e->getMessage() . "\n";
        }
    }

    // users columns
    $userColumns = [
        'permissions' => 'TEXT NULL'
    ];

    foreach ($userColumns as $col => $def) {
        try {
            $db->exec("ALTER TABLE users ADD COLUMN $col $def");
            echo "Added $col to users.\n";
        } catch (PDOException $e) {
            echo "Column $col already exists or error: " . $e->getMessage() . "\n";
        }
    }

    echo "Database migrations completed successfully.\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
