<?php
require_once 'config/db_conn.php';

// กรองตามประเภทการแจ้งเตือน (Filter)
$type_filter = $_GET['type'] ?? '';
$where_clauses = [];
$params = [];

if (!empty($type_filter)) {
    $where_clauses[] = "n.notification_type = :type";
    $params[':type'] = $type_filter;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// ดึงข้อมูลการแจ้งเตือน พร้อม JOIN ข้อมูลผู้ใช้ และรายการยา
$sql = "
    SELECT 
        n.*, 
        u.name AS user_name, 
        m.medicine_name 
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.user_id
    LEFT JOIN medications m ON n.medication_id = m.medication_id
    $where_sql
    ORDER BY n.created_at DESC, n.notification_id DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// นับจำนวนทั้งหมด
$total_count = $conn->query("SELECT COUNT(*) FROM notifications")->fetchColumn();

include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedinTime - การแจ้งเตือน (Notifications)</title>
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <style>
        .filter-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            align-items: center;
        }
        .filter-btn {
            padding: 6px 14px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-mid);
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid var(--lavender);
            transition: var(--transition);
        }
        .filter-btn:hover, .filter-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .badge-type {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-type-reminder { background: #e8f4fd; color: #1976d2; border: 1px solid #bbdefb; }
        .badge-type-missed { background: #fde8e8; color: #c62828; border: 1px solid #ffcdd2; }
        .badge-type-stock { background: #fff4e5; color: #e65100; border: 1px solid #ffe0b2; }
        .badge-type-confirmed { background: #e8f8f5; color: #2e7d32; border: 1px solid #c8e6c9; }
        .badge-type-default { background: #f5f5f5; color: #616161; border: 1px solid #e0e0e0; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1250px;">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <div>
                    <h2 style="margin: 0;">🔔 รายการแจ้งเตือน (Notifications)</h2>
                    <p style="color: var(--text-light); margin: 5px 0 0 0; font-size: 0.9rem;">
                        แจ้งเตือนทั้งหมด: <strong><?= number_format($total_count) ?></strong> รายการ
                    </p>
                </div>
            </div>

            <!-- หมวดหมู่ตัวกรอง (Filter) -->
            <div class="filter-bar">
                <span style="font-size: 0.88rem; font-weight: 600; color: var(--text-mid);">หมวดหมู่:</span>
                <a href="notifications.php" class="filter-btn <?= empty($type_filter) ? 'active' : '' ?>">ทั้งหมด</a>
                <a href="notifications.php?type=medication_reminder" class="filter-btn <?= $type_filter === 'medication_reminder' ? 'active' : '' ?>">⏰ ถึงเวลาทานยา</a>
                <a href="notifications.php?type=missed_dose" class="filter-btn <?= $type_filter === 'missed_dose' ? 'active' : '' ?>">⚠️ ลืมทานยา / พลาดมื้อ</a>
                <a href="notifications.php?type=stock_low" class="filter-btn <?= $type_filter === 'stock_low' ? 'active' : '' ?>">📦 ยาใกล้หมด</a>
                <a href="notifications.php?type=dose_confirmed" class="filter-btn <?= $type_filter === 'dose_confirmed' ? 'active' : '' ?>">✅ บันทึกทานยาแล้ว</a>
            </div>

            <!-- ตารางแสดงข้อมูลการแจ้งเตือน -->
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>ผู้ใช้งาน</th>
                            <th>ประเภท</th>
                            <th>หัวข้อการแจ้งเตือน</th>
                            <th>รายละเอียดข้อความ</th>
                            <th>รายการยาประจำตัว</th>
                            <th>เวลาทานตามกำหนด</th>
                            <th>เวลาที่บันทึก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 35px; color: #888;">
                                    📭 ไม่พบรายการแจ้งเตือนในระบบ
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $n): ?>
                            <tr>
                                <td>#<?= $n['notification_id'] ?></td>
                                <td><strong><?= htmlspecialchars($n['user_name'] ?? 'ผู้ใช้ #' . $n['user_id']) ?></strong></td>
                                <td>
                                    <?php 
                                    switch ($n['notification_type']) {
                                        case 'medication_reminder':
                                            echo '<span class="badge-type badge-type-reminder">⏰ เตือนกินยา</span>';
                                            break;
                                        case 'missed_dose':
                                            echo '<span class="badge-type badge-type-missed">⚠️ พลาดมื้อยา</span>';
                                            break;
                                        case 'stock_low':
                                            echo '<span class="badge-type badge-type-stock">📦 ยาใกล้หมด</span>';
                                            break;
                                        case 'dose_confirmed':
                                            echo '<span class="badge-type badge-type-confirmed">✅ กินยาแล้ว</span>';
                                            break;
                                        default:
                                            echo '<span class="badge-type badge-type-default">' . htmlspecialchars($n['notification_type']) . '</span>';
                                            break;
                                    }
                                    ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($n['title']) ?></strong>
                                </td>
                                <td style="max-width: 340px; font-size: 0.9rem; color: var(--text-mid);">
                                    <?= htmlspecialchars($n['message']) ?>
                                </td>
                                <td>
                                    <?php if (!empty($n['medicine_name'])): ?>
                                        <span class="badge-dosage"><?= htmlspecialchars($n['medicine_name']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #aaa;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--text-mid); white-space: nowrap;">
                                    <?= !empty($n['scheduled_at']) ? date('d/m/Y H:i', strtotime($n['scheduled_at'])) : '-' ?>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--text-mid); white-space: nowrap;">
                                    <?= !empty($n['created_at']) ? date('d/m/Y H:i', strtotime($n['created_at'])) : '-' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>