<?php
// Partial: period-scoped KPI cards + detail panels. Swapped via AJAX.
$periodShort = $period === 'today' ? 'วันนี้' : ($period === 'week' ? 'สัปดาห์นี้' : ($period === 'month' ? 'เดือนนี้' : ($period === 'custom' ? 'ช่วงที่เลือก' : 'ทั้งหมด')));
?>
<!-- Row 1: 4 primary KPI cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500">รายรับรวม (<?php echo $periodShort; ?>)</span>
            <span class="p-2 rounded-xl bg-emerald-50 text-emerald-600"><i data-lucide="banknote" class="w-5 h-5"></i></span>
        </div>
        <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo format_baht($combinedRevenue); ?></div>
        <div class="text-[11px] text-slate-400 mt-1">ขาย <?php echo format_baht($totalRevenue); ?> + ซ่อม <?php echo format_baht($totalRepairRevenue); ?></div>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500">คำสั่งซื้อ (<?php echo $periodShort; ?>)</span>
            <span class="p-2 rounded-xl bg-sky-50 text-sky-600"><i data-lucide="shopping-bag" class="w-5 h-5"></i></span>
        </div>
        <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo $totalOrdersCount; ?> ออเดอร์</div>
        <div class="text-[11px] text-slate-400 mt-1">ชำระแล้ว <?php echo $paidOrdersScoped; ?> รายการ</div>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500">ยอดเฉลี่ยต่อบิล (AOV)</span>
            <span class="p-2 rounded-xl bg-indigo-50 text-indigo-600"><i data-lucide="receipt" class="w-5 h-5"></i></span>
        </div>
        <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo format_baht($avgOrderValue); ?></div>
        <div class="text-[11px] text-slate-400 mt-1">งานซ่อมในช่วงนี้ <?php echo $repairsScoped; ?> งาน</div>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-500">ส่วนลดที่ให้ไป (<?php echo $periodShort; ?>)</span>
            <span class="p-2 rounded-xl bg-rose-50 text-rose-600"><i data-lucide="ticket-percent" class="w-5 h-5"></i></span>
        </div>
        <div class="text-2xl font-bold font-mono text-rose-600 mt-2"><?php echo format_baht($discountsGiven); ?></div>
        <div class="text-[11px] text-slate-400 mt-1">สินค้าลดราคาอยู่ <?php echo $discountedCount; ?> รุ่น</div>
    </div>
</div>

<!-- Row 2: secondary stat chips -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center space-x-3">
        <span class="p-2 rounded-xl bg-amber-50 text-amber-600"><i data-lucide="wrench" class="w-4 h-4"></i></span>
        <div><div class="text-lg font-bold font-mono text-slate-900"><?php echo $activeRepairsCount; ?></div><div class="text-[10px] text-slate-500">งานซ่อมกำลังทำ</div></div>
    </div>
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center space-x-3">
        <span class="p-2 rounded-xl bg-rose-50 text-rose-600"><i data-lucide="alert-triangle" class="w-4 h-4"></i></span>
        <div><div class="text-lg font-bold font-mono text-rose-600"><?php echo (int)$lowStockLaptops + (int)$lowStockParts; ?></div><div class="text-[10px] text-slate-500">สินค้าใกล้หมด</div></div>
    </div>
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center space-x-3">
        <span class="p-2 rounded-xl bg-amber-50 text-amber-700"><i data-lucide="recycle" class="w-4 h-4"></i></span>
        <div><div class="text-lg font-bold font-mono text-slate-900"><?php echo $usedStockCount; ?></div><div class="text-[10px] text-slate-500">สินค้ามือสองพร้อมขาย</div></div>
    </div>
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center space-x-3">
        <span class="p-2 rounded-xl bg-blue-50 text-blue-600"><i data-lucide="users" class="w-4 h-4"></i></span>
        <div><div class="text-lg font-bold font-mono text-slate-900"><?php echo $totalEmployees; ?></div><div class="text-[10px] text-slate-500">พนักงานทั้งหมด</div></div>
    </div>
</div>

<!-- Row 3: Top products + low stock detail -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-2">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center">
            <i data-lucide="trending-up" class="w-4 h-4 mr-2 text-emerald-600"></i>
            <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">สินค้าขายดี (<?php echo $periodShort; ?>)</h3>
        </div>
        <div class="divide-y divide-slate-100 text-xs">
            <?php if (empty($topProducts)): ?>
                <div class="p-4 text-slate-400 text-center">ยังไม่มียอดขายในช่วงนี้</div>
            <?php else: $rank = 1; foreach ($topProducts as $tp): ?>
                <div class="p-3.5 flex items-center justify-between">
                    <div class="flex items-center space-x-2 min-w-0">
                        <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold flex items-center justify-center flex-shrink-0"><?php echo $rank++; ?></span>
                        <span class="font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($tp['product_name']); ?></span>
                    </div>
                    <div class="text-right flex-shrink-0 ml-2">
                        <div class="font-mono font-bold text-slate-900"><?php echo (int)$tp['qty']; ?> เครื่อง</div>
                        <div class="text-[10px] text-emerald-600"><?php echo format_baht($tp['revenue']); ?></div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center">
            <i data-lucide="package-x" class="w-4 h-4 mr-2 text-rose-600"></i>
            <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">โน้ตบุ๊กใกล้หมดสต็อก</h3>
        </div>
        <div class="divide-y divide-slate-100 text-xs">
            <?php if (empty($lowStockList)): ?>
                <div class="p-4 text-slate-400 text-center">สต็อกเพียงพอทุกรายการ</div>
            <?php else: foreach ($lowStockList as $ls): ?>
                <div class="p-3.5 flex items-center justify-between">
                    <span class="font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($ls['brand'] . ' ' . $ls['model']); ?></span>
                    <span class="font-mono font-bold px-2 py-0.5 rounded-full text-[11px] <?php echo $ls['stock_quantity'] <= 0 ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'; ?> flex-shrink-0 ml-2">เหลือ <?php echo (int)$ls['stock_quantity']; ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
