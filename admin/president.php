<?php
$pageTitle = 'ทำเนียบประธานกรรมการบริหาร (CEO Executive Suite)';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_department('president');
$db = get_db();
$currentUser = current_user();

// Handle Corporate Announcements
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_announcement') {
        $title    = trim($_POST['title'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $priority = trim($_POST['priority'] ?? 'normal');

        if (!empty($title) && !empty($content)) {
            $now = date('Y-m-d H:i:s');
            $author = !empty($currentUser['full_name']) ? $currentUser['full_name'] : 'ฝ่ายบริหาร (CEO)';
            $stmt = $db->prepare("INSERT INTO system_announcements (title, message, priority, author_name, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $priority, $author, $now]);
            set_flash('success', 'เผยแพร่ประกาศผู้บริหารเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/president.php'));
        exit;
    }

    if ($action === 'delete_announcement') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $del = $db->prepare("DELETE FROM system_announcements WHERE id = ?");
            $del->execute([$id]);
            set_flash('success', 'ลบประกาศเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/president.php'));
        exit;
    }
}

// Period Filter (รายวัน / รายสัปดาห์ / รายเดือน / โดยรวมทั้งหมด) - ค่าเริ่มต้นคือ 'today'
$period = $_GET['period'] ?? 'today';
if (!in_array($period, ['today', 'week', 'month', 'all'])) {
    $period = 'today';
}

$startDate = null;
$endDate = null;
$periodTitle = 'วันนี้';

if ($period === 'today') {
    $periodTitle = 'ประจำวันนี้ (' . date('d/m/Y') . ')';
    $startDate = date('Y-m-d 00:00:00');
    $endDate   = date('Y-m-d 23:59:59');
} elseif ($period === 'week') {
    $periodTitle = 'ประจำสัปดาห์นี้';
    $startDate = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $endDate   = date('Y-m-d 23:59:59', strtotime('sunday this week'));
} elseif ($period === 'month') {
    $periodTitle = 'ประจำเดือนนี้ (' . date('m/Y') . ')';
    $startDate = date('Y-m-01 00:00:00');
    $endDate   = date('Y-m-t 23:59:59');
} else {
    $periodTitle = 'ภาพรวมทั้งหมด (All-time)';
}

// Executive KPI Metrics
if ($startDate && $endDate) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid' AND created_at >= ? AND created_at <= ?");
    $stmt->execute([$startDate, $endDate]);
    $totalSalesRev = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(total_cost), 0) FROM repair_tickets WHERE status IN ('ready_for_pickup', 'completed') AND created_at >= ? AND created_at <= ?");
    $stmt->execute([$startDate, $endDate]);
    $totalRepairRev = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid' AND created_at >= ? AND created_at <= ?");
    $stmt->execute([$startDate, $endDate]);
    $paidOrdersCount = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at <= ?");
    $stmt->execute([$startDate, $endDate]);
    $allOrdersCount = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM repair_tickets WHERE status IN ('ready_for_pickup', 'completed') AND created_at >= ? AND created_at <= ?");
    $stmt->execute([$startDate, $endDate]);
    $doneRepairsCount = $stmt->fetchColumn();
} else {
    $totalSalesRev = $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
    $totalRepairRev = $db->query("SELECT COALESCE(SUM(total_cost), 0) FROM repair_tickets WHERE status IN ('ready_for_pickup', 'completed')")->fetchColumn();
    $paidOrdersCount = $db->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
    $allOrdersCount = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $doneRepairsCount = $db->query("SELECT COUNT(*) FROM repair_tickets WHERE status IN ('ready_for_pickup', 'completed')")->fetchColumn();
}
$combinedRevenue = (float)$totalSalesRev + (float)$totalRepairRev;

// Inventory metrics
$laptopStockValue = $db->query("SELECT COALESCE(SUM(price * stock_quantity), 0) FROM products")->fetchColumn();
$partsStockValue = $db->query("SELECT COALESCE(SUM(cost_price * stock_quantity), 0) FROM spare_parts")->fetchColumn();
$totalInventoryValue = (float)$laptopStockValue + (float)$partsStockValue;

// Active backlogs
$activeRepairsCount = $db->query("SELECT COUNT(*) FROM repair_tickets WHERE status NOT IN ('completed', 'cancelled')")->fetchColumn();
$doneProductionCount = $db->query("SELECT COUNT(*) FROM production_tasks WHERE status IN ('ready_to_deliver', 'qc_passed')")->fetchColumn();
$activeProductionCount = $db->query("SELECT COUNT(*) FROM production_tasks WHERE status NOT IN ('ready_to_deliver', 'qc_passed')")->fetchColumn();

// Announcements
$announcements = $db->query("SELECT * FROM system_announcements ORDER BY id DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-[calc(100vh-140px)]">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Workspace Area -->
    <div class="flex-1 p-6 lg:p-8 bg-slate-50 overflow-y-auto">
        
        <!-- Welcome Executive Banner -->
        <div class="bg-gradient-to-r from-slate-900 via-purple-950 to-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-lg border border-purple-900/40 mb-8 relative overflow-hidden">
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-purple-500/20 border border-purple-400/30 text-purple-300 text-xs font-semibold mb-3">
                        <i data-lucide="crown" class="w-3.5 h-3.5 text-amber-400"></i>
                        <span>EXECUTIVE SUITE | ทำเนียบผู้บริหารสูงสุด</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">รายงานภาพรวมธุรกิจ (TechNote Pro Ecosystem)</h1>
                    <p class="text-slate-300 text-xs sm:text-sm mt-1 max-w-2xl leading-relaxed">
                        ยินดีต้อนรับท่านประธาน <strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong> สรุปผลประกอบการฝ่ายขาย งานซ่อมบำรุง คลังสินค้า และประสิทธิภาพบุคลากร
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button onclick="openModal('modalAnnouncement')" class="px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold shadow-md flex items-center space-x-2 transition">
                        <i data-lucide="megaphone" class="w-4 h-4"></i>
                        <span>ออกประกาศผู้บริหาร</span>
                    </button>
                    <a href="<?php echo base_url('admin/hr.php'); ?>" class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold border border-white/20 flex items-center space-x-2 transition">
                        <i data-lucide="shield" class="w-4 h-4"></i>
                        <span>ตรวจสิทธิ์ HR</span>
                    </a>
                </div>
            </div>
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-purple-600/10 rounded-full blur-3xl pointer-events-none"></div>
        </div>

        <!-- Period Filter Selector (เลือกดู: วันนี้ / สัปดาห์นี้ / เดือนนี้ / ภาพรวมทั้งหมด) -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center space-x-2">
                <i data-lucide="calendar" class="w-4 h-4 text-purple-600"></i>
                <span class="text-xs font-bold text-slate-700">ช่วงเวลาแสดงผล:</span>
                <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200"><?php echo $periodTitle; ?></span>
            </div>
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <a href="<?php echo base_url('admin/president.php?period=today'); ?>" 
                   class="px-3.5 py-1.5 rounded-xl font-medium transition <?php echo $period === 'today' ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?>">
                    ⚡ วันนี้ (Today)
                </a>
                <a href="<?php echo base_url('admin/president.php?period=week'); ?>" 
                   class="px-3.5 py-1.5 rounded-xl font-medium transition <?php echo $period === 'week' ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?>">
                    📅 สัปดาห์นี้
                </a>
                <a href="<?php echo base_url('admin/president.php?period=month'); ?>" 
                   class="px-3.5 py-1.5 rounded-xl font-medium transition <?php echo $period === 'month' ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?>">
                    🗓️ เดือนนี้
                </a>
                <a href="<?php echo base_url('admin/president.php?period=all'); ?>" 
                   class="px-3.5 py-1.5 rounded-xl font-medium transition <?php echo $period === 'all' ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?>">
                    📊 ภาพรวมทั้งหมด
                </a>
            </div>
        </div>

        <!-- 4 Major Executive KPIs -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <!-- 1. Total Gross Revenue -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>รายได้รวม (<?php echo $period === 'today' ? 'วันนี้' : ($period === 'week' ? 'สัปดาห์นี้' : ($period === 'month' ? 'เดือนนี้' : 'ทั้งหมด')); ?>)</span>
                    <span class="p-2 rounded-xl bg-emerald-50 text-emerald-600"><i data-lucide="trending-up" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2">
                    <?php echo format_baht($combinedRevenue); ?>
                </div>
                <div class="text-[11px] text-slate-400 mt-1">
                    ขาย <?php echo format_baht($totalSalesRev); ?> + ซ่อม <?php echo format_baht($totalRepairRev); ?>
                </div>
            </div>

            <!-- 2. Inventory Asset Value -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>มูลค่าสต็อกสินค้า & อะไหล่</span>
                    <span class="p-2 rounded-xl bg-purple-50 text-purple-600"><i data-lucide="package-check" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2">
                    <?php echo format_baht($totalInventoryValue); ?>
                </div>
                <div class="text-[11px] text-slate-400 mt-1">
                    โน้ตบุ๊ก <?php echo format_baht($laptopStockValue); ?> + ชิ้นส่วนอะไหล่
                </div>
            </div>

            <!-- 3. Completed Repairs -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>งานซ่อมบำรุงที่สำเร็จแล้ว</span>
                    <span class="p-2 rounded-xl bg-cyan-50 text-cyan-600"><i data-lucide="wrench" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2">
                    <?php echo $doneRepairsCount; ?> งาน
                </div>
                <div class="text-[11px] text-cyan-700 font-medium mt-1">
                    กำลังดำเนินการอยู่ <?php echo $activeRepairsCount; ?> เครื่อง
                </div>
            </div>

            <!-- 4. Paid Orders -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>ยอดขายคำสั่งซื้อสำเร็จ</span>
                    <span class="p-2 rounded-xl bg-pink-50 text-pink-600"><i data-lucide="shopping-bag" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2">
                    <?php echo $paidOrdersCount; ?> / <?php echo $allOrdersCount; ?> ออเดอร์
                </div>
                <div class="text-[11px] text-emerald-600 font-medium mt-1">
                    ชำระเงินแล้ว 100%
                </div>
            </div>
        </div>

        <!-- 6 Department Performance Quick Cards -->
        <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center space-x-2">
            <i data-lucide="layout-grid" class="w-5 h-5 text-purple-600"></i>
            <span>ผลการดำเนินงานแยกตาม 6 แผนกหลัก</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-8 text-xs">
            <!-- 1. HR -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="font-bold text-slate-900 flex items-center space-x-2">
                            <span class="text-blue-600 text-base">👥</span>
                            <span>ฝ่ายบุคคล (HR/Admin)</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] bg-blue-100 text-blue-800 font-medium">ปกติ</span>
                    </div>
                    <p class="text-slate-500 mb-3">บริหารงานบุคคล รหัสพนักงาน และรหัสลับความปลอดภัยประจำแผนก</p>
                </div>
                <a href="<?php echo base_url('admin/hr.php'); ?>" class="text-blue-600 font-semibold hover:underline flex items-center">
                    เข้าสู่หน้างาน HR &rarr;
                </a>
            </div>

            <!-- 2. Technician -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="font-bold text-slate-900 flex items-center space-x-2">
                            <span class="text-cyan-600 text-base">🔧</span>
                            <span>ฝ่ายช่างซ่อม (Technician)</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] bg-cyan-100 text-cyan-800 font-medium"><?php echo $activeRepairsCount; ?> งานในคิว</span>
                    </div>
                    <p class="text-slate-500 mb-3">งานซ่อม 7 ขั้นตอน ประเมินราคา เปลี่ยนอะไหล่ และทดสอบ QC เครื่องก่อนส่งมอบ</p>
                </div>
                <a href="<?php echo base_url('admin/technician.php'); ?>" class="text-cyan-600 font-semibold hover:underline flex items-center">
                    เข้าสู่หน้างานช่างซ่อม &rarr;
                </a>
            </div>

            <!-- 3. Sales -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="font-bold text-slate-900 flex items-center space-x-2">
                            <span class="text-pink-600 text-base">🛒</span>
                            <span>ฝ่ายขาย (Sales POS)</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] bg-emerald-100 text-emerald-800 font-medium"><?php echo format_baht($totalSalesRev); ?></span>
                    </div>
                    <p class="text-slate-500 mb-3">จัดการคำสั่งซื้อโน้ตบุ๊ก ออกบิลหน้าร้าน POS และติดตามการชำระเงิน</p>
                </div>
                <a href="<?php echo base_url('admin/sales.php'); ?>" class="text-pink-600 font-semibold hover:underline flex items-center">
                    เข้าสู่หน้างานฝ่ายขาย &rarr;
                </a>
            </div>

            <!-- 4. Stock -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="font-bold text-slate-900 flex items-center space-x-2">
                            <span class="text-amber-600 text-base">📦</span>
                            <span>ฝ่ายสต็อกและคลังสินค้า</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] bg-amber-100 text-amber-800 font-medium">มูลค่า <?php echo format_baht($totalInventoryValue); ?></span>
                    </div>
                    <p class="text-slate-500 mb-3">บริหารคลังโน้ตบุ๊กทุกยี่ห้อ อะไหล่สำรอง บันทึกรับเข้า-เบิกออก</p>
                </div>
                <a href="<?php echo base_url('admin/stock.php'); ?>" class="text-amber-600 font-semibold hover:underline flex items-center">
                    เข้าสู่หน้างานสต็อก &rarr;
                </a>
            </div>

            <!-- 5. Production -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="font-bold text-slate-900 flex items-center space-x-2">
                            <span class="text-emerald-600 text-base">⚙️</span>
                            <span>ฝ่ายผลิตและ QC</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] bg-emerald-100 text-emerald-800 font-medium"><?php echo $doneProductionCount; ?> พร้อมส่ง</span>
                    </div>
                    <p class="text-slate-500 mb-3">เตรียมเครื่อง ลง Windows แท้ อัปเกรด RAM/SSD และ Burn-in Test</p>
                </div>
                <a href="<?php echo base_url('admin/production.php'); ?>" class="text-emerald-600 font-semibold hover:underline flex items-center">
                    เข้าสู่หน้างานผลิต/QC &rarr;
                </a>
            </div>

            <!-- 6. Overview Hub -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="font-bold text-slate-900 flex items-center space-x-2">
                            <span class="text-purple-600 text-base">📊</span>
                            <span>แดชบอร์ดรวมหน้าร้าน</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] bg-purple-100 text-purple-800 font-medium">Overview</span>
                    </div>
                    <p class="text-slate-500 mb-3">สรุปข้อมูลเชิงสถิติของร้านค้า สำหรับพนักงานและผู้บริหารทุกฝ่าย</p>
                </div>
                <a href="<?php echo base_url('admin/index.php'); ?>" class="text-purple-600 font-semibold hover:underline flex items-center">
                    เข้าสู่แดชบอร์ดภาพรวม &rarr;
                </a>
            </div>
        </div>

        <!-- Corporate Announcements Board -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center font-bold">
                        <i data-lucide="megaphone" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">ประกาศข่าวสารองค์กรจากประธาน (Corporate Announcements)</h2>
                        <p class="text-xs text-slate-500">สื่อสารคำสั่งและนโยบายไปยังพนักงานทุกแผนกในระบบ</p>
                    </div>
                </div>
                <button onclick="openModal('modalAnnouncement')" class="px-3.5 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold flex items-center space-x-1.5 transition">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    <span>สร้างประกาศใหม่</span>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <?php if (empty($announcements)): ?>
                    <p class="text-center py-8 text-xs text-slate-400">ยังไม่มีประกาศจากผู้บริหาร</p>
                <?php else: ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase 
                                        <?php 
                                            switch($ann['priority']) {
                                                case 'urgent': echo 'bg-rose-100 text-rose-800'; break;
                                                case 'important': echo 'bg-amber-100 text-amber-800'; break;
                                                default: echo 'bg-sky-100 text-sky-800';
                                            }
                                        ?>">
                                        <?php echo htmlspecialchars($ann['priority'] ?? 'normal'); ?>
                                    </span>
                                    <h3 class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($ann['title']); ?></h3>
                                </div>
                                <p class="text-xs text-slate-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($ann['message'] ?? $ann['content'] ?? '')); ?></p>
                                <div class="text-[11px] text-slate-400 pt-1">
                                    ประกาศโดย: <?php echo htmlspecialchars($ann['author_name'] ?? 'ฝ่ายบริหาร'); ?> | วันที่: <?php echo format_datetime($ann['created_at']); ?>
                                </div>
                            </div>
                            <form method="POST" action="president.php" onsubmit="return confirm('ยืนยันลบประกาศนี้หรือไม่?');">
                                <input type="hidden" name="action" value="delete_announcement">
                                <input type="hidden" name="id" value="<?php echo $ann['id']; ?>">
                                <button type="submit" class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="ลบประกาศ">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Add Announcement -->
<div id="modalAnnouncement" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="megaphone" class="w-5 h-5 text-purple-600"></i>
                <span>สร้างประกาศผู้บริหารใหม่</span>
            </h3>
            <button onclick="closeModal('modalAnnouncement')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="president.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="add_announcement">
            
            <div>
                <label class="block font-semibold text-slate-700 mb-1">หัวข้อประกาศ <span class="text-rose-500">*</span></label>
                <input type="text" name="title" required placeholder="เช่น แจ้งโบนัสประจำไตรมาส, นโยบายงานซ่อมเร่งด่วน" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-purple-500">
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">ระดับความสำคัญ</label>
                <select name="priority" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-purple-500">
                    <option value="normal">ปกติ (Normal)</option>
                    <option value="important">สำคัญ (Important)</option>
                    <option value="urgent">ด่วนที่สุด (Urgent)</option>
                </select>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">เนื้อหาประกาศ <span class="text-rose-500">*</span></label>
                <textarea name="content" rows="4" required placeholder="ระบุรายละเอียดข้อความประกาศ..." class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-purple-500"></textarea>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalAnnouncement')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-medium shadow-sm">เผยแพร่ประกาศ</button>
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
