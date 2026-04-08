<?php
require_once __DIR__ . '/../config/database.php';

class Staff {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function getAll($keyword = '') {
        $sql = "SELECT e.*, u.email, u.username, u.role_id, u.permissions, r.role_name 
                FROM employees e 
                JOIN users u ON e.user_id = u.user_id 
                LEFT JOIN roles r ON u.role_id = r.role_id ";
        
        if (!empty($keyword)) {
            $sql .= " WHERE e.first_name LIKE :keyword OR e.last_name LIKE :keyword OR u.email LIKE :keyword OR r.role_name LIKE :keyword ";
        }
        $sql .= " ORDER BY e.employee_id DESC";

        $stmt = $this->db->prepare($sql);
        if (!empty($keyword)) {
            $stmt->bindValue(':keyword', '%' . $keyword . '%');
        }
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as &$row) {
            $row['permissions'] = $row['permissions'] ? json_decode($row['permissions'], true) : [];
        }
        return $results;
    }

    public function getById($id) {
        $sql = "SELECT e.*, u.email, u.username, u.role_id, u.permissions, r.role_name 
                FROM employees e 
                JOIN users u ON e.user_id = u.user_id 
                LEFT JOIN roles r ON u.role_id = r.role_id 
                WHERE e.employee_id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['permissions'] = $result['permissions'] ? json_decode($result['permissions'], true) : [];
        }
        return $result;
    }

    public function getByUserId($user_id) {
        $sql = "SELECT e.*, u.email, u.username, u.role_id, u.permissions, r.role_name 
                FROM employees e 
                JOIN users u ON e.user_id = u.user_id 
                LEFT JOIN roles r ON u.role_id = r.role_id 
                WHERE e.user_id = :user_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['permissions'] = $result['permissions'] ? json_decode($result['permissions'], true) : [];
        }
        return $result;
    }

    public function create($data) {
        try {
            $this->db->beginTransaction();

            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Allow manual username or fallback to generated
            $username = !empty($data['username']) ? $data['username'] : (strtolower(str_replace(' ', '', $data['first_name'])) . rand(100, 999));
            
            $sqlUser = "INSERT INTO users (role_id, username, password, email, permissions) 
                        VALUES (:role_id, :username, :password, :email, :permissions)";
            $stmtUser = $this->db->prepare($sqlUser);
            $stmtUser->bindValue(':role_id', $data['role_id']);
            $stmtUser->bindValue(':username', $username);
            $stmtUser->bindValue(':password', $password);
            $stmtUser->bindValue(':email', $data['email']);
            $stmtUser->bindValue(':permissions', json_encode($data['permissions'] ?? []));
            $stmtUser->execute();
            
            $userId = $this->db->lastInsertId();

            $sqlEmp = "INSERT INTO employees 
                        (user_id, first_name, last_name, phone, position, address, base_salary, payment_frequency, bank_account_details) 
                        VALUES (:user_id, :first_name, :last_name, :phone, :position, :address, :base_salary, :payment_frequency, :bank_account_details)";
            $stmtEmp = $this->db->prepare($sqlEmp);
            $stmtEmp->bindValue(':user_id', $userId);
            $stmtEmp->bindValue(':first_name', $data['first_name']);
            $stmtEmp->bindValue(':last_name', $data['last_name'] ?? '');
            $stmtEmp->bindValue(':phone', $data['phone'] ?? null);
            $stmtEmp->bindValue(':position', $data['position'] ?? null);
            $stmtEmp->bindValue(':address', $data['address'] ?? null);
            $stmtEmp->bindValue(':base_salary', $data['base_salary'] ?? 0);
            $stmtEmp->bindValue(':payment_frequency', $data['payment_frequency'] ?? null);
            $stmtEmp->bindValue(':bank_account_details', $data['bank_account_details'] ?? null);
            $stmtEmp->execute();

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function update($id, $data) {
        try {
            $this->db->beginTransaction();

            $emp = $this->getById($id);
            if (!$emp) throw new Exception("Employee not found");
            $userId = $emp['user_id'];

            $sqlUser = "UPDATE users SET role_id = :role_id, email = :email, username = :username, permissions = :permissions";
            if (!empty($data['password'])) {
                $sqlUser .= ", password = :password";
            }
            $sqlUser .= " WHERE user_id = :user_id";

            $stmtUser = $this->db->prepare($sqlUser);
            $stmtUser->bindValue(':role_id', $data['role_id']);
            $stmtUser->bindValue(':email', $data['email']);
            // If username is provided, update it, otherwise keep the old one
            $stmtUser->bindValue(':username', !empty($data['username']) ? $data['username'] : $emp['username']);
            $stmtUser->bindValue(':permissions', json_encode($data['permissions'] ?? []));
            $stmtUser->bindValue(':user_id', $userId);
            
            if (!empty($data['password'])) {
                $stmtUser->bindValue(':password', password_hash($data['password'], PASSWORD_DEFAULT));
            }
            $stmtUser->execute();

            $sqlEmp = "UPDATE employees SET 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        phone = :phone, 
                        position = :position, 
                        address = :address, 
                        base_salary = :base_salary, 
                        payment_frequency = :payment_frequency, 
                        bank_account_details = :bank_account_details 
                        WHERE employee_id = :id";
            $stmtEmp = $this->db->prepare($sqlEmp);
            $stmtEmp->bindValue(':id', $id);
            $stmtEmp->bindValue(':first_name', $data['first_name']);
            $stmtEmp->bindValue(':last_name', $data['last_name'] ?? '');
            $stmtEmp->bindValue(':phone', $data['phone'] ?? null);
            $stmtEmp->bindValue(':position', $data['position'] ?? null);
            $stmtEmp->bindValue(':address', $data['address'] ?? null);
            $stmtEmp->bindValue(':base_salary', $data['base_salary'] ?? 0);
            $stmtEmp->bindValue(':payment_frequency', $data['payment_frequency'] ?? null);
            $stmtEmp->bindValue(':bank_account_details', $data['bank_account_details'] ?? null);
            $stmtEmp->execute();

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function delete($id) {
        try {
            $this->db->beginTransaction();
            $emp = $this->getById($id);
            if ($emp) {
                // Delete user (cascade should handle employees if set, but we will explicitly delete both)
                $this->db->prepare("DELETE FROM employees WHERE employee_id = :id")->execute([':id' => $id]);
                $this->db->prepare("DELETE FROM users WHERE user_id = :user_id")->execute([':user_id' => $emp['user_id']]);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function getRoles() {
        $stmt = $this->db->query("SELECT * FROM roles ORDER BY role_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
