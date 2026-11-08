<?php
$user = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$role = $user['role'] ?? 'customer';
?>
<!-- Admin / Staff Sidebar -->
<aside class="w-64 bg-slate-900 text-slate-300 flex-shrink-0 min-h-screen border-r border-slate-800 flex flex-col justify-between hidden md:flex">
    <div>
        <!-- Brand Header -->
        <div class="p-6 border-b border-slate-800">
            <a href="<?php echo base_url('admin/index.php'); ?>" class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-sky-500 text-white flex items-center justify-center font-bold shadow-md shadow-sky-500/20">
                    <i data-lucide="laptop" class="w-5 h-5"></i>
                </div>
                <div>
                    <span class="text-lg font-bold text-white tracking-tight">TechNote Pro</span>
                    <span class="text-[11px] text-sky-400 block font-mono">STAFF ECOSYSTEM</span>
                </div>
            </a>
        </div>

        <!-- Current User Profile Card -->
        <div class="p-4 mx-3 my-4 rounded-xl bg-slate-800/80 border border-slate-700/60 flex items-center space-x-3">
            <div class="w-10 h-10 rounded-lg bg-sky-600/30 text-sky-400 flex items-center justify-center font-bold text-sm border border-sky-500/30">
                <?php echo mb_substr($user['full_name'], 0, 1, 'UTF-8'); ?>
            </div>
            <div class="overflow-hidden">
                <div class="text-xs font-semibold text-white truncate"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div class="text-[10px] text-sky-400 font-mono"><?php echo htmlspecialchars($user['employee_code'] ?? 'STAFF'); ?></div>
                <div class="text-[10px] text-slate-400 truncate"><?php echo get_role_title($role); ?></div>
            </div>
        </div>

        <!-- Department Navigation Links -->
        <div class="px-3 space-y-1 text-xs">
            <div class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                ภาพรวมและแผนกงานเฉพาะ
            </div>

            <!-- 1. ภาพรวมร้าน -->
            <a href="<?php echo base_url('admin/index.php'); ?>" 
               class="flex items-center justify-between px-3 py-2.5 rounded-lg transition <?php echo $currentScript === 'index.php' ? 'bg-sky-600 text-white font-medium shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                <div class="flex items-center space-x-2.5">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    <span>แดชบอร์ดภาพรวมร้าน</span>
                </div>
            </a>

            <!-- 2. ประธาน (CEO) - แสดงเฉพาะ CEO และ Admin -->
            <?php if (has_department_access('president')): ?>
                <?php $isCeoPage = $currentScript === 'president.php'; ?>
                <a href="<?php echo base_url('admin/president.php'); ?>" 
                   class="flex items-center justify-between px-3 py-2.5 rounded-lg transition <?php echo $isCeoPage ? 'bg-purple-600 text-white font-medium shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <div class="flex items-center space-x-2.5">
                        <i data-lucide="crown" class="w-4 h-4 <?php echo $isCeoPage ? 'text-white' : 'text-purple-400'; ?>"></i>
                        <span>ฝ่ายประธาน (CEO)</span>
                    </div>
                </a>
            <?php endif; ?>

            <!-- 3. HR / Admin - แสดงเฉพาะ HR, Admin, CEO -->
            <?php if (has_department_access('hr')): ?>
                <?php $isHrPage = $currentScript === 'hr.php'; ?>
                <a href="<?php echo base_url('admin/hr.php'); ?>" 
                   class="flex items-center justify-between px-3 py-2.5 rounded-lg transition <?php echo $isHrPage ? 'bg-blue-600 text-white font-medium shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <div class="flex items-center space-x-2.5">
                        <i data-lucide="users" class="w-4 h-4 <?php echo $isHrPage ? 'text-white' : 'text-blue-400'; ?>"></i>
                        <span>ฝ่ายบุคคล (HR/Admin)</span>
                    </div>
                </a>
            <?php endif; ?>

            <!-- 4. ฝ่ายผลิต (Production/QC) -->
            <?php if (has_department_access('production')): ?>
                <?php $isPrdPage = $currentScript === 'production.php'; ?>
                <a href="<?php echo base_url('admin/production.php'); ?>" 
                   class="flex items-center justify-between px-3 py-2.5 rounded-lg transition <?php echo $isPrdPage ? 'bg-emerald-600 text-white font-medium shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <div class="flex items-center space-x-2.5">
                        <i data-lucide="cpu" class="w-4 h-4 <?php echo $isPrdPage ? 'text-white' : 'text-emerald-400'; ?>"></i>
                        <span>ฝ่ายผลิตและ QC</span>
                    </div>
                </a>
            <?php endif; ?>

            <!-- 5. สต็อกสินค้า & อะไหล่ -->
            <?php if (has_department_access('stock')): ?>
                <?php $isStkPage = $currentScript === 'stock.php'; ?>
                <a href="<?php echo base_url('admin/stock.php'); ?>" 
                   class="flex items-center justify-between px-3 py-2.5 rounded-lg transition <?php echo $isStkPage ? 'bg-amber-600 text-white font-medium shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <div class="flex items-center space-x-2.5">
                        <i data-lucide="boxes" class="w-4 h-4 <?php echo $isStkPage ? 'text-white' : 'text-amber-400'; ?>"></i>
                        <span>ฝ่ายคลังสินค้า & สต็อก</span>
                    </div>
                </a>
            <?php endif; ?>

            <!-- 6. ฝ่ายขาย / เซลล์ -->
            <?php if (has_department_access('sales')): ?>
                <?php $isSlsPage = $currentScript === 'sales.php'; ?>
                <a href="<?php echo base_url('admin/sales.php'); ?>" 
                   class="flex items-center justify-between px-3 py-2.5 rounded-lg transition <?php echo $isSlsPage ? 'bg-pink-600 text-white font-medium shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <div class="flex items-center space-x-2.5">
                        <i data-lucide="shopping-cart" class="w-4 h-4 <?php echo $isSlsPage ? 'text-white' : 'text-pink-400'; ?>"></i>
                        <span>ฝ่ายขาย (Sales & POS)</span>
                    </div>
                </a>
            <?php endif; ?>

            <!-- 7. ช่างเทคนิค & งานซ่อม -->
            <?php if (has_department_access('technician')): ?>
                <?php $isTecPage = $currentScript === 'technician.php'; ?>
                <a href="<?php echo base_url('admin/technician.php'); ?>" 
                   class="flex items-center justify-between px-3 py-2.5 rounded-lg transition <?php echo $isTecPage ? 'bg-cyan-600 text-white font-medium shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <div class="flex items-center space-x-2.5">
                        <i data-lucide="wrench" class="w-4 h-4 <?php echo $isTecPage ? 'text-white' : 'text-cyan-400'; ?>"></i>
                        <span>ฝ่ายช่างเทคนิค & งานซ่อม</span>
                    </div>
                </a>
            <?php endif; ?>

            <?php if (has_department_access('stock')): ?>
                <div class="pt-2 px-3 py-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                    การจัดการข้อมูล
                </div>

                <!-- จัดการสินค้าโน้ตบุ๊ก -->
                <a href="<?php echo base_url('admin/stock.php?tab=laptops'); ?>" 
                   class="flex items-center space-x-2.5 px-3 py-2.5 rounded-lg transition <?php echo ($currentScript === 'stock.php' && ($_GET['tab'] ?? '') === 'laptops') ? 'bg-slate-800 text-white font-medium' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                    <i data-lucide="laptop" class="w-4 h-4"></i>
                    <span>จัดการโน้ตบุ๊กหน้าร้าน</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom Actions -->
    <div class="p-4 border-t border-slate-800 space-y-1.5 text-xs">
        <a href="<?php echo base_url('index.php'); ?>" target="_blank" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
            <i data-lucide="external-link" class="w-4 h-4"></i>
            <span>ดูหน้าร้าน (เปิดแท็บใหม่)</span>
        </a>
        <a href="<?php echo base_url('logout.php'); ?>" class="flex items-center space-x-2 px-3 py-2 rounded-lg text-rose-400 hover:text-rose-300 hover:bg-rose-950/30 transition">
            <i data-lucide="log-out" class="w-4 h-4"></i>
            <span>ออกจากระบบ</span>
        </a>
    </div>
</aside>
