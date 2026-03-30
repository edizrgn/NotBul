<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * @throws RuntimeException
 */
function sendVerificationEmail(string $recipientEmail, string $recipientName, string $verificationUrl): void
{
    $apiKey = envValue('BREVO_API_KEY');
    if ($apiKey === null || $apiKey === '') {
        throw new RuntimeException('BREVO_API_KEY bulunamadı.');
    }

    $senderEmail = envValue('BREVO_SENDER_EMAIL', 'no-reply@notbul.site');
    $senderName = envValue('BREVO_SENDER_NAME', 'Not Bul');

    $subject = 'Not Bul hesap doğrulama';
    $safeRecipientName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');

    $htmlContent = "
        <p>Merhaba {$safeRecipientName},</p>
        <p>Not Bul hesabını aktifleştirmek için aşağıdaki bağlantıya tıkla:</p>
        <p><a href=\"{$safeUrl}\">Hesabımı doğrula</a></p>
        <p>Bağlantı 24 saat içinde geçerliliğini yitirir.</p>
        <p>Eğer bu kaydı sen oluşturmadıysan bu e-postayı görmezden gelebilirsin.</p>
    ";

    $payload = [
        'sender' => [
            'name' => $senderName,
            'email' => $senderEmail,
        ],
        'to' => [
            [
                'email' => $recipientEmail,
                'name' => $recipientName,
            ],
        ],
        'subject' => $subject,
        'htmlContent' => $htmlContent,
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($ch === false) {
        throw new RuntimeException('Brevo isteği başlatılamadı.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Brevo isteği başarısız: ' . $curlError);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $decoded = json_decode($response, true);
        $message = is_array($decoded) && isset($decoded['message']) ? (string) $decoded['message'] : 'Bilinmeyen hata';
        throw new RuntimeException('Brevo doğrulama e-postası gönderemedi: ' . $message);
    }
}

function buildAppBaseUrl(): string
{
    $configuredUrl = envValue('APP_BASE_URL');
    if ($configuredUrl !== null && $configuredUrl !== '') {
        return rtrim($configuredUrl, '/');
    }

    $https = $_SERVER['HTTPS'] ?? '';
    $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}
