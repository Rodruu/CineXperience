<?php
/**
 * CINE XPERIENCE - CONFIGURACIÓN Y FUNCIONES COMPARTIDAS v2.7 (EDICIÓN RENDER)
 * ADAPTADO PARA POSTGRESQL Y VARIABLES DE ENTORNO
 */

// ===========================
// CONFIGURACIÓN DEL SISTEMA (RENDER / VARIABLES DE ENTORNO)
// ===========================
// Leemos la URL de conexión que Render nos da automáticamente
$dbUrl = getenv('DATABASE_URL');

if ($dbUrl) {
    // Procesamos la URL de Postgres (postgres://user:pass@host:port/dbname)
    $dbParams = parse_url($dbUrl);
    define('DB_HOST', $dbParams['host']);
    define('DB_PORT', $dbParams['port'] ?? 5432);
    define('DB_USER', $dbParams['user']);
    define('DB_PASS', $dbParams['pass']);
    define('DB_NAME', ltrim($dbParams['path'], '/'));
} else {
    // Valores por defecto (locales) si no hay variable de entorno
    define('DB_HOST', 'localhost');
    define('DB_USER', 'postgres');
    define('DB_PASS', '');
    define('DB_NAME', 'cinexperience');
}

// ===========================
// CONFIGURACIÓN DE SEGURIDAD AVANZADA
// ===========================
define('SECRET_KEY', 'admin');
define('SESSION_LIFETIME', 3600);
define('TOKEN_LIFETIME', 1800);
define('MAX_REQUESTS_PER_MINUTE', 60);
define('ALLOWED_REFERRERS', []);
define('BLOCK_DEV_TOOLS', true);
define('ENCRYPT_IFRAME_URLS', true);
define('IFRAME_LOAD_TIMEOUT', 8000);
define('ANTI_REDIRECT_WINDOW', 2500);

// ===========================
// PROTECCIÓN DE DOMINIO
// ===========================
// IMPORTANTE: Cambia esto a tu URL de .onrender.com cuando la tengas
define('DOMINIO_PERMITIDO', getenv('RENDER_EXTERNAL_HOSTNAME') ?: 'localhost');
define('DOMINIOS_PERMITIDOS_ADICIONALES', []);
define('ACCION_DOMINIO_NO_AUTORIZADO', 'bloquear');
define('URL_REDIRECCION_DOMINIO', 'https://google.com');

// ===========================
// CONFIGURACIÓN TMDB API
// ===========================
$tmdbApiKey = '6b89fc30a7df6b5ac90e3a8cf3dc6200';
$tmdbReadAccessToken = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI2Yjg5ZmMzMGE3ZGY2YjVhYzkwZTNhOGNmM2RjNjIwMCIsIm5iZiI6MTc1NTMxMDAzNi45OTMsInN1YiI6IjY4OWZlN2Q0MTE4ZTZlMjFhOWQ0MTU3MSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.0dAWj3b-f_kT1fIOV2SRaz-IgkW-LcGJ_ks-nnZN1Kc';
$tmdbImageUrl = 'https://image.tmdb.org/t/p/w500';
$tmdbApiUrl = 'https://api.themoviedb.org/3';
$tmdbContentRatingCountries = ['ES', 'MX', 'AR', 'CL', 'CO', 'US'];

// ===========================
// FUNCIONES DE PROTECCIÓN DE DOMINIO
// ===========================
function obtenerDominioActual() {
    if (!empty($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        if (strpos($host, ':') !== false) $host = explode(':', $host)[0];
        return strtolower(trim($host));
    }
    if (!empty($_SERVER['SERVER_NAME'])) return strtolower(trim($_SERVER['SERVER_NAME']));
    if (!empty($_SERVER['SERVER_ADDR'])) return $_SERVER['SERVER_ADDR'];
    return null;
}

function verificarDominio() {
    $dominioActual = obtenerDominioActual();
    if (!$dominioActual) return false;
    if ($dominioActual === strtolower(DOMINIO_PERMITIDO)) return true;
    foreach (DOMINIOS_PERMITIDOS_ADICIONALES as $dominioPermitido) {
        if ($dominioActual === strtolower($dominioPermitido)) return true;
    }
    return false;
}

function manejarDominioNoAutorizado() {
    $accion = ACCION_DOMINIO_NO_AUTORIZADO;
    error_log(sprintf("[CineXperience] Acceso no autorizado desde dominio: %s | IP: %s", obtenerDominioActual() ?? 'desconocido', $_SERVER['REMOTE_ADDR'] ?? 'desconocida'));
    if ($accion === 'redirigir' && defined('URL_REDIRECCION_DOMINIO') && !headers_sent()) {
        header("Location: " . URL_REDIRECCION_DOMINIO);
        exit;
    }
    if (!headers_sent()) {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: text/html; charset=UTF-8");
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acceso Denegado</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0a0a0a 0%, #1a0a0a 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; color: #fafafa; text-align: center; padding: 20px; }
.error-container { max-width: 500px; padding: 40px 30px; background: rgba(26, 26, 26, 0.9); border-radius: 20px; border: 1px solid rgba(220, 38, 38, 0.3); box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5); }
.error-icon { font-size: 64px; color: #dc2626; margin-bottom: 20px; }
.error-title { font-size: 24px; font-weight: 700; margin-bottom: 12px; }
.error-message { color: #a3a3a3; font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
.error-details { background: rgba(0,0,0,0.3); padding: 12px 16px; border-radius: 10px; font-size: 12px; color: #737373; text-align: left; font-family: monospace; }
</style>
</head>
<body>
<div class="error-container">
<div class="error-icon">🔒</div>
<h1 class="error-title">Acceso Denegado</h1>
<p class="error-message">Este recurso solo está disponible en el dominio autorizado.</p>
<div class="error-details">
<div><strong>Dominio detectado:</strong> <?= htmlspecialchars(obtenerDominioActual() ?? 'desconocido') ?></div>
<div><strong>IP de origen:</strong> <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'desconocida') ?></div>
<div><strong>Timestamp:</strong> <?= date('Y-m-d H:i:s') ?></div>
</div>
</div>
</body>
</html>
<?php
    exit;
}

function verificarDominioTemprano() {
    if (php_sapi_name() === 'cli') return true;
    $dominioActual = obtenerDominioActual();
    $esDesarrollo = (DOMINIO_PERMITIDO === 'localhost' || DOMINIO_PERMITIDO === '127.0.0.1');
    if ($esDesarrollo && in_array($dominioActual, ['localhost', '127.0.0.1', '::1'])) return true;
    if (!verificarDominio()) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Acceso denegado: dominio no autorizado']);
            exit;
        }
        manejarDominioNoAutorizado();
    }
    return true;
}
verificarDominioTemprano();

// ===========================
// FUNCIONES DE SEGURIDAD Y UTILIDAD
// ===========================
function iniciarSesionSegura() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => SESSION_LIFETIME,
            'cookie_secure' => false,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
            'sid_length' => 64,
            'sid_bits_per_character' => 6
        ]);
    }
}

function generarTokenIframe($iframeId, $url) {
    $timestamp = time();
    $secret = hash_hmac('sha256', $iframeId . $url . ($_SERVER['HTTP_USER_AGENT'] ?? ''), SECRET_KEY);
    $token = base64_encode(json_encode([
        'id' => $iframeId,
        'ts' => $timestamp,
        'hash' => hash_hmac('sha256', $iframeId . $timestamp . $url, $secret)
    ]));
    return str_replace(['+', '/', '='], ['-', '_', ''], $token);
}

function validarTokenIframe($token, $expectedUrl) {
    try {
        $token = str_replace(['-', '_'], ['+', '/'], $token);
        $padding = strlen($token) % 4;
        if ($padding) $token .= str_repeat('=', 4 - $padding);
        $data = json_decode(base64_decode($token), true);
        if (!$data || !isset($data['id'], $data['ts'], $data['hash'])) return false;
        if (time() - $data['ts'] > TOKEN_LIFETIME) return false;
        $secret = hash_hmac('sha256', $data['id'] . $expectedUrl . ($_SERVER['HTTP_USER_AGENT'] ?? ''), SECRET_KEY);
        $expectedHash = hash_hmac('sha256', $data['id'] . $data['ts'] . $expectedUrl, $secret);
        return hash_equals($expectedHash, $data['hash']);
    } catch (Exception $e) {
        return false;
    }
}

function ofuscarUrl($url) {
    if (!ENCRYPT_IFRAME_URLS) return htmlspecialchars($url);
    $parts = parse_url($url);
    if (!$parts) return htmlspecialchars($url);
    $encoded = base64_encode($parts['scheme'] . '://' . ($parts['host'] ?? '') . ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : ''));
    return 'data:frame:' . $encoded;
}

function desofuscarUrl($encoded) {
    if (strpos($encoded, 'data:frame:') !== 0) return $encoded;
    $base64 = substr($encoded, 11);
    $decoded = base64_decode($base64);
    return filter_var($decoded, FILTER_VALIDATE_URL) ? $decoded : '';
}

function verificarRateLimit($ip, $maxRequests = MAX_REQUESTS_PER_MINUTE) {
    $cacheFile = sys_get_temp_dir() . '/cinex_rl_' . md5($ip);
    $now = time();
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && $now - $data['window_start'] < 60) {
            if ($data['count'] >= $maxRequests) return false;
            $data['count']++;
        } else {
            $data = ['window_start' => $now, 'count' => 1];
        }
    } else {
        $data = ['window_start' => $now, 'count' => 1];
    }
    file_put_contents($cacheFile, json_encode($data), LOCK_EX);
    return true;
}

// ===========================
// CONEXIÓN Y CREACIÓN DE BD (POSTGRESQL)
// ===========================
function conectarBD() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        // Driver para Postgres (pgsql)
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        // Tabla iframes (Sintaxis adaptada a Postgres)
        $pdo->exec("CREATE TABLE IF NOT EXISTS iframes (
            id SERIAL PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            poster VARCHAR(500) NOT NULL,
            anio INTEGER NOT NULL,
            url_iframe VARCHAR(1000) NOT NULL,
            tmdb_id INTEGER DEFAULT NULL,
            media_type VARCHAR(10) DEFAULT 'movie',
            sinopsis TEXT DEFAULT NULL,
            reparto TEXT DEFAULT NULL,
            duracion VARCHAR(50) DEFAULT NULL,
            genero VARCHAR(255) DEFAULT NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Crear índices (Postgres los maneja fuera de la creación de tabla)
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_nombre ON iframes(nombre)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tmdb ON iframes(tmdb_id)");
        
        // Tabla secciones
        $pdo->exec("CREATE TABLE IF NOT EXISTS secciones (
            id SERIAL PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            tipo VARCHAR(20) NOT NULL DEFAULT 'section',
            orden INTEGER DEFAULT 0,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Tabla seccion_items
        $pdo->exec("CREATE TABLE IF NOT EXISTS seccion_items (
            id SERIAL PRIMARY KEY,
            seccion_id INTEGER NOT NULL,
            iframe_id INTEGER NOT NULL,
            orden INTEGER DEFAULT 0,
            FOREIGN KEY (seccion_id) REFERENCES secciones(id) ON DELETE CASCADE,
            FOREIGN KEY (iframe_id) REFERENCES iframes(id) ON DELETE CASCADE,
            UNIQUE (seccion_id, iframe_id)
        )");
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Error BD: " . $e->getMessage());
        die("Error de conexión a la base de datos.");
    }
}

// ... El resto de tus funciones TMDB API, Autenticación y AJAX se mantienen igual ...
// (Para ahorrar espacio, asegúrate de mantener el resto del código original de tu config.php aquí abajo)

function tmdbRequest($endpoint, $apiKey, $params = []) {
    if (empty($apiKey) || $apiKey === 'TU_API_KEY_AQUI') return ['error' => 'API Key de TMDB no configurada'];
    $params['api_key'] = $apiKey;
    $query = http_build_query($params);
    $url = $GLOBALS['tmdbApiUrl'] . $endpoint . '?' . $query;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'CineXperience/2.7',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            !empty($GLOBALS['tmdbReadAccessToken']) ? 'Authorization: Bearer ' . $GLOBALS['tmdbReadAccessToken'] : ''
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || !$response) return ['error' => 'Error HTTP ' . $httpCode];
    $data = json_decode($response, true);
    return $data ?? ['error' => 'Respuesta inválida'];
}

function buscarEnTMDB($query, $apiKey) {
    if (strlen($query) < 2) return ['results' => []];
    $resultados = tmdbRequest('/search/multi', $apiKey, ['query' => $query, 'include_adult' => 'false', 'language' => 'es-ES', 'page' => 1]);
    if (isset($resultados['error'])) return $resultados;
    $items = [];
    if (!empty($resultados['results'])) {
        foreach ($resultados['results'] as $item) {
            if (!in_array($item['media_type'], ['movie', 'tv']) || empty($item['poster_path'])) continue;
            $title = $item['title'] ?? $item['name'] ?? '';
            $date = $item['release_date'] ?? $item['first_air_date'] ?? '';
            $year = !empty($date) ? substr($date, 0, 4) : 'N/A';
            $items[] = [
                'id' => $item['id'],
                'title' => $title,
                'year' => $year,
                'type' => $item['media_type'],
                'poster_path' => $item['poster_path'],
                'poster_url' => $item['poster_path'] ? $GLOBALS['tmdbImageUrl'] . $item['poster_path'] : null,
                'overview' => $item['overview'] ?? '',
                'vote_average' => $item['vote_average'] ?? 0,
                'genre_ids' => $item['genre_ids'] ?? []
            ];
        }
    }
    return ['results' => $items];
}

function obtenerDetallesTMDB($id, $mediaType, $apiKey) {
    $endpoint = '/' . $mediaType . '/' . (int)$id;
    $detalles = tmdbRequest($endpoint, $apiKey, ['language' => 'es-ES', 'append_to_response' => 'videos,credits']);
    if (isset($detalles['error'])) return null;
    
    $date = $detalles['release_date'] ?? $detalles['first_air_date'] ?? '';
    $year = !empty($date) ? substr($date, 0, 4) : '';
    $genres = !empty($detalles['genres']) ? implode(', ', array_column($detalles['genres'], 'name')) : '';
    $genreIds = !empty($detalles['genres']) ? array_column($detalles['genres'], 'id') : [];
    
    $trailerUrl = '';
    if (!empty($detalles['videos']['results'])) {
        foreach ($detalles['videos']['results'] as $video) {
            if (($video['site'] ?? '') === 'YouTube' && ($video['type'] ?? '') === 'Trailer') {
                $trailerUrl = 'https://www.youtube.com/embed/' . $video['key'];
                break;
            }
        }
    }
    
    $reparto = '';
    if (!empty($detalles['credits']['cast'])) {
        $actores = array_slice($detalles['credits']['cast'], 0, 10);
        $reparto = implode(', ', array_column($actores, 'name'));
    }
    
    $duracion = '';
    if (!empty($detalles['runtime'])) {
        $min = (int)$detalles['runtime'];
        $h = intdiv($min, 60);
        $m = $min % 60;
        $duracion = $h > 0 ? "{$h}h {$m}min" : "{$m}min";
    } elseif (!empty($detalles['episode_run_time'][0])) {
        $min = (int)$detalles['episode_run_time'][0];
        $h = intdiv($min, 60);
        $m = $min % 60;
        $duracion = $h > 0 ? "{$h}h {$m}min" : "{$m}min";
    }
    
    return [
        'id' => $detalles['id'],
        'title' => $detalles['title'] ?? $detalles['name'] ?? '',
        'year' => $year,
        'poster_path' => $detalles['poster_path'] ?? '',
        'poster_url' => $detalles['poster_path'] ? $GLOBALS['tmdbImageUrl'] . $detalles['poster_path'] : '',
        'media_type' => $mediaType,
        'overview' => $detalles['overview'] ?? '',
        'genres' => $genres,
        'genre_ids' => $genreIds,
        'runtime' => $detalles['runtime'] ?? 0,
        'vote_average' => $detalles['vote_average'] ?? 0,
        'trailer_url' => $trailerUrl,
        'reparto' => $reparto,
        'duracion' => $duracion,
        'sinopsis' => $detalles['overview'] ?? ''
    ];
}

function obtenerClasificacionEdadTMDB($id, $mediaType, $apiKey, $countries = []) {
    if (empty($apiKey)) return null;
    $endpoint = $mediaType === 'movie' ? "/movie/{$id}/release_dates" : "/tv/{$id}/content_ratings";
    $data = tmdbRequest($endpoint, $apiKey);
    if (!$data || isset($data['error'])) return null;
    
    if ($mediaType === 'movie' && !empty($data['results'])) {
        foreach ($countries as $countryCode) {
            foreach ($data['results'] as $result) {
                if (($result['iso_3166_1'] ?? '') === strtoupper($countryCode) && !empty($result['release_dates'])) {
                    foreach ($result['release_dates'] as $rd) {
                        $cert = trim($rd['certification'] ?? '');
                        if ($cert !== '') return ['rating' => $cert, 'country' => strtoupper($countryCode)];
                    }
                }
            }
        }
    }
    if ($mediaType === 'tv' && !empty($data['results'])) {
        foreach ($countries as $countryCode) {
            foreach ($data['results'] as $result) {
                if (($result['iso_3166_1'] ?? '') === strtoupper($countryCode)) {
                    $cert = trim($result['rating'] ?? '');
                    if ($cert !== '') return ['rating' => $cert, 'country' => strtoupper($countryCode)];
                }
            }
        }
    }
    return null;
}

function verificarAccesoGestion() {
    iniciarSesionSegura();
    if (isset($_SESSION['gestion_autenticado']) && $_SESSION['gestion_autenticado'] === true) {
        $_SESSION['last_activity'] = time();
        return true;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_unset();
        session_destroy();
        return false;
    }
    if (isset($_POST['clave_secreta']) && $_POST['clave_secreta'] === SECRET_KEY) {
        session_regenerate_id(true);
        $_SESSION['gestion_autenticado'] = true;
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

function cerrarSesionSegura() {
    iniciarSesionSegura();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header("Location: index.php");
    exit;
}

function mostrarFormularioLogin() {
    iniciarSesionSegura();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acceso Gestión - Cine Xperience</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
:root { --red: #e63030; --red-dim: #c02020; --surface: #0c0c0e; --surface-2: #131318; --surface-3: #1a1a22; --border: rgba(255,255,255,0.07); --border-red: rgba(230,48,48,0.35); --text: #f0f0f5; --text-muted: #888899; --font-display: 'Bebas Neue', sans-serif; --font-body: 'DM Sans', sans-serif; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: var(--font-body); background: var(--surface); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; position: relative; overflow: hidden; }
body::before { content: ''; position: fixed; inset: 0; background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(230,48,48,0.12) 0%, transparent 70%), radial-gradient(ellipse 40% 40% at 80% 80%, rgba(180,30,30,0.08) 0%, transparent 60%); pointer-events: none; }
.login-wrap { position: relative; z-index: 1; width: 100%; max-width: 420px; }
.login-logo-ring { width: 72px; height: 72px; margin: 0 auto 20px; border-radius: 50%; background: conic-gradient(var(--red) 0deg, var(--red-dim) 120deg, transparent 180deg, var(--red) 360deg); display: flex; align-items: center; justify-content: center; animation: rotateLogo 8s linear infinite; }
@keyframes rotateLogo { to { transform: rotate(360deg); } }
.login-logo-inner { width: 58px; height: 58px; border-radius: 50%; background: var(--surface); display: flex; align-items: center; justify-content: center; animation: rotateLogo 8s linear infinite reverse; }
.login-logo-inner i { font-size: 22px; color: var(--red); }
.login-site-name { font-family: var(--font-display); font-size: 36px; letter-spacing: 3px; color: var(--text); text-align: center; line-height: 1; margin-bottom: 6px; }
.login-site-name span { color: var(--red); }
.login-tagline { font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--text-muted); text-align: center; margin-bottom: 30px; }
.login-card { background: var(--surface-2); border: 1px solid var(--border); border-top: 1px solid rgba(230,48,48,0.3); border-radius: 20px; padding: 36px 32px 32px; box-shadow: 0 40px 80px rgba(0,0,0,0.5); }
.login-card-title { font-size: 16px; font-weight: 600; color: var(--text); margin-bottom: 4px; }
.login-card-sub { font-size: 13px; color: var(--text-muted); margin-bottom: 28px; }
.form-field { margin-bottom: 20px; }
.form-label { display: block; font-size: 12px; font-weight: 500; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; }
.form-control { width: 100%; background: var(--surface-3); border: 1px solid var(--border); border-radius: 12px; padding: 13px 16px; font-family: var(--font-body); font-size: 15px; color: var(--text); outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
.form-control:focus { border-color: var(--red); box-shadow: 0 0 0 3px rgba(230,48,48,0.22); }
.btn-access { width: 100%; padding: 14px; background: var(--red); border: none; border-radius: 12px; font-family: var(--font-body); font-size: 14px; font-weight: 600; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s, transform 0.15s; }
.btn-access:hover { background: #f03535; transform: translateY(-2px); }
.error-pill { display: flex; align-items: center; gap: 10px; background: rgba(230,48,48,0.12); border: 1px solid rgba(230,48,48,0.3); border-radius: 10px; padding: 12px 16px; color: #f87171; font-size: 13px; margin-bottom: 20px; }
.login-back { display: block; text-align: center; margin-top: 22px; font-size: 13px; color: var(--text-muted); text-decoration: none; }
.login-back:hover { color: var(--red); }
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-logo-ring"><div class="login-logo-inner"><i class="fa-solid fa-film"></i></div></div>
    <div class="login-site-name">Cine <span>Xperience</span></div>
    <div class="login-tagline">Panel de Gestión</div>
    <div class="login-card">
        <div class="login-card-title">Acceso restringido</div>
        <div class="login-card-sub">Ingresa tu clave secreta</div>
        <?php if (isset($_POST['clave_secreta']) && $_POST['clave_secreta'] !== SECRET_KEY): ?>
        <div class="error-pill"><i class="fa-solid fa-circle-exclamation"></i> Clave incorrecta</div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-field"><label class="form-label">Clave Secreta</label><input type="password" name="clave_secreta" class="form-control" placeholder="••••••••••••" autofocus></div>
            <button type="submit" class="btn-access"><i class="fa-solid fa-unlock"></i> Entrar</button>
        </form>
    </div>
    <a href="index.php" class="login-back"><i class="fa-solid fa-arrow-left"></i> Volver</a>
</div>
</body>
</html>
<?php
    exit;
}

// Procesar logout
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    cerrarSesionSegura();
}

// Procesar peticiones AJAX
if (isset($_GET['action']) && in_array($_GET['action'], ['search', 'details', 'search_local', 'validate_token'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!verificarRateLimit($ip)) {
        http_response_code(429);
        echo json_encode(['error' => 'Demasiadas solicitudes']);
        exit;
    }
    
    if ($_GET['action'] === 'search') {
        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 2) { echo json_encode(['results' => []]); exit; }
        $resultados = buscarEnTMDB($query, $GLOBALS['tmdbApiKey']);
        echo json_encode($resultados);
        exit;
    }
    if ($_GET['action'] === 'details') {
        $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
        $type = in_array($_GET['type'] ?? '', ['movie', 'tv']) ? $_GET['type'] : 'movie';
        if (!$id) { echo json_encode(['error' => 'ID inválido']); exit; }
        $detalles = obtenerDetallesTMDB($id, $type, $GLOBALS['tmdbApiKey']);
        echo json_encode($detalles ?: ['error' => 'No encontrado']);
        exit;
    }
    if ($_GET['action'] === 'search_local') {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 1) { echo json_encode(['results' => []]); exit; }
        $pdo = conectarBD();
        $stmt = $pdo->prepare("SELECT id, nombre, poster, anio, media_type, tmdb_id FROM iframes WHERE nombre LIKE :q ORDER BY nombre ASC LIMIT 20");
        $stmt->execute([':q' => '%' . $q . '%']);
        echo json_encode(['results' => $stmt->fetchAll()]);
        exit;
    }
    if ($_GET['action'] === 'validate_token') {
        $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
        $token = $_GET['token'] ?? '';
        $urlEncoded = $_GET['url'] ?? '';
        if (!$id || !$token || !$urlEncoded) { echo json_encode(['valid' => false]); exit; }
        $url = base64_decode($urlEncoded);
        $pdo = conectarBD();
        $stmt = $pdo->prepare("SELECT url_iframe FROM iframes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        echo json_encode(['valid' => $row && $row['url_iframe'] === $url && validarTokenIframe($token, $url)]);
        exit;
    }
}
?>
