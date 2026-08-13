<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS employee_pay_settings (
        employee_id INT NOT NULL PRIMARY KEY,
        pay_frequency ENUM('Monthly', 'Weekly', 'Daily') NOT NULL DEFAULT 'Monthly',
        monthly_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        weekly_rate DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        daily_rate DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        leave_deduction_per_day DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        absence_deduction_per_day DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_pay_settings_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS attendance_verifications (
        verification_id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        work_date DATE NOT NULL,
        attendance_status ENUM('present', 'leave', 'absent') NOT NULL,
        notes TEXT NULL,
        verified_by INT NULL,
        verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_employee_work_date (employee_id, work_date),
        CONSTRAINT fk_verification_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
        CONSTRAINT fk_verification_user FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "Attendance verification and pay-settings tables are ready.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "Migration failed: " . $e->getMessage() . "\n";
}
