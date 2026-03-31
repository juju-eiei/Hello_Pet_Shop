<?php
require_once __DIR__ . '/../models/Promotion.php';

class PromotionController {
    private $model;

    public function __construct() {
        $this->model = new Promotion();
    }

    public function index() {
        $keyword = $_GET['keyword'] ?? '';
        $promotions = $this->model->getAll($keyword);
        echo json_encode(["data" => $promotions]);
    }

    public function show() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "Promotion ID is required"]);
            return;
        }

        $promotion = $this->model->getById($id);
        if ($promotion) {
            echo json_encode(["data" => $promotion]);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Promotion not found"]);
        }
    }

    public function create() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if ($this->model->create($data)) {
            echo json_encode(["message" => "Promotion created successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to create promotion"]);
        }
    }

    public function update() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $id = $_GET['id'] ?? $data['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "Promotion ID is required"]);
            return;
        }

        if ($this->model->update($id, $data)) {
            echo json_encode(["message" => "Promotion updated successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update promotion"]);
        }
    }

    public function delete() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["message" => "Promotion ID is required"]);
            return;
        }

        if ($this->model->delete($id)) {
            echo json_encode(["message" => "Promotion deleted successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to delete promotion"]);
        }
    }
}
?>
