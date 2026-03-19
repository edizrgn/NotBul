<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'NotShare';
$pageKey = $pageKey ?? 'home';
$navItems = [
    ['key' => 'search', 'label' => 'Not Ara', 'href' => 'search.php'],
    ['key' => 'upload', 'label' => 'Not Yukle', 'href' => 'upload.php'],
];
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body data-page="<?= htmlspecialchars($pageKey, ENT_QUOTES, 'UTF-8'); ?>">
<header class="site-header">
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-light py-2">
            <a class="navbar-brand brand-mark" href="index.php">
                <span class="brand-icon" aria-hidden="true"></span>
                <span>Ogrenci Not Paylasim</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Navigasyonu Ac">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 me-lg-2">
                    <?php foreach ($navItems as $item): ?>
                        <li class="nav-item">
                            <a class="nav-link top-link <?= $pageKey === $item['key'] ? 'active' : ''; ?>" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex gap-2 auth-actions">
                    <a class="btn btn-sm btn-outline-primary" href="#">Giris Yap</a>
                    <a class="btn btn-sm btn-primary" href="#">Kayit Ol</a>
                </div>
            </div>
        </nav>
    </div>
</header>
