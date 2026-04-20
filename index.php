<?php
require_once 'config.php';

$pdo = conectarBD();

// Obtener slider (máx 5) - de secciones tipo 'slider'
$sliderItems = [];
try {
    $stmtSlider = $pdo->query("
        SELECT i.id, i.nombre, i.poster, i.anio 
        FROM secciones s 
        INNER JOIN seccion_items si ON s.id = si.seccion_id 
        INNER JOIN iframes i ON si.iframe_id = i.id 
        WHERE s.tipo = 'slider' 
        ORDER BY s.orden ASC, si.orden ASC 
        LIMIT 5
    ");
    if ($stmtSlider) {
        $sliderItems = $stmtSlider->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Error en slider: " . $e->getMessage());
    $sliderItems = [];
}

// Obtener secciones destacadas (múltiples secciones de tipo 'section')
$secciones = [];
try {
    // Obtener todas las secciones de tipo 'section' que tengan items
    $stmtSecciones = $pdo->query("
        SELECT DISTINCT s.id, s.nombre, s.tipo
        FROM secciones s 
        INNER JOIN seccion_items si ON s.id = si.seccion_id 
        WHERE s.tipo = 'section'
        ORDER BY s.orden ASC, s.id ASC
    ");
    
    if ($stmtSecciones) {
        while ($seccion = $stmtSecciones->fetch()) {
            // Para cada sección, obtenemos sus items
            $stmtItems = $pdo->prepare("
                SELECT i.id, i.nombre, i.poster, i.anio, i.media_type, i.tmdb_id 
                FROM seccion_items si 
                INNER JOIN iframes i ON si.iframe_id = i.id 
                WHERE si.seccion_id = ? 
                ORDER BY si.orden ASC 
                LIMIT 10
            ");
            $stmtItems->execute([$seccion['id']]);
            $items = $stmtItems->fetchAll();
            
            // Solo agregamos la sección si tiene al menos un item
            if (!empty($items)) {
                $secciones[] = [
                    'id' => $seccion['id'],
                    'nombre' => $seccion['nombre'],
                    'items' => $items
                ];
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error en secciones: " . $e->getMessage());
    $secciones = [];
}

// Si no hay secciones configuradas, intentar obtener de tablas alternativas
if (empty($secciones) && empty($sliderItems)) {
    try {
        // Intentar obtener de seccion_películas
        $stmtAlt = $pdo->query("
            SELECT DISTINCT nombre 
            FROM seccion_películas 
            WHERE nombre IS NOT NULL 
            LIMIT 1
        ");
        if ($stmtAlt && $stmtAlt->fetch()) {
            // Hay datos en tablas antiguas, mostrar mensaje
            $necesitaMigracion = true;
        }
    } catch (PDOException $e) {
        // La tabla no existe
    }
}

$totalItems = $pdo->query("SELECT COUNT(*) FROM iframes")->fetchColumn();

if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="description" content="Cine Xperience - Disfruta de las mejores películas en un solo lugar">
<meta name="robots" content="noindex, nofollow">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Cine Xperience | Películas Premium</title>
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
html { scroll-behavior: smooth; scroll-padding-top: 80px; }
body {
    font-family: var(--font-body); background: var(--surface); color: var(--text);
    line-height: 1.55; overflow-x: hidden; min-height: 100vh;
    padding-bottom: 80px;
    user-select: none; -webkit-user-select: none;
}
body::before { content: ''; position: fixed; inset: 0; z-index: 0; background: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(230,48,48,0.10) 0%, transparent 65%), radial-gradient(ellipse 50% 40% at 90% 90%, rgba(180,28,28,0.07) 0%, transparent 60%); pointer-events: none; }
.container { position: relative; z-index: 1; width: 100%; max-width: 1520px; margin: 0 auto; padding: 0 16px; }
@media (min-width: 768px) { .container { padding: 0 24px; } }
@media (min-width: 1024px) { .container { padding: 0 32px; } }

/* HEADER */
.main-header { position: sticky; top: 0; z-index: 200; background: rgba(8,8,11,0.92); backdrop-filter: blur(24px); border-bottom: 1px solid var(--border-red); padding: 12px 0; }
.header-content { display: flex; align-items: center; justify-content: space-between; }
.logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.logo-mark { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, var(--red) 0%, #8c1515 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(230,48,48,0.35); }
.logo-mark i { font-size: 16px; color: #fff; }
.logo-text h1 { font-family: var(--font-display); font-size: 22px; letter-spacing: 2px; color: var(--text); text-transform: uppercase; }
.logo-text h1 span { color: var(--red); }
.logo-text p { font-size: 9px; letter-spacing: 0.15em; color: var(--text-3); }

/* PÁGINAS */
.page { display: none; }
.page.active { display: block; }

/* HERO */
.hero { padding: 40px 0 30px; text-align: center; }
.hero-eyebrow { display: inline-flex; align-items: center; gap: 8px; background: rgba(230,48,48,0.1); border: 1px solid var(--border-red); border-radius: 999px; padding: 5px 14px; font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: #f87171; margin-bottom: 20px; }
.hero-title { font-family: var(--font-display); font-size: clamp(40px, 8vw, 80px); letter-spacing: 3px; line-height: 0.95; margin-bottom: 16px; text-transform: uppercase; }
.hero-title .accent { color: var(--red); }
.hero-sub { font-size: 14px; color: var(--text-2); max-width: 480px; margin: 0 auto; }

/* STATS */
.stats-row { display: flex; justify-content: center; background: var(--surface-2); border: 1px solid var(--border-red); border-radius: 16px; overflow: hidden; margin: 24px auto; max-width: 400px; }
.stat-cell { flex: 1; text-align: center; padding: 16px 8px; position: relative; }
.stat-cell:not(:last-child)::after { content: ''; position: absolute; right: 0; top: 20%; bottom: 20%; width: 1px; background: var(--border); }
.stat-val { font-family: var(--font-display); font-size: 28px; color: var(--red); display: block; }
.stat-lbl { font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-3); }

/* SLIDER AUTOMÁTICO */
.slider-section { margin: 24px 0 32px; }
.slider-header { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.slider-header h2 { font-family: var(--font-display); font-size: 18px; letter-spacing: 2px; color: var(--text); }
.slider-container { position: relative; width: 100%; overflow: hidden; border-radius: 16px; }
.slider-wrapper { display: flex; transition: transform 0.5s ease; }
.slider-slide { flex: 0 0 100%; position: relative; }
.slider-slide a { display: block; text-decoration: none; }
.slider-slide img { width: 100%; aspect-ratio: 16/9; object-fit: cover; display: block; border-radius: 16px; border: 1px solid var(--border-red); }
.slider-dots { display: flex; justify-content: center; gap: 8px; margin-top: 12px; }
.slider-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--surface-3); border: 1px solid var(--border); cursor: pointer; transition: all 0.2s; }
.slider-dot.active { background: var(--red); border-color: var(--red); width: 24px; border-radius: 4px; }

/* SECCIÓN DE PELÍCULAS (CARDS PEQUEÑAS HORIZONTALES) */
.section-block { margin: 32px 0; }
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.section-header-left { display: flex; align-items: center; gap: 10px; }
.section-line { width: 3px; height: 20px; background: linear-gradient(180deg, var(--red) 0%, transparent 100%); border-radius: 2px; }
.section-header h2 { font-family: var(--font-display); font-size: 18px; letter-spacing: 2px; color: var(--text); text-transform: uppercase; }
.section-header .count-badge { background: var(--surface-3); border: 1px solid var(--border); border-radius: 20px; padding: 2px 10px; font-size: 11px; color: var(--text-3); margin-left: 8px; }
.btn-vermas { display: flex; align-items: center; gap: 6px; padding: 6px 14px; background: transparent; border: 1px solid var(--border-red); border-radius: 20px; color: var(--red); font-size: 12px; font-weight: 500; text-decoration: none; transition: all 0.2s; }
.btn-vermas:hover { background: var(--red); color: #fff; border-color: var(--red); }
.btn-vermas i { font-size: 10px; }

.movies-horizontal { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 8px; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; }
.movies-horizontal::-webkit-scrollbar { height: 3px; }
.movies-horizontal::-webkit-scrollbar-thumb { background: var(--red); border-radius: 3px; }
.movie-card-small { flex: 0 0 120px; scroll-snap-align: start; border-radius: 10px; overflow: hidden; background: var(--surface-2); border: 1px solid var(--border); cursor: pointer; text-decoration: none; transition: transform 0.2s, border-color 0.2s; }
@media (min-width: 500px) { .movie-card-small { flex: 0 0 140px; } }
.movie-card-small:hover { transform: translateY(-3px); border-color: var(--border-red); }
.movie-card-small .card-poster-small { position: relative; aspect-ratio: 2/3; overflow: hidden; }
.movie-card-small .card-poster-small img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
.movie-card-small:hover .card-poster-small img { transform: scale(1.05); }
.movie-card-small .card-year-small { position: absolute; top: 6px; right: 6px; background: rgba(8,8,11,0.85); backdrop-filter: blur(4px); border: 1px solid var(--border-red); border-radius: 4px; padding: 2px 6px; font-size: 9px; font-weight: 600; color: #f87171; }
.movie-card-small .card-overlay-small { position: absolute; inset: 0; display: flex; align-items: flex-end; padding: 8px; opacity: 0; transition: opacity 0.2s; background: linear-gradient(180deg, transparent 50%, rgba(8,8,11,0.8) 100%); }
.movie-card-small:hover .card-overlay-small { opacity: 1; }
.movie-card-small .play-icon-small { width: 32px; height: 32px; margin: 0 auto; background: var(--red); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; }
.movie-card-small .card-title-small { padding: 8px 8px 10px; font-size: 12px; font-weight: 500; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center; }

/* PÁGINA QUIÉNES SOMOS */
.about-page { padding: 20px 0 40px; }
.about-hero { text-align: center; padding: 40px 20px; background: linear-gradient(135deg, rgba(230,48,48,0.1) 0%, transparent 100%); border-radius: 24px; margin-bottom: 40px; border: 1px solid var(--border-red); }
.about-hero-icon { font-size: 64px; color: var(--red); margin-bottom: 20px; }
.about-hero h1 { font-family: var(--font-display); font-size: 48px; letter-spacing: 3px; margin-bottom: 16px; }
.about-hero h1 span { color: var(--red); }
.about-hero p { font-size: 16px; color: var(--text-2); max-width: 600px; margin: 0 auto; }
.about-grid { display: grid; grid-template-columns: 1fr; gap: 24px; margin: 40px 0; }
@media (min-width: 768px) { .about-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1024px) { .about-grid { grid-template-columns: repeat(4, 1fr); } }
.about-card { background: var(--surface-2); border: 1px solid var(--border); border-radius: 20px; padding: 28px 20px; text-align: center; transition: transform 0.2s, border-color 0.2s; }
.about-card:hover { transform: translateY(-5px); border-color: var(--border-red); }
.about-card-icon { width: 64px; height: 64px; margin: 0 auto 20px; border-radius: 16px; background: rgba(230,48,48,0.12); border: 1px solid var(--border-red); display: flex; align-items: center; justify-content: center; font-size: 28px; color: var(--red); }
.about-card h3 { font-family: var(--font-display); font-size: 20px; letter-spacing: 1px; margin-bottom: 12px; color: var(--text); }
.about-card p { font-size: 13px; color: var(--text-2); line-height: 1.6; }
.about-mission { background: linear-gradient(135deg, var(--surface-2) 0%, var(--surface-3) 100%); border: 1px solid var(--border-red); border-radius: 24px; padding: 40px 30px; text-align: center; margin-top: 40px; }
.about-mission h2 { font-family: var(--font-display); font-size: 28px; letter-spacing: 2px; margin-bottom: 20px; }
.about-mission h2 span { color: var(--red); }
.about-mission p { font-size: 16px; color: var(--text-2); max-width: 700px; margin: 0 auto; line-height: 1.8; }

/* BOTTOM NAV */
.bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 300; background: rgba(8,8,11,0.95); backdrop-filter: blur(20px); border-top: 1px solid var(--border-red); display: flex; justify-content: space-around; padding: 8px 0 12px; }
.bottom-nav a { color: var(--text-2); text-decoration: none; display: flex; flex-direction: column; align-items: center; font-size: 11px; flex: 1; transition: color 0.2s; cursor: pointer; }
.bottom-nav a.active { color: var(--red); }
.bottom-nav i { font-size: 20px; margin-bottom: 3px; }

/* MENSAJE DE MIGRACIÓN */
.migration-message { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); border-radius: 16px; padding: 24px; margin: 30px 0; text-align: center; }
.migration-message i { font-size: 48px; color: #f59e0b; margin-bottom: 16px; }
.migration-message h3 { font-family: var(--font-display); font-size: 20px; color: #fbbf24; margin-bottom: 12px; }
.migration-message p { color: var(--text-2); margin-bottom: 8px; }
.migration-message code { background: var(--surface-3); padding: 2px 8px; border-radius: 6px; font-family: monospace; }

/* EMPTY STATE */
.empty-state { text-align: center; padding: 60px 20px; background: var(--surface-2); border: 1px dashed rgba(230,48,48,0.2); border-radius: 20px; margin: 40px 0; }
.empty-icon { font-size: 48px; color: var(--surface-4); margin-bottom: 16px; }
.empty-state h3 { font-family: var(--font-display); font-size: 20px; color: var(--text-2); margin-bottom: 8px; }
.empty-state p { font-size: 13px; color: var(--text-3); }

/* LOADING OVERLAY */
.page-loading-overlay { position: fixed; inset: 0; z-index: 9000; background: var(--surface); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
.page-loading-overlay.active { opacity: 1; pointer-events: all; }
.plo-logo { font-family: var(--font-display); font-size: 32px; letter-spacing: 3px; color: var(--text-3); }
.plo-logo span { color: var(--red); }
.plo-ring-outer { width: 56px; height: 56px; border-radius: 50%; border: 2px solid rgba(230,48,48,0.15); border-top-color: var(--red); animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.plo-title { font-family: var(--font-display); font-size: 16px; color: var(--text-2); }
.plo-movie-name { font-size: 12px; color: var(--text-3); }

/* MENSAJE CUANDO NO HAY SECCIONES */
.no-sections-message { text-align: center; padding: 50px 30px; background: linear-gradient(135deg, rgba(230,48,48,0.05) 0%, transparent 100%); border-radius: 20px; border: 1px solid var(--border); margin: 30px 0; }
.no-sections-message i { font-size: 48px; color: var(--text-3); margin-bottom: 20px; opacity: 0.5; }
.no-sections-message h3 { font-family: var(--font-display); font-size: 20px; letter-spacing: 2px; color: var(--text-2); margin-bottom: 12px; }
.no-sections-message p { font-size: 14px; color: var(--text-3); max-width: 400px; margin: 0 auto; line-height: 1.6; }
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
        <a href="#" class="logo" onclick="showPage('home'); return false;">
            <div class="logo-mark"><i class="fa-solid fa-play"></i></div>
            <div class="logo-text"><h1>Cine <span>Xperience</span></h1><p>Películas Premium</p></div>
        </a>
    </div>
</header>

<main>
    <!-- PÁGINA HOME -->
    <div class="page active" id="page-home">
        <div class="container">
            <section class="hero">
                <div class="hero-eyebrow"><i class="fa-solid fa-circle"></i> Disponible ahora</div>
                <h2 class="hero-title"><span class="accent">CINE</span> XPERIENCE</h2>
                <p class="hero-sub">La mejor colección de películas, disponible en un solo lugar.</p>
            </section>
            
            <div class="stats-row">
                <div class="stat-cell"><span class="stat-val"><?= number_format($totalItems) ?></span><span class="stat-lbl">Películas</span></div>
                <div class="stat-cell"><span class="stat-val">HD+</span><span class="stat-lbl">Calidad</span></div>
                <div class="stat-cell"><span class="stat-val">24/7</span><span class="stat-lbl">Disponible</span></div>
            </div>

            <?php if (isset($necesitaMigracion) && $necesitaMigracion): ?>
            <div class="migration-message">
                <i class="fa-solid fa-database"></i>
                <h3>Se detectaron datos en tablas antiguas</h3>
                <p>Ejecuta el script SQL de migración en phpMyAdmin para unificar las tablas.</p>
                <p style="margin-top:12px;"><code style="font-size:12px;">SELECT * FROM seccion_películas</code> contiene datos que deben migrarse.</p>
            </div>
            <?php endif; ?>

            <!-- SLIDER AUTOMÁTICO -->
            <?php if (!empty($sliderItems)): ?>
            <div class="slider-section">
                <div class="slider-header"><div class="section-line"></div><h2>Destacados</h2></div>
                <div class="slider-container">
                    <div class="slider-wrapper" id="sliderWrapper">
                        <?php foreach ($sliderItems as $index => $item): ?>
                        <div class="slider-slide">
                            <a href="reproductor.php?ver=<?= $item['id'] ?>" data-name="<?= htmlspecialchars($item['nombre']) ?>">
                                <img src="<?= htmlspecialchars($item['poster']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" loading="lazy">
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="slider-dots" id="sliderDots"></div>
            </div>
            <?php endif; ?>

            <!-- SECCIONES CREADAS DESDE EL PANEL -->
            <?php if (!empty($secciones)): ?>
                <?php foreach ($secciones as $seccion): ?>
                <div class="section-block">
                    <div class="section-header">
                        <div class="section-header-left">
                            <div class="section-line"></div>
                            <h2><?= htmlspecialchars($seccion['nombre']) ?></h2>
                            <span class="count-badge"><?= count($seccion['items']) ?> películas</span>
                        </div>
                        <a href="peliculas.php" class="btn-vermas">Ver más <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div class="movies-horizontal">
                        <?php foreach ($seccion['items'] as $item): ?>
                        <a href="reproductor.php?ver=<?= $item['id'] ?>" class="movie-card-small" data-name="<?= htmlspecialchars($item['nombre']) ?>">
                            <div class="card-poster-small">
                                <img src="<?= htmlspecialchars($item['poster']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" loading="lazy">
                                <span class="card-year-small"><?= $item['anio'] ?></span>
                                <div class="card-overlay-small">
                                    <span class="play-icon-small"><i class="fa-solid fa-play"></i></span>
                                </div>
                            </div>
                            <div class="card-title-small"><?= htmlspecialchars($item['nombre']) ?></div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php elseif (!isset($necesitaMigracion) || !$necesitaMigracion): ?>
                <!-- MENSAJE CUANDO NO HAY SECCIONES CREADAS -->
                <div class="no-sections-message">
                    <i class="fa-solid fa-layer-group"></i>
                    <h3>No hay secciones configuradas</h3>
                    <p>Accede al panel de gestión para crear secciones y asignar películas. Las secciones que crees aparecerán aquí automáticamente.</p>
                </div>
            <?php endif; ?>

            <?php if (empty($secciones) && empty($sliderItems) && $totalItems == 0): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-regular fa-film"></i></div>
                <h3>SIN CONTENIDO AÚN</h3>
                <p>Pronto agregaremos nuevas películas.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PÁGINA PELÍCULAS (REDIRIGE) -->
    <div class="page" id="page-peliculas"></div>

    <!-- PÁGINA QUIÉNES SOMOS -->
    <div class="page" id="page-about">
        <div class="container">
            <div class="about-page">
                <div class="about-hero">
                    <div class="about-hero-icon"><i class="fa-solid fa-film"></i></div>
                    <h1>¿Quiénes <span>Somos</span>?</h1>
                    <p>Cine Xperience es tu destino definitivo para disfrutar del mejor cine, sin complicaciones ni suscripciones.</p>
                </div>

                <div class="about-grid">
                    <div class="about-card">
                        <div class="about-card-icon"><i class="fa-solid fa-film"></i></div>
                        <h3>Solo Películas</h3>
                        <p>Nos especializamos exclusivamente en cine. Cada título es cuidadosamente seleccionado para ofrecerte lo mejor del séptimo arte.</p>
                    </div>
                    <div class="about-card">
                        <div class="about-card-icon"><i class="fa-solid fa-bolt"></i></div>
                        <h3>Reproducción Inmediata</h3>
                        <p>Sin registros, sin suscripciones. Elige tu película y comienza a verla en segundos.</p>
                    </div>
                    <div class="about-card">
                        <div class="about-card-icon"><i class="fa-solid fa-shield-halved"></i></div>
                        <h3>Sistema de Protección</h3>
                        <p>Nuestro escudo inteligente detecta y bloquea intentos de redirección no deseados durante la reproducción.</p>
                    </div>
                    <div class="about-card">
                        <div class="about-card-icon"><i class="fa-solid fa-mobile-screen"></i></div>
                        <h3>100% Responsive</h3>
                        <p>Disfruta tus películas favoritas en cualquier dispositivo: móvil, tablet o escritorio.</p>
                    </div>
                </div>

                <div class="about-mission">
                    <h2>Nuestra <span>Misión</span></h2>
                    <p>En Cine Xperience creemos que cada película merece ser vivida como una experiencia completa. Trabajamos para reunir en un solo lugar una cuidadosa selección de títulos de todos los géneros y épocas, para que puedas disfrutar del séptimo arte sin complicaciones. Nuestro catálogo crece constantemente, siempre enfocado en ofrecerte la mejor calidad disponible.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<nav class="bottom-nav">
    <a class="active" onclick="showPage('home')"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a onclick="window.location.href='peliculas.php'"><i class="fa-solid fa-film"></i><span>Películas</span></a>
    <a onclick="showPage('about')"><i class="fa-solid fa-circle-info"></i><span>Quiénes Somos</span></a>
</nav>

<script>
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => { if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key.toUpperCase())) || (e.ctrlKey && ['U','S','P'].includes(e.key.toUpperCase()))) e.preventDefault(); });
document.addEventListener('dragstart', e => { if (e.target.tagName === 'IMG') e.preventDefault(); });

function showPage(page) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById('page-' + page).classList.add('active');
    
    document.querySelectorAll('.bottom-nav a').forEach(a => a.classList.remove('active'));
    if (page === 'home') document.querySelectorAll('.bottom-nav a')[0].classList.add('active');
    else if (page === 'peliculas') { window.location.href = 'peliculas.php'; return; }
    else if (page === 'about') document.querySelectorAll('.bottom-nav a')[2].classList.add('active');
    
    window.scrollTo(0, 0);
}

// Slider automático
<?php if (!empty($sliderItems)): ?>
const sliderWrapper = document.getElementById('sliderWrapper');
const sliderDots = document.getElementById('sliderDots');
const slides = document.querySelectorAll('.slider-slide');
const totalSlides = slides.length;
let currentSlide = 0;
let slideInterval;

for (let i = 0; i < totalSlides; i++) {
    const dot = document.createElement('span');
    dot.classList.add('slider-dot');
    dot.addEventListener('click', () => goToSlide(i));
    sliderDots.appendChild(dot);
}
const dots = document.querySelectorAll('.slider-dot');

function goToSlide(index) {
    currentSlide = index;
    sliderWrapper.style.transform = `translateX(-${currentSlide * 100}%)`;
    dots.forEach((dot, i) => dot.classList.toggle('active', i === currentSlide));
}

function nextSlide() { goToSlide((currentSlide + 1) % totalSlides); }
function startSlider() { if (totalSlides > 1) slideInterval = setInterval(nextSlide, 4000); }
function stopSlider() { clearInterval(slideInterval); }

goToSlide(0);
startSlider();

const sliderContainer = document.querySelector('.slider-container');
if (sliderContainer) {
    sliderContainer.addEventListener('mouseenter', stopSlider);
    sliderContainer.addEventListener('mouseleave', startSlider);
}
document.addEventListener('visibilitychange', () => document.hidden ? stopSlider() : startSlider());
<?php endif; ?>

// Loading overlay
const overlay = document.getElementById('pageLoadingOverlay');
const ploName = document.getElementById('ploMovieName');

document.addEventListener('click', function(e) {
    const card = e.target.closest('.movie-card-small') || e.target.closest('.slider-slide a');
    if (!card) return;
    e.preventDefault();
    const name = card.dataset.name || card.querySelector('img')?.alt || 'Cargando...';
    const href = card.getAttribute('href');
    ploName.textContent = name;
    overlay.classList.add('active');
    setTimeout(() => { window.location.href = href; }, 600);
});

<?php if (BLOCK_DEV_TOOLS): ?>
setInterval(() => { if (window.outerWidth - window.innerWidth > 160 || window.outerHeight - window.innerHeight > 160) document.body.innerHTML = '<div style="position:fixed;inset:0;background:#08080b;color:#e63030;display:flex;align-items:center;justify-content:center;">Acceso restringido</div>'; }, 1000);
<?php endif; ?>

console.log('=== CINE XPERIENCE ===');
console.log('Slider items:', <?= json_encode($sliderItems) ?>);
console.log('Secciones:', <?= json_encode($secciones) ?>);
</script>
</body>
</html>