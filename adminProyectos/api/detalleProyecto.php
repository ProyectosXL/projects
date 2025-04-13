
<?php
/**
 * API para gestionar detalles de proyectos
 */

// Configuración para mostrar errores (solo para desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuración de cabeceras para respuestas JSON
header('Content-Type: application/json');

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
            } else {
                $proyecto['clasePrioridad'] = '';
            }
        }
        
        // Responder con los datos del proyecto
        responderExito('Proyecto obtenido correctamente', array(
            'proyecto' => $proyecto
        ));
        
    } catch (Exception $e) {
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
        responderError('Error al obtener comentarios: ' . $e->getMessage());
    }
}

/**
 * Obtiene los documentos de un proyecto
 */
function obtenerDocumentosProyecto($proyectoObj, $idProyecto) {
    try {
        // En una implementación real, aquí se obtendrían los documentos desde la base de datos
        // Para esta simulación, creamos algunos documentos de ejemplo
        
        $documentos = array(
            array(
                'id' => 1,
                'nombre' => 'Especificación de requerimientos.pdf',
                'tipo' => 'application/pdf',
                'tamano' => '2.4 MB',
                'fecha' => date('Y-m-d'),
                'usuario' => 'Admin',
                'url' => '#'
            ),
            array(
                'id' => 2,
                'nombre' => 'Cronograma de proyecto.xlsx',
                'tipo' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'tamano' => '1.2 MB',
                'fecha' => date('Y-m-d', strtotime("-1 day")),
                'usuario' => 'Admin',
                'url' => '#'
            ),
            array(
                'id' => 3,
                'nombre' => 'Diagrama de arquitectura.png',
                'tipo' => 'image/png',
                'tamano' => '0.8 MB',
                'fecha' => date('Y-m-d', strtotime("-2 day")),
                'usuario' => 'Admin',
                'url' => '#'
            )
        );
        
        // Responder con los documentos
        responderExito('Documentos obtenidos correctamente', array(
            'documentos' => $documentos
        ));
        
    } catch (Exception $e) {
        responderError('Error al obtener documentos: ' . $e->getMessage());
    }
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