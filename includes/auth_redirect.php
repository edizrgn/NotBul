<?php
declare(strict_types=1);

/** Keep post-login destinations inside the site's ordinary user pages. */
function authSafeReturnTo(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);
    if ($value === '' || strlen($value) > 4096 || str_starts_with($value, '//')) {
        return '';
    }

    if (preg_match('/[\x00-\x20\x7f\\\\]/', $value) === 1) {
        return '';
    }

    // Browsers can interpret backslashes as slashes. Also reject encoded controls.
    $decoded = $value;
    for ($pass = 0; $pass < 3; $pass++) {
        if (preg_match('/[\x00-\x1f\x7f\\\\]/', $decoded) === 1) {
            return '';
        }
        $next = rawurldecode($decoded);
        if ($next === $decoded) {
            break;
        }
        $decoded = $next;
    }
    if (preg_match('/[\x00-\x1f\x7f\\\\]/', $decoded) === 1) {
        return '';
    }

    $parts = parse_url($value);
    if (!is_array($parts) || array_diff(array_keys($parts), ['path', 'query', 'fragment']) !== []) {
        return '';
    }

    $path = ltrim((string)($parts['path'] ?? ''), '/');
    $allowedPages = ['index.php', 'search.php', 'upload.php', 'note-detail.php', 'note-edit.php', 'note-file-download.php', 'profile.php', 'profile_edit.php', 'comment-edit.php'];
    if (!in_array($path, $allowedPages, true)) {
        return '';
    }

    return $path
        . (isset($parts['query']) ? '?' . $parts['query'] : '')
        . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

function authPageUrl(string $page, string $returnTo = '', array $parameters = []): string
{
    $returnTo = authSafeReturnTo($returnTo);
    if ($returnTo !== '') {
        $parameters['return_to'] = $returnTo;
    }

    return $page . ($parameters !== [] ? '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986) : '');
}

function authLoginUrl(string $returnTo = ''): string
{
    return authPageUrl('login.php', $returnTo);
}

function authReturnToFromRequest(): string
{
    @session_start();

    if (array_key_exists('return_to', $_POST) || array_key_exists('return_to', $_GET)) {
        $returnTo = authSafeReturnTo($_POST['return_to'] ?? $_GET['return_to'] ?? '');
        if ($returnTo === '') {
            unset($_SESSION['auth_return_to'], $_SESSION['auth_return_to_at']);
            return '';
        }
        $_SESSION['auth_return_to'] = $returnTo;
        $_SESSION['auth_return_to_at'] = time();
        return $returnTo;
    }

    if (time() - (int)($_SESSION['auth_return_to_at'] ?? 0) > 7200) {
        unset($_SESSION['auth_return_to'], $_SESSION['auth_return_to_at']);
        return '';
    }

    return authSafeReturnTo($_SESSION['auth_return_to'] ?? '');
}
