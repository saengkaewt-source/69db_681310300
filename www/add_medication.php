<?php
require_once 'config/db_conn.php';

$error_msg = '';

// ดึงรายการยาประจำตัวจากตาราง medications สำหรับ dropdown (อิงตามหน้าแจ้งเตือน)
$med_stmt = $conn->query("
    SELECT DISTINCT medicine_name 
    FROM medications 
    WHERE medicine_name IS NOT NULL AND TRIM(medicine_name) != '' 
    ORDER BY medicine_name ASC
");
$medicine_list = $med_stmt ? $med_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $user_name = trim($_POST['user_name'] ?? '');
        
        // 1. จัดการชื่อยา (ดรอปดาวรายการยาประจำตัว หรือระบุเอง)
        $medicine_name = trim($_POST['medicine_name'] ?? '');
        if ($medicine_name === '__custom__') {
            $medicine_name = trim($_POST['custom_medicine_name'] ?? '');
        }

        // 2. จัดการขนาดยา (เลือกกรอกเป็น ml หรือ mg)
        $dosage_amount = trim($_POST['dosage_amount'] ?? '');
        $dosage_unit = trim($_POST['dosage_unit'] ?? 'mg');
        if ($dosage_amount !== '') {
            $dosage = $dosage_amount . ' ' . $dosage_unit;
        } else {
            $dosage = trim($_POST['dosage'] ?? '');
        }

        $dose_quantity = trim($_POST['dose_quantity'] ?? '');

        // 3. จัดการช่วงเวลาทานยา (ก่อน/หลังอาหาร + ปุ่มเลือกหลายตัวเลือก เช้า กลางวัน เย็น)
        $meal_timing = trim($_POST['meal_timing'] ?? ''); // เช่น ก่อนอาหาร / หลังอาหาร
        $meal_periods = $_POST['meal_periods'] ?? [];     // array เช่น ['เช้า', 'กลางวัน', 'เย็น']
        
        $periods_str = '';
        if (is_array($meal_periods) && !empty($meal_periods)) {
            $periods_str = implode(' - ', array_map('trim', $meal_periods));
        }

        $time_parts = [];
        if (!empty($meal_timing)) {
            $time_parts[] = $meal_timing;
        }
        if (!empty($periods_str)) {
            $time_parts[] = $periods_str;
        }

        $taking_time = !empty($time_parts) ? implode(' ', $time_parts) : trim($_POST['taking_time'] ?? '');

        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $start_date = $_POST['start_date'] ?? date('Y-m-d');
        $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days'));

        if (!empty($user_name) && !empty($medicine_name)) {
            // ค้นหา user_id จากชื่อ
            $stmt_user = $conn->prepare("SELECT user_id FROM users WHERE name = :name LIMIT 1");
            $stmt_user->execute([':name' => $user_name]);
            $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $user_id = $user['user_id'];
            } else {
                // ถ้าไม่พบชื่อ ให้สร้าง User ใหม่ให้อัตโนมัติ เพื่อไม่ให้ติด Constraint Foreign Key
                $dummy_email = 'user_' . time() . '@example.com';
                $stmt_new_user = $conn->prepare("INSERT INTO users (name, email, password_hash) VALUES (:name, :email, 'default_hash')");
                $stmt_new_user->execute([
                    ':name' => $user_name,
                    ':email' => $dummy_email
                ]);
                $user_id = $conn->lastInsertId();
            }

            // บันทึกข้อมูลรายการยาลงในตาราง medications
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
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <style>
        /* ปุ่มเลือกช่วงเวลาและมื้ออาหาร (Pill Buttons) */
        .pill-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 6px;
            margin-bottom: 14px;
        }
        .btn-pill {
            position: relative;
            cursor: pointer;
            user-select: none;
            margin: 0 !important;
            display: inline-block;
        }
        .btn-pill input[type="radio"],
        .btn-pill input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .btn-pill span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #ffffff;
            border: 1.5px solid var(--lavender, #c6ccdf);
            border-radius: 20px;
            font-size: 0.92rem;
            font-weight: 500;
            color: var(--text-dark, #2c3e50);
            transition: all 0.2s ease;
        }
        .btn-pill:hover span {
            border-color: var(--primary, #709ed0);
            background: #f7faff;
        }
        .btn-pill input[type="radio"]:checked + span,
        .btn-pill input[type="checkbox"]:checked + span {
            background: var(--primary-deeper, #3f6a9a);
            border-color: var(--primary-deeper, #3f6a9a);
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 3px 10px rgba(63, 106, 154, 0.28);
        }

        /* กลุ่มกรอกขนาดยา และเลือกหน่วย ml / mg */
        .dosage-input-group {
            display: flex;
            gap: 10px;
            align-items: stretch;
            margin-top: 4px;
            margin-bottom: 14px;
        }
        .dosage-input-group .form-control {
            margin: 0;
            flex: 1;
        }
        .unit-selector {
            display: flex;
            border: 1.5px solid var(--lavender, #c6ccdf);
            border-radius: var(--radius-sm, 6px);
            overflow: hidden;
            background: #fff;
        }
        .unit-btn {
            cursor: pointer;
            user-select: none;
            margin: 0 !important;
            display: flex;
            align-items: stretch;
        }
        .unit-btn input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .unit-btn span {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 18px;
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-mid, #4a5568);
            background: #fff;
            transition: all 0.2s ease;
        }
        .unit-btn:not(:first-child) span {
            border-left: 1px solid var(--silver, #dfe2e3);
        }
        .unit-btn input[type="radio"]:checked + span {
            background: var(--primary-deeper, #3f6a9a);
            color: #ffffff;
        }
    </style>
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

            <form method="POST" id="addMedForm">
                <label>ผู้ใช้งาน / ผู้ป่วย:</label>
                <input type="text" name="user_name" class="form-control" placeholder="กรอกชื่อของคุณ" required>

                <!-- ดรอปดาวชื่อยา อิงข้อมูลจากตาราง รายการยาประจำตัว หน้าแจ้งเตือน -->
                <label>ชื่อยา (รายการยาประจำตัว):</label>
                <select name="medicine_name" id="medicine_name_select" class="form-control" required onchange="handleMedicineSelect(this)">
                    <option value="" disabled selected>-- เลือกชื่อยาจากรายการยาประจำตัว --</option>
                    <?php foreach ($medicine_list as $m_name): ?>
                        <option value="<?= htmlspecialchars($m_name) ?>"><?= htmlspecialchars($m_name) ?></option>
                    <?php endforeach; ?>
                    <option value="__custom__">➕ ระบุชื่อยาอื่น ๆ...</option>
                </select>
                <div id="custom_med_wrapper" style="display: none; margin-top: -6px; margin-bottom: 14px;">
                    <input type="text" name="custom_medicine_name" id="custom_medicine_name" class="form-control" placeholder="พิมพ์ชื่อยาที่ต้องการเพิ่มใหม่">
                </div>

                <!-- ขนาดยาที่ทาน เลือกกรอกเป็น ml หรือ mg -->
                <label>ขนาดยาที่ทาน (เลือกกรอกเป็น ml หรือ mg):</label>
                <div class="dosage-input-group">
                    <input type="number" step="any" min="0" name="dosage_amount" class="form-control" placeholder="ระบุตัวเลข เช่น 500 หรือ 5" required>
                    <div class="unit-selector">
                        <label class="unit-btn">
                            <input type="radio" name="dosage_unit" value="mg" checked>
                            <span>mg</span>
                        </label>
                        <label class="unit-btn">
                            <input type="radio" name="dosage_unit" value="ml">
                            <span>ml</span>
                        </label>
                    </div>
                </div>

                <label>ทานกี่เม็ด / ปริมาณต่อครั้ง:</label>
                <input type="text" name="dose_quantity" class="form-control" placeholder="ระบุจำนวนยา เช่น 1 เม็ด, 2 ช้อนชา เป็นต้น">

                <!-- ก่อนหน้าช่วงเวลา มีส่วนเลือก ก่อน / หลัง อาหาร -->
                <label>การทานยาเทียบกับมื้ออาหาร (ก่อน / หลัง อาหาร):</label>
                <div class="pill-group">
                    <label class="btn-pill">
                        <input type="radio" name="meal_timing" value="ก่อนอาหาร">
                        <span>🥣 ก่อนอาหาร</span>
                    </label>
                    <label class="btn-pill">
                        <input type="radio" name="meal_timing" value="หลังอาหาร">
                        <span>🍲 หลังอาหาร</span>
                    </label>
                    <label class="btn-pill">
                        <input type="radio" name="meal_timing" value="พร้อมอาหาร">
                        <span>🍽️ พร้อมอาหาร</span>
                    </label>
                </div>

                <!-- กดปุ่มเลือกได้หลายตัวเลือก เช้า กลางวัน เย็น แทนดรอปดาว -->
                <label>ทานตอนไหน ช่วงไหน (กดปุ่มเลือกได้หลายตัวเลือก):</label>
                <div class="pill-group">
                    <label class="btn-pill">
                        <input type="checkbox" name="meal_periods[]" value="เช้า">
                        <span>☀️ เช้า</span>
                    </label>
                    <label class="btn-pill">
                        <input type="checkbox" name="meal_periods[]" value="กลางวัน">
                        <span>⛅ กลางวัน</span>
                    </label>
                    <label class="btn-pill">
                        <input type="checkbox" name="meal_periods[]" value="เย็น">
                        <span>🌙 เย็น</span>
                    </label>
                    <label class="btn-pill">
                        <input type="checkbox" name="meal_periods[]" value="ก่อนนอน">
                        <span>🛌 ก่อนนอน</span>
                    </label>
                    <label class="btn-pill">
                        <input type="checkbox" name="meal_periods[]" value="เมื่อมีอาการ">
                        <span>⚡ เมื่อมีอาการ</span>
                    </label>
                </div>

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

    <script>
    // สลับช่องกรอกชื่อยา เมื่อเลือก "ระบุชื่อยาอื่น ๆ"
    function handleMedicineSelect(select) {
        const customWrapper = document.getElementById('custom_med_wrapper');
        const customInput = document.getElementById('custom_medicine_name');
        if (select.value === '__custom__') {
            customWrapper.style.display = 'block';
            customInput.setAttribute('required', 'required');
            customInput.focus();
        } else {
            customWrapper.style.display = 'none';
            customInput.removeAttribute('required');
            customInput.value = '';
        }
    }

    // อนุญาตให้คลิกปุ่มวิทยุ (ก่อน/หลังอาหาร) ซ้ำเพื่อยกเลิกการเลือกได้
    document.querySelectorAll('input[name="meal_timing"]').forEach(radio => {
        radio.addEventListener('click', function() {
            if (this.dataset.waschecked === 'true') {
                this.checked = false;
                this.dataset.waschecked = 'false';
            } else {
                document.querySelectorAll('input[name="meal_timing"]').forEach(r => r.dataset.waschecked = 'false');
                this.dataset.waschecked = 'true';
            }
        });
    });

    // ตรวจสอบความถูกต้องก่อน submit ว่ามีการเลือกช่วงเวลาหรือมื้ออาหาร
    document.getElementById('addMedForm').addEventListener('submit', function(e) {
        const mealTiming = document.querySelector('input[name="meal_timing"]:checked');
        const periods = document.querySelectorAll('input[name="meal_periods[]"]:checked');
        if (!mealTiming && periods.length === 0) {
            e.preventDefault();
            alert('กรุณาเลือกช่วงเวลาทานยา (เช้า, กลางวัน, เย็น...) หรือระบุว่าทานก่อน/หลังอาหาร อย่างน้อย 1 รายการ');
            return false;
        }
    });
    </script>
</body>
</html>
