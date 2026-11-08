<?php
$pageTitle = 'แบบฟอร์มแจ้งซ่อมโน้ตบุ๊กออนไลน์';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$user = current_user();
$error = '';

$customerName  = $user['full_name'] ?? '';
$customerPhone = $user['phone'] ?? '';
$customerEmail = $user['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName    = trim($_POST['customer_name'] ?? '');
    $customerPhone   = trim($_POST['customer_phone'] ?? '');
    $customerEmail   = trim($_POST['customer_email'] ?? '');
    $brand           = trim($_POST['brand'] ?? '');
    $model           = trim($_POST['model'] ?? '');
    $serialNumber    = trim($_POST['serial_number'] ?? '');
    $symptoms        = $_POST['symptoms'] ?? [];
    $moreDetails     = trim($_POST['details'] ?? '');

    if (empty($customerName) || empty($customerPhone) || empty($brand) || empty($model)) {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน (ชื่อผู้ส่งซ่อม, เบอร์โทรศัพท์, แบรนด์โน้ตบุ๊ก, รุ่นโน้ตบุ๊ก)';
    } else {
        $db = get_db();
        
        // Assemble issue description
        $symptomText = !empty($symptoms) ? implode(', ', $symptoms) : '';
        $fullDescription = $symptomText;
        if (!empty($moreDetails)) {
            $fullDescription .= ($fullDescription ? "\n\nรายละเอียดเพิ่มเติม: " : "") . $moreDetails;
        }

        // Generate unique ticket code: REP-2026-XXXX
        $year = date('Y');
        $random = str_pad(rand(10, 999), 3, '0', STR_PAD_LEFT);
        $ticketCode = 'REP-' . $year . '-' . $random;

        $userId = $user['id'] ?? null;
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare("INSERT INTO repair_tickets (
            ticket_code, user_id, customer_name, customer_phone, customer_email,
            brand, model, serial_number, issue_description, status, labor_fee, parts_fee, total_cost, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'received', 500.00, 0.00, 500.00, ?)");

        $stmt->execute([
            $ticketCode, $userId, $customerName, $customerPhone, $customerEmail,
            $brand, $model, $serialNumber, $fullDescription, $now
        ]);

        set_flash('success', "ส่งเรื่องแจ้งซ่อมเรียบร้อยแล้ว! รหัสติดตามงานซ่อมของคุณคือ: {$ticketCode}");
        header("Location: " . base_url('repair-track.php?code=' . urlencode($ticketCode)));
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-900 text-white py-12 border-b border-slate-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-sky-500/20 text-sky-300 text-xs font-semibold mb-3">
            <i data-lucide="wrench" class="w-4 h-4"></i>
            <span>ศูนย์บริการซ่อมมาตรฐานสากล</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">ส่งคำขอแจ้งซ่อมโน้ตบุ๊กออนไลน์</h1>
        <p class="text-sm text-slate-300 mt-2 max-w-xl mx-auto">
            กรอกรายละเอียดอาการเสียเบื้องต้น ช่างเทคนิคจะทำการเตรียมอะไหล่และติดต่อกลับเพื่อประเมินราคาซ่อมอย่างรวดเร็ว
        </p>
    </div>
</div>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center space-x-3">
            <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs">
        <form method="POST" action="repair-service.php" class="space-y-6">
            
            <!-- Section 1: Customer Info -->
            <div class="space-y-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center border-b border-slate-100 pb-2">
                    <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-700 text-xs font-bold inline-flex items-center justify-center mr-2">1</span>
                    ข้อมูลผู้ส่งซ่อม (Contact Information)
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            ชื่อ-นามสกุล <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="customer_name" value="<?php echo htmlspecialchars($customerName); ?>" required
                               placeholder="เช่น นายธนากร มั่นคง"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            เบอร์โทรศัพท์ที่ติดต่อได้ <span class="text-rose-500">*</span>
                        </label>
                        <input type="tel" name="customer_phone" value="<?php echo htmlspecialchars($customerPhone); ?>" required
                               placeholder="เช่น 089-123-4567"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        อีเมล (สำหรับรับใบแจ้งสถานะและใบเสร็จ)
                    </label>
                    <input type="email" name="customer_email" value="<?php echo htmlspecialchars($customerEmail); ?>"
                           placeholder="example@mail.com"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 text-sm">
                </div>
            </div>

            <!-- Section 2: Laptop Device Details -->
            <div class="space-y-4 pt-2">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center border-b border-slate-100 pb-2">
                    <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-700 text-xs font-bold inline-flex items-center justify-center mr-2">2</span>
                    ข้อมูลตัวเครื่องโน้ตบุ๊ก (Notebook Details)
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            แบรนด์ผู้ผลิต <span class="text-rose-500">*</span>
                        </label>
                        <select name="brand" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 text-sm">
                            <option value="">-- เลือกยี่ห้อโน้ตบุ๊ก --</option>
                            <option value="ASUS">ASUS (ROG / TUF / Zenbook / Vivobook)</option>
                            <option value="Acer">Acer (Nitro / Predator / Aspire / Swift)</option>
                            <option value="Lenovo">Lenovo (Legion / LOQ / ThinkPad / IdeaPad)</option>
                            <option value="Dell">Dell (Alienware / XPS / Inspiron / Latitude)</option>
                            <option value="HP">HP (Victus / OMEN / Pavilion / Envy)</option>
                            <option value="Apple">Apple (MacBook Pro / MacBook Air)</option>
                            <option value="MSI">MSI Gaming / Creator</option>
                            <option value="Other">ยี่ห้ออื่นๆ</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            รุ่นโน้ตบุ๊ก (Model) <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="model" required placeholder="เช่น Nitro 5 AN515-55 หรือ Legion 5"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        หมายเลขประจำเครื่อง (Serial Number / S/N)
                    </label>
                    <input type="text" name="serial_number" placeholder="ดูได้จากสติกเกอร์ใต้ท้องเครื่อง (ถ้ามี)"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 text-sm font-mono">
                </div>
            </div>

            <!-- Section 3: Symptoms & Issues -->
            <div class="space-y-4 pt-2">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center border-b border-slate-100 pb-2">
                    <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-700 text-xs font-bold inline-flex items-center justify-center mr-2">3</span>
                    อาการผิดปกติที่พบ (Symptoms Checklist)
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <label class="flex items-center p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" name="symptoms[]" value="เครื่องร้อนผิดปกติ พัดลมเสียงดัง" class="rounded text-sky-600 focus:ring-sky-500 w-4 h-4 mr-2.5">
                        <span>เครื่องร้อนผิดปกติ พัดลมเสียงดัง ดับเอง</span>
                    </label>
                    <label class="flex items-center p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" name="symptoms[]" value="เปิดไม่ติด ไฟไม่เข้า" class="rounded text-sky-600 focus:ring-sky-500 w-4 h-4 mr-2.5">
                        <span>เปิดไม่ติด ไฟไม่เข้าเครื่อง ชาร์จไม่เข้า</span>
                    </label>
                    <label class="flex items-center p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" name="symptoms[]" value="จอแตก จอเป็นเส้น หรือจอมืด" class="rounded text-sky-600 focus:ring-sky-500 w-4 h-4 mr-2.5">
                        <span>จอแตก จอเป็นเส้น หรือจอดำมืดสนิท</span>
                    </label>
                    <label class="flex items-center p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" name="symptoms[]" value="คีย์บอร์ดกดไม่ติด / ทำน้ำหกใส่" class="rounded text-sky-600 focus:ring-sky-500 w-4 h-4 mr-2.5">
                        <span>คีย์บอร์ดกดไม่ติด หรือทำน้ำหกใส่เครื่อง</span>
                    </label>
                    <label class="flex items-center p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" name="symptoms[]" value="ต้องการอัปเกรด RAM หรือ SSD" class="rounded text-sky-600 focus:ring-sky-500 w-4 h-4 mr-2.5">
                        <span>ต้องการอัปเกรด RAM หรือ M.2 SSD เพิ่มความเร็ว</span>
                    </label>
                    <label class="flex items-center p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" name="symptoms[]" value="ติดไวรัส จอฟ้า Windows รวน" class="rounded text-sky-600 focus:ring-sky-500 w-4 h-4 mr-2.5">
                        <span>ติดไวรัส บูตไม่ขึ้น จอฟ้า Windows เสีย</span>
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        คำอธิบายอาการเพิ่มเติม (ระบุอาการเสียอย่างละเอียด)
                    </label>
                    <textarea name="details" rows="3" placeholder="เช่น อาการเริ่มเป็นเมื่อไหร่ เกิดขึ้นตอนเล่นเกมหรือทำงาน มีเสียงอะไรผิดปกติหรือไม่..."
                              class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 text-sm"></textarea>
                </div>
            </div>

            <!-- Submit -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                <span class="text-xs text-slate-500">
                    <i data-lucide="shield-alert" class="w-3.5 h-3.5 inline mr-1 text-sky-600"></i>
                    ฟรีค่าเปิดเครื่องและค่าประเมินราคา
                </span>

                <button type="submit" class="px-7 py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm shadow-md transition flex items-center space-x-2">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    <span>ส่งคำขอแจ้งซ่อม</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
