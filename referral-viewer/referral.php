<?php declare(strict_types=1);

date_default_timezone_set('Asia/Kuala_Lumpur');
ini_set('display_errors', '0');
error_reporting(0);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function abort(int $code, string $message): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    $label = match ($code) {
        400 => 'Bad Request',
        403 => 'Forbidden',
        404 => 'Not Found',
        500 => 'Internal Server Error',
        default => 'Error',
    };
    echo $code . ' ' . $label . "\n\n" . $message;
    exit;
}

function load_env(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Cannot read .env file: ' . $path);
    }
    $result = parse_ini_file(
        filename: $path,
        process_sections: false,
        scanner_mode: INI_SCANNER_RAW
    );
    if ($result === false) {
        throw new RuntimeException('Failed to parse .env file: ' . $path);
    }
    return $result;
}

function env_require(array $config, string $key): string
{
    if (!isset($config[$key]) || (string) $config[$key] === '') {
        abort(500, 'Missing required config key: ' . $key);
    }
    return (string) $config[$key];
}

function decode_referral_id(string $encoded): int
{
    if ($encoded === '') {
        abort(400, 'Missing referral ID.');
    }
    $decoded = base64_decode(string: $encoded, strict: true);
    if ($decoded === false) {
        abort(400, 'Invalid referral ID encoding.');
    }
    if (!ctype_digit($decoded) || (int) $decoded <= 0) {
        abort(400, 'Referral ID must be a positive integer.');
    }
    return (int) $decoded;
}

/**
 * Execute a cURL request.
 *
 * @return array{0: int, 1: string} [httpStatus, responseBody]
 */
function curl_request(
    string $method,
    string $url,
    array $headers,
    ?string $body = null
): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $responseBody = curl_exec($ch);
    $httpStatus   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError    = curl_error($ch);
    curl_close($ch);

    if ($responseBody === false) {
        abort(500, 'Network error communicating with API: ' . $curlError);
    }

    return [$httpStatus, $responseBody];
}

// ---------------------------------------------------------------------------
// JWT token cache
// ---------------------------------------------------------------------------

function cache_read_token(string $cacheFile, int $bufferSeconds): ?string
{
    if (!is_file($cacheFile) || !is_readable($cacheFile)) {
        return null;
    }
    $raw = file_get_contents($cacheFile);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, associative: true);
    if (!is_array($data)
        || !isset($data['token'], $data['expires_at'])
        || !is_string($data['token'])
        || !is_int($data['expires_at'])) {
        return null;
    }
    if (time() + $bufferSeconds >= $data['expires_at']) {
        return null;
    }
    return $data['token'];
}

function cache_write_token(string $cacheFile, string $token, int $expiresAt): void
{
    $json = json_encode(
        ['token' => $token, 'expires_at' => $expiresAt],
        JSON_THROW_ON_ERROR
    );
    $tmp = $cacheFile . '.tmp.' . getmypid();
    if (file_put_contents($tmp, $json, LOCK_EX) !== false) {
        rename($tmp, $cacheFile);
    }
}

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------

function fetch_jwt(array $config): string
{
    $apiHost = env_require($config, 'API_HOST');
    $url     = rtrim($apiHost, '/') . '/api/auth/token';

    $body = json_encode([
        'api_key' => env_require($config, 'API_KEY'),
    ], JSON_THROW_ON_ERROR);

    [$status, $responseBody] = curl_request(
        method: 'POST',
        url: $url,
        headers: [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        body: $body
    );

    if ($status !== 200) {
        abort(500, 'Authentication failed. API returned HTTP ' . $status);
    }

    $data = json_decode($responseBody, associative: true);
    if (!is_array($data) || !isset($data['token']) || !is_string($data['token'])) {
        abort(500, 'Authentication failed. Unexpected API response.');
    }

    return $data['token'];
}

function get_jwt(array $config): string
{
    $cacheFile     = $config['TOKEN_CACHE_FILE'] ?? sys_get_temp_dir() . '/.referral_jwt_cache';
    $bufferSeconds = (int) ($config['TOKEN_CACHE_TTL_BUFFER'] ?? 300);

    $cached = cache_read_token($cacheFile, $bufferSeconds);
    if ($cached !== null) {
        return $cached;
    }

    $token = fetch_jwt($config);

    // Parse exp from JWT payload segment (no signature verification needed for caching)
    $expiresAt = 0;
    $parts = explode('.', $token);
    if (count($parts) === 3) {
        $padded  = str_replace(['-', '_'], ['+', '/'], $parts[1]);
        $payload = json_decode(base64_decode($padded), associative: true);
        $expiresAt = (int) ($payload['exp'] ?? 0);
    }
    if ($expiresAt === 0) {
        $expiresAt = time() + 86400;
    }

    cache_write_token($cacheFile, $token, $expiresAt);
    return $token;
}

// ---------------------------------------------------------------------------
// Referral API
// ---------------------------------------------------------------------------

function fetch_referral(int $id, string $token, string $apiHost): array
{
    $url = rtrim($apiHost, '/') . '/api/referral/successful/' . $id;

    [$status, $body] = curl_request(
        method: 'GET',
        url: $url,
        headers: [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ]
    );

    match (true) {
        $status === 200 => null,
        $status === 404 => abort(404, 'Referral not found.'),
        $status === 401 => abort(500, 'API authentication rejected the token.'),
        $status === 403 => abort(403, 'Access to this referral is not permitted.'),
        default         => abort(500, 'Referral API returned HTTP ' . $status),
    };

    $data = json_decode($body, associative: true);
    if (!is_array($data)) {
        abort(500, 'Invalid JSON response from referral API.');
    }

    return $data;
}

// ---------------------------------------------------------------------------
// PDF
// ---------------------------------------------------------------------------

function decode_pdf(array $responseData): string
{
    $b64 = $responseData['pdfBase64'] ?? null;
    if (!is_string($b64) || $b64 === '') {
        abort(500, 'API response did not include a PDF.');
    }
    $bytes = base64_decode(string: $b64, strict: true);
    if ($bytes === false || $bytes === '') {
        abort(500, 'Failed to decode PDF data from API response.');
    }
    if (!str_starts_with($bytes, '%PDF-')) {
        abort(500, 'Decoded data does not appear to be a valid PDF.');
    }
    return $bytes;
}

function serve_pdf(string $pdfBytes): never
{
    $length = strlen($pdfBytes);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="referral.pdf"');
    header('Content-Length: ' . $length);
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $pdfBytes;
    exit;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

try {
    $config = load_env(__DIR__ . '/.env');
} catch (RuntimeException $e) {
    abort(500, $e->getMessage());
}

$encoded = trim($_GET['id'] ?? '');
if ($encoded === '') {
    abort(400, 'Missing required parameter: id');
}

$referralId = decode_referral_id($encoded);
$apiHost    = env_require($config, 'API_HOST');
$token      = get_jwt($config);
$data       = fetch_referral($referralId, $token, $apiHost);
$pdfBytes   = decode_pdf($data);
serve_pdf($pdfBytes);
