<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

class ScheduleController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    private function getUserId() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['user_id'] ?? null;
    }

    private function getEmployeeIdFromUser($userId) {
        $stmt = $this->db->prepare("SELECT employee_id FROM employees WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['employee_id'] ?? null;
    }

    public function getMonthlySchedules() {
        try {
            $month = $_GET['month'] ?? date('Y-m');
            $employeeId = isset($_GET['employee_id']) && $_GET['employee_id'] !== '' ? (int)$_GET['employee_id'] : null;

            $sql = "SELECT ws.*, e.first_name, e.last_name, e.position, COALESCE(e.payment_frequency, 'Monthly') AS payment_frequency, u.email
                    FROM work_schedules ws
                    JOIN employees e ON e.employee_id = ws.employee_id
                    LEFT JOIN users u ON u.user_id = e.user_id
                    WHERE DATE_FORMAT(ws.work_date, '%Y-%m') = :month";
            $params = [':month' => $month];

            if ($employeeId) {
                $sql .= " AND ws.employee_id = :emp_id";
                $params[':emp_id'] = $employeeId;
            }

            $sql .= " ORDER BY ws.work_date ASC, e.first_name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $byDate = [];
            $summary = [
                'total_scheduled' => 0,
                'approved_count' => 0,
                'pending_count' => 0,
                'rejected_count' => 0,
                'present_count' => 0,
                'absent_count' => 0,
                'unverified_count' => 0
            ];

            foreach ($schedules as $item) {
                $date = $item['work_date'];
                if (!isset($byDate[$date])) {
                    $byDate[$date] = [
                        'approved_count' => 0,
                        'pending_count' => 0,
                        'rejected_count' => 0,
                        'present_count' => 0,
                        'absent_count' => 0,
                        'employees' => []
                    ];
                }

                $bStatus = $item['booking_status'];
                $aStatus = $item['attendance_status'];

                if ($bStatus === 'approved') {
                    $byDate[$date]['approved_count']++;
                    $summary['approved_count']++;
                } else if ($bStatus === 'pending') {
                    $byDate[$date]['pending_count']++;
                    $summary['pending_count']++;
                } else if ($bStatus === 'rejected') {
                    $byDate[$date]['rejected_count']++;
                    $summary['rejected_count']++;
                }

                if ($aStatus === 'present') {
                    $byDate[$date]['present_count']++;
                    $summary['present_count']++;
                } else if ($aStatus === 'absent') {
                    $byDate[$date]['absent_count']++;
                    $summary['absent_count']++;
                } else {
                    $summary['unverified_count']++;
                }

                $summary['total_scheduled']++;

                // Format work hours display
                $workHours = '10:00 - 18:00';
                if ($item['payment_frequency'] === 'Daily') {
                    if (!empty($item['start_time']) && !empty($item['end_time'])) {
                        $workHours = substr($item['start_time'], 0, 5) . ' - ' . substr($item['end_time'], 0, 5);
                    } else if (!empty($item['shift_name'])) {
                        $workHours = $item['shift_name'];
                    }
                }

                $byDate[$date]['employees'][] = [
                    'schedule_id' => (int)$item['schedule_id'],
                    'employee_id' => (int)$item['employee_id'],
                    'name' => trim($item['first_name'] . ' ' . $item['last_name']),
                    'position' => $item['position'] ?? 'พนักงาน',
                    'payment_frequency' => $item['payment_frequency'],
                    'shift_name' => $workHours,
                    'start_time' => $item['start_time'] ? substr($item['start_time'], 0, 5) : '10:00',
                    'end_time' => $item['end_time'] ? substr($item['end_time'], 0, 5) : '18:00',
                    'booking_status' => $bStatus,
                    'attendance_status' => $aStatus,
                    'notes' => $item['notes']
                ];
            }

            $closedStmt = $this->db->prepare("SELECT closed_date, reason FROM shop_closed_days WHERE DATE_FORMAT(closed_date, '%Y-%m') = :month");
            $closedStmt->execute([':month' => $month]);
            $closedDays = $closedStmt->fetchAll(PDO::FETCH_ASSOC);

            Response::json(200, "Success", [
                'month' => $month,
                'summary' => $summary,
                'by_date' => $byDate,
                'schedules' => $schedules,
                'closed_days' => $closedDays
            ]);
        } catch (Exception $e) {
            Response::json(500, "Error fetching schedules: " . $e->getMessage());
        }
    }

    public function getDateDetails() {
        try {
            $workDate = $_GET['work_date'] ?? date('Y-m-d');

            // Fetch all employees with payment_frequency
            $empStmt = $this->db->query("SELECT employee_id, first_name, last_name, position, COALESCE(payment_frequency, 'Monthly') AS payment_frequency FROM employees ORDER BY first_name, last_name");
            $allEmployees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch schedules for this date
            $sql = "SELECT ws.*, e.first_name, e.last_name, e.position, COALESCE(e.payment_frequency, 'Monthly') AS payment_frequency, u.email
                    FROM work_schedules ws
                    JOIN employees e ON e.employee_id = ws.employee_id
                    LEFT JOIN users u ON u.user_id = e.user_id
                    WHERE ws.work_date = :work_date
                    ORDER BY e.first_name, e.last_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':work_date' => $workDate]);
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $pendingRequests = [];
            $scheduledEmployees = [];

            foreach ($list as $row) {
                $workHours = ($row['payment_frequency'] === 'Daily' && !empty($row['start_time']) && !empty($row['end_time']))
                    ? substr($row['start_time'], 0, 5) . ' - ' . substr($row['end_time'], 0, 5)
                    : ($row['shift_name'] ?: '10:00 - 18:00');

                $item = [
                    'schedule_id' => (int)$row['schedule_id'],
                    'employee_id' => (int)$row['employee_id'],
                    'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                    'position' => $row['position'] ?? 'พนักงาน',
                    'payment_frequency' => $row['payment_frequency'],
                    'shift_name' => $workHours,
                    'start_time' => $row['start_time'] ? substr($row['start_time'], 0, 5) : '10:00',
                    'end_time' => $row['end_time'] ? substr($row['end_time'], 0, 5) : '18:00',
                    'booking_status' => $row['booking_status'],
                    'attendance_status' => $row['attendance_status'],
                    'notes' => $row['notes'],
                    'verified_at' => $row['verified_at']
                ];

                if ($row['booking_status'] === 'pending') {
                    $pendingRequests[] = $item;
                } else if ($row['booking_status'] === 'approved') {
                    $scheduledEmployees[] = $item;
                }
            }

            Response::json(200, "Success", [
                'work_date' => $workDate,
                'pending_requests' => $pendingRequests,
                'scheduled_employees' => $scheduledEmployees,
                'all_schedules' => $list,
                'all_employees' => $allEmployees
            ]);
        } catch (Exception $e) {
            Response::json(500, "Error fetching date details: " . $e->getMessage());
        }
    }

    public function bookSchedule() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $userId = $this->getUserId();
            
            $employeeId = (int)($data['employee_id'] ?? 0);
            if (!$employeeId && $userId) {
                $employeeId = $this->getEmployeeIdFromUser($userId);
            }

            $workDates = $data['work_dates'] ?? $data['work_date'] ?? null;
            $notes = trim($data['notes'] ?? '');

            if (!$employeeId || !$workDates) {
                Response::json(400, "Employee ID and Work Date(s) are required");
                return;
            }

            // Fetch employee's payment_frequency
            $empStmt = $this->db->prepare("SELECT payment_frequency FROM employees WHERE employee_id = :id");
            $empStmt->execute([':id' => $employeeId]);
            $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
            $payFreq = $empRow['payment_frequency'] ?? 'Monthly';

            if ($payFreq === 'Daily') {
                $startTime = !empty($data['start_time']) ? $data['start_time'] : '10:00';
                $endTime = !empty($data['end_time']) ? $data['end_time'] : '18:00';
                $shiftName = $startTime . ' - ' . $endTime;
            } else {
                // Monthly & Weekly fixed 10:00 - 18:00
                $startTime = '10:00';
                $endTime = '18:00';
                $shiftName = '10:00 - 18:00';
            }

            $dates = is_array($workDates) ? $workDates : [$workDates];

            $this->db->beginTransaction();
            $stmt = $this->db->prepare("INSERT INTO work_schedules 
                (employee_id, work_date, shift_name, start_time, end_time, booking_status, attendance_status, notes, created_by)
                VALUES (:emp_id, :work_date, :shift, :start_t, :end_t, 'pending', 'unverified', :notes, :by)
                ON DUPLICATE KEY UPDATE 
                shift_name = VALUES(shift_name),
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                booking_status = 'pending',
                notes = VALUES(notes),
                created_by = VALUES(created_by)");

            foreach ($dates as $d) {
                $stmt->execute([
                    ':emp_id' => $employeeId,
                    ':work_date' => substr($d, 0, 10),
                    ':shift' => $shiftName,
                    ':start_t' => $startTime,
                    ':end_t' => $endTime,
                    ':notes' => $notes ?: null,
                    ':by' => $userId
                ]);
            }

            $this->db->commit();
            Response::json(200, "ส่งคำขอจองตารางงานสำเร็จ รอแอดมินอนุมัติ", ["dates" => $dates, "work_hours" => $shiftName]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            Response::json(500, "Error booking schedule: " . $e->getMessage());
        }
    }

    public function approveBooking() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $scheduleId = (int)($data['schedule_id'] ?? 0);
            $employeeId = (int)($data['employee_id'] ?? 0);
            $workDate = $data['work_date'] ?? null;

            $this->db->beginTransaction();

            if ($scheduleId && (!$employeeId || !$workDate)) {
                $getStmt = $this->db->prepare("SELECT employee_id, work_date FROM work_schedules WHERE schedule_id = :id");
                $getStmt->execute([':id' => $scheduleId]);
                $row = $getStmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $employeeId = (int)$row['employee_id'];
                    $workDate = $row['work_date'];
                }
            }

            if (!$employeeId || !$workDate) {
                Response::json(400, "Schedule ID or (Employee ID and Work Date) required");
                return;
            }

            // Fetch employee's payment_frequency
            $empStmt = $this->db->prepare("SELECT payment_frequency FROM employees WHERE employee_id = :id");
            $empStmt->execute([':id' => $employeeId]);
            $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
            $payFreq = $empRow['payment_frequency'] ?? 'Monthly';

            if ($payFreq === 'Monthly') {
                // For Monthly employee: Approve ALL pending schedules of THIS employee for this ENTIRE month individually
                $targetMonth = date('Y-m', strtotime($workDate));

                $appStmt = $this->db->prepare("UPDATE work_schedules 
                    SET booking_status = 'approved' 
                    WHERE employee_id = :emp_id 
                      AND DATE_FORMAT(work_date, '%Y-%m') = :month 
                      AND booking_status = 'pending'");
                $appStmt->execute([':emp_id' => $employeeId, ':month' => $targetMonth]);
                $approvedCount = $appStmt->rowCount();

                // Sync all approved dates of this employee in that month to attendance_logs
                $logStmt = $this->db->prepare("INSERT IGNORE INTO attendance_logs (employee_id, work_date)
                    SELECT employee_id, work_date FROM work_schedules 
                    WHERE employee_id = :emp_id AND DATE_FORMAT(work_date, '%Y-%m') = :month AND booking_status = 'approved'");
                $logStmt->execute([':emp_id' => $employeeId, ':month' => $targetMonth]);

                $this->db->commit();
                Response::json(200, "อนุมัติคำขอจองตารางงานทั้งเดือนของพนักงานเรียบร้อยแล้ว (" . max(1, $approvedCount) . " วัน)");
            } else {
                // For Daily employee: Approve ONLY this single schedule / work date
                if ($scheduleId) {
                    $stmt = $this->db->prepare("UPDATE work_schedules SET booking_status = 'approved' WHERE schedule_id = :id");
                    $stmt->execute([':id' => $scheduleId]);
                } else {
                    $stmt = $this->db->prepare("UPDATE work_schedules SET booking_status = 'approved' WHERE employee_id = :emp_id AND work_date = :work_date");
                    $stmt->execute([':emp_id' => $employeeId, ':work_date' => $workDate]);
                }

                // Sync with attendance_logs
                $logStmt = $this->db->prepare("INSERT IGNORE INTO attendance_logs (employee_id, work_date) VALUES (:emp_id, :work_date)");
                $logStmt->execute([':emp_id' => $employeeId, ':work_date' => $workDate]);

                $this->db->commit();
                Response::json(200, "อนุมัติคำขอจองตารางงานเรียบร้อยแล้ว");
            }
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            Response::json(500, "Error approving booking: " . $e->getMessage());
        }
    }

    public function rejectBooking() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $scheduleId = (int)($data['schedule_id'] ?? 0);
            $employeeId = (int)($data['employee_id'] ?? 0);
            $workDate = $data['work_date'] ?? null;

            $this->db->beginTransaction();

            if ($scheduleId) {
                $getStmt = $this->db->prepare("SELECT employee_id, work_date FROM work_schedules WHERE schedule_id = :id");
                $getStmt->execute([':id' => $scheduleId]);
                $row = $getStmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $employeeId = $row['employee_id'];
                    $workDate = $row['work_date'];
                }

                $stmt = $this->db->prepare("UPDATE work_schedules SET booking_status = 'rejected' WHERE schedule_id = :id");
                $stmt->execute([':id' => $scheduleId]);
            } else if ($employeeId && $workDate) {
                $stmt = $this->db->prepare("UPDATE work_schedules SET booking_status = 'rejected' WHERE employee_id = :emp_id AND work_date = :work_date");
                $stmt->execute([':emp_id' => $employeeId, ':work_date' => $workDate]);
            } else {
                Response::json(400, "Schedule ID or (Employee ID and Work Date) required");
                return;
            }

            if ($employeeId && $workDate) {
                $delLog = $this->db->prepare("DELETE FROM attendance_logs WHERE employee_id = :emp_id AND work_date = :work_date");
                $delLog->execute([':emp_id' => $employeeId, ':work_date' => $workDate]);
            }

            $this->db->commit();
            Response::json(200, "ปฏิเสธคำขอจองตารางงานเรียบร้อยแล้ว");
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            Response::json(500, "Error rejecting booking: " . $e->getMessage());
        }
    }

    public function verifyAttendance() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $employeeId = (int)($data['employee_id'] ?? 0);
            $workDate = $data['work_date'] ?? '';
            $status = $data['attendance_status'] ?? ''; // 'present' or 'absent'
            $notes = trim($data['notes'] ?? '');

            if (!$employeeId || !$workDate || !in_array($status, ['present', 'absent'], true)) {
                Response::json(400, "Employee ID, work date, and a valid status ('present' or 'absent') are required");
                return;
            }

            $userId = $this->getUserId();
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("INSERT INTO work_schedules 
                (employee_id, work_date, booking_status, attendance_status, notes, verified_by, verified_at)
                VALUES (:emp_id, :work_date, 'approved', :status, :notes, :by, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE 
                booking_status = 'approved',
                attendance_status = VALUES(attendance_status),
                notes = VALUES(notes),
                verified_by = VALUES(verified_by),
                verified_at = CURRENT_TIMESTAMP");
            $stmt->execute([
                ':emp_id' => $employeeId,
                ':work_date' => $workDate,
                ':status' => $status,
                ':notes' => $notes ?: null,
                ':by' => $userId
            ]);

            $logStmt = $this->db->prepare("INSERT INTO attendance_logs (employee_id, work_date) VALUES (:emp_id, :work_date) ON DUPLICATE KEY UPDATE employee_id=employee_id");
            $logStmt->execute([':emp_id' => $employeeId, ':work_date' => $workDate]);

            $verifStatus = ($status === 'present') ? 'present' : 'absent';
            $verifStmt = $this->db->prepare("INSERT INTO attendance_verifications 
                (employee_id, work_date, attendance_status, notes, verified_by)
                VALUES (:emp_id, :work_date, :status, :notes, :by)
                ON DUPLICATE KEY UPDATE 
                attendance_status = VALUES(attendance_status),
                notes = VALUES(notes),
                verified_by = VALUES(verified_by)");
            $verifStmt->execute([
                ':emp_id' => $employeeId,
                ':work_date' => $workDate,
                ':status' => $verifStatus,
                ':notes' => $notes ?: null,
                ':by' => $userId
            ]);

            $this->db->commit();
            $msg = ($status === 'present') ? 'บันทึกสถานะมาทำงานจริงสำเร็จ' : 'บันทึกสถานะลา / ขาดงานสำเร็จ';
            Response::json(200, $msg, ['employee_id' => $employeeId, 'work_date' => $workDate, 'attendance_status' => $status]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            Response::json(500, "Error verifying attendance: " . $e->getMessage());
        }
    }

    public function adminAssignSchedule() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $employeeId = (int)($data['employee_id'] ?? 0);
            $workDates = $data['work_dates'] ?? $data['work_date'] ?? null;
            $notes = trim($data['notes'] ?? '');

            if (!$employeeId || !$workDates) {
                Response::json(400, "Employee ID and Work Date(s) are required");
                return;
            }

            // Fetch payment_frequency
            $empStmt = $this->db->prepare("SELECT payment_frequency FROM employees WHERE employee_id = :id");
            $empStmt->execute([':id' => $employeeId]);
            $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
            $payFreq = $empRow['payment_frequency'] ?? 'Monthly';

            if ($payFreq === 'Daily') {
                $startTime = !empty($data['start_time']) ? $data['start_time'] : '10:00';
                $endTime = !empty($data['end_time']) ? $data['end_time'] : '18:00';
                $shiftName = $startTime . ' - ' . $endTime;
            } else {
                $startTime = '10:00';
                $endTime = '18:00';
                $shiftName = '10:00 - 18:00';
            }

            $dates = is_array($workDates) ? $workDates : [$workDates];
            $userId = $this->getUserId();

            $this->db->beginTransaction();
            $stmt = $this->db->prepare("INSERT INTO work_schedules 
                (employee_id, work_date, shift_name, start_time, end_time, booking_status, attendance_status, notes, created_by)
                VALUES (:emp_id, :work_date, :shift, :start_t, :end_t, 'approved', 'unverified', :notes, :by)
                ON DUPLICATE KEY UPDATE 
                shift_name = VALUES(shift_name),
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                booking_status = 'approved',
                notes = VALUES(notes),
                created_by = VALUES(created_by)");

            $logStmt = $this->db->prepare("INSERT INTO attendance_logs (employee_id, work_date) VALUES (:emp_id, :work_date) ON DUPLICATE KEY UPDATE employee_id=employee_id");

            foreach ($dates as $d) {
                $dateStr = substr($d, 0, 10);
                $stmt->execute([
                    ':emp_id' => $employeeId,
                    ':work_date' => $dateStr,
                    ':shift' => $shiftName,
                    ':start_t' => $startTime,
                    ':end_t' => $endTime,
                    ':notes' => $notes ?: null,
                    ':by' => $userId
                ]);

                $logStmt->execute([
                    ':emp_id' => $employeeId,
                    ':work_date' => $dateStr
                ]);
            }

            $this->db->commit();
            Response::json(200, "มอบหมายตารางงานพนักงานเรียบร้อยแล้ว", ["dates" => $dates, "work_hours" => $shiftName]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            Response::json(500, "Error assigning schedule: " . $e->getMessage());
        }
    }

    public function deleteSchedule() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $scheduleId = (int)($data['schedule_id'] ?? $_GET['schedule_id'] ?? 0);
            $employeeId = (int)($data['employee_id'] ?? $_GET['employee_id'] ?? 0);
            $workDate = $data['work_date'] ?? $_GET['work_date'] ?? null;

            $this->db->beginTransaction();

            $deleted = false;

            if ($scheduleId) {
                $getStmt = $this->db->prepare("SELECT employee_id, work_date FROM work_schedules WHERE schedule_id = :id");
                $getStmt->execute([':id' => $scheduleId]);
                $row = $getStmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $employeeId = $row['employee_id'];
                    $workDate = $row['work_date'];
                }

                $stmt = $this->db->prepare("DELETE FROM work_schedules WHERE schedule_id = :id");
                $stmt->execute([':id' => $scheduleId]);
                if ($stmt->rowCount() > 0) {
                    $deleted = true;
                }
            }

            if (!$deleted && $employeeId && $workDate) {
                $stmt = $this->db->prepare("DELETE FROM work_schedules WHERE employee_id = :emp_id AND work_date = :work_date");
                $stmt->execute([':emp_id' => $employeeId, ':work_date' => $workDate]);
                if ($stmt->rowCount() > 0) {
                    $deleted = true;
                }
            }

            if (!$deleted && !$scheduleId && (!$employeeId || !$workDate)) {
                Response::json(400, "Schedule ID or (Employee ID and Work Date) required");
                return;
            }

            if ($employeeId && $workDate) {
                $this->db->prepare("DELETE FROM attendance_logs WHERE employee_id = :emp_id AND work_date = :work_date")->execute([':emp_id' => $employeeId, ':work_date' => $workDate]);
                $this->db->prepare("DELETE FROM attendance_verifications WHERE employee_id = :emp_id AND work_date = :work_date")->execute([':emp_id' => $employeeId, ':work_date' => $workDate]);
            }

            $this->db->commit();
            Response::json(200, "ยกเลิกตารางงานเรียบร้อยแล้ว");
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            Response::json(500, "Error deleting schedule: " . $e->getMessage());
        }
    }

    public function approveMonthSchedules() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $month = $data['month'] ?? date('Y-m');
            $employeeId = !empty($data['employee_id']) ? (int)$data['employee_id'] : null;

            $this->db->beginTransaction();

            $sql = "SELECT ws.schedule_id, ws.employee_id, ws.work_date 
                    FROM work_schedules ws
                    JOIN employees e ON ws.employee_id = e.employee_id
                    WHERE ws.booking_status = 'pending' 
                      AND e.payment_frequency = 'Monthly'
                      AND DATE_FORMAT(ws.work_date, '%Y-%m') = :month";
            $params = [':month' => $month];

            if ($employeeId) {
                $sql .= " AND ws.employee_id = :emp_id";
                $params[':emp_id'] = $employeeId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $pendingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($pendingRows)) {
                $this->db->commit();
                Response::json(200, "ไม่มีคำขอจองค้างอนุมัติของพนักงานรายเดือนในเดือนนี้", ['count' => 0]);
                return;
            }

            $updateSql = "UPDATE work_schedules ws
                          JOIN employees e ON ws.employee_id = e.employee_id
                          SET ws.booking_status = 'approved' 
                          WHERE ws.booking_status = 'pending' 
                            AND e.payment_frequency = 'Monthly'
                            AND DATE_FORMAT(ws.work_date, '%Y-%m') = :month";
            if ($employeeId) $updateSql .= " AND ws.employee_id = :emp_id";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute($params);

            // Sync all approved schedules to attendance_logs
            $logStmt = $this->db->prepare("INSERT IGNORE INTO attendance_logs (employee_id, work_date) VALUES (:emp_id, :work_date)");
            foreach ($pendingRows as $row) {
                $logStmt->execute([':emp_id' => $row['employee_id'], ':work_date' => $row['work_date']]);
            }

            $this->db->commit();
            Response::json(200, "อนุมัติคำขอจองพนักงานรายเดือนทั้งหมดเรียบร้อยแล้ว (" . count($pendingRows) . " รายการ)", ['count' => count($pendingRows)]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            Response::json(500, "Error approving month schedules: " . $e->getMessage());
        }
    }

    public function getClosedDays() {
        try {
            $month = $_GET['month'] ?? date('Y-m');
            $stmt = $this->db->prepare("SELECT closed_id, closed_date, reason FROM shop_closed_days WHERE DATE_FORMAT(closed_date, '%Y-%m') = :month ORDER BY closed_date");
            $stmt->execute([':month' => $month]);
            Response::json(200, "Success", $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            Response::json(500, "Error fetching closed days: " . $e->getMessage());
        }
    }

    public function toggleClosedDay() {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $closedDate = $data['closed_date'] ?? null;
            $reason = trim($data['reason'] ?? 'วันหยุดประจำร้าน');

            if (!$closedDate) {
                Response::json(400, "Closed Date is required");
                return;
            }

            $checkStmt = $this->db->prepare("SELECT closed_id FROM shop_closed_days WHERE closed_date = :cdate");
            $checkStmt->execute([':cdate' => $closedDate]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Delete if exists (toggle off)
                $delStmt = $this->db->prepare("DELETE FROM shop_closed_days WHERE closed_date = :cdate");
                $delStmt->execute([':cdate' => $closedDate]);
                Response::json(200, "ยกเลิกวันปิดร้านเรียบร้อยแล้ว", ['status' => 'removed', 'closed_date' => $closedDate]);
            } else {
                // Insert closed day
                $insStmt = $this->db->prepare("INSERT INTO shop_closed_days (closed_date, reason, created_by) VALUES (:cdate, :reason, :by)");
                $insStmt->execute([':cdate' => $closedDate, ':reason' => $reason, ':by' => $this->getUserId()]);
                Response::json(200, "บันทึกแจ้งวันปิดร้านเรียบร้อยแล้ว", ['status' => 'added', 'closed_date' => $closedDate, 'reason' => $reason]);
            }
        } catch (Exception $e) {
            Response::json(500, "Error updating closed day: " . $e->getMessage());
        }
    }
}
?>
