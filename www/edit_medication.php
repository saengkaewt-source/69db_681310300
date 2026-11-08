<?php
require_once 'config/db_conn.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$medication_id = $_GET['id'];
$error_msg = '';

// 1. ดึงข้อมูลรายการยาที่ต้องการแก้ไข
$stmt = $conn->prepare("SELECT * FROM medications WHERE medication_id = :id");
$stmt->execute([':id' => $medication_id]);
$medication = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$medication) {
    header("Location: index.php");
    exit();
}

// 2. ดึงชื่อผู้ใช้
$user_stmt = $conn->prepare("SELECT name FROM users WHERE user_id = :id");
$user_stmt->execute([':id' => $medication['user_id']]);
$user_name = $user_stmt->fetchColumn() ?: '';

// 3. ดึงรายการยาประจำตัวสำหรับดรอปดาวน์
$med_stmt = $conn->query("
    SELECT DISTINCT medicine_name 
    FROM medications 
    WHERE medicine_name IS NOT NULL AND TRIM(medicine_name) != '' 
    ORDER BY medicine_name ASC
");
$medicine_list = $med_stmt ? $med_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

// แปลงค่าขนาดยาเดิม (dosage) เพื่อแยกตัวเลขและหน่วย ml/mg
$cur_dosage = trim($medication['dosage'] ?? '');
$dosage_amount = '';
$dosage_unit = 'mg';
if (preg_match('/^([\d\.]+)\s*(mg|ml)/i', $cur_dosage, $matches)) {
    $dosage_amount = $matches[1];
    $dosage_unit = strtolower($matches[2]);
} elseif (preg_match('/([\d\.]+)/', $cur_dosage, $matches)) {
    $dosage_amount = $matches[1];
    if (stripos($cur_dosage, 'ml') !== false) {
        $dosage_unit = 'ml';
    } else {
        $dosage_unit = 'mg';
    }
}

// แปลงค่าเวลาทานเดิม (taking_time) เพื่อติ๊กเลือกค่าเริ่มต้น
$cur_taking_time = $medication['taking_time'] ?? '';
$selected_meal_timing = '';
if (strpos($cur_taking_time, 'ก่อนอาหาร') !== false) {
    $selected_meal_timing = 'ก่อนอาหาร';
} elseif (strpos($cur_taking_time, 'หลังอาหาร') !== false) {
    $selected_meal_timing = 'หลังอาหาร';
} elseif (strpos($cur_taking_time, 'พร้อมอาหาร') !== false) {
    $selected_meal_timing = 'พร้อมอาหาร';
}

$has_morning  = (strpos($cur_taking_time, 'เช้า') !== false);
$has_noon     = (strpos($cur_taking_time, 'กลางวัน') !== false);
$has_evening  = (strpos($cur_taking_time, 'เย็น') !== false);
$has_bedtime  = (strpos($cur_taking_time, 'ก่อนนอน') !== false);
$has_symptom  = (strpos($cur_taking_time, 'เมื่อมีอาการ') !== false);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $user_name = trim($_POST['user_name'] ?? '');
        
        // 1. จัดการชื่อยา
        $medicine_name = trim($_POST['medicine_name'] ?? '');
        if ($medicine_name === '__custom__') {
            $medicine_name = trim($_POST['custom_medicine_name'] ?? '');
        }

        // 2. จัดการขนาดยา (เลือกกรอกเป็น ml หรือ mg)
        $dosage_amount_post = trim($_POST['dosage_amount'] ?? '');
        $dosage_unit_post = trim($_POST['dosage_unit'] ?? 'mg');
        if ($dosage_amount_post !== '') {
            $dosage = $dosage_amount_post . ' ' . $dosage_unit_post;
        } else {
            $dosage = trim($_POST['dosage'] ?? '');
        }

        $dose_quantity = trim($_POST['dose_quantity'] ?? '');

        // 3. จัดการช่วงเวลาทานยา (ก่อน/หลังอาหาร + ปุ่มเลือกหลายตัวเลือก เช้า กลางวัน เย็น)
        $meal_timing = trim($_POST['meal_timing'] ?? '');
        $meal_periods = $_POST['meal_periods'] ?? [];
        
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
        } else {
            $error_msg = "กรุณากรอกข้อมูลชื่อผู้ใช้งานและชื่อยาให้ครบถ้วน";
        }
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
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <h2 class="card-title">✏️ แก้ไขรายการยา (Edit Medication)</h2>

            <?php if (!empty($error_msg)): ?>
                <div style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #f5c6cb;">
                    ⚠️ <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="editMedForm">
                <label>ผู้ใช้งาน / ผู้ป่วย:</label>
                <input type="text" name="user_name" class="form-control" value="<?= htmlspecialchars($user_name) ?>" required>

                <!-- ดรอปดาวชื่อยา อิงข้อมูลจากตาราง รายการยาประจำตัว หน้าแจ้งเตือน -->
                <label>ชื่อยา (รายการยาประจำตัว):</label>
                <select name="medicine_name" id="medicine_name_select" class="form-control" required onchange="handleMedicineSelect(this)">
                    <option value="" disabled>-- เลือกชื่อยาจากรายการยาประจำตัว --</option>
                    <?php 
                    $cur_med_name = $medication['medicine_name'];
                    $name_in_list = in_array($cur_med_name, $medicine_list);
                    foreach ($medicine_list as $m_name): 
                    ?>
                        <option value="<?= htmlspecialchars($m_name) ?>" <?= ($cur_med_name === $m_name) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m_name) ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (!empty($cur_med_name) && !$name_in_list): ?>
                        <option value="<?= htmlspecialchars($cur_med_name) ?>" selected>
                            <?= htmlspecialchars($cur_med_name) ?> (ปัจจุบัน)
                        </option>
                    <?php endif; ?>
                    <option value="__custom__">➕ ระบุชื่อยาอื่น ๆ...</option>
                </select>
                <div id="custom_med_wrapper" style="display: none; margin-top: -6px; margin-bottom: 14px;">
                    <input type="text" name="custom_medicine_name" id="custom_medicine_name" class="form-control" placeholder="พิมพ์ชื่อยาที่ต้องการ">
                </div>

                <!-- ขนาดยาที่ทาน เลือกกรอกเป็น ml หรือ mg -->
                <label>ขนาดยาที่ทาน (เลือกกรอกเป็น ml หรือ mg):</label>
                <div class="dosage-input-group">
                    <input type="number" step="any" min="0" name="dosage_amount" class="form-control" 
                           placeholder="ระบุตัวเลข เช่น 500 หรือ 5" 
                           value="<?= htmlspecialchars($dosage_amount) ?>" required>
                    <div class="unit-selector">
                        <label class="unit-btn">
                            <input type="radio" name="dosage_unit" value="mg" <?= ($dosage_unit === 'mg') ? 'checked' : '' ?>>
                            <span>mg</span>
                        </label>
                        <label class="unit-btn">
                            <input type="radio" name="dosage_unit" value="ml" <?= ($dosage_unit === 'ml') ? 'checked' : '' ?>>
                            <span>ml</span>
                        </label>
                    </div>
                </div>

                <label>ทานกี่เม็ด / ปริมาณต่อครั้ง:</label>
                <input type="text" name="dose_quantity" class="form-control" 
                       value="<?= htmlspecialchars($medication['dose_quantity'] ?? '') ?>" 
                       placeholder="ระบุจำนวนยา เช่น 1 เม็ด, 2 ช้อนชา เป็นต้น">

                <!-- ก่อนหน้าช่วงเวลา มีส่วนเลือก ก่อน / หลัง อาหาร -->
                <label>การทานยาเทียบกับมื้ออาหาร (ก่อน / หลัง อาหาร):</label>
                <div class="pill-group">
                    <label class="btn-pill">
                        <input type="radio" name="meal_timing" value="ก่อนอาหาร" 
                               <?= ($selected_meal_timing === 'ก่อนอาหาร') ? 'checked data-waschecked="true"' : 'data-waschecked="false"' ?>>
                        <span>🥣 ก่อนอาหาร</span>
                    </label>
                    <label class="btn-pill">
                        <input type="radio" name="meal_timing" value="หลังอาหาร" 
                               <?= ($selected_meal_timing === 'หลังอาหาร') ? 'checked data-waschecked="true"' : 'data-waschecked="false"' ?>>
                        <span>🍲 หลังอาหาร</span>
                    </label>
                    <label class="btn-pill">
                        <input type="radio" name="meal_timing" value="พร้อมอาหาร" 
                               <?= ($selected_meal_timing === 'พร้อมอาหาร') ? 'checked data-waschecked="true"' : 'data-waschecked="false"' ?>>
                        <span>🍽️ พร้อมอาหาร</span>
                    </label>
                </div>

                <!-- กดปุ่มเลือกได้หลายตัวเลือก เช้า กลางวัน เย็น แทนดรอปดาว -->
                <label>ทานตอนไหน ช่วงไหน (กดปุ่มเลือกได้หลายตัวเลือก):</label>
                <div class="pill-group">
                    <label class="btn-pill">
                        <input type="checkbox" name="meal_periods[]" value="เช้า" <?= $has_morning ? 'checked' : '' ?>>
                        <span>☀️ เช้า</span>
                    </label>
                    <label class="btn-pill">
                        <input type="checkbox" name="meal_periods[]" value="กลางวัน" <?= $has_noon ? 'checked' : '' ?>>
                        <span>⛅ กลางวัน</span>
                    </label>
                    <label class="btn-pill">
                        <input type="checkbox" name="meal_periods[]" value="เย็น" <?= $has_evening ? 'checked' : '' ?>>
                        <span>🌙 เย็น</span>
                    </label>
                    <label class="btn-pill">
                        <input type="checkbox" name="meal_periods[]" value="ก่อนนอน" <?= $has_bedtime ? 'checked' : '' ?>>
                        <span>🛌 ก่อนนอน</span>
                    </label>
                    <label class="btn-pill">
                        <input type="checkbox" name="meal_periods[]" value="เมื่อมีอาการ" <?= $has_symptom ? 'checked' : '' ?>>
                        <span>⚡ เมื่อมีอาการ</span>
                    </label>
                </div>

                <label>จำนวนยาคงเหลือ (เม็ด):</label>
                <input type="number" name="stock_quantity" class="form-control" value="<?= $medication['stock_quantity'] ?>" min="0" required>

                <label>วันเริ่มทานยา:</label>
                <input type="date" name="start_date" class="form-control" value="<?= $medication['start_date'] ?>">

                <label>วันสิ้นสุดการใช้ยา:</label>
                <input type="date" name="end_date" class="form-control" value="<?= $medication['end_date'] ?>">

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 15px;">บันทึกการแก้ไข</button>
                <a href="index.php" style="display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none;">ยกเลิก / กลับหน้าหลัก</a>
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

    // ตรวจสอบความถูกต้องก่อน submit
    document.getElementById('editMedForm').addEventListener('submit', function(e) {
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
