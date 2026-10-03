<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/note_search.php';
require_once __DIR__ . '/fixtures/note_search_database.php';

seedNoteSearchTables($pdo, 10000);
$pdo->exec("INSERT INTO note_comments (note_id, user_id, rating, comment)
    SELECT id, 1, 3, 'Benchmark' FROM notes WHERE deleted_at IS NULL AND upload_status = 'ready' AND scan_status = 'clean'");
$pdo->exec("INSERT INTO note_comments (note_id, user_id, rating, comment)
    SELECT id, 2, 5, 'Benchmark' FROM notes WHERE deleted_at IS NULL AND upload_status = 'ready' AND scan_status = 'clean'");
$pdo->query('ANALYZE TABLE notes, note_comments')->fetchAll();
$cases = [
    'newest' => [], 'downloads' => ['sort' => 'downloads'], 'text' => ['q' => 'ders notu'],
    'no_match' => ['q' => 'bulunmayan'], 'course' => ['course' => 'Matematik'],
    'rating' => ['sort' => 'rating'], 'last_page' => ['page' => '1000'], 'similar' => ['similar_to' => '1'],
];
$timings = [];
foreach ($cases as $label => $parameters) {
    $samples = [];
    for ($i = 0; $i < 7; $i++) {
        $start = hrtime(true);
        $result = noteSearchPage($pdo, noteSearchRequest($parameters));
        $samples[] = (hrtime(true) - $start) / 1000000;
    }
    sort($samples);
    $timings[$label] = ['median_ms' => round($samples[3], 2), 'max_ms' => round(max($samples), 2), 'rows_returned' => count($result['rows'])];
}
$start = hrtime(true);
$featured = noteSearchFeatured($pdo);
$timings['home'] = ['ms' => round((hrtime(true) - $start) / 1000000, 2), 'rows_returned' => count($featured['popular']) + count($featured['latest'])];
$start = hrtime(true);
noteSearchOptions($pdo, noteSearchRequest([])['filters']);
$timings['options'] = ['ms' => round((hrtime(true) - $start) / 1000000, 2)];
$plans = [];
foreach (['latest' => 'n.created_at DESC, n.id DESC', 'downloads' => 'n.download_count DESC, n.created_at DESC, n.id DESC'] as $label => $order) {
    $plans[$label] = $pdo->query('EXPLAIN SELECT ' . noteSearchNoteColumns() . ' FROM notes n WHERE '
        . noteSearchVisibility() . ' ORDER BY ' . $order . ' LIMIT 10')->fetchAll();
}
$plans['count'] = $pdo->query('EXPLAIN SELECT COUNT(*) FROM notes n WHERE ' . noteSearchVisibility())->fetchAll();
$plans['page_ratings'] = $pdo->query('EXPLAIN ' . noteRatingSummarySql(range(10000, 9991)))->fetchAll();
echo json_encode(['public_notes' => 10000, 'comments' => 20006, 'timings' => $timings,
    'php_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2), 'explain' => $plans], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
