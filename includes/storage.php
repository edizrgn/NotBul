<?php
declare(strict_types=1);

function getNoteStorageDir(): string
{
    $configuredPath = getenv('NOTBUL_NOTE_STORAGE_DIR');
    if (is_string($configuredPath) && trim($configuredPath) !== '') {
        return rtrim($configuredPath, "/\\") . DIRECTORY_SEPARATOR;
    }

    return '/var/lib/notbul/notes/';
}

function resolveNoteStoragePath(array $note): ?string
{
    $storagePath = trim((string)($note['storage_path'] ?? ''));
    if ($storagePath === '' && !empty($note['stored_filename'])) {
        $storagePath = (string)$note['stored_filename'];
    }

    $storagePath = str_replace('\\', '/', $storagePath);
    $storagePath = ltrim($storagePath, '/');

    if ($storagePath === '' || strpos($storagePath, '..') !== false) {
        return null;
    }

    return $storagePath;
}

function buildNoteAbsolutePath(string $storagePath): string
{
    return rtrim(getNoteStorageDir(), "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
}

function resolveNoteAbsolutePath(array $note): ?string
{
    if (!in_array(trim((string)($note['storage_disk'] ?? 'local')), ['', 'local'], true)) {
        return null;
    }
    $path = resolveNoteStoragePath($note);
    $root = realpath(getNoteStorageDir());
    $file = $path === null ? false : realpath(buildNoteAbsolutePath($path));
    if ($root === false || $file === false || !is_file($file)) {
        return null;
    }
    $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return strncmp($file, $root, strlen($root)) === 0 ? $file : null;
}

function deleteNotePhysicalFile(array $note): ?string
{
    $storageDisk = trim((string)($note['storage_disk'] ?? 'local'));
    if ($storageDisk !== '' && $storageDisk !== 'local') {
        return 'Not kaydı silindi ancak storage disk local olmadığı için dosya otomatik kaldırılmadı.';
    }

    $storagePath = resolveNoteStoragePath($note);
    if ($storagePath === null) {
        return 'Not kaydı silindi ancak dosya yolu geçersiz olduğu için dosya kontrol edilemedi.';
    }

    $filePath = buildNoteAbsolutePath($storagePath);
    if (!file_exists($filePath)) {
        return null;
    }

    $storageRoot = realpath(getNoteStorageDir());
    $realFilePath = realpath($filePath);

    if ($storageRoot === false || $realFilePath === false) {
        return 'Not kaydı silindi ancak dosya yolu doğrulanamadığı için dosya kaldırılmadı.';
    }

    $storageRoot = rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($realFilePath, $storageRoot, strlen($storageRoot)) !== 0 || !is_file($realFilePath)) {
        return 'Not kaydı silindi ancak dosya güvenli depolama klasörü dışında göründüğü için kaldırılmadı.';
    }

    if (!@unlink($realFilePath)) {
        return 'Not kaydı silindi ancak dosya sunucudan kaldırılamadı. Dosya izinlerini kontrol edin.';
    }

    return null;
}

function deleteNoteStorageFile(array $note): ?string
{
    // Kalıcı not/hesap silme işlemleri geri alma kopyalarını da kaldırır.
    require_once __DIR__ . '/note_files.php';
    $warnings = deleteNoteFileHistory($note);
    $warning = deleteNotePhysicalFile($note);
    if ($warning !== null) {
        $warnings[] = $warning;
    }
    return $warnings === [] ? null : implode(' ', array_unique($warnings));
}

function deleteNotesStorageFiles(array $notes): array
{
    $warnings = [];

    foreach ($notes as $note) {
        if (!is_array($note)) {
            continue;
        }

        $warning = deleteNoteStorageFile($note);
        if ($warning !== null) {
            $noteId = isset($note['id']) ? (int)$note['id'] : 0;
            $warnings[] = ($noteId > 0 ? 'Not #' . $noteId . ': ' : '') . $warning;
        }
    }

    return $warnings;
}
