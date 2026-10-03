<?php
declare(strict_types=1);

// php tests/note_files_test.php
// Gerçek multipart upload, oturum, CSRF ve indirme endpoint'lerini test eder.
// Dosyalar /tmp altında, PDO verileri fixture içinde izole tutulur.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/note_files.php';

$directory = sys_get_temp_dir() . '/notbul-file-test-' . bin2hex(random_bytes(6));
$web = $directory . '/web';
$storage = $directory . '/storage';
mkdir($web . '/includes', 0750, true);
mkdir($web . '/assets/data', 0750, true);
mkdir($storage, 0750, true);
putenv('NOTBUL_NOTE_STORAGE_DIR=' . $storage);
$statePath = $directory . '/database.json';
$checks = 0;
$server = null;
$failed = false;

function verify(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function state(?callable $update = null): array
{
    global $statePath;
    $data = json_decode((string)file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
    if ($update !== null) {
        $data = $update($data);
        file_put_contents($statePath, json_encode($data, JSON_THROW_ON_ERROR));
    }
    return $data;
}

function request(string $path, string $cookie = '', ?array $fields = null, ?array $file = null): array
{
    global $base;
    $curl = curl_init($base . '/' . $path);
    $headers = [];
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_COOKIE => $cookie,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$headers): int {
            if (str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $headers[strtolower($name)] = trim($value);
            }
            return strlen($header);
        },
    ]);
    if ($fields !== null) {
        if ($file !== null) {
            $fields['note_file'] = new CURLFile($file['path'], 'application/octet-stream', $file['name']);
        }
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
    }
    $body = curl_exec($curl);
    if ($body === false) {
        throw new RuntimeException('HTTP test request failed: ' . curl_error($curl));
    }
    $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return ['code' => $code, 'headers' => $headers, 'body' => $body];
}

function fileForm(string $html, string $action): array
{
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($document);
    $form = $xpath->query('//form[.//input[@name="action" and @value="' . $action . '"]]')->item(0);
    if ($form === null) {
        throw new RuntimeException('Missing form: ' . $action);
    }
    $fields = [];
    foreach ($xpath->query('.//input[@type="hidden"]', $form) as $input) {
        $fields[$input->getAttribute('name')] = $input->getAttribute('value');
    }
    return $fields;
}

function formFor(string $page, string $cookie, string $action = 'replace_file'): array
{
    $response = request($page . '?id=7', $cookie);
    verify($response['code'] === 200, 'Edit page must render.');
    return fileForm($response['body'], $action);
}

function metadataForm(string $html): array
{
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($document);
    $form = $xpath->query('//form[.//input[@name="title"]]')->item(0);
    if ($form === null) {
        throw new RuntimeException('Missing metadata form.');
    }
    $fields = [];
    foreach ($xpath->query('.//input[@name and not(@type="file")] | .//textarea[@name] | .//select[@name]', $form) as $input) {
        $name = $input->getAttribute('name');
        if ($input->tagName === 'select') {
            $options = $xpath->query('.//option[@selected]', $input);
            $option = $options->item(0) ?? $xpath->query('.//option', $input)->item(0);
            $fields[$name] = $option?->getAttribute('value') ?? '';
        } else {
            $fields[$name] = $input->tagName === 'textarea' ? $input->textContent : $input->getAttribute('value');
        }
    }
    return $fields;
}

function storedFiles(): array
{
    global $storage;
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && !str_contains($file->getPathname(), '/.versions/')) {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

function removeTestDirectory(string $directory): void
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($directory);
}

try {
    $root = dirname(__DIR__);
    foreach (['note-edit.php', 'admin-note-edit.php', 'note-file-download.php', 'view.php', 'admin.php', 'profile_edit.php',
        'includes/storage.php', 'includes/note_files.php', 'includes/note_file_panel.php', 'includes/upload_config.php',
        'includes/note_form.php', 'includes/auth_redirect.php', 'includes/admin_auth.php', 'includes/header.php',
        'includes/footer.php', 'includes/env.php', 'includes/ratings.php', 'includes/admin_suggested_actions.php',
        'assets/data/universiteler.json', 'assets/data/bolumler.json'] as $file) {
        copy($root . '/' . $file, $web . '/' . $file);
    }
    copy(__DIR__ . '/fixtures/note_file_database.php', $web . '/includes/test_database.php');
    file_put_contents($web . '/includes/db.php', '<?php require __DIR__ . "/test_database.php"; $pdo = new NoteFileTestPDO(getenv("NOTBUL_TEST_STATE"));');
    file_put_contents($web . '/includes/admin_notifications.php', '<?php function sendAdminNotification(...$args) {} function adminNotificationAdminLabel(...$args) { return "Admin"; } function adminNotificationUserLabel(...$args) { return "Owner"; } function adminNotificationUrl($url) { return $url; }');
    file_put_contents($web . '/includes/user_notifications.php', '<?php function sendAdminActionUserNotification(...$args) {} function sendUserSecurityNotification(...$args) {} function userNotificationUrl($url) { return $url; }');
    file_put_contents($web . '/test-session.php', '<?php session_start(); $_SESSION = ["user_id" => (int)$_GET["user"], "first_name" => "Test", "role" => "admin", "csrf_token_admin_panel" => "fixture-admin-token", "csrf_token_profile_edit" => "fixture-profile-token"]; echo "ok";');

    $original = "%PDF-1.4\noriginal note\n%%EOF\n";
    $replacement = "%PDF-1.4\nreplacement note\n%%EOF\n";
    file_put_contents($storage . '/original.pdf', $original);
    file_put_contents($directory . '/replacement.pdf', $replacement);
    $upload = ['path' => $directory . '/replacement.pdf', 'name' => 'yeni not.pdf'];
    $note = [
        'id' => 7, 'user_id' => 1, 'title' => 'Matematik', 'description' => 'Korunacak açıklama',
        'university_id' => null, 'department_type' => null, 'department_id' => null, 'class_id' => null,
        'course' => 'Matematik', 'topic' => 'Limit', 'tags' => 'ders',
        'original_filename' => 'eski not.pdf', 'stored_filename' => 'original.pdf',
        'storage_disk' => 'local', 'storage_path' => 'original.pdf', 'sha256' => hash('sha256', $original),
        'file_size' => strlen($original), 'mime_type' => 'application/pdf',
        'upload_status' => 'ready', 'scan_status' => 'clean', 'deleted_at' => null,
        'download_count' => 17, 'created_at' => '2026-01-01 12:00:00', 'updated_at' => '2026-01-01 12:00:00',
    ];
    $users = [];
    foreach ([1 => 'user', 2 => 'user', 3 => 'admin'] as $id => $role) {
        $users[$id] = ['id' => $id, 'first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.invalid',
            'role' => $role, 'verified' => 1, 'admin_email_notifications' => 0, 'admin_action_user_notifications' => 0,
            'password' => password_hash('fixture-password', PASSWORD_DEFAULT), 'comment_email_notifications' => 0];
    }
    file_put_contents($statePath, json_encode(['notes' => [7 => $note], 'users' => $users], JSON_THROW_ON_ERROR));

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($socket === false) {
        throw new RuntimeException('Could not reserve test port: ' . $errorMessage);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://' . $address;
    $environment = getenv();
    $environment['NOTBUL_TEST_STATE'] = $statePath;
    $server = proc_open([PHP_BINARY, '-d', 'upload_max_filesize=25M', '-d', 'post_max_size=26M',
        '-d', 'session.save_path=' . $directory, '-d', 'date.timezone=Europe/Istanbul', '-S', $address, '-t', $web],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', $directory . '/server.log', 'a'], 2 => ['file', $directory . '/server.log', 'a']], $pipes, $web, $environment);
    if (!is_resource($server)) {
        throw new RuntimeException('Could not start isolated PHP HTTP test server.');
    }
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.1);
        if ($connection !== false) {
            fclose($connection);
            break;
        }
        usleep(20000);
    }
    $cookies = [];
    foreach ([1, 2, 3] as $id) {
        $login = request('test-session.php?user=' . $id);
        $cookies[$id] = explode(';', $login['headers']['set-cookie'])[0];
    }
    [$owner, $stranger, $admin] = [$cookies[1], $cookies[2], $cookies[3]];
    $guestDownload = request('note-file-download.php?id=7');
    verify($guestDownload['code'] === 302, 'Guest download requires login.');
    parse_str((string)parse_url($guestDownload['headers']['location'], PHP_URL_QUERY), $loginParameters);
    verify(($loginParameters['return_to'] ?? '') === 'note-file-download.php?id=7', 'Download destination survives expired-session login.');
    verify(request('note-file-download.php?id=7', $stranger)['code'] === 404, 'Forged session role must not allow download.');
    verify(request('admin-note-edit.php?id=7', $stranger)['code'] === 403, 'Admin edit requires DB admin role.');
    $download = request('note-file-download.php?id=7', $owner);
    verify($download['body'] === $original && str_starts_with($download['headers']['content-disposition'], 'attachment;'), 'Owner downloads actual current file.');
    verify(state()['notes'][7]['download_count'] === 17, 'Management downloads preserve counter.');

    $fields = formFor('note-edit.php', $owner);
    $adminMetadata = metadataForm(request('admin-note-edit.php?id=7', $admin)['body']);
    $bad = $fields;
    $bad['csrf_token'] = 'bad-token';
    verify(str_contains(request('note-edit.php?id=7', $owner, $bad, $upload)['body'], 'Güvenlik doğrulaması'), 'Invalid CSRF is rejected.');
    verify(str_contains(request('note-edit.php?id=7', $owner, $fields, ['path' => $upload['path'], 'name' => 'wrong.docx'])['body'], 'Desteklenmeyen dosya'), 'Extension/content mismatch is rejected.');
    verify(request('note-edit.php?id=7', $stranger, $fields, $upload)['code'] === 302, 'Another owner cannot edit.');
    verify(state()['notes'][7] === $note && count(storedFiles()) === 1, 'Rejected requests keep file and note intact.');

    state(static function ($data) { $data['fail_update'] = true; return $data; });
    verify(request('note-edit.php?id=7', $owner, $fields, $upload)['code'] === 200, 'DB failure is rendered as an error.');
    verify(state()['notes'][7] === $note && count(storedFiles()) === 1 && !file_exists(noteFileHistoryPath(7)), 'Failed replacement restores history and removes staged file.');
    state(static function ($data) { unset($data['fail_update']); return $data; });

    state(static function ($data) { $data['fail_commit'] = true; return $data; });
    request('note-edit.php?id=7', $owner, $fields, $upload);
    verify(state()['notes'][7] === $note && file_get_contents($storage . '/original.pdf') === $original, 'Commit failure keeps original row and file.');
    verify(count(storedFiles()) === 2 && count(readNoteFileHistory(7)) === 1, 'Uncertain commit preserves both files and the recovery journal.');
    state(static function ($data) { unset($data['fail_commit']); return $data; });
    $failedHistory = readNoteFileHistory(7);
    $failedHistory[0]['expires_at'] = 0;
    writeNoteFileHistory(7, $failedHistory);
    formFor('note-edit.php', $owner);
    verify(count(storedFiles()) === 1, 'Expired uncommitted upload is safely cleaned up.');

    $response = request('note-edit.php?id=7', $owner, $fields, $upload);
    verify($response['code'] === 303, 'Owner replacement uses POST/redirect/GET.');
    $changed = state()['notes'][7];
    verify(file_get_contents(resolveNoteAbsolutePath($changed)) === $replacement, 'Uploaded bytes are served from the new file.');
    verify(file_get_contents($storage . '/original.pdf') === $original, 'Previous file is retained.');
    verify($changed['original_filename'] === $upload['name'] && $changed['sha256'] === hash('sha256', $replacement)
        && $changed['file_size'] === strlen($replacement) && $changed['mime_type'] === 'application/pdf', 'Metadata comes from actual upload.');
    verify(array_diff_key($changed, noteFileSnapshot($changed) + ['updated_at' => true]) === array_diff_key($note, noteFileSnapshot($note) + ['updated_at' => true]), 'Note ID, ownership, metadata, counters and moderation remain intact.');
    verify($changed['updated_at'] !== $note['updated_at'], 'File change updates note modification timestamp.');
    $publicView = request('view.php?id=7');
    verify($publicView['body'] === $replacement && str_contains($publicView['headers']['cache-control'], 'no-store'), 'Public preview serves current bytes without caching a stale revision.');
    $entry = readNoteFileHistory(7)[0];
    verify(abs($entry['expires_at'] - time() - 86400) < 5, 'Undo window is 24 hours.');
    verify(str_contains(request('note-edit.php?id=7', $owner, $fields, $upload)['body'], 'Dosya siz sayfayı açtıktan sonra değişmiş'), 'Stale replacement/double submit cannot overwrite a new file.');
    verify(str_contains(request('admin-note-edit.php?id=7', $admin, $adminMetadata)['body'], 'Dosya siz sayfayı açtıktan sonra değişmiş'), 'Stale admin metadata cannot overwrite new file metadata.');
    verify(state()['notes'][7] === $changed, 'Stale admin update leaves the full note intact.');

    $adminPage = request('admin-note-edit.php?id=7', $admin);
    verify(str_contains($adminPage['body'], 'Mevcut Notu İndir') && str_contains($adminPage['body'], 'Son Dosya Değişikliğini Geri Al'), 'Admin edit has current download and undo controls.');
    verify(request('note-file-download.php?id=7', $admin)['body'] === $replacement, 'Admin downloads replaced file.');
    $undo = fileForm($adminPage['body'], 'undo_file');
    $badUndo = $undo;
    $badUndo['csrf_token'] = 'bad-token';
    verify(str_contains(request('admin-note-edit.php?id=7', $admin, $badUndo)['body'], 'Güvenlik doğrulaması'), 'Admin undo checks CSRF.');
    verify(str_contains($adminPage['body'], 'Türkiye saati') && str_contains($adminPage['body'], '+03:00'), 'Undo deadline has explicit Turkish timezone.');
    state(static function ($data) { $data['fail_update'] = true; return $data; });
    request('admin-note-edit.php?id=7', $admin, $undo);
    verify(state()['notes'][7] === $changed && count(readNoteFileHistory(7)) === 1, 'Failed undo leaves current file and undo right intact.');
    state(static function ($data) { unset($data['fail_update']); $data['notes'][7]['upload_status'] = 'rejected'; $data['notes'][7]['scan_status'] = 'infected'; $data['notes'][7]['deleted_at'] = '2026-10-03 12:00:00'; return $data; });
    verify(request('note-file-download.php?id=7', $admin)['body'] === $replacement, 'Admin can download archived/unpublished current note.');
    verify(request('view.php?id=7')['body'] !== $replacement, 'Public preview cannot expose archived/unpublished management downloads.');
    verify(request('admin-note-edit.php?id=7', $admin, $undo)['code'] === 303, 'Admin can undo an owner replacement.');
    $restored = state()['notes'][7];
    verify(noteFileSnapshot($restored) === noteFileSnapshot($note), 'Undo restores filename, bytes, size, MIME and hash.');
    verify($restored['upload_status'] === 'rejected' && $restored['scan_status'] === 'infected' && $restored['deleted_at'] !== null, 'Undo cannot bypass a later moderation/archive decision.');
    verify(count(storedFiles()) === 1 && !file_exists(noteFileHistoryPath(7)), 'Undo removes discarded replacement safely.');
    verify(str_contains(request('admin-note-edit.php?id=7', $admin, $undo)['body'], 'Dosya siz sayfayı açtıktan sonra değişmiş'), 'Repeated undo is rejected.');

    $adminReplace = formFor('admin-note-edit.php', $admin);
    verify(request('admin-note-edit.php?id=7', $admin, $adminReplace, $upload)['code'] === 303, 'Admin can replace archived files.');
    $secondReplace = formFor('note-edit.php', $owner);
    verify(request('note-edit.php?id=7', $owner, $secondReplace, $upload)['code'] === 303, 'Owner can make a second replacement.');
    $secondUndo = formFor('note-edit.php', $owner, 'undo_file');
    verify(request('note-edit.php?id=7', $owner, $secondUndo)['code'] === 303, 'Owner can undo latest replacement.');
    $firstUndo = formFor('note-edit.php', $owner, 'undo_file');
    verify(request('note-edit.php?id=7', $owner, $firstUndo)['code'] === 303, 'Owner can undo earlier admin replacement within its window.');
    verify(count(storedFiles()) === 1 && noteFileSnapshot(state()['notes'][7]) === noteFileSnapshot($note), 'Multiple replacements unwind without losing the original.');

    // Mevcut kullanıcı/admin bilgi düzenleme akışları çalışmaya devam eder.
    $metadata = metadataForm(request('admin-note-edit.php?id=7', $admin)['body']);
    $metadata['description'] = 'Admin açıklaması';
    verify(request('admin-note-edit.php?id=7', $admin, $metadata)['code'] === 303, 'Fresh admin metadata update still works.');
    verify(state()['notes'][7]['description'] === 'Admin açıklaması' && noteFileSnapshot(state()['notes'][7]) === noteFileSnapshot($note), 'Admin metadata update preserves current file.');
    $metadata = metadataForm(request('note-edit.php?id=7', $owner)['body']);
    $metadata['description'] = 'Kullanıcı açıklaması';
    verify(request('note-edit.php?id=7', $owner, $metadata)['code'] === 303, 'Owner metadata update still works.');
    verify(state()['notes'][7]['description'] === 'Kullanıcı açıklaması', 'Owner metadata persists.');

    $fields = formFor('note-edit.php', $owner);
    request('note-edit.php?id=7', $owner, $fields, $upload);
    $undo = formFor('note-edit.php', $owner, 'undo_file');
    file_put_contents($storage . '/original.pdf', 'tampered');
    verify(str_contains(request('note-edit.php?id=7', $owner, $undo)['body'], 'bütünlüğü doğrulanamadı'), 'Undo checks retained file integrity.');
    verify(request('note-file-download.php?id=7', $owner)['body'] === $replacement, 'Integrity error preserves current bytes.');
    file_put_contents($storage . '/original.pdf', $original);
    $history = readNoteFileHistory(7);
    $history[0]['expires_at'] = time() - 1;
    writeNoteFileHistory(7, $history);
    verify(str_contains(request('note-edit.php?id=7', $owner, $undo)['body'], 'artık geri alınamıyor'), 'Expired undo is rejected.');
    verify(!file_exists($storage . '/original.pdf') && count(storedFiles()) === 1, 'Expired copy is deleted and current file protected.');

    // PHP post_max_size aşılınca $_POST ve $_FILES boş gelir; GET id korunmalı.
    $oversize = $directory . '/oversize.pdf';
    $handle = fopen($oversize, 'wb');
    ftruncate($handle, 27 * 1024 * 1024);
    fclose($handle);
    $beforeOversize = state()['notes'][7];
    $response = request('note-edit.php?id=7', $owner, formFor('note-edit.php', $owner), ['path' => $oversize, 'name' => 'large.pdf']);
    verify($response['code'] === 200 && str_contains($response['body'], '25 MB sınırını aşıyor'), 'Oversize POST stays on the correct edit page with a useful error.');
    verify(state()['notes'][7] === $beforeOversize, 'Oversize POST keeps the current file.');

    // Office ZIP container validation through genuine HTTP multipart upload.
    $office = $directory . '/office.docx';
    $zip = new ZipArchive();
    $zip->open($office, ZipArchive::CREATE);
    $zip->addFromString('[Content_Types].xml', '<Types><Override ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('word/document.xml', '<document/>');
    $zip->close();
    verify(request('note-edit.php?id=7', $owner, formFor('note-edit.php', $owner), ['path' => $office, 'name' => 'yeni.docx'])['code'] === 303, 'DOCX upload is accepted.');
    verify(state()['notes'][7]['mime_type'] === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Office MIME is normalized correctly.');

    // Kalıcı silme tüm sürümleri temizlemeli, root dışındaki dosyaya dokunmamalı.
    $outside = $directory . '/outside.pdf';
    file_put_contents($outside, 'outside');
    symlink($outside, $storage . '/outside-link.pdf');
    verify(resolveNoteAbsolutePath(['storage_path' => 'outside-link.pdf']) === null, 'Symlink outside private storage cannot be downloaded.');
    verify(deleteNotePhysicalFile(['storage_path' => 'outside-link.pdf']) !== null && file_get_contents($outside) === 'outside', 'Unsafe deletion does not touch outside file.');
    unlink($storage . '/outside-link.pdf');
    verify(deleteNoteStorageFile(state()['notes'][7]) === null, 'Permanent deletion cleans current and retained files.');
    verify(storedFiles() === [] && !file_exists(noteFileHistoryPath(7)), 'Permanent deletion leaves no retained upload.');

    // Şema migration'ından gelen boş SHA-256 geri almayı engellememeli.
    file_put_contents($storage . '/original.pdf', $original);
    state(static function ($data) use ($note) { $data['notes'][7] = $note; $data['notes'][7]['sha256'] = ''; return $data; });
    verify(request('note-edit.php?id=7', $owner, formFor('note-edit.php', $owner), $upload)['code'] === 303, 'Legacy note without SHA-256 can be replaced.');
    verify(request('note-edit.php?id=7', $owner, formFor('note-edit.php', $owner, 'undo_file'))['code'] === 303, 'Legacy note replacement can be undone.');
    verify(state()['notes'][7]['sha256'] === hash('sha256', $original) && request('note-file-download.php?id=7', $owner)['body'] === $original, 'Legacy undo restores actual bytes with an integrity hash.');

    request('note-edit.php?id=7', $owner, formFor('note-edit.php', $owner), $upload);
    verify(request('admin.php', $admin, ['action' => 'delete_note', 'note_id' => '7', 'csrf_token' => 'fixture-admin-token'])['code'] === 302, 'Admin permanent note deletion completes.');
    verify(!isset(state()['notes'][7]) && storedFiles() === [] && !file_exists(noteFileHistoryPath(7)), 'Admin note deletion cleans all retained versions.');

    file_put_contents($storage . '/original.pdf', $original);
    state(static function ($data) use ($note) { $data['notes'][7] = $note; return $data; });
    request('note-edit.php?id=7', $owner, formFor('note-edit.php', $owner), $upload);
    verify(request('admin.php', $admin, ['action' => 'delete_user', 'user_id' => '1', 'csrf_token' => 'fixture-admin-token'])['code'] === 302, 'Admin permanent account deletion completes.');
    verify(!isset(state()['users'][1]) && storedFiles() === [] && !file_exists(noteFileHistoryPath(7)), 'Admin account deletion cleans all retained versions.');

    file_put_contents($storage . '/original.pdf', $original);
    state(static function ($data) use ($note, $users) { $data['notes'][7] = $note; $data['users'][1] = $users[1]; return $data; });
    request('note-edit.php?id=7', $owner, formFor('note-edit.php', $owner), $upload);
    verify(request('profile_edit.php', $owner, ['action' => 'delete_account', 'csrf_token' => 'fixture-profile-token',
        'delete_current_password' => 'fixture-password', 'delete_confirmation' => 'HESABIMI SİL'])['code'] === 302, 'Owner permanent account deletion completes.');
    verify(!isset(state()['users'][1]) && storedFiles() === [] && !file_exists(noteFileHistoryPath(7)), 'Owner account deletion cleans all retained versions.');
    $log = (string)file_get_contents($directory . '/server.log');
    $log = preg_replace('/^.*PHP Warning:\s+PHP Request Startup: POST Content-Length of \d+ bytes exceeds the limit of \d+ bytes in Unknown on line 0\r?$/m', '', $log) ?? $log;
    verify(!preg_match('/PHP (Fatal error|Warning|Deprecated|Parse error):/', $log), 'Endpoints run without PHP runtime warnings.');
    echo "PASS: {$checks} checks (isolated multipart HTTP, owner/admin, undo, expiry, failures, download and deletion).\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    if (file_exists($directory . '/server.log')) {
        fwrite(STDERR, (string)file_get_contents($directory . '/server.log'));
    }
    $failed = true;
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    removeTestDirectory($directory);
}
exit($failed ? 1 : 0);
