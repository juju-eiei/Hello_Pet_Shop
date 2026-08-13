<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Add hourly_rate, work_start_time, work_end_time to pay_right_settings table
    $stmt = $db->query("SHOW COLUMNS FROM pay_right_settings LIKE 'hourly_rate'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE pay_right_settings ADD COLUMN hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 50.00 AFTER default_rate");
    }

    $stmt2 = $db->query("SHOW COLUMNS FROM pay_right_settings LIKE 'work_start_time'");
    if (!$stmt2->fetch()) {
        $db->exec("ALTER TABLE pay_right_settings ADD COLUMN work_start_time TIME NOT NULL DEFAULT '10:00:00' AFTER hourly_rate");
    }

    $stmt3 = $db->query("SHOW COLUMNS FROM pay_right_settings LIKE 'work_end_time'");
    if (!$stmt3->fetch()) {
        $db->exec("ALTER TABLE pay_right_settings ADD COLUMN work_end_time TIME NOT NULL DEFAULT '18:00:00' AFTER work_start_time");
    }

    // Set defaults
    $db->exec("UPDATE pay_right_settings SET work_start_time = '10:00:00', work_end_time = '18:00:00' WHERE work_start_time IS NULL OR work_start_time = '00:00:00'");
    $db->exec("UPDATE pay_right_settings SET hourly_rate = 50.00 WHERE pay_frequency = 'Daily' AND (hourly_rate IS NULL OR hourly_rate = 0)");

    echo "pay_right_settings columns updated successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
