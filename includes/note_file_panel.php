<?php
declare(strict_types=1);

require_once __DIR__ . '/note_files.php';

function renderNoteFilePanel(array $note, string $csrfToken, string $actionUrl, ?array $undo, string $historyError = ''): void
{
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $noteId = (int)$note['id'];
    $identity = noteFileIdentity($note);
    $deadline = $undo === null ? null : (new DateTimeImmutable('@' . $undo['expires_at']))->setTimezone(new DateTimeZone('Europe/Istanbul'));
    ?>
    <section class="panel-card mb-4" aria-labelledby="noteFileHeading">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h2 class="h4 mb-1" id="noteFileHeading">Not Dosyası</h2>
                <p class="mb-0 text-secondary text-break"><?= $escape((string)$note['original_filename']) ?>
                    <span class="small">(<?= number_format((int)$note['file_size'] / 1024 / 1024, 2, ',', '.') ?> MB)</span>
                </p>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="note-file-download.php?id=<?= $noteId ?>"><i class="fa-solid fa-download me-1" aria-hidden="true"></i>Mevcut Notu İndir</a>
        </div>
        <?php if ($historyError !== ''): ?>
            <div class="alert alert-warning" role="alert"><?= $escape($historyError) ?></div>
        <?php endif; ?>
        <?php if ($undo !== null): ?>
            <div class="alert alert-info">
                <p class="mb-2 text-break">Önceki dosya: <strong><?= $escape((string)$undo['previous']['original_filename']) ?></strong><br>
                    <span class="small">Bu değişikliği <time datetime="<?= $deadline->format('c') ?>"><?= $deadline->format('d.m.Y H:i') ?></time> tarihine kadar geri alabilirsiniz (Türkiye saati).</span>
                </p>
                <form method="POST" action="<?= $escape($actionUrl) ?>" data-note-file-form data-pending-label="Geri alınıyor…">
                    <input type="hidden" name="id" value="<?= $noteId ?>">
                    <input type="hidden" name="action" value="undo_file">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="file_identity" value="<?= $identity ?>">
                    <input type="hidden" name="undo_token" value="<?= $escape($undo['token']) ?>">
                    <button class="btn btn-sm btn-outline-primary" type="submit">Son Dosya Değişikliğini Geri Al</button>
                    <span class="small ms-2" data-file-form-status role="status" aria-live="polite"></span>
                </form>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" action="<?= $escape($actionUrl) ?>" data-note-file-form data-pending-label="Dosya yükleniyor…" data-max-bytes="<?= getMaxUploadBytes() ?>">
            <input type="hidden" name="id" value="<?= $noteId ?>">
            <input type="hidden" name="action" value="replace_file">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="file_identity" value="<?= $identity ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= getMaxUploadBytes() ?>">
            <label class="form-label" for="replacementNoteFile">Yeni dosya</label>
            <input class="form-control" id="replacementNoteFile" type="file" name="note_file" accept=".pdf,.docx,.pptx,.png,.jpg,.jpeg,.webp" aria-describedby="replacementFileHelp" required>
            <p class="small text-secondary mt-2" id="replacementFileHelp">PDF, DOCX, PPTX, PNG, JPG veya WEBP; en fazla <?= getMaxUploadMb() ?> MB. Eski dosya 24 saat boyunca geri alınabilir. Dosya değişimi notun bilgilerini, yorumlarını ve indirme sayısını korur.</p>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">Dosyayı Değiştir</button>
                <span class="small" data-file-form-status role="status" aria-live="polite"></span>
            </div>
        </form>
    </section>
    <?php
}
