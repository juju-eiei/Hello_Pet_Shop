<?php
class PetType
{
    private $conn;
    private $table = "pet_types";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAll()
    {
        $query = "SELECT * FROM " . $this->table . " ORDER BY id ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getById($id)
    {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($name, $code, $description = '')
    {
        $query = "INSERT INTO " . $this->table . " (name, code, description) VALUES (:name, :code, :description)";
        $stmt = $this->conn->prepare($query);
        $name = htmlspecialchars(strip_tags($name));
        $code = strtolower(trim(htmlspecialchars(strip_tags($code))));
        $description = htmlspecialchars(strip_tags($description));

        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":code", $code);
        $stmt->bindParam(":description", $description);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function update($id, $name, $code, $description = '')
    {
        $query = "UPDATE " . $this->table . " SET name = :name, code = :code, description = :description WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $name = htmlspecialchars(strip_tags($name));
        $code = strtolower(trim(htmlspecialchars(strip_tags($code))));
        $description = htmlspecialchars(strip_tags($description));

        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":code", $code);
        $stmt->bindParam(":description", $description);

        return $stmt->execute();
    }

    public function delete($id)
    {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
}
