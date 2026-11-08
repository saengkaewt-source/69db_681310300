<?php
require_once 'config/db_conn.php';
include 'navbar.php';

// ดึงรายชื่อ User ทั้งหมดที่มีในระบบ เพื่อใช้ใน Dropdown ตัวกรอง
$users_stmt = $conn->query("SELECT user_id, name FROM users ORDER BY name ASC");
$users_list = $users_stmt ? $users_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// รับค่าค้นหา
$search = trim($_GET['search'] ?? '');
$selected_user_id = trim($_GET['user_id'] ?? '');

$where_clauses = [];
$params = [];

if (!empty($selected_user_id)) {
    $where_clauses[] = "u.user_id = :user_id";
    $params[':user_id'] = $selected_user_id;
}

if (!empty($search)) {
    $where_clauses[] = "(u.name LIKE :search OR m.medicine_name LIKE :search OR m.dosage LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// คิวรีดึงประวัติการกินยา พร้อมข้อมูลผู้ใช้และยา
$sql = "
    SELECT 
        l.*, 
        r.reminder_time, 
        m.medicine_name, 
        m.dosage,
        m.dose_quantity,
        m.taking_time,
        u.user_id,
        u.name AS user_name
    FROM medication_logs l
    JOIN reminders r ON l.reminder_id = r.reminder_id
    JOIN medications m ON r.medication_id = m.medication_id
    JOIN users u ON m.user_id = u.user_id
    $where_sql
    ORDER BY l.scheduled_at DESC, l.log_id DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// หากมีการเลือก User ให้ดึงรายการยาที่ User คนนั้นลงทะเบียนไว้
$selected_user_name = '';
$user_meds_summary = [];

if (!empty($selected_user_id)) {
    foreach ($users_list as $u) {
        if ($u['user_id'] == $selected_user_id) {
            $selected_user_name = $u['name'];
            break;
        }
    }
    $stmt_meds = $conn->prepare("
        SELECT medication_id, medicine_name, dosage, dose_quantity, taking_time, stock_quantity, start_date, end_date 
        FROM medications 
        WHERE user_id = :uid 
        ORDER BY medicine_name ASC
    ");
    $stmt_meds->execute([':uid' => $selected_user_id]);
    $user_meds_summary = $stmt_meds->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedinTime - ประวัติการกินยา</title>
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <style>
        .badge-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-taken { background: #e8f8f5; color: #2e7d32; border: 1px solid #c8e6c9; }
        .badge-skipped { background: #fff4e5; color: #e65100; border: 1px solid #ffe0b2; }
        .badge-missed { background: #fde8e8; color: #c62828; border: 1px solid #ffcdd2; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1250px;">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <div>
                    <h2 style="margin: 0;">📜 ประวัติการกินยา (Medication Logs)</h2>
                    <p style="color: var(--text-light); margin: 5px 0 0 0; font-size: 0.9rem;">
                        พบประวัติการกินยาทั้งหมด: <strong><?= number_format(count($logs)) ?></strong> รายการ
                    </p>
                </div>
            </div>

            <!-- กล่องค้นหาและตัวกรอง -->
            <form method="GET" action="history.php" style="background: rgba(255, 255, 255, 0.7); padding: 16px 20px; border-radius: var(--radius-md); border: 1.5px solid var(--lavender); margin-bottom: 24px;">
                <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                    <!-- ช่องค้นหาตามชื่อ user หรือชื่อยา -->
                    <div style="flex: 2; min-width: 250px;">
                        <label style="margin: 0 0 6px 0; font-size: 0.85rem; color: var(--text-mid); font-weight: 600;">🔍 ค้นหาคำค้น (ชื่อผู้ใช้ / ชื่อยา / ขนาดยา):</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="พิมพ์ชื่อผู้ใช้ หรือชื่อยาที่ต้องการค้นหา..." 
                               value="<?= htmlspecialchars($search) ?>" 
                               style="margin: 0;">
                    </div>

                    <!-- ตัวเลือกกรองตามรายชื่อผู้ใช้ -->
                    <div style="flex: 1; min-width: 200px;">
                        <label style="margin: 0 0 6px 0; font-size: 0.85rem; color: var(--text-mid); font-weight: 600;">👤 เลือกตามรายชื่อผู้ใช้:</label>
                        <select name="user_id" class="form-control" style="margin: 0;">
                            <option value="">-- ผู้ใช้งานทั้งหมด --</option>
                            <?php foreach ($users_list as $u): ?>
                                <option value="<?= $u['user_id'] ?>" <?= ($selected_user_id == $u['user_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ปุ่มค้นหาและล้างค่า -->
                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn-primary" style="margin: 0; padding: 10px 20px; height: 42px; display: inline-flex; align-items: center; gap: 6px;">
                            <span>🔍 ค้นหา</span>
                        </button>
                        <?php if (!empty($search) || !empty($selected_user_id)): ?>
                            <a href="history.php" class="btn-danger" style="margin: 0; padding: 10px 16px; height: 42px; text-decoration: none; display: inline-flex; align-items: center; border-radius: var(--radius-sm); white-space: nowrap;">
                                ล้างค่า
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- แสดงกล่องรายการยาที่ผู้ใช้ลงทะเบียนไว้ (เมื่อเลือกผู้ใช้) -->
            <?php if (!empty($selected_user_id)): ?>
                <div style="background: #f0f6fc; border: 1.5px solid #c8dcf2; border-radius: var(--radius-md); padding: 18px 22px; margin-bottom: 24px;">
                    <h3 style="margin: 0 0 10px 0; font-size: 1.1rem; color: var(--primary-deeper); display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span>💊 รายการยาที่ <strong><?= htmlspecialchars($selected_user_name) ?></strong> ลงทะเบียนไว้</span>
                        <span style="font-size: 0.82rem; font-weight: 600; background: #fff; padding: 2px 10px; border-radius: 12px; border: 1px solid #bbdefb; color: #1976d2;">
                            <?= count($user_meds_summary) ?> รายการ
                        </span>
                    </h3>
                    
                    <?php if (empty($user_meds_summary)): ?>
                        <p style="margin: 0; color: var(--text-light); font-size: 0.9rem;">ผู้ใช้งานท่านนี้ยังไม่มีรายการยาที่ลงทะเบียนไว้</p>
                    <?php else: ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
                            <?php foreach ($user_meds_summary as $um): ?>
                                <div style="background: #ffffff; border: 1px solid #d0e1f9; border-radius: 8px; padding: 10px 14px; font-size: 0.88rem; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
                                    <strong style="color: var(--primary-deeper); font-size: 0.95rem;"><?= htmlspecialchars($um['medicine_name']) ?></strong>
                                    <div style="margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap;">
                                        <?php if (!empty($um['dosage'])): ?>
                                            <span class="badge-dosage"><?= htmlspecialchars($um['dosage']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($um['dose_quantity'])): ?>
                                            <span class="badge-qty"><?= htmlspecialchars($um['dose_quantity']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($um['taking_time'])): ?>
                                            <span class="badge-time"><?= htmlspecialchars($um['taking_time']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ตารางแสดงประวัติการกินยา -->
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 70px;">ID Log</th>
                            <th>ผู้ป่วย / ผู้ใช้งาน</th>
                            <th>ชื่อยา</th>
                            <th>ขนาดยา / ปริมาณ</th>
                            <th>เวลาตามกำหนด</th>
                            <th>เวลาที่บันทึกกินยา</th>
                            <th style="text-align: center;">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 35px; color: #888;">
                                    📭 ไม่พบประวัติการกินยาตามเงื่อนไขที่ค้นหา
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>#<?= $log['log_id'] ?></td>
                                <td><strong><?= htmlspecialchars($log['user_name']) ?></strong></td>
                                <td><span style="font-weight: 600; color: var(--primary-deeper);"><?= htmlspecialchars($log['medicine_name']) ?></span></td>
                                <td>
                                    <?php if (!empty($log['dosage'])): ?>
                                        <span class="badge-dosage"><?= htmlspecialchars($log['dosage']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($log['dose_quantity'])): ?>
                                        <span class="badge-qty"><?= htmlspecialchars($log['dose_quantity']) ?></span>
                                    <?php endif; ?>
                                    <?php if (empty($log['dosage']) && empty($log['dose_quantity'])): ?>
                                        <span style="color: #aaa;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.88rem; color: var(--text-mid); white-space: nowrap;">
                                    <?= !empty($log['scheduled_at']) ? date('d/m/Y H:i', strtotime($log['scheduled_at'])) : '-' ?>
                                </td>
                                <td style="font-size: 0.88rem; color: var(--text-mid); white-space: nowrap;">
                                    <?= !empty($log['taken_at']) ? date('d/m/Y H:i', strtotime($log['taken_at'])) : '-' ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($log['status'] == 'taken'): ?>
                                        <span class="badge-status badge-taken">✔ กินแล้ว</span>
                                    <?php elseif ($log['status'] == 'skipped'): ?>
                                        <span class="badge-status badge-skipped">⚠️ ข้ามมื้อ</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-missed">❌ พลาดมื้อ</span>
                                    <?php endif; ?>
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
