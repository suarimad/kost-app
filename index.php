<?php
// Section: Configuration & Database
define('ACCESS_KEY', 'ngapain?');

// Helper function untuk Base URL (Penting buat SEO Canonical & OG)
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname($script);
    if ($path === '/' || $path === '\\') $path = '';
    return $protocol . "://" . $host . $path . "/";
}

$baseUrl = getBaseUrl();

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}
loadEnv(__DIR__ . '/.env');

try {
    $dsn = "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8mb4";
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch Data
$company = $pdo->query("SELECT * FROM company_data LIMIT 1")->fetch();
// Ambil SEO spesifik untuk halaman home
$seo = $pdo->query("SELECT * FROM seo_settings WHERE page_name = 'home' LIMIT 1")->fetch();

// Fallback kalau table SEO kosong (biar gak error)
if (!$seo) {
    $seo = [
        'meta_title' => 'Kost Eksklusif',
        'meta_description' => 'Deskripsi default website kost.',
        'meta_keywords' => 'kost, sewa',
        'og_image' => 'default.jpg',
        'favicon' => 'favicon.ico',
        'robots' => 'index, follow',
        'author' => 'Admin'
    ];
}

$galleries = $pdo->query("SELECT * FROM galleries WHERE deleted_at IS NULL ORDER BY created_at DESC")->fetchAll();
$faqs = $pdo->query("SELECT * FROM faqs WHERE deleted_at IS NULL ORDER BY id ASC")->fetchAll();

// Note: Pastikan tabel rooms memiliki kolom 'slug'
$roomsQuery = $pdo->query("SELECT r.*, c.name as category_name, f.bed, f.desk, f.storage, f.window, f.fan, f.ac, f.mirror FROM rooms r LEFT JOIN categories c ON r.category_id = c.id LEFT JOIN room_facilities f ON r.id = f.room_id WHERE r.deleted_at IS NULL ORDER BY r.is_booked ASC, r.price ASC");
$rooms = $roomsQuery->fetchAll();

// Process Rooms Data
$minPrice = 0;
$maxPrice = 0;

foreach ($rooms as &$room) {
    $stmt = $pdo->prepare("SELECT image, caption FROM room_images WHERE room_id = ? AND deleted_at IS NULL");
    $stmt->execute([$room['id']]);
    $rawImages = $stmt->fetchAll();
    
    $room['images'] = array_map(function($img) {
        return 'assets/img/rooms/' . $img['image'];
    }, $rawImages);

    if (empty($room['images'])) {
        $room['images'] = ['https://via.placeholder.com/800x600?text=No+Image']; 
    }

    $room['final_price'] = $room['price'];
    if (!empty($room['discount']) && $room['discount'] > 0) {
        $room['final_price'] = $room['price'] - ($room['price'] * ($room['discount'] / 100));
    }

    // Untuk Schema JSON-LD price range
    if ($minPrice == 0 || $room['final_price'] < $minPrice) $minPrice = $room['final_price'];
    if ($room['final_price'] > $maxPrice) $maxPrice = $room['final_price'];

    if (empty($room['category_name'])) {
        $room['category_name'] = ucfirst($room['mix'] ?? 'General') . ' Area'; 
    }
}
unset($room);

$galleryData = array_map(function($item) {
    return [
        'url' => 'assets/img/galleries/' . $item['image'],
        'caption' => $item['caption']
    ];
}, $galleries);

$colors = [
    'primary' => $company['color_primary'] ?? '#10b981',
    'secondary' => $company['color_accent'] ?? '#1e3a8a'
];

// Setup Variable SEO
$pageTitle = htmlspecialchars($seo['meta_title']);
$pageDesc = htmlspecialchars($seo['meta_description']);
$pageKeywords = htmlspecialchars($seo['meta_keywords']);
$ogImage = $baseUrl . 'assets/img/' . htmlspecialchars($seo['og_image'] ?? 'default.jpg');
$favicon = 'assets/img/' . htmlspecialchars($seo['favicon'] ?? 'favicon.ico');
$canonical = !empty($seo['canonical_url']) ? $seo['canonical_url'] : $baseUrl;

// Generate JSON-LD Schema (LocalBusiness/LodgingBusiness)
$schemaData = [
    "@context" => "https://schema.org",
    "@type" => "LodgingBusiness",
    "name" => $company['name'],
    "image" => $ogImage,
    "@id" => $baseUrl,
    "url" => $baseUrl,
    "telephone" => $company['phone'],
    "priceRange" => "IDR " . number_format($minPrice, 0, ',', '.') . " - IDR " . number_format($maxPrice, 0, ',', '.'),
    "address" => [
        "@type" => "PostalAddress",
        "streetAddress" => $company['address'],
        "addressCountry" => "ID"
    ],
    "geo" => [
        "@type" => "GeoCoordinates",
        // Asumsi ada kolom lat/long di company, kalau tidak ada, bisa dihardcode atau ambil dari embed map regex
        "latitude" => "-6.200000", 
        "longitude" => "106.816666"
    ],
    "description" => $pageDesc
];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= $pageDesc ?>">
    <meta name="keywords" content="<?= $pageKeywords ?>">
    <meta name="author" content="<?= htmlspecialchars($seo['meta_author'] ?? 'Densucode Studio') ?>">
    <meta name="robots" content="<?= htmlspecialchars($seo['robots'] ?? 'index, follow') ?>">
    <link rel="canonical" href="<?= $canonical ?>">

    <meta property="og:type" content="<?= htmlspecialchars($seo['og_type'] ?? 'website') ?>">
    <meta property="og:url" content="<?= $canonical ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seo['og_title'] ?? $pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seo['og_description'] ?? $pageDesc) ?>">
    <meta property="og:image" content="<?= $ogImage ?>">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="<?= htmlspecialchars($company['name']) ?>">

    <meta property="twitter:card" content="<?= htmlspecialchars($seo['twitter_card'] ?? 'summary_large_image') ?>">
    <meta property="twitter:url" content="<?= $canonical ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($seo['og_title'] ?? $pageTitle) ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($seo['og_description'] ?? $pageDesc) ?>">
    <meta property="twitter:image" content="<?= $ogImage ?>">
    <?php if(!empty($seo['twitter_site'])): ?>
    <meta name="twitter:site" content="<?= htmlspecialchars($seo['twitter_site']) ?>">
    <?php endif; ?>

    <link rel="icon" type="image/x-icon" href="<?= $favicon ?>">
    <link rel="apple-touch-icon" href="<?= $favicon ?>">

    <script type="application/ld+json">
    <?= json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?>
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        primary: '<?= $colors['primary'] ?>',
                        secondary: '<?= $colors['secondary'] ?>',
                        dark: '#111827',
                        light: '#F9FAFB', 
                        surface: '#FFFFFF',
                    }
                }
            }
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
        body, html { overflow-x: hidden; width: 100%; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .hero-overlay { background: linear-gradient(to bottom, rgba(17, 24, 39, 0.5), rgba(<?= hexdec(substr($colors['primary'], 1, 2)) ?>, <?= hexdec(substr($colors['primary'], 3, 2)) ?>, <?= hexdec(substr($colors['primary'], 5, 2)) ?>, 0.4)); }
        .map-container iframe { width: 100%; height: 100%; min-height: 400px; filter: grayscale(100%); transition: filter 0.3s; }
        .map-container:hover iframe { filter: grayscale(0%); }
    </style>
    
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="font-sans text-sm text-gray-600 bg-light antialiased tracking-wide leading-relaxed"
      x-data="{ 
          confirmBooked: false, 
          bookedLink: '',
          openConfirm(link) {
              this.bookedLink = link;
              this.confirmBooked = true;
          }
      }">

    <?php include 'layouts/nav.php'; ?>

    <section id="hero" class="relative h-screen min-h-[600px] flex items-center justify-center text-white overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="assets/img/resized_deborah-cortelazzi-gREquCUXQLI-unsplash.webp" alt="Interior Kost" class="w-full h-full object-cover">
        </div>
        <div class="absolute inset-0 hero-overlay z-10 backdrop-blur-[1px]"></div>
        
        <div class="max-w-6xl mx-auto px-6 z-20 text-center" data-aos="fade-up" data-aos-duration="1200">
            <span class="inline-block py-1.5 px-4 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-xs font-medium tracking-widest uppercase mb-6">Kost Eksklusif</span>
            <h1 class="text-5xl md:text-7xl font-bold mb-6 leading-tight tracking-tight">
                <?= htmlspecialchars($company['name']) ?> <span class="text-primary">Residence</span>
            </h1>
            <p class="text-lg md:text-xl font-light mb-10 opacity-90 max-w-2xl mx-auto leading-relaxed">
                <?= htmlspecialchars($company['description']) ?>
            </p>
            <div class="flex flex-col md:flex-row gap-4 justify-center items-center">
                <a href="#products" class="bg-primary hover:bg-green-600 text-white min-w-[160px] py-4 rounded-full font-semibold transition transform hover:-translate-y-1 shadow-xl shadow-primary/30">Pilih Kamar</a>
                <a href="#contact" class="bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/30 text-white min-w-[160px] py-4 rounded-full font-semibold transition">Lokasi</a>
            </div>
        </div>
    </section>

    <section id="products" class="py-24 bg-light">
        <div class="max-w-6xl mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-end mb-16 gap-4" data-aos="fade-up">
                <div>
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">Pilihan Unit Tersedia</h2>
                    <p class="text-gray-500">Temukan kenyamanan yang sesuai dengan kebutuhan Anda.</p>
                </div>
                <div class="hidden md:block w-24 h-[2px] bg-primary/30 mb-2"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach($rooms as $index => $room): 
                    // Revisi: Mengarahkan semua tombol ke detail room berdasarkan slug
                    // Pastikan tabel rooms memiliki kolom 'slug'
                    $slug = $room['slug'] ?? 'kamar-' . $room['id']; // Fallback jika slug kosong
                    $detailUrl = "room/" . $slug;
                ?>
                <div class="bg-surface rounded-2xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl hover:shadow-gray-200/50 transition duration-500 group flex flex-col h-full relative" 
                     data-aos="fade-up" data-aos-delay="<?= ($index + 1) * 100 ?>">
                    
                    <div class="relative h-72 overflow-hidden bg-gray-100" 
                         x-data="{ 
                            active: 0, 
                            images: <?= htmlspecialchars(json_encode($room['images'])) ?>,
                            startX: 0, endX: 0,
                            init() { setInterval(() => { this.next() }, 4000) },
                            next() { this.active = (this.active + 1) % this.images.length },
                            prev() { this.active = (this.active - 1 + this.images.length) % this.images.length },
                            handleSwipe() { if(this.endX < this.startX - 50) this.next(); if(this.endX > this.startX + 50) this.prev(); }
                          }"
                          @touchstart="startX = $event.changedTouches[0].screenX"
                          @touchend="endX = $event.changedTouches[0].screenX; handleSwipe()">
                        
                          <template x-for="(img, idx) in images" :key="idx">
                            <div x-show="active === idx" 
                                 x-transition:enter="transition ease-out duration-700"
                                 x-transition:enter-start="opacity-0 scale-110"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-700 absolute top-0"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute inset-0 w-full h-full">
                                <img :src="img" class="w-full h-full object-cover" :class="{ 'grayscale': <?= $room['is_booked'] ?> }" alt="<?= htmlspecialchars($room['name']) ?>">
                            </div>
                        </template>
                        
                        <?php if($room['is_booked']): ?>
                        <div class="absolute inset-0 bg-black/5 z-20 flex items-center justify-center backdrop-blur-[1px]">
                            <span class="bg-red-300 text-white px-4 py-1 rounded-full font-bold text-sm tracking-widest uppercase border-2 border-white/20">Full Booked</span>
                        </div>
                        <?php endif; ?>

                        <div class="absolute top-4 left-4 bg-white/90 backdrop-blur-sm text-gray-800 text-[10px] font-bold px-3 py-1.5 rounded-full z-10 uppercase tracking-wider border border-gray-200 shadow-sm flex items-center gap-1">
                            <i class="fa-solid fa-tag text-primary"></i> <?= htmlspecialchars($room['category_name']) ?>
                        </div>

                        <div class="absolute top-4 right-4 bg-gray-900/80 backdrop-blur-sm text-white text-[10px] font-bold px-3 py-1.5 rounded-full z-10 shadow-sm uppercase">
                            <?= htmlspecialchars($room['room_no']) ?>
                        </div>

                        <?php if($room['discount'] && !$room['is_booked']): ?>
                        <div class="absolute bottom-4 right-4 bg-red-500 text-white text-[10px] font-bold px-3 py-1.5 rounded-full z-10 shadow-sm">
                            Hemat <?= $room['discount'] ?>%
                        </div>
                        <?php endif; ?>
                        
                        <div class="absolute bottom-4 left-0 right-0 flex justify-center gap-1.5 z-10">
                            <template x-for="(img, idx) in images" :key="idx">
                                <button @click="active = idx" :class="active === idx ? 'bg-white w-4' : 'bg-white/40 w-1.5'" class="h-1.5 rounded-full transition-all duration-300"></button>
                            </template>
                        </div>
                    </div>

                    <div class="p-8 flex-grow flex flex-col">
                        <div class="mb-4">
                            <h3 class="text-lg font-bold text-gray-800 group-hover:text-primary transition mb-1"><?= htmlspecialchars($room['name']) ?></h3>
                            
                            <div class="flex items-baseline gap-2">
                                <p class="text-primary font-bold text-xl">Rp <?= number_format($room['final_price'], 0) ?></p>
                                <?php if($room['discount']): ?>
                                    <span class="text-xs text-gray-400 line-through">Rp <?= number_format($room['price'], 0) ?></span>
                                <?php endif; ?>
                                <span class="text-[10px] text-gray-400 font-medium uppercase ml-auto">/ <?= $room['payment_type'] == 'monthly' ? 'Bulan' : 'Tahun' ?></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-y-3 gap-x-2 mb-8 text-xs text-gray-500 flex-grow">
                            <?php if($room['bed']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-bed text-primary/70 w-4 text-center"></i> Kasur</div><?php endif; ?>
                            <?php if($room['wc_inside']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-bath text-primary/70 w-4 text-center"></i> KM Dalam</div><?php else: ?><div class="flex items-center gap-2"><i class="fa-solid fa-bath text-gray-300 w-4 text-center"></i> KM Luar</div><?php endif; ?>
                            <?php if($room['is_ac'] || $room['ac']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-snowflake text-primary/70 w-4 text-center"></i> AC</div><?php endif; ?>
                            <?php if($room['fan']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-fan text-primary/70 w-4 text-center"></i> Kipas</div><?php endif; ?>
                            <?php if($room['desk']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-chair text-primary/70 w-4 text-center"></i> Meja</div><?php endif; ?>
                            <?php if($room['electricity_include']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-bolt text-primary/70 w-4 text-center"></i> Free Listrik</div><?php endif; ?>
                        </div>

                        <?php if($room['is_booked']): ?>
                            <a href="<?= $detailUrl ?>" class="block w-full text-center border border-gray-200 text-gray-400 hover:border-gray-300 hover:text-gray-600 font-medium py-3 rounded-xl transition text-sm cursor-pointer">
                                Detail Kamar
                            </a>
                        <?php else: ?>
                            <a href="<?= $detailUrl ?>" class="block w-full text-center border border-gray-200 text-gray-600 hover:border-primary hover:bg-primary hover:text-white font-medium py-3 rounded-xl transition text-sm">
                                Detail Kamar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="gallery" class="py-24 bg-surface">
        <div class="max-w-6xl mx-auto px-6"
             x-data="{
                imgModal : false, 
                activeImg : 0, 
                images : <?= htmlspecialchars(json_encode($galleryData)) ?>,
                openModal(index) { this.activeImg = index; this.imgModal = true; },
                closeModal() { this.imgModal = false; },
                nextImg() { this.activeImg = (this.activeImg + 1) % this.images.length; },
                prevImg() { this.activeImg = (this.activeImg - 1 + this.images.length) % this.images.length; }
             }"
             @keydown.window.escape="closeModal()"
             @keydown.window.arrow-right="nextImg()"
             @keydown.window.arrow-left="prevImg()">

            <div class="flex flex-col items-center mb-16 text-center" data-aos="fade-up">
                <span class="text-primary text-xs font-bold tracking-widest uppercase mb-2">Fasilitas & Lingkungan</span>
                <h2 class="text-3xl font-bold text-gray-800">Gallery</h2>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 auto-rows-[200px]">
                <template x-for="(img, index) in images" :key="index">
                    <div :class="{'md:col-span-2 md:row-span-2': index % 5 === 0}" 
                         class="relative group cursor-pointer overflow-hidden rounded-2xl bg-gray-100" 
                         @click="openModal(index)" 
                         data-aos="fade-in">
                        <img :src="img.url" class="w-full h-full object-cover transition duration-700 group-hover:scale-110 opacity-90 group-hover:opacity-100" :alt="img.caption">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition duration-300 flex items-end p-6">
                            <p class="text-white text-sm font-medium" x-text="img.caption"></p>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="imgModal" x-cloak @click="closeModal()" class="fixed inset-0 z-50 flex items-center justify-center bg-white/95 backdrop-blur-sm"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

                <button @click="closeModal()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-800 z-50 transition"><i class="fa-solid fa-xmark text-3xl"></i></button>
                <button @click.stop="prevImg()" class="hidden md:block absolute left-6 text-gray-400 hover:text-gray-800 z-50 transition"><i class="fa-solid fa-arrow-left-long text-3xl"></i></button>

                <div @click.stop class="relative w-full max-w-5xl h-auto max-h-screen p-4 flex flex-col items-center">
                    <img :src="images[activeImg].url" class="w-full h-auto max-h-[80vh] object-contain rounded-lg shadow-2xl" :alt="images[activeImg].caption">
                    <p class="text-center text-gray-800 mt-6 text-lg font-medium" x-text="images[activeImg].caption"></p>
                </div>

                <button @click.stop="nextImg()" class="hidden md:block absolute right-6 text-gray-400 hover:text-gray-800 z-50 transition"><i class="fa-solid fa-arrow-right-long text-3xl"></i></button>
            </div>
        </div>
    </section>

    <section id="faq" class="py-24 bg-light">
        <div class="max-w-2xl mx-auto px-6">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl font-bold text-gray-800 mb-4">FAQ</h2>
                <p class="text-gray-500 text-sm">Pertanyaan yang sering diajukan calon penghuni.</p>
            </div>

            <div class="space-y-4" x-data="{ selected: null }">
                <?php foreach($faqs as $index => $faq): ?>
                <div class="bg-surface rounded-xl border border-gray-200/60 overflow-hidden transition-all duration-300" 
                     :class="selected === <?= $index ?> ? 'shadow-lg border-transparent ring-1 ring-primary/10' : ''"
                     data-aos="fade-up" data-aos-delay="<?= $index * 50 ?>">
                    <button @click="selected !== <?= $index ?> ? selected = <?= $index ?> : selected = null" class="w-full px-8 py-5 text-left flex justify-between items-center focus:outline-none hover:bg-gray-50/50 transition">
                        <span class="font-semibold text-gray-700 text-sm" :class="selected === <?= $index ?> ? 'text-primary' : ''"><?= htmlspecialchars($faq['question']) ?></span>
                        <i class="fa-solid fa-plus transition-transform duration-300 text-xs" :class="selected === <?= $index ?> ? 'rotate-45 text-primary' : 'text-gray-400'"></i>
                    </button>
                    <div x-show="selected === <?= $index ?>" x-collapse class="px-8 pb-6 text-sm text-gray-500 leading-relaxed bg-surface">
                        <div class="pt-2 border-t border-gray-100">
                            <?= htmlspecialchars($faq['answer']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="cta" class="py-24 bg-surface border-t border-gray-100">
        <div class="max-w-6xl mx-auto px-6">
            
            <div class="bg-primary rounded-3xl p-10 md:p-10 text-center text-white relative overflow-hidden mb-24 shadow-2xl shadow-primary/20" data-aos="zoom-in">
                <div class="relative z-10 max-w-2xl mx-auto">
                    <h2 class="text-3xl md:text-3xl font-bold mb-6">Mulai Hidup Nyaman Disini</h2>
                    <p class="text-white/80 text-lg mb-8">Unit terbatas. Amankan kamar impian Anda hari ini juga.</p>
                    <a href="https://wa.me/<?= htmlspecialchars($company['whatsapp']) ?>" class="bg-white text-primary hover:bg-gray-50 px-8 py-4 rounded-full font-bold transition inline-flex items-center gap-2">
                        <i class="fa-brands fa-whatsapp text-xl"></i> Chat WhatsApp
                    </a>
                </div>
                <div class="absolute -top-24 -left-24 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
            </div>

            <div id="contact" class="grid grid-cols-1 md:grid-cols-2 gap-16 items-start">
                <div data-aos="fade-right">
                    <h3 class="text-2xl font-bold text-gray-800 mb-8">Kunjungi Lokasi</h3>
                    
                    <div class="space-y-8">
                        <div class="flex gap-5">
                            <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center text-primary shrink-0 border border-gray-200">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 text-sm mb-1">Alamat</h4>
                                <p class="text-gray-500 text-sm leading-relaxed"><?= htmlspecialchars($company['address']) ?></p>
                            </div>
                        </div>
                        <div class="flex gap-5">
                            <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center text-primary shrink-0 border border-gray-200">
                                <i class="fa-solid fa-phone"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 text-sm mb-1">Kontak</h4>
                                <p class="text-gray-500 text-sm">
                                    WA: <a href="https://wa.me/<?= htmlspecialchars($company['whatsapp']) ?>" class="hover:text-primary transition">+<?= htmlspecialchars($company['phone']) ?></a><br>
                                    Email: <?= htmlspecialchars($company['email']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl overflow-hidden shadow-lg border border-gray-200 h-[400px] map-container" data-aos="fade-left">
                    <iframe src="<?= htmlspecialchars($company['embed_map']) ?>" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>
    </section>

    <?php include 'layouts/footer.php'; ?>

    <div x-show="confirmBooked" 
         x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center px-4"
         role="dialog" aria-modal="true">
        
        <div x-show="confirmBooked"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" 
             @click="confirmBooked = false"></div>

        <div x-show="confirmBooked"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-gray-100 p-6">
            
            <div class="text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 mb-4">
                    <i class="fa-solid fa-door-closed text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold leading-6 text-gray-900 mb-2">Kamar Tidak Tersedia</h3>
                <p class="text-sm text-gray-500 mb-6">Kamar ini sudah penuh. Apakah Anda tetap ingin melihat detail dan menghubungi admin?</p>
                
                <div class="flex gap-3 justify-center">
                    <button type="button" 
                            class="inline-flex w-full justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:w-auto min-w-[100px]"
                            @click="confirmBooked = false">
                        Tidak
                    </button>
                    <button type="button" 
                            class="inline-flex w-full justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-600 sm:w-auto min-w-[100px]"
                            @click="window.location.href = bookedLink">
                        Boleh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, offset: 50, duration: 1000, easing: 'ease-out-quart' });
    </script>
</body>
</html>