<?php
$pageTitle = 'สั่งซื้อสำเร็จ (Order Confirmation)';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$code = trim($_GET['code'] ?? '');
$db = get_db();

$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ? LIMIT 1");
$stmt->execute([$code]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('danger', 'ไม่พบข้อมูลคำสั่งซื้อ');
    header("Location: " . base_url('index.php'));
    exit;
}

// Fetch Items
$stmtItems = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItems->execute([$order['id']]);
$items = $stmtItems->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Success Badge -->
    <div class="text-center mb-8 no-print">
        <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-4 shadow-sm">
            <i data-lucide="check" class="w-8 h-8"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-900">สั่งซื้อสินค้าสำเร็จเรียบร้อย!</h1>
        <p class="text-xs text-slate-500 mt-1">
            ขอบพระคุณสำหรับการสั่งซื้อ เจ้าหน้าที่ฝ่ายขายและฝ่ายผลิตกำลังจัดเตรียมสินค้าเพื่อส่งมอบให้ท่าน
        </p>
    </div>

    <!-- Printable Invoice Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-6">
        
        <!-- Header: Shop & Order Meta -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-6 gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-xs">
                        <i data-lucide="laptop" class="w-4 h-4"></i>
                    </div>
                    <span class="text-lg font-bold text-slate-900">TechNote Pro</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-1">ศูนย์จำหน่ายและซ่อมโน้ตบุ๊กครบวงจร</p>
            </div>

            <div class="text-left sm:text-right">
                <span class="text-xs text-slate-400 block">หมายเลขคำสั่งซื้อ (Order Code)</span>
                <span class="text-base font-mono font-bold text-sky-600"><?php echo htmlspecialchars($order['order_code']); ?></span>
                <div class="text-[11px] text-slate-500 mt-0.5"><?php echo format_datetime($order['created_at']); ?></div>
            </div>
        </div>

        <!-- Customer & Delivery Info -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
                <h3 class="font-bold text-slate-700 uppercase tracking-wider mb-2">ข้อมูลผู้สั่งซื้อ</h3>
                <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                <p class="text-slate-600 mt-0.5">โทร: <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                <?php if (!empty($order['customer_email'])): ?>
                    <p class="text-slate-600">อีเมล: <?php echo htmlspecialchars($order['customer_email']); ?></p>
                <?php endif; ?>
            </div>

            <div>
                <h3 class="font-bold text-slate-700 uppercase tracking-wider mb-2">สถานที่จัดส่งสินค้า</h3>
                <p class="text-slate-800 leading-relaxed"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                <div class="mt-2 flex items-center space-x-2">
                    <span class="text-slate-500">วิธีชำระเงิน:</span>
                    <span class="font-medium text-slate-800"><?php echo htmlspecialchars($order['payment_method']); ?></span>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">รายการสินค้า</th>
                        <th class="py-3 px-4 text-center">จำนวน</th>
                        <th class="py-3 px-4 text-right">ราคาต่อหน่วย</th>
                        <th class="py-3 px-4 text-right">ยอดรวม</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td class="py-3 px-4 font-medium text-slate-900"><?php echo htmlspecialchars($it['product_name']); ?></td>
                            <td class="py-3 px-4 text-center text-slate-600"><?php echo $it['quantity']; ?></td>
                            <td class="py-3 px-4 text-right font-mono text-slate-600"><?php echo format_baht($it['price']); ?></td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-slate-900"><?php echo format_baht($it['subtotal']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-50 font-semibold border-t border-slate-200 divide-y divide-slate-100">
                    <?php 
                    $subtotalCalc = (float)($order['subtotal_amount'] ?? 0);
                    if ($subtotalCalc <= 0) {
                        foreach ($items as $it) { $subtotalCalc += (float)$it['subtotal']; }
                    }
                    $discountCalc = (float)($order['discount_amount'] ?? 0);
                    ?>
                    <?php if ($discountCalc > 0): ?>
                        <tr>
                            <td colspan="3" class="py-2.5 px-4 text-right text-slate-500 font-normal">ราคารวมสินค้า:</td>
                            <td class="py-2.5 px-4 text-right font-mono text-slate-700 font-normal"><?php echo format_baht($subtotalCalc); ?></td>
                        </tr>
                        <tr class="bg-emerald-50/50 text-emerald-800">
                            <td colspan="3" class="py-2.5 px-4 text-right font-medium">
                                โค้ดส่วนลด (<?php echo htmlspecialchars($order['coupon_code'] ?? 'Promo'); ?>):
                            </td>
                            <td class="py-2.5 px-4 text-right font-mono font-bold text-emerald-700">-<?php echo format_baht($discountCalc); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="3" class="py-3 px-4 text-right text-slate-800 font-bold">ยอดชำระสุทธิ:</td>
                        <td class="py-3 px-4 text-right font-mono text-lg text-sky-600 font-bold"><?php echo format_baht($order['total_amount']); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Actions -->
        <div class="pt-4 border-t border-slate-100 flex items-center justify-between no-print">
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold flex items-center space-x-1.5 transition">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>พิมพ์ใบสั่งซื้อ / ใบเสร็จ</span>
            </button>

            <div class="flex space-x-2">
                <a href="<?php echo base_url('products.php'); ?>" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-xs transition">
                    เลือกซื้อสินค้าต่อ
                </a>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
