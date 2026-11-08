    </main>

    <!-- Global Footer -->
    <footer class="bg-slate-900 text-slate-400 text-sm mt-16 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Col 1: Shop Brand -->
                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-xl bg-sky-500 text-white flex items-center justify-center font-bold">
                            <i data-lucide="laptop" class="w-5 h-5"></i>
                        </div>
                        <span class="text-xl font-bold text-white tracking-tight">TechNote Pro</span>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        ศูนย์รวมโน้ตบุ๊กคุณภาพสูง มือ 1 สเปกแรงสำหรับเล่นเกม ทำงาน กราฟิก พร้อมศูนย์บริการซ่อม อัปเกรด และแก้ไขปัญหาฮาร์ดแวร์โดยช่างผู้เชี่ยวชาญ
                    </p>
                    <div class="flex space-x-3 text-slate-400">
                        <span class="p-2 rounded-lg bg-slate-800 hover:text-white cursor-pointer"><i data-lucide="facebook" class="w-4 h-4"></i></span>
                        <span class="p-2 rounded-lg bg-slate-800 hover:text-white cursor-pointer"><i data-lucide="instagram" class="w-4 h-4"></i></span>
                        <span class="p-2 rounded-lg bg-slate-800 hover:text-white cursor-pointer"><i data-lucide="message-circle" class="w-4 h-4"></i></span>
                    </div>
                </div>

                <!-- Col 2: Services -->
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4">บริการหลักของเรา</h4>
                    <ul class="space-y-2.5 text-xs">
                        <li><a href="<?php echo base_url('products.php'); ?>" class="hover:text-sky-400 transition">จำหน่ายโน้ตบุ๊กเกมมิ่ง & บางเบา</a></li>
                        <li><a href="<?php echo base_url('repair-service.php'); ?>" class="hover:text-sky-400 transition">แจ้งซ่อม เปลี่ยนจอ เปลี่ยนแบตเตอรี่</a></li>
                        <li><a href="<?php echo base_url('repair-service.php'); ?>" class="hover:text-sky-400 transition">อัปเกรดความเร็ว RAM & SSD NVMe</a></li>
                        <li><a href="<?php echo base_url('repair-service.php'); ?>" class="hover:text-sky-400 transition">ทำความสะอาด ทาซิลิโคนลดความร้อน</a></li>
                        <li><a href="<?php echo base_url('repair-track.php'); ?>" class="hover:text-sky-400 transition">ติดตามสถานะงานซ่อมแบบเรียลไทม์</a></li>
                    </ul>
                </div>

                <!-- Col 3: Department Fast Links -->
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4">
                        <?php echo (isset($currentUser) && $currentUser['role'] === 'customer') ? 'บริการสำหรับลูกค้า' : 'ระบบปฏิบัติงานภายในร้าน'; ?>
                    </h4>
                    <ul class="space-y-2 text-xs">
                        <?php if (isset($currentUser) && $currentUser['role'] === 'customer'): ?>
                            <li><a href="<?php echo base_url('customer/dashboard.php'); ?>" class="hover:text-sky-400 transition flex items-center"><i data-lucide="user" class="w-3.5 h-3.5 mr-1.5 text-sky-400"></i> บัญชีและประวัติของฉัน</a></li>
                            <li><a href="<?php echo base_url('customer/dashboard.php#orders'); ?>" class="hover:text-sky-400 transition flex items-center"><i data-lucide="shopping-bag" class="w-3.5 h-3.5 mr-1.5 text-sky-400"></i> ตรวจสอบคำสั่งซื้อของฉัน</a></li>
                            <li><a href="<?php echo base_url('repair-track.php'); ?>" class="hover:text-sky-400 transition flex items-center"><i data-lucide="search" class="w-3.5 h-3.5 mr-1.5 text-sky-400"></i> เช็คสถานะงานซ่อมเรียลไทม์</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo base_url('login.php'); ?>" class="hover:text-sky-400 transition flex items-center"><i data-lucide="log-in" class="w-3.5 h-3.5 mr-1.5 text-slate-500"></i> เข้าสู่ระบบรวม (พนักงาน/ลูกค้า)</a></li>
                            <li><a href="<?php echo base_url('install.php'); ?>" class="hover:text-sky-400 transition flex items-center"><i data-lucide="database" class="w-3.5 h-3.5 mr-1.5 text-purple-400"></i> ติดตั้ง/รีเซ็ตฐานข้อมูล (ตัวช่วยอาจารย์)</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Col 4: Contact -->
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4">ติดต่อและที่ตั้งศูนย์บริการ</h4>
                    <p class="text-xs text-slate-400 leading-relaxed mb-3">
                        เลขที่ 99/9 ศูนย์การค้าคอมพิวเตอร์มอลล์ ถ.พหลโยธิน แขวงลาดยาว เขตจตุจักร กรุงเทพฯ 10900
                    </p>
                    <p class="text-xs text-slate-300 font-medium">โทร: 02-999-8888, 089-999-8888</p>
                    <p class="text-xs text-slate-400">Line Official: @technotepro</p>
                    <p class="text-xs text-slate-400 mt-1">เวลาทำการ: ทุกวัน 09:30 - 20:00 น.</p>
                </div>
            </div>

            <div class="mt-12 pt-6 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
                <p>&copy; <?php echo date('Y'); ?> TechNote Pro. ระบบจำหน่ายและบริการซ่อมโน้ตบุ๊ก (โครงงานโปรเจ็คจบการศึกษา)</p>
                <div class="flex items-center space-x-4">
                    <span>Clean Minimalist Design</span>
                    <span>•</span>
                    <span>Fully Responsive</span>
                    <span>•</span>
                    <span>PHP & PDO MySQL</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript helpers -->
    <script src="<?php echo base_url('assets/js/app.js'); ?>"></script>
    <script>
        // Init Lucide Icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Toggle user dropdown
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdownContent');
            if (dropdown) {
                dropdown.classList.toggle('hidden');
            }
        }

        // Toggle mobile drawer
        function toggleMobileMenu() {
            const mobile = document.getElementById('mobileMenu');
            if (mobile) {
                mobile.classList.toggle('hidden');
            }
        }

        // Close dropdown when clicked outside
        window.addEventListener('click', function(e) {
            const userMenu = document.getElementById('userMenuDropdown');
            const dropdown = document.getElementById('userDropdownContent');
            if (userMenu && dropdown && !userMenu.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
