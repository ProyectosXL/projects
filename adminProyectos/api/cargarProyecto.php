<?php
/**
 * Procesa y guarda los documentos adjuntos (localmente y en Google Drive)
 */
function procesarDocumentos($conexion, $proyectoId) {
    $documentosGuardados = array();
    
    // Verificar si hay documentos para procesar
    $numDocumentos = isset($_POST['num_documentos']) ? intval($_POST['num_documentos']) : 0;
    
    if ($numDocumentos <= 0) {
        return $documentosGuardados;
    }
    
    // Crear directorio para almacenar documentos si no existe
    $directorioBase = __DIR__ . '/../documentos/';
    $directorioProyecto = $directorioBase . $proyectoId . '/';
    
    // Crear directorio base si no existe
    if (!file_exists($directorioBase)) {
        if (!mkdir($directorioBase, 0755, true)) {
            error_log("No se pudo crear el directorio base: $directorioBase");
            throw new Exception('Error al crear el directorio para documentos');
        }
    }
    
    // Crear directorio del proyecto si no existe
    if (!file_exists($directorioProyecto)) {
        if (!mkdir($directorioProyecto, 0755, true)) {
            error_log("No se pudo crear el directorio del proyecto: $directorioProyecto");
            throw new Exception('Error al crear el directorio para documentos del proyecto');
        }
    }
    
    // Intentar inicializar Google Drive API
    $googleDrive = null;
    try {
        require_once __DIR__ . '/../class/GoogleDriveAPI.php';
        $googleDrive = new GoogleDriveAPI();
    } catch (Exception $e) {
        // Loguear el error pero continuar con el almacenamiento local
        error_log("Error al inicializar Google Drive API: " . $e->getMessage());
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
                error_log('Error al preparar la consulta para guardar documento: ' . print_r(sqlsrv_errors(), true));
                throw new Exception('Error al preparar la consulta para guardar documento: ' . print_r(sqlsrv_errors(), true));
            }
            
            if (!sqlsrv_execute($stmt)) {
                error_log('Error al guardar información del documento: ' . print_r(sqlsrv_errors(), true));
                throw new Exception('Error al guardar información del documento: ' . print_r(sqlsrv_errors(), true));
            }
            
            // Obtener el ID del documento insertado
            $sql = "SELECT SCOPE_IDENTITY() as ID";
            $stmt = sqlsrv_query($conexion, $sql);
            
            if ($stmt === false) {
                error_log('Error al obtener el ID del documento: ' . print_r(sqlsrv_errors(), true));
                throw new Exception('Error al obtener el ID del documento: ' . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $documentoId = $row['ID'];
            
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
        } else {
            error_log("Error al mover el archivo de $rutaTemporal a $rutaDestino");
            throw new Exception("Error al mover el archivo de {$archivo['name']} al directorio de destino");
        }
    }
    
    return $documentosGuardados;
}