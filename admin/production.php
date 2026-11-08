<?php
$pageTitle = 'ฝ่ายผลิตและตรวจสอบคุณภาพ (Production & QC Suite)';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_department('production');
$db = get_db();
$currentUser = current_user();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create New Production Task
    if ($action === 'create_task') {
        $task_code   = 'PRD-' . date('Y') . '-' . rand(100, 999);
        $notebook    = trim($_POST['notebook_name'] ?? '');
        $task_type   = trim($_POST['task_type'] ?? 'New Machine Setup');
        $spec_detail = trim($_POST['spec_upgrade_details'] ?? '');
        $serial_no   = trim($_POST['serial_number'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');
        $assigned_to = (int)($_POST['assigned_to'] ?? $currentUser['id']);

        $default_qc = json_encode([
            'screen'        => 'pending',
            'keyboard'      => 'pending',
            'trackpad'      => 'pending',
            'wifi_bt'       => 'pending',
            'battery_charge'=> 'pending',
            'stress_test'   => 'pending'
        ]);

        if (!empty($notebook)) {
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare("INSERT INTO production_tasks (task_code, notebook_name, assigned_to, task_type, spec_upgrade_details, qc_checklist, status, serial_number, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)");
            $stmt->execute([$task_code, $notebook, $assigned_to, $task_type, $spec_detail, $default_qc, $serial_no, $notes, $now]);
            set_flash('success', 'เปิดงานเตรียมเครื่องใหม่รหัส [' . $task_code . '] สำเร็จ');
        }
        header("Location: " . base_url('admin/production.php'));
        exit;
    }

    // 2. Update QC Checklist and Status
    if ($action === 'update_qc') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? 'pending');
        $notes  = trim($_POST['notes'] ?? '');

        $qc = [
            'screen'         => $_POST['qc_screen'] ?? 'pending',
            'keyboard'       => $_POST['qc_keyboard'] ?? 'pending',
            'trackpad'       => $_POST['qc_trackpad'] ?? 'pending',
            'wifi_bt'        => $_POST['qc_wifi_bt'] ?? 'pending',
            'battery_charge' => $_POST['qc_battery'] ?? 'pending',
            'stress_test'    => $_POST['qc_stress'] ?? 'pending',
        ];
        $qc_json = json_encode($qc);

        if ($id > 0) {
            $compTime = ($status === 'ready_to_deliver' || $status === 'qc_passed') ? date('Y-m-d H:i:s') : null;
            $update = $db->prepare("UPDATE production_tasks SET status = ?, qc_checklist = ?, notes = ?, completed_at = ? WHERE id = ?");
            $update->execute([$status, $qc_json, $notes, $compTime, $id]);
            set_flash('success', 'บันทึกผลตรวจสอบ QC และปรับสถานะงานเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/production.php'));
        exit;
    }

    // 3. Delete Task
    if ($action === 'delete_task') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $del = $db->prepare("DELETE FROM production_tasks WHERE id = ?");
            $del->execute([$id]);
            set_flash('success', 'ลบงานเตรียมเครื่องเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/production.php'));
        exit;
    }
}

// Fetch all production tasks
$tasks = $db->query("SELECT p.*, u.full_name as tech_name 
                     FROM production_tasks p 
                     LEFT JOIN users u ON p.assigned_to = u.id 
                     ORDER BY p.id DESC")->fetchAll();

// Fetch staff for assignment
$technicians = $db->query("SELECT id, full_name, role FROM users WHERE role IN ('production', 'technician', 'admin')")->fetchAll();

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
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        <i data-lucide="cpu" class="w-3.5 h-3.5 inline mr-1"></i> แผนกผลิตและตรวจเช็คคุณภาพ (Production & QC)
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">คิวงานเตรียมเครื่องโน้ตบุ๊ก & ทดสอบ QC 100%</h1>
                <p class="text-xs text-slate-500">
                    เตรียมเครื่องมือ 1 ติดตั้ง Windows ลิขสิทธิ์ อัปเกรด RAM/SSD ปรับแต่งสเปกตามสั่ง และ Stress Test ก่อนส่งมอบ
                </p>
            </div>

            <button onclick="openModal('modalNewTask')" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shadow-sm flex items-center space-x-1.5 transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>เปิดงานเตรียมเครื่องใหม่</span>
            </button>
        </div>

        <!-- 3 Status Metric Cards -->
        <?php 
            $pendingCount = 0; $inProgCount = 0; $readyCount = 0;
            foreach ($tasks as $t) {
                if ($t['status'] === 'pending') $pendingCount++;
                elseif ($t['status'] === 'in_progress') $inProgCount++;
                elseif (in_array($t['status'], ['ready_to_deliver', 'qc_passed'])) $readyCount++;
            }
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>รอคิวประกอบ / ติดตั้ง</span>
                    <span class="p-2 rounded-xl bg-amber-50 text-amber-600"><i data-lucide="clock" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo $pendingCount; ?> เครื่อง</div>
                <div class="text-[11px] text-amber-600 mt-1">รอช่างรับคิวลงระบบ</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>กำลังติดตั้ง / ทดสอบ QC</span>
                    <span class="p-2 rounded-xl bg-blue-50 text-blue-600"><i data-lucide="activity" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo $inProgCount; ?> เครื่อง</div>
                <div class="text-[11px] text-blue-600 mt-1">กำลัง Stress Test & Benchmark</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>ผ่าน QC พร้อมส่งมอบ</span>
                    <span class="p-2 rounded-xl bg-emerald-50 text-emerald-600"><i data-lucide="check-circle" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo $readyCount; ?> เครื่อง</div>
                <div class="text-[11px] text-emerald-600 mt-1">ฝ่ายขายสามารถส่งมอบลูกค้าได้ทันที</div>
            </div>
        </div>

        <!-- Production Tasks Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">รายการงานฝ่ายผลิตและ QC ทั้งหมด</h2>
                    <p class="text-xs text-slate-500">คลิกที่ปุ่มเพื่อบันทึกผลตรวจสอบ QC Checklist หรือปรับสถานะงาน</p>
                </div>
                <span class="text-xs text-slate-400">ทั้งหมด <?php echo count($tasks); ?> งาน</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4 font-semibold">รหัสงาน / เครื่องโน้ตบุ๊ก</th>
                            <th class="py-3 px-4 font-semibold">ประเภทงาน</th>
                            <th class="py-3 px-4 font-semibold">สเปกที่ปรับแต่ง / ติดตั้ง</th>
                            <th class="py-3 px-4 font-semibold">ผู้รับผิดชอบ</th>
                            <th class="py-3 px-4 font-semibold">สถานะ QC</th>
                            <th class="py-3 px-4 font-semibold text-center">จัดการงาน</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php if (empty($tasks)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-8 text-slate-400">ยังไม่มีงานในคิวฝ่ายผลิต</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): 
                                $qc = json_decode($task['qc_checklist'] ?? '{}', true) ?: [];
                            ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4">
                                        <div class="font-mono text-xs font-bold text-emerald-700"><?php echo htmlspecialchars($task['task_code']); ?></div>
                                        <div class="font-bold text-slate-900 mt-0.5"><?php echo htmlspecialchars($task['notebook_name']); ?></div>
                                        <?php if (!empty($task['serial_number'])): ?>
                                            <div class="text-[11px] text-slate-400 font-mono">S/N: <?php echo htmlspecialchars($task['serial_number']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                                            <?php echo htmlspecialchars($task['task_type']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 max-w-xs">
                                        <div class="text-slate-600 line-clamp-2"><?php echo htmlspecialchars($task['spec_upgrade_details'] ?? '-'); ?></div>
                                    </td>
                                    <td class="py-3 px-4 text-slate-600">
                                        <?php echo htmlspecialchars($task['tech_name'] ?? 'ไม่ระบุ'); ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php 
                                            switch($task['status']) {
                                                case 'ready_to_deliver':
                                                    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800"><i data-lucide="check" class="w-3.5 h-3.5 mr-1"></i> พร้อมส่งมอบ</span>';
                                                    break;
                                                case 'stocked':
                                                    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800"><i data-lucide="package-check" class="w-3.5 h-3.5 mr-1"></i> รับเข้าสต็อกแล้ว</span>';
                                                    break;
                                                case 'qc_passed':
                                                    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-teal-100 text-teal-800">ผ่าน QC แล้ว</span>';
                                                    break;
                                                case 'in_progress':
                                                    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">กำลังดำเนินการ</span>';
                                                    break;
                                                default:
                                                    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">รอคิวเตรียม</span>';
                                                    break;
                                            }
                                        ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="inline-flex items-center space-x-1">
                                            <button onclick='openQCModal(<?php echo json_encode($task); ?>)' class="px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-medium border border-emerald-200 transition flex items-center space-x-1">
                                                <i data-lucide="clipboard-check" class="w-3.5 h-3.5"></i>
                                                <span>ตรวจ QC</span>
                                            </button>
                                            <form method="POST" action="production.php" class="inline" onsubmit="return confirm('ยืนยันลบงานนี้หรือไม่?');">
                                                <input type="hidden" name="action" value="delete_task">
                                                <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                                                <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="ลบงาน">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal 1: New Production Task -->
<div id="modalNewTask" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="cpu" class="w-5 h-5 text-emerald-600"></i>
                <span>เปิดงานเตรียมเครื่อง / สั่งประกอบใหม่</span>
            </h3>
            <button onclick="closeModal('modalNewTask')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="production.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="create_task">
            
            <div>
                <label class="block font-semibold text-slate-700 mb-1">ชื่อเครื่องและรุ่นโน้ตบุ๊ก <span class="text-rose-500">*</span></label>
                <input type="text" name="notebook_name" required placeholder="เช่น ASUS ROG Strix G16 (2025)" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-emerald-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ประเภทงาน</label>
                    <select name="task_type" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-emerald-500">
                        <option value="New Machine Setup">เตรียมเครื่องใหม่ (Setup OS & Drivers)</option>
                        <option value="Custom Upgrade">อัปเกรดตามสั่ง (RAM / SSD)</option>
                        <option value="Burn-in & QC">ทดสอบความเสถียร (Burn-in Stress)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Serial Number (ถ้ามี)</label>
                    <input type="text" name="serial_number" placeholder="เช่น SN-ROG-8821" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-emerald-500 font-mono">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">รายละเอียดสเปกที่ต้องติดตั้ง / อัปเกรด</label>
                <textarea name="spec_upgrade_details" rows="3" placeholder="เช่น ติดตั้ง Windows 11 Home, อัปเกรด RAM 32GB, อัปเดต BIOS ล่าสุด" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-emerald-500"></textarea>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">มอบหมายผู้รับผิดชอบ</label>
                <select name="assigned_to" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-emerald-500">
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?php echo $tech['id']; ?>">
                            <?php echo htmlspecialchars($tech['full_name']); ?> (<?php echo get_role_title($tech['role']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalNewTask')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-medium shadow-sm">เปิดงานทันที</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Update QC Checklist & Status -->
<div id="modalQC" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="clipboard-check" class="w-5 h-5 text-emerald-600"></i>
                <span>บันทึกผลตรวจสอบคุณภาพ QC Checklist</span>
            </h3>
            <button onclick="closeModal('modalQC')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="production.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="update_qc">
            <input type="hidden" name="id" id="qcTaskId">

            <p class="text-slate-500">เครื่อง: <strong id="qcNotebookName" class="text-slate-900"></strong></p>

            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2.5">
                <div class="font-bold text-slate-700 mb-2">Checklist 6 รายการสำคัญ:</div>
                
                <div class="flex items-center justify-between">
                    <span>1. จอภาพ (Dead Pixel & Brightness)</span>
                    <select name="qc_screen" id="qc_screen" class="px-2 py-1 rounded border border-slate-300">
                        <option value="pass">ผ่าน (Pass)</option>
                        <option value="fail">ไม่ผ่าน (Fail)</option>
                        <option value="pending">รอตรวจ</option>
                    </select>
                </div>

                <div class="flex items-center justify-between">
                    <span>2. แป้นพิมพ์ & Touchpad</span>
                    <select name="qc_keyboard" id="qc_keyboard" class="px-2 py-1 rounded border border-slate-300">
                        <option value="pass">ผ่าน (Pass)</option>
                        <option value="fail">ไม่ผ่าน (Fail)</option>
                        <option value="pending">รอตรวจ</option>
                    </select>
                </div>

                <div class="flex items-center justify-between">
                    <span>3. ทัชแพดและคลิกซ้าย-ขวา</span>
                    <select name="qc_trackpad" id="qc_trackpad" class="px-2 py-1 rounded border border-slate-300">
                        <option value="pass">ผ่าน (Pass)</option>
                        <option value="fail">ไม่ผ่าน (Fail)</option>
                        <option value="pending">รอตรวจ</option>
                    </select>
                </div>

                <div class="flex items-center justify-between">
                    <span>4. การเชื่อมต่อ Wi-Fi & Bluetooth</span>
                    <select name="qc_wifi_bt" id="qc_wifi_bt" class="px-2 py-1 rounded border border-slate-300">
                        <option value="pass">ผ่าน (Pass)</option>
                        <option value="fail">ไม่ผ่าน (Fail)</option>
                        <option value="pending">รอตรวจ</option>
                    </select>
                </div>

                <div class="flex items-center justify-between">
                    <span>5. แบตเตอรี่และการชาร์จไฟ</span>
                    <select name="qc_battery" id="qc_battery" class="px-2 py-1 rounded border border-slate-300">
                        <option value="pass">ผ่าน (Pass)</option>
                        <option value="fail">ไม่ผ่าน (Fail)</option>
                        <option value="pending">รอตรวจ</option>
                    </select>
                </div>

                <div class="flex items-center justify-between">
                    <span>6. Burn-in Stress Test & ความร้อน</span>
                    <select name="qc_stress" id="qc_stress" class="px-2 py-1 rounded border border-slate-300">
                        <option value="pass">ผ่าน (Pass)</option>
                        <option value="fail">ไม่ผ่าน (Fail)</option>
                        <option value="pending">รอตรวจ</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">ปรับสถานะงาน</label>
                <select name="status" id="qcStatus" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-emerald-500 font-medium">
                    <option value="pending">1. รอคิวเตรียมเครื่อง (Pending)</option>
                    <option value="in_progress">2. กำลังดำเนินการ / ตรวจเช็ค (In Progress)</option>
                    <option value="qc_passed">3. ผ่าน QC แล้ว (QC Passed)</option>
                    <option value="ready_to_deliver">4. พร้อมส่งมอบให้ฝ่ายขาย (Ready to Deliver)</option>
                    <option value="stocked">5. ฝ่ายคลังรับเข้าสต็อกแล้ว (Stocked)</option>
                </select>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">หมายเหตุช่าง QC</label>
                <textarea name="notes" id="qcNotes" rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-emerald-500" placeholder="ระบุอุณหภูมิ หรือหมายเหตุเพิ่มเติม..."></textarea>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalQC')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-medium shadow-sm">บันทึกผล QC</button>
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

function openQCModal(task) {
    document.getElementById('qcTaskId').value = task.id;
    document.getElementById('qcNotebookName').innerText = task.notebook_name;
    document.getElementById('qcStatus').value = task.status;
    document.getElementById('qcNotes').value = task.notes || '';

    let qc = {};
    try {
        qc = JSON.parse(task.qc_checklist || '{}');
    } catch(e) {}

    document.getElementById('qc_screen').value = qc.screen || 'pending';
    document.getElementById('qc_keyboard').value = qc.keyboard || 'pending';
    document.getElementById('qc_trackpad').value = qc.trackpad || 'pending';
    document.getElementById('qc_wifi_bt').value = qc.wifi_bt || 'pending';
    document.getElementById('qc_battery').value = qc.battery_charge || 'pending';
    document.getElementById('qc_stress').value = qc.stress_test || 'pending';

    openModal('modalQC');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
