<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/RateLimiter.php';
require_once __DIR__ . '/../models/User.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class PasswordResetController {
    private $db;
    private $userModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->userModel = new User($this->db);
    }

    public function requestReset() {
        // 1. Rate limiting (max 5 requests per 15 minutes per IP)
        $ip = RateLimiter::getClientIp();
        $rateCheck = RateLimiter::check('forgot_pwd:' . $ip, 5, 900);
        if (!$rateCheck['allowed']) {
            $minutes = ceil($rateCheck['retry_after'] / 60);
            Response::json(429, "คุณทำรายการขอรีเซ็ทรหัสผ่านบ่อยเกินไป กรุณารออีก {$minutes} นาทีแล้วลองใหม่อีกครั้ง");
            return;
        }
        RateLimiter::hit('forgot_pwd:' . $ip, 900);

        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['email'])) {
            Response::json(400, "กรุณากรอกอีเมล");
            return;
        }

        $email = trim($data['email']);

        // Check if user exists with this email
        $user = $this->userModel->findByEmail($email);
        
        // Return generic success response even if email doesn't exist to prevent email enumeration
        if (!$user) {
            Response::json(200, "หากอีเมลนี้มีอยู่ในระบบ เราได้ส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปให้แล้ว กรุณาตรวจสอบกล่องข้อความอีเมลของคุณ");
            return;
        }

        // Generate raw token and hash for database storage (SHA-256)
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Delete any existing tokens for this email
        $deleteStmt = $this->db->prepare("DELETE FROM password_resets WHERE email = :email");
        $deleteStmt->execute([':email' => $email]);

        // Insert new hashed token
        $insertStmt = $this->db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)");
        $insertStmt->execute([
            ':email' => $email,
            ':token' => $tokenHash,
            ':expires_at' => $expires_at
        ]);

        // Construct reset link with raw token
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $refParts = parse_url($_SERVER['HTTP_REFERER']);
            if (!empty($refParts['host'])) {
                $host = $refParts['host'] . (!empty($refParts['port']) ? ':' . $refParts['port'] : '');
                if (!empty($refParts['scheme'])) {
                    $protocol = $refParts['scheme'];
                }
            }
        }
        $resetLink = $protocol . "://" . $host . "/reset-password?token=" . $rawToken;

        $subject = "รีเซ็ทรหัสผ่าน - Hello Pet Shop";

        $message = "
        <html>
        <head>
            <title>รีเซ็ทรหัสผ่าน - Hello Pet Shop</title>
        </head>
        <body style='font-family: Arial, sans-serif; background-color: #f3f4f6; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #166534;'>Hello Pet Shop</h2>
                </div>
                <p>สวัสดีครับ/ค่ะ,</p>
                <p>คุณได้รับอีเมลนี้เนื่องจากมีการร้องขอรีเซ็ทรหัสผ่านสำหรับบัญชีผู้ใช้ของคุณในระบบ Hello Pet Shop</p>
                <p>กรุณาคลิกที่ปุ่มด้านล่างเพื่อทำการตั้งรหัสผ่านใหม่ ลิงก์นี้จะหมดอายุภายใน 1 ชั่วโมง:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetLink}' style='background-color: #16a34a; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>รีเซ็ทรหัสผ่านใหม่</a>
                </div>
                <p>หรือคัดลอกลิงก์ด้านล่างนี้ไปวางในเบราว์เซอร์ของคุณ:</p>
                <p style='word-break: break-all; color: #166534;'><a href='{$resetLink}'>{$resetLink}</a></p>
                <hr style='border: 0; border-top: 1px solid #e5e7eb; margin: 20px 0;'>
                <p style='font-size: 12px; color: #6b7280;'>หากคุณไม่ได้เป็นผู้ร้องขอการรีเซ็ทรหัสผ่านนี้ คุณไม่จำเป็นต้องดำเนินการใดๆ</p>
            </div>
        </body>
        </html>
        ";

        // Load SMTP config
        $mailConfig = null;
        if (file_exists(__DIR__ . '/../config/mail.php')) {
            $mailConfig = require __DIR__ . '/../config/mail.php';
        }

        $isSmtpConfigured = $mailConfig && 
                             !empty($mailConfig['smtp_user']) && 
                             $mailConfig['smtp_user'] !== 'YOUR_GMAIL_ADDRESS@gmail.com' &&
                             !empty($mailConfig['smtp_pass']) && 
                             $mailConfig['smtp_pass'] !== 'YOUR_GMAIL_APP_PASSWORD';

        $sentSuccessfully = false;

        if ($isSmtpConfigured) {
            try {
                $mail = new PHPMailer(true);

                // Server settings
                $mail->isSMTP();
                $mail->Host       = $mailConfig['smtp_host'];
                $mail->SMTPAuth   = $mailConfig['smtp_auth'];
                $mail->Username   = $mailConfig['smtp_user'];
                $mail->Password   = $mailConfig['smtp_pass'];
                $mail->SMTPSecure = $mailConfig['smtp_secure'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = $mailConfig['smtp_port'];
                $mail->CharSet    = 'UTF-8';

                // Recipients
                $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $message;
                $mail->AltBody = strip_tags($message);

                $mail->send();
                $sentSuccessfully = true;
            } catch (Exception $e) {
                error_log("PHPMailer SMTP Error: " . $e->getMessage());
            }
        }

        // Fallback to local mail() if SMTP is not configured or failed
        if (!$sentSuccessfully) {
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: Hello Pet Shop <noreply@hellopetshop.com>' . "\r\n";

            @mail($email, $subject, $message, $headers);
        }

        Response::json(200, "หากอีเมลนี้มีอยู่ในระบบ เราได้ส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปให้แล้ว กรุณาตรวจสอบกล่องข้อความอีเมลของคุณ");
    }

    public function resetPassword() {
        // Rate limiting (max 10 attempts per 15 minutes per IP)
        $ip = RateLimiter::getClientIp();
        $rateCheck = RateLimiter::check('reset_pwd:' . $ip, 10, 900);
        if (!$rateCheck['allowed']) {
            $minutes = ceil($rateCheck['retry_after'] / 60);
            Response::json(429, "คุณทำรายการบ่อยเกินไป กรุณารออีก {$minutes} นาทีแล้วลองใหม่อีกครั้ง");
            return;
        }
        RateLimiter::hit('reset_pwd:' . $ip, 900);

        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['token']) || empty($data['password'])) {
            Response::json(400, "กรุณากรอกข้อมูลให้ครบถ้วน");
            return;
        }

        $rawToken = trim($data['token']);
        $tokenHash = hash('sha256', $rawToken);
        $newPassword = $data['password'];

        // Find token by hash and check expiry
        $stmt = $this->db->prepare("SELECT * FROM password_resets WHERE token = :token LIMIT 1");
        $stmt->execute([':token' => $tokenHash]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            Response::json(400, "ลิงก์รีเซ็ทรหัสผ่านไม่ถูกต้อง");
            return;
        }

        // Check expiry
        $now = date('Y-m-d H:i:s');
        if (strtotime($record['expires_at']) < strtotime($now)) {
            // Delete expired token
            $deleteStmt = $this->db->prepare("DELETE FROM password_resets WHERE token = :token");
            $deleteStmt->execute([':token' => $tokenHash]);
            
            Response::json(400, "ลิงก์รีเซ็ทรหัสผ่านนี้หมดอายุแล้ว");
            return;
        }

        $email = $record['email'];

        // Get user_id by email
        $userStmt = $this->db->prepare("SELECT user_id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $userStmt->execute([':email' => $email]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::json(404, "ไม่พบผู้ใช้ที่ผูกกับอีเมลนี้");
            return;
        }

        $userId = $user['user_id'];
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password
        $updateStmt = $this->db->prepare("UPDATE users SET password = :password WHERE user_id = :user_id");
        $success = $updateStmt->execute([
            ':password' => $newPasswordHash,
            ':user_id' => $userId
        ]);

        if ($success) {
            // Delete token
            $deleteStmt = $this->db->prepare("DELETE FROM password_resets WHERE email = :email");
            $deleteStmt->execute([':email' => $email]);

            // Clear rate limits for IP
            RateLimiter::clear('reset_pwd:' . $ip);
            RateLimiter::clear('forgot_pwd:' . $ip);

            Response::json(200, "เปลี่ยนรหัสผ่านใหม่สำเร็จแล้ว");
        } else {
            Response::json(500, "ไม่สามารถบันทึกรหัสผ่านใหม่ได้ เกิดข้อผิดพลาดในระบบ");
        }
    }
}
?>
