<?php
// Section: Configuration & Database
define('ACCESS_KEY', 'ngapain?');

// Helper URL
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

// 1. Validasi Parameter Slug
if (empty($_GET['slug'])) {
    header("Location: " . $baseUrl); 
    exit;
}

$slug = $_GET['slug'];

// 2. Fetch Data Kamar
$sql = "SELECT r.*, c.name as category_name, 
        f.bed, f.desk, f.storage, f.window, f.pillow, f.mirror, f.chair, f.fan, f.ac as facility_ac
        FROM rooms r 
        LEFT JOIN categories c ON r.category_id = c.id 
        LEFT JOIN room_facilities f ON r.id = f.room_id 
        WHERE r.slug = :slug AND r.deleted_at IS NULL LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['slug' => $slug]);
$room = $stmt->fetch();

if (!$room) {
    header("Location: " . $baseUrl);
    exit;
}

// 3. Fetch Data Tambahan (Fasilitas Umum & Area)
$general = $pdo->query("SELECT * FROM general_facilities WHERE deleted_at IS NULL LIMIT 1")->fetch();
$areas = $pdo->query("SELECT * FROM areas WHERE deleted_at IS NULL LIMIT 1")->fetch();
$company = $pdo->query("SELECT * FROM company_data LIMIT 1")->fetch(); 

// 4. Fetch Gambar Kamar
$stmtImg = $pdo->prepare("SELECT image, caption FROM room_images WHERE room_id = ? AND deleted_at IS NULL");
$stmtImg->execute([$room['id']]);
$rawImages = $stmtImg->fetchAll();

$roomImages = array_map(function($img) use ($baseUrl) {
    return [
        'src' => $baseUrl . 'assets/img/rooms/' . $img['image'],
        'caption' => $img['caption']
    ];
}, $rawImages);

if (empty($roomImages)) {
    $roomImages = [[
        'src' => 'https://via.placeholder.com/800x600?text=No+Image', 
        'caption' => 'No Image Available'
    ]];
}

// 5. Hitung Harga
$finalPrice = $room['price'];
if (!empty($room['discount']) && $room['discount'] > 0) {
    $finalPrice = $room['price'] - ($room['price'] * ($room['discount'] / 100));
}

$colors = [
    'primary' => $company['color_primary'] ?? '#10b981',
    'secondary' => $company['color_accent'] ?? '#1e3a8a'
];

// Mapping Icon Area Sekitar (Lengkap dengan class FontAwesome)
$areaMapping = [
    'shop' => ['icon' => 'fa-solid fa-store', 'label' => 'Warung / Toko'],
    'restaurant' => ['icon' => 'fa-solid fa-utensils', 'label' => 'Restoran'],
    'gas_station' => ['icon' => 'fa-solid fa-gas-pump', 'label' => 'SPBU'],
    'office_area' => ['icon' => 'fa-solid fa-building', 'label' => 'Perkantoran'],
    'factory' => ['icon' => 'fa-solid fa-industry', 'label' => 'Pabrik'],
    'minimarket' => ['icon' => 'fa-solid fa-cart-shopping', 'label' => 'Minimarket'],
    'clinic' => ['icon' => 'fa-solid fa-house-medical', 'label' => 'Klinik / RS'],
    'toll_road' => ['icon' => 'fa-solid fa-road', 'label' => 'Akses Tol'],
    'prayer_room' => ['icon' => 'fa-solid fa-person-praying', 'label' => 'Mushola'],
    'mosque' => ['icon' => 'fa-solid fa-mosque', 'label' => 'Masjid'],
    'cafe' => ['icon' => 'fa-solid fa-mug-hot', 'label' => 'Cafe'],
    'busway_access' => ['icon' => 'fa-solid fa-bus', 'label' => 'Halte Busway'],
    'train_access' => ['icon' => 'fa-solid fa-train', 'label' => 'Stasiun Kereta'],
    'bus_access' => ['icon' => 'fa-solid fa-bus-simple', 'label' => 'Terminal Bus'],
    'angkot_access' => ['icon' => 'fa-solid fa-van-shuttle', 'label' => 'Jalur Angkot'],
    'lrt_access' => ['icon' => 'fa-solid fa-train-subway', 'label' => 'Stasiun LRT'],
    'mrt_access' => ['icon' => 'fa-solid fa-train-tram', 'label' => 'Stasiun MRT'],
];

?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= $baseUrl ?>"> 
    
    <title><?= htmlspecialchars($room['name']) ?> - Detail Kamar</title>
    <meta name="description" content="Detail kamar <?= htmlspecialchars($room['name']) ?>.">
    
    <link rel="icon" type="image/x-icon" href="<?= $baseUrl ?>assets/img/favicon.ico"> 

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Poppins', 'sans-serif'] },
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
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="font-sans text-gray-600 bg-light antialiased">

    <?php include 'layouts/nav.php'; ?>

    <div class="bg-gray-900 text-white pt-32 pb-10">
        <div class="max-w-6xl mx-auto px-6">
            <a href="index.php" class="text-gray-400 hover:text-white text-sm mb-2 inline-block"><i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda</a>
            <h1 class="text-3xl md:text-4xl font-bold"><?= htmlspecialchars($room['name']) ?></h1>
            <p class="text-gray-400 mt-2 text-sm"><i class="fa-solid fa-tag text-primary mr-1"></i> <?= htmlspecialchars($room['category_name'] ?? 'General') ?> &bull; No. <?= $room['room_no'] ?></p>
        </div>
    </div>

    <section class="py-12">
        <div class="max-w-6xl mx-auto px-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                
                <div class="lg:col-span-2">
                    
                    <div x-data="{ activeImg: 0, images: <?= htmlspecialchars(json_encode($roomImages)) ?> }" class="mb-10">
                        <div class="rounded-2xl overflow-hidden bg-gray-200 h-[300px] md:h-[500px] mb-4 shadow-lg relative group">
                            <img :src="images[activeImg].src" class="w-full h-full object-cover transition duration-500" alt="Room Image">
                            <?php if($room['discount'] > 0): ?>
                            <div class="absolute top-4 left-4 bg-red-500 text-white font-bold px-3 py-1 rounded-full text-sm shadow-md">
                                Diskon <?= $room['discount'] ?>%
                            </div>
                            <?php endif; ?>
                            <button @click="activeImg = (activeImg === 0) ? images.length - 1 : activeImg - 1" class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-gray-800 p-3 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition"><i class="fa-solid fa-chevron-left"></i></button>
                            <button @click="activeImg = (activeImg === images.length - 1) ? 0 : activeImg + 1" class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-gray-800 p-3 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                        <div class="flex gap-3 overflow-x-auto pb-2 no-scrollbar">
                            <template x-for="(img, index) in images" :key="index">
                                <button @click="activeImg = index" class="w-20 h-20 rounded-xl overflow-hidden border-2 flex-shrink-0 transition" :class="activeImg === index ? 'border-primary opacity-100' : 'border-transparent opacity-60 hover:opacity-100'">
                                    <img :src="img.src" class="w-full h-full object-cover">
                                </button>
                            </template>
                        </div>
                    </div>

                    <?php if($areas): ?>
                    <div class="mb-10">
                        <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                            <i class="fa-solid fa-map-location-dot text-primary"></i> Akses & Area Sekitar
                        </h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <?php foreach($areaMapping as $key => $map): 
                                if(!empty($areas[$key])): 
                            ?>
                            <div class="bg-white border border-gray-100 p-4 rounded-xl flex items-center gap-3 hover:shadow-md transition">
                                <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-primary shrink-0">
                                    <i class="<?= $map['icon'] ?>"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider"><?= $map['label'] ?></p>
                                    <p class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($areas[$key]) ?></p>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 border-l-4 border-primary pl-4">Deskripsi Lengkap</h3>
                        <div class="prose prose-sm text-gray-600 leading-relaxed max-w-none">
                            <?= nl2br(htmlspecialchars($room['description'] ?? 'Belum ada deskripsi.')) ?>
                        </div>
                    </div>

                </div>

                <div class="lg:col-span-1">
                    <div class="bg-surface p-6 rounded-3xl shadow-sm border border-gray-100 sticky top-28">
                        
                        <div class="mb-6 border-b border-gray-100 pb-6">
                            <p class="text-sm text-gray-400 mb-1">Harga Sewa / <?= $room['payment_type'] == 'monthly' ? 'Bulan' : 'Tahun' ?></p>
                            <div class="flex items-end gap-3">
                                <h2 class="text-3xl font-bold text-primary">Rp <?= number_format($finalPrice, 0) ?></h2>
                                <?php if($room['discount'] > 0): ?>
                                    <span class="text-sm text-gray-400 line-through mb-1">Rp <?= number_format($room['price'], 0) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="bg-gray-50 p-3 rounded-xl">
                                <span class="text-xs text-gray-400 block">Tipe Penghuni</span>
                                <span class="font-semibold text-gray-700 capitalize">
                                    <?php 
                                        if($room['mix'] == 'man') echo '<i class="fa-solid fa-person text-blue-500 mr-1"></i> Pria';
                                        elseif($room['mix'] == 'woman') echo '<i class="fa-solid fa-person-dress text-pink-500 mr-1"></i> Wanita';
                                        else echo '<i class="fa-solid fa-users text-purple-500 mr-1"></i> Campur';
                                    ?>
                                </span>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-xl">
                                <span class="text-xs text-gray-400 block">Ukuran Kamar</span>
                                <span class="font-semibold text-gray-700">
                                    <?= floatval($room['width']) ?> x <?= floatval($room['length']) ?> m
                                </span>
                            </div>
                        </div>

                        <div class="mb-8">
                            <h3 class="font-bold text-gray-800 mb-3 border-b border-gray-100 pb-2">Fasilitas Kamar</h3>
                            <div class="grid grid-cols-2 gap-y-2 text-xs text-gray-600">
                                <?php if($room['bed']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-bed text-primary w-4 text-center"></i> Kasur</div><?php endif; ?>
                                <?php if($room['wc_inside']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-bath text-primary w-4 text-center"></i> KM Dalam</div><?php else: ?><div class="flex items-center gap-2"><i class="fa-solid fa-bath text-gray-400 w-4 text-center"></i> KM Luar</div><?php endif; ?>
                                <?php if($room['is_ac'] || $room['facility_ac']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-snowflake text-primary w-4 text-center"></i> AC</div><?php endif; ?>
                                <?php if($room['fan']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-fan text-primary w-4 text-center"></i> Kipas</div><?php endif; ?>
                                <?php if($room['desk']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-chair text-primary w-4 text-center"></i> Meja</div><?php endif; ?>
                                <?php if($room['storage']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-box-open text-primary w-4 text-center"></i> Lemari</div><?php endif; ?>
                                <?php if($room['window']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-border-all text-primary w-4 text-center"></i> Jendela</div><?php endif; ?>
                                <?php if($room['electricity_include']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-bolt text-primary w-4 text-center"></i> Free Listrik</div><?php endif; ?>
                                <?php if($room['water_include']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-faucet-drip text-primary w-4 text-center"></i> Free Air</div><?php endif; ?>
                                <?php if($room['kitchen']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-fire-burner text-primary w-4 text-center"></i> Dapur</div><?php endif; ?>
                            </div>
                        </div>

                        <?php if($general): ?>
                        <div class="mb-8">
                            <h3 class="font-bold text-gray-800 mb-3 border-b border-gray-100 pb-2">Fasilitas Umum</h3>
                            <div class="grid grid-cols-2 gap-y-2 text-xs text-gray-600">
                                <?php if($general['wifi']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-wifi text-secondary w-4 text-center"></i> WiFi Kenceng</div><?php endif; ?>
                                <?php if($general['motorbike_parking']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-motorcycle text-secondary w-4 text-center"></i> Parkir Motor</div><?php endif; ?>
                                <?php if($general['car_parking']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-car text-secondary w-4 text-center"></i> Parkir Mobil</div><?php endif; ?>
                                <?php if($general['kitchen']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-fire-burner text-secondary w-4 text-center"></i> Dapur Umum</div><?php endif; ?>
                                <?php if($general['laundry_area']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-jug-detergent text-secondary w-4 text-center"></i> Ruang Jemur</div><?php endif; ?>
                                <?php if($general['guest_area']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-couch text-secondary w-4 text-center"></i> R. Tamu</div><?php endif; ?>
                                <?php if($general['security']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-user-shield text-secondary w-4 text-center"></i> CCTV/Satpam</div><?php endif; ?>
                                <?php if($general['gate_system']): ?><div class="flex items-center gap-2"><i class="fa-solid fa-dungeon text-secondary w-4 text-center"></i> Kunci Gerbang</div><?php endif; ?>
                                
                                <div class="col-span-2 mt-2 bg-blue-50 text-blue-700 px-3 py-2 rounded-lg flex items-center gap-2">
                                    <i class="fa-solid fa-clock"></i>
                                    <?php if($general['is_24hours']): ?>
                                        Akses 24 Jam Bebas
                                    <?php else: ?>
                                        Jam Malam: <span class="font-bold"><?= htmlspecialchars($general['night_time']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mt-4">
                            <?php if ($room['is_booked']): ?>
                                <button disabled class="w-full bg-gray-200 text-gray-400 font-bold py-3 text-sm rounded-xl cursor-not-allowed border border-gray-300 flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-lock"></i> FULL BOOKED
                                </button>
                            <?php else: ?>
                                <a href="booking.php?room_id=<?= $room['id'] ?>" class="w-full block bg-primary hover:bg-green-600 text-white font-bold py-3 text-sm rounded-xl text-center shadow-lg shadow-primary/30 transition transform hover:-translate-y-1">
                                    Booking Sekarang
                                </a>
                                <p class="text-[10px] text-center text-gray-400 mt-2">Proses cepat, aman & otomatis.</p>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php include 'layouts/footer.php'; ?>

</body>
</html>