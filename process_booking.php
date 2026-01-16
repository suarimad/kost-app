<?php
// ==========================================
// CONFIGURATION
// ==========================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// 1. Validasi Request Method (Harus POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// 2. Validasi Session
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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
    die("Connection failed: " . $e->getMessage());
}

// ==========================================
// 3. AMBIL DATA POST
// ==========================================
$roomId = $_POST['room_id'];
$userId = $_POST['user_id'];
$name = trim($_POST['name']);
$phone = trim($_POST['phone']);
$legalId = trim($_POST['legal_id']);
$city = trim($_POST['city']);
$occupation = $_POST['occupation'];
$startDate = $_POST['start_date'];
$duration = (int)$_POST['duration']; 
$pricePerMonth = $_POST['price_per_month'];
$depositAmount = $_POST['deposit_amount'];

// Hitung Total
$totalPrice = ($pricePerMonth * $duration) + $depositAmount;
$endDate = date('Y-m-d', strtotime("+$duration months", strtotime($startDate)));
$orderId = 'INV-' . date('Ymd') . '-' . $userId . '-' . $roomId . '-' . rand(100, 999);

// ==========================================
// 4. CEK KETERSEDIAAN KAMAR
// ==========================================
$stmtCheck = $pdo->prepare("SELECT is_booked, name FROM rooms WHERE id = ?");
$stmtCheck->execute([$roomId]);
$roomData = $stmtCheck->fetch();

if (!$roomData || $roomData['is_booked'] == 1) {
    echo "<script>alert('Gagal! Kamar baru saja dibooking orang lain.'); window.location.href='index.php';</script>";
    exit;
}

// ==========================================
// 5. HANDLE UPLOAD KTP
// ==========================================
$ktpPath = null;
if (isset($_FILES['legal_id_image']) && $_FILES['legal_id_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'assets/img/users/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $cleanName = preg_replace('/[^a-zA-Z0-9]/', '', strtolower(str_replace(' ', '', $name)));
    $fileName = $cleanName . '-' . $legalId . '-' . time() . '.png';
    $targetFile = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['legal_id_image']['tmp_name'], $targetFile)) {
        $ktpPath = $fileName;
    }
}

// ==========================================
// 6. GET SETTINGS & PAYMENT LOGIC
// ==========================================
$paymentSettings = $pdo->query("SELECT * FROM payment_settings WHERE deleted_at IS NULL LIMIT 1")->fetch();
$paymentType = $paymentSettings['payment_type'] ?? 'manual';
$mtServerKey = $paymentSettings['mt_serverkey'] ?? '';

// DETEKSI ENVIRONMENT (Sandbox / Production)
$isSandbox = (strpos($mtServerKey, 'SB') === 0); // Jika diawali 'SB', return true
$midtransApiUrl = $isSandbox 
    ? 'https://app.sandbox.midtrans.com/snap/v1/transactions' 
    : 'https://app.midtrans.com/snap/v1/transactions';

// ==========================================
// 7. DATABASE TRANSACTION
// ==========================================
$pdo->beginTransaction();

try {
    // A. Update Data User
    $sqlUser = "UPDATE users SET 
                phone = ?, legal_id = ?, city = ?, occupation = ?, lease_expires_at = ?, updated_at = NOW()";
    $paramsUser = [$phone, $legalId, $city, $occupation, $endDate];

    if ($ktpPath) {
        $sqlUser .= ", legal_id_image = ?";
        $paramsUser[] = $ktpPath;
    }
    
    $sqlUser .= " WHERE id = ?";
    $paramsUser[] = $userId;

    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute($paramsUser);

    // B. Insert Booking Awal (Status Pending)
    $sqlBooking = "INSERT INTO bookings (
        user_id, room_id, order_id, start_date, duration, 
        price_per_month, deposit_amount, total_amount, 
        payment_method, payment_status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmtBooking = $pdo->prepare($sqlBooking);
    $stmtBooking->execute([
        $userId, $roomId, $orderId, $startDate, $duration,
        $pricePerMonth, $depositAmount, $totalPrice,
        $paymentType, 'pending'
    ]);
    
    $bookingId = $pdo->lastInsertId();

    // C. GENERATE SNAP TOKEN (Jika Automatic)
    if ($paymentType == 'automatic' && !empty($mtServerKey)) {
        
        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int)$totalPrice,
            ],
            'customer_details' => [
                'first_name' => $name,
                'phone' => $phone,
                'email' => $_SESSION['user_email'] ?? 'guest@example.com' 
            ],
            'item_details' => [
                [
                    'id' => 'ROOM-' . $roomId,
                    'price' => (int)$pricePerMonth,
                    'quantity' => $duration,
                    'name' => 'Sewa ' . substr($roomData['name'], 0, 20) . ' (' . $duration . ' Bln)'
                ]
            ]
        ];

        if ($depositAmount > 0) {
            $payload['item_details'][] = [
                'id' => 'DEPOSIT',
                'price' => (int)$depositAmount,
                'quantity' => 1,
                'name' => 'Deposit Jaminan'
            ];
        }

        // Curl Request (Dynamic URL based on Environment)
        $auth = base64_encode($mtServerKey . ':');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $midtransApiUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . $auth
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $mtResponse = json_decode($response, true);
        
        if ($httpCode == 201 && isset($mtResponse['token'])) {
            $snapToken = $mtResponse['token'];
            $pdo->prepare("UPDATE bookings SET snap_token = ? WHERE id = ?")
                ->execute([$snapToken, $bookingId]);
        } else {
            throw new Exception("Gagal membuat Token Pembayaran (Midtrans Error: " . ($mtResponse['error_messages'][0] ?? 'Unknown') . ")");
        }
    }

    $pdo->commit();

    if ($paymentType == 'automatic') {
        header("Location: payment.php?order_id=" . $orderId);
    } else {
        header("Location: invoice.php?order_id=" . $orderId);
    }
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("<div style='padding:20px; color:red; border:1px solid red; background:#fff0f0;'>
            <h3>Terjadi Kesalahan:</h3>
            <p>" . $e->getMessage() . "</p>
            <a href='index.php'>Kembali ke Beranda</a>
         </div>");
}
?>