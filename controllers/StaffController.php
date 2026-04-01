<?php
require_once __DIR__ . '/../models/Staff.php';

class StaffController {
    private $model;

    public function __construct() {
        $this->model = new Staff();
    }

    public function index() {
        $keyword = $_GET['keyword'] ?? '';
        $staff = $this->model->getAll($keyword);
        echo json_encode(["data" => $staff]);
    }

    public function show() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "Employee ID is required"]);
            return;
        }

        $staff = $this->model->getById($id);
        if ($staff) {
            echo json_encode(["data" => $staff]);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Staff member not found"]);
        }
    }

    public function create() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // Validation
        if (empty($data['first_name']) || empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["message" => "Name, Email, and Password are required"]);
            return;
        }

        if ($this->model->create($data)) {
            echo json_encode(["message" => "Staff member created successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to create staff member"]);
        }
    }

    public function update() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $id = $_GET['id'] ?? $data['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "Employee ID is required"]);
            return;
        }

        if ($this->model->update($id, $data)) {
            echo json_encode(["message" => "Staff member updated successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update staff member"]);
        }
    }

    public function delete() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "Employee ID is required"]);
            return;
        }

        if ($this->model->delete($id)) {
            echo json_encode(["message" => "Staff member deleted successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to delete staff member"]);
        }
    }

    public function roles() {
        $roles = $this->model->getRoles();
        echo json_encode(["data" => $roles]);
    }
}
?>
