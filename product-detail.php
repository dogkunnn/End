<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = get_db();

$stmt = $db->prepare("SELECT p.*, c.name as category_name 
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      WHERE p.id = ? LIMIT 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'ไม่พบข้อมูลโน้ตบุ๊กรุ่นที่ต้องการ');
    header("Location: " . base_url('products.php'));
    exit;
}

$pageTitle = $product['brand'] . ' ' . $product['model'];

// Related products
$stmtRel = $db->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? LIMIT 3");
$stmtRel->execute([$product['category_id'], $product['id']]);
$related = $stmtRel->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs -->
<div class="bg-white border-b border-slate-200 py-3">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-xs text-slate-500 flex items-center space-x-2">
        <a href="<?php echo base_url('index.php'); ?>" class="hover:text-slate-900">หน้าแรก</a>
        <span>/</span>
        <a href="<?php echo base_url('products.php'); ?>" class="hover:text-slate-900">โน้ตบุ๊กทั้งหมด</a>
        <span>/</span>
        <a href="<?php echo base_url('products.php?category=' . $product['category_id']); ?>" class="hover:text-slate-900"><?php echo htmlspecialchars($product['category_name']); ?></a>
        <span>/</span>
        <span class="text-slate-900 font-medium truncate"><?php echo htmlspecialchars($product['model']); ?></span>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        
        <!-- Left: Product Image & Badges -->
        <div class="lg:col-span-6 space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs overflow-hidden">
                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($product['model']); ?>" 
                     class="w-full h-80 sm:h-96 object-cover rounded-xl">
            </div>

            <!-- Value props -->
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="p-3 bg-white rounded-xl border border-slate-200">
                    <i data-lucide="shield-check" class="w-5 h-5 mx-auto text-sky-600 mb-1"></i>
                    <div class="text-[11px] font-bold text-slate-800">ประกันศูนย์แท้</div>
                    <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars($product['warranty']); ?></div>
                </div>
                <div class="p-3 bg-white rounded-xl border border-slate-200">
                    <i data-lucide="check-circle-2" class="w-5 h-5 mx-auto text-emerald-600 mb-1"></i>
                    <div class="text-[11px] font-bold text-slate-800">ผ่าน QC 15 จุด</div>
                    <div class="text-[10px] text-slate-400">ฝ่ายผลิตตรวจเช็คสมบูรณ์</div>
                </div>
                <div class="p-3 bg-white rounded-xl border border-slate-200">
                    <i data-lucide="truck" class="w-5 h-5 mx-auto text-indigo-600 mb-1"></i>
                    <div class="text-[11px] font-bold text-slate-800">จัดส่งฟรีทั่วไทย</div>
                    <div class="text-[10px] text-slate-400">หรือรับที่ศูนย์บริการ</div>
                </div>
            </div>
        </div>

        <!-- Right: Pricing, Specs & Purchase -->
        <div class="lg:col-span-6 space-y-6">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-slate-900 text-white">
                        <?php echo htmlspecialchars($product['brand']); ?>
                    </span>
                    <?php echo render_condition_badge($product['item_condition'] ?? 'new'); ?>
                    <span class="px-2.5 py-1 rounded-md text-xs font-medium bg-sky-100 text-sky-800">
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </span>
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-100 text-emerald-800">
                            มีสินค้าพร้อมส่ง (คงเหลือ <?php echo $product['stock_quantity']; ?> เครื่อง)
                        </span>
                    <?php else: ?>
                        <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-rose-100 text-rose-800 inline-flex items-center space-x-1">
                            <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                            <span>สินค้าหมดชั่วคราว (Out of Stock)</span>
                        </span>
                    <?php endif; ?>
                </div>

                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">
                    <?php echo htmlspecialchars($product['model']); ?>
                </h1>

                <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </p>
            </div>

            <!-- Price Box -->
            <?php $pr = product_pricing($product); ?>
            <div class="p-5 rounded-2xl bg-slate-900 text-white flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 block">ราคาจำหน่ายพิเศษ (รวมภาษีแล้ว)</span>
                    <div class="flex items-baseline gap-2">
                        <?php if ($pr['has_discount']): ?>
                            <span class="text-3xl font-bold font-mono text-rose-400"><?php echo format_baht($pr['final']); ?></span>
                            <span class="text-sm text-slate-400 line-through font-mono"><?php echo format_baht($product['price']); ?></span>
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-rose-600 text-white"><?php echo $pr['label']; ?></span>
                        <?php else: ?>
                            <span class="text-3xl font-bold font-mono text-sky-400"><?php echo format_baht($product['price']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-right text-xs text-slate-400">
                    <div>ผ่อน 0% นานสูงสุด 10 เดือน</div>
                    <div class="text-emerald-400">ใช้โค้ดลดเพิ่มในหน้าตะกร้าได้</div>
                </div>
            </div>

            <!-- Purchase / Add to Cart Section -->
            <?php if ($product['stock_quantity'] <= 0): ?>
                <div class="space-y-3">
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center space-x-3">
                        <i data-lucide="slash" class="w-5 h-5 flex-shrink-0 text-rose-500"></i>
                        <div>
                            <span class="font-bold block text-sm text-rose-900">สินค้าหมดสต็อก (Sold Out)</span>
                            ขออภัยในความไม่สะดวก ขณะนี้สินค้าชิ้นนี้หมดคลังและปิดรับคำสั่งซื้อชั่วคราว
                        </div>
                    </div>
                    <button type="button" disabled class="w-full py-3.5 px-5 rounded-xl bg-slate-200 text-slate-400 font-bold text-sm cursor-not-allowed flex items-center justify-center space-x-2">
                        <i data-lucide="ban" class="w-4 h-4"></i>
                        <span>สินค้าหมด (ไม่สามารถสั่งซื้อได้)</span>
                    </button>
                </div>
            <?php elseif (!is_logged_in()): ?>
                <div class="space-y-3">
                    <div class="p-3.5 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 text-xs flex items-center space-x-2.5">
                        <i data-lucide="info" class="w-4 h-4 text-amber-600 flex-shrink-0"></i>
                        <span>ท่านสามารถดูรายละเอียดสเปกสินค้าได้เต็มที่ หากต้องการสั่งซื้อสินค้า กรุณาเข้าสู่ระบบก่อนทำรายการ</span>
                    </div>
                    <a href="<?php echo base_url('login.php?redirect=' . urlencode(base_url('product-detail.php?id=' . $product['id']))); ?>" 
                       class="w-full py-3.5 px-5 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-bold text-sm shadow-md transition flex items-center justify-center space-x-2">
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        <span>เข้าสู่ระบบเพื่อสั่งซื้อ</span>
                    </a>
                </div>
            <?php else: ?>
                <!-- Add to Cart Form -->
                <form method="POST" action="<?php echo base_url('cart.php'); ?>" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                    <div class="flex items-center space-x-3">
                        <div class="w-28">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">จำนวน</label>
                            <select name="quantity" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm font-semibold focus:ring-sky-500">
                                <?php for ($i = 1; $i <= min(5, $product['stock_quantity']); $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> เครื่อง</option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="flex-1 pt-5 flex space-x-3">
                            <button type="submit" class="flex-1 py-3 px-5 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-bold text-sm shadow-md transition flex items-center justify-center space-x-2">
                                <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                                <span>เพิ่มลงในตะกร้า</span>
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Technical Specifications Table -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                <div class="px-5 py-3.5 bg-slate-100 border-b border-slate-200 font-bold text-xs uppercase text-slate-700 tracking-wider flex items-center">
                    <i data-lucide="cpu" class="w-4 h-4 mr-2 text-sky-600"></i>
                    ตารางข้อมูลจำเพาะเชิงเทคนิค (Technical Specifications)
                </div>
                <div class="divide-y divide-slate-100 text-xs">
                    <div class="grid grid-cols-3 py-3 px-5">
                        <span class="text-slate-500 font-medium">หน่วยประมวลผล (CPU)</span>
                        <span class="col-span-2 text-slate-900 font-semibold"><?php echo htmlspecialchars($product['cpu']); ?></span>
                    </div>
                    <div class="grid grid-cols-3 py-3 px-5">
                        <span class="text-slate-500 font-medium">กราฟิกการ์ด (GPU)</span>
                        <span class="col-span-2 text-slate-900 font-semibold"><?php echo htmlspecialchars($product['gpu']); ?></span>
                    </div>
                    <div class="grid grid-cols-3 py-3 px-5">
                        <span class="text-slate-500 font-medium">หน่วยความจำ (RAM)</span>
                        <span class="col-span-2 text-slate-900 font-semibold"><?php echo htmlspecialchars($product['ram']); ?></span>
                    </div>
                    <div class="grid grid-cols-3 py-3 px-5">
                        <span class="text-slate-500 font-medium">พื้นที่จัดเก็บ (Storage)</span>
                        <span class="col-span-2 text-slate-900 font-semibold"><?php echo htmlspecialchars($product['storage']); ?></span>
                    </div>
                    <div class="grid grid-cols-3 py-3 px-5">
                        <span class="text-slate-500 font-medium">หน้าจอแสดงผล (Display)</span>
                        <span class="col-span-2 text-slate-900 font-semibold"><?php echo htmlspecialchars($product['display']); ?></span>
                    </div>
                    <div class="grid grid-cols-3 py-3 px-5">
                        <span class="text-slate-500 font-medium">ระบบปฏิบัติการ (OS)</span>
                        <span class="col-span-2 text-slate-900 font-semibold"><?php echo htmlspecialchars($product['os']); ?></span>
                    </div>
                    <div class="grid grid-cols-3 py-3 px-5">
                        <span class="text-slate-500 font-medium">การรับประกัน (Warranty)</span>
                        <span class="col-span-2 text-slate-900 font-semibold"><?php echo htmlspecialchars($product['warranty']); ?></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($related)): ?>
        <div class="mt-16 pt-10 border-t border-slate-200">
            <h2 class="text-xl font-bold text-slate-900 mb-6">โน้ตบุ๊กในหมวดหมู่เดียวกัน</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <?php foreach ($related as $rel): ?>
                    <a href="<?php echo base_url('product-detail.php?id=' . $rel['id']); ?>" 
                       class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs hover-card-lift block group">
                        <div class="h-40 rounded-xl overflow-hidden bg-slate-100 mb-3">
                            <img src="<?php echo htmlspecialchars($rel['image_url']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        </div>
                        <span class="text-[10px] font-bold text-sky-600 uppercase"><?php echo htmlspecialchars($rel['brand']); ?></span>
                        <h4 class="text-xs font-bold text-slate-900 line-clamp-1"><?php echo htmlspecialchars($rel['model']); ?></h4>
                        <div class="text-sm font-bold text-slate-900 font-mono mt-1"><?php echo format_baht($rel['price']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
