<?php
require_once 'config.php';

$pdo = conectarBD();

echo "<h1>DIAGNÓSTICO DE SECCIONES</h1>";

echo "<h2>1. Tablas en la base de datos:</h2>";
$tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<ul>";
foreach ($tablas as $tabla) {
    echo "<li>$tabla</li>";
}
echo "</ul>";

echo "<h2>2. Datos en tabla 'secciones':</h2>";
$secciones = $pdo->query("SELECT * FROM secciones")->fetchAll();
if (empty($secciones)) {
    echo "<p style='color:red;'>❌ La tabla 'secciones' está VACÍA</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Orden</th></tr>";
    foreach ($secciones as $s) {
        echo "<tr><td>{$s['id']}</td><td>{$s['nombre']}</td><td>{$s['tipo']}</td><td>{$s['orden']}</td></tr>";
    }
    echo "</table>";
}

echo "<h2>3. Datos en tabla 'seccion_items':</h2>";
$items = $pdo->query("SELECT si.*, i.nombre as pelicula FROM seccion_items si LEFT JOIN iframes i ON si.iframe_id = i.id")->fetchAll();
if (empty($items)) {
    echo "<p style='color:red;'>❌ La tabla 'seccion_items' está VACÍA</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Sección ID</th><th>Iframe ID</th><th>Película</th><th>Orden</th></tr>";
    foreach ($items as $item) {
        echo "<tr><td>{$item['id']}</td><td>{$item['seccion_id']}</td><td>{$item['iframe_id']}</td><td>{$item['pelicula']}</td><td>{$item['orden']}</td></tr>";
    }
    echo "</table>";
}

echo "<h2>4. Datos en tabla 'seccion_peliculas' (tabla antigua):</h2>";
try {
    $old = $pdo->query("SELECT * FROM seccion_peliculas LIMIT 10")->fetchAll();
    if (empty($old)) {
        echo "<p>Tabla vacía o no existe</p>";
    } else {
        echo "<p>Columnas: " . implode(', ', array_keys($old[0])) . "</p>";
        echo "<table border='1' cellpadding='5'>";
        foreach ($old as $row) {
            echo "<tr>";
            foreach ($row as $val) {
                echo "<td>" . htmlspecialchars(substr($val ?? '', 0, 50)) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p>No se pudo leer: " . $e->getMessage() . "</p>";
}

echo "<h2>5. Películas en 'iframes':</h2>";
$peli = $pdo->query("SELECT id, nombre, anio FROM iframes")->fetchAll();
echo "<p>Total: " . count($peli) . " películas</p>";
echo "<ul>";
foreach ($peli as $p) {
    echo "<li>ID: {$p['id']} - {$p['nombre']} ({$p['anio']})</li>";
}
echo "</ul>";

echo "<h2>6. Prueba de consulta del index.php:</h2>";
$testSecciones = [];
$stmtTest = $pdo->query("SELECT id, nombre, tipo FROM secciones WHERE tipo = 'section'");
if ($stmtTest) {
    while ($sec = $stmtTest->fetch()) {
        $stmtItems = $pdo->prepare("SELECT i.id, i.nombre FROM seccion_items si JOIN iframes i ON si.iframe_id = i.id WHERE si.seccion_id = ?");
        $stmtItems->execute([$sec['id']]);
        $itemsTest = $stmtItems->fetchAll();
        echo "<p><strong>{$sec['nombre']} ({$sec['tipo']})</strong>: " . count($itemsTest) . " películas</p>";
        if (!empty($itemsTest)) {
            echo "<ul>";
            foreach ($itemsTest as $it) {
                echo "<li>{$it['nombre']}</li>";
            }
            echo "</ul>";
        }
    }
}

echo "<h2>7. ACCIÓN: Migrar datos manualmente</h2>";
echo "<form method='post'>";
echo "<input type='submit' name='migrar' value='EJECUTAR MIGRACIÓN AHORA' style='padding:10px 20px; background:red; color:white; border:none; border-radius:5px; cursor:pointer;'>";
echo "</form>";

if (isset($_POST['migrar'])) {
    echo "<h3>Ejecutando migración...</h3>";
    
    // Intentar migrar desde seccion_peliculas
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'seccion_peliculas'");
        if ($check->rowCount() > 0) {
            $rows = $pdo->query("SELECT * FROM seccion_peliculas")->fetchAll();
            echo "<p>Encontrados " . count($rows) . " registros en seccion_peliculas</p>";
            
            if (!empty($rows)) {
                $first = $rows[0];
                echo "<p>Columnas disponibles: " . implode(', ', array_keys($first)) . "</p>";
                
                // Buscar columna de nombre
                $nombreCol = null;
                foreach (array_keys($first) as $col) {
                    if (stripos($col, 'nombre') !== false || stripos($col, 'seccion') !== false || stripos($col, 'categoria') !== false) {
                        $nombreCol = $col;
                        break;
                    }
                }
                
                // Buscar columna de iframe
                $iframeCol = null;
                foreach (array_keys($first) as $col) {
                    if (stripos($col, 'iframe') !== false || stripos($col, 'pelicula') !== false || $col == 'id_pelicula') {
                        $iframeCol = $col;
                        break;
                    }
                }
                
                echo "<p>Columna de nombre detectada: <strong>" . ($nombreCol ?? 'NINGUNA') . "</strong></p>";
                echo "<p>Columna de iframe detectada: <strong>" . ($iframeCol ?? 'NINGUNA') . "</strong></p>";
                
                if ($nombreCol && $iframeCol) {
                    // Insertar secciones
                    $sql = "INSERT IGNORE INTO secciones (nombre, tipo, orden) SELECT DISTINCT `$nombreCol`, 'section', 0 FROM seccion_peliculas WHERE `$nombreCol` IS NOT NULL AND `$nombreCol` != ''";
                    $pdo->exec($sql);
                    echo "<p>✅ Secciones insertadas</p>";
                    
                    // Insertar items
                    $sql = "INSERT IGNORE INTO seccion_items (seccion_id, iframe_id, orden) SELECT s.id, sp.`$iframeCol`, 0 FROM seccion_peliculas sp JOIN secciones s ON s.nombre = sp.`$nombreCol` WHERE sp.`$iframeCol` IS NOT NULL";
                    $pdo->exec($sql);
                    echo "<p>✅ Items insertados</p>";
                } else {
                    echo "<p style='color:orange;'>⚠️ No se pudieron detectar las columnas automáticamente.</p>";
                    echo "<p>Por favor, dime qué columna contiene el NOMBRE de la sección y cuál el ID de la película.</p>";
                }
            }
        } else {
            echo "<p>La tabla seccion_peliculas no existe.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<p><a href='test.php'>Recargar página</a></p>";
}
?>