<?php
if (!defined('ACCESS_KEY') || ACCESS_KEY !== 'ngapain?') {
    header("HTTP/1.0 403 Forbidden");
    exit('Akses ditolak.');
}
?>
<header id="navbar" 
        x-data="{ mobileMenuOpen: false, scrolled: false }" 
        @scroll.window="scrolled = (window.pageYOffset > 20)"
        :class="{ 'bg-light/90 backdrop-blur-md shadow-sm py-4': scrolled, 'bg-transparent py-6': !scrolled }"
        class="fixed top-0 w-full z-50 transition-all duration-300 border-b border-transparent"
        :class="{ 'border-gray-200': scrolled }">
        
        <div class="max-w-6xl mx-auto px-6 flex justify-between items-center">
            <a href="#" class="flex items-center gap-2 z-50 relative">
                <?php if($company['logo']): ?>
                    <img src="assets/img/<?= htmlspecialchars($company['logo']) ?>" alt="Logo" class="h-10 w-auto object-contain">
                <?php else: ?>
                    <span class="text-xl font-bold" :class="{ 'text-gray-800': scrolled, 'text-white': !scrolled && !mobileMenuOpen, 'text-gray-800': mobileMenuOpen }"><?= htmlspecialchars($company['name']) ?></span>
                <?php endif; ?>
            </a>

            <nav class="hidden md:flex space-x-10 items-center">
                <a href="#hero" :class="scrolled ? 'text-gray-600 hover:text-primary' : 'text-white/90 hover:text-white'" class="font-medium text-xs uppercase tracking-widest transition">Beranda</a>
                <a href="#products" :class="scrolled ? 'text-gray-600 hover:text-primary' : 'text-white/90 hover:text-white'" class="font-medium text-xs uppercase tracking-widest transition">Unit</a>
                <a href="#gallery" :class="scrolled ? 'text-gray-600 hover:text-primary' : 'text-white/90 hover:text-white'" class="font-medium text-xs uppercase tracking-widest transition">Galeri</a>
                <a href="#faq" :class="scrolled ? 'text-gray-600 hover:text-primary' : 'text-white/90 hover:text-white'" class="font-medium text-xs uppercase tracking-widest transition">FAQ</a>
                <a href="#contact" :class="scrolled ? 'text-gray-600 hover:text-primary' : 'text-white/90 hover:text-white'" class="font-medium text-xs uppercase tracking-widest transition">Kontak</a>

                <a href="login" class="bg-primary hover:bg-green-600 text-white px-6 py-2.5 rounded-full font-semibold text-xs transition shadow-lg shadow-primary/30 transform hover:-translate-y-0.5">Masuk</a>
            </nav>

            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden z-50 focus:outline-none transition-transform active:scale-95">
                <i class="fa-solid fa-bars text-xl" :class="{ 'text-gray-800': mobileMenuOpen || scrolled, 'text-white': !mobileMenuOpen && !scrolled, 'fa-xmark': mobileMenuOpen, 'fa-bars': !mobileMenuOpen }"></i>
            </button>
        </div>

        <div x-show="mobileMenuOpen" 
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-full"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-full"
             class="fixed inset-0 bg-light z-40 flex flex-col justify-center items-center h-screen w-screen text-center">
            
            <ul class="w-full max-w-xs space-y-8">
                <li class="border-b border-gray-200 pb-2"><a @click="mobileMenuOpen = false" href="#hero" class="text-2xl font-light text-gray-800 hover:text-primary block">Beranda</a></li>
                <li class="border-b border-gray-200 pb-2"><a @click="mobileMenuOpen = false" href="#products" class="text-2xl font-light text-gray-800 hover:text-primary block">Unit</a></li>
                <li class="border-b border-gray-200 pb-2"><a @click="mobileMenuOpen = false" href="#gallery" class="text-2xl font-light text-gray-800 hover:text-primary block">Galeri</a></li>
                <li class="border-b border-gray-200 pb-2"><a @click="mobileMenuOpen = false" href="#faq" class="text-2xl font-light text-gray-800 hover:text-primary block">FAQ</a></li>
                <li class="border-b border-gray-200 pb-2"><a @click="mobileMenuOpen = false" href="#contact" class="text-2xl font-light text-gray-800 hover:text-primary block">Kontak</a></li>

                <li><a @click="mobileMenuOpen = false" href="login" class="inline-block bg-primary text-white px-10 py-4 rounded-full font-bold shadow-xl shadow-primary/20 mt-4">Login</a></li>
            </ul>
        </div>
    </header>
