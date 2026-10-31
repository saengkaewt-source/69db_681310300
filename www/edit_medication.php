<?php
require_once 'config/db_conn.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$medication_id = $_GET['id'];
$error_msg = '';

// ดึงข้อมูลรายการยาที่ต้องการแก้ไข
$stmt = $conn->prepare("SELECT * FROM medications WHERE medication_id = :id");
$stmt->execute([':id' => $medication_id]);
$medication = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$medication) {
    header("Location: index.php");
    exit();
}

// ดึงชื่อผู้ใช้
$user_stmt = $conn->prepare("SELECT name FROM users WHERE user_id = :id");
$user_stmt->execute([':id' => $medication['user_id']]);
$user_name = $user_stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $user_name = trim($_POST['user_name']);
        $medicine_name = trim($_POST['medicine_name']);
        $dosage = trim($_POST['dosage'] ?? '');
        $dose_quantity = trim($_POST['dose_quantity'] ?? '');
        $taking_time = trim($_POST['taking_time'] ?? '');
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        // ค้นหา user_id
        $stmt_user = $conn->prepare("SELECT user_id FROM users WHERE name = :name LIMIT 1");
        $stmt_user->execute([':name' => $user_name]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $user_id = $user['user_id'];
        } else {
            $dummy_email = 'user_' . time() . '@example.com';
            $stmt_new_user = $conn->prepare("INSERT INTO users (name, email, password_hash) VALUES (:name, :email, 'default_hash')");
            $stmt_new_user->execute([':name' => $user_name, ':email' => $dummy_email]);
            $user_id = $conn->lastInsertId();
        }

        $sql = "UPDATE medications 
                SET user_id = :user_id, 
                    medicine_name = :medicine_name, 
                    dosage = :dosage, 
                    dose_quantity = :dose_quantity,
                    taking_time = :taking_time,
                    stock_quantity = :stock_quantity, 
                    start_date = :start_date, 
                    end_date = :end_date 
                WHERE medication_id = :medication_id";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':medicine_name' => $medicine_name,
            ':dosage' => $dosage,
            ':dose_quantity' => $dose_quantity,
            ':taking_time' => $taking_time,
            ':stock_quantity' => $stock_quantity,
            ':start_date' => $start_date,
            ':end_date' => $end_date,
            ':medication_id' => $medication_id
        ]);

        header("Location: index.php?status=updated");
        exit();
    } catch (PDOException $e) {
        $error_msg = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $e->getMessage();
    }
}

include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedinTime - แก้ไขรายการยา</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <h2 class="card-title">✏️ แก้ไขรายการยา (Edit Medication)</h2>

            <?php if (!empty($error_msg)): ?>
                <div style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #f5c6cb;">
                    ⚠️ <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>ผู้ใช้งาน / ผู้ป่วย:</label>
                    <input type="text" name="user_name" class="form-control" value="<?= htmlspecialchars($user_name) ?>" required>
                </div>

                <div class="form-group">
                    <label>ชื่อยา:</label>
                    <input type="text" name="medicine_name" class="form-control" value="<?= htmlspecialchars($medication['medicine_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>ขนาดยาที่ทาน:</label>
                    <input type="text" name="dosage" class="form-control" value="<?= htmlspecialchars($medication['dosage'] ?? '') ?>" placeholder="เช่น 500 mg หรือ 5 ml เป็นต้น">
                </div>

                <div class="form-group">
                    <label>ทานกี่เม็ด / ปริมาณต่อครั้ง:</label>
                    <input type="text" name="dose_quantity" class="form-control" value="<?= htmlspecialchars($medication['dose_quantity'] ?? '') ?>" placeholder="ระบุจำนวนยา เช่น 1 เม็ด, 2 ช้อนชา เป็นต้น">
                </div>

                <div class="form-group">
                    <label>ทานตอนไหน ช่วงไหน:</label>
                    <?php 
                    $time_options = [
                        'เช้า',
                        'กลางวัน',
                        'เย็น',
                        'ก่อนนอน',
                        'เช้า - เย็น',
                        'เช้า - กลางวัน - เย็น',
                        'เช้า - กลางวัน - เย็น - ก่อนนอน',
                        'เมื่อมีอาการ'
                    ];
                    $current_time = $medication['taking_time'] ?? '';
                    ?>
                    <select name="taking_time" class="form-control" required>
                        <option value="" disabled <?= empty($current_time) ? 'selected' : '' ?>>-- เลือกช่วงเวลาทานยา --</option>
                        <?php foreach ($time_options as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= ($current_time === $opt) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (!empty($current_time) && !in_array($current_time, $time_options)): ?>
                            <option value="<?= htmlspecialchars($current_time) ?>" selected>
                                <?= htmlspecialchars($current_time) ?> (เดิม)
                            </option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>จำนวนยาคงเหลือ (เม็ด):</label>
                    <input type="number" name="stock_quantity" class="form-control" value="<?= $medication['stock_quantity'] ?>" min="0" required>
                </div>

                <div class="form-group">
                    <label>วันเริ่มทานยา:</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $medication['start_date'] ?>">
                </div>

                <div class="form-group">
                    <label>วันสิ้นสุดการใช้ยา:</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $medication['end_date'] ?>">
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 15px;">อัปเดตข้อมูลยา</button>
                <a href="index.php" style="display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none;">ยกเลิก / กลับหน้าหลัก</a>
            </form>
        </div>
    </div>
</body>
</html>