<?php
require_once 'config.php';

$idVer = isset($_GET['ver']) ? (int)$_GET['ver'] : 0;
if (!$idVer) { header("Location: index.php"); exit; }

$pdo = conectarBD();
$stmt = $pdo->prepare("SELECT * FROM iframes WHERE id = ? LIMIT 1");
$stmt->execute([$idVer]);
$item = $stmt->fetch();

if (!$item) { header("Location: index.php"); exit; }

$urlOfuscada = ofuscarUrl($item['url_iframe']);
$token = generarTokenIframe($item['id'], $item['url_iframe']);

$edadRating = null;
if (!empty($item['tmdb_id']) && !empty($tmdbApiKey)) {
    $edadRating = obtenerClasificacionEdadTMDB($item['tmdb_id'], $item['media_type'] ?? 'movie', $tmdbApiKey, $tmdbContentRatingCountries);
}

// Headers que NO revelan información
if (!headers_sent()) {
    header_remove("X-Powered-By");
    header_remove("Server");
    header_remove("X-Frame-Options");
    header_remove("X-Content-Type-Options");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="mobile-web-app-capable" content="yes">
<meta name="format-detection" content="telephone=no">
<meta name="theme-color" content="#08080b">
<title><?= htmlspecialchars($item['nombre']) ?> | Cine Xperience</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
:root{--red:#e63030;--red-dim:#c02020;--red-glow:rgba(230,48,48,0.20);--surface:#08080b;--surface-2:#0f0f14;--surface-3:#16161e;--border:rgba(255,255,255,0.06);--border-red:rgba(230,48,48,0.30);--text:#f0f0f8;--text-2:#a0a0b8;--text-3:#606075;--font-display:'Bebas Neue',sans-serif;--font-body:'DM Sans',sans-serif}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{font-family:var(--font-body);background:var(--surface);color:var(--text);min-height:100vh;user-select:none;-webkit-user-select:none;-webkit-touch-callout:none}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 80% 50% at 50% -10%,rgba(230,48,48,0.10) 0%,transparent 65%);pointer-events:none;z-index:-1}
.container{max-width:1400px;margin:0 auto;padding:0 12px}
@media (min-width:768px){.container{padding:0 24px}}
.main-header{position:sticky;top:0;z-index:200;background:rgba(8,8,11,0.95);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border-bottom:1px solid var(--border-red);padding:10px 0}
.header-content{display:flex;align-items:center;justify-content:space-between}
.logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.logo-mark{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--red) 0%,#8c1515 100%);display:flex;align-items:center;justify-content:center}
.logo-mark i{font-size:15px;color:#fff}
.logo-text h1{font-family:var(--font-display);font-size:18px;letter-spacing:2px;color:var(--text)}
.logo-text h1 span{color:var(--red)}
.btn-back{padding:8px 14px;background:var(--surface-3);border:1px solid var(--border);border-radius:10px;color:var(--text-2);text-decoration:none;font-size:13px;display:flex;align-items:center;gap:6px;transition:all 0.2s}
.btn-back:hover{background:var(--surface-4);color:var(--text)}
.player-page{padding:16px 0 30px}
.info-bar{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px}
.info-poster-thumb{width:45px;height:68px;border-radius:8px;object-fit:cover;border:1px solid var(--border-red)}
.info-text{flex:1}
.info-eyebrow{display:flex;gap:6px;margin-bottom:5px;flex-wrap:wrap}
.info-badge{background:rgba(230,48,48,0.14);border:1px solid rgba(230,48,48,0.32);border-radius:5px;padding:2px 7px;font-size:9px;font-weight:600;text-transform:uppercase;color:#fca5a5}
.info-title{font-family:var(--font-display);font-size:clamp(18px,4vw,26px);letter-spacing:1.5px}
.info-title .year-inline{color:var(--text-3);font-size:0.6em;font-family:var(--font-body);margin-left:6px}
.player-wrap{position:relative;width:100%;background:#000;border-radius:14px;overflow:hidden;border:1px solid var(--border-red);box-shadow:0 20px 50px rgba(0,0,0,0.6)}
.player-aspect{position:relative;padding-bottom:56.25%;height:0}
.player-aspect iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:none;background:#000}
.control-bar{position:absolute;top:10px;right:10px;z-index:20;display:flex;gap:5px;background:rgba(8,8,11,0.85);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(230,48,48,0.35);border-radius:10px;padding:5px;opacity:0;transition:opacity 0.25s}
.player-wrap:hover .control-bar{opacity:1}
.ctrl-btn{width:34px;height:34px;border:none;border-radius:8px;background:var(--surface-3);color:var(--text-2);font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.ctrl-btn:hover{background:var(--red);color:#fff}
.player-loader{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:5;display:flex;flex-direction:column;align-items:center;gap:14px}
.spin-ring{width:44px;height:44px;border:2.5px solid rgba(230,48,48,0.15);border-top-color:var(--red);border-radius:50%;animation:spin 0.8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.player-loader-title{font-family:var(--font-display);font-size:14px;color:var(--text-2);letter-spacing:1px}
.shield-indicator{position:absolute;top:10px;left:10px;z-index:12;display:flex;align-items:center;gap:5px;background:rgba(8,8,11,0.85);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid var(--border-red);border-radius:7px;padding:4px 10px;font-size:10px;font-weight:600;color:#22c55e}
.player-shield{position:absolute;top:0;left:0;right:0;bottom:0;z-index:10;background:transparent;pointer-events:none}
.player-shield.protecting{background:rgba(230,48,48,0.02);pointer-events:auto}
.movie-info-section{margin-top:24px;display:grid;grid-template-columns:1fr;gap:16px}
@media (min-width:768px){.movie-info-section{grid-template-columns:1fr 1fr}}
.info-block{background:var(--surface-2);border:1px solid var(--border);border-radius:14px;padding:16px}
.info-block-title{display:flex;align-items:center;gap:7px;font-size:10px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--red);margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--border)}
.info-block-text{font-size:13px;color:var(--text-2);line-height:1.7}
.duration-val{font-family:var(--font-display);font-size:28px;color:var(--text)}
.toast-stack{position:fixed;bottom:20px;right:16px;left:16px;z-index:600;max-width:320px;margin-left:auto}
.toast-item{background:var(--surface-3);border-left:3px solid var(--red);border-radius:10px;padding:11px 14px;margin-top:6px;font-size:12px;animation:toastSlide 0.3s;box-shadow:0 8px 20px rgba(0,0,0,0.4)}
@keyframes toastSlide{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}
</style>
</head>
<body>
<div class="toast-stack" id="toastStack"></div>

<header class="main-header">
    <div class="container header-content">
        <a href="index.php" class="logo">
            <div class="logo-mark"><i class="fa-solid fa-play"></i></div>
            <div class="logo-text"><h1>Cine <span>Xperience</span></h1></div>
        </a>
        <a href="javascript:history.back()" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</header>

<main>
    <div class="container">
        <div class="player-page">
            <div class="info-bar">
                <?php if (!empty($item['poster'])): ?>
                <img class="info-poster-thumb" src="<?= htmlspecialchars($item['poster']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>">
                <?php endif; ?>
                <div class="info-text">
                    <div class="info-eyebrow">
                        <?php if (!empty($item['tmdb_id'])): ?>
                        <span class="info-badge"><i class="fa-solid <?= $item['media_type'] === 'tv' ? 'fa-tv' : 'fa-film' ?>"></i> <?= $item['media_type'] === 'tv' ? 'Serie' : 'Película' ?></span>
                        <?php endif; ?>
                        <?php if ($edadRating && !empty($edadRating['rating'])): ?>
                        <span class="info-badge"><?= htmlspecialchars($edadRating['rating']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="info-title"><?= htmlspecialchars($item['nombre']) ?> <span class="year-inline"><?= htmlspecialchars($item['anio']) ?></span></div>
                </div>
            </div>

            <div class="player-wrap" id="playerWrap">
                <div class="control-bar" id="controlBar">
                    <button class="ctrl-btn" id="btnFullscreen"><i class="fa-solid fa-expand"></i></button>
                    <button class="ctrl-btn" id="btnReload"><i class="fa-solid fa-rotate-right"></i></button>
                </div>
                <div class="player-aspect">
                    <div class="player-loader" id="playerLoader">
                        <div class="spin-ring"></div>
                        <div class="player-loader-title">CARGANDO...</div>
                    </div>
                    <div class="shield-indicator" id="shieldIndicator"><i class="fa-solid fa-shield-halved"></i> <span id="shieldText">ACTIVO</span></div>
                    <div class="player-shield" id="playerShield"></div>
                    <iframe id="mainIframe" allowfullscreen allow="autoplay; encrypted-media; fullscreen; picture-in-picture; web-share" referrerpolicy="no-referrer" style="display:block;width:100%;height:100%;border:none;background:#000;"></iframe>
                </div>
            </div>

            <?php if (!empty($item['sinopsis']) || !empty($item['reparto']) || !empty($item['duracion'])): ?>
            <div class="movie-info-section">
                <?php if (!empty($item['sinopsis'])): ?>
                <div class="info-block"><div class="info-block-title"><i class="fa-solid fa-align-left"></i> Sinopsis</div><div class="info-block-text"><?= nl2br(htmlspecialchars($item['sinopsis'])) ?></div></div>
                <?php endif; ?>
                <?php if (!empty($item['reparto'])): ?>
                <div class="info-block"><div class="info-block-title"><i class="fa-solid fa-users"></i> Reparto</div><div class="info-block-text"><?= htmlspecialchars($item['reparto']) ?></div></div>
                <?php endif; ?>
                <?php if (!empty($item['duracion'])): ?>
                <div class="info-block"><div class="info-block-title"><i class="fa-regular fa-clock"></i> Duración</div><div class="duration-val"><?= htmlspecialchars($item['duracion']) ?></div></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
(function(){
    "use strict";
    
    // ============================================
    // ULTRA INDETECTABLE - PARCHE COMPLETO
    // ============================================
    
    // 1. Eliminar TODAS las referencias a WebView
    const props = [
        "webkit", "WebView", "chrome", "Android", "webView", "WebViewBridge",
        "ReactNativeWebView", "WKWebView", "JSBridge", "AlipayJSBridge",
        "WeixinJSBridge", "cordova", "PhoneGap", "Capacitor", "Ionic",
        "flutter", "ReactNative", "Expo", "NativeScript"
    ];
    props.forEach(p => { try { delete window[p]; } catch(e) {} });
    
    // 2. Sobrescribir navigator completamente
    const fakeUA = "Mozilla/5.0 (Linux; Android 14; SM-S928B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36";
    const fakePlatform = "Linux armv8l";
    const fakeVendor = "Google Inc.";
    const fakeLanguages = ["es-ES", "es", "en-US", "en"];
    
    const fakeNav = {
        userAgent: fakeUA,
        platform: fakePlatform,
        language: "es-ES",
        languages: fakeLanguages,
        cookieEnabled: true,
        doNotTrack: null,
        hardwareConcurrency: 8,
        maxTouchPoints: 5,
        vendor: fakeVendor,
        vendorSub: "",
        product: "Gecko",
        productSub: "20030107",
        appName: "Netscape",
        appVersion: "5.0 (Linux; Android 14; SM-S928B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36",
        appCodeName: "Mozilla",
        onLine: true,
        webdriver: false,
        deviceMemory: 8,
        connection: { effectiveType: "4g", rtt: 50, downlink: 10, saveData: false },
        getBattery: () => Promise.resolve({ charging: true, level: 0.85, chargingTime: 0, dischargingTime: Infinity }),
        getGamepads: () => [],
        javaEnabled: () => false,
        sendBeacon: () => true,
        vibrate: () => true,
        plugins: { length: 5, refresh: () => {}, 0: { name: "Chrome PDF Plugin" }, 1: { name: "Chrome PDF Viewer" }, 2: { name: "Native Client" } },
        mimeTypes: { length: 4 }
    };
    
    Object.keys(fakeNav).forEach(k => {
        try {
            Object.defineProperty(navigator, k, {
                get: () => fakeNav[k],
                configurable: true,
                enumerable: true
            });
        } catch(e) {}
    });
    
    // 3. Sobrescribir screen
    const fakeScreen = {
        width: window.innerWidth,
        height: window.innerHeight,
        availWidth: window.innerWidth,
        availHeight: window.innerHeight,
        colorDepth: 24,
        pixelDepth: 24,
        availLeft: 0,
        availTop: 0,
        orientation: { type: "portrait-primary", angle: 0 }
    };
    Object.keys(fakeScreen).forEach(k => {
        try {
            Object.defineProperty(screen, k, {
                get: () => typeof fakeScreen[k] === "function" ? fakeScreen[k]() : fakeScreen[k],
                configurable: true
            });
        } catch(e) {}
    });
    
    // 4. Eliminar frameElement
    try { Object.defineProperty(window, "frameElement", { get: () => null }); } catch(e) {}
    try { Object.defineProperty(window, "top", { get: () => window }); } catch(e) {}
    try { Object.defineProperty(window, "parent", { get: () => window }); } catch(e) {}
    try { Object.defineProperty(window, "self", { get: () => window }); } catch(e) {}
    try { delete window.opener; } catch(e) {}
    
    // 5. Bloquear console
    const noop = () => {};
    ["log", "warn", "error", "debug", "info", "trace", "dir", "group", "groupEnd", "table", "time", "timeEnd"].forEach(m => {
        try { console[m] = noop; } catch(e) {}
    });
    
    // 6. Bloquear performance.memory
    try { Object.defineProperty(performance, "memory", { get: () => ({}) }); } catch(e) {}
    
    // 7. Bloquear document.hasFocus
    document.hasFocus = () => true;
    
    // 8. Bloquear matchMedia
    const originalMatchMedia = window.matchMedia;
    window.matchMedia = (query) => {
        if (query.includes("webview")) return { matches: false, media: query, addListener: noop, removeListener: noop };
        return originalMatchMedia.call(window, query);
    };
    
    // ============================================
    // VARIABLES
    // ============================================
    const URL_ENCODED = "<?= $urlOfuscada ?>";
    const ANTI_REDIRECT_WINDOW = <?= ANTI_REDIRECT_WINDOW ?>;
    const IFRAME_TIMEOUT = <?= IFRAME_LOAD_TIMEOUT ?>;
    
    function showToast(msg, isError = false) {
        const el = document.createElement("div");
        el.className = "toast-item";
        if (isError) el.style.borderLeftColor = "#ef4444";
        el.textContent = msg;
        document.getElementById("toastStack").appendChild(el);
        setTimeout(() => { el.style.opacity = "0"; setTimeout(() => el.remove(), 300); }, 4000);
    }
    
    function desofuscarUrl(e) { 
        return e.startsWith("data:frame:") ? atob(e.substring(11)) : e; 
    }
    
    const guard = {
        locked: false, lastClick: 0, blocked: 0,
        init() {
            document.addEventListener("click", () => {
                this.lastClick = Date.now();
                this.locked = true;
                document.getElementById("playerShield")?.classList.add("protecting");
                document.getElementById("shieldText").textContent = "PROTEGIENDO";
                setTimeout(() => {
                    if (Date.now() - this.lastClick >= ANTI_REDIRECT_WINDOW) {
                        this.locked = false;
                        document.getElementById("playerShield")?.classList.remove("protecting");
                        document.getElementById("shieldText").textContent = "ACTIVO";
                    }
                }, ANTI_REDIRECT_WINDOW + 100);
            }, true);
            
            window.addEventListener("beforeunload", (e) => {
                if (this.locked && Date.now() - this.lastClick < ANTI_REDIRECT_WINDOW + 500) {
                    this.blocked++;
                    e.preventDefault();
                    e.returnValue = "";
                    showToast("🛡️ Redirección bloqueada");
                    return false;
                }
            });
            
            const origOpen = window.open;
            window.open = (...args) => this.locked ? null : origOpen.apply(window, args);
        }
    };
    
    const loader = document.getElementById("playerLoader");
    const iframe = document.getElementById("mainIframe");
    
    function cargarIframe() {
        const realUrl = desofuscarUrl(URL_ENCODED);
        
        if (!realUrl) {
            loader.innerHTML = '<div style="color:#f87171;">❌ URL inválida</div>';
            return;
        }
        
        const tid = setTimeout(() => {
            loader.style.display = "none";
            showToast("Reproductor cargado");
        }, IFRAME_TIMEOUT);
        
        iframe.onload = () => {
            clearTimeout(tid);
            loader.style.display = "none";
            showToast("🎬 <?= htmlspecialchars(addslashes($item['nombre'])) ?>");
            
            try {
                const doc = iframe.contentDocument || iframe.contentWindow?.document;
                if (doc) {
                    const script = doc.createElement("script");
                    script.textContent = `
                        (function(){
                            Object.defineProperty(window,'frameElement',{get:()=>null});
                            Object.defineProperty(window,'top',{get:()=>window});
                            Object.defineProperty(window,'parent',{get:()=>window});
                            delete window.opener;
                            console.log=function(){};
                            console.warn=function(){};
                            console.error=function(){};
                            Object.defineProperty(navigator,'webdriver',{get:()=>false});
                            Object.defineProperty(navigator,'userAgent',{get:()=>'${fakeUA}'});
                        })();
                    `;
                    doc.head?.appendChild(script);
                }
            } catch(e) {}
        };
        
        iframe.onerror = () => {
            clearTimeout(tid);
            loader.innerHTML = '<div style="color:#f87171;">❌ Error al cargar</div>';
            showToast("Error al cargar", true);
        };
        
        iframe.src = realUrl;
    }
    
    document.getElementById("btnFullscreen")?.addEventListener("click", (e) => {
        e.stopPropagation();
        const el = document.getElementById("playerWrap");
        const req = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
        req?.call(el);
    });
    
    document.getElementById("btnReload")?.addEventListener("click", (e) => {
        e.stopPropagation();
        loader.style.display = "flex";
        loader.innerHTML = '<div class="spin-ring"></div><div class="player-loader-title">RECARGANDO...</div>';
        iframe.src = "about:blank";
        setTimeout(() => {
            loader.innerHTML = '<div class="spin-ring"></div><div class="player-loader-title">CARGANDO...</div>';
            cargarIframe();
        }, 200);
    });
    
    document.getElementById("playerWrap")?.addEventListener("click", (e) => {
        if (window.innerWidth <= 768 && !e.target.closest(".ctrl-btn")) {
            const bar = document.getElementById("controlBar");
            bar.style.opacity = "1";
            setTimeout(() => bar.style.opacity = "0", 3000);
        }
    });
    
    window.addEventListener("DOMContentLoaded", () => {
        guard.init();
        setTimeout(cargarIframe, 50);
        ["gesturestart", "gesturechange", "gestureend", "contextmenu"].forEach(ev => {
            document.addEventListener(ev, e => e.preventDefault());
        });
    });
    
    document.addEventListener("keydown", e => {
        if (e.key === "F12" || (e.ctrlKey && e.shiftKey && ["I","J","C"].includes(e.key.toUpperCase())) || (e.ctrlKey && ["U","S","P"].includes(e.key.toUpperCase()))) {
            e.preventDefault();
            return false;
        }
    });
    
    window.addEventListener("error", e => { if (e.message && e.message.includes("webview")) e.preventDefault(); });
    
})();
</script>
</body>
</html>