<?php
// Pastikan baris ini sesuai dengan lokasi vendor composer Anda
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Mengirim Email OTP untuk Verifikasi Pendaftaran
 */
function sendOTPEmail($toEmail, $toName, $otpCode) {
    $mail = new PHPMailer(true);

    try {
        // --- KONFIGURASI SMTP HOSTINGER ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'verify@densucode.com'; 
        $mail->Password   = 'Tokopedia31!';        // Password Email
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port       = 465;

        // --- PENGIRIM & PENERIMA ---
        $mail->setFrom('verify@densucode.com', 'Densu Kost');
        $mail->addAddress($toEmail, $toName);

        // --- KONTEN EMAIL ---
        $mail->isHTML(true);
        $mail->Subject = 'Kode Verifikasi OTP - Densu Kost';
        
        // Template Email (Style Minimalis)
        $bodyContent = "
        <div style='font-family: Poppins, sans-serif; background-color: #f9fafb; padding: 40px 0; color: #374151;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #f3f4f6;'>
                <div style='background-color: #ffffff; padding: 30px; text-align: center; border-bottom: 1px solid #f3f4f6;'>
                    <h2 style='margin: 0; color: #10b981; font-size: 24px; font-weight: 700;'>Densu<span style='color: #1e3a8a;'>Kost</span></h2>
                </div>
                <div style='padding: 40px 30px; text-align: center;'>
                    <h1 style='margin-top: 0; font-size: 20px; font-weight: 600; color: #111827;'>Verifikasi Akun Anda</h1>
                    <p style='color: #6b7280; font-size: 14px; margin-bottom: 30px; line-height: 1.6;'>
                        Terima kasih telah mendaftar. Gunakan kode OTP berikut untuk memverifikasi akun Anda.
                    </p>
                    <div style='margin: 30px 0;'>
                        <span style='display: inline-block; font-size: 32px; font-weight: 700; letter-spacing: 5px; color: #10b981; background-color: #ecfdf5; padding: 15px 30px; border-radius: 12px; border: 1px dashed #10b981;'>
                            {$otpCode}
                        </span>
                    </div>
                    <p style='color: #9ca3af; font-size: 12px; margin-top: 30px;'>
                        Kode ini berlaku selama 15 menit. Jangan berikan kode ini kepada siapapun.
                    </p>
                </div>
                <div style='background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #f3f4f6;'>
                    <p style='margin: 0; font-size: 11px; color: #9ca3af;'>
                        &copy; " . date('Y') . " Densu Kost. All rights reserved.
                    </p>
                </div>
            </div>
        </div>";

        $mail->Body = $bodyContent;
        $mail->AltBody = "Kode OTP Anda adalah: $otpCode"; 

        $mail->send();
        return true;

    } catch (Exception $e) {
        // Uncomment baris bawah untuk debugging jika diperlukan
        // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        return false;
    }
}

/**
 * Mengirim Email Reset Password (Lupa Password)
 */
function sendResetEmail($toEmail, $toName, $resetLink) {
    $mail = new PHPMailer(true);

    try {
        // Konfigurasi SMTP (Sama dengan sendOTPEmail)
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'verify@densucode.com'; 
        $mail->Password   = 'Tokopedia31!'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('verify@densucode.com', 'Densu Kost');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Reset Password - Densu Kost';
        
        $bodyContent = "
        <div style='font-family: Poppins, sans-serif; background-color: #f9fafb; padding: 40px 0; color: #374151;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #f3f4f6;'>
                <div style='background-color: #ffffff; padding: 30px; text-align: center; border-bottom: 1px solid #f3f4f6;'>
                    <h2 style='margin: 0; color: #10b981; font-size: 24px; font-weight: 700;'>Densu<span style='color: #1e3a8a;'>Kost</span></h2>
                </div>
                <div style='padding: 40px 30px; text-align: center;'>
                    <h1 style='margin-top: 0; font-size: 20px; font-weight: 600; color: #111827;'>Reset Password</h1>
                    <p style='color: #6b7280; font-size: 14px; margin-bottom: 30px; line-height: 1.6;'>
                        Kami menerima permintaan untuk mereset password akun Anda. Klik tombol di bawah ini untuk melanjutkan.
                    </p>
                    
                    <div style='margin: 30px 0;'>
                        <a href='{$resetLink}' style='display: inline-block; background-color: #10b981; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);'>Buat Password Baru</a>
                    </div>

                    <p style='color: #9ca3af; font-size: 12px; margin-top: 30px;'>
                        Link ini berlaku selama 1 jam. Jika tombol tidak berfungsi, salin link ini:<br>
                        <a href='{$resetLink}' style='color: #10b981; word-break: break-all;'>{$resetLink}</a>
                    </p>
                </div>
                <div style='background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #f3f4f6;'>
                    <p style='margin: 0; font-size: 11px; color: #9ca3af;'>
                        &copy; " . date('Y') . " Densu Kost. All rights reserved.
                    </p>
                </div>
            </div>
        </div>";

        $mail->Body = $bodyContent;
        $mail->AltBody = "Link Reset Password: $resetLink";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}