# 💊 MedinTime — Daily Medication Companion

ระบบเว็บแอปพลิเคชันสำหรับบันทึก แจ้งเตือน และจัดการการรับประทานยาประจำวัน (Medication Management & Reminder System) พัฒนาด้วย **PHP**, **MySQL** และ **Bootstrap** ภายใต้สภาพแวดล้อม **Docker Container**

---

## 🎨 Design Concept & Color Palette

หน้าเว็บได้รับการออกแบบให้ดูสะอาด ตา มีความรู้สึกผ่อนคลาย เหมาะกับแอปพลิเคชันดูแลสุขภาพและความเป็นอยู่ (Health & Wellness System)

### 🎨 ชุดสีประจำระบบ (Color Palette)
| สี | HEX Code | การนำไปใช้ในงานดีไซน์ |
| :--- | :--- | :--- |
| **Cream** | `#f5d8c8` | สีตกแต่งความอบอุ่น ละมุน แถบไฮไลต์ ป้ายเตือนขนาดเล็ก |
| **Silver / Light Gray** | `#dfe2e3` | สีพื้นหลังตาราง แท็บเลื่อน (Scrollbar) และขอบแบ่งส่วน |
| **Soft Lavender** | `#c6ccdf` | เส้นขอบฟอร์มกรอกข้อมูล และพื้นหลังตารางสลับแถบ |
| **Sky Blue** | `#acbedd` | สีรองสำหรับ Gradient, เส้นเน้นปุ่ม และ Card Accent |
| **Primary Blue** | `#709ed0` | สีหลักของแบรนด์, ปุ่มหลัก (CTA), หัวตาราง และแถบ Navigation Bar |
| **Deep Ocean Blue** | `#3f6a9a` | สีข้อความเน้นเข้ม, หัวข้อหลัก และพื้นหลังแถบเมนูหลัก |

---

## 🚀 ฟังก์ชันการทำงานของระบบ (Features)

- **หน้าหลักจัดการรายการยา (`index.php`):**
  - แสดงรายการยาทั้งหมดของผู้ป่วย/ผู้ใช้งาน พร้อมชื่อผู้ใช้ ขนาด/วิธีทาน จำนวนคงเหลือ ช่วงเวลาที่ใช้ยา
  - แสดงสถานะจำนวนยาเตือนสีแดงอัตโนมัติเมื่อคงเหลือ $\le 5$ เม็ด
  - ปุ่มแก้ไข (`edit_medication.php`) และปุ่มลบ (`delete_medication.php`)
- **เพิ่มรายการยาใหม่ (`add_medication.php`):**
  - ฟอร์มบันทึกยาใหม่ กรอกชื่อผู้ใช้ ชื่อยา ขนาดวิธีทาน จำนวนยาคงเหลือ และกำหนดวันเริ่ม-สิ้นสุด
  - ระบบ **Auto-Create User** อัตโนมัติ หากพิมพ์ชื่อผู้ใช้ใหม่ที่ยังไม่มีในฐานข้อมูล เพื่อป้องกันปัญหา Foreign Key
- **แก้ไขรายการยา (`edit_medication.php`):**
  - ดึงข้อมูลยาเดิมมาแก้ไข พร้อมรองรับการอัปเดตชื่อผู้ใช้ ยา ขนาด และจำนวนคงเหลือ
- **ระบบลบรายการยาอย่างปลอดภัย (`delete_medication.php`):**
  - รองรับ **Cascade Delete** ลบข้อมูลความสัมพันธ์ข้ามตาราง (`medication_logs`, `notifications`, `reminders`, `medications`) ออกจาก MySQL ทันทีอย่างสมบูรณ์แบบ
- **การตั้งเตือนรับประทานยา (`reminders.php`):**
  - แสดงรายการเวลาแจ้งเตือนรายวัน แปลงชื่อวันเป็นภาษาไทย (จ.-อา.) พร้อมป้ายสถานะ เปิด/ปิด ใช้งาน
- **ประวัติการรับประทานยา (`history.php`):**
  - บันทึกและแสดงประวัติการทานยา (Medication Logs) ย้อนหลัง แยกสถานะชัดเจน:
    - `taken` (✔ กินแล้ว)
    - `skipped` (⚠️ ข้ามมื้อ)
    - `missed` (❌ พลาดมื้อ)
- **สรุปภาพรวมและสถิติ (`dashboard.php`):**
  - แสดงการ์ดสรุปจำนวนผู้ใช้ทั้งหมด, จำนวนรายการยา, จำนวนการตั้งเตือนที่เปิดใช้งาน และรายการยาที่ใกล้หมด
  - คำนวณอัตราการกินยาตรงเวลา (**Adherence Rate %**) อัตโนมัติจากประวัติการทานยา

---

## 🗄️ โครงสร้างฐานข้อมูล (Database Schema)

ฐานข้อมูล `final_project_db` ประกอบด้วย 5 ตารางหลัก และ 1 View:
1. `users`: ข้อมูลผู้ใช้งาน / ผู้ป่วย (`user_id`, `name`, `email`, `password_hash`)
2. `medications`: รายการยา (`medication_id`, `user_id`, `medicine_name`, `dosage`, `stock_quantity`, `start_date`, `end_date`)
3. `reminders`: การตั้งเวลาเตือน (`reminder_id`, `medication_id`, `reminder_time`, `repeat_days`, `is_active`)
4. `medication_logs`: ประวัติการกินยาจริง (`log_id`, `reminder_id`, `scheduled_at`, `taken_at`, `status`)
5. `notifications`: ระบบการแจ้งเตือน (`notification_id`, `user_id`, `medication_id`, `reminder_id`, `notification_type`, `title`, `message`, `is_read`, `created_at`)
6. `view_dashboard_summary`: View สำหรับดึงข้อมูลสรุปตัวเลขสถิติต่างๆ ใน Dashboard

---

## 💻 วิธีการรันโปรเจกต์ผ่าน Docker

1. ตรวจสอบว่าเปิดโปรแกรม **Docker Desktop** เรียบร้อยแล้ว
2. เปิด Terminal ในโฟลเดอร์โปรเจกต์ แล้วรันคำสั่ง:
   ```bash
   docker compose up -d
   ```
   *(หากมีการแก้ไขโครงสร้างตารางใน `final_project_db.sql` และต้องการล้างข้อมูลใหม่ ให้ใช้ `docker compose down -v` แล้วสั่ง `docker compose up -d`)*

3. เข้าใช้งานผ่านเว็บเบราว์เซอร์:
   - **ระบบ MedinTime Web Application:** [http://localhost:8080](http://localhost:8080)
   - **ระบบจัดการฐานข้อมูล phpMyAdmin:** [http://localhost:8081](http://localhost:8081)
     - **Server:** `db`
     - **Username:** `user` (หรือ `root`)
     - **Password:** `userpassword` (หรือ `rootpassword`)
     - **Database:** `final_project_db`