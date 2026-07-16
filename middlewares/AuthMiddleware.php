<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

// Composer autoload
if(file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class AuthMiddleware {
    public static function authenticate() {
        // 1. Try Token (Header)
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null);
        
        if ($authHeader) {
            $token = str_replace('Bearer ', '', $authHeader);
            try {
                $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
                return (array) $decoded->data; 
            } catch (Exception $e) {
                Response::json(401, "Unauthorized: Invalid token", ["error" => $e->getMessage()]);
            }
        }

        // 2. Try PHP Session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['user_id'])) {
            return [
                'user_id' => $_SESSION['user_id'],
                'role' => $_SESSION['role']
            ];
        }

        Response::json(401, "Unauthorized: User not authenticated");
    }

    public static function checkRole($allowedRoles) {
        $user = self::authenticate();
        
        // Handle case-insensitive comparisons
        $allowedRolesLower = array_map('strtolower', $allowedRoles);
        $userRoleLower = strtolower($user['role'] ?? '');
        
        if(!in_array($userRoleLower, $allowedRolesLower)) {
            Response::json(403, "Forbidden: Access denied");
        }
        return $user;
    }

    public static function checkPermission($permission) {
        $user = self::authenticate();
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Fetch user permissions, role name, and role permissions
        $query = "SELECT u.permissions AS user_permissions, r.role_name, r.permissions AS role_permissions 
                  FROM users u
                  JOIN roles r ON u.role_id = r.role_id
                  WHERE u.user_id = :user_id";
                  
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user['user_id']);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            Response::json(403, "Forbidden: User record not found");
        }
        
        $roleNameLower = strtolower($data['role_name'] ?? '');
        
        // Superadmin role bypasses all checks
        if ($roleNameLower === 'admin') {
            return $user;
        }
        
        $userPerms = json_decode($data['user_permissions'] ?? '[]', true) ?: [];
        $rolePerms = json_decode($data['role_permissions'] ?? '[]', true) ?: [];
        
        // Merge user and role permissions
        $allPerms = array_merge($userPerms, $rolePerms);
        
        if (!in_array($permission, $allPerms)) {
            Response::json(403, "Forbidden: Lacking required permission '{$permission}'");
        }
        
        return $user;
    }
}
?>
