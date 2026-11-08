-- ========================================================
-- TechNote Pro - Laptop Sales & Repair Ecosystem Database
-- Full Database Schema & Seed Data for Graduation Project
-- ========================================================
-- วิธีการนำเข้าบน Web Hosting (InfinityFree, cPanel, DirectAdmin ฯลฯ):
-- 1. สร้างฐานข้อมูลใน Control Panel ของโฮสติ้ง (เช่น if0_43318804_laptop_store)
-- 2. เปิด phpMyAdmin และ "คลิกเลือกชื่อฐานข้อมูลที่สร้างไว้" ที่แถบด้านซ้าย
-- 3. คลิกแท็บ "Import" (นำเข้า) ด้านบน แล้วเลือกไฟล์ database.sql นี้กด Go/Import
-- (ไฟล์นี้ถูกตัดคำสั่ง CREATE DATABASE ออกแล้ว จึงนำเข้าได้ทันที 100% ไม่ติดสิทธิ์ Access Denied #1044)
-- ========================================================

-- 1. Departments & Security Passcodes
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dept_key` VARCHAR(30) NOT NULL UNIQUE,
  `dept_name` VARCHAR(100) NOT NULL,
  `passcode` VARCHAR(50) NOT NULL,
  `description` TEXT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `departments` (`id`, `dept_key`, `dept_name`, `passcode`, `description`) VALUES
(1, 'president', 'ประธาน / ผู้บริหาร (Executive)', 'CEO999', 'สิทธิ์เข้าถึงข้อมูลภาพรวมร้าน ยอดขาย กำไร และประสิทธิภาพพนักงาน'),
(2, 'hr', 'ฝ่ายทรัพยากรบุคคลและระบบ (HR & Admin)', 'HR777', 'สิทธิ์จัดการข้อมูลพนักงาน เพิ่ม ลบ แก้ไข รหัสพนักงาน และรหัสผ่านประจำแผนก'),
(3, 'production', 'ฝ่ายผลิตและตรวจสอบคุณภาพ (Production & QC)', 'PRD202', 'งานเตรียมเครื่อง ลงโปรแกรม ประกอบ อัปเกรดสเปก และตรวจเช็ค QC'),
(4, 'stock', 'ฝ่ายคลังสินค้าและอะไหล่ (Stock & Inventory)', 'STK303', 'จัดการสต็อกโน้ตบุ๊ก อะไหล่ชิ้นส่วน รับเข้า-เบิกออก แจ้งเตือนของใกล้หมด'),
(5, 'sales', 'ฝ่ายขายและลูกค้าสัมพันธ์ (Sales & Marketing)', 'SLS404', 'จัดการคำสั่งซื้อ ปิดการขาย ติดต่อลูกค้า ออกใบเสร็จ POS'),
(6, 'technician', 'ฝ่ายช่างเทคนิคและงานซ่อม (Technician Service)', 'TEC505', 'รับงานซ่อม ตรวจเช็คอาการ เสนอราคา ประเมินอะไหล่ อัปเดตสถานะงานซ่อม');

-- 2. Users (Unified: Customers, Employees, Executives, Admins)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NULL,
  `phone` VARCHAR(20) NULL,
  `address` TEXT NULL,
  `role` VARCHAR(30) NOT NULL DEFAULT 'customer',
  `employee_code` VARCHAR(20) NULL,
  `salary` DECIMAL(10,2) DEFAULT 0.00,
  `status` VARCHAR(20) DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Demo Users (Password: 123456)
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `address`, `role`, `employee_code`, `salary`, `status`, `created_at`) VALUES
(1, 'ceo', '$2y$10$4hKk2Q6QWw5V4mOvx3uGTu/6b1u7K8oE9q8cR0mU4W7wP5x0L6KGy', 'ดร.สมเกียรติ ยั่งยืน (ประธานกรรมการ)', 'ceo@technotepro.com', '081-111-2222', 'สำนักงานใหญ่ กรุงเทพฯ', 'president', 'EMP-CEO-01', 95000.00, 'active', NOW()),
(2, 'admin', '$2y$10$4hKk2Q6QWw5V4mOvx3uGTu/6b1u7K8oE9q8cR0mU4W7wP5x0L6KGy', 'น.ส.วิภาดา บริหารกิจ (ผู้จัดการฝ่ายบุคคล/Admin)', 'hr@technotepro.com', '082-222-3333', 'อาคารบริหาร ชั้น 2', 'hr', 'EMP-HR-01', 45000.00, 'active', NOW()),
(3, 'production', '$2y$10$4hKk2Q6QWw5V4mOvx3uGTu/6b1u7K8oE9q8cR0mU4W7wP5x0L6KGy', 'นายมนัส ผลิตดี (หัวหน้าฝ่ายผลิตและเตรียมเครื่อง)', 'production@technotepro.com', '083-333-4444', 'แผนกประกอบ/เตรียมเครื่อง', 'production', 'EMP-PRD-01', 32000.00, 'active', NOW()),
(4, 'stock', '$2y$10$4hKk2Q6QWw5V4mOvx3uGTu/6b1u7K8oE9q8cR0mU4W7wP5x0L6KGy', 'นายธวัชชัย คลังพานิช (เจ้าหน้าที่สต็อกสินค้า)', 'stock@technotepro.com', '084-444-5555', 'คลังสินค้า ชั้น 1', 'stock', 'EMP-STK-01', 28000.00, 'active', NOW()),
(5, 'sales', '$2y$10$4hKk2Q6QWw5V4mOvx3uGTu/6b1u7K8oE9q8cR0mU4W7wP5x0L6KGy', 'น.ส.แพรวา ขายคล่อง (พนักงานฝ่ายขาย/เซลล์)', 'sales@technotepro.com', '085-555-6666', 'หน้าร้าน สาขาหลัก', 'sales', 'EMP-SLS-01', 30000.00, 'active', NOW()),
(6, 'tech', '$2y$10$4hKk2Q6QWw5V4mOvx3uGTu/6b1u7K8oE9q8cR0mU4W7wP5x0L6KGy', 'นายธีรภัทร ช่างฝีมือ (หัวหน้าช่างซ่อม)', 'tech@technotepro.com', '086-666-7777', 'ศูนย์ซ่อม ชั้น 1', 'technician', 'EMP-TEC-01', 35000.00, 'active', NOW()),
(7, 'customer', '$2y$10$4hKk2Q6QWw5V4mOvx3uGTu/6b1u7K8oE9q8cR0mU4W7wP5x0L6KGy', 'นายเอกชัย ใจดี (ลูกค้าทั่วไป)', 'customer@gmail.com', '089-999-8888', '123/45 ถ.พหลโยธิน แขวงลาดยาว เขตจตุจักร กรุงเทพฯ 10900', 'customer', NULL, 0.00, 'active', NOW()),
(8, 'tech2', '$2y$10$4hKk2Q6QWw5V4mOvx3uGTu/6b1u7K8oE9q8cR0mU4W7wP5x0L6KGy', 'นายกิตติศักดิ์ ซ่อมไว (ช่างเทคนิคฮาร์ดแวร์)', 'tech2@technotepro.com', '087-777-8888', 'ศูนย์ซ่อม ชั้น 1', 'technician', 'EMP-TEC-02', 26000.00, 'active', NOW());

-- 3. Product Categories
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `icon` VARCHAR(50) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`) VALUES
(1, 'Gaming Laptops', 'gaming', 'gamepad-2'),
(2, 'Ultrabook & Thin-Light', 'ultrabook', 'feather'),
(3, 'Business & Workstation', 'business', 'briefcase'),
(4, 'Student & General Use', 'student', 'graduation-cap'),
(5, 'Creator & Graphic Design', 'creator', 'palette');

-- 4. Laptop Products (แยกสินค้ามือหนึ่ง / มือสอง พร้อมระบบส่วนลดและแสดงหน้าร้าน)
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `brand` VARCHAR(50) NOT NULL,
  `model` VARCHAR(150) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `cost_price` DECIMAL(10,2) NOT NULL,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `cpu` VARCHAR(100) NOT NULL,
  `gpu` VARCHAR(100) NOT NULL,
  `ram` VARCHAR(50) NOT NULL,
  `storage` VARCHAR(50) NOT NULL,
  `display` VARCHAR(100) NOT NULL,
  `os` VARCHAR(50) NOT NULL DEFAULT 'Windows 11 Home',
  `warranty` VARCHAR(50) NOT NULL DEFAULT '2 Years Onsite',
  `image_url` VARCHAR(255) NULL,
  `description` TEXT,
  `is_featured` TINYINT(1) DEFAULT 0,
  `status` VARCHAR(20) DEFAULT 'in_stock',
  `item_condition` VARCHAR(20) NOT NULL DEFAULT 'new',
  `discount_type` VARCHAR(10) NOT NULL DEFAULT 'none',
  `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `serial_number` VARCHAR(100) NULL,
  `qc_task_id` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products: โน้ตบุ๊กมือหนึ่ง (Brand New) และ โน้ตบุ๊กมือสอง (Certified Used ผ่าน QC)
INSERT INTO `products` (`id`, `category_id`, `brand`, `model`, `price`, `cost_price`, `stock_quantity`, `cpu`, `gpu`, `ram`, `storage`, `display`, `os`, `warranty`, `image_url`, `description`, `is_featured`, `status`, `item_condition`, `discount_type`, `discount_value`, `serial_number`, `created_at`) VALUES
-- --- สินค้ามือหนึ่ง (Brand New) ---
(1, 1, 'ASUS', 'ROG Strix G16 (2025)', 48990.00, 41500.00, 8, 'Intel Core i7-14650HX', 'NVIDIA GeForce RTX 4060 8GB GDDR6', '16GB DDR5 5600MHz', '1TB PCIe 4.0 NVMe M.2 SSD', '16.0" FHD+ 165Hz IPS 100% sRGB', 'Windows 11 Home', '2 Years Warranty + Perfect Warranty 1st Year', 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?auto=format&fit=crop&w=800&q=80', 'สุดยอดโน้ตบุ๊กเกมมิ่งสายพลัง ขุมพลัง Intel Gen 14th จับคู่กับการ์ดจอ RTX 4060 เล่นเกม AAA ปรับสุดลื่นไหล ระบายความร้อน ROG Intelligent Cooling ยอดเยี่ยม', 1, 'in_stock', 'new', 'fixed', 1000.00, 'SN-ROG-G16-2025-01', NOW()),
(2, 1, 'Lenovo', 'Legion Pro 5 16IRX9', 54990.00, 47000.00, 5, 'Intel Core i9-14900HX', 'NVIDIA GeForce RTX 4070 8GB GDDR6', '32GB DDR5 5600MHz', '1TB PCIe 4.0 SSD', '16.0" WQXGA (2560x1600) 240Hz 500nits 100% DCI-P3', 'Windows 11 Home', '3 Years Ultimate Support Onsite', 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=800&q=80', 'Legion Pro 5 สเปกจัดเต็ม i9-14900HX และการ์ดจอ RTX 4070 หน้าจอระดับเทพ 240Hz สว่าง 500nits เหมาะทั้งสตรีมเมอร์และงานตัดต่อระดับโปร', 1, 'in_stock', 'new', 'percent', 5.00, 'SN-LEGION-PRO5-02', NOW()),
(3, 2, 'Apple', 'MacBook Air 15" M3 (2024)', 47900.00, 42000.00, 12, 'Apple M3 chip with 8-core CPU', '10-core GPU, 16-core Neural Engine', '16GB Unified Memory', '512GB SSD Storage', '15.3" Liquid Retina Display True Tone', 'macOS Sonoma', '1 Year Apple Care Warranty', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80', 'บางเบา ทรงพลัง หน้าจอใหญ่เต็มตา 15 นิ้ว แบตเตอรี่อึดใช้งานได้ตลอดทั้งวัน สูงสุด 18 ชม. ทำงานลื่นไหล เงียบสนิทไม่มีเสียงพัดลม', 1, 'in_stock', 'new', 'none', 0.00, 'SN-MBA15-M3-03', NOW()),
(4, 2, 'ASUS', 'Zenbook 14 OLED UX3405', 38990.00, 33500.00, 7, 'Intel Core Ultra 7 155H with AI Boost', 'Intel Arc Graphics', '16GB LPDDR5X', '1TB NVMe M.2 SSD', '14.0" 3K (2880 x 1800) OLED 120Hz 100% DCI-P3', 'Windows 11 Home + MS Office Home & Student 2021', '3 Years Onsite Warranty', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=800&q=80', 'โน้ตบุ๊กบางเบาระดับพรีเมียม ขับเคลื่อนด้วยโปรเซสเซอร์ AI-ready Intel Core Ultra 7 หน้าจอ 3K OLED สีสวยสมจริงระดับสตูดิโอ น้ำหนักเพียง 1.2 กก.', 1, 'in_stock', 'new', 'none', 0.00, 'SN-ZEN-OLED-04', NOW()),
(5, 3, 'Dell', 'Latitude 5440 Business Notebook', 32900.00, 28000.00, 9, 'Intel Core i5-1335U', 'Intel Iris Xe Graphics', '16GB DDR4 3200MHz', '512GB PCIe NVMe SSD', '14.0" FHD (1920x1080) Anti-Glare Non-Touch', 'Windows 11 Pro', '3 Years ProSupport Next Business Day Onsite', 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?auto=format&fit=crop&w=800&q=80', 'โน้ตบุ๊กสำหรับองค์กรและคนทำงาน ทนทาน ปลอดภัยระดับองค์กร มาพร้อม Windows 11 Pro และบริการซ่อมถึงที่ 3 ปีเต็ม', 0, 'in_stock', 'new', 'none', 0.00, 'SN-LAT-5440-05', NOW()),
(6, 4, 'Acer', 'Aspire 5 A515-58M', 18990.00, 16200.00, 15, 'Intel Core i5-1335U', 'Intel Iris Xe Graphics', '16GB LPDDR5', '512GB PCIe Gen4 SSD', '15.6" Full HD IPS Acer ComfyView', 'Windows 11 Home + Office Home & Student', '2 Years Warranty Local', 'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?auto=format&fit=crop&w=800&q=80', 'โน้ตบุ๊กคุ้มค่ายอดนิยมสำหรับนักเรียน นักศึกษา และทำงานทั่วไป ครบครันด้วยพอร์ตเชื่อมต่อ แป้นพิมพ์ตัวเลข พร้อมใช้งานทันที', 1, 'in_stock', 'new', 'none', 0.00, 'SN-ASP-515-06', NOW()),
(7, 1, 'Acer', 'Nitro V 15 ANV15-51', 27990.00, 24000.00, 6, 'Intel Core i5-13420H', 'NVIDIA GeForce RTX 4050 6GB GDDR6', '16GB DDR5 5200MHz', '512GB PCIe M.2 SSD', '15.6" FHD 144Hz IPS SlimBezel', 'Windows 11 Home', '3 Years (Parts & Labor & Onsite)', 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=800&q=80', 'เกมมิ่งงบประหยัด สเปกแรงคุ้มค่า ได้การ์ดจอ RTX 4050 เล่นเกมยอดนิยม ลื่นไหล ภาพคมชัด 144Hz', 0, 'in_stock', 'new', 'none', 0.00, 'SN-NITRO-V15-07', NOW()),
(8, 5, 'HP', 'Envy x360 2-in-1 14', 35990.00, 31000.00, 4, 'AMD Ryzen 7 7730U', 'AMD Radeon Graphics', '16GB LPDDR4x', '1TB PCIe NVMe SSD', '14.0" 2.8K OLED Touch 360-degree flip', 'Windows 11 Home + HP Stylus Pen', '2 Years Onsite Warranty', 'https://images.unsplash.com/photo-1531297484001-80022131f5a1?auto=format&fit=crop&w=800&q=80', 'จอพับได้ 360 องศา ระบบสัมผัสพร้อมปากกา Stylus เหมาะสำหรับงานวาดภาพ ออกแบบกราฟิก นำเสนองานอย่างมืออาชีพ', 0, 'in_stock', 'new', 'none', 0.00, 'SN-ENVY-360-08', NOW()),

-- --- สินค้ามือสอง ผ่าน QC 25 รายการ รับประกันร้าน (Certified Used) ---
(9, 3, 'Lenovo', 'ThinkPad T14 Gen 2 (มือสอง เกรด A+)', 16900.00, 12500.00, 4, 'Intel Core i5-1145G7 vPro', 'Intel Iris Xe Graphics', '16GB DDR4 3200MHz', '512GB NVMe M.2 SSD', '14.0" FHD IPS Anti-Glare 300 nits', 'Windows 11 Pro แท้', 'ประกันร้าน 6 เดือน + สายชาร์จแท้', 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=800&q=80', 'โน้ตบุ๊กทำงานระดับตำนาน แข็งแรงทนทาน สภาพสวย 95%+ แบตเตอรี่สุขภาพดี 91% คีย์บอร์ดพิมพ์นุ่ม ผ่านการทดสอบ QC 25 รายการสมบูรณ์แบบ', 1, 'in_stock', 'used', 'fixed', 1000.00, 'SN-TP-T14-QC-09', NOW()),
(10, 2, 'Apple', 'MacBook Pro 13" M1 (มือสอง สภาพนางฟ้า)', 24900.00, 19500.00, 3, 'Apple M1 Chip 8-core CPU', '8-core GPU, 16-core Neural Engine', '8GB Unified Memory', '256GB SSD', '13.3" Retina Display True Tone Touch Bar', 'macOS Sonoma', 'ประกันร้าน 6 เดือน', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80', 'MacBook Pro ชิป M1 สภาพ 98% แบตเตอรี่รอบชาร์จต่ำ สุขภาพแบต 94% ลื่นไหล ตัดต่อคลิป 4K ได้สบาย ไม่มีรอยตกหล่น จอไร้ตำหนิ', 1, 'in_stock', 'used', 'none', 0.00, 'SN-MBP-M1-QC-10', NOW()),
(11, 1, 'ASUS', 'ROG Zephyrus G14 (มือสอง บางเบาการ์ดจอแยก)', 29900.00, 23000.00, 2, 'AMD Ryzen 7 5800HS', 'NVIDIA GeForce RTX 3060 6GB GDDR6', '16GB DDR4 3200MHz', '512GB NVMe SSD', '14.0" WQHD (2560x1440) 120Hz IPS', 'Windows 11 Home', 'ประกันร้าน 6 เดือน', 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?auto=format&fit=crop&w=800&q=80', 'เกมมิ่งตัวท็อปไซส์พกพา จอคมชัดระดับ 2K น้ำหนักเพียง 1.6 กก. บอร์ดสะอาด พัดลมตรวจเช็คทาซิลิโคนใหม่ เล่นเกมตัดต่อได้ทันที', 1, 'in_stock', 'used', 'fixed', 1500.00, 'SN-ROG-G14-QC-11', NOW()),
(12, 3, 'Dell', 'Latitude 7410 Carbon Fiber (มือสอง เกรดองค์กร)', 13900.00, 9800.00, 5, 'Intel Core i5-10310U', 'Intel UHD Graphics', '16GB DDR4', '256GB NVMe SSD', '14.0" FHD Narrow Border Display', 'Windows 11 Pro', 'ประกันร้าน 6 เดือน', 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?auto=format&fit=crop&w=800&q=80', 'บอดี้ลายคาร์บอนไฟเบอร์เกรดพรีเมียม สวยเบา 1.2 กก. มีสแกนลายนิ้วมือ กล้องมีม่านปิดเพื่อความปลอดภัย เหมาะใช้งานบัญชีและเอกสาร', 0, 'in_stock', 'used', 'none', 0.00, 'SN-DL-7410-QC-12', NOW());

-- 5. Spare Parts & Repair Components
DROP TABLE IF EXISTS `spare_parts`;
CREATE TABLE `spare_parts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `part_code` VARCHAR(30) NOT NULL UNIQUE,
  `part_name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `cost_price` DECIMAL(10,2) NOT NULL,
  `sell_price` DECIMAL(10,2) NOT NULL,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `min_threshold` INT DEFAULT 3,
  `supplier` VARCHAR(100) NULL,
  `status` VARCHAR(20) DEFAULT 'available',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `spare_parts` (`id`, `part_code`, `part_name`, `category`, `cost_price`, `sell_price`, `stock_quantity`, `min_threshold`, `supplier`, `status`, `created_at`) VALUES
(1, 'PRT-RAM-D4-8', 'RAM DDR4 8GB 3200MHz SODIMM Kingston', 'RAM', 650.00, 1150.00, 18, 5, 'Synnex Thailand', 'available', NOW()),
(2, 'PRT-RAM-D5-16', 'RAM DDR5 16GB 5600MHz SODIMM Crucial', 'RAM', 1450.00, 2390.00, 12, 4, 'Ingram Micro', 'available', NOW()),
(3, 'PRT-SSD-NV-1TB', 'SSD M.2 NVMe PCIe 4.0 1TB Samsung 980 Pro', 'SSD', 2600.00, 3790.00, 15, 5, 'Synnex Thailand', 'available', NOW()),
(4, 'PRT-SSD-NV-512', 'SSD M.2 NVMe PCIe 3.0 512GB WD Blue SN580', 'SSD', 1250.00, 1850.00, 20, 5, 'Advice Distribution', 'available', NOW()),
(5, 'PRT-SCR-156-FHD', 'จอโน้ตบุ๊ก 15.6" FHD IPS 30-pin 144Hz Slim', 'Screen', 1800.00, 2900.00, 4, 3, 'China Direct Parts', 'available', NOW()),
(6, 'PRT-SCR-140-FHD', 'จอโน้ตบุ๊ก 14.0" FHD IPS 30-pin Slim Border', 'Screen', 1600.00, 2600.00, 2, 3, 'China Direct Parts', 'low_stock', NOW()),
(7, 'PRT-BAT-ASUS-G15', 'แบตเตอรี่แท้ ASUS ROG Strix / TUF Gaming 90Wh', 'Battery', 1500.00, 2400.00, 6, 2, 'ASUS Service Partner', 'available', NOW()),
(8, 'PRT-BAT-LEN-L5', 'แบตเตอรี่แท้ Lenovo Legion 5 / 5 Pro 80Wh', 'Battery', 1450.00, 2350.00, 3, 2, 'Lenovo Spares Dept', 'available', NOW()),
(9, 'PRT-KEY-ACR-N5', 'คีย์บอร์ดเรืองแสง RGB Acer Nitro 5 Thai/Eng', 'Keyboard', 600.00, 1200.00, 7, 3, 'Advice Distribution', 'available', NOW()),
(10, 'PRT-FAN-DUAL-G16', 'ชุดพัดลมระบายความร้อน Dual Fan ASUS ROG G16', 'Cooling Fan', 750.00, 1450.00, 5, 2, 'China Direct Parts', 'available', NOW()),
(11, 'PRT-THM-KRY-1G', 'ซิลิโคนนำความร้อนเกรดท็อป Thermal Grizzly Kryonaut 1g', 'Thermal Paste', 180.00, 390.00, 25, 5, 'JIB Supply', 'available', NOW()),
(12, 'PRT-ADP-TYPEC-100', 'อะแดปเตอร์ชาร์จด่วน Type-C GaN 100W Universal PD', 'Adapter', 700.00, 1290.00, 10, 4, 'Baseus Thailand', 'available', NOW());

-- 6. Discount Coupons (ตารางโค้ดส่วนลดโปรโมชั่นสำหรับลูกค้า)
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(200) NULL,
  `discount_type` VARCHAR(10) NOT NULL DEFAULT 'percent',
  `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `min_order` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `expires_at` DATE NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `coupons` (`id`, `code`, `description`, `discount_type`, `discount_value`, `min_order`, `active`, `expires_at`, `created_at`) VALUES
(1, 'SAVE500', 'ส่วนลด 500 บาท เมื่อซื้อครบ 20,000 บาท', 'fixed', 500.00, 20000.00, 1, '2028-12-31', NOW()),
(2, 'WELCOME10', 'ส่วนลดพิเศษ 10% สำหรับลูกค้าใหม่', 'percent', 10.00, 0.00, 1, '2028-12-31', NOW()),
(3, 'TECHNOTE1000', 'ส่วนลดเงินสด 1,000 บาท เมื่อซื้อครบ 35,000 บาท', 'fixed', 1000.00, 35000.00, 1, '2028-12-31', NOW()),
(4, 'SECONDHAND5', 'ส่วนลด 5% เมื่อซื้อโน้ตบุ๊กมือสอง QC ผ่าน', 'percent', 5.00, 10000.00, 1, '2028-12-31', NOW());

-- 7. Sales Orders
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_code` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(20) NOT NULL,
  `customer_email` VARCHAR(100) NULL,
  `shipping_address` TEXT NOT NULL,
  `subtotal_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `coupon_code` VARCHAR(50) NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(50) DEFAULT 'Transfer',
  `payment_status` VARCHAR(30) DEFAULT 'pending',
  `order_status` VARCHAR(30) DEFAULT 'pending',
  `sales_rep_id` INT NULL,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `orders` (`id`, `order_code`, `user_id`, `customer_name`, `customer_phone`, `customer_email`, `shipping_address`, `subtotal_amount`, `discount_amount`, `coupon_code`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `sales_rep_id`, `notes`, `created_at`) VALUES
(1, 'ORD-2026-001', 7, 'นายเอกชัย ใจดี', '089-999-8888', 'customer@gmail.com', '123/45 ถ.พหลโยธิน แขวงลาดยาว เขตจตุจักร กรุงเทพฯ 10900', 48990.00, 0.00, NULL, 48990.00, 'Transfer', 'paid', 'completed', 5, 'ลูกค้าสั่งซื้อหน้าร้าน พร้อมของแถมกระเป๋าเป้และเมาส์ไร้สาย', '2026-10-25 10:15:00'),
(2, 'ORD-2026-002', NULL, 'นางสาวกนกวรรณ รุ่งเรือง', '081-345-6789', 'kanokwan@outlook.com', '88/12 ซอยสุขุมวิท 55 แขวงคลองตันเหนือ เขตวัฒนา กรุงเทพฯ 10110', 47900.00, 500.00, 'SAVE500', 47400.00, 'CreditCard', 'paid', 'processing', 5, 'ออเดอร์ออนไลน์ ใช้โค้ด SAVE500 รอนัดรับสินค้าหรือส่งด่วน Messenger', '2026-10-28 14:30:00'),
(3, 'ORD-2026-003', NULL, 'นายปิยะพงษ์ ยอดเยี่ยม', '092-888-7711', 'piyapong@gmail.com', '45 หมู่ 3 ต.สุเทพ อ.เมือง จ.เชียงใหม่ 50200', 38990.00, 0.00, NULL, 38990.00, 'Transfer', 'paid', 'shipped', 5, 'จัดส่ง Kerry Express หมายเลข KEX12345678TH', '2026-10-29 09:20:00');

-- 8. Order Items
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `subtotal` DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `price`, `quantity`, `subtotal`) VALUES
(1, 1, 1, 'ASUS ROG Strix G16 (2025)', 48990.00, 1, 48990.00),
(2, 2, 3, 'Apple MacBook Air 15" M3 (2024)', 47900.00, 1, 47900.00),
(3, 3, 4, 'ASUS Zenbook 14 OLED UX3405', 38990.00, 1, 38990.00);

-- 9. Repair Tickets (บริการรับซ่อมโน้ตบุ๊ก)
DROP TABLE IF EXISTS `repair_tickets`;
CREATE TABLE `repair_tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_code` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(20) NOT NULL,
  `customer_email` VARCHAR(100) NULL,
  `brand` VARCHAR(50) NOT NULL,
  `model` VARCHAR(100) NOT NULL,
  `serial_number` VARCHAR(100) NULL,
  `issue_description` TEXT NOT NULL,
  `diagnosis` TEXT NULL,
  `technician_id` INT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'received',
  `estimated_cost` DECIMAL(10,2) DEFAULT 0.00,
  `labor_fee` DECIMAL(10,2) DEFAULT 500.00,
  `parts_fee` DECIMAL(10,2) DEFAULT 0.00,
  `total_cost` DECIMAL(10,2) DEFAULT 500.00,
  `technician_notes` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `repair_tickets` (`id`, `ticket_code`, `user_id`, `customer_name`, `customer_phone`, `customer_email`, `brand`, `model`, `serial_number`, `issue_description`, `diagnosis`, `technician_id`, `status`, `estimated_cost`, `labor_fee`, `parts_fee`, `total_cost`, `technician_notes`, `created_at`, `completed_at`) VALUES
(1, 'REP-2026-001', 7, 'นายเอกชัย ใจดี', '089-999-8888', 'customer@gmail.com', 'Acer', 'Nitro 5 AN515-55', 'NXQ7MST0019284', 'เครื่องร้อนผิดปกติ พัดลมเสียงดังมาก ดับเองระหว่างเล่นเกม', 'ตรวจพบซิลิโคนแห้งแข็ง และแกนพัดลมระบายความร้อนฝั่ง CPU ฝืด จำเป็นต้องเปลี่ยนชุดพัดลมและทาซิลิโคนใหม่', 6, 'ready_for_pickup', 1900.00, 450.00, 1450.00, 1900.00, 'เปลี่ยนพัดลมระบายความร้อนเบิกจากสต็อก PRT-FAN-DUAL-G16 และหยอด Thermal Grizzly Kryonaut เรียบร้อย อุณหภูมิ Full Load ลดลงเหลือ 74C ทดสอบผ่าน QC', '2026-10-26 11:00:00', '2026-10-28 16:00:00'),
(2, 'REP-2026-002', NULL, 'น.ส.ชลธิชา สิทธิผล', '083-456-7890', 'chonthicha@hotmail.com', 'ASUS', 'VivoBook S14 S433', 'K9N0CV01E234567', 'ทำน้ำหกใส่คีย์บอร์ด แป้นกดเบิ้ลและบางปุ่มกดไม่ติด', 'คีย์บอร์ดลัดวงจร เมนบอร์ดปลอดภัยไม่มีคราบน้ำลาม ทำความสะอาดคราบน้ำและเปลี่ยนแผงคีย์บอร์ดใหม่', 6, 'repairing', 1700.00, 500.00, 1200.00, 1700.00, 'สั่งเบิกคีย์บอร์ดใหม่ กำลังทำการถอดประกอบและเชื่อมแผงคีย์บอร์ดใหม่', '2026-10-28 15:20:00', NULL),
(3, 'REP-2026-003', NULL, 'นายธนาธิป สุขสวัสดิ์', '095-123-9988', 'thanathip@gmail.com', 'Lenovo', 'IdeaPad Gaming 3', 'PF2X9901M88', 'เปิดเครื่องไฟติดแต่หน้าจอไม่ขึ้นภาพ จอดำสนิท', 'กำลังตรวจเช็ควงจรภาคจ่ายไฟหน้าจอและทดสอบต่อจอแยก HDMI ภาพออกปกติ คาดว่าเป็นที่สายแพรจอหรือพาเนลหน้าจอเสีย', 8, 'checking', 2500.00, 500.00, 2000.00, 2500.00, 'ตรวจเช็คสายแพรจอเบื้องต้น พบรอยหักงอช่วงบานพับ ประเมินราคาแจ้งลูกค้าอนุมัติ', '2026-10-29 13:40:00', NULL),
(4, 'REP-2026-004', NULL, 'นายวรพจน์ มิตรภาพ', '088-776-5544', 'worapoj@gmail.com', 'Dell', 'Inspiron 3511', 'SN-DL-883921', 'เครื่องทำงานช้ามาก บูตวินโดวส์เกือบ 5 นาที ต้องการเพิ่มความเร็ว', 'เครื่องเดิมใช้ HDD 1TB จานหมุน ความเร็วอ่านเขียนต่ำ แนะนำอัปเกรดเป็น M.2 NVMe SSD 512GB และโคลนระบบหรือลงวินโดวส์ใหม่', 6, 'waiting_parts', 2350.00, 500.00, 1850.00, 2350.00, 'ลูกค้าอนุมัติแล้ว รอเบิก SSD PRT-SSD-NV-512 เข้าคิวติดตั้งช่วงบ่าย', '2026-10-30 09:10:00', NULL);

-- 10. Production / Assembly / QC Tasks (ฝ่ายผลิต)
DROP TABLE IF EXISTS `production_tasks`;
CREATE TABLE `production_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_code` VARCHAR(30) NOT NULL UNIQUE,
  `product_id` INT NULL,
  `notebook_name` VARCHAR(150) NOT NULL,
  `assigned_to` INT NULL,
  `task_type` VARCHAR(50) NOT NULL,
  `spec_upgrade_details` TEXT,
  `qc_checklist` TEXT,
  `status` VARCHAR(30) DEFAULT 'pending',
  `serial_number` VARCHAR(100) NULL,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `production_tasks` (`id`, `task_code`, `product_id`, `notebook_name`, `assigned_to`, `task_type`, `spec_upgrade_details`, `qc_checklist`, `status`, `serial_number`, `notes`, `created_at`, `completed_at`) VALUES
(1, 'PRD-2026-001', 1, 'ASUS ROG Strix G16 (Lot A-01)', 3, 'New Machine Setup', 'ติดตั้ง Windows 11 Home ลิขสิทธิ์แท้, ลง ROG Armoury Crate, อัปเดต BIOS v315, อัปเดตไดรเวอร์การ์ดจอ Nvidia GameReady ล่าสุด', '{"screen":"pass","keyboard":"pass","trackpad":"pass","wifi_bt":"pass","battery_charge":"pass","ports_io":"pass","stress_test_15m":"pass"}', 'ready_to_deliver', 'SN-ROG-2026-A0991', 'ผ่านการทดสอบ Benchmark และ Burn-in Test 15 นาที อุณหภูมิเสถียร พร้อมส่งมอบหน้าร้าน', '2026-10-27 09:00:00', '2026-10-27 15:30:00'),
(2, 'PRD-2026-002', 2, 'Lenovo Legion Pro 5 (Custom RAM)', 3, 'Custom Upgrade', 'อัปเกรด RAM เพิ่มเป็น 64GB DDR5 (Crucial 32GBx2) สำหรับลูกค้าสายสถาปัตย์ 3D Rendering', '{"screen":"pass","keyboard":"pass","trackpad":"pass","wifi_bt":"pass","battery_charge":"pass","ports_io":"pass","memtest_64gb":"pass"}', 'qc_passed', 'SN-LEGION-992318', 'ประกอบเสร็จเรียบร้อย ตรวจเช็ค Dual Channel ทำงานสมบูรณ์ รอฝ่ายขายแจ้งลูกค้าส่งมอบ', '2026-10-29 10:30:00', '2026-10-29 16:00:00'),
(3, 'PRD-2026-003', 6, 'Acer Aspire 5 A515 (เตรียมสต็อกหน้าร้าน 5 เครื่อง)', 3, 'New Machine Setup', 'ลงระบบปฏิบัติการ Windows 11 Home + MS Office แท้ + โปรแกรมพื้นฐานสำหรับนักศึกษาและสำนักงาน', '{"screen":"pending","keyboard":"pending","trackpad":"pending","wifi_bt":"pending","battery_charge":"pending"}', 'in_progress', 'SN-ASP5-LOT4-01-05', 'กำลัง Clone Image ระบบและติดตั้งไดรเวอร์ทีละชุด', '2026-10-30 08:30:00', NULL);

-- 11. Stock In/Out Transactions
DROP TABLE IF EXISTS `stock_transactions`;
CREATE TABLE `stock_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(20) NOT NULL,
  `item_type` VARCHAR(20) NOT NULL,
  `item_id` INT NOT NULL,
  `item_name` VARCHAR(150) NOT NULL,
  `quantity` INT NOT NULL,
  `reason` VARCHAR(100) NOT NULL,
  `reference_code` VARCHAR(50) NULL,
  `performed_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock_transactions` (`id`, `type`, `item_type`, `item_id`, `item_name`, `quantity`, `reason`, `reference_code`, `performed_by`, `created_at`) VALUES
(1, 'in', 'product', 1, 'ASUS ROG Strix G16 (2025)', 10, 'รับสินค้าเข้าสต็อกจากตัวแทนจำหน่าย Synnex', 'PO-2026-10-01', 4, '2026-10-20 10:00:00'),
(2, 'out', 'product', 1, 'ASUS ROG Strix G16 (2025)', 1, 'ตัดสต็อกจากการขายออเดอร์หน้าร้าน', 'ORD-2026-001', 4, '2026-10-25 10:30:00'),
(3, 'in', 'spare_part', 3, 'SSD M.2 NVMe PCIe 4.0 1TB Samsung 980 Pro', 20, 'สั่งซื้ออะไหล่สำรองเข้าศูนย์บริการ', 'PO-PART-2026-05', 4, '2026-10-22 14:00:00'),
(4, 'out', 'spare_part', 10, 'ชุดพัดลมระบายความร้อน Dual Fan ASUS ROG G16', 1, 'เบิกชิ้นส่วนเพื่อใช้ในงานซ่อมโน้ตบุ๊ก', 'REP-2026-001', 4, '2026-10-27 11:30:00');

-- 12. System Announcements & Executive Messages
DROP TABLE IF EXISTS `system_announcements`;
CREATE TABLE `system_announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `author_name` VARCHAR(100) NOT NULL,
  `priority` VARCHAR(20) DEFAULT 'normal',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_announcements` (`id`, `title`, `message`, `author_name`, `priority`, `created_at`) VALUES
(1, 'นโยบายยกระดับมาตรฐานงานบริการและศูนย์ซ่อมประจำไตรมาส', 'ขอให้ทุกแผนกตรวจสอบความพร้อมของระบบและการส่งมอบงานแก่ลูกค้าอย่างรวดเร็ว โดยฝ่ายช่างต้องแจ้งผลตรวจเช็คภายใน 24 ชม. และฝ่ายสต็อกหมั่นตรวจสอบอะไหล่ยอดนิยมไม่ให้ขาด เพื่อสร้างความประทับใจสูงสุดให้แก่ลูกค้าทุกท่าน', 'ดร.สมเกียรติ ยั่งยืน (ประธานกรรมการ)', 'important', NOW()),
(2, 'แจ้งปรับปรุงระบบและตรวจนับสต็อกสิ้นเดือน', 'ฝ่ายสต็อกและฝ่ายขายจะทำการตรวจนับสินค้าคงคลังประจำเดือนในวันศุกร์นี้เวลา 18:00 น. ขอให้เตรียมเอกสารให้พร้อม', 'น.ส.วิภาดา บริหารกิจ (HR/Admin)', 'normal', NOW());
