<?php
$pageTitle = 'แดชบอร์ดภาพรวมร้านค้า (Store Overview)';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_staff();
$db = get_db();
$user = current_user();

// Period Filter: today / week / month / all / custom (ยืดหยุ่นเลือกช่วงวันเองได้)
$period = $_GET['period'] ?? 'today';
if (!in_array($period, ['today', 'week', 'month', 'all', 'custom'])) {
    $period = 'today';
}

$startDate = null;
$endDate = null;
$periodTitle = 'วันนี้';
$customStart = $_GET['start'] ?? '';
$customEnd   = $_GET['end'] ?? '';

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
} elseif ($period === 'custom') {
    // Validate YYYY-MM-DD; fall back to today if malformed
    $sOk = preg_match('/^\d{4}-\d{2}-\d{2}$/', $customStart);
    $eOk = preg_match('/^\d{4}-\d{2}-\d{2}$/', $customEnd);
    if (!$sOk) $customStart = date('Y-m-d');
    if (!$eOk) $customEnd = date('Y-m-d');
    if ($customStart > $customEnd) { $tmp = $customStart; $customStart = $customEnd; $customEnd = $tmp; }
    $startDate = $customStart . ' 00:00:00';
    $endDate   = $customEnd . ' 23:59:59';
    $periodTitle = 'ช่วงที่เลือก: ' . date('d/m/Y', strtotime($customStart)) . ' - ' . date('d/m/Y', strtotime($customEnd));
} else {
    $periodTitle = 'ภาพรวมทั้งหมด (All-time)';
}

// --- KPI Calculations (period-scoped) ---
if ($startDate && $endDate) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid' AND created_at >= ? AND created_at <= ?");
    $stmt->execute([$startDate, $endDate]);
    $totalRevenue = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(total_cost), 0) FROM repair_tickets WHERE status IN ('ready_for_pickup', 'completed') AND created_at >= ? AND created_at <= ?");
    $stmt->execute([$startDate, $endDate]);
    $totalRepairRevenue = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at <= ?");
    $stmt->execute([$startDate, $endDate]);
    $totalOrdersCount = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status='paid' AND created_at >= ? AND created_at <= ?");
    $stmt->execute([$startDate, $endDate]);
    $paidRevenueScoped = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE payment_status='paid' AND created_at >= ? AND created_at <= ?");
    $stmt->execute([$startDate, $endDate]);
    $paidOrdersScoped = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(discount_amount),0) FROM orders WHERE created_at >= ? AND created_at <= ?");
    $stmt->execute([$startDate, $endDate]);
    $discountsGiven = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM repair_tickets WHERE created_at >= ? AND created_at <= ?");
    $stmt->execute([$startDate, $endDate]);
    $repairsScoped = (int)$stmt->fetchColumn();

    // Top-selling products in range
    $stmt = $db->prepare("SELECT oi.product_name, SUM(oi.quantity) as qty, SUM(oi.subtotal) as revenue
                          FROM order_items oi JOIN orders o ON oi.order_id = o.id
                          WHERE o.created_at >= ? AND o.created_at <= ?
                          GROUP BY oi.product_name ORDER BY qty DESC LIMIT 5");
    $stmt->execute([$startDate, $endDate]);
    $topProducts = $stmt->fetchAll();
} else {
    $totalRevenue = $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
    $totalRepairRevenue = $db->query("SELECT COALESCE(SUM(total_cost), 0) FROM repair_tickets WHERE status IN ('ready_for_pickup', 'completed')")->fetchColumn();
    $totalOrdersCount = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $paidRevenueScoped = $totalRevenue;
    $paidOrdersScoped = (int)$db->query("SELECT COUNT(*) FROM orders WHERE payment_status='paid'")->fetchColumn();
    $discountsGiven = $db->query("SELECT COALESCE(SUM(discount_amount),0) FROM orders")->fetchColumn();
    $repairsScoped = (int)$db->query("SELECT COUNT(*) FROM repair_tickets")->fetchColumn();
    $topProducts = $db->query("SELECT oi.product_name, SUM(oi.quantity) as qty, SUM(oi.subtotal) as revenue
                               FROM order_items oi GROUP BY oi.product_name ORDER BY qty DESC LIMIT 5")->fetchAll();
}
$combinedRevenue = (float)$totalRevenue + (float)$totalRepairRevenue;
$avgOrderValue = $paidOrdersScoped > 0 ? ((float)$paidRevenueScoped / $paidOrdersScoped) : 0;

$activeRepairsCount = $db->query("SELECT COUNT(*) FROM repair_tickets WHERE status NOT IN ('completed', 'cancelled')")->fetchColumn();
$lowStockLaptops = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 3")->fetchColumn();
$lowStockParts = $db->query("SELECT COUNT(*) FROM spare_parts WHERE stock_quantity <= min_threshold")->fetchColumn();
$totalEmployees = $db->query("SELECT COUNT(*) FROM users WHERE role != 'customer'")->fetchColumn();
$usedStockCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE item_condition = 'used' AND stock_quantity > 0")->fetchColumn();
$discountedCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE discount_type != 'none' AND discount_value > 0")->fetchColumn();

// Lists for detail panels
$lowStockList = $db->query("SELECT brand, model, stock_quantity FROM products WHERE stock_quantity <= 3 ORDER BY stock_quantity ASC LIMIT 6")->fetchAll();
$recentOrders = $db->query("SELECT * FROM orders ORDER BY id DESC LIMIT 5")->fetchAll();
$recentRepairs = $db->query("SELECT r.*, u.full_name as tech_name
                             FROM repair_tickets r
                             LEFT JOIN users u ON r.technician_id = u.id
                             ORDER BY r.id DESC LIMIT 5")->fetchAll();
$announcements = $db->query("SELECT * FROM system_announcements ORDER BY id DESC LIMIT 3")->fetchAll();

// AJAX: return just the metrics + detail section (no reload, no flicker)
if (isset($_GET['ajax'])) {
    require __DIR__ . '/_partials/dashboard_metrics.php';
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-[calc(100vh-140px)]">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Workspace Area -->
    <div class="flex-1 p-6 lg:p-8 bg-slate-50 overflow-y-auto">
        
        <!-- Welcome Banner -->
        <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-slate-200 gap-4 mb-8">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-bold text-sky-600 uppercase tracking-wider">ECOSYSTEM OVERVIEW</span>
                    <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-slate-200 text-slate-700">Driver: <?php echo $db_driver; ?></span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">แดชบอร์ดภาพรวมร้านค้า (Store Overview)</h1>
                <p class="text-xs text-slate-500">
                    ยินดีต้อนรับคุณ <strong class="text-slate-800"><?php echo htmlspecialchars($user['full_name']); ?></strong> (<?php echo get_role_title($user['role']); ?>)
                </p>
            </div>

            <!-- Quick Action Links (แสดงปุ่มเฉพาะตำแหน่งของตนเอง) -->
            <div class="flex items-center space-x-2">
                <?php if ($user['role'] === 'technician'): ?>
                    <a href="<?php echo base_url('admin/technician.php'); ?>" class="px-3.5 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-semibold flex items-center space-x-1.5 transition shadow-xs">
                        <i data-lucide="wrench" class="w-4 h-4"></i>
                        <span>ไปยังคิวงานช่าง</span>
                    </a>
                <?php elseif ($user['role'] === 'stock'): ?>
                    <a href="<?php echo base_url('admin/stock.php'); ?>" class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold flex items-center space-x-1.5 transition shadow-xs">
                        <i data-lucide="boxes" class="w-4 h-4"></i>
                        <span>จัดการคลังสต็อก</span>
                    </a>
                <?php elseif ($user['role'] === 'sales'): ?>
                    <a href="<?php echo base_url('admin/sales.php'); ?>" class="px-3.5 py-2 rounded-xl bg-pink-600 hover:bg-pink-700 text-white text-xs font-semibold flex items-center space-x-1.5 transition shadow-xs">
                        <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                        <span>ไปยังระบบฝ่ายขาย</span>
                    </a>
                <?php elseif ($user['role'] === 'production'): ?>
                    <a href="<?php echo base_url('admin/production.php'); ?>" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold flex items-center space-x-1.5 transition shadow-xs">
                        <i data-lucide="cpu" class="w-4 h-4"></i>
                        <span>ฝ่ายผลิตและ QC</span>
                    </a>
                <?php elseif ($user['role'] === 'hr'): ?>
                    <a href="<?php echo base_url('admin/hr.php'); ?>" class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold flex items-center space-x-1.5 transition shadow-xs">
                        <i data-lucide="users" class="w-4 h-4"></i>
                        <span>บุคลากร (HR)</span>
                    </a>
                <?php else: /* president or admin */ ?>
                    <a href="<?php echo base_url('admin/president.php'); ?>" class="px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold flex items-center space-x-1.5 transition shadow-xs">
                        <i data-lucide="crown" class="w-4 h-4"></i>
                        <span>ห้องทำงาน CEO</span>
                    </a>
                    <a href="<?php echo base_url('admin/hr.php'); ?>" class="px-3.5 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-800 text-xs font-semibold flex items-center space-x-1.5 transition shadow-xs">
                        <i data-lucide="users" class="w-4 h-4"></i>
                        <span>บุคลากร (HR)</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Period Filter Selector (ยืดหยุ่น + เลือกช่วงวันเอง, กดแล้วเปลี่ยนทันทีไม่รีหน้า) -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs mb-6 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center space-x-2">
                    <i data-lucide="calendar" class="w-4 h-4 text-sky-600"></i>
                    <span class="text-xs font-bold text-slate-700">ช่วงเวลาแสดงผล:</span>
                    <span id="dashPeriodTitle" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200"><?php echo $periodTitle; ?></span>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 text-xs" id="dashPeriodBtns">
                    <button type="button" data-period="today" class="dp px-3.5 py-1.5 rounded-xl font-medium transition <?php echo $period === 'today' ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?>">⚡ วันนี้</button>
                    <button type="button" data-period="week" class="dp px-3.5 py-1.5 rounded-xl font-medium transition <?php echo $period === 'week' ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?>">📅 สัปดาห์นี้</button>
                    <button type="button" data-period="month" class="dp px-3.5 py-1.5 rounded-xl font-medium transition <?php echo $period === 'month' ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?>">🗓️ เดือนนี้</button>
                    <button type="button" data-period="all" class="dp px-3.5 py-1.5 rounded-xl font-medium transition <?php echo $period === 'all' ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?>">📊 ทั้งหมด</button>
                </div>
            </div>
            <!-- Custom date range -->
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 text-xs border-t border-slate-100 pt-3">
                <span class="font-bold text-slate-500 flex items-center"><i data-lucide="calendar-range" class="w-3.5 h-3.5 mr-1 text-sky-600"></i>เลือกช่วงวันเอง:</span>
                <input type="date" id="dashStart" value="<?php echo htmlspecialchars($period === 'custom' ? $customStart : date('Y-m-d', strtotime('-7 days'))); ?>" class="px-2.5 py-1.5 rounded-lg border border-slate-300 focus:ring-sky-500">
                <span class="text-slate-400">ถึง</span>
                <input type="date" id="dashEnd" value="<?php echo htmlspecialchars($period === 'custom' ? $customEnd : date('Y-m-d')); ?>" class="px-2.5 py-1.5 rounded-lg border border-slate-300 focus:ring-sky-500">
                <button type="button" id="dashApplyCustom" class="px-3.5 py-1.5 rounded-xl font-semibold bg-slate-900 text-white hover:bg-slate-800 transition">แสดงผลช่วงนี้</button>
            </div>
        </div>

        <!-- Metrics + detail (AJAX target) -->
        <div id="dashMetrics" class="transition-opacity duration-150">
            <?php require __DIR__ . '/_partials/dashboard_metrics.php'; ?>
        </div>

        <!-- 6 Departmental Portal Fast Cards -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                    ประตูเข้าสู่ระบบ 6 แผนกการทำงาน (Department Workspaces)
                </h2>
                <span class="text-xs text-slate-400">ลูกค้าไม่สามารถมองเห็นส่วนนี้ได้</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- 1. President -->
                <a href="<?php echo base_url('admin/president.php'); ?>" 
                   class="p-5 rounded-2xl bg-white border border-slate-200 hover:border-purple-500 hover:shadow-md transition group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center group-hover:bg-purple-600 group-hover:text-white transition">
                            <i data-lucide="crown" class="w-5 h-5"></i>
                        </div>
                        <span class="font-mono text-xs font-bold text-purple-600 bg-purple-50 px-2 py-0.5 rounded">CEO999</span>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 group-hover:text-purple-600">ฝ่ายประธาน / ผู้บริหาร (CEO)</h3>
                    <p class="text-xs text-slate-500 mt-1">สรุปผลประกอบการ กำไร-ขาดทุน รายงานวิสัยทัศน์ และ KPI ของทุกฝ่าย</p>
                </a>

                <!-- 2. HR & Admin -->
                <a href="<?php echo base_url('admin/hr.php'); ?>" 
                   class="p-5 rounded-2xl bg-white border border-slate-200 hover:border-blue-500 hover:shadow-md transition group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                            <i data-lucide="users" class="w-5 h-5"></i>
                        </div>
                        <span class="font-mono text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded">HR777</span>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 group-hover:text-blue-600">ฝ่ายบุคคล & ระบบ (HR/Admin)</h3>
                    <p class="text-xs text-slate-500 mt-1">จัดการพนักงาน เพิ่ม/ลบ/แก้ไขรหัสพนักงาน และแก้ไขรหัสลับประจำแผนก</p>
                </a>

                <!-- 3. Production -->
                <a href="<?php echo base_url('admin/production.php'); ?>" 
                   class="p-5 rounded-2xl bg-white border border-slate-200 hover:border-emerald-500 hover:shadow-md transition group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition">
                            <i data-lucide="cpu" class="w-5 h-5"></i>
                        </div>
                        <span class="font-mono text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">PRD202</span>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 group-hover:text-emerald-600">ฝ่ายผลิต & QC เตรียมเครื่อง</h3>
                    <p class="text-xs text-slate-500 mt-1">งานประกอบ ติดตั้ง Windows/โปรแกรม อัปเกรด RAM/SSD และตรวจเช็ค 15 จุด</p>
                </a>

                <!-- 4. Stock -->
                <a href="<?php echo base_url('admin/stock.php'); ?>" 
                   class="p-5 rounded-2xl bg-white border border-slate-200 hover:border-amber-500 hover:shadow-md transition group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:bg-amber-600 group-hover:text-white transition">
                            <i data-lucide="boxes" class="w-5 h-5"></i>
                        </div>
                        <span class="font-mono text-xs font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded">STK303</span>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 group-hover:text-amber-600">ฝ่ายคลังสินค้า & สต็อกอะไหล่</h3>
                    <p class="text-xs text-slate-500 mt-1">จัดการจำนวนโน้ตบุ๊ก อะไหล่ชิ้นส่วนซ่อม บันทึกรับเข้าและเบิกออก</p>
                </a>

                <!-- 5. Sales -->
                <a href="<?php echo base_url('admin/sales.php'); ?>" 
                   class="p-5 rounded-2xl bg-white border border-slate-200 hover:border-pink-500 hover:shadow-md transition group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-pink-50 text-pink-600 flex items-center justify-center group-hover:bg-pink-600 group-hover:text-white transition">
                            <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                        </div>
                        <span class="font-mono text-xs font-bold text-pink-600 bg-pink-50 px-2 py-0.5 rounded">SLS404</span>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 group-hover:text-pink-600">ฝ่ายขายและลูกค้าสัมพันธ์ (Sales POS)</h3>
                    <p class="text-xs text-slate-500 mt-1">เปิดบิลขายหน้าร้าน จัดการออเดอร์ออนไลน์ ออกใบเสร็จ และประสานงานส่งมอบ</p>
                </a>

                <!-- 6. Technician -->
                <a href="<?php echo base_url('admin/technician.php'); ?>" 
                   class="p-5 rounded-2xl bg-white border border-slate-200 hover:border-cyan-500 hover:shadow-md transition group">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center group-hover:bg-cyan-600 group-hover:text-white transition">
                            <i data-lucide="wrench" class="w-5 h-5"></i>
                        </div>
                        <span class="font-mono text-xs font-bold text-cyan-600 bg-cyan-50 px-2 py-0.5 rounded">TEC505</span>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 group-hover:text-cyan-600">ฝ่ายช่างเทคนิค & ศูนย์ซ่อม</h3>
                    <p class="text-xs text-slate-500 mt-1">รับงานซ่อม วินิจฉัยอาการ เสนอราคา ประเมินค่าอะไหล่ และอัปเดตสถานะงาน</p>
                </a>
            </div>
        </div>

        <!-- 2 Side-by-Side Tables: Recent Orders & Recent Repairs -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Sales Orders -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center">
                        <i data-lucide="shopping-bag" class="w-4 h-4 mr-2 text-sky-600"></i>
                        คำสั่งซื้อโน้ตบุ๊กล่าสุด
                    </h3>
                    <a href="<?php echo base_url('admin/sales.php'); ?>" class="text-xs text-sky-600 hover:underline">
                        ดูทั้งหมด &rarr;
                    </a>
                </div>
                <div class="divide-y divide-slate-100 text-xs">
                    <?php foreach ($recentOrders as $ro): ?>
                        <div class="p-4 flex items-center justify-between">
                            <div>
                                <span class="font-mono font-bold text-sky-600"><?php echo htmlspecialchars($ro['order_code']); ?></span>
                                <div class="font-semibold text-slate-800 mt-0.5"><?php echo htmlspecialchars($ro['customer_name']); ?></div>
                                <div class="text-[11px] text-slate-400"><?php echo format_date($ro['created_at']); ?></div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono font-bold text-slate-900 mb-1"><?php echo format_baht($ro['total_amount']); ?></div>
                                <?php echo render_order_status_badge($ro['order_status']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Repairs -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center">
                        <i data-lucide="wrench" class="w-4 h-4 mr-2 text-cyan-600"></i>
                        งานซ่อมโน้ตบุ๊กล่าสุด
                    </h3>
                    <a href="<?php echo base_url('admin/technician.php'); ?>" class="text-xs text-cyan-600 hover:underline">
                        ดูทั้งหมด &rarr;
                    </a>
                </div>
                <div class="divide-y divide-slate-100 text-xs">
                    <?php foreach ($recentRepairs as $rr): ?>
                        <div class="p-4 flex items-center justify-between">
                            <div>
                                <span class="font-mono font-bold text-cyan-600"><?php echo htmlspecialchars($rr['ticket_code']); ?></span>
                                <div class="font-semibold text-slate-800 mt-0.5"><?php echo htmlspecialchars($rr['brand'] . ' ' . $rr['model']); ?></div>
                                <div class="text-[11px] text-slate-400">ช่าง: <?php echo htmlspecialchars($rr['tech_name'] ?? 'นายธีรภัทร'); ?></div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono font-bold text-slate-900 mb-1"><?php echo format_baht($rr['total_cost']); ?></div>
                                <?php echo render_repair_status_badge($rr['status']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// ---- Dashboard period switching via AJAX (no reload, no flicker) ----
(function () {
    const metrics = document.getElementById('dashMetrics');
    const title = document.getElementById('dashPeriodTitle');
    let reqId = 0;

    function highlight(period) {
        document.querySelectorAll('#dashPeriodBtns .dp').forEach(b => {
            const active = b.getAttribute('data-period') === period;
            b.className = 'dp px-3.5 py-1.5 rounded-xl font-medium transition ' +
                (active ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700');
        });
    }

    function load(params, urlParams) {
        const myReq = ++reqId;
        metrics.style.opacity = '0.4';
        fetch('index.php?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                if (myReq !== reqId) return;
                metrics.innerHTML = html;
                metrics.style.opacity = '1';
                if (window.lucide) lucide.createIcons();
            })
            .catch(() => { metrics.style.opacity = '1'; });
        history.replaceState(null, '', 'index.php?' + urlParams.toString());
    }

    document.querySelectorAll('#dashPeriodBtns .dp').forEach(btn => {
        btn.addEventListener('click', () => {
            const period = btn.getAttribute('data-period');
            highlight(period);
            load(new URLSearchParams({ ajax: '1', period }), new URLSearchParams({ period }));
        });
    });

    document.getElementById('dashApplyCustom').addEventListener('click', () => {
        const start = document.getElementById('dashStart').value;
        const end = document.getElementById('dashEnd').value;
        if (!start || !end) return;
        highlight('custom');
        load(new URLSearchParams({ ajax: '1', period: 'custom', start, end }),
             new URLSearchParams({ period: 'custom', start, end }));
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
