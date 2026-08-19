<?php
/**
 * API para gestionar detalles de proyectos
 */

// Configuración para mostrar errores (desactivado para no interferir con JSON)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Configuración de cabeceras para respuestas JSON
header('Content-Type: application/json; charset=UTF-8');

// Incluir clase de proyecto
require_once '../class/proyecto.php';

try {
    // Verificar que se proporcionó un ID
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        responderError('ID de proyecto no válido');
        exit;
    }
    
    $idProyecto = (int)$_GET['id'];
    
    // Crear instancia del proyecto
    $proyectoObj = new Proyecto();
    
    // Determinar la acción a realizar
    $accion = isset($_GET['accion']) ? $_GET['accion'] : 'obtener';
    
    // Ejecutar acción correspondiente
    switch ($accion) {
        case 'obtener':
            obtenerDetalleProyecto($proyectoObj, $idProyecto);
            break;
            
        case 'comentarios':
            obtenerComentariosProyecto($proyectoObj, $idProyecto);
            break;
            
        case 'documentos':
            obtenerDocumentosProyecto($proyectoObj, $idProyecto);
            break;

        case 'hermanos':
            obtenerHermanosProyecto($proyectoObj, $idProyecto);
            break;

        default:
            responderError('Acción no reconocida: ' . $accion);
            break;
    }
    
} catch (Exception $e) {
    responderError('Error en el servidor: ' . $e->getMessage());
}

/**
 * Obtiene los detalles completos de un proyecto
 */
function obtenerDetalleProyecto($proyectoObj, $idProyecto) {
    try {
        // Obtener datos del proyecto
        $proyecto = $proyectoObj->obtenerProyectoPorId($idProyecto);
        
        if (!$proyecto) {
            responderError('Proyecto no encontrado');
            return;
        }
        
        // Añadir clase para la prioridad
        if (isset($proyecto['Prioridad'])) {
            $prioridadLower = strtolower($proyecto['Prioridad']);
            
            if (strpos($prioridadLower, 'urgente') !== false) {
                $proyecto['clasePrioridad'] = 'prioridad-urgente';
            } elseif (strpos($prioridadLower, 'alta') !== false) {
                $proyecto['clasePrioridad'] = 'prioridad-alta';
            } elseif (strpos($prioridadLower, 'media') !== false) {
                $proyecto['clasePrioridad'] = 'prioridad-media';
            } elseif (strpos($prioridadLower, 'baja') !== false) {
                $proyecto['clasePrioridad'] = 'prioridad-baja';
            } elseif (strpos($prioridadLower, 'sin asignar') !== false || empty($proyecto['Prioridad'])) {
                $proyecto['clasePrioridad'] = 'prioridad-sin-asignar';
                if (empty($proyecto['Prioridad'])) {
                    $proyecto['Prioridad'] = 'Sin asignar';
                }
            } else {
                $proyecto['clasePrioridad'] = 'prioridad-sin-asignar';
            }
        } else {
            $proyecto['Prioridad'] = 'Sin asignar';
            $proyecto['clasePrioridad'] = 'prioridad-sin-asignar';
        }
        
        // Responder con los datos del proyecto
        responderExito('Proyecto obtenido correctamente', array(
            'proyecto' => $proyecto
        ));
        
    } catch (Exception $e) {
        error_log('Error en obtenerDetalleProyecto: ' . $e->getMessage());
        responderError('Error al obtener el proyecto: ' . $e->getMessage());
    }
}

/**
 * Obtiene los comentarios de un proyecto
 */
function obtenerComentariosProyecto($proyectoObj, $idProyecto) {
    try {
        // Obtener comentarios
        $comentarios = $proyectoObj->obtenerComentarios($idProyecto);
        
        // Responder con los comentarios
        responderExito('Comentarios obtenidos correctamente', array(
            'comentarios' => $comentarios
        ));
        
    } catch (Exception $e) {
        error_log('Error en obtenerComentariosProyecto: ' . $e->getMessage());
        responderError('Error al obtener comentarios: ' . $e->getMessage());
    }
}

/**
 * Obtiene los demás proyectos que pertenecen al mismo programa que $idProyecto
 */
function obtenerHermanosProyecto($proyectoObj, $idProyecto) {
    try {
        $proyecto = $proyectoObj->obtenerProyectoPorId($idProyecto);

        if (!$proyecto || empty($proyecto['IdPrograma'])) {
            responderExito('El proyecto no tiene programa asignado', array('hermanos' => array()));
            return;
        }

        $programaObj = new Programa();
        $hermanos = $programaObj->obtenerProyectos($proyecto['IdPrograma'], $idProyecto);

        responderExito('Proyectos del programa obtenidos correctamente', array(
            'hermanos' => $hermanos,
            'nombrePrograma' => $proyecto['NombrePrograma'] ?? null
        ));
    } catch (Exception $e) {
        error_log('Error en obtenerHermanosProyecto: ' . $e->getMessage());
        responderError('Error al obtener proyectos del programa: ' . $e->getMessage());
    }
}

/**
 * Obtiene los documentos de un proyecto
 * Modificado para trabajar solo con almacenamiento local
 */
function obtenerDocumentosProyecto($proyectoObj, $idProyecto) {
    try {
        // Obtener conexión a base de datos
        $conexion = $proyectoObj->getConexion();
        
        // Consulta para obtener documentos (sin columnas de Google Drive)
        $sql = "SELECT 
                    ID, 
                    NombreOriginal, 
                    NombreArchivo, 
                    TipoArchivo, 
                    TamanoArchivo, 
                    FechaSubida
                FROM DocumentosProyectos 
                WHERE ID_Proyecto = ?
                ORDER BY FechaSubida DESC";
        
        $params = array(
            array($idProyecto, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_INT)
        );
        
        $stmt = sqlsrv_prepare($conexion, $sql, $params);
        
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta para obtener documentos: ' . print_r(sqlsrv_errors(), true));
        }
        
        if (!sqlsrv_execute($stmt)) {
            throw new Exception('Error al obtener documentos: ' . print_r(sqlsrv_errors(), true));
        }
        
        $documentos = array();
        
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Formatear fecha
            $fecha = $row['FechaSubida'] ? $row['FechaSubida']->format('Y-m-d') : 'Desconocida';
            
            // Formatear tamaño
            $tamano = formatearTamano($row['TamanoArchivo']);
            
            // Construir la URL local para el archivo - CORREGIDA
            $rutaArchivo = 'api/servirDocumento.php?proyecto=' . $idProyecto . '&archivo=' . urlencode($row['NombreArchivo']);
            
            // Verificar que el archivo físico existe
            $rutaFisica = __DIR__ . '/../documentos/' . $idProyecto . '/' . $row['NombreArchivo'];
            $archivoExiste = file_exists($rutaFisica);
            
            // Log para depuración
            error_log("Archivo: " . $row['NombreArchivo'] . " - Existe: " . ($archivoExiste ? 'SI' : 'NO') . " - Ruta: " . $rutaFisica);
            
            $documentos[] = array(
                'id' => $row['ID'],
                'nombre' => $row['NombreOriginal'],
                'archivo' => $row['NombreArchivo'],
                'tipo' => $row['TipoArchivo'],
                'tamano' => $tamano,
                'fecha' => $fecha,
                'usuario' => 'Admin', // En una implementación real, obtendríamos el usuario que subió el archivo
                'url' => $rutaArchivo,
                'existe' => $archivoExiste // Para depuración
            );
        }
        
        // Log del resultado
        error_log("Documentos encontrados: " . count($documentos));
        
        // Responder con los documentos
        responderExito('Documentos obtenidos correctamente', array(
            'documentos' => $documentos
        ));
        
    } catch (Exception $e) {
        error_log('Error en obtenerDocumentosProyecto: ' . $e->getMessage());
        responderError('Error al obtener documentos: ' . $e->getMessage());
    }
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
    
    echo json_encode($respuesta);
    exit;
}

/**
 * Genera una respuesta de error en formato JSON
 */
function responderError($mensaje, $codigo = 400) {
    http_response_code($codigo);
    
    echo json_encode(array(
        'exito' => false,
        'error' => $mensaje
    ));
    
    exit;
}