<?php
$pageTitle = 'แผงควบคุมบัญชีผู้ใช้ (Customer Dashboard)';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();
$user = current_user();
$db = get_db();

// Handle profile update
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $address  = trim($_POST['address'] ?? '');

    $update = $db->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
    $update->execute([$fullName, $phone, $email, $address, $user['id']]);

    $_SESSION['user_name']  = $fullName;
    $_SESSION['user_phone'] = $phone;
    $_SESSION['user_email'] = $email;
    $user = current_user();

    set_flash('success', 'บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว');
    header("Location: " . base_url('customer/dashboard.php'));
    exit;
}

// Fetch Customer's Orders
$stmtOrders = $db->prepare("SELECT * FROM orders WHERE user_id = ? OR customer_phone = ? ORDER BY id DESC");
$stmtOrders->execute([$user['id'], $user['phone']]);
$myOrders = $stmtOrders->fetchAll();

// Fetch Customer's Repairs
$stmtRepairs = $db->prepare("SELECT * FROM repair_tickets WHERE user_id = ? OR customer_phone = ? ORDER BY id DESC");
$stmtRepairs->execute([$user['id'], $user['phone']]);
$myRepairs = $stmtRepairs->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-slate-100 py-6 border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-slate-900">แผงควบคุมบัญชีของฉัน</h1>
        <p class="text-xs text-slate-500 mt-0.5">ยินดีต้อนรับคุณ <?php echo htmlspecialchars($user['full_name']); ?> | ดูประวัติคำสั่งซื้อและสถานะงานซ่อม</p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Profile Card -->
        <div class="lg:col-span-4 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-sky-500 text-white flex items-center justify-center font-bold text-lg">
                        <?php echo mb_substr($user['full_name'], 0, 1, 'UTF-8'); ?>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                        <span class="text-xs font-mono text-slate-400">@<?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                </div>

                <form method="POST" action="dashboard.php" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">ชื่อ-นามสกุล</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required
                               class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-sky-500">
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">เบอร์โทรศัพท์</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>"
                               class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-sky-500">
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">อีเมล</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                               class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-sky-500">
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">ที่อยู่จัดส่งสินค้า</label>
                        <textarea name="address" rows="3" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-sky-500"><?php echo htmlspecialchars($_SESSION['user_address'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold transition">
                        บันทึกการแก้ไขข้อมูล
                    </button>
                </form>
            </div>
        </div>

        <!-- Orders & Repairs Tabs -->
        <div class="lg:col-span-8 space-y-8">
            
            <!-- Orders History -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="p-5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="package" class="w-5 h-5 text-sky-600"></i>
                        <h3 class="text-sm font-bold text-slate-900">ประวัติการสั่งซื้อโน้ตบุ๊ก</h3>
                    </div>
                    <span class="text-xs text-slate-500"><?php echo count($myOrders); ?> ออเดอร์</span>
                </div>

                <?php if (empty($myOrders)): ?>
                    <div class="p-8 text-center text-xs text-slate-400">ยังไม่มีรายการสั่งซื้อ</div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100 text-xs">
                        <?php foreach ($myOrders as $ord): ?>
                            <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <span class="font-mono font-bold text-sky-600"><?php echo htmlspecialchars($ord['order_code']); ?></span>
                                    <div class="text-slate-500 mt-0.5"><?php echo format_datetime($ord['created_at']); ?></div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="font-mono font-bold text-slate-900"><?php echo format_baht($ord['total_amount']); ?></span>
                                        <?php if (!empty($ord['coupon_code'])): ?>
                                            <span class="text-[10px] font-mono font-bold bg-pink-100 text-pink-800 px-1.5 py-0.5 rounded">
                                                🎫 <?php echo htmlspecialchars($ord['coupon_code']); ?> (-<?php echo format_baht($ord['discount_amount'] ?? 0); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <?php echo render_order_status_badge($ord['order_status']); ?>
                                    <a href="<?php echo base_url('order-success.php?code=' . urlencode($ord['order_code'])); ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 font-semibold text-slate-700">
                                        ดูใบสั่งซื้อ
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Repairs History -->
            <div id="repairs" class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="p-5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="wrench" class="w-5 h-5 text-amber-600"></i>
                        <h3 class="text-sm font-bold text-slate-900">ประวัติการส่งซ่อมโน้ตบุ๊ก</h3>
                    </div>
                    <a href="<?php echo base_url('repair-service.php'); ?>" class="text-xs font-semibold text-sky-600 hover:underline">
                        + แจ้งซ่อมเครื่องใหม่
                    </a>
                </div>

                <?php if (empty($myRepairs)): ?>
                    <div class="p-8 text-center text-xs text-slate-400">ยังไม่มีประวัติการส่งซ่อม</div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100 text-xs">
                        <?php foreach ($myRepairs as $rep): ?>
                            <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <span class="font-mono font-bold text-sky-600"><?php echo htmlspecialchars($rep['ticket_code']); ?></span>
                                    <h4 class="font-bold text-slate-900"><?php echo htmlspecialchars($rep['brand'] . ' ' . $rep['model']); ?></h4>
                                    <p class="text-slate-500 line-clamp-1 mt-0.5"><?php echo htmlspecialchars($rep['issue_description']); ?></p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <?php echo render_repair_status_badge($rep['status']); ?>
                                    <a href="<?php echo base_url('repair-track.php?code=' . urlencode($rep['ticket_code'])); ?>" class="px-3 py-1.5 rounded-lg bg-sky-50 text-sky-700 hover:bg-sky-100 font-semibold">
                                        ติดตามสถานะสด
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
