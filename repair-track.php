<?php
$pageTitle = 'ติดตามสถานะงานซ่อมโน้ตบุ๊ก (Repair Tracking)';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$query = trim($_GET['code'] ?? ($_GET['search'] ?? ''));
$ticket = null;
$tickets = [];

$db = get_db();

if (!empty($query)) {
    // Try exact ticket code or phone search
    $stmt = $db->prepare("SELECT r.*, u.full_name as tech_name 
                          FROM repair_tickets r 
                          LEFT JOIN users u ON r.technician_id = u.id 
                          WHERE UPPER(r.ticket_code) = UPPER(?) OR r.customer_phone = ? 
                          ORDER BY r.id DESC");
    $stmt->execute([$query, $query]);
    $tickets = $stmt->fetchAll();

    if (count($tickets) === 1) {
        $ticket = $tickets[0];
    }
}

// 7-step workflow mapping
$steps = [
    'received'          => ['step' => 1, 'label' => 'รับเครื่องเข้าศูนย์', 'icon' => 'inbox'],
    'checking'          => ['step' => 2, 'label' => 'ช่างตรวจเช็คอาการ', 'icon' => 'search'],
    'waiting_parts'     => ['step' => 3, 'label' => 'รออะไหล่/รออนุมัติ', 'icon' => 'clock'],
    'repairing'         => ['step' => 4, 'label' => 'กำลังดำเนินการซ่อม', 'icon' => 'wrench'],
    'testing_qc'        => ['step' => 5, 'label' => 'ทดสอบระบบ & QC', 'icon' => 'check-circle-2'],
    'ready_for_pickup'  => ['step' => 6, 'label' => 'พร้อมส่งมอบ/รับเครื่อง', 'icon' => 'package-check'],
    'completed'         => ['step' => 7, 'label' => 'ปิดงาน/รับเครื่องแล้ว', 'icon' => 'smile'],
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-900 text-white py-12 border-b border-slate-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">ติดตามสถานะงานซ่อมโน้ตบุ๊กแบบเรียลไทม์</h1>
        <p class="text-sm text-slate-300 mt-2 max-w-xl mx-auto">
            กรอกหมายเลขใบแจ้งซ่อม (เช่น REP-2026-001) หรือเบอร์โทรศัพท์ที่ใช้ลงทะเบียน
        </p>

        <!-- Search Form -->
        <form method="GET" action="repair-track.php" class="mt-6 max-w-md mx-auto flex items-center space-x-2">
            <div class="relative flex-1">
                <input type="text" name="code" value="<?php echo htmlspecialchars($query); ?>" required
                       placeholder="พิมพ์รหัสงานซ่อม เช่น REP-2026-001..." 
                       class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-800 border border-slate-700 text-white placeholder-slate-400 text-sm focus:ring-2 focus:ring-sky-500 font-mono">
                <i data-lucide="search" class="w-5 h-5 text-slate-400 absolute left-3 top-3.5"></i>
            </div>
            <button type="submit" class="px-5 py-3 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-bold text-sm shadow-md transition">
                ค้นหา
            </button>
        </form>

        <!-- Demo Quick Click Links -->
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2 text-xs text-slate-400">
            <span>ตัวอย่างรหัสงานซ่อมทดสอบ:</span>
            <a href="<?php echo base_url('repair-track.php?code=REP-2026-001'); ?>" class="font-mono text-sky-400 hover:underline bg-slate-800 px-2 py-0.5 rounded">REP-2026-001 (พร้อมส่งมอบ)</a>
            <a href="<?php echo base_url('repair-track.php?code=REP-2026-002'); ?>" class="font-mono text-sky-400 hover:underline bg-slate-800 px-2 py-0.5 rounded">REP-2026-002 (กำลังซ่อม)</a>
            <a href="<?php echo base_url('repair-track.php?code=REP-2026-003'); ?>" class="font-mono text-sky-400 hover:underline bg-slate-800 px-2 py-0.5 rounded">REP-2026-003 (กำลังตรวจ)</a>
        </div>
    </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <?php if (empty($query)): ?>
        <!-- Idle State -->
        <div class="text-center py-16 bg-white rounded-2xl border border-slate-200 p-8">
            <div class="w-14 h-14 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="search" class="w-7 h-7"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900">กรุณากรอกรหัสติดตามงานซ่อม</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                หากคุณส่งซ่อมที่หน้าร้าน สามารถดูรหัสได้จากใบรับเครื่อง หรือคลิกตัวอย่างรหัสทดสอบด้านบน
            </p>
        </div>

    <?php elseif (empty($tickets)): ?>
        <!-- Not Found -->
        <div class="text-center py-16 bg-white rounded-2xl border border-slate-200 p-8">
            <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="alert-circle" class="w-7 h-7"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900">ไม่พบข้อมูลงานซ่อมสำหรับ "<?php echo htmlspecialchars($query); ?>"</h3>
            <p class="text-xs text-slate-500 mt-1">
                โปรดตรวจสอบความถูกต้องของรหัสงานซ่อมหรือเบอร์โทรศัพท์อีกครั้ง
            </p>
        </div>

    <?php elseif (count($tickets) > 1 && !$ticket): ?>
        <!-- Multiple Results by Phone -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
            <h3 class="text-base font-bold text-slate-900 mb-4">พบประวัติการส่งซ่อมทั้งหมด <?php echo count($tickets); ?> รายการ</h3>
            <div class="divide-y divide-slate-100">
                <?php foreach ($tickets as $t): ?>
                    <div class="py-4 flex items-center justify-between">
                        <div>
                            <span class="font-mono text-sm font-bold text-sky-600"><?php echo htmlspecialchars($t['ticket_code']); ?></span>
                            <h4 class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($t['brand'] . ' ' . $t['model']); ?></h4>
                            <p class="text-xs text-slate-500">วันที่ส่งซ่อม: <?php echo format_date($t['created_at']); ?></p>
                        </div>
                        <div class="text-right space-y-2">
                            <div><?php echo render_repair_status_badge($t['status']); ?></div>
                            <a href="<?php echo base_url('repair-track.php?code=' . urlencode($t['ticket_code'])); ?>" class="inline-block text-xs font-semibold text-sky-600 hover:underline">
                                ดูรายละเอียดไทม์ไลน์ &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Single Ticket Timeline & Full Details -->
        <?php 
            $currStep = $steps[$ticket['status']]['step'] ?? 1;
        ?>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <!-- Header -->
            <div class="p-6 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-semibold text-slate-500">รหัสใบแจ้งซ่อม:</span>
                        <span class="text-base font-mono font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded border border-sky-200">
                            <?php echo htmlspecialchars($ticket['ticket_code']); ?>
                        </span>
                    </div>
                    <h2 class="text-xl font-bold text-slate-900 mt-1">
                        <?php echo htmlspecialchars($ticket['brand'] . ' ' . $ticket['model']); ?>
                    </h2>
                    <p class="text-xs text-slate-500">ผู้ส่งซ่อม: <?php echo htmlspecialchars($ticket['customer_name']); ?> | โทร: <?php echo htmlspecialchars($ticket['customer_phone']); ?></p>
                </div>

                <div class="text-left sm:text-right">
                    <span class="text-xs text-slate-500 block mb-1">สถานะปัจจุบัน</span>
                    <?php echo render_repair_status_badge($ticket['status']); ?>
                </div>
            </div>

            <!-- Visual Stepper Progress Bar -->
            <div class="p-6 sm:p-8 border-b border-slate-100">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-6">ความคืบหน้าการปฏิบัติงาน (Live Progress)</h3>
                
                <div class="relative">
                    <!-- Step line -->
                    <div class="hidden sm:block absolute top-5 left-8 right-8 h-1 bg-slate-200 -z-0">
                        <div class="h-full bg-sky-600 transition-all duration-500" 
                             style="width: <?php echo max(0, min(100, (($currStep - 1) / (count($steps) - 1)) * 100)); ?>%;"></div>
                    </div>

                    <!-- Steps indicators -->
                    <div class="grid grid-cols-2 sm:grid-cols-7 gap-4 relative z-10">
                        <?php foreach ($steps as $key => $s): ?>
                            <?php 
                                $isCompleted = $s['step'] <= $currStep;
                                $isCurrent   = $s['step'] === $currStep;
                            ?>
                            <div class="flex flex-col items-center text-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs transition duration-200 <?php 
                                    echo $isCurrent ? 'bg-sky-600 text-white ring-4 ring-sky-100 shadow-md' : 
                                        ($isCompleted ? 'bg-sky-500 text-white' : 'bg-slate-100 text-slate-400 border border-slate-200'); ?>">
                                    <i data-lucide="<?php echo $s['icon']; ?>" class="w-4 h-4"></i>
                                </div>
                                <span class="text-[11px] font-semibold mt-2 <?php echo $isCurrent ? 'text-sky-700 font-bold' : ($isCompleted ? 'text-slate-800' : 'text-slate-400'); ?>">
                                    <?php echo $s['label']; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Ticket Detailed Information -->
            <div class="p-6 sm:p-8 space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left: Symptoms & Details -->
                    <div class="space-y-4">
                        <div>
                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">อาการเสียที่ได้รับแจ้ง</h4>
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 leading-relaxed">
                                <?php echo nl2br(htmlspecialchars($ticket['issue_description'])); ?>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">ผลการตรวจเช็คโดยช่าง (Diagnosis)</h4>
                            <div class="p-3.5 rounded-xl bg-sky-50/50 border border-sky-100 text-xs text-slate-800 leading-relaxed">
                                <?php echo !empty($ticket['diagnosis']) ? nl2br(htmlspecialchars($ticket['diagnosis'])) : '<span class="text-slate-400 italic">อยู่ระหว่างขั้นตอนการตรวจเช็คและถอดตรวจสอบวงจร...</span>'; ?>
                            </div>
                        </div>

                        <?php if (!empty($ticket['technician_notes'])): ?>
                            <div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">บันทึกขั้นตอนการซ่อมล่าสุด</h4>
                                <div class="p-3.5 rounded-xl bg-emerald-50/50 border border-emerald-100 text-xs text-emerald-900 leading-relaxed">
                                    <?php echo nl2br(htmlspecialchars($ticket['technician_notes'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right: Cost Breakdown & Tech in charge -->
                    <div class="space-y-4">
                        <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50">
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3">สรุปค่าใช้จ่ายงานซ่อม</h4>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between text-slate-600">
                                    <span>ค่าแรงและบริการตรวจเช็ค:</span>
                                    <span class="font-mono"><?php echo format_baht($ticket['labor_fee']); ?></span>
                                </div>
                                <div class="flex justify-between text-slate-600">
                                    <span>ค่าอะไหล่และอุปกรณ์:</span>
                                    <span class="font-mono"><?php echo format_baht($ticket['parts_fee']); ?></span>
                                </div>
                                <div class="pt-2 border-t border-slate-200 flex justify-between font-bold text-sm text-slate-900">
                                    <span>ยอดรวมสุทธิ:</span>
                                    <span class="font-mono text-sky-600"><?php echo format_baht($ticket['total_cost']); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl border border-slate-200 bg-white flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full bg-cyan-100 text-cyan-700 flex items-center justify-center font-bold">
                                <i data-lucide="wrench" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <div class="text-[11px] text-slate-400">ช่างเทคนิคผู้รับผิดชอบ</div>
                                <div class="text-xs font-bold text-slate-900"><?php echo htmlspecialchars($ticket['tech_name'] ?? 'นายธีรภัทร ช่างฝีมือ (หัวหน้าช่างซ่อม)'); ?></div>
                                <div class="text-[10px] text-slate-500">โทรสอบถามช่าง: 02-999-8888 ต่อ 104</div>
                            </div>
                        </div>

                        <?php if ($ticket['status'] === 'ready_for_pickup'): ?>
                            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs flex items-center space-x-3">
                                <i data-lucide="bell-ring" class="w-6 h-6 flex-shrink-0 text-emerald-600"></i>
                                <div>
                                    <div class="font-bold">โน้ตบุ๊กของคุณซ่อมเสร็จและผ่าน QC แล้ว!</div>
                                    <div>สามารถเดินทางมารับเครื่องได้ที่ศูนย์บริการ TechNote Pro ทุกวัน 09:30 - 20:00 น.</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
