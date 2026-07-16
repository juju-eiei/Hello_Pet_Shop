<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

class RoleController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function getRolePermissions() {
        try {
            $stmt = $this->db->query("SELECT role_id, role_name, permissions FROM roles ORDER BY role_id");
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($roles as &$role) {
                $role['permissions'] = json_decode($role['permissions'] ?? '[]', true) ?: [];
            }

            Response::json(200, "Successfully retrieved role permissions", $roles);
        } catch (Exception $e) {
            Response::json(500, "Error: " . $e->getMessage());
        }
    }

    public function saveRolePermissions() {
        try {
            $data = json_decode(file_get_contents("php://input"), true);

            if (empty($data['role_id']) || !isset($data['permissions'])) {
                Response::json(400, "Missing role_id or permissions");
                return;
            }

            $role_id = $data['role_id'];
            $permissions = $data['permissions'];

            if (!is_array($permissions)) {
                Response::json(400, "Permissions must be an array");
                return;
            }

            $stmt = $this->db->prepare("UPDATE roles SET permissions = :permissions WHERE role_id = :role_id");
            $json_perms = json_encode($permissions);
            $stmt->bindParam(':permissions', $json_perms);
            $stmt->bindParam(':role_id', $role_id);

            if ($stmt->execute()) {
                Response::json(200, "Role permissions updated successfully");
            } else {
                Response::json(500, "Failed to update role permissions");
            }
        } catch (Exception $e) {
            Response::json(500, "Error: " . $e->getMessage());
        }
    }
}
?>
