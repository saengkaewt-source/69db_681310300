<?php
// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'config/db_conn.php';

// 2. ตรวจสอบตัวแปรเชื่อมต่อ DB อัตโนมัติ
$db = null;
if (isset($pdo)) {
    $db = $pdo;
} elseif (isset($conn)) {
    $db = $conn;
} elseif (isset($db_conn)) {
    $db = $db_conn;
}

$reminders = [];
$db_error = '';

if ($db) {
    // 3. ดึงข้อมูลจากตาราง reminders โดยตรง
    $sql = "SELECT * FROM reminders ORDER BY reminder_time ASC";
    
    try {
        if ($db instanceof PDO) {
            $stmt = $db->query($sql);
            $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($db instanceof mysqli) {
            $result = mysqli_query($db, $sql);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $reminders[] = $row;
                }
            }
        }
    } catch (Exception $e) {
        $db_error = "SQL Error: " . $e->getMessage();
    }
} else {
    $db_error = "ไม่พบตัวแปรเชื่อมต่อฐานข้อมูลใน config/db_conn.php";
}

// ฟังก์ชันแปลงชื่อวันเป็นภาษาไทย
function formatDaysToThai($repeat_days) {
    $daysMap = [
        'Mon' => 'จ.',
        'Tue' => 'อ.',
        'Wed' => 'พ.',
        'Thu' => 'พฤ.',
        'Fri' => 'ศ.',
        'Sat' => 'ส.',
        'Sun' => 'อา.'
    ];
    $daysArray = array_map('trim', explode(',', $repeat_days));
    
    if (count($daysArray) === 7) {
        return '<span class="badge bg-success">ทุกวัน</span>';
    }

    $badges = array_map(function($day) use ($daysMap) {
        $thDay = isset($daysMap[$day]) ? $daysMap[$day] : $day;
        return "<span class=\"badge bg-secondary me-1\">{$thDay}</span>";
    }, $daysArray);

    return implode('', $badges);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการแจ้งเตือนกินยา - Reminders</title>
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <?php include 'navbar.php'; ?>

    <div class="container my-4">

        <!-- แสดง Error สีแดงถ้าการเชื่อมต่อหรือ Query มีปัญหา -->
        <?php if (!empty($db_error)): ?>
            <div class="alert alert-danger shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>เกิดข้อผิดพลาด:</strong> <?= htmlspecialchars($db_error) ?>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1">
                    <i class="bi bi-bell-fill me-2"></i>รายการแจ้งเตือนกินยา
                </h2>
                <p class="text-muted small">จัดการเวลาและวันที่ต้องการให้ระบบแจ้งเตือนรับประทานยา</p>
            </div>
        </div>

        <div class="row g-3">
            <?php if (!empty($reminders)): ?>
                <?php foreach ($reminders as $row): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border-0 reminder-card <?= $row['is_active'] ? '' : 'opacity-50' ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge reminder-badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1">
                                        <i class="bi bi-capsule me-1"></i>
                                        ยา ID: #<?= htmlspecialchars($row['medication_id']) ?>
                                    </span>
                                    
                                    <span class="badge reminder-badge <?= $row['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $row['is_active'] ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?>
                                    </span>
                                </div>

                                <div class="my-3">
                                    <div class="display-6 fw-bold text-dark">
                                        <i class="bi bi-clock me-2 text-muted fs-4"></i>
                                        <?= date('H:i', strtotime($row['reminder_time'])) ?> 
                                        <span class="fs-6 text-muted fw-normal">น.</span>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <div class="text-muted small mb-1">วันแจ้งเตือน:</div>
                                    <div>
                                        <?= formatDaysToThai($row['repeat_days']) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-top-0 d-flex justify-content-between text-muted small">
                                <span>รหัสแจ้งเตือน: #<?= $row['reminder_id'] ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center py-4" role="alert">
                        <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                        ไม่พบข้อมูลการแจ้งเตือนในระบบ
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>