
<?php
// Mostrar todos los errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir la clase de conexión a base de datos
require_once __DIR__ . '/../../class/conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Subida de Archivos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Prueba de Subida de Archivos</h1>
        
        <?php
        // Procesar el formulario si se ha enviado
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo '<div class="alert alert-info">';
            echo '<h3>Datos recibidos:</h3>';
            echo '<pre>';
            echo "POST: " . print_r($_POST, true);
            echo "FILES: " . print_r($_FILES, true);
            echo '</pre>';
            
            // Verificar si hay un archivo para subir
            if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                echo '<h3>Procesando archivo...</h3>';
                
                $archivo = $_FILES['archivo'];
                $nombreOriginal = $archivo['name'];
                $tipoArchivo = $archivo['type'];
                $tamanoArchivo = $archivo['size'];
                $rutaTemporal = $archivo['tmp_name'];
                
                // Generar nombre único
                $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
                $nombreArchivo = uniqid('test_') . '.' . $extension;
                
                // Crear directorio para la prueba
                $directorioBase = __DIR__ . '/../documentos/';
                $directorioTest = $directorioBase . 'test/';
                
                // Verificar directorio base
                if (!file_exists($directorioBase)) {
                    if (mkdir($directorioBase, 0755, true)) {
                        echo "<p>✅ Directorio base creado: $directorioBase</p>";
                    } else {
                        echo "<p>❌ ERROR: No se pudo crear el directorio base: $directorioBase</p>";
                    }
                } else {
                    echo "<p>✅ Directorio base existe: $directorioBase</p>";
                    echo "<p>Permisos del directorio base: " . substr(sprintf('%o', fileperms($directorioBase)), -4) . "</p>";
                }
                
                // Verificar directorio de test
                if (!file_exists($directorioTest)) {
                    if (mkdir($directorioTest, 0755, true)) {
                        echo "<p>✅ Directorio test creado: $directorioTest</p>";
                    } else {
                        echo "<p>❌ ERROR: No se pudo crear el directorio test: $directorioTest</p>";
                    }
                } else {
                    echo "<p>✅ Directorio test existe: $directorioTest</p>";
                    echo "<p>Permisos del directorio test: " . substr(sprintf('%o', fileperms($directorioTest)), -4) . "</p>";
                }
                
                // Mover el archivo
                $rutaDestino = $directorioTest . $nombreArchivo;
                if (move_uploaded_file($rutaTemporal, $rutaDestino)) {
                    echo "<p>✅ Archivo movido correctamente a: $rutaDestino</p>";
                    
                    // Intentar guardar en base de datos
                    try {
                        // Crear conexión
                        $conexion = new Conexion();
                        $cid = $conexion->conectar('central');
                        
                        if (!$cid) {
                            echo "<p>❌ ERROR: No se pudo conectar a la base de datos</p>";
                            echo "<pre>" . print_r(sqlsrv_errors(), true) . "</pre>";
                        } else {
                            echo "<p>✅ Conexión a la base de datos establecida</p>";
                            
                            // Verificar si la tabla existe
                            $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'DocumentosProyectos'";
                            $result = sqlsrv_query($cid, $sql);
                            
                            if ($result === false) {
                                echo "<p>❌ ERROR: No se pudo verificar si la tabla existe</p>";
                                echo "<pre>" . print_r(sqlsrv_errors(), true) . "</pre>";
                            } else {
                                $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                                
                                if ($row['count'] == 0) {
                                    echo "<p>❌ ERROR: La tabla DocumentosProyectos no existe</p>";
                                } else {
                                    echo "<p>✅ La tabla DocumentosProyectos existe</p>";
                                    
                                    // Insertar el registro usando parámetros tipados
                                    $fechaActual = date('Y-m-d H:i:s');
                                    $proyectoIdTest = 1; // Usar un ID de proyecto existente para pruebas
                                    
                                    $sql = "INSERT INTO DocumentosProyectos (
                                                ID_Proyecto,
                                                NombreOriginal,
                                                NombreArchivo,
                                                TipoArchivo,
                                                TamanoArchivo,
                                                FechaSubida
                                            ) VALUES (?, ?, ?, ?, ?, ?)";
                                    
                                    $params = array(
                                        array($proyectoIdTest, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_INT),
                                        array($nombreOriginal, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR(255)),
                                        array($nombreArchivo, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR(255)),
                                        array($tipoArchivo, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR(100)),
                                        array($tamanoArchivo, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_BIGINT),
                                        array($fechaActual, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_DATETIME)
                                    );
                                    
                                    $stmt = sqlsrv_prepare($cid, $sql, $params);
                                    
                                    if ($stmt === false) {
                                        echo "<p>❌ ERROR: No se pudo preparar la consulta</p>";
                                        echo "<pre>" . print_r(sqlsrv_errors(), true) . "</pre>";
                                    } else {
                                        if (sqlsrv_execute($stmt)) {
                                            echo "<p>✅ Registro insertado correctamente en la base de datos</p>";
                                        } else {
                                            echo "<p>❌ ERROR: No se pudo ejecutar la consulta</p>";
                                            echo "<pre>" . print_r(sqlsrv_errors(), true) . "</pre>";
                                        }
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        echo "<p>❌ ERROR: " . $e->getMessage() . "</p>";
                    }
                } else {
                    echo "<p>❌ ERROR: No se pudo mover el archivo de $rutaTemporal a $rutaDestino</p>";
                }
                
            } else {
                echo '<p>❌ No se recibió ningún archivo o hubo un error al subirlo.</p>';
                if (isset($_FILES['archivo'])) {
                    echo '<p>Código de error: ' . $_FILES['archivo']['error'] . '</p>';
                    
                    // Mostrar mensaje de error según el código
                    $uploadErrors = array(
                        UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido en php.ini',
                        UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido en el formulario',
                        UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                        UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
                        UPLOAD_ERR_NO_TMP_DIR => 'No se encuentra el directorio temporal',
                        UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el disco',
                        UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida'
                    );
                    
                    if (isset($uploadErrors[$_FILES['archivo']['error']])) {
                        echo '<p>Explicación: ' . $uploadErrors[$_FILES['archivo']['error']] . '</p>';
                    }
                }
            }
            
            echo '</div>';
        }
        ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Formulario de Prueba</h2>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="archivo" class="form-label">Selecciona un archivo:</label>
                        <input type="file" class="form-control" id="archivo" name="archivo" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Subir Archivo</button>
                </form>
            </div>
        </div>
        
        <div class="mt-4">
            <h3>Información del sistema:</h3>
            <ul class="list-group">
                <li class="list-group-item">
                    <strong>upload_max_filesize:</strong> <?php echo ini_get('upload_max_filesize'); ?>
                </li>
                <li class="list-group-item">
                    <strong>post_max_size:</strong> <?php echo ini_get('post_max_size'); ?>
                </li>
                <li class="list-group-item">
                    <strong>max_file_uploads:</strong> <?php echo ini_get('max_file_uploads'); ?>
                </li>
                <li class="list-group-item">
                    <strong>memory_limit:</strong> <?php echo ini_get('memory_limit'); ?>
                </li>
                <li class="list-group-item">
                    <strong>PHP version:</strong> <?php echo phpversion(); ?>
                </li>
            </ul>
        </div>
    </div>
</body>
</html>