<?php
require_once 'config/db_conn.php';
include 'navbar.php';

$stmt = $conn->prepare("
    SELECT m.*, u.name as user_name 
    FROM medications m 
    JOIN users u ON m.user_id = u.user_id 
    ORDER BY m.medication_id DESC
");
$stmt->execute();
$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status = $_GET['status'] ?? '';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedinTime - รายการยาของฉัน</title>
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <!-- SweetAlert2 CSS & JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container" style="max-width: 1250px;">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">📋 รายการยาทั้งหมด (My Medicines)</h2>
                <a href="add_medication.php" class="btn-primary" style="text-decoration: none; font-size: 0.9rem;">
                    ➕ เพิ่มรายการยาใหม่
                </a>
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>ผู้ใช้งาน</th>
                            <th>ชื่อยา</th>
                            <th>ขนาดยา</th>
                            <th>ทานกี่เม็ด</th>
                            <th>ทานตอนไหน / ช่วงไหน</th>
                            <th>จำนวนคงเหลือ</th>
                            <th>ระยะเวลาที่ใช้ยา</th>
                            <th style="text-align: center; width: 140px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medications)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 30px; color: #888;">
                                    ยังไม่มีข้อมูลรายการยา คลิกที่ปุ่ม <strong>"เพิ่มรายการยาใหม่"</strong> เพื่อเริ่มต้นเพิ่มข้อมูล
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($medications as $med): ?>
                            <tr>
                                <td><?= $med['medication_id'] ?></td>
                                <td><strong><?= htmlspecialchars($med['user_name']) ?></strong></td>
                                <td><span style="font-weight: 600; color: var(--primary-deeper);"><?= htmlspecialchars($med['medicine_name']) ?></span></td>
                                <td>
                                    <?php if (!empty($med['dosage'])): ?>
                                        <span class="badge-dosage"><?= htmlspecialchars($med['dosage']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #aaa;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($med['dose_quantity'])): ?>
                                        <span class="badge-qty"><?= htmlspecialchars($med['dose_quantity']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #aaa;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($med['taking_time'])): ?>
                                        <span class="badge-time"><?= htmlspecialchars($med['taking_time']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #aaa;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: <?= $med['stock_quantity'] <= 5 ? '#e74c3c' : '#27ae60' ?>;">
                                        <?= number_format($med['stock_quantity']) ?> เม็ด
                                    </strong>
                                </td>
                                <td style="font-size: 0.88rem; color: var(--text-mid);">
                                    <?= $med['start_date'] ?> ถึง <?= $med['end_date'] ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="edit_medication.php?id=<?= $med['medication_id'] ?>" style="color: #3f6a9a; margin-right: 8px; text-decoration: none; font-weight: 600;">✏️ แก้ไข</a>
                                    <button type="button" 
                                            class="btn-danger" 
                                            style="padding: 4px 10px; font-size: 0.82rem; border-radius: 6px; cursor: pointer;" 
                                            onclick="confirmDelete(<?= $med['medication_id'] ?>, '<?= htmlspecialchars(addslashes($med['medicine_name'])) ?>')">
                                        ลบ
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    // 1. ฟังก์ชันป๊อปอัพยืนยันการลบ
    function confirmDelete(id, name) {
        Swal.fire({
            title: 'ยืนยันการลบรายการยา?',
            html: `คุณต้องการลบ <strong>"${name}"</strong> ออกจากระบบใช่หรือไม่?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ยืนยันการลบ',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete_medication.php?id=' + id;
            }
        });
    }

    // 2. ป๊อปอัพแจ้งเตือนเมื่อบันทึกหรือลบสำเร็จ
    document.addEventListener("DOMContentLoaded", function() {
        const status = "<?= htmlspecialchars($status) ?>";
        
        if (status === 'added') {
            Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ!',
                text: 'เพิ่มรายการยาใหม่เรียบร้อยแล้ว',
                confirmButtonColor: '#709ed0',
                timer: 2500,
                timerProgressBar: true
            });
            window.history.replaceState(null, null, window.location.pathname);
        } else if (status === 'updated') {
            Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ!',
                text: 'อัปเดตข้อมูลรายการยาเรียบร้อยแล้ว',
                confirmButtonColor: '#709ed0',
                timer: 2500,
                timerProgressBar: true
            });
            window.history.replaceState(null, null, window.location.pathname);
        } else if (status === 'deleted') {
            Swal.fire({
                icon: 'success',
                title: 'ลบสำเร็จ!',
                text: 'ลบรายการยาออกจากระบบเรียบร้อยแล้ว',
                confirmButtonColor: '#709ed0',
                timer: 2500,
                timerProgressBar: true
            });
            window.history.replaceState(null, null, window.location.pathname);
        }
    });
    </script>
</body>
</html>