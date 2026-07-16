<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

class AttendanceController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function checkIn() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $employee_id = $data['employee_id'] ?? null;
        $work_date = $data['work_date'] ?? date('Y-m-d');

        if (!$employee_id) {
            Response::json(400, "Employee ID is required");
        }

        $dates = is_array($work_date) ? $work_date : [$work_date];

        try {
            $this->db->beginTransaction();
            // Using ON DUPLICATE KEY UPDATE to handle existing log gracefully
            $sql = "INSERT INTO attendance_logs (employee_id, work_date) 
                    VALUES (:employee_id, :work_date) 
                    ON DUPLICATE KEY UPDATE employee_id = employee_id";
            $stmt = $this->db->prepare($sql);
            
            foreach ($dates as $date) {
                $stmt->execute([
                    ':employee_id' => $employee_id,
                    ':work_date' => $date
                ]);
            }

            $this->db->commit();
            Response::json(200, "Checked in successfully", ["employee_id" => $employee_id, "work_dates" => $dates]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Response::json(500, "Error checking in: " . $e->getMessage());
        }
    }

    public function checkOut() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $employee_id = $data['employee_id'] ?? null;
        $work_date = $data['work_date'] ?? null;

        if (!$employee_id || !$work_date) {
            Response::json(400, "Employee ID and Date are required");
        }

        $dates = is_array($work_date) ? $work_date : [$work_date];

        try {
            $this->db->beginTransaction();
            $sql = "DELETE FROM attendance_logs WHERE employee_id = :employee_id AND work_date = :work_date";
            $stmt = $this->db->prepare($sql);
            
            foreach ($dates as $date) {
                $stmt->execute([
                    ':employee_id' => $employee_id,
                    ':work_date' => $date
                ]);
            }

            $this->db->commit();
            Response::json(200, "Checked out successfully", ["employee_id" => $employee_id, "work_dates" => $dates]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Response::json(500, "Error checking out: " . $e->getMessage());
        }
    }

    public function getAttendance() {
        $employee_id = $_GET['employee_id'] ?? null;
        $month = $_GET['month'] ?? date('Y-m');

        if (!$employee_id) {
            Response::json(400, "Employee ID is required");
        }

        try {
            if (strlen($month) === 4) {
                $sql = "SELECT work_date FROM attendance_logs 
                        WHERE employee_id = :employee_id AND DATE_FORMAT(work_date, '%Y') = :month 
                        ORDER BY work_date ASC";
            } else {
                $sql = "SELECT work_date FROM attendance_logs 
                        WHERE employee_id = :employee_id AND DATE_FORMAT(work_date, '%Y-%m') = :month 
                        ORDER BY work_date ASC";
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':employee_id' => $employee_id,
                ':month' => $month
            ]);
            $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

            Response::json(200, "Success", ["dates" => $dates]);
        } catch (Exception $e) {
            Response::json(500, "Error fetching attendance: " . $e->getMessage());
        }
    }
}
