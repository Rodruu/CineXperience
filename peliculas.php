<?php
require_once 'config.php';

$pdo = conectarBD();
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$porPagina = 20;
$offset = ($pagina - 1) * $porPagina;

$where = [];
$params = [];
$busqueda = trim($_GET['q'] ?? '');
$genero = isset($_GET['genero']) ? (int)$_GET['genero'] : null;

if ($busqueda !== '') {
    $where[] = "nombre LIKE :q";
    $params[':q'] = '%' . $busqueda . '%';
}
if ($genero) {
    $where[] = "FIND_IN_SET(:genero, REPLACE(genero, ', ', ','))";
    $params[':genero'] = $genero;
}

$sqlCount = "SELECT COUNT(*) FROM iframes";
if ($where) $sqlCount .= " WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPaginas = ceil($total / $porPagina);

$sql = "SELECT * FROM iframes";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY fecha_creacion DESC LIMIT $offset, $porPagina";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$peliculas = $stmt->fetchAll();

iniciarSesionSegura();
if (!isset($_SESSION['tmdb_genres'])) {
    $genresData = tmdbRequest('/genre/movie/list', $tmdbApiKey, ['language' => 'es']);
    $_SESSION['tmdb_genres'] = $genresData['genres'] ?? [];
}
$generos = $_SESSION['tmdb_genres'];

if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="description" content="Todas las películas - Cine Xperience">
<meta name="robots" content="noindex, nofollow">
<title>Todas las Películas - Cine Xperience</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
:root {
    --red: #e63030; --red-dim: #c02020; --red-glow: rgba(230,48,48,0.20);
    --surface: #08080b; --surface-2: #0f0f14; --surface-3: #16161e;
    --border: rgba(255,255,255,0.06); --border-red: rgba(230,48,48,0.30);
    --text: #f0f0f8; --text-2: #a0a0b8; --text-3: #606075;
    --font-display: 'Bebas Neue', sans-serif; --font-body: 'DM Sans', sans-serif;
    --ease-out: cubic-bezier(0.22, 1, 0.36, 1);
}
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--font-body); background: var(--surface); color: var(--text);
    line-height: 1.55; overflow-x: hidden; min-height: 100vh;
    padding-bottom: 80px;
    user-select: none; -webkit-user-select: none;
}
body::before { content: ''; position: fixed; inset: 0; z-index: 0; background: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(230,48,48,0.10) 0%, transparent 65%); pointer-events: none; }
.container { position: relative; z-index: 1; width: 100%; max-width: 1520px; margin: 0 auto; padding: 0 16px; }
@media (min-width: 768px) { .container { padding: 0 24px; } }

.main-header { position: sticky; top: 0; z-index: 200; background: rgba(8,8,11,0.92); backdrop-filter: blur(24px); border-bottom: 1px solid var(--border-red); padding: 12px 0; }
.header-content { display: flex; align-items: center; justify-content: space-between; }
.logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.logo-mark { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, var(--red) 0%, #8c1515 100%); display: flex; align-items: center; justify-content: center; }
.logo-mark i { font-size: 16px; color: #fff; }
.logo-text h1 { font-family: var(--font-display); font-size: 22px; letter-spacing: 2px; color: var(--text); }
.logo-text h1 span { color: var(--red); }

.page-title { font-family: var(--font-display); font-size: 28px; letter-spacing: 2px; margin: 24px 0 20px; }
.filter-bar { display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap; }
.search-wrap { position: relative; flex: 2; min-width: 200px; }
.search-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-3); font-size: 14px; }
.search-input { width: 100%; padding: 12px 16px 12px 42px; background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; font-size: 14px; color: var(--text); outline: none; }
.search-input:focus { border-color: var(--red); }
.filter-select { padding: 12px 16px; background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; font-size: 14px; color: var(--text); outline: none; min-width: 160px; }
.btn-filter { padding: 12px 24px; background: var(--red); border: none; border-radius: 12px; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; }
.btn-filter:hover { background: #f03535; }

.movies-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
@media (min-width: 500px) { .movies-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 768px) { .movies-grid { grid-template-columns: repeat(4, 1fr); } }
@media (min-width: 1200px) { .movies-grid { grid-template-columns: repeat(5, 1fr); } }

.movie-card { position: relative; border-radius: 14px; overflow: hidden; background: var(--surface-2); border: 1px solid var(--border); cursor: pointer; text-decoration: none; display: block; transition: transform 0.25s; }
.movie-card:hover { transform: translateY(-4px); border-color: rgba(230,48,48,0.45); }
.card-poster { position: relative; aspect-ratio: 2/3; overflow: hidden; background: var(--surface-3); }
.card-poster img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
.movie-card:hover .card-poster img { transform: scale(1.05); }
.card-badge-year { position: absolute; top: 8px; right: 8px; z-index: 2; background: rgba(8,8,11,0.85); backdrop-filter: blur(6px); border: 1px solid var(--border-red); border-radius: 6px; padding: 2px 8px; font-size: 10px; font-weight: 600; color: #f87171; }
.card-overlay { position: absolute; inset: 0; z-index: 3; display: flex; align-items: flex-end; padding: 12px; opacity: 0; transition: opacity 0.25s; }
.movie-card:hover .card-overlay { opacity: 1; }
.play-btn { width: 100%; padding: 10px; background: var(--red); border: none; border-radius: 10px; color: #fff; font-size: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 6px; }
.card-body { padding: 10px 12px 12px; }
.card-title { font-size: 13px; font-weight: 500; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.pagination { display: flex; justify-content: center; align-items: center; gap: 6px; margin: 40px 0 20px; flex-wrap: wrap; }
.page-btn { display: flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 8px; background: var(--surface-2); border: 1px solid var(--border); border-radius: 10px; color: var(--text-2); text-decoration: none; font-size: 13px; transition: all 0.18s; }
.page-btn:hover { border-color: var(--border-red); color: var(--text); }
.page-btn.active { background: var(--red); border-color: var(--red); color: #fff; }
.page-info { font-size: 12px; color: var(--text-3); margin: 0 8px; }

.bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 300; background: rgba(8,8,11,0.95); backdrop-filter: blur(20px); border-top: 1px solid var(--border-red); display: flex; justify-content: space-around; padding: 8px 0 12px; }
.bottom-nav a { color: var(--text-2); text-decoration: none; display: flex; flex-direction: column; align-items: center; font-size: 11px; flex: 1; }
.bottom-nav a.active { color: var(--red); }
.bottom-nav i { font-size: 20px; margin-bottom: 3px; }

.empty-state { text-align: center; padding: 60px 20px; background: var(--surface-2); border: 1px dashed rgba(230,48,48,0.2); border-radius: 20px; }
.empty-icon { font-size: 48px; color: var(--surface-4); margin-bottom: 16px; }
.empty-state h3 { font-family: var(--font-display); font-size: 20px; color: var(--text-2); }

.page-loading-overlay { position: fixed; inset: 0; z-index: 9000; background: var(--surface); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
.page-loading-overlay.active { opacity: 1; pointer-events: all; }
.plo-logo { font-family: var(--font-display); font-size: 32px; letter-spacing: 3px; color: var(--text-3); }
.plo-logo span { color: var(--red); }
.plo-ring-outer { width: 56px; height: 56px; border-radius: 50%; border: 2px solid rgba(230,48,48,0.15); border-top-color: var(--red); animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.plo-title { font-family: var(--font-display); font-size: 16px; color: var(--text-2); }
.plo-movie-name { font-size: 12px; color: var(--text-3); }
</style>
</head>
<body>
<div class="page-loading-overlay" id="pageLoadingOverlay">
    <div class="plo-logo">Cine <span>Xperience</span></div>
    <div class="plo-ring-outer"></div>
    <div class="plo-title">CARGANDO</div>
    <div class="plo-movie-name" id="ploMovieName">Preparando el reproductor...</div>
</div>

<header class="main-header">
    <div class="container header-content">
        <a href="index.php" class="logo">
            <div class="logo-mark"><i class="fa-solid fa-play"></i></div>
            <div class="logo-text"><h1>Cine <span>Xperience</span></h1></div>
        </a>
    </div>
</header>

<main>
    <div class="container">
        <h1 class="page-title">Todas las Películas</h1>
        
        <form method="GET" class="filter-bar">
            <div class="search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" class="search-input" placeholder="Buscar película..." value="<?= htmlspecialchars($busqueda) ?>">
            </div>
            <select name="genero" class="filter-select">
                <option value="">Todos los géneros</option>
                <?php foreach ($generos as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $genero == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Filtrar</button>
            <?php if ($busqueda || $genero): ?>
            <a href="peliculas.php" class="btn-filter" style="background: var(--surface-3); color: var(--text-2);"><i class="fa-solid fa-xmark"></i> Limpiar</a>
            <?php endif; ?>
        </form>

        <?php if (empty($peliculas)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fa-regular fa-face-frown"></i></div>
            <h3>No se encontraron películas</h3>
            <p style="color: var(--text-3); margin-top: 8px;">Intenta con otra búsqueda</p>
        </div>
        <?php else: ?>
        <div class="movies-grid" id="moviesGrid">
            <?php foreach ($peliculas as $p): ?>
            <a href="reproductor.php?ver=<?= $p['id'] ?>" class="movie-card" data-name="<?= htmlspecialchars($p['nombre']) ?>">
                <div class="card-poster">
                    <img src="<?= htmlspecialchars($p['poster']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" loading="lazy">
                    <span class="card-badge-year"><?= $p['anio'] ?></span>
                    <div class="card-overlay"><span class="play-btn"><i class="fa-solid fa-play"></i> Reproducir</span></div>
                </div>
                <div class="card-body"><p class="card-title"><?= htmlspecialchars($p['nombre']) ?></p></div>
            </a>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPaginas > 1): ?>
        <div class="pagination">
            <?php if ($pagina > 1): ?>
            <a href="?pagina=1&q=<?= urlencode($busqueda) ?>&genero=<?= $genero ?>" class="page-btn"><i class="fa-solid fa-angles-left"></i></a>
            <a href="?pagina=<?= $pagina-1 ?>&q=<?= urlencode($busqueda) ?>&genero=<?= $genero ?>" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
            <?php endif; ?>
            
            <?php
            $start = max(1, $pagina - 2);
            $end = min($totalPaginas, $pagina + 2);
            for ($i = $start; $i <= $end; $i++): ?>
            <a href="?pagina=<?= $i ?>&q=<?= urlencode($busqueda) ?>&genero=<?= $genero ?>" class="page-btn <?= $i == $pagina ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            
            <?php if ($pagina < $totalPaginas): ?>
            <a href="?pagina=<?= $pagina+1 ?>&q=<?= urlencode($busqueda) ?>&genero=<?= $genero ?>" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
            <a href="?pagina=<?= $totalPaginas ?>&q=<?= urlencode($busqueda) ?>&genero=<?= $genero ?>" class="page-btn"><i class="fa-solid fa-angles-right"></i></a>
            <?php endif; ?>
            <span class="page-info">Pág. <?= $pagina ?> de <?= $totalPaginas ?></span>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<nav class="bottom-nav">
    <a href="index.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="peliculas.php" class="active"><i class="fa-solid fa-film"></i><span>Películas</span></a>
    <a href="index.php#quienesSomos"><i class="fa-solid fa-circle-info"></i><span>Quiénes Somos</span></a>
</nav>

<script>
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => { if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key.toUpperCase())) || (e.ctrlKey && ['U','S','P'].includes(e.key.toUpperCase()))) e.preventDefault(); });

const overlay = document.getElementById('pageLoadingOverlay');
const ploName = document.getElementById('ploMovieName');

document.addEventListener('click', function(e) {
    const card = e.target.closest('.movie-card');
    if (!card) return;
    e.preventDefault();
    const name = card.dataset.name || 'Cargando...';
    const href = card.getAttribute('href');
    ploName.textContent = name;
    overlay.classList.add('active');
    setTimeout(() => { window.location.href = href; }, 600);
});

<?php if (BLOCK_DEV_TOOLS): ?>
setInterval(() => { if (window.outerWidth - window.innerWidth > 160 || window.outerHeight - window.innerHeight > 160) document.body.innerHTML = '<div style="position:fixed;inset:0;background:#08080b;color:#e63030;display:flex;align-items:center;justify-content:center;">Acceso restringido</div>'; }, 1000);
<?php endif; ?>
</script>
</body>
</html>