<?php
require_once 'config.php';

if (!verificarAccesoGestion()) {
    mostrarFormularioLogin();
    exit;
}

$pdo = conectarBD();
$mensaje = '';
$tipoMensaje = '';

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    // Guardar nueva película
    if ($accion === 'guardar') {
        $nombre = trim($_POST['nombre'] ?? '');
        $poster = trim($_POST['poster'] ?? '');
        $anio = trim($_POST['anio'] ?? '');
        $urlFrame = trim($_POST['url_iframe'] ?? '');
        $tmdbId = filter_var($_POST['tmdb_id'] ?? '', FILTER_VALIDATE_INT);
        $mediaType = in_array($_POST['media_type'] ?? '', ['movie', 'tv']) ? $_POST['media_type'] : 'movie';
        $sinopsis = trim($_POST['sinopsis'] ?? '');
        $reparto = trim($_POST['reparto'] ?? '');
        $duracion = trim($_POST['duracion'] ?? '');
        $genero = trim($_POST['genero'] ?? '');
        
        if ($nombre && $poster && $anio && $urlFrame) {
            $stmt = $pdo->prepare("INSERT INTO iframes (nombre, poster, anio, url_iframe, tmdb_id, media_type, sinopsis, reparto, duracion, genero) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $poster, $anio, $urlFrame, $tmdbId ?: null, $mediaType, $sinopsis, $reparto, $duracion, $genero]);
            $mensaje = 'Película agregada correctamente.';
            $tipoMensaje = 'success';
        } else {
            $mensaje = 'Todos los campos obligatorios deben completarse.';
            $tipoMensaje = 'error';
        }
    }
    
    // Editar película
    if ($accion === 'editar' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $nombre = trim($_POST['nombre'] ?? '');
        $poster = trim($_POST['poster'] ?? '');
        $anio = trim($_POST['anio'] ?? '');
        $urlFrame = trim($_POST['url_iframe'] ?? '');
        $tmdbId = filter_var($_POST['tmdb_id'] ?? '', FILTER_VALIDATE_INT);
        $mediaType = in_array($_POST['media_type'] ?? '', ['movie', 'tv']) ? $_POST['media_type'] : 'movie';
        $sinopsis = trim($_POST['sinopsis'] ?? '');
        $reparto = trim($_POST['reparto'] ?? '');
        $duracion = trim($_POST['duracion'] ?? '');
        $genero = trim($_POST['genero'] ?? '');
        
        if ($nombre && $poster && $anio && $urlFrame) {
            $stmt = $pdo->prepare("UPDATE iframes SET nombre=?, poster=?, anio=?, url_iframe=?, tmdb_id=?, media_type=?, sinopsis=?, reparto=?, duracion=?, genero=? WHERE id=?");
            $stmt->execute([$nombre, $poster, $anio, $urlFrame, $tmdbId ?: null, $mediaType, $sinopsis, $reparto, $duracion, $genero, $id]);
            $mensaje = 'Película actualizada correctamente.';
            $tipoMensaje = 'success';
        }
    }
    
    // Eliminar película
    if ($accion === 'eliminar' && !empty($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM seccion_items WHERE iframe_id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $stmt = $pdo->prepare("DELETE FROM iframes WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $mensaje = 'Película eliminada correctamente.';
        $tipoMensaje = 'info';
    }
    
    // Crear sección - CORREGIDO
    if ($accion === 'crear_seccion') {
        $nombre = trim($_POST['nombre_seccion'] ?? '');
        $tipo = $_POST['tipo_seccion'] ?? 'section';
        
        // Asegurar que el tipo sea válido
        if (!in_array($tipo, ['section', 'slider'])) {
            $tipo = 'section';
        }
        
        if ($nombre) {
            try {
                $stmt = $pdo->prepare("INSERT INTO secciones (nombre, tipo, orden) VALUES (?, ?, 0)");
                $stmt->execute([$nombre, $tipo]);
                $mensaje = 'Sección "' . htmlspecialchars($nombre) . '" creada correctamente como ' . ($tipo === 'slider' ? 'Slider' : 'Sección normal') . '.';
                $tipoMensaje = 'success';
            } catch (PDOException $e) {
                $mensaje = 'Error al crear la sección: ' . $e->getMessage();
                $tipoMensaje = 'error';
            }
        } else {
            $mensaje = 'El nombre de la sección es obligatorio.';
            $tipoMensaje = 'error';
        }
    }
    
    // Eliminar sección
    if ($accion === 'eliminar_seccion' && !empty($_POST['seccion_id'])) {
        $stmt = $pdo->prepare("DELETE FROM seccion_items WHERE seccion_id = ?");
        $stmt->execute([(int)$_POST['seccion_id']]);
        $stmt = $pdo->prepare("DELETE FROM secciones WHERE id = ?");
        $stmt->execute([(int)$_POST['seccion_id']]);
        $mensaje = 'Sección eliminada correctamente.';
        $tipoMensaje = 'info';
    }
    
    // Asignar items a sección
    if ($accion === 'asignar_items' && !empty($_POST['seccion_id'])) {
        $seccionId = (int)$_POST['seccion_id'];
        $items = $_POST['items'] ?? [];
        
        $seccion = $pdo->prepare("SELECT tipo, nombre FROM secciones WHERE id = ?");
        $seccion->execute([$seccionId]);
        $secData = $seccion->fetch();
        
        if (!$secData) {
            $mensaje = 'La sección no existe.';
            $tipoMensaje = 'error';
        } else {
            $tipo = $secData['tipo'] ?: 'section'; // Si está vacío, asumir 'section'
            $maxItems = $tipo === 'slider' ? 5 : 10;
            
            if (count($items) > $maxItems) {
                $mensaje = "Máximo $maxItems items permitidos para esta sección.";
                $tipoMensaje = 'error';
            } else {
                try {
                    // Eliminar items existentes
                    $pdo->prepare("DELETE FROM seccion_items WHERE seccion_id = ?")->execute([$seccionId]);
                    
                    // Insertar nuevos items
                    if (!empty($items)) {
                        $stmt = $pdo->prepare("INSERT INTO seccion_items (seccion_id, iframe_id, orden) VALUES (?, ?, ?)");
                        foreach ($items as $orden => $iframeId) {
                            $stmt->execute([$seccionId, (int)$iframeId, $orden]);
                        }
                    }
                    $mensaje = 'Items asignados correctamente a "' . htmlspecialchars($secData['nombre']) . '".';
                    $tipoMensaje = 'success';
                } catch (PDOException $e) {
                    $mensaje = 'Error al asignar items: ' . $e->getMessage();
                    $tipoMensaje = 'error';
                }
            }
        }
    }
}

// Obtener datos para la vista
$peliculas = $pdo->query("SELECT * FROM iframes ORDER BY fecha_creacion DESC")->fetchAll();

// Obtener secciones con conteo de items - CORREGIDO para manejar tipo vacío
$secciones = $pdo->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM seccion_items WHERE seccion_id = s.id) as total_items
    FROM secciones s 
    ORDER BY s.orden, s.id
")->fetchAll();

// Corregir secciones con tipo vacío (parche automático)
foreach ($secciones as $sec) {
    if (empty($sec['tipo'])) {
        $pdo->prepare("UPDATE secciones SET tipo = 'section' WHERE id = ?")->execute([$sec['id']]);
    }
}
// Recargar secciones después de corregir
$secciones = $pdo->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM seccion_items WHERE seccion_id = s.id) as total_items
    FROM secciones s 
    ORDER BY s.orden, s.id
")->fetchAll();

// Película a editar
$editItem = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM iframes WHERE id = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $editItem = $stmt->fetch();
}

// Sección a editar items
$seccionEdit = null;
$itemsSeccion = [];
if (isset($_GET['seccion']) && is_numeric($_GET['seccion'])) {
    $stmt = $pdo->prepare("SELECT * FROM secciones WHERE id = ?");
    $stmt->execute([(int)$_GET['seccion']]);
    $seccionEdit = $stmt->fetch();
    if ($seccionEdit) {
        $stmt = $pdo->prepare("SELECT iframe_id FROM seccion_items WHERE seccion_id = ? ORDER BY orden");
        $stmt->execute([$seccionEdit['id']]);
        $itemsSeccion = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Gestión - Cine Xperience</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
:root {
    --red: #e63030; --red-dim: #c02020; --red-glow: rgba(230,48,48,0.18);
    --ok: #22c55e; --ok-bg: rgba(34,197,94,0.10); --info: #38bdf8;
    --surface: #08080b; --surface-2: #0f0f14; --surface-3: #16161e; --surface-4: #1d1d28;
    --border: rgba(255,255,255,0.06); --border-red: rgba(230,48,48,0.28);
    --text: #f0f0f8; --text-2: #a0a0b8; --text-3: #606075;
    --font-display: 'Bebas Neue', sans-serif; --font-body: 'DM Sans', sans-serif;
}
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
body { font-family: var(--font-body); background: var(--surface); color: var(--text); min-height: 100vh; padding-bottom: 40px; }
.container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }

.header { position: sticky; top: 0; z-index: 200; background: rgba(8,8,11,0.95); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-red); padding: 14px 0; margin-bottom: 30px; }
.header-content { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.header-brand { display: flex; align-items: center; gap: 14px; }
.logo-mark { width: 44px; height: 44px; border-radius: 14px; background: linear-gradient(135deg, var(--red) 0%, #8c1515 100%); display: flex; align-items: center; justify-content: center; }
.logo-mark i { font-size: 19px; color: #fff; }
.header-title h1 { font-family: var(--font-display); font-size: 20px; letter-spacing: 2px; }
.header-title h1 span { color: var(--red); }
.btn-logout { padding: 8px 18px; background: rgba(230,48,48,0.12); border: 1px solid var(--border-red); border-radius: 10px; color: #f87171; text-decoration: none; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-logout:hover { background: rgba(230,48,48,0.22); }

.alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; animation: slideDown 0.3s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.alert-success { background: var(--ok-bg); border: 1px solid rgba(34,197,94,0.28); color: var(--ok); }
.alert-error { background: rgba(230,48,48,0.12); border: 1px solid rgba(230,48,48,0.30); color: #f87171; }
.alert-info { background: rgba(56,189,248,0.10); border: 1px solid rgba(56,189,248,0.28); color: var(--info); }

.layout { display: grid; grid-template-columns: 1fr; gap: 30px; }
@media (min-width: 1100px) { .layout { grid-template-columns: 420px 1fr; } }

.panel-card { background: var(--surface-2); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; margin-bottom: 24px; }
.panel-header { padding: 18px 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; font-weight: 600; }
.panel-header i { color: var(--red); }
.panel-body { padding: 22px; }

.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-3); margin-bottom: 6px; }
.form-input { width: 100%; padding: 11px 14px; background: var(--surface-3); border: 1px solid var(--border); border-radius: 10px; font-size: 14px; color: var(--text); outline: none; }
.form-input:focus { border-color: var(--red); }
textarea.form-input { min-height: 80px; resize: vertical; }
.btn { padding: 11px 20px; border: none; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 7px; text-decoration: none; transition: all 0.2s; }
.btn-primary { background: var(--red); color: #fff; }
.btn-primary:hover { background: #f03535; transform: translateY(-2px); }
.btn-ghost { background: var(--surface-3); border: 1px solid var(--border); color: var(--text-2); }
.btn-ghost:hover { background: var(--surface-4); color: var(--text); }
.btn-danger { background: rgba(230,48,48,0.15); border: 1px solid var(--border-red); color: #f87171; }
.btn-danger:hover { background: rgba(230,48,48,0.25); }
.btn-sm { padding: 7px 12px; font-size: 12px; }

.table-list { width: 100%; border-collapse: collapse; }
.table-list th { text-align: left; padding: 12px; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-3); border-bottom: 1px solid var(--border); }
.table-list td { padding: 12px; border-bottom: 1px solid var(--border); font-size: 14px; }
.table-list tr:hover { background: rgba(230,48,48,0.05); }
.actions { display: flex; gap: 8px; }

.section-card { background: var(--surface-2); border: 1px solid var(--border); border-radius: 14px; padding: 16px; margin-bottom: 16px; transition: border-color 0.2s; }
.section-card:hover { border-color: var(--border-red); }
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.section-title { font-weight: 600; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.section-badge { font-size: 10px; padding: 2px 8px; border-radius: 20px; background: var(--surface-3); color: var(--text-3); }
.section-badge.slider { background: rgba(230,48,48,0.15); color: #f87171; }
.section-badge.section { background: rgba(56,189,248,0.12); color: #38bdf8; }
.section-badge.vacio { background: rgba(245,158,11,0.15); color: #fbbf24; }
.checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; max-height: 350px; overflow-y: auto; padding: 12px; background: var(--surface-3); border-radius: 10px; }
.checkbox-item { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }
.checkbox-item input { accent-color: var(--red); cursor: pointer; }

.autocomplete-wrap { position: relative; }
.ac-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: var(--surface-3); border: 1px solid var(--border-red); border-radius: 12px; max-height: 300px; overflow-y: auto; z-index: 100; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
.ac-dropdown.active { display: block; }
.ac-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; cursor: pointer; border-bottom: 1px solid var(--border); }
.ac-item:hover { background: rgba(230,48,48,0.10); }
.ac-item img { width: 38px; height: 57px; border-radius: 6px; object-fit: cover; }
.ac-info { flex: 1; }
.ac-title { font-size: 13px; font-weight: 600; }
.ac-meta { font-size: 11px; color: var(--text-3); }
.ac-loading { padding: 20px; text-align: center; color: var(--text-3); }

.modal-overlay { display: none; position: fixed; inset: 0; z-index: 400; background: rgba(4,4,6,0.95); backdrop-filter: blur(20px); justify-content: center; align-items: center; }
.modal-overlay.active { display: flex; }
.modal-box { background: var(--surface-2); border: 1px solid var(--border-red); border-radius: 20px; width: 90%; max-width: 450px; padding: 30px; text-align: center; animation: modalIn 0.3s ease; }
@keyframes modalIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
.modal-icon { font-size: 48px; color: var(--red); margin-bottom: 20px; }
.modal-title { font-size: 18px; font-weight: 700; margin-bottom: 12px; }
.modal-text { color: var(--text-2); margin-bottom: 24px; }
.modal-actions { display: flex; gap: 10px; }
.modal-actions .btn { flex: 1; }

.tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 1px solid var(--border); }
.tab-btn { padding: 12px 24px; background: none; border: none; color: var(--text-3); font-size: 14px; font-weight: 600; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
.tab-btn:hover { color: var(--text-2); }
.tab-btn.active { color: var(--red); border-bottom-color: var(--red); }
.tab-content { display: none; }
.tab-content.active { display: block; }

.empty-message { text-align: center; padding: 40px; color: var(--text-3); }
.empty-message i { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
.info-note { background: rgba(56,189,248,0.08); border: 1px solid rgba(56,189,248,0.2); border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: var(--text-2); display: flex; align-items: center; gap: 10px; }
.info-note i { color: #38bdf8; }
.info-note.warning { background: rgba(245,158,11,0.08); border-color: rgba(245,158,11,0.3); }
.info-note.warning i { color: #f59e0b; }

.selection-counter { font-size: 12px; color: var(--text-2); margin-bottom: 12px; padding: 8px 12px; background: var(--surface-4); border-radius: 8px; display: inline-block; }
.selection-counter.warning { color: #fbbf24; }
.selection-counter.error { color: #f87171; }

.fix-button { margin-top: 16px; padding: 8px 12px; background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); border-radius: 8px; }
.fix-button button { background: #f59e0b; color: #000; border: none; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; }
</style>
</head>
<body>
<div class="modal-overlay" id="logoutModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
        <h3 class="modal-title">Cerrar sesión</h3>
        <p class="modal-text">¿Estás seguro de que deseas salir del panel de gestión?</p>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeLogoutModal()">Cancelar</button>
            <a href="?logout=1" class="btn btn-primary">Salir</a>
        </div>
    </div>
</div>

<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h3 class="modal-title">Eliminar película</h3>
        <p class="modal-text" id="deleteModalText">¿Confirmas la eliminación de esta película?</p>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeDeleteModal()">Cancelar</button>
            <button class="btn btn-danger" id="confirmDeleteBtn">Eliminar</button>
        </div>
    </div>
</div>

<header class="header">
    <div class="container">
        <div class="header-content">
            <div class="header-brand">
                <div class="logo-mark"><i class="fa-solid fa-clapperboard"></i></div>
                <div class="header-title">
                    <h1>Cine <span>Xperience</span></h1>
                    <p style="font-size:10px;color:var(--text-3);letter-spacing:1px;">PANEL DE GESTIÓN</p>
                </div>
            </div>
            <div>
                <a href="index.php" class="btn btn-ghost btn-sm" style="margin-right:10px;"><i class="fa-solid fa-eye"></i> Ver sitio</a>
                <button class="btn-logout" onclick="openLogoutModal()"><i class="fa-solid fa-right-from-bracket"></i> Salir</button>
            </div>
        </div>
    </div>
</header>

<main>
    <div class="container">
        <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?>">
            <i class="fa-solid fa-<?= $tipoMensaje === 'success' ? 'circle-check' : ($tipoMensaje === 'error' ? 'circle-exclamation' : 'circle-info') ?>"></i>
            <span><?= $mensaje ?></span>
        </div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('peliculas')"><i class="fa-solid fa-film"></i> Películas</button>
            <button class="tab-btn" onclick="showTab('secciones')"><i class="fa-solid fa-layer-group"></i> Secciones y Sliders</button>
        </div>

        <!-- TAB PELÍCULAS -->
        <div class="tab-content active" id="tab-peliculas">
            <div class="layout">
                <aside>
                    <div class="panel-card">
                        <div class="panel-header"><i class="fa-solid fa-<?= $editItem ? 'pen-to-square' : 'circle-plus' ?>"></i> <?= $editItem ? 'Editar Película' : 'Nueva Película' ?></div>
                        <div class="panel-body">
                            <form method="POST" id="formPelicula">
                                <input type="hidden" name="accion" value="<?= $editItem ? 'editar' : 'guardar' ?>">
                                <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
                                <input type="hidden" name="tmdb_id" id="tmdb_id" value="<?= htmlspecialchars($editItem['tmdb_id'] ?? '') ?>">
                                <input type="hidden" name="media_type" id="media_type" value="<?= htmlspecialchars($editItem['media_type'] ?? 'movie') ?>">
                                
                                <div class="form-group">
                                    <label class="form-label"><i class="fa-solid fa-magnifying-glass"></i> Buscar en TMDB</label>
                                    <div class="autocomplete-wrap">
                                        <input type="text" id="search_tmdb" class="form-input" placeholder="Escribe el título de la película..." autocomplete="off">
                                        <div class="ac-dropdown" id="autocompleteDropdown"></div>
                                    </div>
                                </div>
                                <div class="form-group"><label class="form-label">Título <span style="color:var(--red);">*</span></label><input type="text" name="nombre" id="nombre" class="form-input" value="<?= htmlspecialchars($editItem['nombre'] ?? '') ?>" required></div>
                                <div class="form-group"><label class="form-label">Año <span style="color:var(--red);">*</span></label><input type="number" name="anio" id="anio" class="form-input" value="<?= htmlspecialchars($editItem['anio'] ?? '') ?>" required></div>
                                <div class="form-group"><label class="form-label">Poster URL <span style="color:var(--red);">*</span></label><input type="url" name="poster" id="poster" class="form-input" value="<?= htmlspecialchars($editItem['poster'] ?? '') ?>" required></div>
                                <div class="form-group"><label class="form-label">URL Iframe <span style="color:var(--red);">*</span></label><input type="url" name="url_iframe" id="url_iframe" class="form-input" value="<?= htmlspecialchars($editItem['url_iframe'] ?? '') ?>" placeholder="https://..." required></div>
                                <div class="form-group"><label class="form-label">Sinopsis</label><textarea name="sinopsis" id="sinopsis" class="form-input" placeholder="Descripción de la película..."><?= htmlspecialchars($editItem['sinopsis'] ?? '') ?></textarea></div>
                                <div class="form-group"><label class="form-label">Reparto</label><input type="text" name="reparto" id="reparto" class="form-input" value="<?= htmlspecialchars($editItem['reparto'] ?? '') ?>" placeholder="Actor 1, Actor 2..."></div>
                                <div class="form-group"><label class="form-label">Duración</label><input type="text" name="duracion" id="duracion" class="form-input" value="<?= htmlspecialchars($editItem['duracion'] ?? '') ?>" placeholder="2h 15min"></div>
                                <div class="form-group"><label class="form-label">Género(s)</label><input type="text" name="genero" id="genero" class="form-input" value="<?= htmlspecialchars($editItem['genero'] ?? '') ?>" placeholder="Acción, Aventura, Comedia..."></div>
                                <div style="display:flex; gap:10px; margin-top:20px;">
                                    <?php if ($editItem): ?><a href="gestion.php" class="btn btn-ghost" style="flex:1;">Cancelar</a><?php endif; ?>
                                    <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </aside>
                
                <section>
                    <div class="panel-card">
                        <div class="panel-header"><i class="fa-solid fa-list"></i> Películas Registradas <span style="margin-left:auto; font-size:12px; background:var(--surface-3); padding:4px 12px; border-radius:20px;"><?= count($peliculas) ?> total</span></div>
                        <div class="panel-body" style="padding:0;">
                            <?php if (empty($peliculas)): ?>
                            <div class="empty-message">
                                <i class="fa-regular fa-film"></i>
                                <p>No hay películas registradas</p>
                                <p style="font-size:12px; margin-top:8px;">Agrega tu primera película usando el formulario</p>
                            </div>
                            <?php else: ?>
                            <table class="table-list">
                                <thead><tr><th>Título</th><th>Año</th><th>Género</th><th style="width:120px;">Acciones</th></tr></thead>
                                <tbody>
                                    <?php foreach ($peliculas as $p): ?>
                                    <tr>
                                        <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($p['nombre']) ?></td>
                                        <td><?= $p['anio'] ?></td>
                                        <td><?= htmlspecialchars($p['genero'] ?: '—') ?></td>
                                        <td class="actions">
                                            <a href="?editar=<?= $p['id'] ?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-pen"></i></a>
                                            <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nombre'])) ?>')"><i class="fa-solid fa-trash"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- TAB SECCIONES -->
        <div class="tab-content" id="tab-secciones">
            <div class="info-note">
                <i class="fa-solid fa-circle-info"></i>
                <span><strong>Sección normal:</strong> Hasta 10 películas mostradas en fila horizontal. <strong>Slider:</strong> Hasta 5 películas con desplazamiento automático.</span>
            </div>
            
            <?php if (empty($peliculas)): ?>
            <div class="info-note warning">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><strong>No hay películas registradas.</strong> Primero debes agregar películas antes de poder crear secciones.</span>
            </div>
            <?php endif; ?>
            
            <div class="layout">
                <aside>
                    <div class="panel-card">
                        <div class="panel-header"><i class="fa-solid fa-plus"></i> Crear Nueva Sección</div>
                        <div class="panel-body">
                            <form method="POST">
                                <input type="hidden" name="accion" value="crear_seccion">
                                <div class="form-group">
                                    <label class="form-label">Nombre de la sección</label>
                                    <input type="text" name="nombre_seccion" class="form-input" placeholder="Ej: Estrenos, Más Vistas, Acción..." required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tipo de sección</label>
                                    <select name="tipo_seccion" class="form-input">
                                        <option value="section">📋 Sección normal (máx 10 películas)</option>
                                        <option value="slider">🎬 Slider automático (máx 5 películas)</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-plus"></i> Crear Sección</button>
                            </form>
                            <p style="font-size:11px; color:var(--text-3); margin-top:16px; text-align:center;">
                                <i class="fa-regular fa-lightbulb"></i> Las secciones aparecerán automáticamente en la página principal
                            </p>
                        </div>
                    </div>
                </aside>
                
                <section>
                    <div class="panel-card">
                        <div class="panel-header"><i class="fa-solid fa-layer-group"></i> Secciones Creadas</div>
                        <div class="panel-body">
                            <?php if (empty($secciones)): ?>
                            <div class="empty-message">
                                <i class="fa-regular fa-folder-open"></i>
                                <p>No hay secciones creadas</p>
                                <p style="font-size:12px; margin-top:8px;">Crea tu primera sección usando el formulario</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($secciones as $sec): 
                                $count = $sec['total_items'];
                                $tipoSec = $sec['tipo'] ?: 'section';
                                $max = $tipoSec === 'slider' ? 5 : 10;
                                $isEditing = $seccionEdit && $seccionEdit['id'] == $sec['id'];
                            ?>
                            <div class="section-card" style="<?= $isEditing ? 'border-color: var(--red);' : '' ?>">
                                <div class="section-header">
                                    <span class="section-title">
                                        <?= htmlspecialchars($sec['nombre']) ?>
                                        <?php if ($tipoSec === 'slider'): ?>
                                            <span class="section-badge slider">🎬 Slider</span>
                                        <?php elseif ($tipoSec === 'section'): ?>
                                            <span class="section-badge section">📋 Sección</span>
                                        <?php else: ?>
                                            <span class="section-badge vacio">⚠️ Sin tipo</span>
                                        <?php endif; ?>
                                        <span class="section-badge" style="background:var(--surface-4);"><?= $count ?>/<?= $max ?></span>
                                    </span>
                                    <div style="display:flex; gap:6px;">
                                        <a href="?seccion=<?= $sec['id'] ?>#tab-secciones" class="btn btn-ghost btn-sm"><i class="fa-solid fa-list-check"></i> Asignar</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar la sección "<?= htmlspecialchars(addslashes($sec['nombre'])) ?>"?')">
                                            <input type="hidden" name="accion" value="eliminar_seccion">
                                            <input type="hidden" name="seccion_id" value="<?= $sec['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                                
                                <?php if ($isEditing): ?>
                                <form method="POST" style="margin-top:16px;" id="asignarForm_<?= $sec['id'] ?>">
                                    <input type="hidden" name="accion" value="asignar_items">
                                    <input type="hidden" name="seccion_id" value="<?= $sec['id'] ?>">
                                    <p style="font-size:12px; color:var(--text-2); margin-bottom:12px;">
                                        <i class="fa-solid fa-hand-pointer"></i> Selecciona las películas para esta sección (máximo <?= $max ?>)
                                    </p>
                                    <div id="selectionCounter_<?= $sec['id'] ?>" class="selection-counter">
                                        <span id="selectedCount_<?= $sec['id'] ?>"><?= count($itemsSeccion) ?></span> de <?= $max ?> seleccionadas
                                    </div>
                                    <div class="checkbox-grid" id="checkboxGrid_<?= $sec['id'] ?>">
                                        <?php foreach ($peliculas as $p): ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="items[]" value="<?= $p['id'] ?>" <?= in_array($p['id'], $itemsSeccion) ? 'checked' : '' ?> data-section="<?= $sec['id'] ?>">
                                            <span><?= htmlspecialchars($p['nombre']) ?> <span style="color:var(--text-3);">(<?= $p['anio'] ?>)</span></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div style="display:flex; gap:10px; margin-top:16px;">
                                        <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fa-solid fa-save"></i> Guardar asignación</button>
                                        <a href="gestion.php#tab-secciones" class="btn btn-ghost" style="flex:1;">Cancelar</a>
                                    </div>
                                </form>
                                <?php elseif ($count > 0): ?>
                                <div style="margin-top:12px; padding:10px; background:var(--surface-3); border-radius:8px;">
                                    <p style="font-size:11px; color:var(--text-3); margin-bottom:6px;">PELÍCULAS ASIGNADAS:</p>
                                    <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                        <?php 
                                        $stmtItems = $pdo->prepare("SELECT i.nombre FROM seccion_items si JOIN iframes i ON si.iframe_id = i.id WHERE si.seccion_id = ? ORDER BY si.orden LIMIT 5");
                                        $stmtItems->execute([$sec['id']]);
                                        $items = $stmtItems->fetchAll();
                                        foreach ($items as $item): ?>
                                        <span style="font-size:11px; background:var(--surface-4); padding:2px 8px; border-radius:4px; color:var(--text-2);"><?= htmlspecialchars($item['nombre']) ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($count > 5): ?>
                                        <span style="font-size:11px; color:var(--text-3);">+<?= $count - 5 ?> más</span>
                                        <?php endif; ?>
                                    </div>
                                    <p style="font-size:10px; color:var(--ok); margin-top:8px;">
                                        <i class="fa-solid fa-check-circle"></i> Esta sección se mostrará en la página principal
                                    </p>
                                </div>
                                <?php else: ?>
                                <div style="margin-top:12px; padding:10px; background:var(--surface-3); border-radius:8px;">
                                    <p style="font-size:12px; color:var(--text-3); font-style:italic;">
                                        <i class="fa-regular fa-circle"></i> Sin películas asignadas
                                    </p>
                                    <p style="font-size:10px; color:var(--text-3); margin-top:4px;">
                                        Haz clic en "Asignar" para agregar películas a esta sección
                                    </p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (empty($sec['tipo'])): ?>
                                <div class="fix-button">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="accion" value="crear_seccion">
                                        <input type="hidden" name="nombre_seccion" value="<?= htmlspecialchars($sec['nombre']) ?>">
                                        <input type="hidden" name="tipo_seccion" value="section">
                                        <button type="submit" style="background:#f59e0b; color:#000; border:none; padding:4px 10px; border-radius:4px; font-size:11px; cursor:pointer;">
                                            <i class="fa-solid fa-wrench"></i> Corregir tipo (establecer como Sección)
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>

<script>
function showTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    if (tab === 'peliculas') {
        document.querySelectorAll('.tab-btn')[0].classList.add('active');
        document.getElementById('tab-peliculas').classList.add('active');
        window.location.hash = '';
    } else {
        document.querySelectorAll('.tab-btn')[1].classList.add('active');
        document.getElementById('tab-secciones').classList.add('active');
        window.location.hash = 'tab-secciones';
    }
}

if (window.location.hash === '#tab-secciones') { showTab('secciones'); }

function openLogoutModal() { document.getElementById('logoutModal').classList.add('active'); }
function closeLogoutModal() { document.getElementById('logoutModal').classList.remove('active'); }

let deleteId = null;
function confirmDelete(id, nombre) {
    deleteId = id;
    document.getElementById('deleteModalText').textContent = `¿Eliminar "${nombre}"? Esta acción es irreversible y también eliminará la película de todas las secciones.`;
    document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); deleteId = null; }
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (deleteId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="' + deleteId + '">';
        document.body.appendChild(form);
        form.submit();
    }
});

document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active')); });

// Contador de selección para checkboxes
document.querySelectorAll('[id^="checkboxGrid_"]').forEach(grid => {
    const sectionId = grid.id.replace('checkboxGrid_', '');
    const checkboxes = grid.querySelectorAll('input[type="checkbox"]');
    const counterSpan = document.getElementById(`selectedCount_${sectionId}`);
    const counterDiv = document.getElementById(`selectionCounter_${sectionId}`);
    const maxItems = document.querySelector(`#asignarForm_${sectionId}`) ? 
        (document.querySelector(`#asignarForm_${sectionId}`).closest('.section-card').querySelector('.section-badge.slider') ? 5 : 10) : 10;
    
    function updateCounter() {
        const checked = grid.querySelectorAll('input[type="checkbox"]:checked').length;
        if (counterSpan) counterSpan.textContent = checked;
        if (counterDiv) {
            counterDiv.classList.remove('warning', 'error');
            if (checked >= maxItems) {
                counterDiv.classList.add('warning');
            }
            if (checked > maxItems) {
                counterDiv.classList.add('error');
            }
        }
    }
    
    checkboxes.forEach(cb => cb.addEventListener('change', updateCounter));
    updateCounter();
});

// Autocomplete TMDB
const searchEl = document.getElementById('search_tmdb');
const dropdown = document.getElementById('autocompleteDropdown');
let debounceTimer;

searchEl?.addEventListener('input', function() {
    clearTimeout(debounceTimer);
    const q = this.value.trim();
    if (q.length < 2) { dropdown.classList.remove('active'); return; }
    dropdown.innerHTML = '<div class="ac-loading"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</div>';
    dropdown.classList.add('active');
    debounceTimer = setTimeout(async () => {
        try {
            const r = await fetch('?action=search&q=' + encodeURIComponent(q));
            const d = await r.json();
            const items = (d.results || []).slice(0, 8);
            if (!items.length) {
                dropdown.innerHTML = '<div class="ac-loading">Sin resultados</div>';
            } else {
                dropdown.innerHTML = items.map(it => `
                    <div class="ac-item" data-id="${it.id}" data-type="${it.type}" data-title="${it.title.replace(/"/g, '&quot;')}" data-year="${it.year}" data-poster="${it.poster_url || ''}">
                        ${it.poster_url ? `<img src="${it.poster_url}" alt="">` : '<div style="width:38px;height:57px;background:var(--surface-4);border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--text-3);"><i class="fa-regular fa-image"></i></div>'}
                        <div class="ac-info"><div class="ac-title">${it.title}</div><div class="ac-meta">${it.year} • ${it.type === 'tv' ? 'Serie' : 'Película'} ${it.vote_average > 0 ? '★ ' + it.vote_average.toFixed(1) : ''}</div></div>
                    </div>
                `).join('');
                dropdown.querySelectorAll('.ac-item').forEach(el => el.addEventListener('click', () => selectItem(el)));
            }
        } catch(e) { dropdown.innerHTML = '<div class="ac-loading">Error de conexión</div>'; }
    }, 400);
});

async function selectItem(el) {
    const id = el.dataset.id, type = el.dataset.type;
    document.getElementById('nombre').value = el.dataset.title;
    document.getElementById('anio').value = el.dataset.year;
    document.getElementById('poster').value = el.dataset.poster;
    document.getElementById('tmdb_id').value = id;
    document.getElementById('media_type').value = type;
    dropdown.classList.remove('active');
    searchEl.value = '';
    
    try {
        const r = await fetch(`?action=details&id=${id}&type=${type}`);
        const d = await r.json();
        if (!d.error) {
            document.getElementById('sinopsis').value = d.sinopsis || '';
            document.getElementById('reparto').value = d.reparto || '';
            document.getElementById('duracion').value = d.duracion || '';
            document.getElementById('genero').value = d.genres || '';
        }
    } catch(e) {}
}

document.addEventListener('click', e => { if (!e.target.closest('.autocomplete-wrap')) dropdown.classList.remove('active'); });

console.log('Películas cargadas:', <?= count($peliculas) ?>);
console.log('Secciones cargadas:', <?= count($secciones) ?>);
</script>
</body>
</html>