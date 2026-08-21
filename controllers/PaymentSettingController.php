<?php
require_once __DIR__ . '/../models/PaymentSetting.php';
require_once __DIR__ . '/../utils/Response.php';

class PaymentSettingController {
    private $model;

    public function __construct() {
        $this->model = new PaymentSetting();
    }

    public function getSettings() {
        try {
            $settings = $this->model->getSettings();
            if (!$settings) {
                Response::json(404, "Payment settings not found");
                return;
            }
            Response::json(200, "Successfully fetched payment settings", $settings);
        } catch (Exception $e) {
            Response::json(500, "Error: " . $e->getMessage());
        }
    }

    public function updateSettings() {
        try {
            $bankName = $_POST['bank_name'] ?? '';
            $accountNumber = $_POST['account_number'] ?? '';
            $accountName = $_POST['account_name'] ?? '';
            $promptpayId = $_POST['promptpay_id'] ?? '';
            $instructions = $_POST['instructions'] ?? '';

            if (empty($bankName) || empty($accountNumber) || empty($accountName)) {
                Response::json(400, "กรุณากรอกชื่อธนาคาร, เลขที่บัญชี และชื่อเจ้าของบัญชีให้ครบถ้วน");
                return;
            }

            $updateData = [
                'bank_name' => trim($bankName),
                'account_number' => trim($accountNumber),
                'account_name' => trim($accountName),
                'promptpay_id' => trim($promptpayId),
                'instructions' => trim($instructions),
                'qr_image_url' => null
            ];

            // Handle file upload if provided
            if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['qr_image'];
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedExts)) {
                    Response::json(400, "รองรับเฉพาะไฟล์รูปภาพ .jpg, .jpeg, .png, .webp เท่านั้น");
                    return;
                }

                $targetDir = __DIR__ . '/../public/image/';
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                $filename = 'promptpay_qr_' . time() . '.' . $ext;
                $targetFile = $targetDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                    $updateData['qr_image_url'] = '/image/' . $filename;
                } else {
                    Response::json(500, "ไม่สามารถบันทึกไฟล์รูปภาพ QR Code ได้");
                    return;
                }
            }

            $success = $this->model->updateSettings($updateData);
            if ($success) {
                $updated = $this->model->getSettings();
                Response::json(200, "บันทึกการตั้งค่าบัญชีรับเงินและ QR Code สำเร็จ", $updated);
            } else {
                Response::json(500, "ไม่สามารถบันทึกข้อมูลได้");
            }
        } catch (Exception $e) {
            Response::json(500, "Error: " . $e->getMessage());
        }
    }
}
