<?php
// Al inicio del archivo
ini_set('display_errors', 0); // Cambiar a 0 en producción
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Configurar cabeceras para respuestas JSON
header('Content-Type: application/json');

// Capturar toda la salida para evitar contenido adicional en la respuesta JSON
ob_start();

// Verificar si se está solicitando la lista de solicitantes
if (isset($_GET['accion']) && $_GET['accion'] === 'obtenerSolicitantes') {
    try {
        // Incluir la clase Proyecto
        require_once __DIR__ . '/../class/proyecto.php';
        
        // Crear instancia de Proyecto
        $proyectoObj = new Proyecto();
        
        // Obtener solicitantes
        $solicitantes = $proyectoObj->obtenerSolicitantes();
        
        // Devolver respuesta
        echo json_encode([
            'exito' => true,
            'solicitantes' => $solicitantes
        ]);
        
    } catch (Exception $e) {
        error_log('Error al obtener solicitantes: ' . $e->getMessage());
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Error al obtener solicitantes: ' . $e->getMessage()
        ]);
    }
    
    $output = ob_get_clean();
    echo $output;
    exit;
}

// Manejar la creación del proyecto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Incluir la clase necesaria si no está incluida ya
        require_once __DIR__ . '/../class/proyecto.php';
        
        // Crear instancia de la clase Proyecto
        $proyectoObj = new Proyecto();

        // Obtener conexión a la base de datos
        $conexion = $proyectoObj->getConexion();
        
        // Verificar si la conexión es válida
        if (!$conexion) {
            throw new Exception("No se pudo establecer conexión con la base de datos");
        }
        
        // Recopilar datos del formulario
        $datos = array(
            'tipo' => $_POST['tipo'] ?? '',
            'desarrollo' => $_POST['desarrollo'] ?? '',
            'solicitante' => $_POST['solicitante'] ?? '',
            'pais' => $_POST['pais'] ?? '',
            'sector' => $_POST['sector'] ?? '',
            'funcionalidad' => $_POST['funcionalidad'] ?? ''
        );
        
        error_log("Datos recibidos para crear proyecto: " . json_encode($datos));
        
        // Crear el proyecto y obtener su ID
        $proyectoId = $proyectoObj->crearProyecto($datos);
        error_log("Proyecto creado con ID: " . ($proyectoId ? $proyectoId : 'NULL'));
        
        // Verificar que el ID del proyecto sea válido
        if (!$proyectoId) {
            throw new Exception('No se pudo obtener el ID del proyecto creado');
        }
        
        // Convertir a entero para asegurarnos de que es un ID válido
        $proyectoId = intval($proyectoId);
        if ($proyectoId <= 0) {
            throw new Exception('ID de proyecto inválido: ' . $proyectoId);
        }
        
        error_log("ID del proyecto validado: $proyectoId");
        
        // Procesar documentos adjuntos
        $documentosGuardados = procesarDocumentos($conexion, $proyectoId);
        error_log("Documentos guardados: " . json_encode($documentosGuardados));
        
        // Devolver respuesta exitosa
        echo json_encode([
            'exito' => true,
            'mensaje' => 'Proyecto guardado correctamente',
            'proyectoId' => $proyectoId,
            'documentos' => $documentosGuardados
        ]);
        
    } catch (Exception $e) {
        // Registrar el error
        error_log("Error al guardar proyecto: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        
        // Devolver respuesta de error
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Error al guardar el proyecto: ' . $e->getMessage()
        ]);
    }
    
    $output = ob_get_clean();
    echo $output;
    exit;
}

/**
 * Verifica si la tabla DocumentosProyectos existe y tiene las columnas necesarias para Drive
 */
function verificarTablaDocumentosProyectos($conexion) {
    // Verificar si la tabla existe
    $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'DocumentosProyectos'";
    $result = sqlsrv_query($conexion, $sql);
    
    if ($result === false) {
        throw new Exception('Error al verificar la tabla DocumentosProyectos: ' . print_r(sqlsrv_errors(), true));
    }
    
    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
    
    if ($row['count'] == 0) {
        // Crear la tabla si no existe
        $sqlCrear = "CREATE TABLE DocumentosProyectos (
            ID INT IDENTITY(1,1) PRIMARY KEY,
            ID_Proyecto INT NOT NULL,
            NombreOriginal NVARCHAR(255) NOT NULL,
            NombreArchivo NVARCHAR(255) NOT NULL,
            TipoArchivo NVARCHAR(100),
            TamanoArchivo BIGINT,
            FechaSubida DATETIME,
            DriveFileId NVARCHAR(255) NULL,
            DriveFileUrl NVARCHAR(500) NULL,
            FOREIGN KEY (ID_Proyecto) REFERENCES HistorialProyectos(ID)
        )";
        
        $resultCrear = sqlsrv_query($conexion, $sqlCrear);
        
        if ($resultCrear === false) {
            throw new Exception('Error al crear la tabla DocumentosProyectos: ' . print_r(sqlsrv_errors(), true));
        }
    } else {
        // Verificar si existen las columnas para Drive
        $columnas = ['DriveFileId', 'DriveFileUrl'];
        
        foreach ($columnas as $columna) {
            $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'DocumentosProyectos' AND COLUMN_NAME = '$columna'";
            $result = sqlsrv_query($conexion, $sql);
            
            if ($result === false) {
                throw new Exception('Error al verificar la columna ' . $columna . ': ' . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            
            if ($row['count'] == 0) {
                // Agregar la columna si no existe
                $tipo = ($columna == 'DriveFileId') ? 'NVARCHAR(255)' : 'NVARCHAR(500)';
                $sqlAlter = "ALTER TABLE DocumentosProyectos ADD $columna $tipo NULL";
                $resultAlter = sqlsrv_query($conexion, $sqlAlter);
                
                if ($resultAlter === false) {
                    throw new Exception('Error al agregar la columna ' . $columna . ': ' . print_r(sqlsrv_errors(), true));
                }
            }
        }
    }
}

/**
 * Procesa y guarda los documentos adjuntos (localmente y en Google Drive)
 */
function procesarDocumentos($conexion, $proyectoId) {
    error_log("Iniciando procesarDocumentos() con proyectoId: $proyectoId");
    error_log("FILES recibidos: " . print_r($_FILES, true));
    error_log("POST num_documentos: " . (isset($_POST['num_documentos']) ? $_POST['num_documentos'] : 'No definido'));
    
    // Verificar que proyectoId no sea null
    if (!$proyectoId) {
        error_log("ERROR: proyectoId es nulo o vacío");
        return array();
    }
    
    $documentosGuardados = array();
    
    // Verificar si hay documentos para procesar
    $numDocumentos = isset($_POST['num_documentos']) ? intval($_POST['num_documentos']) : 0;
    error_log("Número de documentos a procesar: $numDocumentos");
    
    if ($numDocumentos <= 0) {
        error_log("No hay documentos para procesar");
        return $documentosGuardados;
    }
    
    // Verificar la conexión a base de datos
    error_log("Conexión a base de datos válida: " . ($conexion ? 'Sí' : 'No'));
    
    // Crear directorio para almacenar documentos si no existe
    $directorioBase = __DIR__ . '/../documentos/';
    $directorioProyecto = $directorioBase . $proyectoId . '/';
    
    error_log("Verificando directorios - Base: $directorioBase, Proyecto: $directorioProyecto");
    error_log("Directorio base existe: " . (file_exists($directorioBase) ? 'Sí' : 'No'));
    
    // Crear directorio base si no existe
    if (!file_exists($directorioBase)) {
        error_log("Intentando crear directorio base: $directorioBase");
        if (!mkdir($directorioBase, 0755, true)) {
            error_log("No se pudo crear el directorio base: $directorioBase");
            throw new Exception('Error al crear el directorio para documentos');
        }
        error_log("Directorio base creado correctamente");
    }
    
    // Verificar si el directorio base es escribible
    if (!is_writable($directorioBase)) {
        error_log("ERROR: El directorio base no es escribible: $directorioBase");
        chmod($directorioBase, 0755); // Intentar cambiar permisos
        error_log("Intentando cambiar permisos del directorio base");
    }
    
    // Crear directorio del proyecto si no existe
    if (!file_exists($directorioProyecto)) {
        error_log("Intentando crear directorio de proyecto: $directorioProyecto");
        if (!mkdir($directorioProyecto, 0755, true)) {
            error_log("No se pudo crear el directorio del proyecto: $directorioProyecto");
            throw new Exception('Error al crear el directorio para documentos del proyecto');
        }
        error_log("Directorio de proyecto creado correctamente");
    }
    
    // Verificar si el directorio del proyecto es escribible
    if (!is_writable($directorioProyecto)) {
        error_log("ERROR: El directorio del proyecto no es escribible: $directorioProyecto");
        chmod($directorioProyecto, 0755); // Intentar cambiar permisos
        error_log("Intentando cambiar permisos del directorio del proyecto");
    }
    
    // Intentar inicializar Google Drive API
    $googleDrive = null;
    try {
        require_once __DIR__ . '/../class/GoogleDriveAPI.php';
        $googleDrive = new GoogleDriveAPI();
        error_log("Google Drive API inicializada correctamente");
        echo "<p>✓ Google Drive API inicializada correctamente</p>";
    } catch (Exception $e) {
        // Loguear el error pero continuar con el almacenamiento local
        error_log("Error al inicializar Google Drive API: " . $e->getMessage());
        echo "<p>✗ Error al inicializar Google Drive API: " . $e->getMessage() . "</p>";
    }
    
    // Procesar cada documento
    for ($i = 0; $i < $numDocumentos; $i++) {
        $docKey = 'documento_' . $i;
        
        if (!isset($_FILES[$docKey]) || $_FILES[$docKey]['error'] !== UPLOAD_ERR_OK) {
            error_log("Error con el archivo $docKey: " . ($_FILES[$docKey]['error'] ?? 'No definido'));
            continue;
        }
        
        $archivo = $_FILES[$docKey];
        $nombreOriginal = $archivo['name'];
        $tipoArchivo = $archivo['type'];
        $tamanoArchivo = $archivo['size'];
        $rutaTemporal = $archivo['tmp_name'];
        
        error_log("Procesando archivo: $nombreOriginal (Tipo: $tipoArchivo, Tamaño: $tamanoArchivo)");
        
        // Generar nombre único para el archivo
        $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
        $nombreArchivo = uniqid('doc_') . '.' . $extension;
        $rutaDestino = $directorioProyecto . $nombreArchivo;
        
        // Verificar si el archivo temporal existe
        if (!file_exists($rutaTemporal)) {
            error_log("El archivo temporal no existe: $rutaTemporal");
            continue;
        }
        
        // Variables para Google Drive
        $driveFileId = null;
        $driveFileUrl = null;
        
        // Mover el archivo al directorio de destino
        error_log("Intentando mover archivo de $rutaTemporal a $rutaDestino");
        if (move_uploaded_file($rutaTemporal, $rutaDestino)) {
            error_log("Archivo movido correctamente a: $rutaDestino");
            
            // Intentar subir a Google Drive si está disponible
            if ($googleDrive !== null) {
                try {
                    // La función uploadProjectFile establece la estructura de carpetas 
                    // y sube el archivo a la carpeta del proyecto
                    $driveFile = $googleDrive->uploadProjectFile(
                        $rutaDestino,
                        $nombreOriginal,
                        $proyectoId,
                        $tipoArchivo
                    );
                    
                    $driveFileId = $driveFile['id'];
                    $driveFileUrl = $driveFile['webViewLink'];
                    
                    error_log("Archivo subido correctamente a Google Drive: $driveFileUrl");
                } catch (Exception $e) {
                    // Loguear el error pero continuar con el almacenamiento local
                    error_log("Error al subir a Google Drive: " . $e->getMessage());
                }
            }
            
            // Verificar si la tabla tiene las columnas para Drive
            verificarTablaDocumentosProyectos($conexion);
            
            // Crear consulta SQL según si tenemos información de Drive o no
            if ($driveFileId && $driveFileUrl) {
                $sql = "INSERT INTO DocumentosProyectos (
                            ID_Proyecto,
                            NombreOriginal,
                            NombreArchivo,
                            TipoArchivo,
                            TamanoArchivo,
                            FechaSubida,
                            DriveFileId,
                            DriveFileUrl
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $fechaActual = date('Y-m-d H:i:s');
                $proyectoIdInt = intval($proyectoId);
                $tamanoArchivoInt = intval($tamanoArchivo);
                
                $params = array(
                    array($proyectoIdInt, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_INT),
                    array($nombreOriginal, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR(255)),
                    array($nombreArchivo, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR(255)),
                    array($tipoArchivo, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR(100)),
                    array($tamanoArchivoInt, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_BIGINT),
                    array($fechaActual, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_DATETIME),
                    array($driveFileId, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR(255)),
                    array($driveFileUrl, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR(500))
                );
            } else {
                $sql = "INSERT INTO DocumentosProyectos (
                            ID_Proyecto,
                            NombreOriginal,
                            NombreArchivo,
                            TipoArchivo,
                            TamanoArchivo,
                            FechaSubida
                        ) VALUES (?, ?, ?, ?, ?, ?)";
                
                $fechaActual = date('Y-m-d H:i:s');
                $proyectoIdInt = intval($proyectoId);
                $tamanoArchivoInt = intval($tamanoArchivo);
                
                $params = array(
                    array($proyectoIdInt, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_INT),
                    array($nombreOriginal, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR(255)),
                    array($nombreArchivo, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR(255)),
                    array($tipoArchivo, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR(100)),
                    array($tamanoArchivoInt, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_BIGINT),
                    array($fechaActual, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_DATETIME)
                );
            }
            
            $stmt = sqlsrv_prepare($conexion, $sql, $params);
            
            if (!$stmt) {
                $sqlErrors = print_r(sqlsrv_errors(), true);
                error_log("Error al preparar la consulta SQL: $sqlErrors");
                throw new Exception("Error al preparar la consulta para guardar documento: $sqlErrors");
            }
            
            if (!sqlsrv_execute($stmt)) {
                $sqlErrors = print_r(sqlsrv_errors(), true);
                error_log("Error al ejecutar la consulta SQL: $sqlErrors");
                throw new Exception("Error al guardar información del documento: $sqlErrors");
            }
            
            // Obtener el ID del documento insertado
            $sql = "SELECT SCOPE_IDENTITY() as ID";
            $stmt = sqlsrv_query($conexion, $sql);
            
            if ($stmt === false) {
                $sqlErrors = print_r(sqlsrv_errors(), true);
                error_log("Error al obtener el ID del documento: $sqlErrors");
                throw new Exception("Error al obtener el ID del documento: $sqlErrors");
            }
            
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $documentoId = $row['ID'];
            error_log("Documento guardado en BD con ID: $documentoId");
            
            // Agregar a la lista de documentos guardados
            $documentoGuardado = array(
                'id' => $documentoId,
                'nombre' => $nombreOriginal,
                'ruta' => 'documentos/' . $proyectoId . '/' . $nombreArchivo,
                'tipo' => $tipoArchivo,
                'tamano' => $tamanoArchivo
            );
            
            // Si se subió a Drive, añadir esa información
            if ($driveFileId && $driveFileUrl) {
                $documentoGuardado['driveFileId'] = $driveFileId;
                $documentoGuardado['driveUrl'] = $driveFileUrl;
            }
            
            $documentosGuardados[] = $documentoGuardado;
            error_log("Documento añadido a la lista de guardados");
        } else {
            error_log("Error al mover el archivo de $rutaTemporal a $rutaDestino");
            throw new Exception("Error al mover el archivo de {$archivo['name']} al directorio de destino");
        }
    }
    
    error_log("Procesamiento de documentos finalizado. Total documentos: " . count($documentosGuardados));
    return $documentosGuardados;
}