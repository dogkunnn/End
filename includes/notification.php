<?php
$flash = get_flash();
if ($flash):
    $colors = [
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
        'danger'  => 'bg-rose-50 border-rose-200 text-rose-800',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
        'info'    => 'bg-sky-50 border-sky-200 text-sky-800'
    ];
    $icons = [
        'success' => 'check-circle-2',
        'danger'  => 'alert-octagon',
        'warning' => 'alert-triangle',
        'info'    => 'info'
    ];
    $type = $flash['type'] ?? 'info';
    $c = $colors[$type] ?? $colors['info'];
    $icon = $icons[$type] ?? $icons['info'];
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
    <div class="p-4 rounded-xl border flex items-center justify-between shadow-xs <?php echo $c; ?> animate-fade-in">
        <div class="flex items-center space-x-3">
            <i data-lucide="<?php echo $icon; ?>" class="w-5 h-5 flex-shrink-0"></i>
            <span class="text-sm font-medium"><?php echo htmlspecialchars($flash['message']); ?></span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="p-1 rounded-lg hover:bg-black/5 opacity-70 hover:opacity-100 transition">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
</div>
<?php endif; ?>
