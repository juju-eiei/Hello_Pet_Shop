<?php
$passwords = ['', 'root', 'admin', 'password'];
$hosts = ["localhost", "127.0.0.1"];
$username = "root";

foreach ($hosts as $host) {
    foreach ($passwords as $password) {
        echo "Testing user 'root' on '$host' with password '" . ($password === '' ? '[empty]' : $password) . "'... ";
        try {
            $conn = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
            echo "SUCCESS!\n";
            $conn = null;
        } catch (PDOException $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
        }
    }
}
?>
