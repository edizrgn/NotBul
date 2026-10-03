<?php
declare(strict_types=1);

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/upload_config.php';

final class NoteFileException extends RuntimeException {}

function noteFileUndoSeconds(): int
{
    return 24 * 60 * 60;
}

function noteFileSnapshot(array $note): array
{
    return array_intersect_key($note, array_flip([
        'original_filename', 'stored_filename', 'storage_disk', 'storage_path',
        'sha256', 'file_size', 'mime_type',
    ]));
}

function noteFileIdentity(array $note): string
{
    return hash('sha256', (resolveNoteStoragePath($note) ?? '') . "\n" . (string)($note['sha256'] ?? ''));
}

function noteFileHistoryPath(int $noteId): string
{
    return getNoteStorageDir() . '.versions/' . $noteId . '.json';
}

function withNoteFileLock(int $noteId, callable $callback): mixed
{
    if ($noteId <= 0) {
        throw new NoteFileException('Not bulunamadı.');
    }
    $directory = getNoteStorageDir() . '.versions';
    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new NoteFileException('Dosya değişikliği kaydedilemedi. Depolama klasörü izinlerini kontrol edin.');
    }
    if (is_link($directory) || is_link($directory . '/' . $noteId . '.lock')) {
        throw new RuntimeException('Unsafe note history directory.');
    }
    $lock = @fopen($directory . '/' . $noteId . '.lock', 'c');
    if ($lock === false) {
        throw new NoteFileException('Dosya değişikliği için depolama kilidi oluşturulamadı.');
    }
    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Could not lock note file history.');
        }
        return $callback();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function readNoteFileHistory(int $noteId): array
{
    $path = noteFileHistoryPath($noteId);
    if (!file_exists($path)) {
        return [];
    }
    if (is_link($path)) {
        throw new RuntimeException('Unsafe note history file.');
    }
    $contents = @file_get_contents($path);
    $history = $contents === false ? null : json_decode($contents, true);
    if (!is_array($history) || !isset($history['entries']) || !is_array($history['entries'])) {
        throw new RuntimeException('Note history could not be read.');
    }
    foreach ($history['entries'] as $entry) {
        if (!is_array($entry) || !is_string($entry['token'] ?? null)
            || !is_int($entry['expires_at'] ?? null)
            || !is_array($entry['previous'] ?? null) || !is_array($entry['current'] ?? null)) {
            throw new RuntimeException('Invalid note history entry.');
        }
    }
    return $history['entries'];
}

function writeNoteFileHistory(int $noteId, array $entries): void
{
    $path = noteFileHistoryPath($noteId);
    if ($entries === []) {
        if (file_exists($path) && !@unlink($path)) {
            throw new RuntimeException('Could not remove note history.');
        }
        return;
    }
    $json = json_encode(['entries' => array_values($entries)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
    try {
        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
            throw new RuntimeException('Could not write note history.');
        }
        @chmod($temporary, 0640);
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Could not save note history.');
        }
    } finally {
        if (file_exists($temporary)) {
            @unlink($temporary);
        }
    }
}

function pruneNoteFileHistory(int $noteId, array $entries, array $note): array
{
    $protected = [resolveNoteStoragePath($note) => true];
    foreach ($entries as $entry) {
        if ($entry['expires_at'] > time()) {
            foreach (['previous', 'current'] as $key) {
                $protected[resolveNoteStoragePath($entry[$key])] = true;
            }
        }
    }
    $remaining = [];
    foreach ($entries as $entry) {
        if ($entry['expires_at'] > time()) {
            $remaining[] = $entry;
            continue;
        }
        $failed = false;
        foreach (['previous', 'current'] as $key) {
            if (!isset($protected[resolveNoteStoragePath($entry[$key])])) {
                $warning = deleteNotePhysicalFile($entry[$key]);
                if ($warning !== null) {
                    error_log('note file expiration: ' . $warning);
                    $failed = true;
                }
            }
        }
        if ($failed) {
            $remaining[] = $entry;
        }
    }
    if ($remaining !== $entries) {
        writeNoteFileHistory($noteId, $remaining);
    }
    return $remaining;
}

function lockEditableNoteFile(PDO $pdo, int $noteId, int $actorId, bool $asAdmin): array
{
    // Yetkiyi oturumdaki role yerine veritabanındaki güncel role göre doğrula.
    if ($asAdmin) {
        $actorStmt = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
        $actorStmt->execute(['id' => $actorId]);
        if ($actorStmt->fetchColumn() !== 'admin') {
            throw new NoteFileException('Bu işlem için yetkiniz yok.');
        }
    }
    $stmt = $pdo->prepare('SELECT * FROM notes WHERE id = :id LIMIT 1 FOR UPDATE');
    $stmt->execute(['id' => $noteId]);
    $note = $stmt->fetch();
    if (!$note || (!$asAdmin && (int)$note['user_id'] !== $actorId)) {
        throw new NoteFileException('Not bulunamadı veya bu notu düzenleme yetkiniz yok.');
    }
    return $note;
}

function findNoteFileUndo(array $entries, array $note): ?array
{
    foreach (array_reverse($entries) as $entry) {
        if ($entry['expires_at'] > time()
            && hash_equals(noteFileIdentity($entry['current']), noteFileIdentity($note))) {
            return $entry;
        }
    }
    return null;
}

function noteFileUndoInfo(PDO $pdo, array $note, int $actorId, bool $asAdmin = false): ?array
{
    $noteId = (int)$note['id'];
    if (!file_exists(noteFileHistoryPath($noteId))) {
        return null;
    }
    return withNoteFileLock($noteId, function () use ($pdo, $noteId, $actorId, $asAdmin): ?array {
        $pdo->beginTransaction();
        try {
            $current = lockEditableNoteFile($pdo, $noteId, $actorId, $asAdmin);
            $entries = pruneNoteFileHistory($noteId, readNoteFileHistory($noteId), $current);
            $undo = findNoteFileUndo($entries, $current);
            $pdo->commit();
            return $undo;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    });
}

function storeNoteReplacementUpload(array $file): array
{
    $error = is_int($file['error'] ?? null) ? $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        throw new NoteFileException(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu ' . getMaxUploadMb() . ' MB sınırını aşıyor.',
            UPLOAD_ERR_NO_FILE => 'Lütfen yeni dosyayı seçin.',
            UPLOAD_ERR_PARTIAL => 'Dosya yüklemesi tamamlanamadı. Lütfen tekrar deneyin.',
            default => 'Dosya yüklenemedi. Lütfen tekrar deneyin.',
        });
    }
    $temporary = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
    $filename = is_string($file['name'] ?? null) ? $file['name'] : '';
    $filename = basename(str_replace('\\', '/', $filename));
    $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';
    if (!is_uploaded_file($temporary) || $filename === '' || mb_strlen($filename) > 255) {
        throw new NoteFileException('Dosya veya dosya adı doğrulanamadı. Lütfen tekrar seçin.');
    }
    $size = filesize($temporary);
    if ($size === false || $size < 1 || $size > getMaxUploadBytes()) {
        throw new NoteFileException('Dosya boş olmamalı ve ' . getMaxUploadMb() . ' MB sınırını aşmamalı.');
    }
    $types = [
        'pdf' => 'application/pdf', 'png' => 'image/png',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    // Bazı sunucular Office belgelerini application/zip olarak tanımlar.
    if (in_array($extension, ['docx', 'pptx'], true) && $mime === 'application/zip' && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($temporary) === true) {
            $part = $extension === 'docx' ? 'word/document.xml' : 'ppt/presentation.xml';
            $mainType = $extension === 'docx' ? 'wordprocessingml.document' : 'presentationml.presentation';
            $stat = $zip->statName('[Content_Types].xml');
            $contentTypes = $stat && $stat['size'] <= 1024 * 1024 ? $zip->getFromName('[Content_Types].xml') : false;
            if ($zip->locateName($part) !== false && is_string($contentTypes)
                && str_contains($contentTypes, 'application/vnd.openxmlformats-officedocument.' . $mainType . '.main+xml')) {
                $mime = $types[$extension];
            }
            $zip->close();
        }
    }
    if (!isset($types[$extension]) || $mime !== $types[$extension]) {
        throw new NoteFileException('Desteklenmeyen dosya formatı. PDF, DOCX, PPTX, PNG, JPG veya WEBP seçin.');
    }
    $sha256 = hash_file('sha256', $temporary);
    if ($sha256 === false) {
        throw new NoteFileException('Dosya bütünlüğü doğrulanamadı.');
    }
    $relativeDirectory = date('Y/m');
    $directory = buildNoteAbsolutePath($relativeDirectory);
    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new NoteFileException('Dosya saklama klasörüne yazılamadı.');
    }
    $root = realpath(getNoteStorageDir());
    $realDirectory = realpath($directory);
    if ($root === false || $realDirectory === false
        || !str_starts_with($realDirectory . '/', rtrim($root, '/') . '/')) {
        throw new RuntimeException('Unsafe upload directory.');
    }
    $storedFilename = bin2hex(random_bytes(16)) . '.' . $extension;
    $path = $relativeDirectory . '/' . $storedFilename;
    if (!move_uploaded_file($temporary, buildNoteAbsolutePath($path))) {
        throw new NoteFileException('Dosya sunucuya taşınamadı. Mevcut dosyanız korundu.');
    }
    @chmod(buildNoteAbsolutePath($path), 0640);
    return [
        'original_filename' => $filename, 'stored_filename' => $storedFilename,
        'storage_disk' => 'local', 'storage_path' => $path,
        'sha256' => $sha256, 'file_size' => $size, 'mime_type' => $mime,
    ];
}

function updateNoteFileSnapshot(PDO $pdo, int $noteId, array $snapshot): void
{
    $stmt = $pdo->prepare('UPDATE notes SET original_filename = :original_filename,
        stored_filename = :stored_filename, storage_disk = :storage_disk, storage_path = :storage_path,
        sha256 = :sha256, file_size = :file_size, mime_type = :mime_type WHERE id = :id LIMIT 1');
    if (!$stmt->execute(noteFileSnapshot($snapshot) + ['id' => $noteId])) {
        throw new RuntimeException('Could not update note file.');
    }
}

function changeNoteFile(PDO $pdo, int $noteId, int $actorId, bool $asAdmin, string $identity, ?array $upload, string $undoToken = ''): array
{
    return withNoteFileLock($noteId, function () use ($pdo, $noteId, $actorId, $asAdmin, $identity, $upload, $undoToken): array {
        $pdo->beginTransaction();
        $newFile = null;
        $beforeHistory = null;
        $commitAttempted = false;
        try {
            $note = lockEditableNoteFile($pdo, $noteId, $actorId, $asAdmin);
            if ($identity === '' || !hash_equals(noteFileIdentity($note), $identity)) {
                throw new NoteFileException('Dosya siz sayfayı açtıktan sonra değişmiş. Sayfayı yenileyip tekrar deneyin.');
            }
            $entries = pruneNoteFileHistory($noteId, readNoteFileHistory($noteId), $note);
            $beforeHistory = $entries;
            if ($upload !== null) {
                $previousPath = resolveNoteAbsolutePath($note);
                if ($previousPath === null) {
                    throw new NoteFileException('Mevcut dosya bulunamadığı için geri alma kopyası korunamıyor.');
                }
                $previous = noteFileSnapshot($note);
                // Eski şemadan taşınan notların SHA-256 alanı boş olabilir.
                $previous['sha256'] = hash_file('sha256', $previousPath);
                if ($previous['sha256'] === false) {
                    throw new NoteFileException('Mevcut dosyanın bütünlüğü doğrulanamadı.');
                }
                $newFile = storeNoteReplacementUpload($upload);
                $snapshot = $newFile;
                $entries[] = [
                    'token' => bin2hex(random_bytes(16)), 'expires_at' => time() + noteFileUndoSeconds(),
                    'previous' => $previous, 'current' => $snapshot,
                ];
                // DB commit edilmeden önce geri alma kaydı kalıcı olarak yazılır.
                writeNoteFileHistory($noteId, $entries);
            } else {
                $undo = findNoteFileUndo($entries, $note);
                if ($undo === null || $undoToken === '' || !hash_equals($undo['token'], $undoToken)) {
                    throw new NoteFileException('Bu değişiklik artık geri alınamıyor. Geri alma süresi 24 saattir.');
                }
                $snapshot = $undo['previous'];
                $previousPath = resolveNoteAbsolutePath($snapshot);
                if ($previousPath === null || !hash_equals((string)$snapshot['sha256'], (string)hash_file('sha256', $previousPath))) {
                    throw new NoteFileException('Önceki dosya bulunamadı veya bütünlüğü doğrulanamadı. Mevcut dosyanız korundu.');
                }
                // Undo sonrası kaydı süre aşımına geçir; commit sonrası güvenle temizlenir.
                foreach ($entries as &$entry) {
                    if ($entry['token'] === $undo['token']) {
                        $entry['expires_at'] = 0;
                    }
                }
                unset($entry);
            }
            updateNoteFileSnapshot($pdo, $noteId, $snapshot);
            $commitAttempted = true;
            if (!$pdo->commit()) {
                throw new RuntimeException('Could not commit note file update.');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newFile !== null && !$commitAttempted) {
                try {
                    writeNoteFileHistory($noteId, $beforeHistory ?? []);
                    deleteNotePhysicalFile($newFile);
                } catch (Throwable $cleanupError) {
                    error_log('note file failed replacement cleanup: ' . $cleanupError->getMessage());
                }
            }
            throw $e;
        }
        $updated = array_replace($note, $snapshot);
        try {
            if ($upload === null) {
                writeNoteFileHistory($noteId, $entries);
            }
            pruneNoteFileHistory($noteId, $entries, $updated);
        } catch (Throwable $cleanupError) {
            // Değişiklik başarılıdır; temizlik bir sonraki erişimde tekrar denenir.
            error_log('note file history cleanup: ' . $cleanupError->getMessage());
        }
        return $updated;
    });
}

function deleteNoteFileHistory(array $note): array
{
    $noteId = (int)($note['id'] ?? 0);
    if ($noteId <= 0 || !file_exists(noteFileHistoryPath($noteId))) {
        return [];
    }
    try {
        return withNoteFileLock($noteId, function () use ($noteId): array {
            $warnings = [];
            foreach (readNoteFileHistory($noteId) as $entry) {
                foreach (['previous', 'current'] as $key) {
                    $warning = deleteNotePhysicalFile($entry[$key]);
                    if ($warning !== null) {
                        $warnings[] = $warning;
                    }
                }
            }
            if ($warnings === []) {
                writeNoteFileHistory($noteId, []);
            }
            return $warnings;
        });
    } catch (Throwable $e) {
        error_log('delete note file history: ' . $e->getMessage());
        return ['Notun geri alma dosyaları sunucudan kaldırılamadı.'];
    }
}
