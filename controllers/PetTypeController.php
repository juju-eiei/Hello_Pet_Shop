<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/PetType.php';
require_once __DIR__ . '/../utils/Response.php';

class PetTypeController
{
    private $db;
    private $petType;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->petType = new PetType($this->db);
    }

    private function isAdmin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
    }

    public function index()
    {
        $stmt = $this->petType->getAll();
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::json(200, "Success", $types);
    }

    public function create()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['name'])) {
            Response::json(400, "Missing name");
            return;
        }
        $code = !empty($data['code']) ? $data['code'] : strtolower(trim($data['name']));
        $description = $data['description'] ?? '';

        $id = $this->petType->create($data['name'], $code, $description);
        if ($id) {
            Response::json(201, "Pet type created", ["id" => $id]);
        } else {
            Response::json(500, "Failed to create pet type");
        }
    }

    public function update()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['id']) || empty($data['name'])) {
            Response::json(400, "Missing id or name");
            return;
        }
        $code = !empty($data['code']) ? $data['code'] : strtolower(trim($data['name']));
        $description = $data['description'] ?? '';

        if ($this->petType->update($data['id'], $data['name'], $code, $description)) {
            Response::json(200, "Pet type updated successfully");
        } else {
            Response::json(500, "Failed to update pet type");
        }
    }

    public function delete()
    {
        if (!$this->isAdmin()) {
            Response::json(403, "Admins only");
            return;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['id'])) {
            Response::json(400, "Missing id");
            return;
        }
        
        if ($this->petType->delete($data['id'])) {
            Response::json(200, "Pet type deleted successfully");
        } else {
            Response::json(500, "Failed to delete pet type");
        }
    }
}
