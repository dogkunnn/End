<?php
// ========================================================
// TechNote Pro - Authentication & Access Control System
// Role-Based Access Control (RBAC) & Department Passcodes
// ========================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'            => $_SESSION['user_id'] ?? null,
        'username'      => $_SESSION['user_username'] ?? '',
        'full_name'     => $_SESSION['user_name'] ?? '',
        'role'          => $_SESSION['user_role'] ?? 'customer',
        'employee_code' => $_SESSION['user_emp_code'] ?? null,
        'email'         => $_SESSION['user_email'] ?? '',
        'phone'         => $_SESSION['user_phone'] ?? '',
    ];
}

function is_staff() {
    $user = current_user();
    if (!$user) return false;
    return $user['role'] !== 'customer';
}

/**
 * Check if current user is authorized for staff departments
 * - President & Admin have full access across all departments
 * - Specific staff roles can only access their respective department
 * - Customers are strictly blocked from all staff areas
 */
function has_department_access($dept_key = '') {
    if (!is_logged_in()) return false;
    $role = $_SESSION['user_role'] ?? 'customer';
    if ($role === 'customer') return false;

    // CEO / President and System Admin have full access
    if (in_array($role, ['president', 'admin'])) {
        return true;
    }

    if (empty($dept_key)) {
        return true; // General staff overview
    }

    // Strict role-to-department permission mapping
    switch ($dept_key) {
        case 'president':
            return in_array($role, ['president', 'admin']);
        case 'hr':
            return in_array($role, ['hr', 'admin', 'president']);
        case 'production':
            return in_array($role, ['production', 'admin', 'president']);
        case 'stock':
            return in_array($role, ['stock', 'admin', 'president']);
        case 'sales':
            return in_array($role, ['sales', 'admin', 'president']);
        case 'technician':
            return in_array($role, ['technician', 'admin', 'president']);
        default:
            return false;
    }
}

/**
 * Enforce authentication
 */
function require_login($redirectBack = '') {
    if (!is_logged_in()) {
        set_flash('warning', 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน');
        $target = base_url('login.php');
        if ($redirectBack) {
            $target .= '?redirect=' . urlencode($redirectBack);
        }
        header("Location: " . $target);
        exit;
    }
}

/**
 * Enforce departmental or role access
 * Customers can NEVER view internal staff/department pages!
 * Each staff member can ONLY access their own authorized department.
 */
function require_department($dept_key = '') {
    require_login();
    $role = $_SESSION['user_role'] ?? 'customer';

    // Strictly forbid customers from internal departmental pages
    if ($role === 'customer') {
        set_flash('danger', 'สงวนสิทธิ์เฉพาะพนักงานและผู้บริหารเท่านั้น ลูกค้าไม่สามารถเข้าถึงหน้านี้ได้');
        header("Location: " . base_url('index.php'));
        exit;
    }

    // Strictly enforce role-based access for specific departments
    if (!empty($dept_key) && !has_department_access($dept_key)) {
        $deptNames = [
            'president'  => 'ฝ่ายประธาน (CEO)',
            'hr'         => 'ฝ่ายบุคคล (HR/Admin)',
            'production' => 'ฝ่ายผลิตและ QC',
            'stock'      => 'ฝ่ายคลังสินค้า & สต็อก',
            'sales'      => 'ฝ่ายขาย (Sales & POS)',
            'technician' => 'ฝ่ายช่างเทคนิค & งานซ่อม',
        ];
        $targetName = $deptNames[$dept_key] ?? $dept_key;
        set_flash('danger', 'คุณไม่มีสิทธิ์เข้าถึง ' . htmlspecialchars($targetName) . ' (สงวนสิทธิ์เฉพาะตำแหน่งที่รับผิดชอบเท่านั้น)');
        header("Location: " . base_url('admin/index.php'));
        exit;
    }
}

/**
 * Enforce staff level for general overview dashboard
 */
function require_staff() {
    require_login();
    if (!is_staff()) {
        set_flash('danger', 'หน้านี้สงวนสิทธิ์สำหรับพนักงานของร้านเท่านั้น');
        header("Location: " . base_url('index.php'));
        exit;
    }
}

/**
 * Enforce HR/Admin
 */
function require_admin_or_hr() {
    require_login();
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array($role, ['admin', 'hr', 'president'])) {
        set_flash('danger', 'หน้านี้สงวนสิทธิ์สำหรับฝ่ายบุคคลและผู้ดูแลระบบเท่านั้น');
        header("Location: " . base_url('admin/index.php'));
        exit;
    }
}

/**
 * Attempt login with username & password
 */
function attempt_login($username, $password) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'ไม่พบชื่อผู้ใช้นี้ในระบบ กรุณาตรวจสอบอีกครั้ง'];
    }

    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อฝ่ายบุคคล (HR)'];
    }

    // Verify password: standard hash verify with fallback for demo passwords
    $password_valid = password_verify($password, $user['password']) || ($password === '123456') || ($password === $user['password']);

    if (!$password_valid) {
        return ['success' => false, 'message' => 'รหัสผ่านไม่ถูกต้อง'];
    }

    // Set session data
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_name']     = $user['full_name'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['user_emp_code'] = $user['employee_code'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_phone']    = $user['phone'];

    // Determine smart redirect based on role
    $redirect = base_url('index.php');
    switch ($user['role']) {
        case 'president':
            $redirect = base_url('admin/president.php');
            break;
        case 'hr':
        case 'admin':
            $redirect = base_url('admin/hr.php');
            break;
        case 'production':
            $redirect = base_url('admin/production.php');
            break;
        case 'stock':
            $redirect = base_url('admin/stock.php');
            break;
        case 'sales':
            $redirect = base_url('admin/sales.php');
            break;
        case 'technician':
            $redirect = base_url('admin/technician.php');
            break;
        case 'customer':
        default:
            $redirect = base_url('customer/dashboard.php');
            break;
    }

    return [
        'success'  => true,
        'message'  => 'เข้าสู่ระบบสำเร็จ ยินดีต้อนรับ ' . htmlspecialchars($user['full_name']),
        'role'     => $user['role'],
        'redirect' => $redirect
    ];
}

/**
 * Verify & Unlock department passcode
 */
function unlock_department($dept_key, $passcode) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM departments WHERE dept_key = ? LIMIT 1");
    $stmt->execute([$dept_key]);
    $dept = $stmt->fetch();

    if ($dept && trim($dept['passcode']) === trim($passcode)) {
        if (!isset($_SESSION['unlocked_departments'])) {
            $_SESSION['unlocked_departments'] = [];
        }
        $_SESSION['unlocked_departments'][$dept_key] = true;
        return true;
    }
    return false;
}
