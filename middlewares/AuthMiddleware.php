<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';

// Composer autoload
if(file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class AuthMiddleware {
    public static function authenticate() {
        $headers = apache_request_headers();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null);
        
        if(!$authHeader) {
            Response::json(401, "Unauthorized: Token not found");
        }

        $token = str_replace('Bearer ', '', $authHeader);

        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
            $_userData = (array) $decoded->data;
            return $_userData; 
        } catch (Exception $e) {
            Response::json(401, "Unauthorized: Invalid token", ["error" => $e->getMessage()]);
        }
    }

    public static function checkRole($allowedRoles) {
        $user = self::authenticate();
        if(!in_array($user['role'], $allowedRoles)) {
            Response::json(403, "Forbidden: Access denied");
        }
        return $user;
    }
}
?>
