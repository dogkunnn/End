<?php
// ========================================================
// TechNote Pro - One-Click Database Installer & Setup Wizard
// Perfect for student submission and grading demonstrations
// ========================================================

require_once __DIR__ . '/config/helpers.php';

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    $engine = $_POST['engine'] ?? 'auto';
    $mysql_host = $_POST['mysql_host'] ?? '127.0.0.1';
    $mysql_port = $_POST['mysql_port'] ?? '3306';
    $mysql_user = $_POST['mysql_user'] ?? 'root';
    $mysql_pass = $_POST['mysql_pass'] ?? '';
    $mysql_db   = $_POST['mysql_db'] ?? 'laptop_store';

    $installed = false;

    // If MySQL chosen or auto
    if ($engine === 'mysql' || $engine === 'auto') {
        try {
            $dbPdo = null;
            // 1. First try direct connection to target database (hosting friendly: InfinityFree / cPanel)
            try {
                $dbDsn = "mysql:host={$mysql_host};port={$mysql_port};dbname={$mysql_db};charset=utf8mb4";
                $dbPdo = new PDO($dbDsn, $mysql_user, $mysql_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
            } catch (Exception $eDirect) {
                // If unknown database and on localhost/root, try create database
                if (strpos($eDirect->getMessage(), 'Unknown database') !== false && ($mysql_user === 'root' || $mysql_host === '127.0.0.1')) {
                    $rootDsn = "mysql:host={$mysql_host};port={$mysql_port};charset=utf8mb4";
                    $rootPdo = new PDO($rootDsn, $mysql_user, $mysql_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
                    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$mysql_db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                    $dbPdo = new PDO($dbDsn, $mysql_user, $mysql_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                } else {
                    throw $eDirect;
                }
            }

            if ($dbPdo) {
                $sqlFile = __DIR__ . '/sql/database.sql';
                if (file_exists($sqlFile)) {
                    $sql = file_get_contents($sqlFile);
                    $dbPdo->exec($sql);
                    $installed = true;
                    $status = 'success';
                    $message = "ติดตั้งฐานข้อมูล MySQL (`{$mysql_db}`) สำเร็จ พร้อมข้อมูลจำลองครบถ้วนเรียบร้อยแล้ว!";
                }
            }
        } catch (Exception $e) {
            if ($engine === 'mysql') {
                $status = 'danger';
                $message = "ไม่สามารถเชื่อมต่อ MySQL ได้: " . $e->getMessage();
            }
        }
    }

    // Fallback to SQLite if MySQL failed or requested
    if (!$installed && ($engine === 'sqlite' || $engine === 'auto')) {
        try {
            $sqlitePath = __DIR__ . '/data/laptop_store.sqlite';
            if (file_exists($sqlitePath)) {
                @unlink($sqlitePath);
            }
            $sqlitePdo = new PDO("sqlite:" . $sqlitePath);
            $sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            require_once __DIR__ . '/config/init_sqlite.php';
            init_sqlite_database($sqlitePdo);
            
            $status = 'success';
            $message = "ติดตั้งฐานข้อมูลระบบ (Zero-Config SQLite) สำเร็จเรียบร้อยแล้ว พร้อมใช้งานทันที!";
            $installed = true;
        } catch (Exception $e) {
            $status = 'danger';
            $message = "เกิดข้อผิดพลาดในการติดตั้งฐานข้อมูล: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตั้งระบบฐานข้อมูล | TechNote Pro</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen py-10 px-4">
    <div class="max-w-3xl mx-auto">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-200 mb-4">
                <i data-lucide="laptop" class="w-9 h-9"></i>
            </div>
            <h1 class="text-3xl font-bold text-slate-900">TechNote Pro</h1>
            <p class="text-slate-500 mt-1">ระบบจำหน่ายและศูนย์บริการซ่อมโน้ตบุ๊กแบบครบวงจร (โปรเจ็คจบการศึกษา)</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-xl border flex items-center space-x-3 <?php echo $status === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'; ?>">
                <i data-lucide="<?php echo $status === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-6 h-6 flex-shrink-0"></i>
                <div class="flex-1 font-medium"><?php echo htmlspecialchars($message); ?></div>
            </div>
        <?php endif; ?>

        <!-- Setup Card -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 mb-8">
            <div class="flex items-center space-x-3 border-b border-slate-100 pb-4 mb-6">
                <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                    <i data-lucide="database" class="w-6 h-6"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-900">ตัวช่วยติดตั้งฐานข้อมูลและรีเซ็ตข้อมูลตัวอย่าง</h2>
                    <p class="text-sm text-slate-500">คลิกเพียงครั้งเดียวเพื่อสร้างตารางทั้งหมด และเพิ่มข้อมูลตัวอย่างสำหรับนำเสนออาจารย์</p>
                </div>
            </div>

            <form method="POST" action="install.php" class="space-y-6">
                <input type="hidden" name="action" value="install">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">เลือกประเภทฐานข้อมูล</label>
                        <select name="engine" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="auto">ตรวจหาอัตโนมัติ (แนะนำ - MySQL หรือ SQLite สำรอง)</option>
                            <option value="mysql">MySQL / MariaDB (XAMPP ปกติ)</option>
                            <option value="sqlite">SQLite (ใช้งานได้ทันทีไม่ต้องเปิด MySQL)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">MySQL Database Host</label>
                        <input type="text" name="mysql_host" value="127.0.0.1" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">MySQL Username</label>
                        <input type="text" name="mysql_user" value="root" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">MySQL Password</label>
                        <input type="password" name="mysql_pass" placeholder="ค่าว่าง (XAMPP default ว่างไว้ได้)" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                    <a href="index.php" class="inline-flex items-center space-x-2 text-sm text-slate-600 hover:text-slate-900 font-medium">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>กลับไปหน้าแรกของร้าน</span>
                    </a>
                    <button type="submit" class="inline-flex items-center space-x-2 px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-md shadow-indigo-200 transition">
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                        <span>เริ่มการติดตั้ง / ติดตั้งข้อมูลใหม่ 1-Click</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Testing & Demo Credentials Card -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <h3 class="text-lg font-bold text-slate-900 mb-2 flex items-center space-x-2">
                <i data-lucide="key-round" class="w-5 h-5 text-indigo-600"></i>
                <span>ข้อมูลบัญชีทดสอบระบบสำหรับทุกแผนก (Demo Accounts)</span>
            </h3>
            <p class="text-sm text-slate-500 mb-6">รหัสผ่านสำหรับทุกบัญชีพนักงานและลูกค้าคือ: <strong class="text-indigo-600 font-mono bg-indigo-50 px-2 py-0.5 rounded">123456</strong></p>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 text-xs uppercase font-semibold">
                            <th class="py-3 px-4 rounded-l-lg">ตำแหน่ง / แผนก</th>
                            <th class="py-3 px-4">Username</th>
                            <th class="py-3 px-4">รหัสผ่าน</th>
                            <th class="py-3 px-4 rounded-r-lg">รหัสลับประจำแผนก (Passcode)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-normal">
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-medium text-slate-900 flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                                <span>1. ประธาน / ผู้บริหาร (President/CEO)</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-indigo-600 font-semibold">ceo</td>
                            <td class="py-3 px-4 font-mono text-slate-600">123456</td>
                            <td class="py-3 px-4 font-mono font-bold text-purple-600 bg-purple-50">CEO999</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-medium text-slate-900 flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                <span>2. ผู้จัดการฝ่ายบุคคล / Admin (HR)</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-indigo-600 font-semibold">admin</td>
                            <td class="py-3 px-4 font-mono text-slate-600">123456</td>
                            <td class="py-3 px-4 font-mono font-bold text-blue-600 bg-blue-50">HR777</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-medium text-slate-900 flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                <span>3. ฝ่ายผลิตและตรวจสอบ (Production/QC)</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-indigo-600 font-semibold">production</td>
                            <td class="py-3 px-4 font-mono text-slate-600">123456</td>
                            <td class="py-3 px-4 font-mono font-bold text-emerald-600 bg-emerald-50">PRD202</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-medium text-slate-900 flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                <span>4. ฝ่ายคลังสินค้าและสต็อก (Stock)</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-indigo-600 font-semibold">stock</td>
                            <td class="py-3 px-4 font-mono text-slate-600">123456</td>
                            <td class="py-3 px-4 font-mono font-bold text-amber-600 bg-amber-50">STK303</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-medium text-slate-900 flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-pink-500"></span>
                                <span>5. ฝ่ายขายและลูกค้าสัมพันธ์ (Sales)</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-indigo-600 font-semibold">sales</td>
                            <td class="py-3 px-4 font-mono text-slate-600">123456</td>
                            <td class="py-3 px-4 font-mono font-bold text-pink-600 bg-pink-50">SLS404</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-medium text-slate-900 flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-cyan-500"></span>
                                <span>6. ฝ่ายช่างเทคนิคและงานซ่อม (Technician)</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-indigo-600 font-semibold">tech</td>
                            <td class="py-3 px-4 font-mono text-slate-600">123456</td>
                            <td class="py-3 px-4 font-mono font-bold text-cyan-600 bg-cyan-50">TEC505</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-medium text-slate-900 flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                                <span>7. ลูกค้าทั่วไป (Customer)</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-indigo-600 font-semibold">customer</td>
                            <td class="py-3 px-4 font-mono text-slate-600">123456</td>
                            <td class="py-3 px-4 text-slate-400 italic">ไม่มีสิทธิ์เข้าแผนกภายใน</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Guide: Hosting & InfinityFree Setup (วิธีแก้ปัญหาบนโฮสติ้งและ phpMyAdmin) -->
            <div class="mt-8 p-6 rounded-2xl bg-amber-50 border border-amber-200 text-slate-800 space-y-4">
                <div class="flex items-center space-x-3 text-amber-900 font-bold text-base">
                    <i data-lucide="help-circle" class="w-6 h-6 text-amber-600"></i>
                    <span>คำแนะนำการติดตั้งบน Web Hosting (เช่น InfinityFree, cPanel)</span>
                </div>

                <div class="text-xs text-slate-700 space-y-3 leading-relaxed">
                    <div class="p-3 bg-white rounded-xl border border-amber-200">
                        <strong class="text-amber-900 font-bold block mb-1">❓ สาเหตุที่เจอ Error #1044 - Access denied for user 'if0_43318804' to database 'laptop_store':</strong>
                        บนฟรีโฮสติ้งหรือ Shared Hosting ทั่วไป ผู้ใช้จะไม่มีสิทธิ์รันคำสั่ง <code class="bg-slate-100 px-1 py-0.5 rounded font-mono text-rose-600">CREATE DATABASE</code> หรือตั้งชื่อฐานข้อมูลเองตามใจชอบ แต่ต้องใช้ชื่อฐานข้อมูลที่ระบบโฮสติ้งสร้างให้เท่านั้น (เช่น <code class="bg-slate-100 px-1 py-0.5 rounded font-mono text-emerald-700">if0_43318804_laptop_store</code>)
                    </div>

                    <ol class="list-decimal list-inside space-y-1.5 pl-1 font-medium">
                        <li><strong>สร้างฐานข้อมูลใน Control Panel:</strong> เข้า vPanel/cPanel -> เมนู <strong>MySQL Databases</strong> -> กดสร้างฐานข้อมูล (เช่น ใส่ชื่อ laptop_store จะได้ชื่อเต็ม เช่น <code class="font-mono text-indigo-700">if0_43318804_laptop_store</code>)</li>
                        <li><strong>เปิด phpMyAdmin:</strong> คลิกปุ่ม <strong>phpMyAdmin</strong> จาก Control Panel -> <strong class="text-rose-700">คลิกเลือกชื่อฐานข้อมูลที่สร้างไว้ทางซ้ายก่อนเสมอ</strong></li>
                        <li><strong>นำเข้าไฟล์ SQL:</strong> คลิกแท็บ <strong>Import (นำเข้า)</strong> ด้านบน -> เลือกไฟล์ <code class="font-mono text-indigo-700">sql/database.sql</code> (ซึ่งระบบแก้ไขตัดคำสั่ง CREATE DATABASE ออกให้เรียบร้อยแล้ว) -> กดปุ่ม <strong>Import</strong></li>
                        <li><strong>ตั้งค่าใน config/db.php:</strong> เปิดไฟล์ <code class="font-mono text-indigo-700">config/db.php</code> กรอก <strong>MySQL Hostname</strong> (เช่น <code class="font-mono text-indigo-700">sqlXXX.infinityfree.com</code> ห้ามใส่ 127.0.0.1 หรือ localhost), User, Password, และ Database name ให้ตรงกับหน้า MySQL Details</li>
                    </ol>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <span class="text-xs text-slate-500">หมายเหตุ: รหัสลับประจำแผนกสามารถแก้ไขได้โดยฝ่ายบุคคล (HR/Admin)</span>
                <div class="flex space-x-3">
                    <a href="login.php" class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium transition">
                        ไปหน้าเข้าสู่ระบบ (Login)
                    </a>
                    <a href="index.php" class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-100 text-slate-700 text-sm font-medium transition">
                        เข้าสู่หน้าร้านหลัก
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
