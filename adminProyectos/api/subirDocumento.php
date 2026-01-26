<?php
/**
 * API para subir documentos a un proyecto específico
 */

// Configuración para ocultar errores en producción
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Configuración de cabeceras para respuestas JSON
header('Content-Type: application/json; charset=UTF-8');

// Incluir clase de proyecto
require_once '../class/proyecto.php';

try {
    // Verificar que se envió por POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responderError('Método no permitido', 405);
        exit;
    }
    
    // Verificar que se proporcionó un ID de proyecto
    if (!isset($_POST['proyecto_id']) || !is_numeric($_POST['proyecto_id'])) {
        responderError('ID de proyecto no válido');
        exit;
    }
    
    $proyectoId = (int)$_POST['proyecto_id'];
    
    // Crear instancia del proyecto
    $proyectoObj = new Proyecto();
    
    // Verificar que el proyecto existe
    $proyecto = $proyectoObj->obtenerProyectoPorId($proyectoId);
    if (!$proyecto) {
        responderError('Proyecto no encontrado');
        exit;
    }
    
    // Verificar que se subieron archivos
    if (empty($_FILES)) {
        responderError('No se enviaron archivos');
        exit;
    }
    
    // Procesar documentos
    $conexion = $proyectoObj->getConexion();
    $documentosGuardados = procesarDocumentos($conexion, $proyectoId);
    
    if (empty($documentosGuardados)) {
        responderError('No se pudieron procesar los documentos');
        exit;
    }
    
    // Responder con éxito
    responderExito('Documentos subidos correctamente', array(
        'documentos' => $documentosGuardados
    ));
    
} catch (Exception $e) {
    error_log('Error en subirDocumento.php: ' . $e->getMessage());
    responderError('Error en el servidor: ' . $e->getMessage());
}

/**
 * Procesa los documentos subidos
 */
function procesarDocumentos($conexion, $proyectoId) {
    $documentosGuardados = array();
    
    // Directorio de destino
    $directorioDestino = __DIR__ . '/../documentos/' . $proyectoId;
    
    // Crear directorio si no existe
    if (!file_exists($directorioDestino)) {
        if (!mkdir($directorioDestino, 0755, true)) {
            throw new Exception('No se pudo crear el directorio de documentos');
        }
    }
    
    // Procesar cada archivo
    foreach ($_FILES as $key => $archivo) {
        if ($archivo['error'] === UPLOAD_ERR_OK) {
            $documentoGuardado = procesarArchivoIndividual($conexion, $proyectoId, $archivo, $directorioDestino);
            if ($documentoGuardado) {
                $documentosGuardados[] = $documentoGuardado;
            }
        }
    }
    
    return $documentosGuardados;
}

/**
 * Procesa un archivo individual
 */
function procesarArchivoIndividual($conexion, $proyectoId, $archivo, $directorioDestino) {
    try {
        // Validar el archivo
        $validacion = validarArchivo($archivo);
        if (!$validacion['valido']) {
            throw new Exception($validacion['error']);
        }
        
        // Generar nombre único para el archivo
        $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombreArchivo = uniqid('doc_') . '.' . $extension;
        $rutaDestino = $directorioDestino . '/' . $nombreArchivo;
        
        // Mover el archivo
        if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            throw new Exception('Error al mover el archivo al directorio de destino');
        }
        
        // Guardar información en la base de datos
        $sql = "INSERT INTO DocumentosProyectos (
                    ID_Proyecto,
                    NombreOriginal,
                    NombreArchivo,
                    TipoArchivo,
                    TamanoArchivo,
                    FechaSubida
                ) VALUES (?, ?, ?, ?, ?, GETDATE())";
        
        $params = array(
            $proyectoId,
            $archivo['name'],
            $nombreArchivo,
            $archivo['type'],
            $archivo['size']
        );
        
        $stmt = sqlsrv_prepare($conexion, $sql, $params);
        
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . print_r(sqlsrv_errors(), true));
        }
        
        if (!sqlsrv_execute($stmt)) {
            throw new Exception('Error al guardar información del documento: ' . print_r(sqlsrv_errors(), true));
        }
        
        // Obtener el ID del documento insertado
        $sqlId = "SELECT SCOPE_IDENTITY() as ID";
        $stmtId = sqlsrv_query($conexion, $sqlId);
        
        if ($stmtId && $row = sqlsrv_fetch_array($stmtId, SQLSRV_FETCH_ASSOC)) {
            $documentoId = $row['ID'];
        } else {
            $documentoId = null;
        }
        
        return array(
            'id' => $documentoId,
            'nombre' => $archivo['name'],
            'archivo' => $nombreArchivo,
            'tipo' => $archivo['type'],
            'tamano' => formatearTamano($archivo['size']),
            'fecha' => date('Y-m-d'),
            'usuario' => 'Admin'
        );
        
    } catch (Exception $e) {
        error_log('Error al procesar archivo: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Valida un archivo subido
 */
function validarArchivo($archivo) {
    // Verificar errores de subida
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return array('valido' => false, 'error' => 'Error al subir el archivo');
    }
    
    // Verificar tamaño máximo (10MB)
    $tamanoMaximo = 10 * 1024 * 1024; // 10MB
    if ($archivo['size'] > $tamanoMaximo) {
        return array('valido' => false, 'error' => 'El archivo es demasiado grande (máximo 10MB)');
    }
    
    // Verificar tipo de archivo
    $tiposPermitidos = array(
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/png',
        'image/jpeg',
        'application/zip'
    );
    
    if (!in_array($archivo['type'], $tiposPermitidos)) {
        return array('valido' => false, 'error' => 'Tipo de archivo no permitido');
    }
    
    return array('valido' => true);
}

/**
 * Función auxiliar para formatear el tamaño de archivo
 */
function formatearTamano($bytes) {
    if ($bytes === 0 || $bytes === null) return '0 Bytes';
    
    $bytes = intval($bytes);
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

/**
 * Genera una respuesta de éxito en formato JSON
 */
function responderExito($mensaje, $datos = array()) {
    $respuesta = array(
        'exito' => true,
        'mensaje' => $mensaje
    );
    
    if (!empty($datos)) {
        $respuesta = array_merge($respuesta, $datos);
    }
    
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Genera una respuesta de error en formato JSON
 */
function responderError($mensaje, $codigo = 400) {
    http_response_code($codigo);
    
    echo json_encode(array(
        'exito' => false,
        'mensaje' => $mensaje
    ), JSON_UNESCAPED_UNICODE);
    
    exit;
}