<?php
$pageTitle = 'ศูนย์จำหน่ายและซ่อมโน้ตบุ๊กครบวงจร';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$db = get_db();

// Fetch Categories
$categories = $db->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

// 1. Fetch Brand New Products (โน้ตบุ๊กมือหนึ่ง ป้ายแดง)
$newProducts = $db->query("SELECT p.*, c.name as category_name 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.item_condition = 'new' AND p.status != 'hidden'
                          ORDER BY p.is_featured DESC, (p.stock_quantity > 0) DESC, p.id DESC LIMIT 6")->fetchAll();

// 2. Fetch Certified Second-Hand Products (โน้ตบุ๊กมือสอง ผ่าน QC)
$usedProducts = $db->query("SELECT p.*, c.name as category_name 
                           FROM products p 
                           LEFT JOIN categories c ON p.category_id = c.id 
                           WHERE p.item_condition IN ('used', 'refurbished') AND p.status != 'hidden'
                           ORDER BY p.is_featured DESC, (p.stock_quantity > 0) DESC, p.id DESC LIMIT 6")->fetchAll();

// 3. Fetch Active Coupons for customer banner
$activeCoupons = [];
try {
    $activeCoupons = $db->query("SELECT * FROM coupons WHERE active = 1 AND (expires_at IS NULL OR expires_at >= CURRENT_DATE) ORDER BY min_order ASC LIMIT 4")->fetchAll();
} catch (Exception $e) {
    $activeCoupons = [];
}

// Fetch Announcements
$announcements = $db->query("SELECT * FROM system_announcements ORDER BY created_at DESC LIMIT 2")->fetchAll();

// Counts for stats
$laptopCount = $db->query("SELECT COUNT(*) FROM products WHERE status != 'hidden'")->fetchColumn();
$newCount = $db->query("SELECT COUNT(*) FROM products WHERE item_condition = 'new' AND status != 'hidden'")->fetchColumn();
$usedCount = $db->query("SELECT COUNT(*) FROM products WHERE item_condition IN ('used', 'refurbished') AND status != 'hidden'")->fetchColumn();
$repairDoneCount = $db->query("SELECT COUNT(*) FROM repair_tickets WHERE status IN ('ready_for_pickup', 'completed')")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="relative bg-slate-900 text-white overflow-hidden py-16 lg:py-24 border-b border-slate-800">
    <div class="absolute inset-0 bg-gradient-to-tr from-slate-950 via-slate-900 to-sky-950 opacity-90"></div>
    <div class="absolute inset-0 bg-[radial-gradient(#38bdf8_1px,transparent_1px)] [background-size:24px_24px] opacity-10"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            
            <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                <div class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-full bg-sky-500/10 border border-sky-400/20 text-sky-300 text-xs font-medium">
                    <span class="flex h-2 w-2 rounded-full bg-sky-400 animate-pulse"></span>
                    <span>บริการหลัก: โน้ตบุ๊กมือหนึ่ง & มือสอง QC ผ่าน & ศูนย์ซ่อมมาตรฐาน</span>
                </div>

                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-white leading-tight">
                    โน้ตบุ๊กคุณภาพ สเปกแรง <br class="hidden sm:inline">
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-sky-400 to-blue-400">
                        ทั้งมือ 1 และมือ 2 ผ่าน QC
                    </span>
                </h1>

                <p class="text-slate-300 text-base sm:text-lg max-w-2xl leading-relaxed">
                    ศูนย์รวมโน้ตบุ๊กเกมมิ่ง, ทำงาน, บางเบา เครื่องใหม่ประกันศูนย์ 100% และโน้ตบุ๊กมือสองคัดเกรดพรีเมียม ผ่านการตรวจเช็ค 25 จุด พร้อมศูนย์บริการซ่อมมาตรฐาน
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3 pt-2">
                    <a href="<?php echo base_url('products.php?condition=new'); ?>" class="w-full sm:w-auto px-6 py-3.5 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-bold shadow-lg shadow-sky-500/25 transition duration-150 flex items-center justify-center space-x-2">
                        <i data-lucide="sparkles" class="w-4 h-4"></i>
                        <span>โน้ตบุ๊กมือหนึ่ง (<?php echo $newCount; ?> รุ่น)</span>
                    </a>
                    <a href="<?php echo base_url('products.php?condition=used'); ?>" class="w-full sm:w-auto px-6 py-3.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold shadow-lg shadow-amber-500/25 transition duration-150 flex items-center justify-center space-x-2">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                        <span>โน้ตบุ๊กมือสอง (<?php echo $usedCount; ?> รุ่น)</span>
                    </a>
                    <a href="<?php echo base_url('repair-service.php'); ?>" class="w-full sm:w-auto px-5 py-3.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-medium border border-slate-700 transition duration-150 flex items-center justify-center space-x-1.5">
                        <i data-lucide="wrench" class="w-4 h-4 text-sky-400"></i>
                        <span>แจ้งซ่อมออนไลน์</span>
                    </a>
                </div>

                <!-- Trust Badges -->
                <div class="grid grid-cols-3 gap-4 pt-6 border-t border-slate-800/80 text-left">
                    <div>
                        <div class="text-2xl font-bold text-white font-mono"><?php echo $laptopCount; ?>+ รุ่น</div>
                        <div class="text-xs text-slate-400">โน้ตบุ๊กพร้อมส่ง</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-sky-400 font-mono">100% QC</div>
                        <div class="text-xs text-slate-400">ตรวจเช็คก่อนส่งมอบ</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-white font-mono"><?php echo (int)$repairDoneCount + 420; ?>+ งาน</div>
                        <div class="text-xs text-slate-400">บริการซ่อมสำเร็จ</div>
                    </div>
                </div>
            </div>

            <!-- Hero Feature Card -->
            <div class="lg:col-span-5">
                <div class="relative mx-auto max-w-md lg:max-w-none">
                    <div class="rounded-2xl overflow-hidden border border-slate-700/80 shadow-2xl bg-slate-800/50 backdrop-blur-sm">
                        <img src="https://images.unsplash.com/photo-1603302576837-37561b2e2302?auto=format&fit=crop&w=1000&q=80" 
                             alt="Featured Laptop" 
                             class="w-full h-72 object-cover">
                        <div class="p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-sky-500/20 text-sky-300 border border-sky-400/30">
                                    ⭐ สินค้าเด่นประจำสัปดาห์
                                </span>
                                <span class="text-xs text-slate-400 flex items-center">
                                    <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-400 mr-1"></i> มีสินค้าในสต็อก
                                </span>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white">ASUS ROG Strix G16 (2025)</h3>
                                <p class="text-xs text-slate-400 mt-1">i7-14650HX | RTX 4060 | 16GB DDR5 | 1TB SSD | 165Hz</p>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-700/60">
                                <div>
                                    <span class="text-xs text-slate-400 block">ราคาพิเศษ (มีโค้ดลดเพิ่ม)</span>
                                    <span class="text-xl font-bold text-sky-400 font-mono">฿48,990.00</span>
                                </div>
                                <a href="<?php echo base_url('product-detail.php?id=1'); ?>" class="px-4 py-2 rounded-lg bg-white hover:bg-slate-100 text-slate-900 text-xs font-semibold transition">
                                    ดูสเปก & สั่งซื้อ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Active Promotional Coupons Strip -->
<?php if (!empty($activeCoupons)): ?>
<section class="bg-gradient-to-r from-pink-600 via-rose-600 to-pink-700 text-white py-3.5 px-4 shadow-sm">
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-3 text-xs">
        <div class="flex items-center space-x-2 text-center md:text-left">
            <span class="p-1 rounded-md bg-white/20 text-white"><i data-lucide="ticket-percent" class="w-4 h-4"></i></span>
            <span class="font-bold text-sm">โค้ดส่วนลดพิเศษออนไลน์:</span>
            <span class="text-pink-100 hidden sm:inline">กรอกโค้ดได้ที่หน้าตะกร้าสินค้าก่อนชำระเงิน</span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php foreach ($activeCoupons as $cp): ?>
                <a href="<?php echo base_url('cart.php'); ?>" 
                   class="px-3 py-1 rounded-lg bg-white text-slate-900 font-mono font-bold text-xs hover:bg-pink-50 transition shadow-xs flex items-center space-x-1"
                   title="<?php echo htmlspecialchars($cp['description']); ?>">
                    <span><?php echo htmlspecialchars($cp['code']); ?></span>
                    <span class="text-[10px] text-pink-600 font-sans">
                        (<?php echo $cp['discount_type'] === 'percent' ? 'ลด ' . (int)$cp['discount_value'] . '%' : 'ลด ฿' . number_format($cp['discount_value']); ?>)
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Category Quick Filter Row -->
<section class="py-8 bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-slate-900">หมวดหมู่โน้ตบุ๊กยอดนิยม</h2>
            <a href="<?php echo base_url('products.php'); ?>" class="text-xs font-semibold text-sky-600 hover:text-sky-700 flex items-center">
                ดูทั้งหมด <i data-lucide="chevron-right" class="w-4 h-4 ml-0.5"></i>
            </a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <?php foreach ($categories as $cat): ?>
                <a href="<?php echo base_url('products.php?category=' . $cat['id']); ?>" 
                   class="p-4 rounded-xl border border-slate-200 hover:border-sky-500 hover:bg-sky-50/50 transition group flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 group-hover:bg-sky-500 group-hover:text-white text-slate-700 flex items-center justify-center transition">
                        <i data-lucide="<?php echo htmlspecialchars($cat['icon'] ?? 'laptop'); ?>" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-800 group-hover:text-sky-600"><?php echo htmlspecialchars($cat['name']); ?></div>
                        <div class="text-[11px] text-slate-400">เลือกดูสินค้า</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- SECTION 1: โน้ตบุ๊กมือหนึ่ง (Brand New Laptops) -->
<section class="py-14 bg-slate-50 border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-8">
            <div>
                <div class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full bg-sky-100 text-sky-800 text-xs font-bold">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5 text-sky-600"></i>
                    <span>BRAND NEW 100%</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 mt-2">โน้ตบุ๊กมือหนึ่ง ป้ายแดง (ประกันศูนย์ไทย)</h2>
                <p class="text-sm text-slate-500 mt-1">เครื่องใหม่แกะกล่อง ประกันศูนย์ 2-3 ปีเต็ม พร้อมบริการตรวจเช็คและลงระบบพื้นฐานฟรี</p>
            </div>
            <a href="<?php echo base_url('products.php?condition=new'); ?>" class="mt-4 sm:mt-0 inline-flex items-center space-x-1.5 px-4 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-sky-50 hover:text-sky-700 text-slate-700 text-xs font-semibold shadow-xs transition">
                <span>ดูโน้ตบุ๊กมือหนึ่งทั้งหมด (<?php echo $newCount; ?> รุ่น)</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($newProducts as $item): ?>
                <?php $pr = product_pricing($item); ?>
                <div class="bg-white rounded-2xl border <?php echo $item['stock_quantity'] <= 0 ? 'border-slate-200 bg-slate-50/50' : 'border-slate-200'; ?> overflow-hidden shadow-xs hover-card-lift flex flex-col justify-between">
                    <div>
                        <!-- Product Image -->
                        <div class="relative bg-slate-100 h-52 overflow-hidden group <?php echo $item['stock_quantity'] <= 0 ? 'grayscale-75 opacity-75' : ''; ?>">
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['model']); ?>" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                            
                            <span class="absolute top-3 left-3 px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-900/80 text-white backdrop-blur-xs">
                                <?php echo htmlspecialchars($item['brand']); ?>
                            </span>

                            <span class="absolute bottom-3 left-3 px-2 py-0.5 rounded text-[10px] font-bold bg-sky-500 text-white shadow-xs">
                                มือหนึ่ง ป้ายแดง
                            </span>

                            <?php if ($pr['has_discount']): ?>
                                <span class="absolute bottom-3 right-3 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white shadow-xs">
                                    <?php echo $pr['label']; ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($item['stock_quantity'] > 0): ?>
                                <span class="absolute top-3 right-3 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                    พร้อมส่ง (คงเหลือ <?php echo $item['stock_quantity']; ?> เครื่อง)
                                </span>
                            <?php else: ?>
                                <span class="absolute top-3 right-3 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-600 text-white shadow-xs">
                                    สินค้าหมด
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Content -->
                        <div class="p-5 space-y-3">
                            <div>
                                <span class="text-[11px] font-medium text-sky-600"><?php echo htmlspecialchars($item['category_name'] ?? 'Laptop'); ?></span>
                                <h3 class="text-base font-bold text-slate-900 line-clamp-1 mt-0.5">
                                    <a href="<?php echo base_url('product-detail.php?id=' . $item['id']); ?>" class="hover:text-sky-600 transition">
                                        <?php echo htmlspecialchars($item['model']); ?>
                                    </a>
                                </h3>
                            </div>

                            <!-- Specs Chips -->
                            <div class="grid grid-cols-2 gap-2 text-[11px] bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-slate-600">
                                <div class="truncate"><strong class="text-slate-800">CPU:</strong> <?php echo htmlspecialchars($item['cpu']); ?></div>
                                <div class="truncate"><strong class="text-slate-800">RAM:</strong> <?php echo htmlspecialchars($item['ram']); ?></div>
                                <div class="truncate"><strong class="text-slate-800">SSD:</strong> <?php echo htmlspecialchars($item['storage']); ?></div>
                                <div class="truncate"><strong class="text-slate-800">GPU:</strong> <?php echo htmlspecialchars($item['gpu']); ?></div>
                            </div>

                            <p class="text-xs text-slate-500 line-clamp-2">
                                <?php echo htmlspecialchars($item['description']); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Footer: Price & Actions -->
                    <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-400 block">ราคาจำหน่าย</span>
                            <?php if ($pr['has_discount']): ?>
                                <div class="text-[11px] text-slate-400 line-through font-mono"><?php echo format_baht($item['price']); ?></div>
                                <span class="text-lg font-bold text-rose-600 font-mono"><?php echo format_baht($pr['final']); ?></span>
                            <?php else: ?>
                                <span class="text-lg font-bold text-slate-900 font-mono"><?php echo format_baht($item['price']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="flex space-x-2">
                            <a href="<?php echo base_url('product-detail.php?id=' . $item['id']); ?>" 
                               class="px-3 py-2 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-semibold transition" title="ดูรายละเอียดสเปก">
                                รายละเอียด
                            </a>
                            <?php if ($item['stock_quantity'] <= 0): ?>
                                <span class="px-3.5 py-2 rounded-xl bg-slate-100 text-slate-400 text-xs font-semibold cursor-not-allowed">
                                    สินค้าหมด
                                </span>
                            <?php elseif (!is_logged_in()): ?>
                                <a href="<?php echo base_url('login.php?redirect=' . urlencode(base_url('product-detail.php?id=' . $item['id']))); ?>" 
                                   class="px-3.5 py-2 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 text-xs font-semibold transition flex items-center space-x-1">
                                    <i data-lucide="log-in" class="w-3.5 h-3.5"></i>
                                    <span>สั่งซื้อ</span>
                                </a>
                            <?php else: ?>
                                <a href="<?php echo base_url('cart.php?action=add&id=' . $item['id']); ?>" 
                                   class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-xs transition flex items-center space-x-1">
                                    <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i>
                                    <span>สั่งซื้อ</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- SECTION 2: โน้ตบุ๊กมือสอง คัดเกรดพรีเมียม (Certified Used Laptops) -->
<section class="py-14 bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-8">
            <div>
                <div class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 text-xs font-bold">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5 text-amber-600"></i>
                    <span>CERTIFIED PRE-OWNED (QC 100%)</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 mt-2">โน้ตบุ๊กมือสอง คัดเกรดพรีเมียม (ผ่าน QC 25 รายการ)</h2>
                <p class="text-sm text-slate-500 mt-1">โน้ตบุ๊กมือสองสภาพสวย 95%+ บอร์ดเดิม ไม่ผ่านการซ่อมหนัก แบตเตอรี่สุขภาพดี พร้อมรับประกันร้าน</p>
            </div>
            <a href="<?php echo base_url('products.php?condition=used'); ?>" class="mt-4 sm:mt-0 inline-flex items-center space-x-1.5 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold shadow-xs transition">
                <span>ดูโน้ตบุ๊กมือสองทั้งหมด (<?php echo $usedCount; ?> รุ่น)</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($usedProducts as $item): ?>
                <?php $pr = product_pricing($item); ?>
                <div class="bg-white rounded-2xl border border-amber-200/80 overflow-hidden shadow-xs hover-card-lift flex flex-col justify-between ring-1 ring-amber-100">
                    <div>
                        <!-- Product Image -->
                        <div class="relative bg-slate-100 h-52 overflow-hidden group">
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['model']); ?>" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                            
                            <span class="absolute top-3 left-3 px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-900/80 text-white backdrop-blur-xs">
                                <?php echo htmlspecialchars($item['brand']); ?>
                            </span>

                            <span class="absolute bottom-3 left-3 px-2.5 py-0.5 rounded text-[10px] font-bold bg-amber-500 text-white shadow-xs flex items-center gap-1">
                                <i data-lucide="shield-check" class="w-3 h-3"></i> มือสอง QC ผ่าน
                            </span>

                            <?php if ($pr['has_discount']): ?>
                                <span class="absolute bottom-3 right-3 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white shadow-xs">
                                    <?php echo $pr['label']; ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($item['stock_quantity'] > 0): ?>
                                <span class="absolute top-3 right-3 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                    พร้อมส่ง (มี <?php echo $item['stock_quantity']; ?> เครื่อง)
                                </span>
                            <?php else: ?>
                                <span class="absolute top-3 right-3 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-600 text-white shadow-xs">
                                    สินค้าหมด
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Content -->
                        <div class="p-5 space-y-3">
                            <div>
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-medium text-amber-700"><?php echo htmlspecialchars($item['category_name'] ?? 'Laptop'); ?></span>
                                    <span class="text-slate-400 font-mono text-[10px]">ประกันร้าน 6 เดือน</span>
                                </div>
                                <h3 class="text-base font-bold text-slate-900 line-clamp-1 mt-0.5">
                                    <a href="<?php echo base_url('product-detail.php?id=' . $item['id']); ?>" class="hover:text-amber-600 transition">
                                        <?php echo htmlspecialchars($item['model']); ?>
                                    </a>
                                </h3>
                            </div>

                            <!-- Specs Chips -->
                            <div class="grid grid-cols-2 gap-2 text-[11px] bg-amber-50/50 p-2.5 rounded-xl border border-amber-100 text-slate-600">
                                <div class="truncate"><strong class="text-slate-800">CPU:</strong> <?php echo htmlspecialchars($item['cpu']); ?></div>
                                <div class="truncate"><strong class="text-slate-800">RAM:</strong> <?php echo htmlspecialchars($item['ram']); ?></div>
                                <div class="truncate"><strong class="text-slate-800">SSD:</strong> <?php echo htmlspecialchars($item['storage']); ?></div>
                                <div class="truncate"><strong class="text-slate-800">GPU:</strong> <?php echo htmlspecialchars($item['gpu']); ?></div>
                            </div>

                            <p class="text-xs text-slate-500 line-clamp-2">
                                <?php echo htmlspecialchars($item['description']); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Footer: Price & Actions -->
                    <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-400 block">ราคาประหยัด</span>
                            <?php if ($pr['has_discount']): ?>
                                <div class="text-[11px] text-slate-400 line-through font-mono"><?php echo format_baht($item['price']); ?></div>
                                <span class="text-lg font-bold text-rose-600 font-mono"><?php echo format_baht($pr['final']); ?></span>
                            <?php else: ?>
                                <span class="text-lg font-bold text-amber-700 font-mono"><?php echo format_baht($item['price']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="flex space-x-2">
                            <a href="<?php echo base_url('product-detail.php?id=' . $item['id']); ?>" 
                               class="px-3 py-2 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-semibold transition">
                                สเปก & รายการ QC
                            </a>
                            <?php if ($item['stock_quantity'] <= 0): ?>
                                <span class="px-3.5 py-2 rounded-xl bg-slate-100 text-slate-400 text-xs font-semibold cursor-not-allowed">
                                    สินค้าหมด
                                </span>
                            <?php elseif (!is_logged_in()): ?>
                                <a href="<?php echo base_url('login.php?redirect=' . urlencode(base_url('product-detail.php?id=' . $item['id']))); ?>" 
                                   class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold transition flex items-center space-x-1">
                                    <i data-lucide="log-in" class="w-3.5 h-3.5"></i>
                                    <span>สั่งซื้อ</span>
                                </a>
                            <?php else: ?>
                                <a href="<?php echo base_url('cart.php?action=add&id=' . $item['id']); ?>" 
                                   class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-xs transition flex items-center space-x-1">
                                    <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i>
                                    <span>สั่งซื้อ</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Main Service 2: Laptop Repair & Upgrade Service -->
<section class="py-16 bg-slate-50 border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <span class="text-xs font-bold text-sky-600 uppercase tracking-wider">REPAIR & UPGRADE WORKSHOP</span>
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 mt-1">บริการศูนย์ซ่อมและอัปเกรดโน้ตบุ๊ก</h2>
            <p class="text-sm text-slate-500 mt-2">
                ตรวจเช็คอาการฟรี แจ้งประเมินราคาก่อนเริ่มซ่อม อะไหล่แท้ทุกชิ้น พร้อมระบบติดตามสถานะงานซ่อมแบบ Real-time
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="p-6 rounded-2xl border border-slate-200 bg-white hover:border-sky-300 hover:shadow-md transition">
                <div class="w-12 h-12 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center mb-4">
                    <i data-lucide="monitor" class="w-6 h-6"></i>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-2">เปลี่ยนจอโน้ตบุ๊ก & ซ่อมบานพับ</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    จอแตก จอเป็นเส้น จอกระพริบ จอมืดสนิท หรือบานพับหัก เรามีอะไหล่จอ 14" และ 15.6" Full HD / 144Hz / OLED พร้อมเปลี่ยนเสร็จไว
                </p>
            </div>

            <div class="p-6 rounded-2xl border border-slate-200 bg-white hover:border-sky-300 hover:shadow-md transition">
                <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center mb-4">
                    <i data-lucide="hard-drive" class="w-6 h-6"></i>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-2">อัปเกรด RAM & M.2 NVMe SSD</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    เพิ่มความเร็วเครื่องเก่าให้ลื่นไหลเหมือนเครื่องใหม่ บูตเครื่องภายใน 5 วินาที พร้อมโคลนระบบหรือลง Windows แท้ ครบชุด
                </p>
            </div>

            <div class="p-6 rounded-2xl border border-slate-200 bg-white hover:border-sky-300 hover:shadow-md transition">
                <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center mb-4">
                    <i data-lucide="flame" class="w-6 h-6"></i>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-2">ล้างฝุ่น ทาซิลิโคน & ซ่อมเมนบอร์ด</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    แก้ปัญหาเครื่องร้อน ดับเอง พัดลมเสียงดัง ทาซิลิโคนเกรดท็อป และซ่อมชิปเซ็ตภาคจ่ายไฟโน้ตบุ๊กทุกยี่ห้อ
                </p>
            </div>
        </div>

        <!-- Banner for Quick Booking -->
        <div class="bg-gradient-to-r from-slate-900 to-sky-950 rounded-3xl p-8 sm:p-10 text-white flex flex-col md:flex-row items-center justify-between gap-6 shadow-xl">
            <div class="space-y-2 text-center md:text-left">
                <div class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full bg-sky-500/20 text-sky-300 text-xs font-semibold">
                    <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                    <span>ตรวจเช็คฟรี ไม่มีค่าเปิดเครื่อง</span>
                </div>
                <h3 class="text-2xl font-bold">โน้ตบุ๊กของคุณมีปัญหาใช่หรือไม่?</h3>
                <p class="text-sm text-slate-300 max-w-xl">
                    ส่งข้อมูลแจ้งซ่อมออนไลน์ล่วงหน้า เพื่อให้ช่างเตรียมอะไหล่และประเมินเวลาซ่อมให้คุณได้ทันที
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="<?php echo base_url('repair-service.php'); ?>" class="px-6 py-3.5 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-semibold shadow-lg text-sm text-center transition">
                    ส่งฟอร์มแจ้งซ่อมออนไลน์
                </a>
                <a href="<?php echo base_url('repair-track.php'); ?>" class="px-6 py-3.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-medium border border-slate-700 text-sm text-center transition">
                    ติดตามเครื่องที่กำลังซ่อม
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Latest Announcements Section -->
<?php if (!empty($announcements)): ?>
<section class="py-10 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-slate-50 rounded-2xl border border-slate-200 p-6 shadow-xs">
            <div class="flex items-center space-x-3 mb-4">
                <span class="p-2 rounded-lg bg-sky-100 text-sky-700">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                </span>
                <h3 class="text-base font-bold text-slate-900">ประกาศและข่าวสารจากศูนย์บริการ</h3>
            </div>
            <div class="divide-y divide-slate-200/60">
                <?php foreach ($announcements as $ann): ?>
                    <div class="py-3 first:pt-0 last:pb-0">
                        <div class="flex items-center space-x-2 text-xs text-slate-500 mb-1">
                            <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($ann['author_name']); ?></span>
                            <span>•</span>
                            <span><?php echo format_date($ann['created_at']); ?></span>
                        </div>
                        <h4 class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($ann['title']); ?></h4>
                        <p class="text-xs text-slate-600 mt-1 leading-relaxed"><?php echo htmlspecialchars($ann['message']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
