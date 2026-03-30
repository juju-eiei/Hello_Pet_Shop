<?php
class User {
    private $conn;
    private $table = 'users';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function findByUsername($username) {
        $query = "SELECT u.*, r.role_name 
                  FROM " . $this->table . " u
                  JOIN roles r ON u.role_id = r.role_id
                  WHERE u.username = :username 
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        try {
            $this->conn->beginTransaction();

            // 1. Insert into users
            // Columns: role_id, username, password, email
            $query = "INSERT INTO " . $this->table . " 
                     (role_id, username, password, email) 
                     VALUES (:role_id, :username, :password, :email)";
            
            $stmt = $this->conn->prepare($query);
            
            $role_id = $data['role_id'] ?? 3; // Default to 'customer'
            $password = password_hash($data['password'], PASSWORD_BCRYPT);

            $stmt->bindParam(':role_id', $role_id);
            $stmt->bindParam(':username', $data['username']);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':email', $data['email']);

            if (!$stmt->execute()) {
                throw new Exception("Error creating user record.");
            }

            $userId = $this->conn->lastInsertId();

            // 2. If it's a customer (role_id 3), insert into customers table
            if ($role_id == 3) {
                // Table: customers
                // Columns: user_id, first_name, last_name, phone, points
                $queryCustomer = "INSERT INTO customers (user_id, first_name, last_name, phone, points) 
                                  VALUES (:user_id, :first_name, :last_name, :phone, 0)";
                $stmtCust = $this->conn->prepare($queryCustomer);
                
                $names = explode(' ', $data['full_name'], 2);
                $first_name = $names[0];
                $last_name = $names[1] ?? '';
                $phone = $data['phone'] ?? '';

                $stmtCust->bindParam(':user_id', $userId);
                $stmtCust->bindParam(':first_name', $first_name);
                $stmtCust->bindParam(':last_name', $last_name);
                $stmtCust->bindParam(':phone', $phone);

                if (!$stmtCust->execute()) {
                    throw new Exception("Error creating customer record.");
                }
            }

            $this->conn->commit();
            return $userId;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Registration Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
