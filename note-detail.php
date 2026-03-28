<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
@session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT n.*, u.first_name, u.last_name 
    FROM notes n
    JOIN users u ON n.user_id = u.id
    WHERE n.id = :id
");
$stmt->execute(['id' => $id]);
$note = $stmt->fetch();

if (!$note) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Not Bul | ' . htmlspecialchars($note['title']);
$pageKey = 'detail';
require __DIR__ . '/includes/header.php';
?>
<main class="page-shell">
    <section class="container section-block">
        <p class="text-secondary small mb-3">
            Anasayfa > <?= htmlspecialchars($note['course']) ?> > <?= htmlspecialchars($note['title']) ?>
        </p>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="preview-shell">
                    <div class="preview-toolbar d-flex justify-content-between align-items-center">
                        <strong>Dosya Önizleme</strong>
                        <span class="badge text-bg-info"><?= strtoupper(pathinfo($note['original_filename'], PATHINFO_EXTENSION)) ?> Önizleme</span>
                    </div>
                    <div class="preview-canvas p-0" style="min-height: 500px; background: #f8f9fa;">
                        <?php if (str_contains($note['mime_type'], 'pdf')): ?>
                            <iframe src="view.php?id=<?= $note['id'] ?>#toolbar=0" width="100%" height="600px" style="border: none;"></iframe>
                        <?php elseif (str_starts_with($note['mime_type'], 'image/')): ?>
                            <img src="view.php?id=<?= $note['id'] ?>" class="img-fluid" alt="<?= htmlspecialchars($note['title']) ?>">
                        <?php else: ?>
                            <div class="p-4 text-center">
                                <p class="mb-2 fw-semibold">Bu dosya formatı (<?= htmlspecialchars($note['mime_type']) ?>) için tarayıcıda doğrudan önizleme desteklenmiyor.</p>
                                <p class="mb-0 text-secondary">Aşağıdaki butonu kullanarak dosyayı indirebilirsiniz.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <article class="panel-card h-100">
                    <h1 class="section-title mb-2"><?= htmlspecialchars($note['title']) ?></h1>
                    <p class="text-secondary"><?= nl2br(htmlspecialchars($note['description'] ?? 'Açıklama belirtilmedi.')) ?></p>

                    <div class="note-meta-grid">
                        <div><span>Yükleyen</span><strong><?= htmlspecialchars($note['first_name'] . ' ' . $note['last_name']) ?></strong></div>
                        <div><span>Ders</span><strong><?= htmlspecialchars($note['course']) ?></strong></div>
                        <div><span>Bölüm</span><strong><?= htmlspecialchars($note['department_id'] ?: '-') ?></strong></div>
                        <div><span>Sınıf</span><strong><?= htmlspecialchars($note['class_id'] ?: '-') ?></strong></div>
                        <div><span>Tarih</span><strong><?= date('d.m.Y', strtotime($note['created_at'])) ?></strong></div>
                        <div><span>Boyut</span><strong><?= round($note['file_size'] / 1024, 2) ?> KB</strong></div>
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <?php 
                        $tags = explode(',', $note['tags']);
                        foreach ($tags as $tag): 
                            if ($tag = trim($tag)):
                        ?>
                            <span class="badge rounded-pill text-bg-light">#<?= htmlspecialchars($tag) ?></span>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>

                    <div class="mt-4 d-grid gap-2 d-md-flex">
                        <a class="btn btn-primary btn-lg px-4" href="view.php?id=<?= $note['id'] ?>" download="<?= htmlspecialchars($note['original_filename']) ?>">İndir</a>
                        <a class="btn btn-outline-primary btn-lg" href="search.php">Benzer Notlar</a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- Statik yorumlar korunuyor -->
    <section class="container section-block pb-5">
        <div class="panel-card">
            <h2 class="section-title mb-3">Yorumlar</h2>
            <div id="commentsList" class="comment-list">
                <article class="comment-item">
                    <header>
                        <strong>Zeynep</strong> <span class="text-secondary">| 5/5</span>
                    </header>
                    <p class="mb-0">Özellikle final öncesi çok faydalı oldu. Teşekkürler.</p>
                </article>
            </div>
            <!-- ... geri kalan statik yorumlar ve form ... -->
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>

