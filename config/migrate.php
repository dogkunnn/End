<?php
// ========================================================
// TechNote Pro - Idempotent Schema Migration
// Adds: second-hand/QC product fields, per-product discounts,
//       coupons table, order discount fields.
// Safe to run multiple times on both MySQL and SQLite.
// ========================================================

function col_exists(PDO $db, $driver, $table, $col) {
    if ($driver === 'sqlite') {
        $rows = $db->query("PRAGMA table_info(" . $table . ")")->fetchAll();
        foreach ($rows as $r) {
            if (strcasecmp($r['name'], $col) === 0) return true;
        }
        return false;
    }
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $col]);
    return (int)$stmt->fetchColumn() > 0;
}

function table_exists(PDO $db, $driver, $table) {
    if ($driver === 'sqlite') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name = ?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function add_col(PDO $db, $driver, $table, $col, $definition, &$log) {
    if (!col_exists($db, $driver, $table, $col)) {
        $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}");
        $log[] = "ADD {$table}.{$col}";
    }
}

function run_migration(PDO $db, $driver) {
    $log = [];

    // --- products: second-hand + discount support ---
    add_col($db, $driver, 'products', 'item_condition', "VARCHAR(20) DEFAULT 'new'", $log);
    add_col($db, $driver, 'products', 'discount_type', "VARCHAR(10) DEFAULT 'none'", $log);
    add_col($db, $driver, 'products', 'discount_value', "DECIMAL(10,2) DEFAULT 0", $log);
    add_col($db, $driver, 'products', 'serial_number', "VARCHAR(100) NULL", $log);
    add_col($db, $driver, 'products', 'qc_task_id', "INT NULL", $log);

    // --- orders: discount + coupon fields ---
    add_col($db, $driver, 'orders', 'subtotal_amount', "DECIMAL(10,2) DEFAULT 0", $log);
    add_col($db, $driver, 'orders', 'discount_amount', "DECIMAL(10,2) DEFAULT 0", $log);
    add_col($db, $driver, 'orders', 'coupon_code', "VARCHAR(50) NULL", $log);

    // --- coupons table ---
    if (!table_exists($db, $driver, 'coupons')) {
        if ($driver === 'sqlite') {
            $db->exec("CREATE TABLE coupons (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code VARCHAR(50) NOT NULL UNIQUE,
                description VARCHAR(200) NULL,
                discount_type VARCHAR(10) NOT NULL DEFAULT 'percent',
                discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
                min_order DECIMAL(10,2) NOT NULL DEFAULT 0,
                active INTEGER NOT NULL DEFAULT 1,
                expires_at DATE NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $db->exec("CREATE TABLE `coupons` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(50) NOT NULL UNIQUE,
                `description` VARCHAR(200) NULL,
                `discount_type` VARCHAR(10) NOT NULL DEFAULT 'percent',
                `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `min_order` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `expires_at` DATE NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        $log[] = "CREATE TABLE coupons";

        // Seed two demo coupons
        $ins = $db->prepare("INSERT INTO coupons (code, description, discount_type, discount_value, min_order, active) VALUES (?, ?, ?, ?, ?, 1)");
        $ins->execute(['SAVE500', 'ส่วนลด 500 บาท เมื่อซื้อครบ 20,000', 'fixed', 500, 20000]);
        $ins->execute(['WELCOME10', 'ส่วนลด 10% ต้อนรับลูกค้าใหม่', 'percent', 10, 0]);
        $log[] = "SEED coupons (SAVE500, WELCOME10)";
    }

    // Backfill existing products to 'new' condition if empty
    $db->exec("UPDATE products SET item_condition = 'new' WHERE item_condition IS NULL OR item_condition = ''");
    $db->exec("UPDATE products SET discount_type = 'none' WHERE discount_type IS NULL OR discount_type = ''");

    // Seed demo second-hand / QC-passed laptops if none exist
    $usedCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE item_condition = 'used'")->fetchColumn();
    if ($usedCount === 0) {
        $insProd = $db->prepare("INSERT INTO products (
            category_id, brand, model, price, cost_price, stock_quantity,
            cpu, gpu, ram, storage, display, os, warranty, image_url,
            description, is_featured, status, item_condition, serial_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $insProd->execute([
            3, 'Dell', 'XPS 13 9315 (มือสอง สภาพ 95% QC ผ่าน)', 22900.0, 16000.0, 3,
            'Intel Core i5-1230U', 'Intel Iris Xe Graphics', '16GB LPDDR5', '512GB PCIe NVMe SSD',
            '13.4" FHD+ (1920x1200) InfinityEdge', 'Windows 11 Pro', 'ประกันร้าน 6 เดือน ผ่าน QC 20 รายการ',
            'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=800&q=80',
            'เครื่องมือสองเกรดเอ สภาพนางฟ้า 95% ตัวเครื่องไร้รอยตกหล่น แบตเตอรี่เก็บไฟสมบูรณ์ ผ่านการตรวจเช็คระบบโดยช่างผู้เชี่ยวชาญ',
            1, 'in_stock', 'used', 'SN-QC-DELL-8821'
        ]);

        $insProd->execute([
            1, 'ASUS', 'TUF Gaming A15 (มือสอง การ์ดจอ RTX 3060)', 19900.0, 14000.0, 2,
            'AMD Ryzen 7 5800H', 'NVIDIA GeForce RTX 3060 6GB GDDR6', '16GB DDR4 3200MHz', '512GB NVMe M.2 SSD',
            '15.6" FHD 144Hz IPS', 'Windows 11 Home', 'ประกันร้าน 6 เดือน ทดสอบความเสถียร 100%',
            'https://images.unsplash.com/photo-1603302576837-37561b2e2302?auto=format&fit=crop&w=800&q=80',
            'โน้ตบุ๊กเกมมิ่งมือสองสุดคุ้ม เล่นเกมลื่นทุกแนว เปลี่ยนซิลิโคนลดความร้อนเรียบร้อย พร้อมใช้งานทันที',
            1, 'in_stock', 'used', 'SN-QC-ASUS-4432'
        ]);

        $insProd->execute([
            2, 'Apple', 'MacBook Air 13" M1 (มือสอง Cycle Count ต่ำ)', 18500.0, 13000.0, 4,
            'Apple M1 Chip 8-Core', '7-Core GPU', '8GB Unified Memory', '256GB SSD',
            '13.3" Retina Display True Tone', 'macOS Sonoma', 'ประกันร้าน 6 เดือน อุปกรณ์แท้ครบกล่อง',
            'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80',
            'MacBook Air M1 มือสอง แบตเตอรี่สุขภาพ 94% Cycle Count ต่ำ ไม่เคยผ่านการแกะซ่อม สภาพสวยงาม',
            1, 'in_stock', 'used', 'SN-QC-APL-1092'
        ]);
        $log[] = "SEED used products (3 items)";
    }

    return $log;
}
