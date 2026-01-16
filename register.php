<?php
session_start();
define('ACCESS_KEY', 'ngapain?');

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

$company = $pdo->query("SELECT * FROM company_data LIMIT 1")->fetch();
$seo = $pdo->query("SELECT * FROM seo_settings LIMIT 1")->fetch();

$colors = [
    'primary' => $company['color_primary'] ?? '#10b981',
    'secondary' => $company['color_accent'] ?? '#1e3a8a'
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    // Tambahan Phone untuk keperluan Booking
    $phone = trim($_POST['phone']); 
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = "Semua kolom wajib diisi.";
    } elseif ($password !== $confirm) {
        $error = "Konfirmasi password tidak sesuai.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $error = "Email sudah terdaftar.";
        } else {
            $otp = rand(100000, 999999);
            $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Insert Phone ke Database
            // Pastikan kolom 'phone' sudah ada di tabel users
            $insert = $pdo->prepare("INSERT INTO users (name, email, phone, password, otp_code, otp_expires_at, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
            
            if ($insert->execute([$name, $email, $phone, $hashed, $otp, $expiry])) {
                
                // --- KIRIM OTP VIA EMAIL ---
                require_once 'helpers/mailer.php';
                $sent = sendOTPEmail($email, $name, $otp);

                if ($sent) {
                    $_SESSION['verify_email'] = $email;
                    header("Location: verify-otp.php");
                    exit;
                } else {
                    // Rollback jika gagal kirim email
                    $pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);
                    $error = "Gagal mengirim kode OTP. Cek koneksi email Anda.";
                }

            } else {
                $error = "Gagal mendaftar.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - <?= htmlspecialchars($company['name']) ?></title>
    <link rel="icon" type="image/x-icon" href="assets/img/<?= htmlspecialchars($seo['favicon']) ?>">
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
                    }
                }
            }
        }
    </script>
</head>
<body class="font-sans text-sm text-gray-600 antialiased h-screen w-screen overflow-hidden flex">

    <div class="w-full md:w-1/2 flex flex-col justify-center items-center bg-white relative p-8 md:p-16">
        
        <a href="index.php" class="absolute top-8 left-8 text-gray-400 hover:text-primary transition flex items-center gap-2 text-xs font-semibold uppercase tracking-wider">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>

        <div class="w-full max-w-sm">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Buat Akun</h1>
                <p class="text-gray-500">Mulai perjalanan hunian nyaman Anda.</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-100 text-red-600 px-4 py-3 rounded-xl text-xs flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Nama Lengkap</label>
                    <input type="text" name="name" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">No. WhatsApp</label>
                    <input type="number" name="phone" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition" placeholder="081234567890">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Email</label>
                    <input type="email" name="email" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition">
                </div>
                <div class="flex gap-4">
                    <div class="w-1/2">
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Password</label>
                        <input type="password" name="password" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition">
                    </div>
                    <div class="w-1/2">
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Konfirmasi</label>
                        <input type="password" name="confirm_password" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition">
                    </div>
                </div>

                <button type="submit" class="w-full bg-primary hover:bg-green-600 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-primary/30 transition transform hover:-translate-y-0.5 mt-2">
                    Daftar & Kirim OTP
                </button>
            </form>

            <div class="mt-8 text-center text-xs text-gray-500">
                Sudah punya akun? <a href="login.php" class="text-primary font-bold hover:underline">Masuk disini</a>
            </div>
        </div>
    </div>

    <div class="hidden md:flex md:w-1/2 bg-gray-900 relative items-center justify-center">
        <img src="assets/img/resized_deborah-cortelazzi-gREquCUXQLI-unsplash.webp" class="absolute inset-0 w-full h-full object-cover opacity-60">
        <div class="relative z-10 p-12 text-white text-center">
            <h2 class="text-4xl font-bold mb-4">Bergabung Bersama Kami</h2>
            <p class="text-lg opacity-90">Ribuan penghuni telah menemukan kenyamanan di <?= htmlspecialchars($company['name']) ?>.</p>
        </div>
    </div>
</body>
</html>