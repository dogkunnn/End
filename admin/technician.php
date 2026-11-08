<?php
$pageTitle = 'ฝ่ายช่างซ่อมบำรุงและบริการ (Technician Service Suite)';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_department('technician');
$db = get_db();
$currentUser = current_user();

// Handle Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Open New Repair Ticket
    if ($action === 'create_ticket') {
        $ticket_code = 'REP-' . date('Y') . '-' . str_pad(rand(10, 999), 3, '0', STR_PAD_LEFT);
        $cust_name   = trim($_POST['customer_name'] ?? '');
        $cust_phone  = trim($_POST['customer_phone'] ?? '');
        $cust_email  = trim($_POST['customer_email'] ?? '');
        $brand       = trim($_POST['brand'] ?? '');
        $model       = trim($_POST['model'] ?? '');
        $serial_no   = trim($_POST['serial_number'] ?? '');
        $issue       = trim($_POST['issue_description'] ?? '');
        $est_cost    = (float)($_POST['estimated_cost'] ?? 500);
        $tech_id     = (int)($_POST['technician_id'] ?? $currentUser['id']);

        if (!empty($cust_name) && !empty($brand) && !empty($issue)) {
            $now = date('Y-m-d H:i:s');
            $sql = "INSERT INTO repair_tickets (ticket_code, customer_name, customer_phone, customer_email, brand, model, serial_number, issue_description, technician_id, status, estimated_cost, labor_fee, parts_fee, total_cost, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'received', ?, 500, 0, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$ticket_code, $cust_name, $cust_phone, $cust_email, $brand, $model, $serial_no, $issue, $tech_id, $est_cost, $est_cost, $now]);
            set_flash('success', 'เปิดใบรับซ่อมใหม่รหัส [' . $ticket_code . '] สำเร็จ ลูกค้าสามารถใช้รหัสนี้ติดตามสถานะซ่อมได้ทันที');
        }
        header("Location: " . base_url('admin/technician.php'));
        exit;
    }

    // 2. Update Repair Status & Costs
    if ($action === 'update_repair') {
        $id          = (int)($_POST['id'] ?? 0);
        $status      = trim($_POST['status'] ?? 'received');
        $diagnosis   = trim($_POST['diagnosis'] ?? '');
        $labor_fee   = (float)($_POST['labor_fee'] ?? 0);
        $parts_fee   = (float)($_POST['parts_fee'] ?? 0);
        $tech_notes  = trim($_POST['technician_notes'] ?? '');
        $total_cost  = $labor_fee + $parts_fee;
        $compTime    = in_array($status, ['ready_for_pickup', 'completed']) ? date('Y-m-d H:i:s') : null;

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE repair_tickets SET status = ?, diagnosis = ?, labor_fee = ?, parts_fee = ?, total_cost = ?, technician_notes = ?, completed_at = ? WHERE id = ?");
            $stmt->execute([$status, $diagnosis, $labor_fee, $parts_fee, $total_cost, $tech_notes, $compTime, $id]);
            set_flash('success', 'อัปเดตสถานะงานซ่อมและค่าบริการเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/technician.php'));
        exit;
    }

    // 3. Delete Ticket
    if ($action === 'delete_ticket') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $del = $db->prepare("DELETE FROM repair_tickets WHERE id = ?");
            $del->execute([$id]);
            set_flash('success', 'ลบใบรับซ่อมเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/technician.php'));
        exit;
    }
}

// Filter, Search & Sorting
$statusFilter = $_GET['status'] ?? 'all';
$sortOrder    = $_GET['sort'] ?? 'oldest'; // Default 'oldest' = FIFO (เรียงอันเก่าขึ้นก่อนตามคิวงาน)
$search       = trim($_GET['q'] ?? '');

$querySql = "SELECT r.*, u.full_name as tech_name
             FROM repair_tickets r
             LEFT JOIN users u ON r.technician_id = u.id ";
$conds = [];
$params = [];
if ($statusFilter !== 'all') {
    $conds[] = "r.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    // ค้นหาด้วยรหัสใบรับซ่อม / Serial / ชื่อลูกค้า / รุ่นเครื่อง
    $conds[] = "(r.ticket_code LIKE ? OR r.serial_number LIKE ? OR r.customer_name LIKE ? OR r.customer_phone LIKE ? OR r.brand LIKE ? OR r.model LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($conds) {
    $querySql .= " WHERE " . implode(' AND ', $conds);
}
$querySql .= $sortOrder === 'newest' ? " ORDER BY r.id DESC" : " ORDER BY r.id ASC";
$stmt = $db->prepare($querySql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// AJAX: return only the list markup (no reload, no flicker)
if (isset($_GET['ajax'])) {
    require __DIR__ . '/_partials/repair_list.php';
    exit;
}

// Technicians list
$technicians = $db->query("SELECT id, full_name FROM users WHERE role IN ('technician', 'admin')")->fetchAll();

// KPI counts
$totalRepairs = $db->query("SELECT COUNT(*) FROM repair_tickets")->fetchColumn();
$activeRepairs = $db->query("SELECT COUNT(*) FROM repair_tickets WHERE status NOT IN ('completed', 'cancelled')")->fetchColumn();
$readyPickup = $db->query("SELECT COUNT(*) FROM repair_tickets WHERE status = 'ready_for_pickup'")->fetchColumn();

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
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cyan-100 text-cyan-800">
                        <i data-lucide="wrench" class="w-3.5 h-3.5 inline mr-1"></i> แผนกฝ่ายช่างซ่อม (Technician Service)
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">คิวงานซ่อมโน้ตบุ๊ก & อัปเดตสถานะ 7 ขั้นตอน</h1>
                <p class="text-xs text-slate-500">
                    รับเครื่องซ่อม ตรวจเช็คอาการ เสนอราคา เปลี่ยนชิ้นส่วนอะไหล่ บันทึกผล QC และออกใบรับเครื่อง
                </p>
            </div>

            <div class="flex items-center space-x-2">
                <a href="<?php echo base_url('repair-track.php'); ?>" target="_blank" class="px-3 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-medium transition flex items-center space-x-1.5">
                    <i data-lucide="external-link" class="w-4 h-4"></i>
                    <span>มุมมองลูกค้า (Track Page)</span>
                </a>
                <button onclick="openModal('modalNewTicket')" class="px-4 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-semibold shadow-sm flex items-center space-x-1.5 transition">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>เปิดใบรับซ่อมใหม่</span>
                </button>
            </div>
        </div>

        <!-- 3 Quick KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>งานซ่อมกำลังดำเนินการ</span>
                    <span class="p-2 rounded-xl bg-cyan-50 text-cyan-600"><i data-lucide="wrench" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-cyan-700 mt-2"><?php echo $activeRepairs; ?> งาน</div>
                <div class="text-[11px] text-slate-400 mt-1">กำลังตรวจเช็คหรือรออะไหล่</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>พร้อมส่งมอบให้ลูกค้า</span>
                    <span class="p-2 rounded-xl bg-emerald-50 text-emerald-600"><i data-lucide="check-circle-2" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-emerald-600 mt-2"><?php echo $readyPickup; ?> เครื่อง</div>
                <div class="text-[11px] text-emerald-600 font-medium mt-1">ทดสอบ QC ผ่าน รอรับเครื่อง</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>ประวัติงานซ่อมสะสม</span>
                    <span class="p-2 rounded-xl bg-slate-100 text-slate-600"><i data-lucide="archive" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo $totalRepairs; ?> รายการ</div>
                <div class="text-[11px] text-slate-400 mt-1">บันทึกในฐานข้อมูลทั้งหมด</div>
            </div>
        </div>

        <!-- Search + Filter & Sorting Bar -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs mb-6 space-y-3">
            <!-- Search box (ค้นหารหัสใบรับซ่อม / Serial / ลูกค้า / รุ่น) -->
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                <input type="text" id="repairSearch" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="ค้นหารหัสใบรับซ่อม, Serial Number, ชื่อลูกค้า, เบอร์โทร หรือรุ่นเครื่อง..."
                       class="w-full pl-9 pr-9 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 text-xs">
                <button type="button" id="repairSearchClear" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <!-- Filter by Status -->
                <div class="flex flex-wrap items-center gap-1.5 text-xs" id="repairStatusFilters">
                    <span class="font-bold text-slate-500 mr-1">สถานะ:</span>
                    <button type="button" data-status="all" class="rf px-2.5 py-1.5 rounded-lg font-medium transition <?php echo $statusFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">ทั้งหมด</button>
                    <button type="button" data-status="checking" class="rf px-2.5 py-1.5 rounded-lg font-medium transition <?php echo $statusFilter === 'checking' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">2. ตรวจเช็ค</button>
                    <button type="button" data-status="waiting_parts" class="rf px-2.5 py-1.5 rounded-lg font-medium transition <?php echo $statusFilter === 'waiting_parts' ? 'bg-orange-500 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">3. รออะไหล่</button>
                    <button type="button" data-status="repairing" class="rf px-2.5 py-1.5 rounded-lg font-medium transition <?php echo $statusFilter === 'repairing' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">4. กำลังซ่อม</button>
                    <button type="button" data-status="ready_for_pickup" class="rf px-2.5 py-1.5 rounded-lg font-medium transition <?php echo $statusFilter === 'ready_for_pickup' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">6. พร้อมส่งมอบ</button>
                    <button type="button" data-status="completed" class="rf px-2.5 py-1.5 rounded-lg font-medium transition <?php echo $statusFilter === 'completed' ? 'bg-slate-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">7. ปิดงานแล้ว</button>
                </div>

                <!-- Sorting Toggle -->
                <div class="flex items-center space-x-1.5 text-xs border-t md:border-t-0 pt-2 md:pt-0" id="repairSortToggle">
                    <span class="font-bold text-slate-500 mr-1 flex items-center">
                        <i data-lucide="arrow-up-down" class="w-3.5 h-3.5 mr-1 text-cyan-600"></i>ลำดับคิว:
                    </span>
                    <button type="button" data-sort="oldest" class="rs px-3 py-1.5 rounded-lg font-medium transition flex items-center space-x-1 <?php echo $sortOrder === 'oldest' ? 'bg-cyan-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?>" title="เรียงงานเก่าสุดขึ้นก่อน (FIFO)"><span>📋 เรียงตามคิวงาน (เก่าไปใหม่)</span></button>
                    <button type="button" data-sort="newest" class="rs px-3 py-1.5 rounded-lg font-medium transition flex items-center space-x-1 <?php echo $sortOrder === 'newest' ? 'bg-cyan-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?>"><span>⚡ ล่าสุดก่อน</span></button>
                </div>
            </div>
        </div>

        <!-- Repair Tickets List (AJAX target) -->
        <div id="repairListContainer" class="relative transition-opacity duration-150">
            <?php require __DIR__ . '/_partials/repair_list.php'; ?>
        </div>

    </div>
</div>

<!-- Modal 1: New Repair Ticket -->
<div id="modalNewTicket" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="file-plus" class="w-5 h-5 text-cyan-600"></i>
                <span>เปิดใบรับซ่อมโน้ตบุ๊กใหม่</span>
            </h3>
            <button onclick="closeModal('modalNewTicket')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="technician.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="create_ticket">
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ชื่อลูกค้า <span class="text-rose-500">*</span></label>
                    <input type="text" name="customer_name" required placeholder="เช่น นายวรพจน์ มิตรภาพ" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-cyan-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">เบอร์โทรศัพท์ <span class="text-rose-500">*</span></label>
                    <input type="tel" name="customer_phone" required placeholder="08x-xxx-xxxx" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-cyan-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ยี่ห้อ (Brand) <span class="text-rose-500">*</span></label>
                    <input type="text" name="brand" required placeholder="เช่น ASUS, Acer, Lenovo, Dell" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-cyan-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">รุ่นโน้ตบุ๊ก (Model) <span class="text-rose-500">*</span></label>
                    <input type="text" name="model" required placeholder="เช่น Nitro 5 AN515-55" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-cyan-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Serial Number</label>
                    <input type="text" name="serial_number" placeholder="เช่น SN-DL-883921" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-cyan-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ประเมินราคาเบื้องต้น (บาท)</label>
                    <input type="number" step="any" name="estimated_cost" value="500" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-cyan-500 font-mono">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">อาการเสียที่ลูกค้าแจ้ง <span class="text-rose-500">*</span></label>
                <textarea name="issue_description" rows="3" required placeholder="เช่น เปิดเครื่องไม่ติด พัดลมไม่หมุน, น้ำหกใส่แป้นพิมพ์, หน้าจอกระพริบ..." class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-cyan-500"></textarea>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">มอบหมายช่างผู้รับผิดชอบ</label>
                <select name="technician_id" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-cyan-500">
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?php echo $tech['id']; ?>"><?php echo htmlspecialchars($tech['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalNewTicket')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-medium shadow-sm">เปิดใบรับซ่อมทันที</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit Repair Status -->
<div id="modalEditRepair" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="wrench" class="w-5 h-5 text-cyan-600"></i>
                <span>อัปเดตผลการซ่อม & สถานะงาน</span>
            </h3>
            <button onclick="closeModal('modalEditRepair')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="technician.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="update_repair">
            <input type="hidden" name="id" id="editRepairId">

            <div class="bg-cyan-50/60 p-3.5 rounded-xl border border-cyan-100 flex items-center justify-between">
                <div>
                    <span class="text-[11px] text-cyan-700 font-semibold">รหัสใบรับซ่อม:</span>
                    <div id="editRepairCode" class="font-mono text-sm font-bold text-slate-900"></div>
                </div>
                <div class="text-right">
                    <span class="text-[11px] text-cyan-700 font-semibold">ลูกค้า:</span>
                    <div id="editRepairCustomer" class="font-bold text-slate-900"></div>
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">ขั้นตอนสถานะงานซ่อม 7 ขั้นตอนมาตรฐาน <span class="text-rose-500">*</span></label>
                <select name="status" id="editRepairStatus" required class="w-full px-3 py-2.5 rounded-xl border border-slate-300 focus:ring-cyan-500 font-medium">
                    <option value="received">1. รับเครื่องแล้ว (Received)</option>
                    <option value="checking">2. กำลังตรวจเช็คและวิเคราะห์อาการ (Checking)</option>
                    <option value="waiting_parts">3. รออะไหล่ / รอการอนุมัติราคาจากลูกค้า (Waiting Parts)</option>
                    <option value="repairing">4. กำลังซ่อมบำรุง / เปลี่ยนชิ้นส่วน (Repairing)</option>
                    <option value="testing_qc">5. ทดสอบ QC และ Stress Test (Testing QC)</option>
                    <option value="ready_for_pickup">6. พร้อมส่งมอบให้ลูกค้า (Ready for Pickup)</option>
                    <option value="completed">7. ปิดงาน / ลูกค้ารับเครื่องแล้ว (Completed)</option>
                    <option value="cancelled">ยกเลิกงานซ่อม (Cancelled)</option>
                </select>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">ผลการตรวจเช็คทางเทคนิค (Diagnosis)</label>
                <textarea name="diagnosis" id="editRepairDiag" rows="2" placeholder="เช่น ตรวจพบมอเตอร์พัดลมฝืด ซิลิโคนแห้ง..." class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-cyan-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ค่าบริการ / ค่าแรงช่าง (บาท)</label>
                    <input type="number" step="any" name="labor_fee" id="editLaborFee" oninput="recalcTotal()" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-cyan-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ค่าอะไหล่ที่ใช้ (บาท)</label>
                    <input type="number" step="any" name="parts_fee" id="editPartsFee" oninput="recalcTotal()" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-cyan-500 font-mono">
                </div>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between font-bold">
                <span class="text-slate-600">ยอดรวมค่าซ่อมสุทธิ:</span>
                <span id="editRepairTotal" class="text-sm font-mono text-cyan-800">฿0.00</span>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">รายละเอียดงานซ่อม & หมายเหตุส่งมอบ</label>
                <textarea name="technician_notes" id="editTechNotes" rows="2" placeholder="เช่น เปลี่ยนพัดลมแท้ใหม่ ทดสอบเล่นเกม 30 นาที อุณหภูมิสูงสุด 72C ประกันงานซ่อม 90 วัน" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-cyan-500"></textarea>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalEditRepair')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-medium shadow-sm">บันทึกผลการซ่อม</button>
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

function openEditRepairModal(t) {
    document.getElementById('editRepairId').value = t.id;
    document.getElementById('editRepairCode').innerText = t.ticket_code;
    document.getElementById('editRepairCustomer').innerText = t.customer_name;
    document.getElementById('editRepairStatus').value = t.status;
    document.getElementById('editRepairDiag').value = t.diagnosis || '';
    document.getElementById('editLaborFee').value = t.labor_fee || 500;
    document.getElementById('editPartsFee').value = t.parts_fee || 0;
    document.getElementById('editTechNotes').value = t.technician_notes || '';
    recalcTotal();
    openModal('modalEditRepair');
}

function recalcTotal() {
    const labor = parseFloat(document.getElementById('editLaborFee').value) || 0;
    const parts = parseFloat(document.getElementById('editPartsFee').value) || 0;
    const total = labor + parts;
    document.getElementById('editRepairTotal').innerText = '฿' + total.toLocaleString('th-TH', {minimumFractionDigits: 2});
}

// ---- Live AJAX filtering/search (no reload, no flicker) ----
(function () {
    let state = {
        status: <?php echo json_encode($statusFilter); ?>,
        sort: <?php echo json_encode($sortOrder); ?>,
        q: <?php echo json_encode($search); ?>
    };
    const container = document.getElementById('repairListContainer');
    const searchInput = document.getElementById('repairSearch');
    const clearBtn = document.getElementById('repairSearchClear');
    let timer = null;
    let reqId = 0;

    function setActive(selector, attr, val, activeOn) {
        document.querySelectorAll(selector).forEach(btn => {
            const isActive = btn.getAttribute(attr) === val;
            // reset to neutral then apply active class map
            btn.className = btn.className.replace(/bg-\S+|text-white|shadow-xs/g, '').trim();
            if (isActive) {
                btn.classList.add(...activeOn(val).split(' '));
            } else {
                btn.classList.add('bg-slate-100', 'text-slate-700', 'hover:bg-slate-200');
            }
        });
    }

    const statusColors = {
        all: 'bg-slate-900 text-white', checking: 'bg-amber-500 text-white',
        waiting_parts: 'bg-orange-500 text-white', repairing: 'bg-indigo-600 text-white',
        ready_for_pickup: 'bg-emerald-600 text-white', completed: 'bg-slate-600 text-white'
    };

    function load() {
        const params = new URLSearchParams({ ajax: '1', status: state.status, sort: state.sort, q: state.q });
        const myReq = ++reqId;
        container.style.opacity = '0.4';
        fetch('technician.php?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                if (myReq !== reqId) return; // ignore stale responses
                container.innerHTML = html;
                container.style.opacity = '1';
                if (window.lucide) lucide.createIcons();
            })
            .catch(() => { container.style.opacity = '1'; });
        // keep URL in sync without navigation
        const urlP = new URLSearchParams({ status: state.status, sort: state.sort });
        if (state.q) urlP.set('q', state.q);
        history.replaceState(null, '', 'technician.php?' + urlP.toString());
    }

    document.querySelectorAll('#repairStatusFilters .rf').forEach(btn => {
        btn.addEventListener('click', () => {
            state.status = btn.getAttribute('data-status');
            document.querySelectorAll('#repairStatusFilters .rf').forEach(b => {
                b.className = 'rf px-2.5 py-1.5 rounded-lg font-medium transition ' +
                    (b === btn ? (statusColors[state.status] || 'bg-slate-900 text-white') : 'bg-slate-100 text-slate-700 hover:bg-slate-200');
            });
            load();
        });
    });

    document.querySelectorAll('#repairSortToggle .rs').forEach(btn => {
        btn.addEventListener('click', () => {
            state.sort = btn.getAttribute('data-sort');
            document.querySelectorAll('#repairSortToggle .rs').forEach(b => {
                b.className = 'rs px-3 py-1.5 rounded-lg font-medium transition flex items-center space-x-1 ' +
                    (b === btn ? 'bg-cyan-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700');
            });
            load();
        });
    });

    function onSearch() {
        state.q = searchInput.value.trim();
        clearBtn.classList.toggle('hidden', state.q === '');
        clearTimeout(timer);
        timer = setTimeout(load, 250);
    }
    searchInput.addEventListener('input', onSearch);
    clearBtn.addEventListener('click', () => { searchInput.value = ''; onSearch(); searchInput.focus(); });
    if (state.q) clearBtn.classList.remove('hidden');
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
