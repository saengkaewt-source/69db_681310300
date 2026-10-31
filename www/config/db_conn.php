<?php
// db_conn.php – ใช้ PDO เพื่อให้สอดคล้องกับโค้ดส่วนอื่น
$host     = 'db';               // ชื่อ Service ใน docker‑compose
$user     = 'user';
$pass     = 'userpassword';
$dbname   = 'final_project_db';
$charset  = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);

    // ตรวจสอบและเพิ่มคอลัมน์ dose_quantity และ taking_time อัตโนมัติ หากยังไม่มีในตาราง medications
    try {
        $colCheck1 = $conn->query("SHOW COLUMNS FROM medications LIKE 'dose_quantity'")->fetch();
        if (!$colCheck1) {
            $conn->exec("ALTER TABLE medications ADD COLUMN dose_quantity VARCHAR(100) NULL AFTER dosage");
        }
        $colCheck2 = $conn->query("SHOW COLUMNS FROM medications LIKE 'taking_time'")->fetch();
        if (!$colCheck2) {
            $conn->exec("ALTER TABLE medications ADD COLUMN taking_time VARCHAR(255) NULL AFTER dose_quantity");
        }
    } catch (Exception $e) {
        // หากตารางยังไม่ถูกสร้างหรือเกิดข้อผิดพลาด ให้ข้ามไปก่อน
    }
} catch (PDOException $e) {
    // แสดงข้อความข้อผิดพลาดแบบ friendly (ไม่เปิดเผยรายละเอียดเซิร์ฟเวอร์)
    die('Database connection failed: ' . $e->getMessage());
}
?>