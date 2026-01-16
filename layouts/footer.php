<?php
if (!defined('ACCESS_KEY') || ACCESS_KEY !== 'ngapain?') {
    header("HTTP/1.0 403 Forbidden");
    exit('Akses ditolak.');
}
?>
<footer id="footer" class="bg-light border-t border-gray-200 pt-16 pb-12">
        <div class="max-w-6xl mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between gap-12 mb-16">
                <div class="max-w-xs">
                    <a href="#" class="flex items-center gap-2 mb-4">
                        <?php if($company['logo']): ?>
                            <img src="assets/img/<?= htmlspecialchars($company['logo']) ?>" alt="Logo" class="h-8 w-auto object-contain grayscale opacity-80 hover:grayscale-0 hover:opacity-100 transition">
                        <?php else: ?>
                            <span class="text-lg font-bold text-gray-700"><?= htmlspecialchars($company['name']) ?></span>
                        <?php endif; ?>
                    </a>
                    <p class="text-gray-400 text-sm leading-relaxed">Hunian nyaman dan strategis untuk mendukung produktivitas Anda.</p>
                </div>

                <div class="flex gap-16 flex-wrap">
                    <div>
                        <h4 class="font-bold text-gray-800 mb-4 text-sm">Navigasi</h4>
                        <ul class="space-y-3 text-sm text-gray-500">
                            <li><a href="/" class="hover:text-primary transition">Beranda</a></li>
                            <li><a href="#products" class="hover:text-primary transition">Unit</a></li>
                            <li><a href="#gallery" class="hover:text-primary transition">Galeri</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 mb-4 text-sm">Sosial Media</h4>
                        <div class="flex gap-3">
                            <?php if($company['instagram_url']): ?>
                            <a href="<?= htmlspecialchars($company['instagram_url']) ?>" class="w-9 h-9 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-primary hover:text-white hover:border-primary transition"><i class="fa-brands fa-instagram"></i></a>
                            <?php endif; ?>
                            <?php if($company['facebook_url']): ?>
                            <a href="<?= htmlspecialchars($company['facebook_url']) ?>" class="w-9 h-9 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-primary hover:text-white hover:border-primary transition"><i class="fa-brands fa-facebook-f"></i></a>
                            <?php endif; ?>
                            <?php if($company['tiktok_url']): ?>
                            <a href="<?= htmlspecialchars($company['tiktok_url']) ?>" class="w-9 h-9 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-primary hover:text-white hover:border-primary transition"><i class="fa-brands fa-tiktok"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-8 text-center md:text-left flex flex-col md:flex-row justify-between items-center text-xs text-gray-400">
                <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($company['name']) ?>. All rights reserved.</p>
                <p class="mt-2 md:mt-0">Designed elegantly.</p>
            </div>
        </div>
    </footer>
