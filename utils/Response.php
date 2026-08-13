<?php
class Response {
    public static function json($status, $message, $data = null) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        
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
