<?php
$pageTitle = 'รายการโน้ตบุ๊กทั้งหมด (Laptop Store)';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$db = get_db();

// Filter parameters
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$brand      = isset($_GET['brand']) ? trim($_GET['brand']) : '';
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort       = isset($_GET['sort']) ? trim($_GET['sort']) : 'id_desc';
$priceRange = isset($_GET['price_range']) ? trim($_GET['price_range']) : '';
$condition  = isset($_GET['condition']) ? trim($_GET['condition']) : ''; // 'new', 'used', or '' (all)
if (!in_array($condition, ['new', 'used', ''])) {
    $condition = '';
}

// Helper to build URL with query params
function build_filter_url($overrides = []) {
    global $categoryId, $brand, $search, $sort, $priceRange, $condition;
    $params = [
        'category'    => $categoryId ?: null,
        'brand'       => $brand ?: null,
        'search'      => $search ?: null,
        'sort'        => $sort !== 'id_desc' ? $sort : null,
        'price_range' => $priceRange ?: null,
        'condition'   => $condition ?: null,
    ];
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '' || $v === 0) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    $q = http_build_query($params);
    return base_url('products.php' . ($q ? '?' . $q : ''));
}

// Build Query
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE (p.status IS NULL OR p.status != 'hidden')";
$params = [];

if ($categoryId > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryId;
}

if (!empty($brand)) {
    $sql .= " AND p.brand = ?";
    $params[] = $brand;
}

if (!empty($condition)) {
    if ($condition === 'used') {
        $sql .= " AND (p.item_condition = 'used' OR p.item_condition = 'refurbished')";
    } else {
        $sql .= " AND p.item_condition = ?";
        $params[] = $condition;
    }
}

if (!empty($search)) {
    $sql .= " AND (p.model LIKE ? OR p.cpu LIKE ? OR p.gpu LIKE ? OR p.brand LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($priceRange)) {
    if ($priceRange === 'under_20k') {
        $sql .= " AND p.price < 20000";
    } elseif ($priceRange === '20k_35k') {
        $sql .= " AND p.price >= 20000 AND p.price <= 35000";
    } elseif ($priceRange === '35k_50k') {
        $sql .= " AND p.price > 35000 AND p.price <= 50000";
    } elseif ($priceRange === 'over_50k') {
        $sql .= " AND p.price > 50000";
    }
}

// Sorting (Always show in-stock items first, out-of-stock items at the bottom)
$stockOrder = " (p.stock_quantity > 0) DESC, ";
if ($sort === 'price_asc') {
    $sql .= " ORDER BY " . $stockOrder . " p.price ASC";
} elseif ($sort === 'price_desc') {
    $sql .= " ORDER BY " . $stockOrder . " p.price DESC";
} else {
    $sql .= " ORDER BY " . $stockOrder . " p.id DESC";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Categories & Distinct Brands for filters
$categories = $db->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
$brands = $db->query("SELECT DISTINCT brand FROM products ORDER BY brand ASC")->fetchAll(PDO::FETCH_COLUMN);

// Counts for condition tabs
$countAll  = (int)$db->query("SELECT COUNT(*) FROM products WHERE (status IS NULL OR status != 'hidden')")->fetchColumn();
$countNew  = (int)$db->query("SELECT COUNT(*) FROM products WHERE item_condition = 'new' AND (status IS NULL OR status != 'hidden')")->fetchColumn();
$countUsed = (int)$db->query("SELECT COUNT(*) FROM products WHERE item_condition IN ('used', 'refurbished') AND (status IS NULL OR status != 'hidden')")->fetchColumn();

// Dynamic heading and description based on condition
if ($condition === 'new') {
    $headerTitle = 'โน้ตบุ๊กมือหนึ่ง (เครื่องใหม่ ประกันศูนย์)';
    $headerSubtitle = 'เครื่องใหม่แกะกล่อง 100% ประกันศูนย์ไทยเต็มระยะเวลา พร้อมบริการลงระบบและโปรแกรมพื้นฐานฟรี';
} elseif ($condition === 'used') {
    $headerTitle = 'โน้ตบุ๊กมือสอง ผ่านการตรวจเช็ค QC (Used & Certified)';
    $headerSubtitle = 'โน้ตบุ๊กมือสองสภาพเยี่ยม ผ่านการทดสอบฮาร์ดแวร์และ QC กว่า 20 รายการ พร้อมรับประกันจากทางร้าน 6 เดือนเต็ม';
} else {
    $headerTitle = 'รายการโน้ตบุ๊กทั้งหมด (มือหนึ่ง & มือสอง QC ผ่าน)';
    $headerSubtitle = 'บริการจำหน่ายโน้ตบุ๊กแท้ทุกรุ่น ทั้งเครื่องใหม่ประกันศูนย์ และมือสองสภาพนางฟ้าผ่าน QC ละเอียด';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-100 py-6 border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                    <?php if ($condition === 'used'): ?>
                        <span class="p-1.5 rounded-lg bg-amber-500 text-white"><i data-lucide="shield-check" class="w-5 h-5"></i></span>
                    <?php elseif ($condition === 'new'): ?>
                        <span class="p-1.5 rounded-lg bg-sky-500 text-white"><i data-lucide="sparkles" class="w-5 h-5"></i></span>
                    <?php else: ?>
                        <span class="p-1.5 rounded-lg bg-slate-900 text-white"><i data-lucide="laptop" class="w-5 h-5"></i></span>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($headerTitle); ?>
                </h1>
                <p class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($headerSubtitle); ?></p>
            </div>
            
            <!-- Quick Search Bar -->
            <form method="GET" action="products.php" class="flex items-center space-x-2">
                <?php if ($categoryId > 0): ?><input type="hidden" name="category" value="<?php echo $categoryId; ?>"><?php endif; ?>
                <?php if (!empty($brand)): ?><input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand); ?>"><?php endif; ?>
                <?php if (!empty($condition)): ?><input type="hidden" name="condition" value="<?php echo htmlspecialchars($condition); ?>"><?php endif; ?>
                <div class="relative w-full sm:w-72">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="ค้นหารุ่น, ซีพียู, การ์ดจอ..." 
                           class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-sky-500 focus:border-sky-500 bg-white">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
                <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-semibold hover:bg-slate-800 transition">
                    ค้นหา
                </button>
            </form>
        </div>

        <!-- Condition Navigation Tabs: แยกสินค้ามือหนึ่ง และ มือสอง ชัดเจน -->
        <div class="mt-6 flex flex-wrap items-center gap-2 pt-4 border-t border-slate-200">
            <span class="text-xs font-bold text-slate-600 mr-2">เลือกประเภทสินค้า:</span>
            
            <a href="<?php echo build_filter_url(['condition' => '']); ?>" 
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 <?php echo empty($condition) ? 'bg-slate-900 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-200 border border-slate-200'; ?>">
                <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                <span>สินค้าทั้งหมด</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] <?php echo empty($condition) ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'; ?>"><?php echo $countAll; ?></span>
            </a>

            <a href="<?php echo build_filter_url(['condition' => 'new']); ?>" 
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 <?php echo $condition === 'new' ? 'bg-sky-600 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-sky-50 hover:text-sky-700 border border-slate-200'; ?>">
                <i data-lucide="sparkles" class="w-3.5 h-3.5 text-sky-400"></i>
                <span>โน้ตบุ๊กมือหนึ่ง (เครื่องใหม่)</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] <?php echo $condition === 'new' ? 'bg-white/20 text-white' : 'bg-sky-50 text-sky-700'; ?>"><?php echo $countNew; ?></span>
            </a>

            <a href="<?php echo build_filter_url(['condition' => 'used']); ?>" 
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 <?php echo $condition === 'used' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-amber-50 hover:text-amber-700 border border-slate-200'; ?>">
                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-amber-300"></i>
                <span>โน้ตบุ๊กมือสอง (QC ผ่าน 100%)</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] <?php echo $condition === 'used' ? 'bg-white/20 text-white' : 'bg-amber-50 text-amber-700'; ?>"><?php echo $countUsed; ?></span>
            </a>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <!-- Filters Sidebar -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <span class="text-sm font-bold text-slate-900 flex items-center">
                        <i data-lucide="sliders-horizontal" class="w-4 h-4 mr-2 text-sky-600"></i> ตัวกรองสินค้า
                    </span>
                    <a href="<?php echo base_url('products.php'); ?>" class="text-[11px] text-sky-600 hover:underline">
                        ล้างตัวกรอง
                    </a>
                </div>

                <!-- Category Filter -->
                <div>
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">หมวดหมู่การใช้งาน</h3>
                    <div class="space-y-1 text-xs">
                        <a href="<?php echo base_url('products.php' . ($brand ? '?brand=' . urlencode($brand) : '')); ?>" 
                           class="block px-2.5 py-1.5 rounded-lg <?php echo $categoryId === 0 ? 'bg-sky-50 text-sky-700 font-semibold' : 'text-slate-600 hover:bg-slate-50'; ?>">
                            ทั้งหมด
                        </a>
                        <?php foreach ($categories as $cat): ?>
                            <a href="<?php echo base_url('products.php?category=' . $cat['id'] . ($brand ? '&brand=' . urlencode($brand) : '')); ?>" 
                               class="block px-2.5 py-1.5 rounded-lg <?php echo $categoryId === (int)$cat['id'] ? 'bg-sky-50 text-sky-700 font-semibold' : 'text-slate-600 hover:bg-slate-50'; ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Brand Filter -->
                <div>
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">แบรนด์ผู้ผลิต</h3>
                    <div class="flex flex-wrap gap-1.5 text-xs">
                        <a href="<?php echo base_url('products.php' . ($categoryId ? '?category=' . $categoryId : '')); ?>" 
                           class="px-2.5 py-1 rounded-md border <?php echo empty($brand) ? 'bg-slate-900 text-white border-slate-900 font-semibold' : 'border-slate-200 text-slate-600 hover:bg-slate-50'; ?>">
                            ทุกแบรนด์
                        </a>
                        <?php foreach ($brands as $b): ?>
                            <a href="<?php echo base_url('products.php?brand=' . urlencode($b) . ($categoryId ? '&category=' . $categoryId : '')); ?>" 
                               class="px-2.5 py-1 rounded-md border <?php echo $brand === $b ? 'bg-slate-900 text-white border-slate-900 font-semibold' : 'border-slate-200 text-slate-600 hover:bg-slate-50'; ?>">
                                <?php echo htmlspecialchars($b); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Price Range Filter -->
                <div>
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">ช่วงราคา</h3>
                    <div class="space-y-1 text-xs">
                        <a href="<?php echo base_url('products.php?category=' . $categoryId . '&brand=' . urlencode($brand) . '&price_range=under_20k'); ?>" 
                           class="block px-2.5 py-1.5 rounded-lg <?php echo $priceRange === 'under_20k' ? 'bg-sky-50 text-sky-700 font-semibold' : 'text-slate-600 hover:bg-slate-50'; ?>">
                            ต่ำกว่า ฿20,000
                        </a>
                        <a href="<?php echo base_url('products.php?category=' . $categoryId . '&brand=' . urlencode($brand) . '&price_range=20k_35k'); ?>" 
                           class="block px-2.5 py-1.5 rounded-lg <?php echo $priceRange === '20k_35k' ? 'bg-sky-50 text-sky-700 font-semibold' : 'text-slate-600 hover:bg-slate-50'; ?>">
                            ฿20,000 - ฿35,000
                        </a>
                        <a href="<?php echo base_url('products.php?category=' . $categoryId . '&brand=' . urlencode($brand) . '&price_range=35k_50k'); ?>" 
                           class="block px-2.5 py-1.5 rounded-lg <?php echo $priceRange === '35k_50k' ? 'bg-sky-50 text-sky-700 font-semibold' : 'text-slate-600 hover:bg-slate-50'; ?>">
                            ฿35,000 - ฿50,000
                        </a>
                        <a href="<?php echo base_url('products.php?category=' . $categoryId . '&brand=' . urlencode($brand) . '&price_range=over_50k'); ?>" 
                           class="block px-2.5 py-1.5 rounded-lg <?php echo $priceRange === 'over_50k' ? 'bg-sky-50 text-sky-700 font-semibold' : 'text-slate-600 hover:bg-slate-50'; ?>">
                            มากกว่า ฿50,000
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <!-- Products List Grid -->
        <div class="lg:col-span-3">
            <!-- Header bar: count + sort -->
            <div class="flex items-center justify-between mb-6 pb-2 border-b border-slate-200">
                <span class="text-xs text-slate-500">
                    พบโน้ตบุ๊กทั้งหมด <strong class="text-slate-800 font-semibold"><?php echo count($products); ?></strong> รายการ
                </span>

                <div class="flex items-center space-x-2 text-xs">
                    <span class="text-slate-500">เรียงตาม:</span>
                    <select onchange="location = this.value;" class="px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white text-xs text-slate-700 focus:ring-sky-500">
                        <option value="<?php echo base_url('products.php?sort=id_desc&category=' . $categoryId . '&brand=' . urlencode($brand)); ?>" <?php echo $sort === 'id_desc' ? 'selected' : ''; ?>>สินค้าล่าสุด</option>
                        <option value="<?php echo base_url('products.php?sort=price_asc&category=' . $categoryId . '&brand=' . urlencode($brand)); ?>" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>ราคา: ต่ำไปสูง</option>
                        <option value="<?php echo base_url('products.php?sort=price_desc&category=' . $categoryId . '&brand=' . urlencode($brand)); ?>" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>ราคา: สูงไปต่ำ</option>
                    </select>
                </div>
            </div>

            <?php if (empty($products)): ?>
                <div class="text-center py-16 bg-white rounded-2xl border border-slate-200 p-8">
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="search-x" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900">ไม่พบโน้ตบุ๊กตามเงื่อนไขที่เลือก</h3>
                    <p class="text-xs text-slate-500 mt-1">ลองล้างตัวกรองหรือเปลี่ยนคำค้นหาใหม่อีกครั้ง</p>
                    <a href="<?php echo base_url('products.php'); ?>" class="mt-4 inline-block px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold">
                        ล้างตัวกรองทั้งหมด
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($products as $item): ?>
                        <div class="bg-white rounded-2xl border <?php echo $item['stock_quantity'] <= 0 ? 'border-slate-200 bg-slate-50/50' : 'border-slate-200'; ?> overflow-hidden shadow-xs hover-card-lift flex flex-col justify-between">
                            <div>
                                <div class="relative bg-slate-100 h-48 overflow-hidden group <?php echo $item['stock_quantity'] <= 0 ? 'grayscale-75 opacity-75' : ''; ?>">
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['model']); ?>" 
                                         class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                    
                                    <span class="absolute top-3 left-3 px-2 py-0.5 rounded text-[10px] font-bold bg-slate-900/80 text-white backdrop-blur-xs">
                                        <?php echo htmlspecialchars($item['brand']); ?>
                                    </span>
                                    <?php $pr = product_pricing($item); ?>
                                    <?php if (($item['item_condition'] ?? 'new') !== 'new'): ?>
                                        <span class="absolute bottom-3 left-3 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500 text-white shadow-xs">
                                            <?php echo ($item['item_condition'] === 'refurbished') ? 'Refurbished' : 'มือสอง QC ผ่าน'; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($pr['has_discount']): ?>
                                        <span class="absolute bottom-3 right-3 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white shadow-xs">
                                            <?php echo $pr['label']; ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($item['stock_quantity'] > 0): ?>
                                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                                            มีสินค้า (<?php echo $item['stock_quantity']; ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white shadow-xs">
                                            สินค้าหมด
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="p-4 space-y-2.5">
                                    <div>
                                        <span class="text-[10px] font-semibold text-sky-600 uppercase tracking-wider"><?php echo htmlspecialchars($item['category_name'] ?? 'Laptop'); ?></span>
                                        <h3 class="text-sm font-bold text-slate-900 line-clamp-1 mt-0.5">
                                            <a href="<?php echo base_url('product-detail.php?id=' . $item['id']); ?>" class="hover:text-sky-600 transition">
                                                <?php echo htmlspecialchars($item['model']); ?>
                                            </a>
                                        </h3>
                                    </div>

                                    <!-- Specs Mini Grid -->
                                    <div class="grid grid-cols-2 gap-1.5 text-[10px] bg-slate-50 p-2 rounded-lg border border-slate-100 text-slate-600">
                                        <div class="truncate">CPU: <?php echo htmlspecialchars($item['cpu']); ?></div>
                                        <div class="truncate">RAM: <?php echo htmlspecialchars($item['ram']); ?></div>
                                        <div class="truncate">SSD: <?php echo htmlspecialchars($item['storage']); ?></div>
                                        <div class="truncate">GPU: <?php echo htmlspecialchars($item['gpu']); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="px-4 pb-4 pt-2 border-t border-slate-100 flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] text-slate-400 block">ราคา</span>
                                    <?php if ($pr['has_discount']): ?>
                                        <span class="text-[11px] text-slate-400 line-through font-mono block"><?php echo format_baht($item['price']); ?></span>
                                        <span class="text-base font-bold text-rose-600 font-mono"><?php echo format_baht($pr['final']); ?></span>
                                    <?php else: ?>
                                        <span class="text-base font-bold text-slate-900 font-mono"><?php echo format_baht($item['price']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex space-x-1.5">
                                    <a href="<?php echo base_url('product-detail.php?id=' . $item['id']); ?>" 
                                       class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-100 transition">
                                        สเปก
                                    </a>
                                    <?php if ($item['stock_quantity'] <= 0): ?>
                                        <span class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-400 text-xs font-semibold cursor-not-allowed">
                                            สินค้าหมด
                                        </span>
                                    <?php elseif (!is_logged_in()): ?>
                                        <a href="<?php echo base_url('login.php?redirect=' . urlencode(base_url('product-detail.php?id=' . $item['id']))); ?>" 
                                           class="px-3 py-1.5 rounded-lg bg-sky-500 hover:bg-sky-400 text-slate-950 text-xs font-semibold transition flex items-center space-x-1" title="เข้าสู่ระบบเพื่อสั่งซื้อ">
                                            <i data-lucide="log-in" class="w-3.5 h-3.5"></i>
                                            <span>สั่งซื้อ</span>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo base_url('cart.php?action=add&id=' . $item['id']); ?>" 
                                           class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold transition flex items-center space-x-1">
                                            <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i>
                                            <span>สั่งซื้อ</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
