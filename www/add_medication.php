<?php
require_once 'config/db_conn.php';

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $user_name = trim($_POST['user_name']);
        $medicine_name = trim($_POST['medicine_name']);
        $dosage = trim($_POST['dosage'] ?? '');
        $dose_quantity = trim($_POST['dose_quantity'] ?? '');
        $taking_time = trim($_POST['taking_time'] ?? '');
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $start_date = $_POST['start_date'] ?? date('Y-m-d');
        $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days'));

        if (!empty($user_name) && !empty($medicine_name)) {
            // 1. ค้นหา user_id จากชื่อ
            $stmt_user = $conn->prepare("SELECT user_id FROM users WHERE name = :name LIMIT 1");
            $stmt_user->execute([':name' => $user_name]);
            $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $user_id = $user['user_id'];
            } else {
                // 2. ถ้าไม่พบชื่อ ให้สร้าง User ใหม่ให้อัตโนมัติ เพื่อไม่ให้ติด Constraint Foreign Key
                $dummy_email = 'user_' . time() . '@example.com';
                $stmt_new_user = $conn->prepare("INSERT INTO users (name, email, password_hash) VALUES (:name, :email, 'default_hash')");
                $stmt_new_user->execute([
                    ':name' => $user_name,
                    ':email' => $dummy_email
                ]);
                $user_id = $conn->lastInsertId();
            }

            // 3. บันทึกข้อมูลรายการยาลงในตาราง medications (แยก ขนาดยา / ทานกี่เม็ด / ทานตอนไหน ช่วงไหน)
            $sql = "INSERT INTO medications (user_id, medicine_name, dosage, dose_quantity, taking_time, stock_quantity, start_date, end_date) 
                    VALUES (:user_id, :medicine_name, :dosage, :dose_quantity, :taking_time, :stock_quantity, :start_date, :end_date)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':user_id' => $user_id,
                ':medicine_name' => $medicine_name,
                ':dosage' => $dosage,
                ':dose_quantity' => $dose_quantity,
                ':taking_time' => $taking_time,
                ':stock_quantity' => $stock_quantity,
                ':start_date' => $start_date,
                ':end_date' => $end_date
            ]);

            header("Location: index.php?status=added");
            exit();
        } else {
            $error_msg = "กรุณากรอกข้อมูลชื่อผู้ใช้งานและชื่อยาให้ครบถ้วน";
        }
    } catch (PDOException $e) {
        $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
    }
}

include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedinTime - เพิ่มรายการยา</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="card" style="max-width: 600px; margin: auto;">
            <h2>➕ เพิ่มรายการยาใหม่ (Add Medication)</h2>

            <?php if (!empty($error_msg)): ?>
                <div style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #f5c6cb;">
                    ⚠️ <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <label>ผู้ใช้งาน / ผู้ป่วย:</label>
                <input type="text" name="user_name" class="form-control" placeholder="กรอกชื่อของคุณ" required>

                <label>ชื่อยา:</label>
                <input type="text" name="medicine_name" class="form-control" placeholder="เช่น Paracetamol" required>

                <label>ขนาดยาที่ทาน:</label>
                <input type="text" name="dosage" class="form-control" placeholder="เช่น 500 mg หรือ 5 ml เป็นต้น">

                <label>ทานกี่เม็ด / ปริมาณต่อครั้ง:</label>
                <input type="text" name="dose_quantity" class="form-control" placeholder="ระบุจำนวนยา เช่น 1 เม็ด, 2 ช้อนชา เป็นต้น">

                <label>ทานตอนไหน ช่วงไหน:</label>
                <select name="taking_time" class="form-control" required>
                    <option value="" disabled selected>-- เลือกช่วงเวลาทานยา --</option>
                    <option value="เช้า">เช้า</option>
                    <option value="กลางวัน">กลางวัน</option>
                    <option value="เย็น">เย็น</option>
                    <option value="ก่อนนอน">ก่อนนอน</option>
                    <option value="เช้า - เย็น">เช้า - เย็น</option>
                    <option value="เช้า - กลางวัน - เย็น">เช้า - กลางวัน - เย็น</option>
                    <option value="เช้า - กลางวัน - เย็น - ก่อนนอน">เช้า - กลางวัน - เย็น - ก่อนนอน</option>
                    <option value="เมื่อมีอาการ">เมื่อมีอาการ</option>
                </select>

                <label>จำนวนยาคงเหลือ (เม็ด):</label>
                <input type="number" name="stock_quantity" class="form-control" placeholder="กรอกจำนวนยาคงเหลือ" min="0" value="0" required>

                <label>วันเริ่มทานยา:</label>
                <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">

                <label>วันสิ้นสุด:</label>
                <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 15px;">บันทึกข้อมูลยา</button>
            </form>
        </div>
    </div>
</body>
</html>