<?php
require_once __DIR__ . '/controllers/PromotionController.php';

// Mock $_GET
$_GET['keyword'] = '';

try {
    $controller = new PromotionController();
    $controller->index();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["message" => "Server Error: " . $e->getMessage()]);
}
?>
