<?php
session_start();
define('ACCESS_KEY', 'ngapain?');

if (!isset($_SESSION['verify_email'])) {
    header("Location: register.php");
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
$email = $_SESSION['verify_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_input = trim($_POST['otp']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND otp_code = ? AND is_verified = 0");
    $stmt->execute([$email, $otp_input]);
    $user = $stmt->fetch();

    if ($user) {
        if (strtotime($user['otp_expires_at']) < time()) {
            $error = "Kode OTP telah kadaluarsa.";
        } else {
            $update = $pdo->prepare("UPDATE users SET is_verified = 1, otp_code = NULL WHERE id = ?");
            $update->execute([$user['id']]);
            unset($_SESSION['verify_email']);
            $_SESSION['success_msg'] = "Akun berhasil diverifikasi. Silakan login.";
            header("Location: login.php");
            exit;
        }
    } else {
        $error = "Kode OTP salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP - <?= htmlspecialchars($company['name']) ?></title>
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
<body class="font-sans text-sm text-gray-600 bg-gray-50 h-screen w-screen overflow-hidden flex items-center justify-center">

    <div class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-gray-100 p-10 text-center relative">
        <a href="login.php" class="absolute top-6 left-6 text-gray-400 hover:text-primary transition"><i class="fa-solid fa-arrow-left text-xl"></i></a>

        <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fa-solid fa-shield-halved text-2xl"></i>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-2">Verifikasi OTP</h1>
        <p class="text-gray-500 mb-6 text-xs">Kami telah mengirimkan kode 6 digit ke email: <strong><?= htmlspecialchars($email) ?></strong></p>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-100 text-red-600 px-4 py-2 rounded-xl text-xs flex items-center justify-center gap-2">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="mb-6">
                <input type="text" name="otp" maxlength="6" required 
                       class="w-full text-center text-3xl font-bold tracking-[0.5em] bg-gray-50 border border-gray-200 rounded-xl px-4 py-4 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition uppercase text-gray-800 placeholder-gray-300"
                       placeholder="000000">
            </div>

            <button type="submit" class="w-full bg-primary hover:bg-green-600 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-primary/30 transition transform hover:-translate-y-0.5">
                Verifikasi Akun
            </button>
        </form>

        <div class="mt-6 text-xs text-gray-400">
            Tidak menerima kode? <a href="login.php" class="text-primary font-bold hover:underline">Kirim Ulang</a>
        </div>
    </div>

</body>
</html>