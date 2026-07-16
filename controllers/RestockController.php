<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/RestockOrder.php';
require_once __DIR__ . '/../utils/Response.php';

class RestockController {
    private $db;
    private $restockModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->restockModel = new RestockOrder($this->db);
    }

    public function index() {
        $keyword = $_GET['keyword'] ?? '';
        $orders = $this->restockModel->getAll($keyword);
        Response::json(200, "Success", $orders);
    }

    public function show() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            Response::json(400, "Missing restock_id");
            return;
        }

        $order = $this->restockModel->getById($id);
        if ($order) {
            Response::json(200, "Success", $order);
        } else {
            Response::json(404, "Restock order not found");
        }
    }

    public function create() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['items']) || !is_array($data['items'])) {
            Response::json(400, "Bad Request: Missing items");
            return;
        }

        foreach ($data['items'] as $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || !isset($item['unit_cost'])) {
                Response::json(400, "Bad Request: Invalid item fields");
                return;
            }
        }

        $restock_id = $this->restockModel->create($data);
        if ($restock_id) {
            Response::json(201, "Purchase order created successfully", ["restock_id" => $restock_id]);
        } else {
            Response::json(500, "Failed to create purchase order");
        }
    }

    public function receive() {
        $data = json_decode(file_get_contents("php://input"), true);
        $restock_id = $data['restock_id'] ?? null;
        $employee_id = $data['employee_id'] ?? null;

        if (!$restock_id || !$employee_id) {
            Response::json(400, "Bad Request: Missing restock_id or employee_id");
            return;
        }

        $success = $this->restockModel->receive($restock_id, $employee_id);
        if ($success) {
            Response::json(200, "Goods imported to stock successfully");
        } else {
            Response::json(500, "Failed to import goods to stock");
        }
    }
}
