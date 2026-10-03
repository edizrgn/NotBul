<?php
declare(strict_types=1);

@session_start();
require_once __DIR__ . '/includes/auth_redirect.php';
$requestId = $_GET['id'] ?? 0;
$noteId = is_scalar($requestId) ? (int)$requestId : 0;
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . authLoginUrl('note-file-download.php?id=' . $noteId));
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/storage.php';

// Yönetim indirmesi arşiv/yayın durumundan bağımsızdır ve sayacı artırmaz.
$stmt = $pdo->prepare("SELECT n.* FROM notes n
    JOIN users viewer ON viewer.id = :actor_id
    WHERE n.id = :id AND (n.user_id = :owner_id OR viewer.role = 'admin') LIMIT 1");
$stmt->execute(['id' => $noteId, 'actor_id' => (int)$_SESSION['user_id'], 'owner_id' => (int)$_SESSION['user_id']]);
$note = $stmt->fetch();
$path = $note ? resolveNoteAbsolutePath($note) : null;
$file = $path === null ? false : @fopen($path, 'rb');
if ($file === false) {
    http_response_code(404);
    exit('Not dosyası bulunamadı veya bu dosyaya erişim yetkiniz yok.');
}

$filename = basename(str_replace('\\', '/', (string)$note['original_filename']));
$filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?: 'not-' . $noteId;
$stat = fstat($file);
session_write_close();
header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . $stat['size']);
header('Content-Disposition: attachment; filename="' . addcslashes($filename, "\\\"") . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
fpassthru($file);
fclose($file);
