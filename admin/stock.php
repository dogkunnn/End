<?php
$pageTitle = 'ฝ่ายคลังสินค้าและอะไหล่ (Stock & Inventory Suite)';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_department('stock');
$db = get_db();
$currentUser = current_user();

// Handle Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 0. Intake Product from Production & QC
    if ($action === 'intake_qc') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $prodId = (int)($_POST['product_id'] ?? 0);
        
        // Fetch task
        $tStmt = $db->prepare("SELECT * FROM production_tasks WHERE id = ?");
        $tStmt->execute([$taskId]);
        $task = $tStmt->fetch();

        // Condition + optional second-hand price from the intake form
        $condition  = in_array($_POST['item_condition'] ?? '', ['new', 'used', 'refurbished']) ? $_POST['item_condition'] : 'new';
        $usedPrice  = (float)($_POST['used_price'] ?? 0);

        if ($task && in_array($task['status'], ['qc_passed', 'ready_to_deliver'])) {
            $targetProdId = $prodId > 0 ? $prodId : (int)($task['product_id'] ?? 0);
            $now = date('Y-m-d H:i:s');
            $performedBy = $currentUser['id'] ?? 4;

            if ($targetProdId > 0) {
                // Increment product stock
                $updProd = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + 1 WHERE id = ?");
                $updProd->execute([$targetProdId]);

                // Mark condition + serial + link to QC task; set a second-hand price if provided
                if ($condition !== 'new' && $usedPrice > 0) {
                    $updMeta = $db->prepare("UPDATE products SET item_condition = ?, serial_number = ?, qc_task_id = ?, price = ? WHERE id = ?");
                    $updMeta->execute([$condition, $task['serial_number'], $taskId, $usedPrice, $targetProdId]);
                } else {
                    $updMeta = $db->prepare("UPDATE products SET item_condition = ?, serial_number = ?, qc_task_id = ? WHERE id = ?");
                    $updMeta->execute([$condition, $task['serial_number'], $taskId, $targetProdId]);
                }

                // Update task status to stocked
                $updTask = $db->prepare("UPDATE production_tasks SET status = 'stocked' WHERE id = ?");
                $updTask->execute([$taskId]);

                // Log into stock transactions
                $condLabel = $condition === 'used' ? ' [สินค้ามือสอง]' : ($condition === 'refurbished' ? ' [Refurbished]' : '');
                $logStmt = $db->prepare("INSERT INTO stock_transactions (type, item_type, item_id, item_name, quantity, reason, reference_code, performed_by, created_at)
                                         VALUES ('in', 'product', ?, ?, 1, ?, ?, ?, ?)");
                $logStmt->execute([
                    $targetProdId,
                    $task['notebook_name'],
                    'รับเข้าคลังหลังผ่าน QC จากฝ่ายผลิต' . $condLabel . ' (Task: ' . $task['task_code'] . ')',
                    $task['task_code'],
                    $performedBy,
                    $now
                ]);

                set_flash('success', "รับเครื่อง [{$task['notebook_name']}] เข้าสต็อกเรียบร้อยแล้ว (+1 เครื่อง)" . $condLabel);
            } else {
                set_flash('warning', 'กรุณาเลือกรายการโน้ตบุ๊กที่ต้องการเพิ่มจำนวนสต็อก หรือเปิดเป็นโน้ตบุ๊กรุ่นใหม่');
            }
        } else {
            set_flash('danger', 'ไม่พบงานผลิต หรือสถานะงานนี้ยังไม่ผ่าน QC');
        }
        header("Location: " . base_url('admin/stock.php?tab=qc_intake'));
        exit;
    }

    // 0.2 Set / clear per-product discount
    if ($action === 'set_discount') {
        $id    = (int)($_POST['id'] ?? 0);
        $dtype = in_array($_POST['discount_type'] ?? '', ['none', 'percent', 'fixed']) ? $_POST['discount_type'] : 'none';
        $dval  = (float)($_POST['discount_value'] ?? 0);
        if ($dtype === 'none' || $dval <= 0) { $dtype = 'none'; $dval = 0; }
        if ($id > 0) {
            $upd = $db->prepare("UPDATE products SET discount_type = ?, discount_value = ? WHERE id = ?");
            $upd->execute([$dtype, $dval, $id]);
            set_flash('success', $dtype === 'none' ? 'ยกเลิกส่วนลดสินค้าเรียบร้อยแล้ว' : 'ตั้งส่วนลดสินค้าเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/stock.php?tab=laptops'));
        exit;
    }

    // 0.1 Mark QC Task as Stocked / Handled
    if ($action === 'mark_qc_stocked') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            $upd = $db->prepare("UPDATE production_tasks SET status = 'stocked' WHERE id = ?");
            $upd->execute([$taskId]);
            set_flash('success', 'ทำเครื่องหมายว่าจัดการรับเข้าคลังเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/stock.php?tab=qc_intake'));
        exit;
    }

    // 1. Quick Adjust Laptop Stock
    if ($action === 'adjust_laptop_stock') {
        $id    = (int)($_POST['id'] ?? 0);
        $delta = (int)($_POST['delta'] ?? 0);
        if ($id > 0 && $delta !== 0) {
            $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $current = (int)$stmt->fetchColumn();
            $newQty = max(0, $current + $delta);
            $upd = $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $upd->execute([$newQty, $id]);
            set_flash('success', 'ปรับปรุงจำนวนสต็อกโน้ตบุ๊กเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/stock.php?tab=laptops'));
        exit;
    }

    // 2. Add New Laptop Product
    if ($action === 'add_laptop') {
        $brand       = trim($_POST['brand'] ?? '');
        $model       = trim($_POST['model'] ?? '');
        $cat_id      = (int)($_POST['category_id'] ?? 1);
        $price       = (float)($_POST['price'] ?? 0);
        $cost_price  = (float)($_POST['cost_price'] ?? 0);
        $stock_qty   = (int)($_POST['stock_quantity'] ?? 1);
        $cpu         = trim($_POST['cpu'] ?? '');
        $gpu         = trim($_POST['gpu'] ?? '');
        $ram         = trim($_POST['ram'] ?? '');
        $storage     = trim($_POST['storage'] ?? '');
        $display     = trim($_POST['display'] ?? '');
        $os          = trim($_POST['os'] ?? 'Windows 11 Home');
        $warranty    = trim($_POST['warranty'] ?? '2 Years');
        $img         = trim($_POST['image_url'] ?? 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80');
        $desc        = trim($_POST['description'] ?? '');

        if (!empty($brand) && !empty($model) && $price > 0) {
            $now = date('Y-m-d H:i:s');
            $sql = "INSERT INTO products (category_id, brand, model, price, cost_price, stock_quantity, cpu, gpu, ram, storage, display, os, warranty, image_url, description, is_featured, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'in_stock', ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$cat_id, $brand, $model, $price, $cost_price, $stock_qty, $cpu, $gpu, $ram, $storage, $display, $os, $warranty, $img, $desc, $now]);
            set_flash('success', 'เพิ่มโน้ตบุ๊กใหม่เข้าคลังสินค้าสำเร็จ');
        }
        header("Location: " . base_url('admin/stock.php?tab=laptops'));
        exit;
    }

    // 3. Quick Adjust Spare Part Stock
    if ($action === 'adjust_part_stock') {
        $id    = (int)($_POST['id'] ?? 0);
        $delta = (int)($_POST['delta'] ?? 0);
        if ($id > 0 && $delta !== 0) {
            $stmt = $db->prepare("SELECT stock_quantity FROM spare_parts WHERE id = ?");
            $stmt->execute([$id]);
            $current = (int)$stmt->fetchColumn();
            $newQty = max(0, $current + $delta);
            $upd = $db->prepare("UPDATE spare_parts SET stock_quantity = ? WHERE id = ?");
            $upd->execute([$newQty, $id]);
            set_flash('success', 'ปรับปรุงสต็อกอะไหล่เรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/stock.php?tab=parts'));
        exit;
    }

    // 4. Add New Spare Part
    if ($action === 'add_part') {
        $part_code   = trim($_POST['part_code'] ?? 'PRT-' . rand(1000, 9999));
        $part_name   = trim($_POST['part_name'] ?? '');
        $category    = trim($_POST['category'] ?? 'General');
        $cost_price  = (float)($_POST['cost_price'] ?? 0);
        $sell_price  = (float)($_POST['sell_price'] ?? 0);
        $stock_qty   = (int)($_POST['stock_quantity'] ?? 0);
        $min_thresh  = (int)($_POST['min_threshold'] ?? 3);
        $supplier    = trim($_POST['supplier'] ?? '');

        if (!empty($part_name) && $sell_price > 0) {
            $now = date('Y-m-d H:i:s');
            $sql = "INSERT INTO spare_parts (part_code, part_name, category, cost_price, sell_price, stock_quantity, min_threshold, supplier, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$part_code, $part_name, $category, $cost_price, $sell_price, $stock_qty, $min_thresh, $supplier, $now]);
            set_flash('success', 'เพิ่มรายการอะไหล่เข้าคลังเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/stock.php?tab=parts'));
        exit;
    }

    // 5. Delete Laptop
    if ($action === 'delete_laptop') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $del = $db->prepare("DELETE FROM products WHERE id = ?");
            $del->execute([$id]);
            set_flash('success', 'ลบโน้ตบุ๊กออกจากคลังสินค้าแล้ว');
        }
        header("Location: " . base_url('admin/stock.php?tab=laptops'));
        exit;
    }

    // 5.1 Toggle Featured on Storefront
    if ($action === 'toggle_featured') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE products SET is_featured = CASE WHEN is_featured = 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
            set_flash('success', 'อัปเดตสถานะสินค้าแนะนำหน้าร้านเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/stock.php?tab=laptops'));
        exit;
    }

    // 5.2 Toggle Visibility on Storefront
    if ($action === 'toggle_visibility') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE products SET status = CASE WHEN status = 'hidden' THEN 'in_stock' ELSE 'hidden' END WHERE id = ?")->execute([$id]);
            set_flash('success', 'อัปเดตสถานะการแสดงผลหน้าร้านเรียบร้อยแล้ว');
        }
        header("Location: " . base_url('admin/stock.php?tab=laptops'));
        exit;
    }

    // 6. Edit Laptop (Direct Stock Number & Information)
    if ($action === 'edit_laptop') {
        $id          = (int)($_POST['id'] ?? 0);
        $brand       = trim($_POST['brand'] ?? '');
        $model       = trim($_POST['model'] ?? '');
        $cat_id      = (int)($_POST['category_id'] ?? 1);
        $price       = (float)($_POST['price'] ?? 0);
        $cost_price  = (float)($_POST['cost_price'] ?? 0);
        $stock_qty   = max(0, (int)($_POST['stock_quantity'] ?? 0));
        $cpu         = trim($_POST['cpu'] ?? '');
        $gpu         = trim($_POST['gpu'] ?? '');
        $ram         = trim($_POST['ram'] ?? '');
        $storage     = trim($_POST['storage'] ?? '');
        $display     = trim($_POST['display'] ?? '');
        $os          = trim($_POST['os'] ?? 'Windows 11 Home');
        $warranty    = trim($_POST['warranty'] ?? '2 Years');
        $img         = trim($_POST['image_url'] ?? '');
        $desc        = trim($_POST['description'] ?? '');
        $condition   = in_array($_POST['item_condition'] ?? '', ['new', 'used', 'refurbished']) ? $_POST['item_condition'] : 'new';
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $status      = in_array($_POST['status'] ?? '', ['in_stock', 'low_stock', 'out_of_stock', 'hidden']) ? $_POST['status'] : 'in_stock';
        $sn          = trim($_POST['serial_number'] ?? '');

        if ($id > 0 && !empty($brand) && !empty($model) && $price > 0) {
            $sql = "UPDATE products SET category_id = ?, brand = ?, model = ?, price = ?, cost_price = ?, stock_quantity = ?, cpu = ?, gpu = ?, ram = ?, storage = ?, display = ?, os = ?, warranty = ?, image_url = ?, description = ?, item_condition = ?, is_featured = ?, status = ?, serial_number = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$cat_id, $brand, $model, $price, $cost_price, $stock_qty, $cpu, $gpu, $ram, $storage, $display, $os, $warranty, $img, $desc, $condition, $is_featured, $status, $sn, $id]);
            set_flash('success', 'บันทึกการแก้ไขข้อมูลและปรับสต็อกโน้ตบุ๊กเรียบร้อยแล้ว (จำนวน: ' . $stock_qty . ' เครื่อง)');
        }
        header("Location: " . base_url('admin/stock.php?tab=laptops'));
        exit;
    }

    // 7. Edit Spare Part (Direct Stock Number & Information)
    if ($action === 'edit_part') {
        $id          = (int)($_POST['id'] ?? 0);
        $part_code   = trim($_POST['part_code'] ?? '');
        $part_name   = trim($_POST['part_name'] ?? '');
        $category    = trim($_POST['category'] ?? 'General');
        $cost_price  = (float)($_POST['cost_price'] ?? 0);
        $sell_price  = (float)($_POST['sell_price'] ?? 0);
        $stock_qty   = max(0, (int)($_POST['stock_quantity'] ?? 0));
        $min_thresh  = (int)($_POST['min_threshold'] ?? 3);
        $supplier    = trim($_POST['supplier'] ?? '');

        if ($id > 0 && !empty($part_name) && $sell_price > 0) {
            $sql = "UPDATE spare_parts SET part_code = ?, part_name = ?, category = ?, cost_price = ?, sell_price = ?, stock_quantity = ?, min_threshold = ?, supplier = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$part_code, $part_name, $category, $cost_price, $sell_price, $stock_qty, $min_thresh, $supplier, $id]);
            set_flash('success', 'บันทึกการแก้ไขข้อมูลและปรับสต็อกอะไหล่เรียบร้อยแล้ว (จำนวน: ' . $stock_qty . ' ชิ้น)');
        }
        header("Location: " . base_url('admin/stock.php?tab=parts'));
        exit;
    }

    // 8. Delete Spare Part
    if ($action === 'delete_part') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $del = $db->prepare("DELETE FROM spare_parts WHERE id = ?");
            $del->execute([$id]);
            set_flash('success', 'ลบรายการอะไหล่ออกจากคลังแล้ว');
        }
        header("Location: " . base_url('admin/stock.php?tab=parts'));
        exit;
    }
}

$tab = $_GET['tab'] ?? 'laptops';

// Fetch Laptops
$laptops = $db->query("SELECT p.*, c.name as category_name 
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       ORDER BY p.id DESC")->fetchAll();

// Fetch Spare Parts
$spareParts = $db->query("SELECT * FROM spare_parts ORDER BY id DESC")->fetchAll();

// Fetch Categories for dropdown
$categories = $db->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

// Fetch Production tasks that passed QC and need stock management
$qcPassedTasks = $db->query("SELECT pt.*, p.id as matched_product_id, p.model as matched_product_model, p.brand as matched_product_brand, p.stock_quantity as current_stock, p.price as current_price
                             FROM production_tasks pt 
                             LEFT JOIN products p ON pt.product_id = p.id 
                             WHERE pt.status IN ('qc_passed', 'ready_to_deliver') 
                             ORDER BY pt.completed_at DESC, pt.id DESC")->fetchAll();
$qcPassedCount = count($qcPassedTasks);

// KPI counts
$totalLaptopsCount = $db->query("SELECT COALESCE(SUM(stock_quantity), 0) FROM products")->fetchColumn();
$totalPartsUnits   = $db->query("SELECT COALESCE(SUM(stock_quantity), 0) FROM spare_parts")->fetchColumn();
$lowStockLaptopsCount = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 3")->fetchColumn();
$lowStockPartsCount = $db->query("SELECT COUNT(*) FROM spare_parts WHERE stock_quantity <= min_threshold")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex min-h-[calc(100vh-140px)]">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Workspace Area -->
    <div class="flex-1 p-6 lg:p-8 bg-slate-50 overflow-y-auto">
        
        <!-- Header Banner -->
        <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-slate-200 gap-4 mb-6">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                        <i data-lucide="boxes" class="w-3.5 h-3.5 inline mr-1"></i> แผนกคลังสินค้าและอะไหล่ (Stock & Warehouse)
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">จัดการสต็อกโน้ตบุ๊ก & อะไหล่ชิ้นส่วนซ่อม</h1>
                <p class="text-xs text-slate-500">
                    ตรวจเช็คจำนวนคงเหลือ เพิ่มสินค้าใหม่ ปรับสต็อกด่วน และระบบแจ้งเตือนสินค้าใกล้หมด
                </p>
            </div>

            <div class="flex items-center space-x-2">
                <button onclick="openModal('modalAddLaptop')" class="px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold shadow-sm flex items-center space-x-1.5 transition">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>เพิ่มโน้ตบุ๊กใหม่</span>
                </button>
                <button onclick="openModal('modalAddPart')" class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-sm flex items-center space-x-1.5 transition">
                    <i data-lucide="plus" class="w-4 h-4 text-amber-400"></i>
                    <span>เพิ่มอะไหล่ใหม่</span>
                </button>
            </div>
        </div>

        <!-- 4 Quick KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>โน้ตบุ๊กพร้อมขายในคลัง</span>
                    <span class="p-2 rounded-xl bg-amber-50 text-amber-600"><i data-lucide="laptop" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo $totalLaptopsCount; ?> เครื่อง</div>
                <div class="text-[11px] text-slate-400 mt-1">ทั้งหมด <?php echo count($laptops); ?> รุ่นสินค้า</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>อะไหล่ซ่อมในสต็อกทั้งหมด</span>
                    <span class="p-2 rounded-xl bg-blue-50 text-blue-600"><i data-lucide="cpu" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-2"><?php echo $totalPartsUnits; ?> ชิ้น</div>
                <div class="text-[11px] text-slate-400 mt-1">ทั้งหมด <?php echo count($spareParts); ?> รายการอะไหล่</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span>แจ้งเตือนสินค้าใกล้หมด (Low Stock)</span>
                    <span class="p-2 rounded-xl bg-rose-50 text-rose-600"><i data-lucide="alert-triangle" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono text-rose-600 mt-2"><?php echo ($lowStockLaptopsCount + $lowStockPartsCount); ?> รายการ</div>
                <div class="text-[11px] text-rose-600 font-medium mt-1">โน้ตบุ๊ก <?php echo $lowStockLaptopsCount; ?> + อะไหล่ <?php echo $lowStockPartsCount; ?></div>
            </div>

            <div class="bg-white p-5 rounded-2xl border <?php echo $qcPassedCount > 0 ? 'border-emerald-300 ring-2 ring-emerald-100 bg-emerald-50/20' : 'border-slate-200'; ?> shadow-xs">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                    <span class="<?php echo $qcPassedCount > 0 ? 'text-emerald-700 font-bold' : ''; ?>">สินค้าผ่าน QC รอรับเข้าคลัง</span>
                    <span class="p-2 rounded-xl bg-emerald-100 text-emerald-700"><i data-lucide="clipboard-check" class="w-5 h-5"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono <?php echo $qcPassedCount > 0 ? 'text-emerald-600' : 'text-slate-900'; ?> mt-2"><?php echo $qcPassedCount; ?> รายการ</div>
                <div class="text-[11px] text-slate-500 mt-1 flex items-center justify-between">
                    <span>พร้อมเปิดขาย/เพิ่มสต็อก</span>
                    <?php if ($qcPassedCount > 0): ?>
                        <a href="<?php echo base_url('admin/stock.php?tab=qc_intake'); ?>" class="text-emerald-700 font-bold hover:underline">จัดการ &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($qcPassedCount > 0 && $tab !== 'qc_intake'): ?>
            <!-- Prominent Banner for QC Passed Items Waiting for Stock -->
            <div class="mb-6 p-4 rounded-2xl bg-gradient-to-r from-emerald-50 via-teal-50 to-white border-2 border-emerald-300 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex items-start space-x-3.5">
                    <div class="p-2.5 rounded-xl bg-emerald-600 text-white shadow-xs flex-shrink-0 animate-bounce">
                        <i data-lucide="package-check" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <h2 class="text-sm font-bold text-slate-900">มีโน้ตบุ๊กผ่านการตรวจ QC แล้ว <?php echo $qcPassedCount; ?> รายการ ที่ยังไม่ได้จัดการนำเข้าสต็อก!</h2>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-200 text-emerald-900">ตรวจแล้ว 100%</span>
                        </div>
                        <p class="text-xs text-slate-600 mt-1">
                            ฝ่ายผลิตได้ตรวจสอบคุณภาพและส่งมอบเครื่องแล้ว กรุณากดรับเข้าสต็อกเพื่อเปิดให้ลูกค้าสั่งซื้อ หรือบันทึกเข้าคลังสินค้า
                        </p>
                    </div>
                </div>
                <a href="<?php echo base_url('admin/stock.php?tab=qc_intake'); ?>" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm transition whitespace-nowrap flex items-center space-x-1.5">
                    <i data-lucide="arrow-right-circle" class="w-4 h-4"></i>
                    <span>จัดการสินค้าผ่าน QC (<?php echo $qcPassedCount; ?>)</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- Tabs Selector -->
        <div class="flex items-center space-x-2 border-b border-slate-200 mb-6">
            <a href="<?php echo base_url('admin/stock.php?tab=laptops'); ?>" class="pb-3 px-4 text-xs font-bold border-b-2 transition flex items-center space-x-2 <?php echo $tab === 'laptops' ? 'border-amber-600 text-amber-600' : 'border-transparent text-slate-500 hover:text-slate-900'; ?>">
                <i data-lucide="laptop" class="w-4 h-4"></i>
                <span>คลังโน้ตบุ๊ก (Laptop Inventory)</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-100 text-amber-800"><?php echo count($laptops); ?></span>
            </a>
            <a href="<?php echo base_url('admin/stock.php?tab=parts'); ?>" class="pb-3 px-4 text-xs font-bold border-b-2 transition flex items-center space-x-2 <?php echo $tab === 'parts' ? 'border-amber-600 text-amber-600' : 'border-transparent text-slate-500 hover:text-slate-900'; ?>">
                <i data-lucide="wrench" class="w-4 h-4"></i>
                <span>คลังอะไหล่และชิ้นส่วนซ่อม (Spare Parts)</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-blue-100 text-blue-800"><?php echo count($spareParts); ?></span>
            </a>
            <a href="<?php echo base_url('admin/stock.php?tab=qc_intake'); ?>" class="pb-3 px-4 text-xs font-bold border-b-2 transition flex items-center space-x-2 <?php echo $tab === 'qc_intake' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-900'; ?>">
                <i data-lucide="clipboard-check" class="w-4 h-4 text-emerald-600"></i>
                <span>สินค้าผ่าน QC รอจัดการ (QC Intake)</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] <?php echo $qcPassedCount > 0 ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-100 text-slate-600'; ?>"><?php echo $qcPassedCount; ?></span>
            </a>
        </div>

        <?php if ($tab === 'laptops'): ?>
            <!-- Tab 1: Laptops Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4 font-semibold">รูป / แบรนด์ / รุ่นโน้ตบุ๊ก</th>
                                <th class="py-3 px-4 font-semibold">หมวดหมู่</th>
                                <th class="py-3 px-4 font-semibold">สเปกหลัก (CPU / RAM / SSD)</th>
                                <th class="py-3 px-4 font-semibold">ราคาขาย</th>
                                <th class="py-3 px-4 font-semibold text-center">คงเหลือในสต็อก</th>
                                <th class="py-3 px-4 font-semibold text-center">แสดงหน้าร้าน</th>
                                <th class="py-3 px-4 font-semibold text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php foreach ($laptops as $p): ?>
                                <tr class="hover:bg-slate-50/80 transition <?php echo ($p['status'] ?? '') === 'hidden' ? 'bg-slate-50/50 opacity-75' : ''; ?>">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center space-x-3">
                                            <img src="<?php echo htmlspecialchars($p['image_url']); ?>" alt="" class="w-12 h-12 rounded-xl object-cover border border-slate-200 bg-slate-100 flex-shrink-0">
                                            <div>
                                                <div class="text-[11px] font-bold text-amber-700 uppercase"><?php echo htmlspecialchars($p['brand']); ?></div>
                                                <div class="font-bold text-slate-900 flex items-center gap-1.5"><?php echo htmlspecialchars($p['model']); ?> <?php echo render_condition_badge($p['item_condition'] ?? 'new'); ?></div>
                                                <?php if (!empty($p['serial_number'])): ?>
                                                    <div class="text-[10px] text-slate-400 font-mono">S/N: <?php echo htmlspecialchars($p['serial_number']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-slate-600">
                                        <?php echo htmlspecialchars($p['category_name'] ?? 'ทั่วไป'); ?>
                                    </td>
                                    <td class="py-3 px-4 max-w-xs text-slate-600">
                                        <div><?php echo htmlspecialchars($p['cpu']); ?></div>
                                        <div class="text-[11px] text-slate-400"><?php echo htmlspecialchars($p['ram']); ?> | <?php echo htmlspecialchars($p['storage']); ?></div>
                                    </td>
                                    <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                        <?php $pr = product_pricing($p); ?>
                                        <?php if ($pr['has_discount']): ?>
                                            <div class="text-[11px] text-slate-400 line-through font-normal"><?php echo format_baht($p['price']); ?></div>
                                            <div class="text-rose-600"><?php echo format_baht($pr['final']); ?></div>
                                            <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700"><?php echo $pr['label']; ?></span>
                                        <?php else: ?>
                                            <?php echo format_baht($p['price']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="inline-flex items-center space-x-2">
                                            <!-- -1 -->
                                            <form method="POST" action="stock.php" class="inline">
                                                <input type="hidden" name="action" value="adjust_laptop_stock">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <input type="hidden" name="delta" value="-1">
                                                <button type="submit" class="w-6 h-6 rounded-md bg-slate-100 hover:bg-rose-100 text-slate-700 hover:text-rose-700 font-bold flex items-center justify-center transition" <?php echo $p['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>-</button>
                                            </form>

                                            <span class="font-mono font-bold px-2.5 py-0.5 rounded-full text-xs 
                                                <?php 
                                                    if ($p['stock_quantity'] <= 0) echo 'bg-rose-100 text-rose-800';
                                                    elseif ($p['stock_quantity'] <= 3) echo 'bg-amber-100 text-amber-800';
                                                    else echo 'bg-emerald-100 text-emerald-800';
                                                ?>">
                                                <?php echo $p['stock_quantity']; ?>
                                            </span>

                                            <!-- +1 -->
                                            <form method="POST" action="stock.php" class="inline">
                                                <input type="hidden" name="action" value="adjust_laptop_stock">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <input type="hidden" name="delta" value="1">
                                                <button type="submit" class="w-6 h-6 rounded-md bg-slate-100 hover:bg-emerald-100 text-slate-700 hover:text-emerald-700 font-bold flex items-center justify-center transition">+</button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center space-x-1.5">
                                            <!-- Toggle Featured -->
                                            <form method="POST" action="stock.php" class="inline" title="<?php echo !empty($p['is_featured']) ? 'คลิกเพื่อยกเลิกสินค้าแนะนำ' : 'คลิกเพื่อตั้งเป็นสินค้าแนะนำหน้าร้าน'; ?>">
                                                <input type="hidden" name="action" value="toggle_featured">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="p-1.5 rounded-lg border transition <?php echo !empty($p['is_featured']) ? 'bg-amber-100 border-amber-300 text-amber-700 shadow-xs' : 'bg-slate-100 border-slate-200 text-slate-400 hover:text-amber-500'; ?>">
                                                    <i data-lucide="star" class="w-3.5 h-3.5 <?php echo !empty($p['is_featured']) ? 'fill-amber-400 text-amber-500' : ''; ?>"></i>
                                                </button>
                                            </form>

                                            <!-- Toggle Visibility -->
                                            <form method="POST" action="stock.php" class="inline" title="<?php echo ($p['status'] ?? '') === 'hidden' ? 'สินค้านี้ถูกซ่อนอยู่ (คลิกเพื่อแสดงหน้าร้าน)' : 'แสดงอยู่หน้าร้าน (คลิกเพื่อซ่อน)'; ?>">
                                                <input type="hidden" name="action" value="toggle_visibility">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="p-1.5 rounded-lg border transition <?php echo ($p['status'] ?? '') === 'hidden' ? 'bg-rose-50 border-rose-200 text-rose-600' : 'bg-emerald-50 border-emerald-200 text-emerald-600 hover:bg-emerald-100'; ?>">
                                                    <i data-lucide="<?php echo ($p['status'] ?? '') === 'hidden' ? 'eye-off' : 'eye'; ?>" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <div class="text-[10px] mt-0.5">
                                            <?php if (!empty($p['is_featured'])): ?><span class="text-amber-600 font-bold">★ แนะนำ</span><?php endif; ?>
                                            <?php if (($p['status'] ?? '') === 'hidden'): ?><span class="text-rose-600 font-bold block">🚫 ซ่อนอยู่</span><?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center space-x-1">
                                            <button type="button"
                                                    onclick='openEditLaptopModal(<?php echo json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                                    class="p-1.5 rounded-lg text-amber-700 bg-amber-50 hover:bg-amber-100 transition flex items-center space-x-1 text-xs font-semibold" title="แก้ไขข้อมูล/สต็อก">
                                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                                <span>แก้ไข</span>
                                            </button>
                                            <button type="button"
                                                    onclick='openDiscountModal(<?php echo json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                                    class="p-1.5 rounded-lg text-rose-700 bg-rose-50 hover:bg-rose-100 transition flex items-center space-x-1 text-xs font-semibold" title="ตั้งส่วนลด">
                                                <i data-lucide="ticket-percent" class="w-3.5 h-3.5"></i>
                                                <span>ส่วนลด</span>
                                            </button>
                                            <form method="POST" action="stock.php" class="inline" onsubmit="return confirm('ยืนยันลบสินค้านี้ออกจากคลังหรือไม่?');">
                                                <input type="hidden" name="action" value="delete_laptop">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="ลบสินค้า">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($tab === 'parts'): ?>
            <!-- Tab 2: Spare Parts Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4 font-semibold">รหัสอะไหล่ / ชื่อชิ้นส่วน</th>
                                <th class="py-3 px-4 font-semibold">ประเภท</th>
                                <th class="py-3 px-4 font-semibold">ราคาทุน</th>
                                <th class="py-3 px-4 font-semibold">ราคาซ่อม/ขาย</th>
                                <th class="py-3 px-4 font-semibold text-center">จำนวนในสต็อก</th>
                                <th class="py-3 px-4 font-semibold">ผู้จำหน่าย (Supplier)</th>
                                <th class="py-3 px-4 font-semibold text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php foreach ($spareParts as $part): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4">
                                        <div class="font-mono text-xs font-bold text-blue-700"><?php echo htmlspecialchars($part['part_code']); ?></div>
                                        <div class="font-bold text-slate-900 mt-0.5"><?php echo htmlspecialchars($part['part_name']); ?></div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                                            <?php echo htmlspecialchars($part['category']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-slate-500">
                                        <?php echo format_baht($part['cost_price']); ?>
                                    </td>
                                    <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                        <?php echo format_baht($part['sell_price']); ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="inline-flex items-center space-x-2">
                                            <form method="POST" action="stock.php" class="inline">
                                                <input type="hidden" name="action" value="adjust_part_stock">
                                                <input type="hidden" name="id" value="<?php echo $part['id']; ?>">
                                                <input type="hidden" name="delta" value="-1">
                                                <button type="submit" class="w-6 h-6 rounded-md bg-slate-100 hover:bg-rose-100 text-slate-700 font-bold flex items-center justify-center transition">-</button>
                                            </form>

                                            <span class="font-mono font-bold px-2.5 py-0.5 rounded-full text-xs 
                                                <?php 
                                                    if ($part['stock_quantity'] <= $part['min_threshold']) echo 'bg-rose-100 text-rose-800';
                                                    else echo 'bg-blue-100 text-blue-800';
                                                ?>">
                                                <?php echo $part['stock_quantity']; ?>
                                            </span>

                                            <form method="POST" action="stock.php" class="inline">
                                                <input type="hidden" name="action" value="adjust_part_stock">
                                                <input type="hidden" name="id" value="<?php echo $part['id']; ?>">
                                                <input type="hidden" name="delta" value="1">
                                                <button type="submit" class="w-6 h-6 rounded-md bg-slate-100 hover:bg-emerald-100 text-slate-700 font-bold flex items-center justify-center transition">+</button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-slate-500">
                                        <?php echo htmlspecialchars($part['supplier'] ?? '-'); ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center space-x-1">
                                            <button type="button" 
                                                    onclick='openEditPartModal(<?php echo json_encode($part, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' 
                                                    class="p-1.5 rounded-lg text-blue-700 bg-blue-50 hover:bg-blue-100 transition flex items-center space-x-1 text-xs font-semibold" title="แก้ไขข้อมูล/สต็อก">
                                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                                <span>แก้ไข</span>
                                            </button>
                                            <form method="POST" action="stock.php" class="inline" onsubmit="return confirm('ยืนยันลบอะไหล่นี้ออกจากคลังหรือไม่?');">
                                                <input type="hidden" name="action" value="delete_part">
                                                <input type="hidden" name="id" value="<?php echo $part['id']; ?>">
                                                <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="ลบอะไหล่">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($tab === 'qc_intake'): ?>
            <!-- Tab 3: QC Passed Items Awaiting Intake Table -->
            <div class="space-y-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 flex items-center">
                                <i data-lucide="clipboard-check" class="w-3.5 h-3.5 mr-1"></i> ฝ่ายผลิต & ตรวจสอบคุณภาพส่งมอบ
                            </span>
                            <span class="text-xs text-slate-400">|</span>
                            <span class="text-xs text-slate-500">รอฝ่ายคลังตรวจรับและนำเข้าสต็อกจำหน่าย</span>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900 mt-1">สินค้าผ่าน QC รอจัดการนำเข้าสต็อก (Pending QC Intake)</h2>
                        <p class="text-xs text-slate-500">เมื่อฝ่ายผลิตทดสอบ QC 100% เรียบร้อยแล้ว รายการจะแสดงที่นี่เพื่อให้ฝ่ายคลังตรวจเช็คและกดรับเข้าสต็อกสินค้าพร้อมขาย</p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <span class="px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold">
                            รอดำเนินการ: <?php echo $qcPassedCount; ?> รายการ
                        </span>
                    </div>
                </div>

                <?php if ($qcPassedCount === 0): ?>
                    <div class="text-center py-16 bg-white rounded-2xl border border-slate-200 p-8">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3 shadow-xs">
                            <i data-lucide="check-check" class="w-7 h-7"></i>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">ไม่มีสินค้าค้างรอจัดการ</h3>
                        <p class="text-xs text-slate-500 mt-1">โน้ตบุ๊กทุกเครื่องที่ผ่านการตรวจ QC ถูกรับเข้าสต็อกคลังสินค้าเรียบร้อยแล้วทั้งหมด</p>
                        <a href="<?php echo base_url('admin/stock.php?tab=laptops'); ?>" class="mt-4 inline-flex items-center space-x-1.5 px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition">
                            <i data-lucide="laptop" class="w-4 h-4"></i>
                            <span>กลับไปดูคลังโน้ตบุ๊ก</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                                    <tr>
                                        <th class="py-3 px-4 font-semibold">รหัสงาน / วันที่ QC เสร็จ</th>
                                        <th class="py-3 px-4 font-semibold">ชื่อโน้ตบุ๊ก & Serial Number</th>
                                        <th class="py-3 px-4 font-semibold">รายละเอียดงาน & ผล QC</th>
                                        <th class="py-3 px-4 font-semibold">สินค้าในคลังที่ตรงกัน</th>
                                        <th class="py-3 px-4 font-semibold text-center">จัดการนำเข้าสต็อก</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <?php foreach ($qcPassedTasks as $task): ?>
                                        <tr class="hover:bg-emerald-50/30 transition">
                                            <td class="py-3 px-4">
                                                <span class="font-mono font-bold text-slate-900"><?php echo htmlspecialchars($task['task_code']); ?></span>
                                                <div class="text-[10px] text-slate-400 mt-0.5">
                                                    <?php echo $task['completed_at'] ? date('d/m/Y H:i', strtotime($task['completed_at'])) : date('d/m/Y H:i', strtotime($task['created_at'])); ?>
                                                </div>
                                                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                                                    <i data-lucide="check" class="w-3 h-3 mr-0.5"></i> ผ่าน QC แล้ว
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="font-bold text-slate-900"><?php echo htmlspecialchars($task['notebook_name']); ?></div>
                                                <?php if (!empty($task['serial_number'])): ?>
                                                    <div class="font-mono text-[10px] text-indigo-700 font-semibold mt-0.5">
                                                        S/N: <?php echo htmlspecialchars($task['serial_number']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="text-[10px] text-slate-500"><?php echo htmlspecialchars($task['task_type']); ?></span>
                                            </td>
                                            <td class="py-3 px-4 max-w-xs">
                                                <div class="text-[11px] text-slate-600 line-clamp-2"><?php echo htmlspecialchars($task['spec_upgrade_details'] ?: $task['notes'] ?: '-'); ?></div>
                                                <?php if (!empty($task['notes'])): ?>
                                                    <div class="text-[10px] text-emerald-700 italic mt-0.5">โน้ตช่าง: <?php echo htmlspecialchars($task['notes']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4">
                                                <?php if (!empty($task['matched_product_id'])): ?>
                                                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                                                        <div class="text-[11px] font-bold text-slate-900"><?php echo htmlspecialchars($task['matched_product_brand'] . ' ' . $task['matched_product_model']); ?></div>
                                                        <div class="text-[10px] text-slate-500 mt-0.5">
                                                            สต็อกปัจจุบัน: <strong class="text-amber-600"><?php echo (int)$task['current_stock']; ?></strong> เครื่อง | ราคา: <?php echo format_baht($task['current_price']); ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-[11px] text-amber-600 font-medium bg-amber-50 px-2 py-1 rounded-lg border border-amber-200 block">
                                                        ยังไม่ได้จับคู่สินค้า (เลือกสินค้าด้านขวา)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <div class="flex flex-col sm:flex-row items-center justify-center gap-1.5">
                                                    <!-- Intake directly to matched product -->
                                                    <?php if (!empty($task['matched_product_id'])): ?>
                                                        <button type="button"
                                                            onclick='openIntakeModal(<?php echo json_encode($task, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                                            class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-xs transition flex items-center space-x-1 whitespace-nowrap">
                                                            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                                                            <span>รับเข้าสต็อก</span>
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- Select which product to link to -->
                                                        <form method="POST" action="stock.php" class="flex items-center space-x-1">
                                                            <input type="hidden" name="action" value="intake_qc">
                                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                            <input type="hidden" name="item_condition" value="used">
                                                            <select name="product_id" class="px-2 py-1 rounded-lg border border-slate-300 text-[11px] max-w-[140px]" required>
                                                                <option value="">-- เลือกรุ่นในคลัง --</option>
                                                                <?php foreach ($laptops as $lp): ?>
                                                                    <option value="<?php echo $lp['id']; ?>"><?php echo htmlspecialchars($lp['brand'] . ' ' . $lp['model']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-xs transition">
                                                                รับเข้า
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <!-- Mark Handled -->
                                                    <form method="POST" action="stock.php" class="inline" onsubmit="return confirm('ทำเครื่องหมายว่าจัดการรับเข้าคลังเรียบร้อยแล้วหรือไม่?');">
                                                        <input type="hidden" name="action" value="mark_qc_stocked">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        <button type="submit" class="px-2.5 py-1.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 text-xs font-medium transition" title="บันทึกว่าจัดการแล้ว">
                                                            จัดการแล้ว
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Modal 1: Add Laptop -->
<div id="modalAddLaptop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="laptop" class="w-5 h-5 text-amber-600"></i>
                <span>เพิ่มสินค้าโน้ตบุ๊กรุ่นใหม่</span>
            </h3>
            <button onclick="closeModal('modalAddLaptop')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="stock.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="add_laptop">
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">แบรนด์ (Brand) <span class="text-rose-500">*</span></label>
                    <input type="text" name="brand" required placeholder="เช่น ASUS, Lenovo, Acer, Apple" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ชื่อรุ่น (Model) <span class="text-rose-500">*</span></label>
                    <input type="text" name="model" required placeholder="เช่น ROG Strix G16 (2025)" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-4 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">หมวดหมู่</label>
                    <select name="category_id" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ราคาทุน (บาท)</label>
                    <input type="number" step="any" name="cost_price" placeholder="เช่น 31000" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ราคาขาย (บาท) <span class="text-rose-500">*</span></label>
                    <input type="number" step="any" name="price" required placeholder="เช่น 35990" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">จำนวนสต็อก <span class="text-rose-500">*</span></label>
                    <input type="number" name="stock_quantity" value="5" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">หน่วยประมวลผล (CPU)</label>
                    <input type="text" name="cpu" placeholder="เช่น Intel Core i7-14650HX" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">การ์ดจอ (GPU)</label>
                    <input type="text" name="gpu" placeholder="เช่น NVIDIA GeForce RTX 4060 8GB" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">RAM</label>
                    <input type="text" name="ram" placeholder="เช่น 16GB DDR5 5600MHz" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ความจุ SSD</label>
                    <input type="text" name="storage" placeholder="เช่น 1TB PCIe 4.0 NVMe M.2" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">หน้าจอแสดงผล (Display)</label>
                <input type="text" name="display" placeholder="เช่น 16.0" FHD+ 165Hz IPS" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">URL รูปภาพสินค้า</label>
                <input type="url" name="image_url" value="https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=800&q=80" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">คำอธิบายสินค้า</label>
                <textarea name="description" rows="2" placeholder="จุดเด่นของเครื่อง..." class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500"></textarea>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalAddLaptop')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-medium shadow-sm">บันทึกโน้ตบุ๊กใหม่</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Add Spare Part -->
<div id="modalAddPart" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="tool" class="w-5 h-5 text-blue-600"></i>
                <span>เพิ่มรายการอะไหล่ซ่อมใหม่</span>
            </h3>
            <button onclick="closeModal('modalAddPart')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="stock.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="add_part">
            
            <div>
                <label class="block font-semibold text-slate-700 mb-1">รหัสอะไหล่ (Part Code) <span class="text-rose-500">*</span></label>
                <input type="text" name="part_code" required placeholder="เช่น PRT-RAM-D5-32" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500 font-mono">
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">ชื่ออะไหล่ <span class="text-rose-500">*</span></label>
                <input type="text" name="part_name" required placeholder="เช่น RAM DDR5 32GB 5600MHz Kingston Fury" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ประเภท</label>
                    <select name="category" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500">
                        <option value="RAM">RAM</option>
                        <option value="SSD">SSD / Storage</option>
                        <option value="Screen">หน้าจอ (Screen Panel)</option>
                        <option value="Battery">แบตเตอรี่ (Battery)</option>
                        <option value="Keyboard">คีย์บอร์ด (Keyboard)</option>
                        <option value="Cooling Fan">พัดลมระบายความร้อน</option>
                        <option value="Thermal Paste">ซิลิโคนความร้อน</option>
                        <option value="Adapter">อะแดปเตอร์สายชาร์จ</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">จำนวนสต็อก <span class="text-rose-500">*</span></label>
                    <input type="number" name="stock_quantity" value="10" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ราคาทุน (บาท)</label>
                    <input type="number" step="any" name="cost_price" placeholder="เช่น 1200" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ราคาคิดค่าซ่อม/ขาย (บาท) <span class="text-rose-500">*</span></label>
                    <input type="number" step="any" name="sell_price" required placeholder="เช่น 1850" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500 font-mono">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">ผู้จัดจำหน่าย (Supplier)</label>
                <input type="text" name="supplier" placeholder="เช่น Synnex, Ingram Micro, Advice" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500">
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalAddPart')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-medium shadow-sm">บันทึกอะไหล่ใหม่</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Edit Laptop Modal (แก้ไขข้อมูลและระบุจำนวนสต็อกโดยตรง) -->
<div id="modalEditLaptop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="edit" class="w-5 h-5 text-amber-600"></i>
                <span>แก้ไขข้อมูลและระบุจำนวนสต็อกโน้ตบุ๊ก</span>
            </h3>
            <button onclick="closeModal('modalEditLaptop')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="stock.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="edit_laptop">
            <input type="hidden" name="id" id="editLaptopId">
            
            <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-between">
                <div>
                    <label class="block font-bold text-amber-900 text-xs mb-0.5">⚡ จำนวนสินค้าคงเหลือในสต็อก (กรอกตัวเลขได้ทันที)</label>
                    <p class="text-[11px] text-amber-700">สามารถพิมพ์ตัวเลขสต็อกใหม่ได้โดยตรง ไม่ต้องกดบวกลบทีละอัน</p>
                </div>
                <div class="w-32">
                    <input type="number" min="0" name="stock_quantity" id="editLaptopStock" required class="w-full px-3 py-2 rounded-xl border border-amber-300 bg-white font-mono font-bold text-amber-900 text-base focus:ring-amber-500 text-center">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">แบรนด์ (Brand) <span class="text-rose-500">*</span></label>
                    <input type="text" name="brand" id="editLaptopBrand" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ชื่อรุ่น (Model) <span class="text-rose-500">*</span></label>
                    <input type="text" name="model" id="editLaptopModel" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">หมวดหมู่</label>
                    <select name="category_id" id="editLaptopCategory" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ราคาทุน (บาท)</label>
                    <input type="number" step="any" name="cost_price" id="editLaptopCostPrice" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ราคาขาย (บาท) <span class="text-rose-500">*</span></label>
                    <input type="number" step="any" name="price" id="editLaptopPrice" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500 font-mono font-bold">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">หน่วยประมวลผล (CPU)</label>
                    <input type="text" name="cpu" id="editLaptopCpu" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">การ์ดจอ (GPU)</label>
                    <input type="text" name="gpu" id="editLaptopGpu" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">RAM</label>
                    <input type="text" name="ram" id="editLaptopRam" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ความจุ SSD</label>
                    <input type="text" name="storage" id="editLaptopStorage" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">หน้าจอ (Display)</label>
                    <input type="text" name="display" id="editLaptopDisplay" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ระบบปฏิบัติการ (OS)</label>
                    <input type="text" name="os" id="editLaptopOs" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">การรับประกัน</label>
                    <input type="text" name="warranty" id="editLaptopWarranty" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">URL รูปภาพสินค้า</label>
                <input type="url" name="image_url" id="editLaptopImage" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">สภาพสินค้า</label>
                    <select name="item_condition" id="editLaptopCondition" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                        <option value="new">มือหนึ่ง (Brand New)</option>
                        <option value="used">มือสอง ผ่าน QC (Used)</option>
                        <option value="refurbished">Refurbished</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">การแสดงผลหน้าร้าน</label>
                    <select name="status" id="editLaptopStatus" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500">
                        <option value="in_stock">👁️ แสดงปกติ (พร้อมจำหน่าย)</option>
                        <option value="low_stock">⚠️ แสดง (สินค้าใกล้หมด)</option>
                        <option value="hidden">🚫 ซ่อนจากหน้าร้าน (ไม่แสดง)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Serial Number</label>
                    <input type="text" name="serial_number" id="editLaptopSerial" placeholder="เช่น SN-ROG-001" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500 font-mono">
                </div>
                <div class="sm:col-span-3 pt-1">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="is_featured" id="editLaptopFeatured" value="1" class="rounded text-amber-600 focus:ring-amber-500 w-4 h-4">
                        <span class="font-semibold text-slate-800">⭐ แสดงเป็นสินค้าแนะนำหน้าร้าน (Featured on Homepage)</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">คำอธิบายสินค้า</label>
                <textarea name="description" id="editLaptopDesc" rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-amber-500"></textarea>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalEditLaptop')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-medium shadow-sm">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 4: Edit Spare Part Modal (แก้ไขข้อมูลและระบุสต็อกอะไหล่โดยตรง) -->
<div id="modalEditPart" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="edit" class="w-5 h-5 text-blue-600"></i>
                <span>แก้ไขข้อมูลและระบุจำนวนสต็อกอะไหล่</span>
            </h3>
            <button onclick="closeModal('modalEditPart')" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="stock.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="edit_part">
            <input type="hidden" name="id" id="editPartId">
            
            <div class="p-3 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-between">
                <div>
                    <label class="block font-bold text-blue-900 text-xs mb-0.5">⚡ จำนวนสต็อกคงเหลือ (กรอกตัวเลขได้ทันที)</label>
                    <p class="text-[11px] text-blue-700">พิมพ์ตัวเลขอัปเดตจำนวนชิ้นในสต็อกได้โดยตรง</p>
                </div>
                <div class="w-32">
                    <input type="number" min="0" name="stock_quantity" id="editPartStock" required class="w-full px-3 py-2 rounded-xl border border-blue-300 bg-white font-mono font-bold text-blue-900 text-base focus:ring-blue-500 text-center">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">รหัสอะไหล่ (Part Code) <span class="text-rose-500">*</span></label>
                <input type="text" name="part_code" id="editPartCode" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500 font-mono">
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">ชื่ออะไหล่ <span class="text-rose-500">*</span></label>
                <input type="text" name="part_name" id="editPartName" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ประเภท</label>
                    <select name="category" id="editPartCategory" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500">
                        <option value="RAM">RAM</option>
                        <option value="SSD">SSD / Storage</option>
                        <option value="Screen">หน้าจอ (Screen Panel)</option>
                        <option value="Battery">แบตเตอรี่ (Battery)</option>
                        <option value="Keyboard">คีย์บอร์ด (Keyboard)</option>
                        <option value="Cooling Fan">พัดลมระบายความร้อน</option>
                        <option value="Thermal Paste">ซิลิโคนความร้อน</option>
                        <option value="Adapter">อะแดปเตอร์สายชาร์จ</option>
                        <option value="General">ทั่วไป (General)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">จุดเตือนสต็อกต่ำ (Min)</label>
                    <input type="number" name="min_threshold" id="editPartMinThreshold" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ราคาทุน (บาท)</label>
                    <input type="number" step="any" name="cost_price" id="editPartCostPrice" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500 font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ราคาคิดค่าซ่อม/ขาย (บาท) <span class="text-rose-500">*</span></label>
                    <input type="number" step="any" name="sell_price" id="editPartSellPrice" required class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500 font-mono font-bold">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">ผู้จัดจำหน่าย (Supplier)</label>
                <input type="text" name="supplier" id="editPartSupplier" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-blue-500">
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalEditPart')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-medium shadow-sm">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: QC Intake (set condition + second-hand price) -->
<div id="modalIntake" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="package-check" class="w-5 h-5 text-emerald-600"></i><span>รับเครื่องผ่าน QC เข้าสต็อก</span>
            </h3>
            <button onclick="closeModal('modalIntake')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <form method="POST" action="stock.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="intake_qc">
            <input type="hidden" name="task_id" id="intakeTaskId">
            <input type="hidden" name="product_id" id="intakeProductId">

            <div class="p-3 rounded-xl bg-emerald-50/60 border border-emerald-100">
                <div class="font-bold text-slate-900" id="intakeProductName"></div>
                <div class="text-[11px] text-slate-500 mt-0.5" id="intakeSerial"></div>
                <div class="text-[11px] text-slate-500">ราคาปัจจุบันในคลัง: <span id="intakeCurrentPrice" class="font-mono"></span></div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">สภาพสินค้า</label>
                <select name="item_condition" id="intakeCondition" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-emerald-500" onchange="toggleUsedPrice()">
                    <option value="new">สินค้าใหม่ (New)</option>
                    <option value="used" selected>มือสอง ผ่าน QC (Used)</option>
                    <option value="refurbished">Refurbished (ปรับสภาพ)</option>
                </select>
            </div>

            <div id="usedPriceWrap">
                <label class="block font-semibold text-slate-700 mb-1">ราคาขายมือสอง (บาท)</label>
                <input type="number" step="any" min="0" name="used_price" id="intakeUsedPrice" placeholder="เว้นว่าง = ใช้ราคาเดิมในคลัง" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-emerald-500 font-mono">
                <p class="text-[10px] text-slate-400 mt-1">* ตั้งราคาสำหรับเครื่องมือสองที่ผ่าน QC จะอัปเดตราคาสินค้ารุ่นนี้</p>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalIntake')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-medium shadow-sm">รับเข้าสต็อก (+1)</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Set Product Discount -->
<div id="modalDiscount" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                <i data-lucide="ticket-percent" class="w-5 h-5 text-rose-600"></i><span>ตั้งส่วนลดสินค้า</span>
            </h3>
            <button onclick="closeModal('modalDiscount')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <form method="POST" action="stock.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="set_discount">
            <input type="hidden" name="id" id="discId">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <div class="font-bold text-slate-900" id="discProductName"></div>
                <div class="text-[11px] text-slate-500">ราคาเต็ม: <span id="discFullPrice" class="font-mono"></span></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">ประเภทส่วนลด</label>
                    <select name="discount_type" id="discType" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-rose-500" onchange="calcDiscPreview()">
                        <option value="none">ไม่มีส่วนลด</option>
                        <option value="percent">เปอร์เซ็นต์ (%)</option>
                        <option value="fixed">จำนวนเงิน (บาท)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">มูลค่า</label>
                    <input type="number" step="any" min="0" name="discount_value" id="discValue" value="0" oninput="calcDiscPreview()" class="w-full px-3 py-2 rounded-xl border border-slate-300 focus:ring-rose-500 font-mono">
                </div>
            </div>
            <div class="p-3 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-between font-bold">
                <span class="text-slate-600">ราคาหลังลด:</span>
                <span id="discPreview" class="text-sm font-mono text-rose-700">฿0.00</span>
            </div>
            <div class="pt-3 flex items-center justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalDiscount')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-medium shadow-sm">บันทึกส่วนลด</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

var _discFull = 0;
function openDiscountModal(p) {
    _discFull = parseFloat(p.price) || 0;
    document.getElementById('discId').value = p.id;
    document.getElementById('discProductName').innerText = (p.brand || '') + ' ' + (p.model || '');
    document.getElementById('discFullPrice').innerText = '฿' + _discFull.toLocaleString('th-TH', {minimumFractionDigits: 2});
    document.getElementById('discType').value = (p.discount_type && p.discount_type !== 'none') ? p.discount_type : 'none';
    document.getElementById('discValue').value = parseFloat(p.discount_value) || 0;
    calcDiscPreview();
    openModal('modalDiscount');
}
function calcDiscPreview() {
    var type = document.getElementById('discType').value;
    var val = parseFloat(document.getElementById('discValue').value) || 0;
    var disc = 0;
    if (type === 'percent') disc = _discFull * (val / 100);
    else if (type === 'fixed') disc = val;
    disc = Math.min(disc, _discFull);
    var final = Math.max(0, _discFull - disc);
    document.getElementById('discPreview').innerText = '฿' + final.toLocaleString('th-TH', {minimumFractionDigits: 2});
}

function openIntakeModal(task) {
    document.getElementById('intakeTaskId').value = task.id;
    document.getElementById('intakeProductId').value = task.matched_product_id || '';
    document.getElementById('intakeProductName').innerText = (task.matched_product_brand || '') + ' ' + (task.matched_product_model || task.notebook_name || '');
    document.getElementById('intakeSerial').innerText = task.serial_number ? ('S/N: ' + task.serial_number) : '';
    var cp = parseFloat(task.current_price) || 0;
    document.getElementById('intakeCurrentPrice').innerText = '฿' + cp.toLocaleString('th-TH', {minimumFractionDigits: 2});
    document.getElementById('intakeCondition').value = 'used';
    document.getElementById('intakeUsedPrice').value = '';
    toggleUsedPrice();
    openModal('modalIntake');
}
function toggleUsedPrice() {
    var cond = document.getElementById('intakeCondition').value;
    document.getElementById('usedPriceWrap').style.display = (cond === 'new') ? 'none' : 'block';
}

function openEditLaptopModal(p) {
    document.getElementById('editLaptopId').value = p.id;
    document.getElementById('editLaptopStock').value = p.stock_quantity;
    document.getElementById('editLaptopBrand').value = p.brand || '';
    document.getElementById('editLaptopModel').value = p.model || '';
    document.getElementById('editLaptopCategory').value = p.category_id || 1;
    document.getElementById('editLaptopCostPrice').value = p.cost_price || 0;
    document.getElementById('editLaptopPrice').value = p.price || 0;
    document.getElementById('editLaptopCpu').value = p.cpu || '';
    document.getElementById('editLaptopGpu').value = p.gpu || '';
    document.getElementById('editLaptopRam').value = p.ram || '';
    document.getElementById('editLaptopStorage').value = p.storage || '';
    document.getElementById('editLaptopDisplay').value = p.display || '';
    document.getElementById('editLaptopOs').value = p.os || '';
    document.getElementById('editLaptopWarranty').value = p.warranty || '';
    document.getElementById('editLaptopImage').value = p.image_url || '';
    document.getElementById('editLaptopDesc').value = p.description || '';
    document.getElementById('editLaptopCondition').value = p.item_condition || 'new';
    document.getElementById('editLaptopStatus').value = p.status || 'in_stock';
    document.getElementById('editLaptopSerial').value = p.serial_number || '';
    document.getElementById('editLaptopFeatured').checked = (parseInt(p.is_featured) === 1);
    openModal('modalEditLaptop');
}

function openEditPartModal(part) {
    document.getElementById('editPartId').value = part.id;
    document.getElementById('editPartStock').value = part.stock_quantity;
    document.getElementById('editPartCode').value = part.part_code || '';
    document.getElementById('editPartName').value = part.part_name || '';
    document.getElementById('editPartCategory').value = part.category || 'General';
    document.getElementById('editPartMinThreshold').value = part.min_threshold || 3;
    document.getElementById('editPartCostPrice').value = part.cost_price || 0;
    document.getElementById('editPartSellPrice').value = part.sell_price || 0;
    document.getElementById('editPartSupplier').value = part.supplier || '';
    openModal('modalEditPart');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
