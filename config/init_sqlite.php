<?php
// SQLite initializer for instant zero-config fallback

function init_sqlite_database($pdo) {
    $queries = [
        "CREATE TABLE IF NOT EXISTS departments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            dept_key TEXT NOT NULL UNIQUE,
            dept_name TEXT NOT NULL,
            passcode TEXT NOT NULL,
            description TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            full_name TEXT NOT NULL,
            email TEXT,
            phone TEXT,
            address TEXT,
            role TEXT NOT NULL DEFAULT 'customer',
            employee_code TEXT,
            salary REAL DEFAULT 0.0,
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            icon TEXT
        )",
        
        "CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL,
            brand TEXT NOT NULL,
            model TEXT NOT NULL,
            price REAL NOT NULL,
            cost_price REAL NOT NULL,
            stock_quantity INTEGER NOT NULL DEFAULT 0,
            cpu TEXT NOT NULL,
            gpu TEXT NOT NULL,
            ram TEXT NOT NULL,
            storage TEXT NOT NULL,
            display TEXT NOT NULL,
            os TEXT NOT NULL DEFAULT 'Windows 11 Home',
            warranty TEXT NOT NULL DEFAULT '2 Years Onsite',
            image_url TEXT,
            description TEXT,
            is_featured INTEGER DEFAULT 0,
            status TEXT DEFAULT 'in_stock',
            item_condition TEXT DEFAULT 'new',
            discount_type TEXT DEFAULT 'none',
            discount_value REAL DEFAULT 0,
            serial_number TEXT,
            qc_task_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            description TEXT,
            discount_type TEXT NOT NULL DEFAULT 'percent',
            discount_value REAL NOT NULL DEFAULT 0,
            min_order REAL NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1,
            expires_at DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS spare_parts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            part_code TEXT NOT NULL UNIQUE,
            part_name TEXT NOT NULL,
            category TEXT NOT NULL,
            cost_price REAL NOT NULL,
            sell_price REAL NOT NULL,
            stock_quantity INTEGER NOT NULL DEFAULT 0,
            min_threshold INTEGER DEFAULT 3,
            supplier TEXT,
            status TEXT DEFAULT 'available',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_code TEXT NOT NULL UNIQUE,
            user_id INTEGER,
            customer_name TEXT NOT NULL,
            customer_phone TEXT NOT NULL,
            customer_email TEXT,
            shipping_address TEXT NOT NULL,
            subtotal_amount REAL NOT NULL DEFAULT 0.0,
            discount_amount REAL NOT NULL DEFAULT 0.0,
            coupon_code TEXT,
            total_amount REAL NOT NULL DEFAULT 0.0,
            payment_method TEXT DEFAULT 'Transfer',
            payment_status TEXT DEFAULT 'pending',
            order_status TEXT DEFAULT 'pending',
            sales_rep_id INTEGER,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            product_name TEXT NOT NULL,
            price REAL NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 1,
            subtotal REAL NOT NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS repair_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_code TEXT NOT NULL UNIQUE,
            user_id INTEGER,
            customer_name TEXT NOT NULL,
            customer_phone TEXT NOT NULL,
            customer_email TEXT,
            brand TEXT NOT NULL,
            model TEXT NOT NULL,
            serial_number TEXT,
            issue_description TEXT NOT NULL,
            diagnosis TEXT,
            technician_id INTEGER,
            status TEXT NOT NULL DEFAULT 'received',
            estimated_cost REAL DEFAULT 0.0,
            labor_fee REAL DEFAULT 500.0,
            parts_fee REAL DEFAULT 0.0,
            total_cost REAL DEFAULT 500.0,
            technician_notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME
        )",
        
        "CREATE TABLE IF NOT EXISTS production_tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_code TEXT NOT NULL UNIQUE,
            product_id INTEGER,
            notebook_name TEXT NOT NULL,
            assigned_to INTEGER,
            task_type TEXT NOT NULL,
            spec_upgrade_details TEXT,
            qc_checklist TEXT,
            status TEXT DEFAULT 'pending',
            serial_number TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME
        )",
        
        "CREATE TABLE IF NOT EXISTS stock_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            item_type TEXT NOT NULL,
            item_id INTEGER NOT NULL,
            item_name TEXT NOT NULL,
            quantity INTEGER NOT NULL,
            reason TEXT NOT NULL,
            reference_code TEXT,
            performed_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS system_announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            author_name TEXT NOT NULL,
            priority TEXT DEFAULT 'normal',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($queries as $q) {
        $pdo->exec($q);
    }

    // Seed Departments
    $passHash = password_hash('123456', PASSWORD_BCRYPT);

    $pdo->exec("INSERT OR IGNORE INTO departments (id, dept_key, dept_name, passcode, description) VALUES
        (1, 'president', 'ประธาน / ผู้บริหาร (Executive)', 'CEO999', 'สิทธิ์เข้าถึงข้อมูลภาพรวมร้าน ยอดขาย กำไร และประสิทธิภาพพนักงาน'),
        (2, 'hr', 'ฝ่ายทรัพยากรบุคคลและระบบ (HR & Admin)', 'HR777', 'สิทธิ์จัดการข้อมูลพนักงาน เพิ่ม ลบ แก้ไข รหัสพนักงาน และรหัสผ่านประจำแผนก'),
        (3, 'production', 'ฝ่ายผลิตและตรวจสอบคุณภาพ (Production & QC)', 'PRD202', 'งานเตรียมเครื่อง ลงโปรแกรม ประกอบ อัปเกรดสเปก และตรวจเช็ค QC'),
        (4, 'stock', 'ฝ่ายคลังสินค้าและอะไหล่ (Stock & Inventory)', 'STK303', 'จัดการสต็อกโน้ตบุ๊ก อะไหล่ชิ้นส่วน รับเข้า-เบิกออก แจ้งเตือนของใกล้หมด'),
        (5, 'sales', 'ฝ่ายขายและลูกค้าสัมพันธ์ (Sales & Marketing)', 'SLS404', 'จัดการคำสั่งซื้อ ปิดการขาย ติดต่อลูกค้า ออกใบเสร็จ POS'),
        (6, 'technician', 'ฝ่ายช่างเทคนิคและงานซ่อม (Technician Service)', 'TEC505', 'รับงานซ่อม ตรวจเช็คอาการ เสนอราคา ประเมินอะไหล่ อัปเดตสถานะงานซ่อม')
    ");

    // Seed Users
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (id, username, password, full_name, email, phone, address, role, employee_code, salary, status) VALUES 
        (1, 'ceo', ?, 'ดร.สมเกียรติ ยั่งยืน (ประธานกรรมการ)', 'ceo@technotepro.com', '081-111-2222', 'สำนักงานใหญ่ กรุงเทพฯ', 'president', 'EMP-CEO-01', 95000.0, 'active'),
        (2, 'admin', ?, 'น.ส.วิภาดา บริหารกิจ (ผู้จัดการฝ่ายบุคคล/Admin)', 'hr@technotepro.com', '082-222-3333', 'อาคารบริหาร ชั้น 2', 'hr', 'EMP-HR-01', 45000.0, 'active'),
        (3, 'production', ?, 'นายมนัส ผลิตดี (หัวหน้าฝ่ายผลิตและเตรียมเครื่อง)', 'production@technotepro.com', '083-333-4444', 'แผนกประกอบ/เตรียมเครื่อง', 'production', 'EMP-PRD-01', 32000.0, 'active'),
        (4, 'stock', ?, 'นายธวัชชัย คลังพานิช (เจ้าหน้าที่สต็อกสินค้า)', 'stock@technotepro.com', '084-444-5555', 'คลังสินค้า ชั้น 1', 'stock', 'EMP-STK-01', 28000.0, 'active'),
        (5, 'sales', ?, 'น.ส.แพรวา ขายคล่อง (พนักงานฝ่ายขาย/เซลล์)', 'sales@technotepro.com', '085-555-6666', 'หน้าร้าน สาขาหลัก', 'sales', 'EMP-SLS-01', 30000.0, 'active'),
        (6, 'tech', ?, 'นายธีรภัทร ช่างฝีมือ (หัวหน้าช่างซ่อม)', 'tech@technotepro.com', '086-666-7777', 'ศูนย์ซ่อม ชั้น 1', 'technician', 'EMP-TEC-01', 35000.0, 'active'),
        (7, 'customer', ?, 'นายเอกชัย ใจดี (ลูกค้าทั่วไป)', 'customer@gmail.com', '089-999-8888', '123/45 ถ.พหลโยธิน จตุจักร กรุงเทพฯ', 'customer', NULL, 0.0, 'active'),
        (8, 'tech2', ?, 'นายกิตติศักดิ์ ซ่อมไว (ช่างเทคนิคฮาร์ดแวร์)', 'tech2@technotepro.com', '087-777-8888', 'ศูนย์ซ่อม ชั้น 1', 'technician', 'EMP-TEC-02', 26000.0, 'active')
    ");
    $stmt->execute([$passHash, $passHash, $passHash, $passHash, $passHash, $passHash, $passHash, $passHash]);

    // Categories
    $pdo->exec("INSERT OR IGNORE INTO categories (id, name, slug, icon) VALUES
        (1, 'Gaming Laptops', 'gaming', 'gamepad-2'),
        (2, 'Ultrabook & Thin-Light', 'ultrabook', 'feather'),
        (3, 'Business & Workstation', 'business', 'briefcase'),
        (4, 'Student & General Use', 'student', 'graduation-cap'),
        (5, 'Creator & Graphic Design', 'creator', 'palette')
    ");

    // Products
    $pdo->exec("INSERT OR IGNORE INTO products (id, category_id, brand, model, price, cost_price, stock_quantity, cpu, gpu, ram, storage, display, os, warranty, image_url, description, is_featured, status) VALUES
        (1, 1, 'ASUS', 'ROG Strix G16 (2025)', 48990.0, 41500.0, 8, 'Intel Core i7-14650HX', 'NVIDIA GeForce RTX 4060 8GB GDDR6', '16GB DDR5 5600MHz', '1TB PCIe 4.0 NVMe M.2 SSD', '16.0\" FHD+ 165Hz IPS 100% sRGB', 'Windows 11 Home', '2 Years Warranty + Perfect Warranty', 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?auto=format&fit=crop&w=800&q=80', 'สุดยอดโน้ตบุ๊กเกมมิ่งสายพลัง ขุมพลัง Intel Gen 14th จับคู่กับการ์ดจอ RTX 4060 เล่นเกม AAA ปรับสุดลื่นไหล', 1, 'in_stock'),
        (2, 1, 'Lenovo', 'Legion Pro 5 16IRX9', 54990.0, 47000.0, 5, 'Intel Core i9-14900HX', 'NVIDIA GeForce RTX 4070 8GB GDDR6', '32GB DDR5 5600MHz', '1TB PCIe 4.0 SSD', '16.0\" WQXGA (2560x1600) 240Hz 500nits', 'Windows 11 Home', '3 Years Ultimate Support Onsite', 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=800&q=80', 'Legion Pro 5 สเปกจัดเต็ม i9-14900HX และ RTX 4070 หน้าจอระดับเทพ 240Hz 500nits', 1, 'in_stock'),
        (3, 2, 'Apple', 'MacBook Air 15\" M3 (2024)', 47900.0, 42000.0, 12, 'Apple M3 chip 8-core CPU', '10-core GPU, 16-core Neural Engine', '16GB Unified Memory', '512GB SSD Storage', '15.3\" Liquid Retina Display True Tone', 'macOS Sonoma', '1 Year Apple Care Warranty', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80', 'บางเบา ทรงพลัง จอใหญ่ 15 นิ้ว แบตเตอรี่อึดใช้งานได้ตลอดทั้งวัน 18 ชม.', 1, 'in_stock'),
        (4, 2, 'ASUS', 'Zenbook 14 OLED UX3405', 38990.0, 33500.0, 7, 'Intel Core Ultra 7 155H with AI Boost', 'Intel Arc Graphics', '16GB LPDDR5X', '1TB NVMe M.2 SSD', '14.0\" 3K (2880 x 1800) OLED 120Hz', 'Windows 11 Home + MS Office Home & Student', '3 Years Onsite Warranty', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=800&q=80', 'โน้ตบุ๊กบางเบาระดับพรีเมียม ขับเคลื่อนด้วยโปรเซสเซอร์ AI-ready Intel Core Ultra 7 หน้าจอ 3K OLED', 1, 'in_stock'),
        (5, 3, 'Dell', 'Latitude 5440 Business Notebook', 32900.0, 28000.0, 9, 'Intel Core i5-1335U', 'Intel Iris Xe Graphics', '16GB DDR4 3200MHz', '512GB PCIe NVMe SSD', '14.0\" FHD (1920x1080) Anti-Glare', 'Windows 11 Pro', '3 Years ProSupport Next Business Day', 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?auto=format&fit=crop&w=800&q=80', 'โน้ตบุ๊กสำหรับองค์กร ทนทาน ปลอดภัยระดับ Enterprise มาพร้อม Windows 11 Pro', 0, 'in_stock'),
        (6, 4, 'Acer', 'Aspire 5 A515-58M', 18990.0, 16200.0, 15, 'Intel Core i5-1335U', 'Intel Iris Xe Graphics', '16GB LPDDR5', '512GB PCIe Gen4 SSD', '15.6\" Full HD IPS Acer ComfyView', 'Windows 11 Home + MS Office', '2 Years Warranty Local', 'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?auto=format&fit=crop&w=800&q=80', 'โน้ตบุ๊กคุ้มค่ายอดนิยมสำหรับนักเรียน นักศึกษา และทำงานทั่วไป ครบครันด้วยพอร์ตเชื่อมต่อ', 1, 'in_stock'),
        (7, 1, 'Acer', 'Nitro V 15 ANV15-51', 27990.0, 24000.0, 6, 'Intel Core i5-13420H', 'NVIDIA GeForce RTX 4050 6GB GDDR6', '16GB DDR5 5200MHz', '512GB PCIe M.2 SSD', '15.6\" FHD 144Hz IPS SlimBezel', 'Windows 11 Home', '3 Years Onsite Warranty', 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=800&q=80', 'เกมมิ่งงบประหยัด สเปกแรงคุ้มค่า ได้การ์ดจอ RTX 4050 เล่นเกมยอดนิยม ลื่นไหล 144Hz', 0, 'in_stock'),
        (8, 5, 'HP', 'Envy x360 2-in-1 14', 35990.0, 31000.0, 4, 'AMD Ryzen 7 7730U', 'AMD Radeon Graphics', '16GB LPDDR4x', '1TB PCIe NVMe SSD', '14.0\" 2.8K OLED Touch 360-degree', 'Windows 11 Home + Stylus Pen', '2 Years Onsite Warranty', 'https://images.unsplash.com/photo-1531297484001-80022131f5a1?auto=format&fit=crop&w=800&q=80', 'จอสัมผัสพับได้ 360 องศา พร้อมปากกา Stylus เหมาะสำหรับงานวาดภาพ กราฟิก และพรีเซนต์งาน', 0, 'in_stock')
    ");

    // Spare Parts
    $pdo->exec("INSERT OR IGNORE INTO spare_parts (id, part_code, part_name, category, cost_price, sell_price, stock_quantity, min_threshold, supplier, status) VALUES
        (1, 'PRT-RAM-D4-8', 'RAM DDR4 8GB 3200MHz SODIMM Kingston', 'RAM', 650.0, 1150.0, 18, 5, 'Synnex Thailand', 'available'),
        (2, 'PRT-RAM-D5-16', 'RAM DDR5 16GB 5600MHz SODIMM Crucial', 'RAM', 1450.0, 2390.0, 12, 4, 'Ingram Micro', 'available'),
        (3, 'PRT-SSD-NV-1TB', 'SSD M.2 NVMe PCIe 4.0 1TB Samsung 980 Pro', 'SSD', 2600.0, 3790.0, 15, 5, 'Synnex Thailand', 'available'),
        (4, 'PRT-SSD-NV-512', 'SSD M.2 NVMe PCIe 3.0 512GB WD Blue SN580', 'SSD', 1250.0, 1850.0, 20, 5, 'Advice Distribution', 'available'),
        (5, 'PRT-SCR-156-FHD', 'จอโน้ตบุ๊ก 15.6\" FHD IPS 30-pin 144Hz Slim', 'Screen', 1800.0, 2900.0, 4, 3, 'China Direct Parts', 'available'),
        (6, 'PRT-SCR-140-FHD', 'จอโน้ตบุ๊ก 14.0\" FHD IPS 30-pin Slim Border', 'Screen', 1600.0, 2600.0, 2, 3, 'China Direct Parts', 'low_stock'),
        (7, 'PRT-BAT-ASUS-G15', 'แบตเตอรี่แท้ ASUS ROG Strix / TUF Gaming 90Wh', 'Battery', 1500.0, 2400.0, 6, 2, 'ASUS Service Partner', 'available'),
        (8, 'PRT-BAT-LEN-L5', 'แบตเตอรี่แท้ Lenovo Legion 5 / 5 Pro 80Wh', 'Battery', 1450.0, 2350.0, 3, 2, 'Lenovo Spares Dept', 'available'),
        (9, 'PRT-KEY-ACR-N5', 'คีย์บอร์ดเรืองแสง RGB Acer Nitro 5 Thai/Eng', 'Keyboard', 600.0, 1200.0, 7, 3, 'Advice Distribution', 'available'),
        (10, 'PRT-FAN-DUAL-G16', 'ชุดพัดลมระบายความร้อน Dual Fan ASUS ROG G16', 'Cooling Fan', 750.0, 1450.0, 5, 2, 'China Direct Parts', 'available'),
        (11, 'PRT-THM-KRY-1G', 'ซิลิโคน Thermal Grizzly Kryonaut 1g', 'Thermal Paste', 180.0, 390.0, 25, 5, 'JIB Supply', 'available'),
        (12, 'PRT-ADP-TYPEC-100', 'อะแดปเตอร์ชาร์จด่วน Type-C GaN 100W PD', 'Adapter', 700.0, 1290.0, 10, 4, 'Baseus Thailand', 'available')
    ");

    // Orders
    $pdo->exec("INSERT OR IGNORE INTO orders (id, order_code, user_id, customer_name, customer_phone, customer_email, shipping_address, total_amount, payment_method, payment_status, order_status, sales_rep_id, notes, created_at) VALUES
        (1, 'ORD-2026-001', 7, 'นายเอกชัย ใจดี', '089-999-8888', 'customer@gmail.com', '123/45 ถ.พหลโยธิน จตุจักร กรุงเทพฯ', 48990.0, 'Transfer', 'paid', 'completed', 5, 'ลูกค้าสั่งซื้อหน้าร้าน พร้อมของแถมกระเป๋าเป้และเมาส์', '2026-10-25 10:15:00'),
        (2, 'ORD-2026-002', NULL, 'นางสาวกนกวรรณ รุ่งเรือง', '081-345-6789', 'kanokwan@outlook.com', '88/12 ซอยสุขุมวิท 55 แขวงคลองตันเหนือ วัฒนา กทม.', 47900.0, 'CreditCard', 'paid', 'processing', 5, 'ออเดอร์ออนไลน์ รอส่งด่วน', '2026-10-28 14:30:00'),
        (3, 'ORD-2026-003', NULL, 'นายปิยะพงษ์ ยอดเยี่ยม', '092-888-7711', 'piyapong@gmail.com', '45 หมู่ 3 ต.สุเทพ อ.เมือง เชียงใหม่', 38990.0, 'Transfer', 'paid', 'shipped', 5, 'ส่ง Kerry KEX12345678TH', '2026-10-29 09:20:00')
    ");

    // Order items
    $pdo->exec("INSERT OR IGNORE INTO order_items (id, order_id, product_id, product_name, price, quantity, subtotal) VALUES
        (1, 1, 1, 'ASUS ROG Strix G16 (2025)', 48990.0, 1, 48990.0),
        (2, 2, 3, 'Apple MacBook Air 15\" M3 (2024)', 47900.0, 1, 47900.0),
        (3, 3, 4, 'ASUS Zenbook 14 OLED UX3405', 38990.0, 1, 38990.0)
    ");

    // Repair Tickets
    $pdo->exec("INSERT OR IGNORE INTO repair_tickets (id, ticket_code, user_id, customer_name, customer_phone, customer_email, brand, model, serial_number, issue_description, diagnosis, technician_id, status, estimated_cost, labor_fee, parts_fee, total_cost, technician_notes, created_at, completed_at) VALUES
        (1, 'REP-2026-001', 7, 'นายเอกชัย ใจดี', '089-999-8888', 'customer@gmail.com', 'Acer', 'Nitro 5 AN515-55', 'NXQ7MST0019284', 'เครื่องร้อนผิดปกติ พัดลมเสียงดังมาก ดับเองระหว่างเล่นเกม', 'ตรวจพบซิลิโคนแห้งแข็ง และแกนพัดลมระบายความร้อนฝั่ง CPU ฝืด ต้องเปลี่ยนชุดพัดลมและทาซิลิโคนใหม่', 6, 'ready_for_pickup', 1900.0, 450.0, 1450.0, 1900.0, 'เปลี่ยนพัดลมระบายความร้อนเบิกจากสต็อก PRT-FAN-DUAL-G16 และหยอด Thermal Grizzly Kryonaut เรียบร้อย อุณหภูมิ Full Load ลดลงเหลือ 74C ผ่าน QC', '2026-10-26 11:00:00', '2026-10-28 16:00:00'),
        (2, 'REP-2026-002', NULL, 'น.ส.ชลธิชา สิทธิผล', '083-456-7890', 'chonthicha@hotmail.com', 'ASUS', 'VivoBook S14 S433', 'K9N0CV01E234567', 'ทำน้ำหกใส่คีย์บอร์ด แป้นกดเบิ้ลและบางปุ่มกดไม่ติด', 'คีย์บอร์ดลัดวงจร เมนบอร์ดปกติ ทำความสะอาดและเปลี่ยนแผงคีย์บอร์ดใหม่', 6, 'repairing', 1700.0, 500.0, 1200.0, 1700.0, 'สั่งเบิกคีย์บอร์ดใหม่ กำลังทำการถอดประกอบและเชื่อมแผงคีย์บอร์ด', '2026-10-28 15:20:00', NULL),
        (3, 'REP-2026-003', NULL, 'นายธนาธิป สุขสวัสดิ์', '095-123-9988', 'thanathip@gmail.com', 'Lenovo', 'IdeaPad Gaming 3', 'PF2X9901M88', 'เปิดเครื่องไฟติดแต่หน้าจอไม่ขึ้นภาพ จอดำสนิท', 'ตรวจเช็ควงจรภาคจ่ายไฟหน้าจอ ต่อจอแยก HDMI ภาพออกปกติ สันนิษฐานสายแพรจอหักงอช่วงบานพับ', 8, 'checking', 2500.0, 500.0, 2000.0, 2500.0, 'ประเมินราคาแจ้งลูกค้าอนุมัติการเปลี่ยนสายแพรจอ', '2026-10-29 13:40:00', NULL),
        (4, 'REP-2026-004', NULL, 'นายวรพจน์ มิตรภาพ', '088-776-5544', 'worapoj@gmail.com', 'Dell', 'Inspiron 3511', 'SN-DL-883921', 'เครื่องทำงานช้ามาก บูตวินโดวส์เกือบ 5 นาที', 'เครื่องเดิมใช้ HDD 1TB จานหมุน แนะนำอัปเกรดเป็น M.2 NVMe SSD 512GB และลงวินโดวส์ใหม่', 6, 'waiting_parts', 2350.0, 500.0, 1850.0, 2350.0, 'ลูกค้าอนุมัติแล้ว รอเบิก SSD PRT-SSD-NV-512 เข้าคิวติดตั้ง', '2026-10-30 09:10:00', NULL)
    ");

    // Production Tasks
    $pdo->exec("INSERT OR IGNORE INTO production_tasks (id, task_code, product_id, notebook_name, assigned_to, task_type, spec_upgrade_details, qc_checklist, status, serial_number, notes, created_at, completed_at) VALUES
        (1, 'PRD-2026-001', 1, 'ASUS ROG Strix G16 (Lot A-01)', 3, 'New Machine Setup', 'ติดตั้ง Windows 11 Home ลิขสิทธิ์แท้, ลง ROG Armoury Crate, อัปเดต BIOS v315, อัปเดตไดรเวอร์ล่าสุด', '{\"screen\":\"pass\",\"keyboard\":\"pass\",\"trackpad\":\"pass\",\"wifi_bt\":\"pass\",\"battery_charge\":\"pass\",\"ports_io\":\"pass\",\"stress_test_15m\":\"pass\"}', 'ready_to_deliver', 'SN-ROG-2026-A0991', 'ผ่านการทดสอบ Benchmark และ Burn-in Test 15 นาที เสถียรดี', '2026-10-27 09:00:00', '2026-10-27 15:30:00'),
        (2, 'PRD-2026-002', 2, 'Lenovo Legion Pro 5 (Custom RAM)', 3, 'Custom Upgrade', 'อัปเกรด RAM เป็น 64GB DDR5 (Crucial 32GBx2) สำหรับงานตัดต่อ 3D', '{\"screen\":\"pass\",\"keyboard\":\"pass\",\"trackpad\":\"pass\",\"wifi_bt\":\"pass\",\"battery_charge\":\"pass\",\"ports_io\":\"pass\",\"memtest_64gb\":\"pass\"}', 'qc_passed', 'SN-LEGION-992318', 'ตรวจเช็ค Dual Channel ทำงานสมบูรณ์ รอส่งมอบ', '2026-10-29 10:30:00', '2026-10-29 16:00:00'),
        (3, 'PRD-2026-003', 6, 'Acer Aspire 5 A515 (เตรียมสต็อกหน้าร้าน 5 เครื่อง)', 3, 'New Machine Setup', 'ลงระบบปฏิบัติการ Windows 11 Home + MS Office แท้ + โปรแกรมพื้นฐาน', '{\"screen\":\"pending\",\"keyboard\":\"pending\",\"trackpad\":\"pending\",\"wifi_bt\":\"pending\",\"battery_charge\":\"pending\"}', 'in_progress', 'SN-ASP5-LOT4-01-05', 'กำลังติดตั้งไดรเวอร์และโปรแกรมพื้นฐาน', '2026-10-30 08:30:00', NULL)
    ");

    // Stock Transactions
    $pdo->exec("INSERT OR IGNORE INTO stock_transactions (id, type, item_type, item_id, item_name, quantity, reason, reference_code, performed_by, created_at) VALUES
        (1, 'in', 'product', 1, 'ASUS ROG Strix G16 (2025)', 10, 'รับสินค้าเข้าสต็อกจากตัวแทนจำหน่าย Synnex', 'PO-2026-10-01', 4, '2026-10-20 10:00:00'),
        (2, 'out', 'product', 1, 'ASUS ROG Strix G16 (2025)', 1, 'ตัดสต็อกจากการขายออเดอร์หน้าร้าน', 'ORD-2026-001', 4, '2026-10-25 10:30:00'),
        (3, 'in', 'spare_part', 3, 'SSD M.2 NVMe PCIe 4.0 1TB Samsung 980 Pro', 20, 'สั่งซื้ออะไหล่สำรองเข้าศูนย์บริการ', 'PO-PART-2026-05', 4, '2026-10-22 14:00:00'),
        (4, 'out', 'spare_part', 10, 'ชุดพัดลมระบายความร้อน Dual Fan ASUS ROG G16', 1, 'เบิกชิ้นส่วนเพื่อใช้ในงานซ่อมโน้ตบุ๊ก', 'REP-2026-001', 4, '2026-10-27 11:30:00')
    ");

    // Announcements
    $pdo->exec("INSERT OR IGNORE INTO system_announcements (id, title, message, author_name, priority, created_at) VALUES
        (1, 'นโยบายยกระดับมาตรฐานงานบริการและศูนย์ซ่อมประจำไตรมาส', 'ขอให้ทุกแผนกตรวจสอบความพร้อมของระบบและการส่งมอบงานแก่ลูกค้าอย่างรวดเร็ว โดยฝ่ายช่างต้องแจ้งผลตรวจเช็คภายใน 24 ชม. และฝ่ายสต็อกหมั่นตรวจสอบอะไหล่ยอดนิยมไม่ให้ขาด เพื่อสร้างความประทับใจสูงสุดให้แก่ลูกค้าทุกท่าน', 'ดร.สมเกียรติ ยั่งยืน (ประธานกรรมการ)', 'important', '2026-10-25 09:00:00'),
        (2, 'แจ้งปรับปรุงระบบและตรวจนับสต็อกสิ้นเดือน', 'ฝ่ายสต็อกและฝ่ายขายจะทำการตรวจนับสินค้าคงคลังประจำเดือนในวันศุกร์นี้เวลา 18:00 น. ขอให้เตรียมเอกสารให้พร้อม', 'น.ส.วิภาดา บริหารกิจ (HR/Admin)', 'normal', '2026-10-29 11:00:00')
    ");
}
