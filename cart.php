<?php
$pageTitle = 'ตะกร้าสินค้าและการสั่งซื้อ (Shopping Cart)';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$db = get_db();
$user = current_user();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Compute cart subtotal helper
function compute_cart_subtotal() {
    $sub = 0;
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $sub += ($item['price'] ?? 0) * ($item['qty'] ?? 1);
        }
    }
    return $sub;
}

// Handle Add to cart via GET or POST
if (isset($_GET['action']) && $_GET['action'] === 'add') {
    $productId = (int)$_GET['id'];
    $qty = isset($_GET['qty']) ? max(1, (int)$_GET['qty']) : 1;

    if (!is_logged_in()) {
        set_flash('warning', 'กรุณาเข้าสู่ระบบก่อนเลือกซื้อหรือสั่งซื้อสินค้า');
        header("Location: " . base_url('login.php?redirect=' . urlencode(base_url('product-detail.php?id=' . $productId))));
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $prod = $stmt->fetch();

    if ($prod) {
        if ((int)$prod['stock_quantity'] <= 0) {
            set_flash('danger', 'ขออภัย สินค้ารุ่นนี้หมดสต็อกแล้ว ไม่สามารถสั่งซื้อได้');
            header("Location: " . base_url('product-detail.php?id=' . $productId));
            exit;
        }

        $currentInCart = isset($_SESSION['cart'][$productId]) ? (int)$_SESSION['cart'][$productId]['qty'] : 0;
        if ($currentInCart + $qty > (int)$prod['stock_quantity']) {
            set_flash('warning', "สินค้าคงเหลือในคลังเพียง {$prod['stock_quantity']} เครื่อง ไม่สามารถสั่งซื้อเกินจำนวนคงเหลือได้");
            header("Location: " . base_url('cart.php'));
            exit;
        }

        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId]['qty'] += $qty;
        } else {
            $pr = product_pricing($prod);
            $_SESSION['cart'][$productId] = [
                'id'             => $prod['id'],
                'model'          => $prod['model'],
                'brand'          => $prod['brand'],
                'price'          => $pr['final'],
                'image_url'      => $prod['image_url'],
                'item_condition' => $prod['item_condition'] ?? 'new',
                'qty'            => $qty
            ];
        }
        set_flash('success', "เพิ่ม {$prod['brand']} {$prod['model']} ลงตะกร้าแล้ว");
    }
    header("Location: " . base_url('cart.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $productId = (int)$_POST['product_id'];
    $qty = max(1, (int)($_POST['quantity'] ?? 1));

    if (!is_logged_in()) {
        set_flash('warning', 'กรุณาเข้าสู่ระบบก่อนเลือกซื้อหรือสั่งซื้อสินค้า');
        header("Location: " . base_url('login.php?redirect=' . urlencode(base_url('product-detail.php?id=' . $productId))));
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $prod = $stmt->fetch();

    if ($prod) {
        if ((int)$prod['stock_quantity'] <= 0) {
            set_flash('danger', 'ขออภัย สินค้ารุ่นนี้หมดสต็อกแล้ว ไม่สามารถสั่งซื้อได้');
            header("Location: " . base_url('product-detail.php?id=' . $productId));
            exit;
        }

        $currentInCart = isset($_SESSION['cart'][$productId]) ? (int)$_SESSION['cart'][$productId]['qty'] : 0;
        if ($currentInCart + $qty > (int)$prod['stock_quantity']) {
            set_flash('warning', "สินค้าคงเหลือในคลังเพียง {$prod['stock_quantity']} เครื่อง ไม่สามารถสั่งซื้อเกินจำนวนคงเหลือได้");
            header("Location: " . base_url('cart.php'));
            exit;
        }

        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId]['qty'] += $qty;
        } else {
            $pr = product_pricing($prod);
            $_SESSION['cart'][$productId] = [
                'id'             => $prod['id'],
                'model'          => $prod['model'],
                'brand'          => $prod['brand'],
                'price'          => $pr['final'],
                'image_url'      => $prod['image_url'],
                'item_condition' => $prod['item_condition'] ?? 'new',
                'qty'            => $qty
            ];
        }
        set_flash('success', "เพิ่ม {$prod['brand']} {$prod['model']} ลงตะกร้าแล้ว");
    }
    header("Location: " . base_url('cart.php'));
    exit;
}

// Handle Remove Item
if (isset($_GET['action']) && $_GET['action'] === 'remove') {
    $removeId = (int)$_GET['id'];
    if (isset($_SESSION['cart'][$removeId])) {
        unset($_SESSION['cart'][$removeId]);
        set_flash('info', 'นำสินค้าออกจากตะกร้าเรียบร้อยแล้ว');
    }
    header("Location: " . base_url('cart.php'));
    exit;
}

// Handle Update Quantity
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $pId => $newQty) {
            $pId = (int)$pId;
            $newQty = (int)$newQty;
            if ($newQty > 0 && isset($_SESSION['cart'][$pId])) {
                $_SESSION['cart'][$pId]['qty'] = $newQty;
            } elseif ($newQty <= 0) {
                unset($_SESSION['cart'][$pId]);
            }
        }
        set_flash('success', 'อัปเดตจำนวนสินค้าเรียบร้อยแล้ว');
    }
    header("Location: " . base_url('cart.php'));
    exit;
}

// Handle Apply Coupon (โค้ดส่วนลดโปรโมชั่น)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_coupon') {
    $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
    $subtotal = compute_cart_subtotal();

    if (empty($code)) {
        set_flash('warning', 'กรุณากรอกรหัสโค้ดส่วนลด');
    } elseif ($subtotal <= 0) {
        set_flash('warning', 'ยังไม่มีสินค้าในตะกร้า ไม่สามารถใช้โค้ดส่วนลดได้');
    } else {
        $cStmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND active = 1 LIMIT 1");
        $cStmt->execute([$code]);
        $coupon = $cStmt->fetch();

        if (!$coupon) {
            set_flash('danger', "ไม่พบคูปองส่วนลดรหัส \"{$code}\" หรือคูปองนี้ปิดใช้งานแล้ว");
        } elseif (!empty($coupon['expires_at']) && $coupon['expires_at'] < date('Y-m-d')) {
            set_flash('danger', "คูปองส่วนลด \"{$code}\" หมดอายุการใช้งานแล้วเมื่อ " . htmlspecialchars($coupon['expires_at']));
        } elseif ($subtotal < (float)$coupon['min_order']) {
            set_flash('warning', "คูปอง \"{$code}\" ใช้ได้เมื่อมียอดสั่งซื้อขั้นต่ำ " . format_baht($coupon['min_order']) . " ขึ้นไป (ยอดสั่งซื้อปัจจุบันของคุณ: " . format_baht($subtotal) . ")");
        } else {
            $_SESSION['applied_coupon'] = [
                'code'           => $coupon['code'],
                'description'    => $coupon['description'],
                'discount_type'  => $coupon['discount_type'],
                'discount_value' => (float)$coupon['discount_value'],
                'min_order'      => (float)$coupon['min_order']
            ];
            set_flash('success', "ใช้โค้ดส่วนลด \"{$coupon['code']}\" สำเร็จแล้ว!");
        }
    }
    header("Location: " . base_url('cart.php'));
    exit;
}

// Handle Remove Coupon
if (isset($_GET['action']) && $_GET['action'] === 'remove_coupon') {
    unset($_SESSION['applied_coupon']);
    set_flash('info', 'ยกเลิกโค้ดส่วนลดเรียบร้อยแล้ว');
    header("Location: " . base_url('cart.php'));
    exit;
}

// Compute Subtotal and Discount
$subtotal = compute_cart_subtotal();
$discountAmount = 0.00;
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;

if ($appliedCoupon && $subtotal > 0) {
    if ($subtotal >= $appliedCoupon['min_order']) {
        if ($appliedCoupon['discount_type'] === 'percent') {
            $discountAmount = round($subtotal * ($appliedCoupon['discount_value'] / 100), 2);
        } else {
            $discountAmount = min($subtotal, (float)$appliedCoupon['discount_value']);
        }
    } else {
        // Invalidate if subtotal dropped below min order
        unset($_SESSION['applied_coupon']);
        $appliedCoupon = null;
    }
}
$netTotal = max(0, $subtotal - $discountAmount);

// Fetch active coupons for quick-click display
$activeCoupons = [];
try {
    $activeCoupons = $db->query("SELECT * FROM coupons WHERE active = 1 AND (expires_at IS NULL OR expires_at >= CURRENT_DATE) ORDER BY min_order ASC LIMIT 4")->fetchAll();
} catch (Exception $e) {
    $activeCoupons = [];
}

// Handle Checkout
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    if (!is_logged_in()) {
        set_flash('warning', 'กรุณาเข้าสู่ระบบก่อนทำการสั่งซื้อ');
        header("Location: " . base_url('login.php?redirect=' . urlencode(base_url('cart.php'))));
        exit;
    }

    if (empty($_SESSION['cart'])) {
        $error = 'ไม่มีสินค้าในตะกร้า ไม่สามารถสั่งซื้อได้';
    } else {
        $cName     = trim($_POST['customer_name'] ?? '');
        $cPhone    = trim($_POST['customer_phone'] ?? '');
        $cEmail    = trim($_POST['customer_email'] ?? '');
        $cAddress  = trim($_POST['shipping_address'] ?? '');
        $payMethod = $_POST['payment_method'] ?? 'Transfer';
        $notes     = trim($_POST['notes'] ?? '');

        if (empty($cName) || empty($cPhone) || empty($cAddress)) {
            $error = 'กรุณากรอกชื่อ-นามสกุล, เบอร์โทรศัพท์ และที่อยู่จัดส่งให้ครบถ้วน';
        } else {
            // Check stock availability for all cart items before proceeding
            foreach ($_SESSION['cart'] as $item) {
                $chk = $db->prepare("SELECT stock_quantity, brand, model FROM products WHERE id = ?");
                $chk->execute([$item['id']]);
                $pData = $chk->fetch();
                $avail = (int)($pData['stock_quantity'] ?? 0);
                if ($avail <= 0) {
                    $error = "ขออภัย โน้ตบุ๊ก '{$item['brand']} {$item['model']}' หมดสต็อกแล้ว กรุณานำออกจากตะกร้าก่อนสั่งซื้อ";
                    break;
                }
                if ($avail < (int)$item['qty']) {
                    $error = "ขออภัย โน้ตบุ๊ก '{$item['brand']} {$item['model']}' คงเหลือในคลังเพียง {$avail} เครื่อง ไม่เพียงพอกับจำนวนที่ท่านสั่งซื้อ";
                    break;
                }
            }

            if (empty($error)) {
                $orderCode = 'ORD-' . date('Y') . '-' . str_pad(rand(10, 999), 3, '0', STR_PAD_LEFT);
                $userId = $user['id'] ?? null;
                $now = date('Y-m-d H:i:s');
                $couponCodeUsed = $appliedCoupon ? $appliedCoupon['code'] : null;

                // Insert Order with discount and coupon columns
                $stmt = $db->prepare("INSERT INTO orders (
                    order_code, user_id, customer_name, customer_phone, customer_email,
                    shipping_address, subtotal_amount, discount_amount, coupon_code, total_amount, 
                    payment_method, payment_status, order_status, sales_rep_id, notes, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'processing', 5, ?, ?)");
                
                $stmt->execute([
                    $orderCode, $userId, $cName, $cPhone, $cEmail,
                    $cAddress, $subtotal, $discountAmount, $couponCodeUsed, $netTotal,
                    $payMethod, $notes, $now
                ]);
                $orderId = $db->lastInsertId();

                // Insert items and decrease stock safely
                $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $stockLog = $db->prepare("INSERT INTO stock_transactions (type, item_type, item_id, item_name, quantity, reason, reference_code, performed_by, created_at) VALUES ('out', 'product', ?, ?, ?, 'Customer Online Order', ?, 5, ?)");

                foreach ($_SESSION['cart'] as $item) {
                    $itemSubtotal = $item['price'] * $item['qty'];
                    $itemStmt->execute([$orderId, $item['id'], $item['brand'] . ' ' . $item['model'], $item['price'], $item['qty'], $itemSubtotal]);
                    
                    // Decrease stock safely
                    $stmtCur = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                    $stmtCur->execute([$item['id']]);
                    $curStock = (int)$stmtCur->fetchColumn();
                    $newStock = max(0, $curStock - (int)$item['qty']);

                    $stmtUpd = $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                    $stmtUpd->execute([$newStock, $item['id']]);

                    // Log stock movement
                    $stockLog->execute([$item['id'], $item['brand'] . ' ' . $item['model'], $item['qty'], $orderCode, $now]);
                }

                // Clear Cart and Applied Coupon
                $_SESSION['cart'] = [];
                unset($_SESSION['cart']);
                unset($_SESSION['applied_coupon']);

                set_flash('success', 'สั่งซื้อสำเร็จเรียบร้อย! หมายเลขออเดอร์ของคุณคือ: ' . $orderCode);
                header("Location: " . base_url('order-success.php?code=' . urlencode($orderCode)));
                exit;
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-100 py-6 border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-slate-900">ตะกร้าสินค้าและการสั่งซื้อ</h1>
        <p class="text-xs text-slate-500 mt-0.5">ตรวจสอบรายการโน้ตบุ๊ก กรอกโค้ดส่วนลด และที่อยู่สำหรับจัดส่งสินค้า</p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center space-x-3">
            <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <?php if (empty($_SESSION['cart'])): ?>
        <div class="text-center py-16 bg-white rounded-2xl border border-slate-200 p-8 max-w-lg mx-auto shadow-xs">
            <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="shopping-bag" class="w-8 h-8"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900">ยังไม่มีสินค้าในตะกร้า</h3>
            <p class="text-xs text-slate-500 mt-1 mb-6">เลือกชมโน้ตบุ๊กทั้งมือหนึ่งป้ายแดง และมือสองคุณภาพผ่าน QC พร้อมโค้ดลดมากมาย</p>
            <div class="flex flex-col sm:flex-row justify-center gap-3">
                <a href="<?php echo base_url('products.php?condition=new'); ?>" class="inline-flex items-center justify-center space-x-1.5 px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-semibold shadow-xs transition">
                    <i data-lucide="sparkles" class="w-4 h-4"></i>
                    <span>โน้ตบุ๊กมือหนึ่ง</span>
                </a>
                <a href="<?php echo base_url('products.php?condition=used'); ?>" class="inline-flex items-center justify-center space-x-1.5 px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-semibold shadow-xs transition">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    <span>โน้ตบุ๊กมือสอง QC ผ่าน</span>
                </a>
            </div>
        </div>
    <?php else: ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Col 1: Items in Cart -->
            <div class="lg:col-span-7 space-y-4">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                            รายการโน้ตบุ๊กที่เลือก (<?php echo count($_SESSION['cart']); ?> รายการ)
                        </span>
                        <a href="<?php echo base_url('products.php'); ?>" class="text-xs text-sky-600 hover:underline flex items-center gap-1 font-semibold">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i> เลือกสินค้าเพิ่ม
                        </a>
                    </div>

                    <form method="POST" action="cart.php">
                        <input type="hidden" name="action" value="update">
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($_SESSION['cart'] as $pId => $item): ?>
                                <div class="p-4 sm:p-5 flex items-center space-x-4">
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                         class="w-16 h-16 sm:w-20 sm:h-20 object-cover rounded-xl border border-slate-200 flex-shrink-0 bg-slate-100">
                                    
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-bold text-sky-600 uppercase"><?php echo htmlspecialchars($item['brand']); ?></span>
                                            <?php echo render_condition_badge($item['item_condition'] ?? 'new'); ?>
                                        </div>
                                        <h4 class="text-sm font-bold text-slate-900 truncate mt-0.5"><?php echo htmlspecialchars($item['model']); ?></h4>
                                        <div class="text-xs font-mono font-semibold text-slate-700 mt-1"><?php echo format_baht($item['price']); ?></div>
                                    </div>

                                    <!-- Quantity Input -->
                                    <div class="flex items-center space-x-2">
                                        <input type="number" name="quantities[<?php echo $pId; ?>]" value="<?php echo $item['qty']; ?>" min="1" max="10" 
                                               class="w-16 px-2 py-1.5 border border-slate-300 rounded-lg text-center text-xs font-semibold focus:ring-sky-500">
                                        
                                        <a href="<?php echo base_url('cart.php?action=remove&id=' . $pId); ?>" 
                                           class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition" title="ลบสินค้า">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                            <button type="submit" class="px-4 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition">
                                อัปเดตจำนวนสินค้า
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Col 2: Summary + Coupon Code + Checkout Box -->
            <div class="lg:col-span-5 space-y-4">

                <!-- 1. Coupon Code Box (กล่องกรอกโค้ดส่วนลด) -->
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs space-y-3">
                    <div class="flex items-center space-x-2">
                        <div class="w-7 h-7 rounded-lg bg-pink-100 text-pink-700 flex items-center justify-center font-bold">
                            <i data-lucide="ticket-percent" class="w-4 h-4"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">โค้ดส่วนลดโปรโมชั่น (Promo Code)</h3>
                    </div>

                    <?php if ($appliedCoupon): ?>
                        <!-- Applied Coupon State -->
                        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-between">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold">
                                    <i data-lucide="check" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-bold text-xs bg-emerald-600 text-white px-2 py-0.5 rounded">
                                            <?php echo htmlspecialchars($appliedCoupon['code']); ?>
                                        </span>
                                        <span class="text-xs font-bold text-emerald-800">
                                            -<?php echo format_baht($discountAmount); ?>
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-emerald-700 mt-0.5"><?php echo htmlspecialchars($appliedCoupon['description'] ?? ''); ?></p>
                                </div>
                            </div>

                            <a href="<?php echo base_url('cart.php?action=remove_coupon'); ?>" 
                               class="text-xs font-semibold text-rose-600 hover:text-rose-800 hover:underline px-2 py-1" title="ยกเลิกการใช้โค้ด">
                                ยกเลิกโค้ด
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Coupon Input Form -->
                        <form method="POST" action="cart.php" class="flex gap-2">
                            <input type="hidden" name="action" value="apply_coupon">
                            <div class="relative flex-1">
                                <input type="text" name="coupon_code" id="couponInput" placeholder="กรอกโค้ดส่วนลด เช่น SAVE500" 
                                       class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs font-mono uppercase focus:ring-pink-500 focus:border-pink-500 pl-8">
                                <i data-lucide="tag" class="w-4 h-4 text-slate-400 absolute left-2.5 top-3"></i>
                            </div>
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-pink-600 hover:bg-pink-500 text-white font-bold text-xs shadow-xs transition flex items-center space-x-1">
                                <span>ใช้โค้ด</span>
                            </button>
                        </form>

                        <!-- Available Coupons Chips (คลิกเพื่อใส่โค้ดอัตโนมัติ) -->
                        <?php if (!empty($activeCoupons)): ?>
                            <div class="pt-2 border-t border-slate-100">
                                <p class="text-[11px] font-semibold text-slate-500 mb-1.5">โค้ดส่วนลดแนะนำที่ใช้งานได้ตอนนี้:</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <?php foreach ($activeCoupons as $ac): ?>
                                        <button type="button" 
                                                onclick="applyCouponChip('<?php echo htmlspecialchars($ac['code']); ?>')"
                                                class="px-2.5 py-1 rounded-lg border border-pink-200 bg-pink-50 hover:bg-pink-100 text-pink-800 text-[11px] font-mono font-bold flex items-center space-x-1 transition cursor-pointer"
                                                title="<?php echo htmlspecialchars($ac['description']); ?>">
                                            <span>🎫 <?php echo htmlspecialchars($ac['code']); ?></span>
                                            <span class="text-[10px] text-pink-600 font-sans">
                                                (<?php echo $ac['discount_type'] === 'percent' ? 'ลด ' . (int)$ac['discount_value'] . '%' : 'ลด ' . number_format($ac['discount_value']) . 'บ.'; ?>)
                                            </span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- 2. Order Summary & Checkout Box -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-6">
                    <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">
                        ข้อมูลการสั่งซื้อและชำระเงิน
                    </h2>

                    <!-- Price Summary Breakdown -->
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>ราคารวมสินค้า (<?php echo count($_SESSION['cart']); ?> รายการ):</span>
                            <span class="font-mono font-semibold text-slate-800"><?php echo format_baht($subtotal); ?></span>
                        </div>

                        <?php if ($discountAmount > 0): ?>
                            <div class="flex justify-between text-emerald-700 font-semibold bg-emerald-50/70 p-2 rounded-lg">
                                <span class="flex items-center gap-1">
                                    <i data-lucide="ticket" class="w-3.5 h-3.5"></i>
                                    โค้ดส่วนลด (<?php echo htmlspecialchars($appliedCoupon['code']); ?>):
                                </span>
                                <span class="font-mono text-emerald-800 font-bold">-<?php echo format_baht($discountAmount); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="flex justify-between text-slate-600">
                            <span>ค่าจัดส่ง (Kerry / ขนส่งด่วนพิเศษ):</span>
                            <span class="text-emerald-600 font-semibold">ฟรี (โปรโมชั่น)</span>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex justify-between items-center text-sm font-bold text-slate-900">
                            <span>ยอดชำระสุทธิ:</span>
                            <div class="text-right">
                                <?php if ($discountAmount > 0): ?>
                                    <span class="text-xs text-slate-400 line-through font-normal block font-mono"><?php echo format_baht($subtotal); ?></span>
                                <?php endif; ?>
                                <span class="font-mono text-2xl text-sky-600 font-extrabold"><?php echo format_baht($netTotal); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Checkout Customer Form -->
                    <?php if (!is_logged_in()): ?>
                        <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs space-y-3">
                            <div class="flex items-center space-x-2 font-bold text-sm">
                                <i data-lucide="info" class="w-4 h-4 text-amber-600 flex-shrink-0"></i>
                                <span>กรุณาเข้าสู่ระบบก่อนทำการสั่งซื้อ</span>
                            </div>
                            <p class="text-slate-600 leading-relaxed">
                                ท่านต้องเข้าสู่ระบบบัญชีลูกค้าก่อนยืนยันสั่งซื้อ เพื่อบันทึกคำสั่งซื้อ สิทธิ์การรับประกันศูนย์ และออกใบเสร็จ
                            </p>
                            <a href="<?php echo base_url('login.php?redirect=' . urlencode(base_url('cart.php'))); ?>" 
                               class="w-full py-3 px-4 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-bold text-xs shadow-sm transition flex items-center justify-center space-x-2">
                                <i data-lucide="log-in" class="w-4 h-4"></i>
                                <span>เข้าสู่ระบบเพื่อดำเนินการสั่งซื้อ</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="cart.php" class="space-y-4 pt-2">
                            <input type="hidden" name="action" value="checkout">

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    ชื่อ-นามสกุล ผู้รับ <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="customer_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required
                                       placeholder="เช่น นายสมชาย หมายมั่น"
                                       class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:ring-sky-500">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                                        เบอร์โทรศัพท์ <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="tel" name="customer_phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required
                                           placeholder="เช่น 081-234-5678"
                                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:ring-sky-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                                        อีเมล
                                    </label>
                                    <input type="email" name="customer_email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                           placeholder="mail@example.com"
                                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:ring-sky-500">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    ที่อยู่จัดส่งโดยละเอียด <span class="text-rose-500">*</span>
                                </label>
                                <textarea name="shipping_address" rows="2" required placeholder="บ้านเลขที่, ถนน, แขวง, เขต, จังหวัด, รหัสไปรษณีย์"
                                          class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:ring-sky-500"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    วิธีการชำระเงิน
                                </label>
                                <select name="payment_method" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:ring-sky-500">
                                    <option value="Transfer">โอนเงินผ่านธนาคาร / PromptPay QR</option>
                                    <option value="CreditCard">บัตรเครดิต / เดบิต (0% สูงสุด 10 เดือน)</option>
                                    <option value="CashOnDelivery">เก็บเงินปลายทาง (COD)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    หมายเหตุเพิ่มเติม (ถ้ามี)
                                </label>
                                <input type="text" name="notes" placeholder="เช่น ขอใบกำกับภาษี หรือจัดส่งช่วงบ่าย"
                                       class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:ring-sky-500">
                            </div>

                            <button type="submit" class="w-full py-3.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm shadow-md transition duration-150 flex items-center justify-center space-x-2">
                                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400"></i>
                                <span>ยืนยันการสั่งซื้อโน้ตบุ๊ก (ยอด <?php echo format_baht($netTotal); ?>)</span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    <?php endif; ?>
</div>

<script>
function applyCouponChip(code) {
    const input = document.getElementById('couponInput');
    if (input) {
        input.value = code;
        input.form.submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
