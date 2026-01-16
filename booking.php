<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
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

// AJAX Handler
if (isset($_GET['action']) && $_GET['action'] == 'check_status' && isset($_GET['room_id'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT is_booked FROM rooms WHERE id = ?");
    $stmt->execute([$_GET['room_id']]);
    $status = $stmt->fetch();
    echo json_encode(['status' => ($status ? 'success' : 'error'), 'is_booked' => $status['is_booked'] ?? 1]);
    exit;
}

// Authentication
if (empty($_GET['room_id'])) { header("Location: index.php"); exit; }
$roomId = $_GET['room_id'];

if (!isset($_SESSION['user_id'])) {
    $_SESSION['return_url'] = "booking.php?room_id=" . $roomId;
    header("Location: register.php");
    exit;
}

$userId = $_SESSION['user_id'];
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

// Fetch Room
$sql = "SELECT r.*, c.name as category_name FROM rooms r LEFT JOIN categories c ON r.category_id = c.id WHERE r.id = :id AND r.deleted_at IS NULL LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $roomId]);
$room = $stmt->fetch();

if (!$room || $room['is_booked'] == 1) {
    echo "<script>alert('Maaf, kamar ini tidak ditemukan atau sudah terisi.'); window.location.href='index.php';</script>";
    exit;
}

// Data Settings
$depositSettings = $pdo->query("SELECT * FROM deposit_settings LIMIT 1")->fetch();
$depositAmount = $depositSettings['deposit'] ?? 0;
$depositCondition = $depositSettings['condition'] ?? 'Jaminan keamanan fasilitas.';

$paymentSettings = $pdo->query("SELECT * FROM payment_settings WHERE deleted_at IS NULL LIMIT 1")->fetch();
if (!$paymentSettings) $paymentSettings = ['payment_type' => 'manual', 'bank' => 'Unknown', 'account_no' => '-', 'account_name' => '-'];

$stmtImg = $pdo->prepare("SELECT image FROM room_images WHERE room_id = ? AND deleted_at IS NULL ORDER BY id ASC LIMIT 1");
$stmtImg->execute([$room['id']]);
$imgData = $stmtImg->fetch();
$roomImage = $imgData ? $baseUrl . 'assets/img/rooms/' . $imgData['image'] : 'https://via.placeholder.com/800x600?text=No+Image';

$finalPrice = $room['price'];
if (!empty($room['discount']) && $room['discount'] > 0) {
    $finalPrice = $room['price'] - ($room['price'] * ($room['discount'] / 100));
}

$company = $pdo->query("SELECT * FROM company_data LIMIT 1")->fetch();
$colors = [
    'primary' => $company['color_primary'] ?? '#10b981',
    'secondary' => $company['color_accent'] ?? '#1e3a8a'
];

$minDate = date('Y-m-d', strtotime('+1 day'));
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= $baseUrl ?>">
    <title>Booking Kamar - <?= htmlspecialchars($room['name']) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= $baseUrl ?>assets/img/favicon.ico">
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .toast-enter { transform: translateY(-100%); opacity: 0; }
        .toast-enter-active { transform: translateY(0); opacity: 1; transition: all 0.3s ease-out; }
        .toast-exit { transform: translateY(0); opacity: 1; }
        .toast-exit-active { transform: translateY(-100%); opacity: 0; transition: all 0.3s ease-in; }
        .loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(5px); z-index: 9999;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .spinner {
            width: 50px; height: 50px; border: 5px solid <?= $colors['primary'] ?>;
            border-bottom-color: transparent; border-radius: 50%; animation: rotation 1s linear infinite;
        }
        @keyframes rotation { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>

<body class="font-sans text-gray-600 bg-light antialiased" x-data="bookingApp()">

    <div x-show="isLoading" class="loading-overlay" x-cloak>
        <div class="spinner mb-4"></div>
        <p class="text-gray-800 font-bold animate-pulse text-lg">Memproses Pesanan...</p>
        <p class="text-sm text-gray-500 mt-2">Sedang mengompres gambar & verifikasi kamar.</p>
        <p class="text-xs text-red-400 mt-1">Jangan tutup halaman ini.</p>
    </div>

    <div x-show="showError" class="fixed inset-0 z-[100] flex items-center justify-center px-4" x-cloak>
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <div class="bg-white rounded-2xl p-6 max-w-sm w-full relative z-10 text-center shadow-2xl">
            <div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-circle-xmark text-3xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-2">Gagal Memproses</h3>
            <p class="text-sm text-gray-500 mb-6">Maaf, kamar ini baru saja dibooking oleh orang lain.</p>
            <a href="index.php" class="block w-full bg-red-500 text-white font-bold py-3 rounded-xl hover:bg-red-600 transition">Cari Kamar Lain</a>
        </div>
    </div>

    <div x-show="showToast" 
         x-transition:enter="toast-enter" x-transition:enter-start="toast-enter" x-transition:enter-end="toast-enter-active"
         x-transition:leave="toast-exit" x-transition:leave-start="toast-exit" x-transition:leave-end="toast-exit-active"
         class="fixed top-24 left-1/2 transform -translate-x-1/2 bg-gray-900/90 backdrop-blur-md text-white px-6 py-3 rounded-full shadow-2xl z-[80] flex items-center gap-3 text-sm border border-white/10"
         x-cloak>
        <i class="fa-solid fa-check-circle text-green-400"></i>
        <span>Rekening disalin!</span>
    </div>

    <nav class="bg-white border-b border-gray-200 py-4 px-6 fixed w-full z-50 top-0">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <a href="room.php?slug=<?= htmlspecialchars($room['slug'] ?? '') ?>" class="font-bold text-xl text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-chevron-left text-sm"></i> Batal
            </a>
            <span class="font-bold text-primary">Formulir Pemesanan</span>
        </div>
    </nav>

    <div class="pt-24 pb-12 px-6">
        <div class="max-w-5xl mx-auto">
            <form id="bookingForm" action="<?= $baseUrl ?>process_booking.php" method="POST" enctype="multipart/form-data" 
                  class="grid grid-cols-1 lg:grid-cols-3 gap-8" @submit.prevent="submitForm">
                
                <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                <input type="hidden" name="user_id" value="<?= $userId ?>">
                <input type="hidden" name="price_per_month" value="<?= $finalPrice ?>">
                <input type="hidden" name="deposit_amount" value="<?= $depositAmount ?>">

                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Identitas Penyewa</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Nama Lengkap</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-primary" readonly>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">No. WhatsApp <span class="text-red-500">*</span></label>
                                <input type="number" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required placeholder="08123456789" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">NIK / No. KTP <span class="text-red-500">*</span></label>
                                <input type="text" name="legal_id" value="<?= htmlspecialchars($user['legal_id'] ?? '') ?>" required 
                                       placeholder="16 Digit NIK" maxlength="16" inputmode="numeric"
                                       @input="$el.value = $el.value.replace(/[^0-9]/g, '').slice(0, 16)"
                                       class="w-full bg-white border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Kota Asal <span class="text-red-500">*</span></label>
                                <input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" required placeholder="Contoh: Surabaya" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Pekerjaan <span class="text-red-500">*</span></label>
                                <select name="occupation" required class="w-full bg-white border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                    <option value="" disabled <?= empty($user['occupation']) ? 'selected' : '' ?>>Pilih Pekerjaan</option>
                                    <option value="Karyawan" <?= ($user['occupation'] ?? '') == 'Karyawan' ? 'selected' : '' ?>>Karyawan</option>
                                    <option value="Mahasiswa" <?= ($user['occupation'] ?? '') == 'Mahasiswa' ? 'selected' : '' ?>>Mahasiswa</option>
                                    <option value="Lainnya" <?= ($user['occupation'] ?? '') == 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Foto KTP <span class="text-red-500">*</span></label>
                                <div class="flex items-center gap-4">
                                    <label class="cursor-pointer bg-gray-50 border border-gray-300 text-gray-600 hover:bg-gray-100 px-4 py-2.5 rounded-lg text-xs font-bold flex items-center gap-2 transition w-full justify-center">
                                        <i class="fa-solid fa-camera"></i> Pilih Foto
                                        <input type="file" id="ktpInput" name="legal_id_image" accept="image/*" class="hidden" @change="handleKtpUpload">
                                    </label>
                                </div>
                                <div x-show="ktpPreview" class="mt-3 relative w-full h-32 bg-gray-100 rounded-lg overflow-hidden border border-gray-200" x-cloak>
                                    <img :src="ktpPreview" class="w-full h-full object-cover">
                                    <button type="button" @click="removeKtp" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 w-6 h-6 flex items-center justify-center shadow-md hover:bg-red-600 transition"><i class="fa-solid fa-times text-xs"></i></button>
                                </div>
                                <p x-show="!ktpPreview" class="text-[10px] text-gray-400 mt-1 italic">Belum ada foto dipilih.</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Detail Sewa</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Mulai Kost <span class="text-red-500">*</span></label>
                                <input type="date" name="start_date" id="start_date" required min="<?= $minDate ?>" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary cursor-pointer">
                                <p class="text-[10px] text-gray-400 mt-1">Minimal check-in: <?= date('d M Y', strtotime($minDate)) ?></p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Durasi Sewa</label>
                                <select name="duration" id="duration" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                    <option value="1">1 Bulan</option>
                                    <option value="3">3 Bulan</option>
                                    <option value="6">6 Bulan</option>
                                    <option value="12">1 Tahun</option>
                                </select>
                            </div>
                        </div>
                        <div class="bg-blue-50 text-blue-800 p-4 rounded-xl text-xs flex gap-3 items-start">
                            <i class="fa-solid fa-circle-info mt-0.5"></i>
                            <p>Pembayaran awal dilakukan untuk durasi yang dipilih. Perpanjangan selanjutnya dapat dilakukan melalui dashboard penghuni.</p>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-white p-6 rounded-2xl shadow-lg border border-primary/10 sticky top-24">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Ringkasan</h3>
                        <div class="flex gap-4 mb-6 pb-6 border-b border-gray-100">
                            <img src="<?= $roomImage ?>" class="w-20 h-20 object-cover rounded-lg bg-gray-200">
                            <div>
                                <h4 class="font-bold text-gray-800 text-sm mb-1"><?= htmlspecialchars($room['name']) ?></h4>
                                <span class="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-500">Kamar No. <?= $room['room_no'] ?></span>
                                <p class="text-xs text-primary font-semibold mt-1"><?= htmlspecialchars($room['category_name']) ?></p>
                            </div>
                        </div>

                        <div class="space-y-3 mb-6 text-sm">
                            <div class="flex justify-between text-gray-500">
                                <span>Harga / Bulan</span>
                                <span>Rp <?= number_format($finalPrice, 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between text-gray-500">
                                <span>Durasi</span>
                                <span id="duration_label">1 Bulan</span>
                            </div>
                            
                            <?php if($depositAmount > 0): ?>
                            <div class="flex justify-between text-gray-800 font-medium">
                                <span class="flex items-center gap-1 cursor-pointer border-b border-dashed border-gray-400" @click="showTooltip = !showTooltip">
                                    Deposit Jaminan <i class="fa-solid fa-circle-question text-gray-400 text-xs hover:text-primary"></i>
                                </span>
                                <span>Rp <?= number_format($depositAmount, 0, ',', '.') ?></span>
                            </div>
                            <div x-show="showTooltip" x-transition.opacity.duration.300ms class="bg-yellow-50 text-yellow-800 text-xs p-3 rounded-lg border border-yellow-100 mb-2" x-cloak>
                                <span class="font-bold">Ketentuan Deposit:</span><br>
                                <?= htmlspecialchars($depositCondition) ?>
                            </div>
                            <?php endif; ?>

                            <div class="border-t border-dashed border-gray-200 pt-3 flex justify-between items-center">
                                <span class="font-bold text-gray-800">Total Pembayaran</span>
                                <span class="font-bold text-xl text-primary" id="total_display">Rp 0</span>
                            </div>
                        </div>

                        <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Metode Pembayaran</h4>
                            <?php if($paymentSettings['payment_type'] == 'manual'): ?>
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center border border-gray-200 text-blue-600 shrink-0"><i class="fa-solid fa-building-columns"></i></div>
                                    <div class="overflow-hidden w-full">
                                        <p class="font-bold text-gray-800 text-sm truncate"><?= htmlspecialchars($paymentSettings['bank']) ?></p>
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-xs text-gray-500 font-mono tracking-wide" id="rekNum"><?= htmlspecialchars($paymentSettings['account_no']) ?></p>
                                            <button type="button" @click="copyToClipboard('<?= htmlspecialchars($paymentSettings['account_no']) ?>')" class="text-primary hover:text-green-600 transition p-1" title="Salin Rekening"><i class="fa-regular fa-copy"></i></button>
                                        </div>
                                        <p class="text-[10px] text-gray-400 uppercase truncate">a.n <?= htmlspecialchars($paymentSettings['account_name']) ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center border border-gray-200 text-primary"><i class="fa-solid fa-bolt"></i></div>
                                    <div><p class="font-bold text-gray-800 text-sm">Pembayaran Otomatis</p><p class="text-xs text-gray-500">QRIS / Virtual Account</p></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="w-full bg-primary hover:bg-green-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/30 transition transform hover:-translate-y-1">Lanjut Pembayaran <i class="fa-solid fa-arrow-right ml-2"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function bookingApp() {
            return {
                isLoading: false,
                showError: false,
                showToast: false,
                showTooltip: false, 
                pricePerMonth: <?= $finalPrice ?>,
                depositAmount: <?= $depositAmount ?>,
                roomId: <?= $roomId ?>,
                ktpPreview: null,

                init() {
                    this.calculateTotal();
                    const durationSelect = document.getElementById('duration');
                    if(durationSelect) {
                        durationSelect.addEventListener('change', () => this.calculateTotal());
                    }
                },

                calculateTotal() {
                    const durationEl = document.getElementById('duration');
                    const labelEl = document.getElementById('duration_label');
                    const displayEl = document.getElementById('total_display');
                    if(!durationEl || !labelEl || !displayEl) return;
                    const duration = parseInt(durationEl.value);
                    const totalSewa = this.pricePerMonth * duration;
                    const grandTotal = totalSewa + this.depositAmount;
                    labelEl.textContent = duration + (duration == 12 ? ' Tahun' : ' Bulan');
                    displayEl.textContent = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(grandTotal);
                },

                copyToClipboard(text) {
                    navigator.clipboard.writeText(text).then(() => {
                        this.showToast = true;
                        setTimeout(() => this.showToast = false, 2500);
                    });
                },

                handleKtpUpload(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    this.ktpPreview = URL.createObjectURL(file);
                    const reader = new FileReader();
                    reader.readAsDataURL(file);
                    reader.onload = (event) => {
                        const img = new Image();
                        img.src = event.target.result;
                        img.onload = () => {
                            const canvas = document.createElement('canvas');
                            const ctx = canvas.getContext('2d');
                            const MAX_WIDTH = 1000;
                            let width = img.width;
                            let height = img.height;
                            if (width > MAX_WIDTH) { height *= MAX_WIDTH / width; width = MAX_WIDTH; }
                            canvas.width = width; canvas.height = height;
                            ctx.drawImage(img, 0, 0, width, height);
                            canvas.toBlob((blob) => {
                                if(blob) {
                                    const compressedFile = new File([blob], file.name, { type: 'image/jpeg', lastModified: Date.now() });
                                    const dataTransfer = new DataTransfer();
                                    dataTransfer.items.add(compressedFile);
                                    document.getElementById('ktpInput').files = dataTransfer.files;
                                    this.ktpPreview = URL.createObjectURL(compressedFile);
                                }
                            }, 'image/jpeg', 0.7); 
                        }
                    }
                },

                removeKtp() {
                    this.ktpPreview = null;
                    document.getElementById('ktpInput').value = '';
                },

                submitForm(e) {
                    this.isLoading = true;
                    if (!document.getElementById('ktpInput').files.length) {
                        alert("Harap upload foto KTP Anda.");
                        this.isLoading = false;
                        return;
                    }
                    fetch(`booking.php?action=check_status&room_id=${this.roomId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success' && data.is_booked == 0) {
                                document.getElementById('bookingForm').submit();
                            } else {
                                this.isLoading = false;
                                this.showError = true;
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            this.isLoading = false;
                            alert("Koneksi gagal. Coba lagi.");
                        });
                }
            }
        }
    </script>
</body>
</html>