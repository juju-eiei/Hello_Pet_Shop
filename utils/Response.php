<?php
class Response {
    public static function json($status, $message, $data = null) {
        http_response_code($status);
        header('Content-Type: application/json');
        
        $response = array(
            "status" => $status,
            "message" => $message
        );
        
        if ($data !== null) {
            $response["data"] = $data;
        }
        
        echo json_encode($response);
        exit;
    }
}
?>
