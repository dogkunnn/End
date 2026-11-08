<?php
$pageTitle = 'เข้าสู่ระบบรวม (Unified Login)';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

// If already logged in, redirect
if (is_logged_in()) {
    $role = $_SESSION['user_role'] ?? 'customer';
    if ($role === 'customer') {
        header("Location: " . base_url('customer/dashboard.php'));
    } else {
        header("Location: " . base_url('admin/index.php'));
    }
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกทั้งชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $result = attempt_login($username, $password);
        if ($result['success']) {
            set_flash('success', $result['message']);
            $target = !empty($_GET['redirect']) ? $_GET['redirect'] : $result['redirect'];
            header("Location: " . $target);
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-[80vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div class="text-center">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-slate-900 text-white shadow-lg mb-3">
                <i data-lucide="shield-check" class="w-7 h-7 text-sky-400"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">เข้าสู่ระบบ TechNote Pro</h2>
            <p class="mt-1 text-sm text-slate-500">
                เข้าสู่ระบบรวมหน้าเดียว (ตรวจสิทธิ์พนักงาน ผู้บริหาร และลูกค้าอัตโนมัติ)
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center space-x-3">
                <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <div class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm">
            <form method="POST" action="login.php<?php echo !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" class="space-y-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        ชื่อผู้ใช้ (Username)
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </div>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required
                               placeholder="กรอกชื่อผู้ใช้ เช่น admin, tech, customer"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm transition">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                            รหัสผ่าน (Password)
                        </label>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <input type="password" name="password" id="loginPassword" required
                               placeholder="รหัสผ่านของคุณ (ตัวอย่าง: 123456)"
                               class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm transition">
                        <button type="button" onclick="togglePassVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600">
                            <i data-lucide="eye" id="passEyeIcon" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-medium text-sm shadow-md transition duration-150 flex items-center justify-center space-x-2">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    <span>เข้าสู่ระบบ</span>
                </button>
            </form>

            <!-- Quick Auto-Fill for Testing & Presentation -->
            <div class="mt-6 pt-5 border-t border-slate-100">
                <button type="button" onclick="toggleTestDrawer()" class="w-full py-2 px-3 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 text-xs font-medium flex items-center justify-between transition">
                    <span class="flex items-center">
                        <i data-lucide="graduation-cap" class="w-4 h-4 mr-1.5 text-sky-600"></i>
                        <span>บัญชีทดสอบระบบสำหรับส่งอาจารย์ (คลิกเพื่อแสดง 6 แผนก)</span>
                    </span>
                    <i data-lucide="chevron-down" id="testDrawerIcon" class="w-4 h-4 text-slate-400 transition-transform"></i>
                </button>

                <div id="testAccountsDrawer" class="mt-3 space-y-2 hidden">
                    <p class="text-[11px] text-slate-500 text-center">คลิกเลือกเพื่อเข้าสู่ระบบทดสอบทันที (รหัสผ่านทุกบัญชี: 123456)</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 text-xs">
                        <button type="button" onclick="fillCred('ceo')" class="p-2 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-800 text-left transition font-medium border border-purple-200/50">
                            👑 ประธาน (CEO)
                        </button>
                        <button type="button" onclick="fillCred('admin')" class="p-2 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-800 text-left transition font-medium border border-blue-200/50">
                            👥 ฝ่ายบุคคล (HR)
                        </button>
                        <button type="button" onclick="fillCred('tech')" class="p-2 rounded-lg bg-cyan-50 hover:bg-cyan-100 text-cyan-800 text-left transition font-medium border border-cyan-200/50">
                            🔧 ฝ่ายช่างซ่อม
                        </button>
                        <button type="button" onclick="fillCred('stock')" class="p-2 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 text-left transition font-medium border border-amber-200/50">
                            📦 ฝ่ายสต็อก
                        </button>
                        <button type="button" onclick="fillCred('production')" class="p-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-left transition font-medium border border-emerald-200/50">
                            ⚙️ ฝ่ายผลิต/QC
                        </button>
                        <button type="button" onclick="fillCred('sales')" class="p-2 rounded-lg bg-pink-50 hover:bg-pink-100 text-pink-800 text-left transition font-medium border border-pink-200/50">
                            🛒 ฝ่ายขาย
                        </button>
                    </div>
                    <div class="pt-1 text-center">
                        <button type="button" onclick="fillCred('customer')" class="text-xs text-sky-600 hover:text-sky-800 font-medium">
                            👤 เข้าสู่ระบบในฐานะลูกค้าทั่วไป (customer)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Links to Register or Home -->
        <div class="flex items-center justify-between text-xs text-slate-500">
            <div>
                ยังไม่มีบัญชีลูกค้า? 
                <a href="<?php echo base_url('register.php'); ?>" class="text-sky-600 hover:underline font-semibold">สมัครสมาชิกที่นี่</a>
            </div>
            <div>
                <a href="<?php echo base_url('index.php'); ?>" class="text-slate-500 hover:text-slate-800 hover:underline flex items-center">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5 mr-1"></i> กลับหน้าหลักร้านค้า
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function fillCred(role) {
    document.querySelector('input[name="username"]').value = role;
    document.querySelector('input[name="password"]').value = '123456';
    document.querySelector('form').submit();
}

function toggleTestDrawer() {
    const drawer = document.getElementById('testAccountsDrawer');
    const icon = document.getElementById('testDrawerIcon');
    if (drawer) {
        drawer.classList.toggle('hidden');
        if (icon) {
            icon.classList.toggle('rotate-180');
        }
    }
}

function togglePassVisibility() {
    const input = document.getElementById('loginPassword');
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
