<?php
$pageTitle = 'ฝ่ายบุคคลและบริหารระบบ (HR & Administration)';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_department('hr');
$db = get_db();
$currentUser = current_user();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Add Employee
    if ($action === 'add_employee') {
        $username     = trim($_POST['username'] ?? '');
        $password     = trim($_POST['password'] ?? '');
        $full_name    = trim($_POST['full_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $role         = trim($_POST['role'] ?? 'technician');
        $emp_code     = trim($_POST['employee_code'] ?? '');
        $salary       = (float)($_POST['salary'] ?? 0);

        if (empty($username) || empty($password) || empty($full_name)) {
            set_flash('danger', 'กรุณากรอกชื่อผู้ใช้, รหัสผ่าน และชื่อ-นามสกุลพนักงาน');
        } else {
            // Check duplicate username
            $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                set_flash('danger', 'ชื่อผู้ใช้ "' . htmlspecialchars($username) . '" มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น');
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                if (empty($emp_code)) {
                    $emp_code = 'EMP-' . strtoupper(substr($role, 0, 3)) . '-' . rand(10, 99);
                }
                $now = date('Y-m-d H:i:s');
                $insert = $db->prepare("INSERT INTO users (username, password, full_name, email, phone, role, employee_code, salary, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
                $insert->execute([$username, $hashed, $full_name, $email, $phone, $role, $emp_code, $salary, $now]);
                set_flash('success', 'เพิ่มพนักงานใหม่ "' . htmlspecialchars($full_name) . '" (รหัส: ' . htmlspecialchars($emp_code) . ') สำเร็จ');
            }
        }
        header("Location: " . base_url('admin/hr.php'));
        exit;
    }

    // 2. Edit Employee
    if ($action === 'edit_employee') {
        $id        = (int)($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $role      = trim($_POST['role'] ?? 'technician');
        $emp_code  = trim($_POST['employee_code'] ?? '');
        $salary    = (float)($_POST['salary'] ?? 0);
        $status    = trim($_POST['status'] ?? 'active');

        if ($id > 0 && !empty($full_name)) {
            $update = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, employee_code = ?, salary = ?, status = ? WHERE id = ?");
            $update->execute([$full_name, $email, $phone, $role, $emp_code, $salary, $status, $id]);
            set_flash('success', 'อัปเดตข้อมูลพนักงาน ID #' . $id . ' เรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/hr.php'));
        exit;
    }

    // 3. Change Employee Password
    if ($action === 'change_password') {
        $id       = (int)($_POST['id'] ?? 0);
        $new_pass = trim($_POST['new_password'] ?? '');

        if ($id > 0 && strlen($new_pass) >= 4) {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $id]);
            set_flash('success', 'เปลี่ยนรหัสผ่านของพนักงาน ID #' . $id . ' เรียบร้อยแล้ว (รหัสใหม่: ' . htmlspecialchars($new_pass) . ')');
        } else {
            set_flash('danger', 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 4 ตัวอักษร');
        }
        header("Location: " . base_url('admin/hr.php'));
        exit;
    }

    // 4. Delete Employee
    if ($action === 'delete_employee') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$currentUser['id']) {
            set_flash('danger', 'ไม่สามารถลบบัญชีของตัวเองได้');
        } elseif ($id === 1) {
            set_flash('danger', 'ไม่สามารถลบบัญชีประธานกรรมการบริหาร (CEO) ได้');
        } else {
            $del = $db->prepare("DELETE FROM users WHERE id = ?");
            $del->execute([$id]);
            set_flash('success', 'ลบพนักงานออกจากระบบเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/hr.php'));
        exit;
    }

    // 5. Update Department Security Passcode
    if ($action === 'update_passcode') {
        $dept_key = trim($_POST['dept_key'] ?? '');
        $passcode = trim($_POST['passcode'] ?? '');
        $dept_name = trim($_POST['dept_name'] ?? '');

        if (!empty($dept_key) && !empty($passcode)) {
            $stmt = $db->prepare("UPDATE departments SET passcode = ?, dept_name = ? WHERE dept_key = ?");
            $stmt->execute([$passcode, $dept_name, $dept_key]);
            set_flash('success', 'อัปเดตรหัสลับแผนก "' . htmlspecialchars($dept_name) . '" เป็น [' . htmlspecialchars($passcode) . '] สำเร็จ');
        }
        header("Location: " . base_url('admin/hr.php'));
        exit;
    }

    // 6. Add New Department
    if ($action === 'add_department') {
        $dept_key    = strtolower(trim($_POST['dept_key'] ?? ''));
        $dept_name   = trim($_POST['dept_name'] ?? '');
        $passcode    = trim($_POST['passcode'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!empty($dept_key) && !empty($passcode) && !empty($dept_name)) {
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare("INSERT INTO departments (dept_key, dept_name, passcode, description, updated_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$dept_key, $dept_name, $passcode, $description, $now]);
            set_flash('success', 'เพิ่มแผนกใหม่และรหัสลับสำเร็จ');
        }
        header("Location: " . base_url('admin/hr.php'));
        exit;
    }
}

// Fetch all staff (non-customers)
$staffList = $db->query("SELECT * FROM users WHERE role != 'customer' ORDER BY id ASC")->fetchAll();

// Fetch customer accounts count
$customerCount = $db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();

// Fetch all departments
$departments = $db->query("SELECT * FROM departments ORDER BY id ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-[calc(100vh-140px)]">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Workspace Area -->
    <div class="flex-1 p-6 lg:p-8 bg-slate-50 overflow-y-auto">
        
        <!-- Header Banner -->
        <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-slate-200 gap-4 mb-6">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                        <i data-lucide="users" class="w-3.5 h-3.5 inline mr-1"></i> แผนกฝ่ายบุคคล (HR & Administration)
                    </span>
                    <span class="text-xs text-slate-400">ควบคุมสิทธิ์และบุคลากรทั้งหมด</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">จัดการข้อมูลและบัญชีพนักงาน (Staff Management)</h1>
                <p class="text-xs text-slate-500">
                    ฝ่ายบุคคล (HR) และ Admin สามารถ ดูรายชื่อ, แก้ไขข้อมูล, เปลี่ยนรหัสผ่าน, เพิ่มพนักงาน และระงับ/ลบพนักงานได้ที่นี่
                </p>
            </div>

            <div class="flex items-center space-x-2">
                <button onclick="openModal('modalAddEmployee')" class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-sm flex items-center space-x-1.5 transition">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>เพิ่มพนักงานใหม่</span>
                </button>
            </div>
        </div>

        <!-- 3 KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500">บุคลากรพนักงานทั้งหมด</span>
                    <span class="p-2 rounded-xl bg-blue-50 text-blue-600"><i data-lucide="users" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo count($staffList); ?> คน</div>
                <div class="text-[11px] text-slate-400 mt-1">ครอบคลุมทั้ง 6 แผนกการทำงาน</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500">สถานะปฏิบัติหน้าที่ปกติ</span>
                    <span class="p-2 rounded-xl bg-emerald-50 text-emerald-600"><i data-lucide="shield-check" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-emerald-600 mt-2"><?php echo count($staffList); ?> บัญชี</div>
                <div class="text-[11px] text-emerald-700 font-medium mt-1">Active พร้อมปฏิบัติงาน</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500">สมาชิกบัญชีลูกค้า</span>
                    <span class="p-2 rounded-xl bg-slate-100 text-slate-600"><i data-lucide="user-check" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo $customerCount; ?> บัญชี</div>
                <div class="text-[11px] text-slate-400 mt-1">ลูกค้าทั่วไป (ไม่สามารถเข้าหน้าแผนกได้)</div>
            </div>
        </div>

        <!-- Section: Employee Staff Directory & CRUD -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">รายชื่อพนักงานทั้งหมด (Staff Management)</h2>
                    <p class="text-xs text-slate-500">ดูรายชื่อ แก้ไขข้อมูล ตั้งรหัสผ่านใหม่ หรือระงับ/ลบพนักงานออกจากระบบ</p>
                </div>
                <div class="text-xs text-slate-400">
                    แสดงทั้งหมด <span class="font-bold text-slate-700"><?php echo count($staffList); ?></span> คน
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4 font-semibold">รหัส / ชื่อพนักงาน</th>
                            <th class="py-3 px-4 font-semibold">Username</th>
                            <th class="py-3 px-4 font-semibold">แผนก / บทบาท</th>
                            <th class="py-3 px-4 font-semibold">เบอร์โทรศัพท์</th>
                            <th class="py-3 px-4 font-semibold">เงินเดือน</th>
                            <th class="py-3 px-4 font-semibold">สถานะ</th>
                            <th class="py-3 px-4 font-semibold text-center">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach ($staffList as $staff): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-xs uppercase">
                                            <?php echo mb_substr($staff['full_name'], 0, 1, 'UTF-8'); ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900"><?php echo htmlspecialchars($staff['full_name']); ?></div>
                                            <div class="text-[11px] font-mono text-slate-400"><?php echo htmlspecialchars($staff['employee_code'] ?? 'EMP-00'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4 font-mono font-medium text-slate-600">
                                    @<?php echo htmlspecialchars($staff['username']); ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php 
                                            switch($staff['role']) {
                                                case 'president': echo 'bg-purple-100 text-purple-800'; break;
                                                case 'hr': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'production': echo 'bg-emerald-100 text-emerald-800'; break;
                                                case 'stock': echo 'bg-amber-100 text-amber-800'; break;
                                                case 'sales': echo 'bg-pink-100 text-pink-800'; break;
                                                case 'technician': echo 'bg-cyan-100 text-cyan-800'; break;
                                                default: echo 'bg-slate-100 text-slate-800';
                                            }
                                        ?>">
                                        <?php echo get_role_title($staff['role']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <?php echo htmlspecialchars($staff['phone'] ?? '-'); ?>
                                </td>
                                <td class="py-3 px-4 font-mono font-medium text-slate-800">
                                    <?php echo format_baht($staff['salary'] ?? 0); ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($staff['status'] === 'active'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span> ปกติ
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-rose-100 text-rose-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5"></span> ระงับ
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="inline-flex items-center space-x-1">
                                        <!-- Edit button -->
                                        <button onclick='openEditModal(<?php echo json_encode($staff); ?>)' class="p-1.5 rounded-lg text-slate-500 hover:text-sky-600 hover:bg-sky-50 transition" title="แก้ไขข้อมูล">
                                            <i data-lucide="edit-2" class="w-4 h-4"></i>
                                        </button>
                                        <!-- Reset Password -->
                                        <button onclick="openPassModal(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars(addslashes($staff['full_name'])); ?>')" class="p-1.5 rounded-lg text-slate-500 hover:text-amber-600 hover:bg-amber-50 transition" title="เปลี่ยนรหัสผ่าน">
                                            <i data-lucide="key" class="w-4 h-4"></i>
                                        </button>
                                        <!-- Delete button -->
                                        <?php if ($staff['id'] != 1 && $staff['id'] != $currentUser['id']): ?>
                                            <form method="POST" action="hr.php" class="inline" onsubmit="return confirm('ยืนยันที่จะลบพนักงาน <?php echo htmlspecialchars(addslashes($staff['full_name'])); ?> หรือไม่?');">
                                                <input type="hidden" name="action" value="delete_employee">
                                                <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
                                                <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="ลบพนักงาน">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal 1: Add Employee -->
<div id="modalAddEmployee" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="user-plus" class="w-5 h-5 text-blue-600"></i>
                <span>เพิ่มพนักงานใหม่เข้าสู่ระบบ</span>
            </h3>
            <button onclick="closeModal('modalAddEmployee')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="hr.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="add_employee">
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ชื่อผู้ใช้ (Username) <span class="text-rose-500">*</span></label>
                    <input type="text" name="username" required placeholder="เช่น tech3, sales2" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">รหัสผ่านเริ่มต้น <span class="text-rose-500">*</span></label>
                    <input type="text" name="password" required value="123456" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500 font-mono">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">ชื่อ-นามสกุลพนักงาน <span class="text-rose-500">*</span></label>
                <input type="text" name="full_name" required placeholder="เช่น นายสมศักดิ์ ขยันยิ่ง" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">แผนก / บทบาท <span class="text-rose-500">*</span></label>
                    <select name="role" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500">
                        <option value="technician">ฝ่ายช่างซ่อม (Technician)</option>
                        <option value="sales">ฝ่ายขาย (Sales)</option>
                        <option value="stock">ฝ่ายสต็อก (Stock)</option>
                        <option value="production">ฝ่ายผลิต/QC (Production)</option>
                        <option value="hr">ฝ่ายบุคคล/Admin (HR)</option>
                        <option value="president">ประธาน (President)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">รหัสพนักงาน (ถ้ามี)</label>
                    <input type="text" name="employee_code" placeholder="เช่น EMP-TEC-03" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">เบอร์โทรศัพท์</label>
                    <input type="tel" name="phone" placeholder="08x-xxx-xxxx" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">เงินเดือน (บาท)</label>
                    <input type="number" step="any" name="salary" placeholder="เช่น 25000" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">อีเมลพนักงาน</label>
                <input type="email" name="email" placeholder="staff@technotepro.com" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500">
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalAddEmployee')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-medium shadow-sm">บันทึกพนักงานใหม่</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit Employee -->
<div id="modalEditEmployee" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="edit" class="w-5 h-5 text-sky-600"></i>
                <span>แก้ไขข้อมูลพนักงาน</span>
            </h3>
            <button onclick="closeModal('modalEditEmployee')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="hr.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="edit_employee">
            <input type="hidden" name="id" id="editEmpId">
            
            <div>
                <label class="block font-semibold text-slate-700 mb-1">ชื่อ-นามสกุลพนักงาน <span class="text-rose-500">*</span></label>
                <input type="text" name="full_name" id="editEmpName" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-sky-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">แผนก / บทบาท</label>
                    <select name="role" id="editEmpRole" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-sky-500">
                        <option value="technician">ฝ่ายช่างซ่อม (Technician)</option>
                        <option value="sales">ฝ่ายขาย (Sales)</option>
                        <option value="stock">ฝ่ายสต็อก (Stock)</option>
                        <option value="production">ฝ่ายผลิต/QC (Production)</option>
                        <option value="hr">ฝ่ายบุคคล/Admin (HR)</option>
                        <option value="president">ประธาน (President)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">รหัสพนักงาน</label>
                    <input type="text" name="employee_code" id="editEmpCode" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-sky-500 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">เบอร์โทรศัพท์</label>
                    <input type="tel" name="phone" id="editEmpPhone" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">เงินเดือน (บาท)</label>
                    <input type="number" step="any" name="salary" id="editEmpSalary" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-sky-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">อีเมล</label>
                    <input type="email" name="email" id="editEmpEmail" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">สถานะการทำงาน</label>
                    <select name="status" id="editEmpStatus" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-sky-500">
                        <option value="active">ปกติ (Active)</option>
                        <option value="suspended">ระงับการใช้งาน (Suspended)</option>
                    </select>
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalEditEmployee')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-medium shadow-sm">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Change Password -->
<div id="modalChangePass" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="key" class="w-5 h-5 text-amber-500"></i>
                <span>เปลี่ยนรหัสผ่านพนักงาน</span>
            </h3>
            <button onclick="closeModal('modalChangePass')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="hr.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="id" id="passEmpId">
            
            <p class="text-slate-500">เปลี่ยนรหัสผ่านสำหรับ: <strong id="passEmpName" class="text-slate-800"></strong></p>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">รหัสผ่านใหม่ <span class="text-rose-500">*</span></label>
                <input type="text" name="new_password" required placeholder="กรอกรหัสผ่านใหม่" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500 font-mono">
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalChangePass')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold shadow-sm">บันทึกรหัสผ่านใหม่</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

function openEditModal(staff) {
    document.getElementById('editEmpId').value = staff.id;
    document.getElementById('editEmpName').value = staff.full_name;
    document.getElementById('editEmpRole').value = staff.role;
    document.getElementById('editEmpCode').value = staff.employee_code || '';
    document.getElementById('editEmpPhone').value = staff.phone || '';
    document.getElementById('editEmpSalary').value = staff.salary || 0;
    document.getElementById('editEmpEmail').value = staff.email || '';
    document.getElementById('editEmpStatus').value = staff.status || 'active';
    openModal('modalEditEmployee');
}

function openPassModal(id, name) {
    document.getElementById('passEmpId').value = id;
    document.getElementById('passEmpName').innerText = name;
    openModal('modalChangePass');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
