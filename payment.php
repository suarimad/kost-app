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
    die("Connection failed.");
}

// 1. Validasi Akses
if (empty($_GET['order_id'])) {
    header("Location: index.php");
    exit;
}

$orderId = $_GET['order_id'];

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. Fetch Booking Data & User Data
$stmt = $pdo->prepare("SELECT b.*, r.name as room_name, r.room_no, u.name as user_name, u.phone as user_phone, u.email as user_email
                       FROM bookings b 
                       JOIN rooms r ON b.room_id = r.id 
                       JOIN users u ON b.user_id = u.id
                       WHERE b.order_id = ? AND b.user_id = ? LIMIT 1");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    echo "<script>alert('Data pesanan tidak ditemukan!'); window.location.href='index.php';</script>";
    exit;
}

// 3. Get Payment Settings
$paymentSettings = $pdo->query("SELECT * FROM payment_settings WHERE deleted_at IS NULL LIMIT 1")->fetch();
$mtClientKey = $paymentSettings['mt_clientkey'] ?? '';
$mtServerKey = $paymentSettings['mt_serverkey'] ?? '';

// ==========================================
// 4. AUTO-REGENERATE TOKEN (FIX ERROR)
// ==========================================
// Jika metode automatic TAPI token kosong, kita request ulang ke Midtrans sekarang juga.
if ($booking['payment_method'] == 'automatic' && empty($booking['snap_token']) && !empty($mtServerKey)) {
    
    $payload = [
        'transaction_details' => [
            'order_id' => $booking['order_id'], // Gunakan order ID yang sama
            'gross_amount' => (int)$booking['total_amount'],
        ],
        'customer_details' => [
            'first_name' => $booking['user_name'],
            'email' => $booking['user_email'] ?? 'guest@example.com',
            'phone' => $booking['user_phone'],
        ],
        'item_details' => [
            [
                'id' => 'ROOM-' . $booking['room_id'],
                'price' => (int)$booking['total_amount'], // Simplifikasi total agar match gross_amount
                'quantity' => 1,
                'name' => 'Sewa Kos Total'
            ]
        ]
    ];

    $auth = base64_encode($mtServerKey . ':');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://app.sandbox.midtrans.com/snap/v1/transactions");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . $auth
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $mtResponse = json_decode($response, true);
    
    if (isset($mtResponse['token'])) {
        $newToken = $mtResponse['token'];
        // Simpan ke DB agar tidak request ulang nanti
        $pdo->prepare("UPDATE bookings SET snap_token = ? WHERE id = ?")->execute([$newToken, $booking['id']]);
        // Update variable booking saat ini
        $booking['snap_token'] = $newToken;
    }
}

// 5. Handle Upload Bukti Transfer (Manual Only)
$uploadError = '';
$uploadSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['proof_image'])) {
    if ($booking['payment_method'] !== 'manual') {
        $uploadError = "Metode pembayaran ini tidak memerlukan upload bukti manual.";
    } else {
        $file = $_FILES['proof_image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];
            
            if (in_array($ext, $allowed)) {
                $uploadDir = 'assets/img/proofs/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $fileName = 'proof-' . $orderId . '-' . time() . '.' . $ext;
                
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                    $pdo->prepare("UPDATE bookings SET proof_image = ?, payment_status = 'pending_verification', updated_at = NOW() WHERE id = ?")
                        ->execute([$fileName, $booking['id']]);
                    
                    $uploadSuccess = true;
                    // Refresh data
                    $stmt->execute([$orderId, $_SESSION['user_id']]);
                    $booking = $stmt->fetch();
                } else {
                    $uploadError = "Gagal menyimpan file.";
                }
            } else {
                $uploadError = "Format file harus JPG atau PNG.";
            }
        } else {
            $uploadError = "Terjadi kesalahan saat upload.";
        }
    }
}

// Status Helper
$statusColor = 'bg-yellow-100 text-yellow-700';
$statusLabel = 'Menunggu Pembayaran';

if ($booking['payment_status'] == 'paid' || $booking['payment_status'] == 'settlement') {
    $statusColor = 'bg-green-100 text-green-700';
    $statusLabel = 'Lunas / Berhasil';
} elseif ($booking['payment_status'] == 'pending_verification') {
    $statusColor = 'bg-blue-100 text-blue-700';
    $statusLabel = 'Menunggu Verifikasi Admin';
} elseif ($booking['payment_status'] == 'expired' || $booking['payment_status'] == 'failed') {
    $statusColor = 'bg-red-100 text-red-700';
    $statusLabel = 'Gagal / Kadaluarsa';
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= $baseUrl ?>">
    <title>Pembayaran - <?= $orderId ?></title>
    
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="<?= $mtClientKey ?>"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Poppins', 'sans-serif'] },
                    colors: { primary: '#10b981', secondary: '#1e3a8a' }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .toast-enter { transform: translateY(-100%); opacity: 0; }
        .toast-enter-active { transform: translateY(0); opacity: 1; transition: all 0.3s ease-out; }
        .toast-exit { transform: translateY(0); opacity: 1; }
        .toast-exit-active { transform: translateY(-100%); opacity: 0; transition: all 0.3s ease-in; }
    </style>
</head>
<body class="bg-gray-50 font-sans text-gray-600" x-data="paymentPage()">

    <nav class="bg-white border-b border-gray-200 py-4 px-4 md:px-6 fixed w-full z-50 top-0 shadow-sm">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <a href="index.php" class="font-bold text-base md:text-lg text-gray-800 flex items-center gap-2 hover:text-primary transition">
                <i class="fa-solid fa-home"></i> <span class="hidden md:inline">Beranda</span>
            </a>
            <span class="font-bold text-primary text-sm md:text-base">Detail Pembayaran</span>
        </div>
    </nav>

    <div x-show="showToast" 
         x-transition:enter="toast-enter" x-transition:enter-start="toast-enter" x-transition:enter-end="toast-enter-active"
         x-transition:leave="toast-exit" x-transition:leave-start="toast-exit" x-transition:leave-end="toast-exit-active"
         class="fixed top-20 left-1/2 transform -translate-x-1/2 bg-gray-900/90 backdrop-blur-md text-white px-6 py-3 rounded-full shadow-2xl z-[80] flex items-center gap-3 text-sm border border-white/10 whitespace-nowrap"
         x-cloak>
        <i class="fa-solid fa-check-circle text-green-400"></i>
        <span>Nomor rekening disalin!</span>
    </div>

    <div class="pt-24 pb-12 px-4 md:px-6">
        <div class="w-full max-w-lg mx-auto">

            <?php if($uploadSuccess): ?>
            <div class="bg-green-100 border border-green-200 text-green-800 px-4 py-3 rounded-xl mb-6 flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-circle-check text-xl shrink-0"></i>
                <div>
                    <p class="font-bold text-sm">Bukti Transfer Terkirim!</p>
                    <p class="text-xs">Admin akan memverifikasi pembayaran Anda segera.</p>
                </div>
            </div>
            <?php endif; ?>

            <?php if($uploadError): ?>
            <div class="bg-red-100 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-6 flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-circle-exclamation text-xl shrink-0"></i>
                <p class="text-xs"><?= htmlspecialchars($uploadError) ?></p>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-3xl shadow-lg overflow-hidden mb-6 border border-gray-100 relative">
                <div class="bg-gray-900 p-8 text-white text-center relative overflow-hidden">
                    <div class="relative z-10">
                        <p class="text-gray-400 text-xs uppercase tracking-widest mb-2 font-semibold">Total Tagihan</p>
                        <h1 class="text-3xl md:text-4xl font-bold tracking-tight">Rp <?= number_format($booking['total_amount'], 0, ',', '.') ?></h1>
                        <div class="mt-4 inline-block px-4 py-1.5 rounded-full text-[10px] md:text-xs font-bold uppercase tracking-wide shadow-sm <?= $statusColor ?>">
                            <?= $statusLabel ?>
                        </div>
                    </div>
                    <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-gray-800 to-gray-900 opacity-50"></div>
                </div>

                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-gray-500 text-xs md:text-sm">Order ID</span>
                        <span class="font-mono text-gray-800 font-bold text-xs md:text-sm bg-gray-100 px-2 py-1 rounded"><?= $booking['order_id'] ?></span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-gray-500 text-xs md:text-sm">Kamar</span>
                        <div class="text-right">
                            <span class="text-gray-800 font-bold text-xs md:text-sm block"><?= htmlspecialchars($booking['room_name']) ?></span>
                            <span class="text-xs text-primary font-medium">No. <?= $booking['room_no'] ?></span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 text-xs md:text-sm">Durasi Sewa</span>
                        <span class="text-gray-800 font-bold text-xs md:text-sm"><?= $booking['duration'] ?> Bulan</span>
                    </div>
                </div>
            </div>

            <?php if ($booking['payment_method'] == 'automatic'): ?>
                <div class="bg-white rounded-3xl shadow-lg p-6 border border-gray-100 text-center">
                    <div class="w-16 h-16 bg-blue-50 text-primary rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
                        <i class="fa-solid fa-bolt text-2xl"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 mb-2 text-lg">Pembayaran Otomatis</h3>
                    <p class="text-sm text-gray-500 mb-6 px-4">Klik tombol di bawah untuk memilih metode pembayaran (QRIS, VA, E-Wallet).</p>
                    
                    <?php if ($booking['payment_status'] == 'pending'): ?>
                        <button id="pay-button" 
                                data-token="<?= $booking['snap_token'] ?>"
                                class="w-full bg-primary hover:bg-green-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/30 transition transform hover:-translate-y-1 active:scale-95 text-sm md:text-base">
                            Bayar Sekarang
                        </button>
                    <?php else: ?>
                        <button disabled class="w-full bg-gray-100 text-gray-400 font-bold py-4 rounded-xl cursor-not-allowed border border-gray-200">
                            Transaksi Selesai / Kadaluarsa
                        </button>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="bg-white rounded-3xl shadow-lg p-6 border border-gray-100">
                    <h3 class="font-bold text-gray-800 mb-4 border-b pb-2 text-lg">Transfer Manual</h3>
                    
                    <div class="flex items-center gap-4 mb-6 bg-blue-50 p-4 rounded-2xl border border-blue-100">
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-blue-600 border border-blue-100 shadow-sm shrink-0">
                            <i class="fa-solid fa-building-columns text-xl"></i>
                        </div>
                        <div class="flex-grow overflow-hidden">
                            <p class="font-bold text-gray-800 truncate"><?= htmlspecialchars($paymentSettings['bank']) ?></p>
                            <p class="text-xs text-gray-500 uppercase truncate">a.n <?= htmlspecialchars($paymentSettings['account_name']) ?></p>
                        </div>
                    </div>

                    <div class="bg-gray-800 text-white rounded-2xl p-5 mb-8 relative overflow-hidden group">
                        <div class="relative z-10 flex justify-between items-center">
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase tracking-wide mb-1">Nomor Rekening</p>
                                <p class="text-xl md:text-2xl font-mono font-bold tracking-wider"><?= htmlspecialchars($paymentSettings['account_no']) ?></p>
                            </div>
                            <button @click="copyToClipboard('<?= htmlspecialchars($paymentSettings['account_no']) ?>')" 
                                    class="bg-white/10 hover:bg-white/20 text-white p-3 rounded-lg transition backdrop-blur-sm">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                        <div class="absolute -right-6 -bottom-10 text-white/5 text-9xl">
                            <i class="fa-brands fa-cc-visa"></i>
                        </div>
                    </div>

                    <?php if ($booking['payment_status'] == 'pending'): ?>
                        <h3 class="font-bold text-gray-800 mb-2 text-sm">Upload Bukti Transfer</h3>
                        <p class="text-xs text-gray-500 mb-4">Pastikan nominal transfer sesuai hingga 3 digit terakhir.</p>
                        
                        <form action="" method="POST" enctype="multipart/form-data">
                            <label class="block w-full cursor-pointer border-2 border-dashed border-gray-300 hover:border-primary rounded-xl p-8 text-center transition bg-gray-50 hover:bg-green-50/30 mb-4 group">
                                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm text-gray-400 group-hover:text-primary transition">
                                    <i class="fa-solid fa-cloud-arrow-up text-xl"></i>
                                </div>
                                <span class="block text-sm font-semibold text-gray-600 group-hover:text-primary">Klik untuk upload foto</span>
                                <span class="block text-[10px] text-gray-400 mt-1">JPG/PNG Max 2MB</span>
                                <input type="file" name="proof_image" accept="image/*" class="hidden" required onchange="previewImage(this)">
                            </label>
                            
                            <div id="imagePreview" class="hidden mb-4 rounded-xl overflow-hidden border border-gray-200 bg-gray-100">
                                <img src="" class="w-full h-auto object-cover">
                            </div>

                            <button type="submit" class="w-full bg-secondary hover:bg-blue-900 text-white font-bold py-4 rounded-xl shadow-lg transition transform hover:-translate-y-1 text-sm md:text-base">
                                Kirim Bukti Pembayaran
                            </button>
                        </form>
                    
                    <?php elseif ($booking['payment_status'] == 'pending_verification'): ?>
                        <div class="text-center py-8 bg-blue-50/50 rounded-2xl border border-blue-100">
                            <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-3 animate-pulse">
                                <i class="fa-solid fa-hourglass-half text-2xl"></i>
                            </div>
                            <h4 class="font-bold text-gray-800">Sedang Diverifikasi</h4>
                            <p class="text-xs text-gray-500 mt-1 px-4">Bukti transfer Anda sedang dicek oleh admin kami.</p>
                            
                            <?php if($booking['proof_image']): ?>
                                <a href="assets/img/proofs/<?= $booking['proof_image'] ?>" target="_blank" class="inline-flex items-center gap-2 mt-4 text-xs text-primary font-bold hover:underline bg-white px-4 py-2 rounded-full shadow-sm border border-gray-100">
                                    <i class="fa-solid fa-eye"></i> Lihat Bukti Upload
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        function paymentPage() {
            return {
                showToast: false,
                copyToClipboard(text) {
                    navigator.clipboard.writeText(text).then(() => {
                        this.showToast = true;
                        setTimeout(() => this.showToast = false, 2500);
                    });
                }
            }
        }

        // Script Midtrans
        const payButton = document.getElementById('pay-button');
        if (payButton) {
            payButton.addEventListener('click', function () {
                // Ambil token dari data-attribute, lebih aman dari php injection langsung
                const snapToken = this.getAttribute('data-token');
                
                if(!snapToken) {
                    alert("Token pembayaran tidak valid. Halaman akan dimuat ulang.");
                    window.location.reload();
                    return;
                }

                window.snap.pay(snapToken, {
                    onSuccess: function (result) {
                        alert("Pembayaran Berhasil!");
                        window.location.reload(); 
                    },
                    onPending: function (result) {
                        alert("Menunggu pembayaran...");
                        window.location.reload();
                    },
                    onError: function (result) {
                        alert("Pembayaran gagal!");
                        window.location.reload();
                    },
                    onClose: function () {}
                });
            });
        }

        // Script Preview Image
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const img = preview.querySelector('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>