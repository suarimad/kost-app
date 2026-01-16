<?php
session_start();
define('ACCESS_KEY', 'ngapain?');

// SET TIMEZONE JAKARTA
date_default_timezone_set('Asia/Jakarta');

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
} catch (PDOException $e) { die("Connection failed."); }

$company = $pdo->query("SELECT * FROM company_data LIMIT 1")->fetch();
$seo = $pdo->query("SELECT * FROM seo_settings LIMIT 1")->fetch();
$colors = ['primary' => $company['color_primary'] ?? '#10b981', 'secondary' => $company['color_accent'] ?? '#1e3a8a'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        
        // Expiry menggunakan Timezone Jakarta (Sudah di-set di atas)
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));
        
        $upd = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE id = ?");
        $upd->execute([$token, $expiry, $user['id']]);

        // URL Reset (Auto detect HTTPS/HTTP)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $resetLink = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;

        require_once 'helpers/mailer.php';
        if (sendResetEmail($email, $user['name'], $resetLink)) {
            $success = "Link reset password telah dikirim ke email Anda.";
        } else {
            $error = "Gagal mengirim email. Coba lagi nanti.";
        }
    } else {
        $error = "Email tidak ditemukan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - <?= htmlspecialchars($company['name']) ?></title>
    <link rel="icon" type="image/x-icon" href="assets/img/<?= htmlspecialchars($seo['favicon']) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Poppins', 'sans-serif'] }, colors: { primary: '<?= $colors['primary'] ?>', secondary: '<?= $colors['secondary'] ?>' } } } }
    </script>
</head>
<body class="font-sans text-sm text-gray-600 antialiased h-screen w-screen overflow-hidden flex">

    <div class="hidden md:flex md:w-1/2 bg-gray-900 relative items-center justify-center">
        <img src="assets/img/resized_deborah-cortelazzi-gREquCUXQLI-unsplash.webp" class="absolute inset-0 w-full h-full object-cover opacity-60">
        <div class="relative z-10 p-12 text-white text-center">
            <h2 class="text-4xl font-bold mb-4">Lupa Password?</h2>
            <p class="text-lg opacity-90">Jangan khawatir, kami akan membantu mengembalikan akses akun Anda.</p>
        </div>
    </div>

    <div class="w-full md:w-1/2 flex flex-col justify-center items-center bg-white relative p-8 md:p-16">
        <a href="login.php" class="absolute top-8 left-8 text-gray-400 hover:text-primary transition flex items-center gap-2 text-xs font-semibold uppercase tracking-wider">
            <i class="fa-solid fa-arrow-left"></i> Kembali Login
        </a>

        <div class="w-full max-w-sm">
            <div class="mb-10">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Reset Password</h1>
                <p class="text-gray-500">Masukkan email yang terdaftar untuk menerima link reset.</p>
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

                <button type="submit" class="w-full bg-primary hover:bg-green-600 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-primary/30 transition transform hover:-translate-y-0.5">
                    Kirim Link Reset
                </button>
            </form>
        </div>
    </div>
</body>
</html>