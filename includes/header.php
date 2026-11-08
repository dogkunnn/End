<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['qty'] ?? 1);
    }
}

$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="th" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : ''; ?>TechNote Pro - ศูนย์จำหน่ายและซ่อมโน้ตบุ๊กครบวงจร</title>
    <!-- Google Fonts: Prompt & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Prompt', 'Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            200: '#bae0fd',
                            500: '#0284c7',
                            600: '#0369a1',
                            700: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Custom styling -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css'); ?>">
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen font-sans antialiased selection:bg-sky-500 selection:text-white">

    <?php 
    $dbDebug = function_exists('get_db_debug_info') ? get_db_debug_info() : null;
    if ($dbDebug && !$dbDebug['mysql_connected']): 
    ?>
    <!-- Database Connection Debug Banner -->
    <div id="dbDebugBanner" class="bg-amber-500 text-slate-950 px-4 py-2 text-xs border-b border-amber-600 shadow-xs z-50">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-start md:items-center justify-between gap-2">
            <div class="flex items-start md:items-center space-x-2">
                <span class="px-2 py-0.5 rounded bg-slate-900 text-amber-300 font-mono font-bold text-[10px] tracking-wider uppercase">DB DEBUG</span>
                <div>
                    <span class="font-bold">แจ้งเตือนสถานะฐานข้อมูล:</span>
                    <span>ยังไม่ได้เชื่อมต่อ MySQL (โฮสต์: <code class="font-mono bg-amber-400/80 px-1 rounded"><?php echo htmlspecialchars($dbDebug['mysql_host']); ?></code>, ฐานข้อมูล: <code class="font-mono bg-amber-400/80 px-1 rounded"><?php echo htmlspecialchars($dbDebug['mysql_db']); ?></code>)</span>
                    <span class="text-amber-950 block sm:inline font-medium"> | กำลังใช้โหมดสำรอง SQLite ชั่วคราว</span>
                    <?php if (!empty($dbDebug['mysql_error'])): ?>
                        <div class="text-[11px] text-amber-950 font-mono mt-0.5 bg-amber-400/60 px-2 py-0.5 rounded inline-block">
                            ข้อผิดพลาด: <?php echo htmlspecialchars($dbDebug['mysql_error']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center space-x-2 flex-shrink-0">
                <a href="<?php echo base_url('install.php?tab=debug'); ?>" class="px-3 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-xs transition">
                    วิธีตั้งค่าเชื่อมต่อ MySQL บนโฮสติ้ง
                </a>
                <button type="button" onclick="document.getElementById('dbDebugBanner').remove()" class="p-1 rounded hover:bg-amber-600/30 text-slate-900" title="ปิดข้อความแจ้งเตือน">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Announcement Bar -->
    <div class="bg-slate-900 text-slate-300 text-xs py-2 px-4 border-b border-slate-800">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-2">
            <div class="flex items-center space-x-4">
                <span class="flex items-center"><i data-lucide="map-pin" class="w-3.5 h-3.5 mr-1 text-sky-400"></i> ศูนย์บริการเปิดทุกวัน 09:30 - 20:00 น.</span>
                <span class="hidden md:flex items-center"><i data-lucide="phone-call" class="w-3.5 h-3.5 mr-1 text-emerald-400"></i> สายด่วนซ่อม & ฝ่ายขาย: 02-999-8888</span>
            </div>
            <div class="flex items-center space-x-4 text-xs">
                <a href="<?php echo base_url('repair-track.php'); ?>" class="hover:text-white flex items-center text-sky-300 hover:underline">
                    <i data-lucide="search" class="w-3.5 h-3.5 mr-1"></i> เช็คสถานะซ่อมด่วน
                </a>
                <?php if ($currentUser && $currentUser['role'] !== 'customer'): ?>
                    <span class="text-slate-600">|</span>
                    <a href="<?php echo base_url('admin/index.php'); ?>" class="hover:text-white flex items-center text-sky-400 font-medium">
                        <i data-lucide="layout-dashboard" class="w-3.5 h-3.5 mr-1"></i> ระบบงานพนักงาน
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <header class="bg-white/95 backdrop-blur-md border-b border-slate-200 sticky top-0 z-40 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 sm:h-20">
                <!-- Brand Logo -->
                <div class="flex items-center space-x-3">
                    <a href="<?php echo base_url('index.php'); ?>" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-slate-900 text-white flex items-center justify-center shadow-md group-hover:bg-sky-600 transition duration-200">
                            <i data-lucide="laptop" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <span class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 block leading-tight">
                                Tech<span class="text-sky-600">Note</span> <span class="text-xs bg-sky-100 text-sky-800 px-1.5 py-0.5 rounded font-mono font-medium">PRO</span>
                            </span>
                            <span class="text-[11px] text-slate-500 hidden sm:block tracking-wide">จำหน่ายและศูนย์บริการซ่อมโน้ตบุ๊ก</span>
                        </div>
                    </a>
                </div>

                <!-- Desktop Navigation Menu -->
                <nav class="hidden lg:flex items-center space-x-1">
                    <a href="<?php echo base_url('index.php'); ?>" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:text-sky-600 hover:bg-slate-50 transition">
                        หน้าแรก
                    </a>
                    <a href="<?php echo base_url('products.php?condition=new'); ?>" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:text-sky-600 hover:bg-slate-50 transition flex items-center">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5 mr-1 text-sky-500"></i> โน้ตบุ๊กมือหนึ่ง
                    </a>
                    <a href="<?php echo base_url('products.php?condition=used'); ?>" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:text-amber-600 hover:bg-slate-50 transition flex items-center">
                        <i data-lucide="shield-check" class="w-3.5 h-3.5 mr-1 text-amber-500"></i> มือสอง QC ผ่าน
                    </a>
                    <a href="<?php echo base_url('repair-service.php'); ?>" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:text-sky-600 hover:bg-slate-50 transition flex items-center">
                        <span class="relative flex h-2 w-2 mr-1.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-sky-500"></span>
                        </span>
                        แจ้งซ่อมออนไลน์
                    </a>
                    <a href="<?php echo base_url('repair-track.php'); ?>" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:text-sky-600 hover:bg-slate-50 transition">
                        ติดตามสถานะซ่อม
                    </a>
                </nav>

                <!-- Actions: Cart + User Profile / Login -->
                <div class="flex items-center space-x-3 sm:space-x-4">
                    <!-- Shopping Cart -->
                    <a href="<?php echo base_url('cart.php'); ?>" class="relative p-2.5 rounded-xl text-slate-700 hover:text-sky-600 hover:bg-slate-100 transition" title="ตะกร้าสินค้า">
                        <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                        <?php if ($cartCount > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-rose-500 text-white text-[11px] font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-xs">
                                <?php echo $cartCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- Staff / User Dropdown or Login -->
                    <?php if ($currentUser): ?>
                        <div class="relative" id="userMenuDropdown">
                            <button onclick="toggleUserMenu()" class="flex items-center space-x-2 p-1.5 sm:px-3 sm:py-2 rounded-xl border border-slate-200 hover:border-slate-300 bg-white hover:bg-slate-50 transition text-left">
                                <div class="w-8 h-8 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center font-bold text-xs uppercase">
                                    <?php echo mb_substr($currentUser['full_name'], 0, 1, 'UTF-8'); ?>
                                </div>
                                <div class="hidden sm:block text-left">
                                    <div class="text-xs font-semibold text-slate-900 truncate max-w-[120px]">
                                        <?php echo htmlspecialchars($currentUser['full_name']); ?>
                                    </div>
                                    <div class="text-[10px] text-slate-500">
                                        <?php echo get_role_title($currentUser['role']); ?>
                                    </div>
                                </div>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                            </button>

                            <!-- Dropdown Menu -->
                            <div id="userDropdownContent" class="hidden absolute right-0 mt-2 w-64 bg-white rounded-2xl shadow-xl border border-slate-100 py-2 z-50 divide-y divide-slate-100">
                                <div class="px-4 py-3">
                                    <p class="text-xs text-slate-500">เข้าสู่ระบบในชื่อ</p>
                                    <p class="text-sm font-bold text-slate-900 truncate"><?php echo htmlspecialchars($currentUser['full_name']); ?></p>
                                    <span class="inline-block mt-1 text-[11px] px-2 py-0.5 rounded-full bg-sky-100 text-sky-800 font-medium">
                                        <?php echo get_role_title($currentUser['role']); ?>
                                    </span>
                                </div>

                                <div class="py-1">
                                    <?php if ($currentUser['role'] !== 'customer'): ?>
                                        <!-- Staff Portals Link -->
                                        <a href="<?php echo base_url('admin/index.php'); ?>" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-sky-50 hover:text-sky-700 font-medium">
                                            <i data-lucide="layout-dashboard" class="w-4 h-4 mr-2.5 text-sky-600"></i>
                                            แดชบอร์ดภาพรวมร้าน
                                        </a>

                                        <?php if ($currentUser['role'] === 'president' || $currentUser['role'] === 'admin'): ?>
                                            <a href="<?php echo base_url('admin/president.php'); ?>" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                <i data-lucide="crown" class="w-4 h-4 mr-2.5 text-purple-600"></i>
                                                หน้าท่านประธาน (CEO)
                                            </a>
                                        <?php endif; ?>

                                        <?php if (in_array($currentUser['role'], ['hr', 'admin', 'president'])): ?>
                                            <a href="<?php echo base_url('admin/hr.php'); ?>" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                <i data-lucide="users" class="w-4 h-4 mr-2.5 text-blue-600"></i>
                                                ฝ่ายบุคคล/Admin (จัดการรหัส)
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($currentUser['role'] === 'technician' || $currentUser['role'] === 'admin'): ?>
                                            <a href="<?php echo base_url('admin/technician.php'); ?>" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                <i data-lucide="wrench" class="w-4 h-4 mr-2.5 text-cyan-600"></i>
                                                หน้างานช่างเทคนิค
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($currentUser['role'] === 'stock' || $currentUser['role'] === 'admin'): ?>
                                            <a href="<?php echo base_url('admin/stock.php'); ?>" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                <i data-lucide="boxes" class="w-4 h-4 mr-2.5 text-amber-600"></i>
                                                ฝ่ายคลังสินค้าและสต็อก
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($currentUser['role'] === 'production' || $currentUser['role'] === 'admin'): ?>
                                            <a href="<?php echo base_url('admin/production.php'); ?>" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                <i data-lucide="cpu" class="w-4 h-4 mr-2.5 text-emerald-600"></i>
                                                ฝ่ายผลิต/QC เตรียมเครื่อง
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($currentUser['role'] === 'sales' || $currentUser['role'] === 'admin'): ?>
                                            <a href="<?php echo base_url('admin/sales.php'); ?>" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                <i data-lucide="shopping-cart" class="w-4 h-4 mr-2.5 text-pink-600"></i>
                                                ฝ่ายขาย (Sales POS)
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Customer links -->
                                        <a href="<?php echo base_url('customer/dashboard.php'); ?>" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                            <i data-lucide="user" class="w-4 h-4 mr-2.5 text-slate-500"></i>
                                            ข้อมูลส่วนตัวและคำสั่งซื้อ
                                        </a>
                                        <a href="<?php echo base_url('customer/dashboard.php#repairs'); ?>" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                            <i data-lucide="clock" class="w-4 h-4 mr-2.5 text-slate-500"></i>
                                            ประวัติการส่งซ่อมของฉัน
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="py-1">
                                    <a href="<?php echo base_url('logout.php'); ?>" class="flex items-center px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 font-medium">
                                        <i data-lucide="log-out" class="w-4 h-4 mr-2.5"></i>
                                        ออกจากระบบ
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Login / Register Buttons -->
                        <div class="flex items-center space-x-2">
                            <a href="<?php echo base_url('login.php'); ?>" class="px-4 py-2 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-100 transition">
                                เข้าสู่ระบบ
                            </a>
                            <a href="<?php echo base_url('register.php'); ?>" class="px-4 py-2 rounded-xl text-sm font-semibold bg-slate-900 hover:bg-slate-800 text-white shadow-sm transition">
                                สมัครสมาชิก
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Mobile Menu Hamburger -->
                    <button type="button" onclick="toggleMobileMenu()" class="lg:hidden p-2 rounded-xl text-slate-700 hover:bg-slate-100 focus:outline-none">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Drawer Menu -->
        <div id="mobileMenu" class="hidden lg:hidden border-t border-slate-200 bg-white px-4 pt-3 pb-6 space-y-1 shadow-lg">
            <a href="<?php echo base_url('index.php'); ?>" class="block px-3 py-2 rounded-lg text-base font-medium text-slate-800 hover:bg-slate-50">
                หน้าแรก
            </a>
            <a href="<?php echo base_url('products.php?condition=new'); ?>" class="block px-3 py-2 rounded-lg text-base font-medium text-slate-800 hover:bg-slate-50 flex items-center">
                <i data-lucide="sparkles" class="w-4 h-4 mr-2 text-sky-500"></i> โน้ตบุ๊กมือหนึ่ง (เครื่องใหม่)
            </a>
            <a href="<?php echo base_url('products.php?condition=used'); ?>" class="block px-3 py-2 rounded-lg text-base font-medium text-slate-800 hover:bg-slate-50 flex items-center">
                <i data-lucide="shield-check" class="w-4 h-4 mr-2 text-amber-500"></i> โน้ตบุ๊กมือสอง (QC ผ่าน)
            </a>
            <a href="<?php echo base_url('repair-service.php'); ?>" class="block px-3 py-2 rounded-lg text-base font-medium text-slate-800 hover:bg-slate-50">
                แจ้งซ่อมโน้ตบุ๊กออนไลน์
            </a>
            <a href="<?php echo base_url('repair-track.php'); ?>" class="block px-3 py-2 rounded-lg text-base font-medium text-slate-800 hover:bg-slate-50">
                ตรวจสอบสถานะงานซ่อม
            </a>
        </div>
    </header>

    <!-- Global Notification/Flash Banner -->
    <?php require_once __DIR__ . '/notification.php'; ?>

    <!-- Main Content Container -->
    <main class="flex-1">
