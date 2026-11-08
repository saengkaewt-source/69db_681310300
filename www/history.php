<?php
require_once 'config/db_conn.php';
include 'navbar.php';

$sql = "
    SELECT l.*, r.reminder_time, m.medicine_name, u.name as user_name
    FROM medication_logs l
    JOIN reminders r ON l.reminder_id = r.reminder_id
    JOIN medications m ON r.medication_id = m.medication_id
    JOIN users u ON m.user_id = u.user_id
    ORDER BY l.scheduled_at DESC
";
$logs = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>MedinTime - ประวัติการกินยา</title>
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
</head>
<body>
    <div class="container">
        <div class="card">
            <h2>📜 ประวัติการกินยา (Medication Logs)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID Log</th>
                        <th>ผู้ป่วย</th>
                        <th>ชื่อยา</th>
                        <th>เวลาตามกำหนด</th>
                        <th>เวลาที่บันทึกกินยา</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $log['log_id'] ?></td>
                        <td><?= htmlspecialchars($log['user_name']) ?></td>
                        <td><?= htmlspecialchars($log['medicine_name']) ?></td>
                        <td><?= $log['scheduled_at'] ?></td>
                        <td><?= $log['taken_at'] ?? '-' ?></td>
                        <td>
                            <?php if ($log['status'] == 'taken'): ?>
                                <span style="color: green; font-weight: bold;">✔ กินแล้ว</span>
                            <?php elseif ($log['status'] == 'skipped'): ?>
                                <span style="color: orange; font-weight: bold;">⚠️ ข้ามมื้อ</span>
                            <?php else: ?>
                                <span style="color: red; font-weight: bold;">❌ พลาดมื้อ</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>