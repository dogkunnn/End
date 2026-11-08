<?php
// ========================================================
// TechNote Pro - Helper Functions & Utilities
// ========================================================

/**
 * Format money in Thai Baht
 */
function format_baht($amount) {
    return '฿' . number_format((float)$amount, 2);
}

/**
 * Format date in clean Thai/readable format
 */
function format_datetime($dt) {
    if (!$dt) return '-';
    $time = strtotime($dt);
    $months = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    $d = date('j', $time);
    $m = $months[(int)date('n', $time)];
    $y = (int)date('Y', $time) + 543;
    $hm = date('H:i', $time);
    return "{$d} {$m} {$y} {$hm} น.";
}

function format_date($dt) {
    if (!$dt) return '-';
    $time = strtotime($dt);
    $months = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    $d = date('j', $time);
    $m = $months[(int)date('n', $time)];
    $y = (int)date('Y', $time) + 543;
    return "{$d} {$m} {$y}";
}

/**
 * Compute effective price after a per-product discount.
 * Returns [final_price, discount_amount, has_discount, label]
 */
function product_pricing($product) {
    $price = (float)($product['price'] ?? 0);
    $type  = $product['discount_type'] ?? 'none';
    $value = (float)($product['discount_value'] ?? 0);

    $discount = 0.0;
    $label = '';
    if ($type === 'percent' && $value > 0) {
        $discount = $price * ($value / 100);
        $label = '-' . rtrim(rtrim(number_format($value, 2), '0'), '.') . '%';
    } elseif ($type === 'fixed' && $value > 0) {
        $discount = $value;
        $label = '-' . format_baht($value);
    }
    $discount = min($discount, $price); // never below zero
    $final = max(0, $price - $discount);
    return [
        'final'       => $final,
        'discount'    => $discount,
        'has_discount'=> $discount > 0,
        'label'       => $label,
    ];
}

/**
 * Badge for product condition (new / used / refurbished)
 */
function render_condition_badge($condition) {
    $badges = [
        'new'         => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-100 text-sky-800">สินค้าใหม่</span>',
        'used'        => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800">มือสอง (QC ผ่าน)</span>',
        'refurbished' => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-violet-100 text-violet-800">Refurbished</span>',
    ];
    return $badges[$condition] ?? '';
}

/**
 * Flash message helper
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render Status Badges for Orders
 */
function render_order_status_badge($status) {
    $badges = [
        'pending' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">รอดำเนินการ</span>',
        'processing' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">กำลังจัดเตรียม</span>',
        'shipped' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">จัดส่งแล้ว</span>',
        'completed' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">สำเร็จแล้ว</span>',
        'cancelled' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">ยกเลิก</span>',
    ];
    return $badges[$status] ?? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">' . htmlspecialchars($status) . '</span>';
}

/**
 * Render Status Badges for Repairs
 */
function render_repair_status_badge($status) {
    $badges = [
        'received' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800">1. รับเครื่องแล้ว</span>',
        'checking' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">2. กำลังตรวจเช็ค</span>',
        'waiting_parts' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">3. รออะไหล่/รออนุมัติ</span>',
        'repairing' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">4. กำลังซ่อมบำรุง</span>',
        'testing_qc' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-800">5. ทดสอบ QC</span>',
        'ready_for_pickup' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">6. พร้อมส่งมอบ</span>',
        'completed' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-200 text-slate-800">7. ปิดงาน/รับเครื่องแล้ว</span>',
        'cancelled' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">ยกเลิกงานซ่อม</span>',
    ];
    return $badges[$status] ?? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">' . htmlspecialchars($status) . '</span>';
}

/**
 * Render Production Status Badges
 */
function render_production_status_badge($status) {
    $badges = [
        'pending' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">รอคิวเตรียมเครื่อง</span>',
        'in_progress' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">กำลังติดตั้ง/ประกอบ</span>',
        'qc_passed' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-800">ผ่านการตรวจ QC</span>',
        'ready_to_deliver' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">พร้อมส่งมอบหน้าร้าน</span>',
    ];
    return $badges[$status] ?? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">' . htmlspecialchars($status) . '</span>';
}

/**
 * Thai Role Name
 */
function get_role_title($role) {
    $titles = [
        'president' => 'ประธาน / ผู้บริหาร (CEO)',
        'hr' => 'ผู้จัดการฝ่ายบุคคลและระบบ (HR/Admin)',
        'admin' => 'ผู้ดูแลระบบ (Admin)',
        'production' => 'ฝ่ายผลิตและตรวจสอบ (Production/QC)',
        'stock' => 'ฝ่ายคลังสินค้าและอะไหล่ (Stock)',
        'sales' => 'ฝ่ายขายและลูกค้าสัมพันธ์ (Sales)',
        'technician' => 'ฝ่ายช่างเทคนิคและงานซ่อม (Technician)',
        'customer' => 'ลูกค้าทั่วไป (Customer)'
    ];
    return $titles[$role] ?? 'ผู้ใช้งานทั่วไป';
}

/**
 * Generate relative base url
 */
function base_url($path = '') {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $base = rtrim(str_replace('\\', '/', $scriptDir), '/');
    if ($base === '' || $base === '/' || $base === '.') {
        return '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
    // If inside admin/ or customer/, adjust root
    $lastSegment = basename($base);
    if ($lastSegment === 'admin' || $lastSegment === 'customer') {
        $root = rtrim(str_replace('\\', '/', dirname($base)), '/');
        if ($root === '' || $root === '/' || $root === '.') {
            return '/' . ltrim(str_replace('\\', '/', $path), '/');
        }
        return $root . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
    return $base . '/' . ltrim(str_replace('\\', '/', $path), '/');
}
