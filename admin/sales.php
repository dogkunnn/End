<?php
$pageTitle = 'ฝ่ายขายและการจัดการคำสั่งซื้อ (Sales & POS Suite)';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_department('sales');
$db = get_db();
$currentUser = current_user();

// Handle Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update Order Status
    if ($action === 'update_order_status') {
        $id             = (int)($_POST['id'] ?? 0);
        $order_status   = trim($_POST['order_status'] ?? 'pending');
        $payment_status = trim($_POST['payment_status'] ?? 'pending');
        $notes          = trim($_POST['notes'] ?? '');

        if ($id > 0) {
            $update = $db->prepare("UPDATE orders SET order_status = ?, payment_status = ?, notes = ? WHERE id = ?");
            $update->execute([$order_status, $payment_status, $notes, $id]);
            set_flash('success', 'ปรับปรุงสถานะคำสั่งซื้อ #' . $id . ' เรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/sales.php'));
        exit;
    }

    // 2. POS Counter Sale (เปิดบิลขายหน้าร้านด่วน + ส่วนลด/คูปอง)
    if ($action === 'pos_quick_sale') {
        $cust_name   = trim($_POST['customer_name'] ?? 'ลูกค้าหน้าร้าน (Walk-in)');
        $cust_phone  = trim($_POST['customer_phone'] ?? '08x-xxx-xxxx');
        $product_id  = (int)($_POST['product_id'] ?? 0);
        $qty         = max(1, (int)($_POST['quantity'] ?? 1));
        $pay_method  = trim($_POST['payment_method'] ?? 'Cash');
        $notes       = trim($_POST['notes'] ?? 'ขายหน้าร้าน POS โดยฝ่ายขาย');
        $manualType  = $_POST['bill_discount_type'] ?? 'none'; // none|percent|fixed
        $manualVal   = (float)($_POST['bill_discount_value'] ?? 0);
        $couponCode  = strtoupper(trim($_POST['coupon_code'] ?? ''));

        if ($product_id > 0) {
            $pStmt = $db->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
            $pStmt->execute([$product_id]);
            $product = $pStmt->fetch();

            if ($product && $product['stock_quantity'] >= $qty) {
                // Line price already reflects any per-product discount
                $pricing   = product_pricing($product);
                $unitPrice = $pricing['final'];
                $subtotal  = $unitPrice * $qty;

                // Bill-level discount: manual OR coupon (manual takes precedence if set)
                $billDiscount = 0.0;
                $appliedCoupon = null;
                if ($manualType === 'percent' && $manualVal > 0) {
                    $billDiscount = $subtotal * ($manualVal / 100);
                } elseif ($manualType === 'fixed' && $manualVal > 0) {
                    $billDiscount = $manualVal;
                } elseif ($couponCode !== '') {
                    $cStmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND active = 1 LIMIT 1");
                    $cStmt->execute([$couponCode]);
                    $coupon = $cStmt->fetch();
                    if ($coupon
                        && (empty($coupon['expires_at']) || $coupon['expires_at'] >= date('Y-m-d'))
                        && $subtotal >= (float)$coupon['min_order']) {
                        if ($coupon['discount_type'] === 'percent') {
                            $billDiscount = $subtotal * ((float)$coupon['discount_value'] / 100);
                        } else {
                            $billDiscount = (float)$coupon['discount_value'];
                        }
                        $appliedCoupon = $coupon['code'];
                    } else {
                        set_flash('danger', 'โค้ดส่วนลด "' . htmlspecialchars($couponCode) . '" ใช้ไม่ได้ (หมดอายุ, ยอดไม่ถึงขั้นต่ำ หรือไม่พบโค้ด)');
                        header("Location: " . base_url('admin/sales.php'));
                        exit;
                    }
                }
                $billDiscount = min($billDiscount, $subtotal);
                $total = max(0, $subtotal - $billDiscount);
                $orderCode = 'POS-' . date('Ymd') . '-' . rand(100, 999);
                $now = date('Y-m-d H:i:s');

                $insOrder = $db->prepare("INSERT INTO orders (order_code, customer_name, customer_phone, customer_email, shipping_address, subtotal_amount, discount_amount, coupon_code, total_amount, payment_method, payment_status, order_status, sales_rep_id, notes, created_at)
                                          VALUES (?, ?, ?, 'walkin@store.com', 'รับสินค้าหน้าร้าน สาขาหลัก', ?, ?, ?, ?, ?, 'paid', 'completed', ?, ?, ?)");
                $insOrder->execute([$orderCode, $cust_name, $cust_phone, $subtotal, $billDiscount, $appliedCoupon, $total, $pay_method, $currentUser['id'], $notes, $now]);
                $orderId = $db->lastInsertId();

                $insItem = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $insItem->execute([$orderId, $product['id'], $product['brand'] . ' ' . $product['model'], $unitPrice, $qty, $subtotal]);

                $deduct = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $deduct->execute([$qty, $product['id']]);

                $msg = 'เปิดบิลขายหน้าร้านสำเร็จ! รหัส [' . $orderCode . '] ยอดสุทธิ ' . format_baht($total);
                if ($billDiscount > 0) $msg .= ' (ส่วนลด ' . format_baht($billDiscount) . ')';
                set_flash('success', $msg);
            } else {
                set_flash('danger', 'สินค้าในคลังไม่เพียงพอสำหรับการขาย');
            }
        }
        header("Location: " . base_url('admin/sales.php'));
        exit;
    }

    // 3. Coupon: Create
    if ($action === 'add_coupon') {
        $code  = strtoupper(trim($_POST['code'] ?? ''));
        $desc  = trim($_POST['description'] ?? '');
        $dtype = in_array($_POST['discount_type'] ?? '', ['percent', 'fixed']) ? $_POST['discount_type'] : 'percent';
        $dval  = (float)($_POST['discount_value'] ?? 0);
        $minOrder = (float)($_POST['min_order'] ?? 0);
        $expires  = trim($_POST['expires_at'] ?? '');
        $expires  = $expires !== '' ? $expires : null;

        if ($code !== '' && $dval > 0) {
            try {
                $ins = $db->prepare("INSERT INTO coupons (code, description, discount_type, discount_value, min_order, active, expires_at) VALUES (?, ?, ?, ?, ?, 1, ?)");
                $ins->execute([$code, $desc, $dtype, $dval, $minOrder, $expires]);
                set_flash('success', 'สร้างโค้ดส่วนลด [' . htmlspecialchars($code) . '] เรียบร้อยแล้ว');
            } catch (PDOException $e) {
                set_flash('danger', 'โค้ดนี้มีอยู่แล้วในระบบ กรุณาใช้โค้ดอื่น');
            }
        } else {
            set_flash('danger', 'กรุณากรอกโค้ดและมูลค่าส่วนลดให้ถูกต้อง');
        }
        header("Location: " . base_url('admin/sales.php?tab=coupons'));
        exit;
    }

    // 4. Coupon: Toggle active
    if ($action === 'toggle_coupon') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE coupons SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
            set_flash('success', 'ปรับสถานะโค้ดส่วนลดเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/sales.php?tab=coupons'));
        exit;
    }

    // 5. Coupon: Delete
    if ($action === 'delete_coupon') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
            set_flash('success', 'ลบโค้ดส่วนลดเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/sales.php?tab=coupons'));
        exit;
    }
}

$tab    = $_GET['tab'] ?? 'orders';
$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';

// Fetch orders (with search by code / customer / phone / coupon)
$ordSql = "SELECT o.*, u.full_name as sales_name
           FROM orders o
           LEFT JOIN users u ON o.sales_rep_id = u.id ";
$conds = [];
$params = [];
if ($statusFilter !== 'all') {
    $conds[] = "o.order_status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $conds[] = "(o.order_code LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.coupon_code LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($conds) $ordSql .= " WHERE " . implode(' AND ', $conds);
$ordSql .= " ORDER BY o.id DESC";
$ostmt = $db->prepare($ordSql);
$ostmt->execute($params);
$orders = $ostmt->fetchAll();

// AJAX: return only orders list markup
if (isset($_GET['ajax'])) {
    require __DIR__ . '/_partials/orders_list.php';
    exit;
}

// Fetch laptops with stock for POS dropdown (include discount fields)
$availableLaptops = $db->query("SELECT id, brand, model, price, stock_quantity, discount_type, discount_value, item_condition FROM products WHERE stock_quantity > 0 ORDER BY brand ASC")->fetchAll();

// Fetch coupons
$coupons = $db->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll();

// Metrics
$totalSales = $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$paidCount  = $db->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$pendingCount = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();

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
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-pink-100 text-pink-800">
                        <i data-lucide="shopping-cart" class="w-3.5 h-3.5 inline mr-1"></i> แผนกฝ่ายขาย & POS (Sales Management)
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">จัดการคำสั่งซื้อโน้ตบุ๊ก & ออกบิลขายหน้าร้าน POS</h1>
                <p class="text-xs text-slate-500">
                    ตรวจสอบออเดอร์ลูกค้า อัปเดตเลขพัสดุจัดส่ง ติดตามยอดชำระเงิน และเปิดบิลขายหน้าร้านแบบเรียลไทม์
                </p>
            </div>

            <button onclick="openModal('modalPOS')" class="px-4 py-2.5 rounded-xl bg-pink-600 hover:bg-pink-500 text-white text-xs font-semibold shadow-sm flex items-center space-x-1.5 transition">
                <i data-lucide="receipt" class="w-4 h-4"></i>
                <span>เปิดบิลขายหน้าร้าน (POS)</span>
            </button>
        </div>

        <!-- 3 Sales KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>ยอดขายรวมทั้งหมด</span>
                    <span class="p-2 rounded-xl bg-emerald-50 text-emerald-600"><i data-lucide="banknote" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo format_baht($totalSales); ?></div>
                <div class="text-[11px] text-emerald-600 font-medium mt-1">คำสั่งซื้อชำระเงินแล้ว <?php echo $paidCount; ?> รายการ</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>คำสั่งซื้อทั้งหมด</span>
                    <span class="p-2 rounded-xl bg-blue-50 text-blue-600"><i data-lucide="shopping-bag" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo count($orders); ?> ออเดอร์</div>
                <div class="text-[11px] text-slate-400 mt-1">ทั้งออนไลน์และหน้าร้าน POS</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>รอจัดเตรียม / รอดำเนินการ</span>
                    <span class="p-2 rounded-xl bg-amber-50 text-amber-600"><i data-lucide="clock" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-amber-700 mt-2"><?php echo $pendingCount; ?> ออเดอร์</div>
                <div class="text-[11px] text-amber-700 font-medium mt-1">ต้องเตรียมจัดส่งให้ลูกค้า</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center space-x-2 border-b border-slate-200 mb-6">
            <a href="<?php echo base_url('admin/sales.php?tab=orders'); ?>" class="pb-3 px-4 text-xs font-bold border-b-2 transition flex items-center space-x-2 <?php echo $tab === 'orders' ? 'border-pink-600 text-pink-600' : 'border-transparent text-slate-500 hover:text-slate-900'; ?>">
                <i data-lucide="shopping-bag" class="w-4 h-4"></i><span>คำสั่งซื้อทั้งหมด (Orders)</span>
            </a>
            <a href="<?php echo base_url('admin/sales.php?tab=coupons'); ?>" class="pb-3 px-4 text-xs font-bold border-b-2 transition flex items-center space-x-2 <?php echo $tab === 'coupons' ? 'border-pink-600 text-pink-600' : 'border-transparent text-slate-500 hover:text-slate-900'; ?>">
                <i data-lucide="ticket-percent" class="w-4 h-4"></i><span>โค้ดส่วนลด (Coupons)</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-pink-100 text-pink-800"><?php echo count($coupons); ?></span>
            </a>
        </div>

        <?php if ($tab === 'coupons'): ?>
            <!-- Coupons management -->
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">โค้ดส่วนลดทั้งหมด (Discount Coupons)</h2>
                    <p class="text-xs text-slate-500">สร้างโค้ดส่วนลด กำหนดเงื่อนไขขั้นต่ำและวันหมดอายุ ใช้ได้ที่หน้า POS</p>
                </div>
                <button onclick="openModal('modalAddCoupon')" class="px-4 py-2.5 rounded-xl bg-pink-600 hover:bg-pink-500 text-white text-xs font-semibold shadow-sm flex items-center space-x-1.5 transition">
                    <i data-lucide="plus" class="w-4 h-4"></i><span>สร้างโค้ดใหม่</span>
                </button>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4 font-semibold">โค้ด / รายละเอียด</th>
                                <th class="py-3 px-4 font-semibold">ส่วนลด</th>
                                <th class="py-3 px-4 font-semibold">ยอดขั้นต่ำ</th>
                                <th class="py-3 px-4 font-semibold">หมดอายุ</th>
                                <th class="py-3 px-4 font-semibold text-center">สถานะ</th>
                                <th class="py-3 px-4 font-semibold text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php if (empty($coupons)): ?>
                                <tr><td colspan="6" class="text-center py-8 text-slate-400">ยังไม่มีโค้ดส่วนลด</td></tr>
                            <?php else: foreach ($coupons as $cp): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4">
                                        <div class="font-mono font-bold text-pink-700"><?php echo htmlspecialchars($cp['code']); ?></div>
                                        <div class="text-[11px] text-slate-500"><?php echo htmlspecialchars($cp['description'] ?? ''); ?></div>
                                    </td>
                                    <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                        <?php echo $cp['discount_type'] === 'percent' ? rtrim(rtrim(number_format($cp['discount_value'],2),'0'),'.') . '%' : format_baht($cp['discount_value']); ?>
                                    </td>
                                    <td class="py-3 px-4 text-slate-600"><?php echo (float)$cp['min_order'] > 0 ? format_baht($cp['min_order']) : '-'; ?></td>
                                    <td class="py-3 px-4 text-slate-600"><?php echo !empty($cp['expires_at']) ? format_date($cp['expires_at']) : 'ไม่จำกัด'; ?></td>
                                    <td class="py-3 px-4 text-center">
                                        <?php if ((int)$cp['active'] === 1): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">เปิดใช้งาน</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-500">ปิดอยู่</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="inline-flex items-center space-x-1">
                                            <form method="POST" action="sales.php" class="inline">
                                                <input type="hidden" name="action" value="toggle_coupon">
                                                <input type="hidden" name="id" value="<?php echo $cp['id']; ?>">
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium transition"><?php echo (int)$cp['active'] === 1 ? 'ปิด' : 'เปิด'; ?></button>
                                            </form>
                                            <form method="POST" action="sales.php" class="inline" onsubmit="return confirm('ยืนยันลบโค้ดส่วนลดนี้?');">
                                                <input type="hidden" name="action" value="delete_coupon">
                                                <input type="hidden" name="id" value="<?php echo $cp['id']; ?>">
                                                <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- Orders: search + filter + AJAX list -->
            <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs mb-6 space-y-3">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input type="text" id="orderSearch" value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="ค้นหาเลขออเดอร์, ชื่อลูกค้า, เบอร์โทร หรือโค้ดส่วนลด..."
                           class="w-full pl-9 pr-9 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-pink-500 focus:border-pink-500 text-xs">
                    <button type="button" id="orderSearchClear" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 text-xs" id="orderStatusFilters">
                    <span class="font-bold text-slate-500 mr-1">สถานะ:</span>
                    <button type="button" data-status="all" class="of px-2.5 py-1.5 rounded-lg font-medium transition <?php echo $statusFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">ทั้งหมด</button>
                    <button type="button" data-status="pending" class="of px-2.5 py-1.5 rounded-lg font-medium transition <?php echo $statusFilter === 'pending' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">รอดำเนินการ</button>
                    <button type="button" data-status="processing" class="of px-2.5 py-1.5 rounded-lg font-medium transition <?php echo $statusFilter === 'processing' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">กำลังจัดเตรียม</button>
                    <button type="button" data-status="shipped" class="of px-2.5 py-1.5 rounded-lg font-medium transition <?php echo $statusFilter === 'shipped' ? 'bg-purple-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">จัดส่งแล้ว</button>
                    <button type="button" data-status="completed" class="of px-2.5 py-1.5 rounded-lg font-medium transition <?php echo $statusFilter === 'completed' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">สำเร็จ</button>
                    <button type="button" data-status="cancelled" class="of px-2.5 py-1.5 rounded-lg font-medium transition <?php echo $statusFilter === 'cancelled' ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">ยกเลิก</button>
                </div>
            </div>

            <div id="ordersListContainer" class="relative transition-opacity duration-150">
                <?php require __DIR__ . '/_partials/orders_list.php'; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Modal 1: POS Quick Sale -->
<div id="modalPOS" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="receipt" class="w-5 h-5 text-pink-600"></i>
                <span>เปิดบิลขายหน้าร้าน POS (Direct Sale)</span>
            </h3>
            <button onclick="closeModal('modalPOS')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="sales.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="pos_quick_sale">
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ชื่อลูกค้า <span class="text-rose-500">*</span></label>
                    <input type="text" name="customer_name" required value="ลูกค้าหน้าร้าน (Walk-in)" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">เบอร์โทรศัพท์ลูกค้า</label>
                    <input type="tel" name="customer_phone" placeholder="08x-xxx-xxxx" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">เลือกโน้ตบุ๊กที่ต้องการซื้อ <span class="text-rose-500">*</span></label>
                <select name="product_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-300 focus:ring-pink-500 font-medium">
                    <?php foreach ($availableLaptops as $lap): ?>
                        <option value="<?php echo $lap['id']; ?>">
                            [<?php echo htmlspecialchars($lap['brand']); ?>] <?php echo htmlspecialchars($lap['model']); ?> - <?php echo format_baht($lap['price']); ?> (เหลือ <?php echo $lap['stock_quantity']; ?> เครื่อง)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">จำนวนที่ซื้อ</label>
                    <input type="number" name="quantity" value="1" min="1" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ช่องทางชำระเงิน</label>
                    <select name="payment_method" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500">
                        <option value="Cash">เงินสด (Cash)</option>
                        <option value="Transfer">โอนเงินธนาคาร (Bank Transfer)</option>
                        <option value="CreditCard">บัตรเครดิต / เดบิต (Card)</option>
                    </select>
                </div>
            </div>

            <!-- Discount section -->
            <div class="p-3 rounded-xl bg-pink-50/50 border border-pink-100 space-y-3">
                <div class="flex items-center space-x-1.5 text-pink-700 font-bold text-[11px]">
                    <i data-lucide="ticket-percent" class="w-4 h-4"></i><span>ส่วนลดท้ายบิล (เลือกอย่างใดอย่างหนึ่ง)</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">ลดเอง</label>
                        <select name="bill_discount_type" id="posDiscType" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500">
                            <option value="none">ไม่ลด</option>
                            <option value="percent">ลดเป็น %</option>
                            <option value="fixed">ลดเป็นบาท</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">มูลค่าส่วนลด</label>
                        <input type="number" step="any" min="0" name="bill_discount_value" id="posDiscValue" value="0" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500 font-mono">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">หรือใส่โค้ดส่วนลด (Coupon)</label>
                    <input type="text" name="coupon_code" id="posCoupon" placeholder="เช่น SAVE500, WELCOME10" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500 font-mono uppercase">
                    <p class="text-[10px] text-slate-400 mt-1">* ถ้ากรอก "ลดเอง" ระบบจะใช้ส่วนลดนั้นก่อนโค้ด</p>
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">หมายเหตุ / ของแถม</label>
                <input type="text" name="notes" placeholder="เช่น แถมกระเป๋าเป้, เมาส์ไร้สาย, ประกัน 2 ปี" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500">
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalPOS')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-pink-600 hover:bg-pink-500 text-white font-medium shadow-sm">บันทึกเปิดบิลและตัดสต็อก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit Order Status -->
<div id="modalEditOrder" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="edit-3" class="w-5 h-5 text-pink-600"></i>
                <span>อัปเดตสถานะคำสั่งซื้อ</span>
            </h3>
            <button onclick="closeModal('modalEditOrder')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="sales.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="update_order_status">
            <input type="hidden" name="id" id="editOrderId">

            <p class="text-slate-500">คำสั่งซื้อ: <strong id="editOrderCode" class="text-slate-900 font-mono"></strong></p>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">สถานะคำสั่งซื้อ</label>
                <select name="order_status" id="editOrderStatus" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500 font-medium">
                    <option value="pending">รอดำเนินการ (Pending)</option>
                    <option value="processing">กำลังจัดเตรียมเครื่อง (Processing)</option>
                    <option value="shipped">จัดส่งแล้ว (Shipped)</option>
                    <option value="completed">สำเร็จเรียบร้อย (Completed)</option>
                    <option value="cancelled">ยกเลิกคำสั่งซื้อ (Cancelled)</option>
                </select>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">สถานะการชำระเงิน</label>
                <select name="payment_status" id="editPaymentStatus" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500 font-medium">
                    <option value="pending">รอการชำระเงิน (Pending)</option>
                    <option value="paid">ชำระเงินเรียบร้อยแล้ว (Paid)</option>
                </select>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">หมายเหตุ / เลขติดตามพัสดุ (Tracking No.)</label>
                <textarea name="notes" id="editOrderNotes" rows="2" placeholder="เช่น จัดส่งผ่าน Flash Express เลข TH12345678" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500"></textarea>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalEditOrder')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-pink-600 hover:bg-pink-500 text-white font-medium shadow-sm">บันทึกสถานะ</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Add Coupon -->
<div id="modalAddCoupon" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="ticket-percent" class="w-5 h-5 text-pink-600"></i><span>สร้างโค้ดส่วนลดใหม่</span>
            </h3>
            <button onclick="closeModal('modalAddCoupon')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <form method="POST" action="sales.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="add_coupon">
            <div>
                <label class="block font-semibold text-slate-700 mb-1">โค้ดส่วนลด <span class="text-rose-500">*</span></label>
                <input type="text" name="code" required placeholder="เช่น SAVE500" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500 font-mono uppercase">
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">รายละเอียด</label>
                <input type="text" name="description" placeholder="เช่น ส่วนลดต้อนรับลูกค้าใหม่" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ประเภทส่วนลด</label>
                    <select name="discount_type" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500">
                        <option value="percent">เปอร์เซ็นต์ (%)</option>
                        <option value="fixed">จำนวนเงิน (บาท)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">มูลค่า <span class="text-rose-500">*</span></label>
                    <input type="number" step="any" min="0" name="discount_value" required value="10" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500 font-mono">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ยอดซื้อขั้นต่ำ (บาท)</label>
                    <input type="number" step="any" min="0" name="min_order" value="0" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">วันหมดอายุ (ไม่บังคับ)</label>
                    <input type="date" name="expires_at" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-pink-500">
                </div>
            </div>
            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalAddCoupon')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-pink-600 hover:bg-pink-500 text-white font-medium shadow-sm">สร้างโค้ด</button>
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

function openEditOrderModal(ord) {
    document.getElementById('editOrderId').value = ord.id;
    document.getElementById('editOrderCode').innerText = ord.order_code;
    document.getElementById('editOrderStatus').value = ord.order_status;
    document.getElementById('editPaymentStatus').value = ord.payment_status;
    document.getElementById('editOrderNotes').value = ord.notes || '';
    openModal('modalEditOrder');
}

// ---- Live AJAX order search/filter (no reload) ----
(function () {
    const container = document.getElementById('ordersListContainer');
    if (!container) return; // coupons tab
    let state = { status: <?php echo json_encode($statusFilter); ?>, q: <?php echo json_encode($search); ?> };
    const searchInput = document.getElementById('orderSearch');
    const clearBtn = document.getElementById('orderSearchClear');
    let timer = null, reqId = 0;
    const statusColors = {
        all: 'bg-slate-900 text-white', pending: 'bg-amber-500 text-white',
        processing: 'bg-blue-600 text-white', shipped: 'bg-purple-600 text-white',
        completed: 'bg-emerald-600 text-white', cancelled: 'bg-rose-600 text-white'
    };
    function load() {
        const params = new URLSearchParams({ ajax: '1', status: state.status, q: state.q });
        const myReq = ++reqId;
        container.style.opacity = '0.4';
        fetch('sales.php?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                if (myReq !== reqId) return;
                container.innerHTML = html;
                container.style.opacity = '1';
                if (window.lucide) lucide.createIcons();
            })
            .catch(() => { container.style.opacity = '1'; });
        const urlP = new URLSearchParams({ tab: 'orders', status: state.status });
        if (state.q) urlP.set('q', state.q);
        history.replaceState(null, '', 'sales.php?' + urlP.toString());
    }
    document.querySelectorAll('#orderStatusFilters .of').forEach(btn => {
        btn.addEventListener('click', () => {
            state.status = btn.getAttribute('data-status');
            document.querySelectorAll('#orderStatusFilters .of').forEach(b => {
                b.className = 'of px-2.5 py-1.5 rounded-lg font-medium transition ' +
                    (b === btn ? (statusColors[state.status] || 'bg-slate-900 text-white') : 'bg-slate-100 text-slate-700 hover:bg-slate-200');
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
