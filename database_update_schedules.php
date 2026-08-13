<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table for Work Schedules & Bookings
    $db->exec("CREATE TABLE IF NOT EXISTS work_schedules (
        schedule_id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        work_date DATE NOT NULL,
        shift_name VARCHAR(50) NOT NULL DEFAULT 'กะปกติ (09:00 - 18:00)',
        booking_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
        attendance_status ENUM('unverified', 'present', 'absent') NOT NULL DEFAULT 'unverified',
        notes TEXT NULL,
        created_by INT NULL,
        verified_by INT NULL,
        verified_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_emp_work_date (employee_id, work_date),
        CONSTRAINT fk_schedule_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Sync existing attendance_logs into work_schedules if not present
    $db->exec("INSERT INTO work_schedules (employee_id, work_date, booking_status, attendance_status, created_at)
        SELECT al.employee_id, al.work_date, 'approved', 
               CASE WHEN av.attendance_status = 'present' THEN 'present' 
                    WHEN av.attendance_status IN ('leave', 'absent') THEN 'absent'
                    ELSE 'unverified' END,
               CURRENT_TIMESTAMP
        FROM attendance_logs al
        LEFT JOIN attendance_verifications av ON av.employee_id = al.employee_id AND av.work_date = al.work_date
        ON DUPLICATE KEY UPDATE 
           attendance_status = VALUES(attendance_status)");

    echo "Work schedules table created and existing logs synced successfully.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
