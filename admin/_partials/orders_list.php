<?php
// Partial: orders as desktop table + mobile cards. Expects $orders in scope.
if (!isset($orders)) { $orders = []; }
?>
<!-- Desktop table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden hidden md:block">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="py-3 px-4 font-semibold">เลขออเดอร์ / วันที่</th>
                    <th class="py-3 px-4 font-semibold">ข้อมูลลูกค้า</th>
                    <th class="py-3 px-4 font-semibold">ยอดชำระเงิน</th>
                    <th class="py-3 px-4 font-semibold">วิธีชำระ</th>
                    <th class="py-3 px-4 font-semibold">สถานะการชำระ</th>
                    <th class="py-3 px-4 font-semibold">สถานะคำสั่งซื้อ</th>
                    <th class="py-3 px-4 font-semibold text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" class="text-center py-8 text-slate-400">ไม่พบคำสั่งซื้อตามเงื่อนไข</td></tr>
                <?php else: foreach ($orders as $ord): ?>
                    <tr class="hover:bg-slate-50/80 transition">
                        <td class="py-3.5 px-4">
                            <div class="font-mono text-xs font-bold text-pink-700"><?php echo htmlspecialchars($ord['order_code']); ?></div>
                            <div class="text-[11px] text-slate-400 mt-0.5"><?php echo format_datetime($ord['created_at']); ?></div>
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="font-bold text-slate-900"><?php echo htmlspecialchars($ord['customer_name']); ?></div>
                            <div class="text-[11px] text-slate-500"><?php echo htmlspecialchars($ord['customer_phone']); ?></div>
                            <div class="text-[10px] text-slate-400 truncate max-w-xs"><?php echo htmlspecialchars($ord['shipping_address']); ?></div>
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="font-mono font-bold text-slate-900 text-sm"><?php echo format_baht($ord['total_amount']); ?></div>
                            <?php if (!empty($ord['discount_amount']) && (float)$ord['discount_amount'] > 0): ?>
                                <div class="text-[10px] text-emerald-600">ส่วนลด <?php echo format_baht($ord['discount_amount']); ?><?php echo !empty($ord['coupon_code']) ? ' (' . htmlspecialchars($ord['coupon_code']) . ')' : ''; ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] bg-slate-100 text-slate-700"><?php echo htmlspecialchars($ord['payment_method'] ?? 'Transfer'); ?></span>
                        </td>
                        <td class="py-3.5 px-4">
                            <?php if ($ord['payment_status'] === 'paid'): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800"><i data-lucide="check" class="w-3 h-3 mr-1"></i> ชำระแล้ว</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800"><i data-lucide="clock" class="w-3 h-3 mr-1"></i> รอชำระ</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-4"><?php echo render_order_status_badge($ord['order_status']); ?></td>
                        <td class="py-3.5 px-4 text-center">
                            <button onclick='openEditOrderModal(<?php echo json_encode($ord, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' class="px-2.5 py-1.5 rounded-lg bg-pink-50 hover:bg-pink-100 text-pink-800 text-xs font-medium border border-pink-200 transition inline-flex items-center space-x-1">
                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i><span>อัปเดตสถานะ</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Mobile cards -->
<div class="md:hidden space-y-3">
    <?php if (empty($orders)): ?>
        <div class="text-center py-8 text-slate-400 text-xs bg-white rounded-2xl border border-slate-200">ไม่พบคำสั่งซื้อตามเงื่อนไข</div>
    <?php else: foreach ($orders as $ord): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4">
            <div class="flex items-center justify-between mb-1">
                <span class="font-mono text-xs font-bold text-pink-700"><?php echo htmlspecialchars($ord['order_code']); ?></span>
                <?php echo render_order_status_badge($ord['order_status']); ?>
            </div>
            <div class="text-[11px] text-slate-400"><?php echo format_datetime($ord['created_at']); ?></div>
            <div class="text-sm font-bold text-slate-900 mt-1"><?php echo htmlspecialchars($ord['customer_name']); ?></div>
            <div class="text-[11px] text-slate-500"><?php echo htmlspecialchars($ord['customer_phone']); ?></div>
            <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100">
                <div>
                    <div class="font-mono font-bold text-slate-900 text-sm"><?php echo format_baht($ord['total_amount']); ?></div>
                    <?php if (!empty($ord['discount_amount']) && (float)$ord['discount_amount'] > 0): ?>
                        <div class="text-[10px] text-emerald-600">ส่วนลด <?php echo format_baht($ord['discount_amount']); ?></div>
                    <?php endif; ?>
                    <?php if ($ord['payment_status'] === 'paid'): ?>
                        <span class="text-[10px] text-emerald-700 font-semibold">ชำระแล้ว</span>
                    <?php else: ?>
                        <span class="text-[10px] text-amber-700 font-semibold">รอชำระ</span>
                    <?php endif; ?>
                </div>
                <button onclick='openEditOrderModal(<?php echo json_encode($ord, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' class="px-3 py-1.5 rounded-lg bg-pink-600 text-white text-xs font-medium inline-flex items-center space-x-1">
                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i><span>อัปเดต</span>
                </button>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
