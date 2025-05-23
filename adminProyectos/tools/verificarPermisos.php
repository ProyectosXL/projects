
<?php
// Mostrar todos los errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Definir directorios a verificar
$directorios = [
    'documentos' => __DIR__ . '/../documentos/',
    'vendor' => __DIR__ . '/../vendor/',
    'class' => __DIR__ . '/../class/',
    'api' => __DIR__ . '/../api/'
];

// Función para verificar permisos recursivamente
function verificarDirectorio($path, $nivel = 0) {
    $indentacion = str_repeat('&nbsp;&nbsp;', $nivel);
    $permisos = substr(sprintf('%o', fileperms($path)), -4);
    
    echo "$indentacion📁 <strong>$path</strong> (Permisos: $permisos) ";
    
    if (!is_readable($path)) {
        echo "❌ No se puede leer";
    } elseif (!is_writable($path)) {
        echo "❌ No se puede escribir";
    } else {
        echo "✅ OK";
    }
    echo "<br>";
    
    if (is_dir($path) && is_readable($path)) {
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $itemPath = $path . '/' . $item;
            
            if (is_dir($itemPath)) {
                verificarDirectorio($itemPath, $nivel + 1);
            } else {
                $permisos = substr(sprintf('%o', fileperms($itemPath)), -4);
                echo "$indentacion&nbsp;&nbsp;📄 $item (Permisos: $permisos) ";
                
                if (!is_readable($itemPath)) {
                    echo "❌ No se puede leer";
                } elseif (!is_writable($itemPath)) {
                    echo "❌ No se puede escribir";
                } else {
                    echo "✅ OK";
                }
                echo "<br>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Permisos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Verificación de Permisos de Archivos y Directorios</h1>
        
        <div class="alert alert-info">
            Esta herramienta verifica los permisos de archivos y directorios importantes para el funcionamiento del sistema.
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h3>Información del Sistema</h3>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    <li class="list-group-item"><strong>Sistema Operativo:</strong> <?php echo PHP_OS; ?></li>
                    <li class="list-group-item"><strong>Servidor Web:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?></li>
                    <li class="list-group-item"><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                    <li class="list-group-item">
                        <strong>Usuario del Proceso:</strong> 
                        <?php 
                        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                            $processUser = posix_getpwuid(posix_geteuid());
                            echo $processUser['name'];
                        } else {
                            echo "Función no disponible en Windows";
                        }
                        ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Directorio Raíz:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Permisos de Directorios</h3>
            </div>
            <div class="card-body">
                <?php
                foreach ($directorios as $nombre => $ruta) {
                    echo "<h4>Directorio: $nombre</h4>";
                    
                    if (!file_exists($ruta)) {
                        echo "<div class='alert alert-danger'>El directorio $ruta no existe</div>";
                        
                        // Intentar crear el directorio si es 'documentos'
                        if ($nombre === 'documentos') {
                            echo "<p>Intentando crear el directorio...</p>";
                            if (mkdir($ruta, 0755, true)) {
                                echo "<div class='alert alert-success'>Directorio creado correctamente</div>";
                                verificarDirectorio($ruta);
                            } else {
                                echo "<div class='alert alert-danger'>No se pudo crear el directorio</div>";
                            }
                        }
                    } else {
                        verificarDirectorio($ruta);
                    }
                    
                    echo "<hr>";
                }
                ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Prueba de Escritura</h3>
            </div>
            <div class="card-body">
                <?php
                $dirPrueba = __DIR__ . '/../documentos/prueba_escritura.txt';
                echo "<p>Intentando escribir archivo de prueba en: $dirPrueba</p>";
                
                $resultado = @file_put_contents($dirPrueba, "Prueba de escritura realizada el " . date('Y-m-d H:i:s'));
                
                if ($resultado !== false) {
                    echo "<div class='alert alert-success'>✅ Archivo escrito correctamente</div>";
                    unlink($dirPrueba); // Eliminar el archivo de prueba
                    echo "<p>Archivo de prueba eliminado</p>";
                } else {
                    echo "<div class='alert alert-danger'>❌ No se pudo escribir el archivo de prueba</div>";
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>