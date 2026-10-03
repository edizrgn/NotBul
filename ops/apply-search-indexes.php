<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/db.php';
$sql = preg_replace('/^--.*$/m', '', (string)file_get_contents(__DIR__ . '/search-indexes.sql'));
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    $pdo->exec($statement);
}
echo "Arama indeksleri hazır.\n";
