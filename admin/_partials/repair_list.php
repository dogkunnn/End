<?php
// Partial: renders repair tickets as a desktop table + mobile cards.
// Expects $tickets (array) in scope. Used by technician.php (full + AJAX).
if (!isset($tickets)) { $tickets = []; }
?>
<!-- Desktop Table (md and up) -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden hidden md:block">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="py-3 px-4 font-semibold">คิวงาน / รหัสใบรับซ่อม</th>
                    <th class="py-3 px-4 font-semibold">ข้อมูลลูกค้า</th>
                    <th class="py-3 px-4 font-semibold">เครื่อง / รุ่น / S/N</th>
                    <th class="py-3 px-4 font-semibold">อาการเสียที่แจ้ง</th>
                    <th class="py-3 px-4 font-semibold">สถานะ 7 ขั้นตอน</th>
                    <th class="py-3 px-4 font-semibold">ค่าใช้จ่ายรวม</th>
                    <th class="py-3 px-4 font-semibold text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                <?php if (empty($tickets)): ?>
                    <tr><td colspan="7" class="text-center py-8 text-slate-400">ไม่พบรายการงานซ่อมตามเงื่อนไข</td></tr>
                <?php else: $queueNumber = 1; foreach ($tickets as $ticket):
                    $isActive = !in_array($ticket['status'], ['completed', 'cancelled']); ?>
                    <tr class="hover:bg-slate-50/80 transition">
                        <td class="py-3.5 px-4">
                            <div class="flex items-center space-x-2">
                                <?php if ($isActive): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-cyan-100 text-cyan-800 font-mono">คิว #<?php echo $queueNumber++; ?></span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">ปิดงาน</span>
                                <?php endif; ?>
                                <span class="font-mono text-xs font-bold text-cyan-700"><?php echo htmlspecialchars($ticket['ticket_code']); ?></span>
                            </div>
                            <div class="text-[11px] text-slate-400 mt-1"><?php echo format_datetime($ticket['created_at']); ?></div>
                            <div class="text-[10px] text-slate-500">ช่าง: <?php echo htmlspecialchars($ticket['tech_name'] ?? 'ยังไม่ระบุ'); ?></div>
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="font-bold text-slate-900"><?php echo htmlspecialchars($ticket['customer_name']); ?></div>
                            <div class="text-[11px] text-slate-500"><?php echo htmlspecialchars($ticket['customer_phone']); ?></div>
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="font-bold text-slate-800">[<?php echo htmlspecialchars($ticket['brand']); ?>] <?php echo htmlspecialchars($ticket['model']); ?></div>
                            <?php if (!empty($ticket['serial_number'])): ?>
                                <div class="text-[11px] text-slate-400 font-mono">S/N: <?php echo htmlspecialchars($ticket['serial_number']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-4 max-w-xs">
                            <div class="text-slate-700 line-clamp-2"><?php echo htmlspecialchars($ticket['issue_description']); ?></div>
                            <?php if (!empty($ticket['diagnosis'])): ?>
                                <div class="text-[11px] text-cyan-700 mt-1 line-clamp-1">ตรวจพบ: <?php echo htmlspecialchars($ticket['diagnosis']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-4"><?php echo render_repair_status_badge($ticket['status']); ?></td>
                        <td class="py-3.5 px-4 font-mono font-bold text-slate-900 text-sm"><?php echo format_baht($ticket['total_cost']); ?></td>
                        <td class="py-3.5 px-4 text-center">
                            <div class="inline-flex items-center space-x-1">
                                <button onclick='openEditRepairModal(<?php echo json_encode($ticket, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' class="px-2.5 py-1.5 rounded-lg bg-cyan-50 hover:bg-cyan-100 text-cyan-800 text-xs font-medium border border-cyan-200 transition inline-flex items-center space-x-1">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i><span>อัปเดตงาน</span>
                                </button>
                                <form method="POST" action="technician.php" class="inline" onsubmit="return confirm('ยืนยันลบใบรับซ่อมนี้หรือไม่?');">
                                    <input type="hidden" name="action" value="delete_ticket">
                                    <input type="hidden" name="id" value="<?php echo $ticket['id']; ?>">
                                    <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="ลบงานซ่อม">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Mobile Cards (below md) -->
<div class="md:hidden space-y-3">
    <?php if (empty($tickets)): ?>
        <div class="text-center py-8 text-slate-400 text-xs bg-white rounded-2xl border border-slate-200">ไม่พบรายการงานซ่อมตามเงื่อนไข</div>
    <?php else: $queueNumber = 1; foreach ($tickets as $ticket):
        $isActive = !in_array($ticket['status'], ['completed', 'cancelled']); ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center space-x-2">
                    <?php if ($isActive): ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-cyan-100 text-cyan-800 font-mono">คิว #<?php echo $queueNumber++; ?></span>
                    <?php else: ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">ปิดงาน</span>
                    <?php endif; ?>
                    <span class="font-mono text-xs font-bold text-cyan-700"><?php echo htmlspecialchars($ticket['ticket_code']); ?></span>
                </div>
                <?php echo render_repair_status_badge($ticket['status']); ?>
            </div>
            <div class="text-sm font-bold text-slate-900">[<?php echo htmlspecialchars($ticket['brand']); ?>] <?php echo htmlspecialchars($ticket['model']); ?></div>
            <?php if (!empty($ticket['serial_number'])): ?>
                <div class="text-[11px] text-slate-400 font-mono">S/N: <?php echo htmlspecialchars($ticket['serial_number']); ?></div>
            <?php endif; ?>
            <div class="mt-2 text-xs text-slate-600">
                <div><i data-lucide="user" class="w-3 h-3 inline text-slate-400"></i> <?php echo htmlspecialchars($ticket['customer_name']); ?> · <?php echo htmlspecialchars($ticket['customer_phone']); ?></div>
                <div class="line-clamp-2 mt-1"><span class="text-slate-400">อาการ:</span> <?php echo htmlspecialchars($ticket['issue_description']); ?></div>
            </div>
            <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100">
                <span class="font-mono font-bold text-slate-900 text-sm"><?php echo format_baht($ticket['total_cost']); ?></span>
                <div class="flex items-center space-x-1">
                    <button onclick='openEditRepairModal(<?php echo json_encode($ticket, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' class="px-3 py-1.5 rounded-lg bg-cyan-600 text-white text-xs font-medium inline-flex items-center space-x-1">
                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i><span>อัปเดต</span>
                    </button>
                    <form method="POST" action="technician.php" class="inline" onsubmit="return confirm('ยืนยันลบใบรับซ่อมนี้หรือไม่?');">
                        <input type="hidden" name="action" value="delete_ticket">
                        <input type="hidden" name="id" value="<?php echo $ticket['id']; ?>">
                        <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
