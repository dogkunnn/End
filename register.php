<?php
$pageTitle = 'สมัครสมาชิกใหม่ (Register)';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

if (is_logged_in()) {
    header("Location: " . base_url('index.php'));
    exit;
}

$error = '';
$fullName = '';
$username = '';
$email    = '';
$phone    = '';
$address  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName        = trim($_POST['full_name'] ?? '');
    $username        = trim($_POST['username'] ?? '');
    $password        = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $address         = trim($_POST['address'] ?? '');

    // Validation
    if (empty($fullName) || empty($username) || empty($password)) {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน (ชื่อ-นามสกุล, ชื่อผู้ใช้, รหัสผ่าน)';
    } elseif ($password !== $passwordConfirm) {
        $error = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } else {
        $db = get_db();
        // Check if username already exists (for customer OR staff)
        $stmt = $db->prepare("SELECT id, role FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
        $stmt->execute([$username]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Requirement: "ถ้า user pass ของลูกค้าตรงกับพนักงานก็ให้ใส่ user อื่น"
            $error = 'ชื่อผู้ใช้ "' . htmlspecialchars($username) . '" มีอยู่ในระบบแล้ว (อาจตรงกับพนักงานหรือสมาชิกท่านอื่น) กรุณาเลือกใช้ชื่อผู้ใช้อื่น';
        } else {
            // Hash password and insert
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $now = date('Y-m-d H:i:s');
            $insert = $db->prepare("INSERT INTO users (username, password, full_name, email, phone, address, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'customer', 'active', ?)");
            $insert->execute([$username, $hash, $fullName, $email, $phone, $address, $now]);
            
            set_flash('success', 'สมัครสมาชิกสำเร็จเรียบร้อย! คุณสามารถเข้าสู่ระบบเพื่อสั่งซื้อหรือติดตามงานซ่อมได้ทันที');
            header("Location: " . base_url('login.php'));
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-[80vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-lg w-full space-y-6">
        <!-- Header -->
        <div class="text-center">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-slate-900 text-white shadow-lg mb-3">
                <i data-lucide="user-plus" class="w-7 h-7 text-sky-400"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">สมัครสมาชิกผู้ใช้งานใหม่</h2>
            <p class="mt-1 text-sm text-slate-500">
                สร้างบัญชีสำหรับสั่งซื้อโน้ตบุ๊ก ติดตามสถานะงานซ่อม และสะสมประวัติการบริการ
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center space-x-3">
                <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm">
            <form method="POST" action="register.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                        ชื่อ-นามสกุลจริง <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($fullName); ?>" required
                           placeholder="เช่น นายเอกชัย ใจดี"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                        ชื่อผู้ใช้ (Username) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required
                           placeholder="ภาษาอังกฤษหรือตัวเลข (เช่น ekkachai99)"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm">
                    <p class="text-[11px] text-slate-400 mt-1">หากชื่อผู้ใช้ตรงกับของพนักงานหรือสมาชิกท่านอื่น ระบบจะแจ้งเตือนให้ใช้ชื่ออื่น</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            รหัสผ่าน <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" name="password" required placeholder="อย่างน้อย 6 ตัวอักษร"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            ยืนยันรหัสผ่าน <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" name="password_confirm" required placeholder="กรอกรหัสผ่านซ้ำอีกครั้ง"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            เบอร์โทรศัพท์ติดต่อ
                        </label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>"
                               placeholder="เช่น 089-123-4567"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            อีเมล (Email)
                        </label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>"
                               placeholder="example@mail.com"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                        ที่อยู่จัดส่ง / ที่อยู่ติดต่อ
                    </label>
                    <textarea name="address" rows="2" placeholder="บ้านเลขที่, ถนน, แขวง/ตำบล, เขต/อำเภอ, จังหวัด, รหัสไปรษณีย์"
                              class="w-full px-4 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm"><?php echo htmlspecialchars($address); ?></textarea>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-medium text-sm shadow-md transition duration-150 flex items-center justify-center space-x-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span>ยืนยันการสมัครสมาชิก</span>
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-4 border-t border-slate-100 text-center text-xs text-slate-500">
                มีบัญชีอยู่แล้วใช่ไหม? 
                <a href="<?php echo base_url('login.php'); ?>" class="text-sky-600 hover:underline font-semibold">เข้าสู่ระบบที่นี่</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
