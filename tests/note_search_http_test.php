<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$directory = sys_get_temp_dir() . '/notbul-search-http-' . bin2hex(random_bytes(6));
$web = $directory . '/web';
mkdir($web . '/includes', 0750, true);
mkdir($web . '/assets/data', 0750, true);
$server = null;
$failed = false;
$checks = 0;

function httpSearchCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function searchHttp(string $path): array
{
    global $base;
    $curl = curl_init($base . '/' . $path);
    $headers = [];
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
            if (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $headers[strtolower($key)] = trim($value); }
            return strlen($line);
        }]);
    $body = curl_exec($curl);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return ['code' => $code, 'body' => $body, 'headers' => $headers];
}

try {
    $root = dirname(__DIR__);
    foreach (['index.php', 'search.php', 'includes/note_search.php', 'includes/ratings.php', 'includes/header.php',
        'includes/footer.php', 'includes/env.php', 'includes/auth_redirect.php', 'assets/data/universiteler.json', 'assets/data/bolumler.json'] as $file) {
        copy($root . '/' . $file, $web . '/' . $file);
    }
    file_put_contents($web . '/includes/db.php', '<?php if (isset($_GET["test_database_unavailable"])) throw new RuntimeException("Injected DB outage"); require ' . var_export($root . '/includes/db.php', true) . '; require ' . var_export(__DIR__ . '/fixtures/note_search_database.php', true) . '; seedNoteSearchTables($pdo);');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
    if ($socket === false) throw new RuntimeException('Could not reserve test port.');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://' . $address;
    $server = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $directory, '-S', $address, '-t', $web],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', $directory . '/server.log', 'a'], 2 => ['file', $directory . '/server.log', 'a']], $pipes, $web);
    for ($i = 0; $i < 50; $i++) {
        $connection = @stream_socket_client('tcp://' . $address, $code, $message, 0.1);
        if ($connection) { fclose($connection); break; }
        usleep(20000);
    }
    $page = searchHttp('search.php');
    httpSearchCheck($page['code'] === 200 && substr_count($page['body'], '<article class="result-item">') === 10, 'Initial server render has ten results');
    httpSearchCheck(str_contains($page['body'], 'name="q" form="searchFilterForm"') && str_contains($page['body'], 'name="sort" form="searchFilterForm"'), 'Query and sort belong to native GET form');
    httpSearchCheck(str_contains($page['body'], 'data-page="2" href="search.php?page=2"'), 'Pagination has usable native links');
    httpSearchCheck(!str_contains($page['body'], 'window.NOTBUL_NOTES') && !str_contains($page['body'], '<noscript>'), 'No all-note JSON or JavaScript requirement');
    $json = searchHttp('search.php?format=json&page=2');
    $data = json_decode($json['body'], true, 512, JSON_THROW_ON_ERROR);
    httpSearchCheck($json['code'] === 200 && $data['page'] === 2 && $data['total'] === 40, 'JSON server pagination');
    httpSearchCheck(substr_count($data['resultsHtml'], '<article class="result-item">') === 10 && $data['options'] === [], 'Page response bounded and omits unnecessary facets');
    httpSearchCheck(!str_contains($json['body'], 'storage_path') && !str_contains($json['body'], 'ÖZEL İÇERİK'), 'Response excludes private file data and unpublished notes');
    $query = searchHttp('search.php?format=json&q=isik%20sisli&include_options=1');
    $data = json_decode($query['body'], true, 512, JSON_THROW_ON_ERROR);
    httpSearchCheck($data['total'] === 1 && str_contains($data['resultsHtml'], 'IŞIK'), 'HTTP Turkish search');
    httpSearchCheck(count($data['options']) === 6 && str_contains($data['queryString'], 'q=isik%20sisli'), 'Facets and canonical URL returned');
    $query = searchHttp('search.php?q=isik%20sisli');
    httpSearchCheck(substr_count($query['body'], '<article class="result-item">') === 1 && str_contains($query['body'], 'value="isik sisli"'), 'Native GET search preserves input');
    $options = json_decode(searchHttp('search.php?format=options')['body'], true, 512, JSON_THROW_ON_ERROR);
    httpSearchCheck($options['resultsHtml'] === '' && count($options['options']) === 6, 'Options endpoint does not fetch result pages');
    $limit = json_decode(searchHttp('search.php?format=json&limit=100000&per_page=100000&page=99999')['body'], true, 512, JSON_THROW_ON_ERROR);
    httpSearchCheck($limit['page'] === 4 && substr_count($limit['resultsHtml'], '<article class="result-item">') === 10, 'Client cannot bypass page size or request huge offset');
    $home = searchHttp('index.php');
    httpSearchCheck($home['code'] === 200 && substr_count($home['body'], '<article class="col-sm-6 col-xl-4">') === 12, 'Homepage renders two bounded sections');
    httpSearchCheck(!str_contains($home['body'], 'window.NOTBUL_NOTES') && str_contains($home['body'], 'method="GET" action="search.php"'), 'Homepage native search without all-note payload');
    $home = json_decode(searchHttp('search.php?format=home&q=ders')['body'], true, 512, JSON_THROW_ON_ERROR);
    httpSearchCheck($home['searchActive'] && substr_count($home['resultsHtml'], '<article class="col-sm-6 col-xl-4">') === 6, 'Home live search returns six cards');
    $home = json_decode(searchHttp('search.php?format=home')['body'], true, 512, JSON_THROW_ON_ERROR);
    httpSearchCheck(!$home['searchActive'] && substr_count($home['latestHtml'], '<article class="col-sm-6 col-xl-4">') === 6, 'Clearing homepage restores latest section');
    $xss = json_decode(searchHttp('search.php?format=json&q=alert')['body'], true, 512, JSON_THROW_ON_ERROR);
    httpSearchCheck(str_contains($xss['resultsHtml'], '&lt;script&gt;') && !str_contains($xss['resultsHtml'], '<script>'), 'HTML fragments escape stored content');
    $bad = searchHttp('search.php?format=json&q=' . str_repeat('a', 201));
    httpSearchCheck($bad['code'] === 400 && isset(json_decode($bad['body'], true)['error']), 'Invalid input returns JSON 400');
    $outage = searchHttp('search.php?format=json&test_database_unavailable=1');
    httpSearchCheck($outage['code'] === 503 && !str_contains($outage['body'], 'Injected'), 'DB failure returns safe JSON 503');
    $outage = searchHttp('search.php?test_database_unavailable=1');
    httpSearchCheck($outage['code'] === 200 && str_contains($outage['body'], 'Notlar şu anda getirilemiyor'), 'HTML DB outage shows useful message');
    $log = (string)file_get_contents($directory . '/server.log');
    httpSearchCheck(!preg_match('/PHP (Warning|Fatal error|Deprecated|Parse error):/', $log), 'No PHP runtime warnings');
    echo "PASS: {$checks} HTTP checks (real MariaDB temporary tables, server HTML/JSON and native forms).\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    if (file_exists($directory . '/server.log')) fwrite(STDERR, (string)file_get_contents($directory . '/server.log'));
    $failed = true;
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($directory);
}
exit($failed ? 1 : 0);
