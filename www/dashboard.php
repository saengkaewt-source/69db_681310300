<?php
require_once 'config/db_conn.php';
include 'navbar.php';

// 1. จำนวนผู้ใช้ทั้งหมด
$stmt = $conn->query("SELECT COUNT(*) FROM users");
$total_users = $stmt->fetchColumn();

// 2. จำนวนรายการยาทั้งหมด
$stmt = $conn->query("SELECT COUNT(*) FROM medications");
$total_meds = $stmt->fetchColumn();

// 3. จำนวนการแจ้งเตือนทั้งหมด และที่เปิดใช้งานอยู่
$stmt = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active FROM reminders");
$reminders_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_reminders = $reminders_data['total'] ?? 0;
$active_reminders = $reminders_data['active'] ?? 0;

// 4. ยาใกล้หมด (คงเหลือ <= 5 เม็ด)
$stmt = $conn->query("SELECT COUNT(*) FROM medications WHERE stock_quantity <= 5");
$low_stock_meds = $stmt->fetchColumn();

// 5. จำนวนบันทึกการกินยา และสรุปแยกตามสถานะ (taken, skipped, missed)
$stmt = $conn->query("
    SELECT 
        COUNT(*) as total_logs,
        SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken_count,
        SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped_count,
        SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed_count
    FROM medication_logs
");
$logs_summary = $stmt->fetch(PDO::FETCH_ASSOC);

$total_logs = $logs_summary['total_logs'] ?? 0;
$taken_count = $logs_summary['taken_count'] ?? 0;
$skipped_count = $logs_summary['skipped_count'] ?? 0;
$missed_count = $logs_summary['missed_count'] ?? 0;

// คำนวณสัดส่วน (%)
$taken_pct = $total_logs > 0 ? round(($taken_count / $total_logs) * 100, 1) : 0;
$skipped_pct = $total_logs > 0 ? round(($skipped_count / $total_logs) * 100, 1) : 0;
$missed_pct = $total_logs > 0 ? round(($missed_count / $total_logs) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>MedinTime - Dashboard สรุปภาพรวม</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: var(--radius-md);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border-left: 5px solid var(--primary);
            transition: var(--transition);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .stat-card.warning { border-left-color: #e74c3c; }
        .stat-card.success { border-left-color: #2ecc71; }
        .stat-card.info { border-left-color: #3498db; }
        
        .stat-title {
            font-size: 0.9rem;
            color: var(--text-light);
            font-weight: 600;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-deeper);
            margin-top: 5px;
        }
        .stat-desc {
            font-size: 0.82rem;
            color: var(--text-mid);
            margin-top: 4px;
        }
        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .pill-taken { background: #e8f8f5; color: #27ae60; }
        .pill-skipped { background: #fef9e7; color: #f39c12; }
        .pill-missed { background: #fadbd8; color: #c0392b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h2>📊 MedinTime Admin – Dashboard</h2>
            <p style="color: var(--text-light); margin-top: -10px; margin-bottom: 20px;">
                สรุปภาพรวมข้อมูลผู้ใช้งาน รายการยา และสถิติการรับประทานยา
            </p>

            <!-- Grid การ์ดสรุปตัวเลข -->
            <div class="grid-stats">
                <div class="stat-card info">
                    <div class="stat-title">จำนวนผู้ใช้ทั้งหมด</div>
                    <div class="stat-value"><?= number_format($total_users) ?></div>
                    <div class="stat-desc">ผู้ใช้งานในระบบ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">จำนวนรายการยา</div>
                    <div class="stat-value"><?= number_format($total_meds) ?></div>
                    <div class="stat-desc">รายการยาทั้งหมด</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-title">การตั้งแจ้งเตือน</div>
                    <div class="stat-value"><?= number_format($active_reminders) ?> / <?= number_format($total_reminders) ?></div>
                    <div class="stat-desc">เปิดใช้งาน / ทั้งหมด</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-title">ยาใกล้หมด</div>
                    <div class="stat-value" style="color: #e74c3c;"><?= number_format($low_stock_meds) ?></div>
                    <div class="stat-desc">คงเหลือ ≤ 5 เม็ด</div>
                </div>
            </div>

            <!-- ตารางสรุปสถานะการกินยา -->
            <h3>📋 สรุปสถานะการกินยา (Medication Adherence)</h3>
            <table>
                <thead>
                    <tr>
                        <th>สถานะ</th>
                        <th>จำนวน (ครั้ง)</th>
                        <th>สัดส่วน (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="status-pill pill-taken">Taken (กินแล้ว)</span></td>
                        <td><strong><?= number_format($taken_count) ?></strong></td>
                        <td><?= $taken_pct ?>%</td>
                    </tr>
                    <tr>
                        <td><span class="status-pill pill-skipped">Skipped (ข้าม)</span></td>
                        <td><strong><?= number_format($skipped_count) ?></strong></td>
                        <td><?= $skipped_pct ?>%</td>
                    </tr>
                    <tr>
                        <td><span class="status-pill pill-missed">Missed (ไม่ได้กิน)</span></td>
                        <td><strong><?= number_format($missed_count) ?></strong></td>
                        <td><?= $missed_pct ?>%</td>
                    </tr>
                    <tr style="background: rgba(112, 158, 208, 0.1); font-weight: bold;">
                        <td>รวมบันทึกการกินยาทั้งหมด</td>
                        <td><?= number_format($total_logs) ?></td>
                        <td>100.0%</td>
                    </tr>
                </tbody>
            </table>

            <div style="margin-top: 20px; padding: 12px; background: #eef1f8; border-radius: 8px; font-size: 0.9rem;">
                💡 <strong>อัตราการกินยาตรงเวลา (Adherence Rate):</strong> 
                <span style="color: #27ae60; font-size: 1.1rem; font-weight: bold;"><?= $taken_pct ?>%</span>
                (คำนวณจากจำนวน Taken ÷ บันทึกทั้งหมด)
            </div>
        </div>
    </div>
</body>
</html>