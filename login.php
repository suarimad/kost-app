<?php
session_start();
define('ACCESS_KEY', 'ngapain?');

// Jika sudah login, cek return_url dulu sebelum lempar ke index
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['return_url'])) {
        $url = $_SESSION['return_url'];
        unset($_SESSION['return_url']);
        header("Location: " . $url);
    } else {
        header("Location: index.php");
    }
    exit;
}

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

$company = $pdo->query("SELECT * FROM company_data LIMIT 1")->fetch();
$seo = $pdo->query("SELECT * FROM seo_settings LIMIT 1")->fetch();

$colors = [
    'primary' => $company['color_primary'] ?? '#10b981',
    'secondary' => $company['color_accent'] ?? '#1e3a8a'
];

$error = '';
$success = $_SESSION['success_msg'] ?? '';
unset($_SESSION['success_msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Email dan password wajib diisi.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_verified'] == 0) {
                $_SESSION['verify_email'] = $email;
                
                // Resend OTP via Email
                $otp = rand(100000, 999999);
                $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));
                
                $upd = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
                if ($upd->execute([$otp, $expiry, $user['id']])) {
                    require_once 'helpers/mailer.php';
                    sendOTPEmail($email, $user['name'], $otp);
                    
                    header("Location: verify-otp.php");
                    exit;
                }
            } else {
                // Set Session Login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];

                // LOGIC REDIRECT KE HALAMAN SEBELUMNYA (BOOKING)
                if (isset($_SESSION['return_url'])) {
                    $url = $_SESSION['return_url'];
                    unset($_SESSION['return_url']); // Hapus session biar gak nyangkut
                    header("Location: " . $url);
                } else {
                    header("Location: index.php");
                }
                exit;
            }
        } else {
            $error = "Email atau password salah.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($company['name']) ?></title>
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

    <div class="hidden md:flex md:w-1/2 bg-gray-900 relative items-center justify-center">
        <img src="assets/img/resized_deborah-cortelazzi-gREquCUXQLI-unsplash.webp" class="absolute inset-0 w-full h-full object-cover opacity-60">
        <div class="relative z-10 p-12 text-white text-center">
            <h2 class="text-4xl font-bold mb-4">Selamat Datang Kembali</h2>
            <p class="text-lg opacity-90">Akses akun Anda untuk mengelola hunian impian.</p>
        </div>
    </div>

    <div class="w-full md:w-1/2 flex flex-col justify-center items-center bg-white relative p-8 md:p-16">
        
        <a href="index.php" class="absolute top-8 left-8 text-gray-400 hover:text-primary transition flex items-center gap-2 text-xs font-semibold uppercase tracking-wider">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>

        <div class="w-full max-w-sm">
            <div class="mb-10">
                <?php if($company['logo']): ?>
                    <img src="assets/img/<?= htmlspecialchars($company['logo']) ?>" alt="Logo" class="h-10 mb-6 object-contain">
                <?php else: ?>
                    <h2 class="text-2xl font-bold text-primary mb-6"><?= htmlspecialchars($company['name']) ?></h2>
                <?php endif; ?>
                
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Masuk</h1>
                <p class="text-gray-500">Silakan masukkan email dan password Anda.</p>
            </div>

            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 border border-green-100 text-green-600 px-4 py-3 rounded-xl text-xs flex items-center gap-2">
                    <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-100 text-red-600 px-4 py-3 rounded-xl text-xs flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" name="email" required class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3.5 pl-10 pr-4 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition placeholder-gray-300" placeholder="nama@email.com">
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Password</label>
                        <a href="forgot-password" class="text-xs text-primary hover:underline">Lupa Password?</a>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" required class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3.5 pl-10 pr-4 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition placeholder-gray-300" placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="w-full bg-primary hover:bg-green-600 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-primary/30 transition transform hover:-translate-y-0.5">
                    Masuk Sekarang
                </button>
            </form>

            <div class="mt-8 text-center text-xs text-gray-500">
                Belum punya akun? <a href="register.php" class="text-primary font-bold hover:underline">Daftar disini</a>
            </div>
        </div>
    </div>
</body>
</html>