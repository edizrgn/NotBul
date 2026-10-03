<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/note_search.php';
require_once __DIR__ . '/fixtures/note_search_database.php';

$checks = 0;
function searchCheck(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
}
function searchCase(array $parameters = []): array
{
    global $pdo;
    return noteSearchPage($pdo, noteSearchRequest($parameters));
}

try {
    seedNoteSearchTables($pdo);
    $first = searchCase();
    searchCheck($first['total'] === 40 && count($first['rows']) === 10 && $first['pages'] === 4, 'SQL count and bounded page');
    searchCheck(array_column($first['rows'], 'id') === range(40, 31), 'Stable newest/id order');
    $second = searchCase(['page' => '2']);
    searchCheck(array_column($second['rows'], 'id') === range(30, 21), 'Correct second page without duplicates');
    searchCheck(searchCase(['page' => PHP_INT_MAX])['request']['page'] === 4, 'Huge page is clamped before SQL offset');
    searchCheck(searchCase(['page' => '-1'])['request']['page'] === 1, 'Negative page normalized');
    searchCheck(searchCase(['q' => 'isik sisli'])['rows'][0]['id'] === 1, 'Turkish ASCII and words across fields');
    searchCheck(searchCase(['q' => 'IŞIK ÇÖZÜM'])['total'] === 1, 'Turkish uppercase search');
    searchCheck(searchCase(['q' => '%'])['total'] === 1 && searchCase(['q' => '_'])['total'] === 1, 'LIKE wildcards treated literally');
    searchCheck(searchCase(['q' => '=x'])['total'] === 2, 'LIKE escape character treated literally');
    searchCheck(searchCase(['q' => "' OR 1=1 --"])['total'] === 0, 'Prepared search input cannot inject SQL');
    searchCheck(searchCase(['q' => 'olmayan'])['total'] === 0, 'Empty result');
    searchCheck(searchCase(['q' => '0'])['request']['q'] === '0' && searchCase(['q' => '0'])['total'] < 40, 'Numeric zero remains a search term');
    searchCheck(searchCase(['sort' => 'downloads'])['rows'][0]['id'] === 1, 'Downloads sort');
    searchCheck(searchCase(['sort' => 'rating'])['rows'][0]['id'] === 3, 'Rating average/count sort');
    searchCheck(searchCase(['q' => 'final'])['rows'][0]['id'] === 8, 'Weighted relevance');
    $one = searchCase(['q' => 'sisli'])['rows'][0];
    searchCheck((float)$one['rating_average'] === 4.0 && $one['rating_count'] === 2, 'Latest rating per user');
    foreach (['pdf', 'docx', 'pptx', 'image'] as $type) {
        searchCheck(searchCase(['file_type' => $type])['total'] === 10, 'File type ' . $type);
    }
    searchCheck(searchCase(['file_type' => 'bad'])['total'] === 0, 'Invalid file type does not broaden results');
    $lookups = noteSearchLookups();
    $universityId = (string)array_key_first($lookups['university_id']);
    $universityName = $lookups['university_id'][$universityId];
    $university = searchCase(['university_id' => $universityId]);
    searchCheck($university['total'] === 20, 'University filter');
    searchCheck(searchCase(['q' => $universityName])['total'] === 20, 'University name search from lookup');
    searchCheck(searchCase(['course' => 'IŞIK'])['total'] === 2, 'Course filter');
    searchCheck(searchCase(['class_id' => '1', 'topic' => 'Final'])['total'] === 10, 'Combined class/topic filters');
    $similar = searchCase(['similar_to' => '1']);
    searchCheck(!in_array(1, array_column($similar['rows'], 'id')), 'Similar results exclude source');
    searchCheck(in_array(4, array_column($similar['rows'], 'id')) && in_array(5, array_column($similar['rows'], 'id')), 'Similar tags/title/course');
    searchCheck(searchCase(['similar_to' => '99999'])['request']['similar_to'] === 0, 'Missing similar source resets mode');
    searchCheck(searchCase(['similar_to' => '41'])['request']['similar_to'] === 0, 'Private source cannot expose similar information');
    $options = noteSearchOptions($pdo, noteSearchRequest([])['filters']);
    searchCheck(count($options['university_id']) === 2 && !isset($options['university_id']['private-university']), 'Facets exclude private notes');
    $scopedOptions = noteSearchOptions($pdo, noteSearchRequest(['university_id' => $universityId])['filters']);
    searchCheck(count($scopedOptions['department_id']) === 1, 'Dependent department options');
    $cards = noteSearchFeatured($pdo);
    searchCheck(count($cards['popular']) === 6 && count($cards['latest']) === 6, 'Homepage data bounded at six per section');
    searchCheck($cards['popular'][0]['id'] === 1 && $cards['latest'][0]['id'] === 40, 'Featured ordering');
    $html = noteSearchResults(searchCase(['q' => 'alert']));
    searchCheck(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Server HTML escapes note title');
    $pagination = noteSearchPagination(searchCase(['q' => 'Final', 'university_id' => $universityId, 'sort' => 'downloads']));
    searchCheck(str_contains($pagination, 'q=Final') && str_contains($pagination, 'sort=downloads') && str_contains($pagination, 'university_id='), 'Pagination preserves filters/query/sort');
    $bad = false;
    try { noteSearchRequest(['q' => str_repeat('a', 201)]); } catch (InvalidArgumentException $error) { $bad = true; }
    searchCheck($bad, 'Expensive unbounded query rejected');
    echo "PASS: {$checks} real MariaDB search checks (temporary tables, no persistent writes).\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
