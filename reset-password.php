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
} catch (PDOException $e) { die("Connection failed."); }

$company = $pdo->query("SELECT * FROM company_data LIMIT 1")->fetch();
$seo = $pdo->query("SELECT * FROM seo_settings LIMIT 1")->fetch();
$colors = ['primary' => $company['color_primary'] ?? '#10b981', 'secondary' => $company['color_accent'] ?? '#1e3a8a'];

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Cek Token Valid
$stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expires_at > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user && !$success) {
    $error = "Link tidak valid atau sudah kadaluarsa.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($pass !== $confirm) {
        $error = "Password tidak cocok.";
    } elseif (strlen($pass) < 6) {
        $error = "Password minimal 6 karakter.";
    } else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?");
        
        if ($upd->execute([$hashed, $user['id']])) {
            $_SESSION['success_msg'] = "Password berhasil diubah. Silakan login.";
            header("Location: login.php");
            exit;
        } else {
            $error = "Gagal mengubah password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubah Password - <?= htmlspecialchars($company['name']) ?></title>
    <link rel="icon" type="image/x-icon" href="assets/img/<?= htmlspecialchars($seo['favicon']) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Poppins', 'sans-serif'] }, colors: { primary: '<?= $colors['primary'] ?>', secondary: '<?= $colors['secondary'] ?>' } } } }
    </script>
</head>
<body class="font-sans text-sm text-gray-600 antialiased h-screen w-screen overflow-hidden flex items-center justify-center bg-gray-50">

    <div class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-gray-100 p-10 relative">
        <a href="login.php" class="absolute top-6 left-6 text-gray-400 hover:text-primary transition"><i class="fa-solid fa-xmark text-xl"></i></a>

        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Buat Password Baru</h1>
            <p class="text-gray-500 text-xs">Silakan masukkan password baru Anda.</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-100 text-red-600 px-4 py-3 rounded-xl text-xs flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($user): ?>
        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Password Baru</label>
                <input type="password" name="password" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Konfirmasi Password</label>
                <input type="password" name="confirm_password" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3.5 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition">
            </div>

            <button type="submit" class="w-full bg-primary hover:bg-green-600 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-primary/30 transition transform hover:-translate-y-0.5 mt-2">
                Simpan Password
            </button>
        </form>
        <?php else: ?>
            <div class="text-center mt-6">
                <a href="forgot-password.php" class="text-primary font-bold hover:underline">Kirim Ulang Link Reset</a>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>