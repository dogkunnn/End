<?php
// ========================================================
// TechNote Pro - Smart Database Connection Layer
// Supports MySQL (XAMPP / Web Hosting) with automatic SQLite fallback & Debug Mode
// ========================================================

// Safe session start with fallback directory
if (session_status() === PHP_SESSION_NONE) {
    $sessDir = __DIR__ . '/../data/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0777, true);
    }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
    }
    @session_start();
}

// ========================================================
// ตั้งค่าการเชื่อมต่อฐานข้อมูล MySQL (Database Credentials)
// ========================================================
// สำหรับ Localhost (XAMPP):
//   $mysql_host = '127.0.0.1';
//   $mysql_port = '3306';
//   $mysql_user = 'root';
//   $mysql_pass = '';
//   $mysql_db   = 'laptop_store';
//
// สำหรับ InfinityFree / Free Hosting:
//   $mysql_host = 'sqlXXX.infinityfree.com';  <-- ดูจากแท็บ MySQL Details ใน Control Panel
//   $mysql_user = 'if0_43318804';             <-- บัญชี MySQL Username ของคุณ
//   $mysql_pass = 'YOUR_VPANEL_PASSWORD';     <-- รหัสผ่านเดียวกับที่เข้า vPanel/cPanel
//   $mysql_db   = 'if0_43318804_laptop_store'; <-- ชื่อฐานข้อมูลเต็มที่สร้างใน MySQL Databases
// ========================================================

$mysql_host = getenv('DB_HOST') ?: '127.0.0.1';
$mysql_port = getenv('DB_PORT') ?: '3306';
$mysql_user = getenv('DB_USER') ?: 'root';
$mysql_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$mysql_db   = getenv('DB_NAME') ?: 'laptop_store';

$pdo = null;
$db_driver = 'none';
$mysql_connected = false;
$mysql_error_message = null;

// 1. Try MySQL Connection
try {
    $dsn = "mysql:host={$mysql_host};port={$mysql_port};dbname={$mysql_db};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 2,
    ];
    $pdo = new PDO($dsn, $mysql_user, $mysql_pass, $options);
    $db_driver = 'mysql';
    $mysql_connected = true;

    // Check if tables exist, if empty and file exists, run seed (for local root auto-install)
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'products'")->fetch();
        if (!$checkTable) {
            $sqlFile = __DIR__ . '/../sql/database.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
            }
        }
    } catch (Exception $e) {
        // Ignored, proceed
    }

} catch (PDOException $e) {
    $mysql_error_message = $e->getMessage();
    
    // If unknown database on localhost with root, attempt creation
    if (strpos($e->getMessage(), 'Unknown database') !== false && ($mysql_user === 'root' || $mysql_host === '127.0.0.1')) {
        try {
            $rootDsn = "mysql:host={$mysql_host};port={$mysql_port};charset=utf8mb4";
            $rootPdo = new PDO($rootDsn, $mysql_user, $mysql_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$mysql_db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            
            $pdo = new PDO($dsn, $mysql_user, $mysql_pass, $options);
            $db_driver = 'mysql';
            $mysql_connected = true;
            $mysql_error_message = null;
            
            $sqlFile = __DIR__ . '/../sql/database.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
            }
        } catch (Exception $ex) {
            $mysql_error_message = $ex->getMessage();
            $pdo = null;
        }
    } else {
        $pdo = null;
    }
}

// 2. If MySQL failed, fallback to SQLite
if (!$pdo) {
    $sqlitePath = __DIR__ . '/../data/laptop_store.sqlite';
    $isNewDb = !file_exists($sqlitePath) || filesize($sqlitePath) === 0;

    try {
        $pdo = new PDO("sqlite:" . $sqlitePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db_driver = 'sqlite';

        if ($isNewDb) {
            require_once __DIR__ . '/init_sqlite.php';
            init_sqlite_database($pdo);
        }
    } catch (PDOException $e) {
        // Both MySQL and SQLite failed -> Show friendly diagnostics page
        $sqlite_error = $e->getMessage();
        ?>
        <!DOCTYPE html>
        <html lang="th">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>TechNote Pro - Database Connection Error</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4 font-sans">
            <div class="max-w-2xl w-full bg-slate-800 rounded-2xl border border-slate-700 p-6 sm:p-8 shadow-2xl space-y-6">
                <div class="flex items-center space-x-3 text-rose-400">
                    <svg class="w-8 h-8 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <h1 class="text-xl font-bold text-white">ไม่สามารถเชื่อมต่อฐานข้อมูลได้ (Database Connection Error)</h1>
                        <p class="text-xs text-slate-400 mt-0.5">TechNote Pro ไม่สามารถเชื่อมต่อกับ MySQL และไม่สามารถเขียนไฟล์ SQLite สำรองได้</p>
                    </div>
                </div>

                <div class="bg-slate-900/80 rounded-xl p-4 border border-rose-900/50 space-y-3">
                    <h3 class="text-xs font-bold text-rose-300 uppercase tracking-wider">สาเหตุและรายละเอียด Error (Debug Info):</h3>
                    <div class="text-xs space-y-1 font-mono text-slate-300">
                        <p><strong class="text-slate-400">MySQL Host:</strong> <?php echo htmlspecialchars($mysql_host); ?>:<?php echo htmlspecialchars($mysql_port); ?></p>
                        <p><strong class="text-slate-400">MySQL Database:</strong> <?php echo htmlspecialchars($mysql_db); ?></p>
                        <p><strong class="text-slate-400">MySQL User:</strong> <?php echo htmlspecialchars($mysql_user); ?></p>
                        <p><strong class="text-rose-400">MySQL Error:</strong> <?php echo htmlspecialchars($mysql_error_message ?: 'Connection failed'); ?></p>
                        <p><strong class="text-amber-400">SQLite Fallback Error:</strong> <?php echo htmlspecialchars($sqlite_error); ?></p>
                    </div>
                </div>

                <div class="space-y-3 text-xs leading-relaxed text-slate-300">
                    <h3 class="font-bold text-white text-sm">💡 วิธีแก้ไขสำหรับ Web Hosting (เช่น InfinityFree):</h3>
                    <ol class="list-decimal list-inside space-y-1.5 pl-1 text-slate-300">
                        <li>เปิดไฟล์ <code class="px-1.5 py-0.5 rounded bg-slate-900 text-sky-400 font-mono">config/db.php</code></li>
                        <li>แก้ไขตัวแปร <code class="text-sky-300 font-mono">$mysql_host</code> ให้ตรงกับค่า <strong>MySQL Hostname</strong> ในหน้า Control Panel ของโฮสติ้ง (เช่น <code class="font-mono text-amber-300">sqlXXX.infinityfree.com</code> ห้ามใส่ localhost)</li>
                        <li>แก้ไข <code class="text-sky-300 font-mono">$mysql_user</code> เป็นชื่อผู้ใช้ MySQL (เช่น <code class="font-mono text-amber-300">if0_43318804</code>)</li>
                        <li>แก้ไข <code class="text-sky-300 font-mono">$mysql_pass</code> เป็นรหัสผ่านบัญชีโฮสติ้ง</li>
                        <li>แก้ไข <code class="text-sky-300 font-mono">$mysql_db</code> เป็นชื่อฐานข้อมูลเต็ม (เช่น <code class="font-mono text-amber-300">if0_43318804_laptop_store</code>)</li>
                        <li>ใน phpMyAdmin: เลือกฐานข้อมูลแล้ว Import ไฟล์ <code class="text-sky-300 font-mono">sql/database.sql</code> ให้เรียบร้อย</li>
                    </ol>
                </div>

                <div class="pt-4 border-t border-slate-700 flex justify-between items-center text-xs text-slate-400">
                    <span>TechNote Pro Ecosystem</span>
                    <a href="javascript:location.reload()" class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-semibold transition">
                        ลองเชื่อมต่อใหม่อีกครั้ง (Retry)
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// 3. Ensure newer schema columns/tables exist (idempotent, safe on both drivers)
if ($pdo) {
    try {
        require_once __DIR__ . '/migrate.php';
        run_migration($pdo, $db_driver);
    } catch (Exception $e) {
        error_log('TechNote migration skipped: ' . $e->getMessage());
    }
}

// Global helper to get DB instance
function get_db() {
    global $pdo;
    return $pdo;
}

// Global helper to get database connection diagnostics
function get_db_debug_info() {
    global $pdo, $db_driver, $mysql_connected, $mysql_host, $mysql_port, $mysql_user, $mysql_db, $mysql_error_message;
    return [
        'connected'       => (bool)$pdo,
        'driver'          => $db_driver,
        'mysql_connected' => (bool)$mysql_connected,
        'mysql_host'      => $mysql_host,
        'mysql_port'      => $mysql_port,
        'mysql_user'      => $mysql_user,
        'mysql_db'        => $mysql_db,
        'mysql_error'     => $mysql_error_message,
    ];
}
