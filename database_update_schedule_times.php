<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Add start_time and end_time columns if they don't exist
    $stmt = $db->query("SHOW COLUMNS FROM work_schedules LIKE 'start_time'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE work_schedules ADD COLUMN start_time TIME NULL AFTER shift_name");
    }

    $stmt2 = $db->query("SHOW COLUMNS FROM work_schedules LIKE 'end_time'");
    if (!$stmt2->fetch()) {
        $db->exec("ALTER TABLE work_schedules ADD COLUMN end_time TIME NULL AFTER start_time");
    }

    echo "Work schedule start_time and end_time columns checked/added successfully.\n";
} catch (Exception $e) {
    echo "Error updating table: " . $e->getMessage() . "\n";
}
?>
