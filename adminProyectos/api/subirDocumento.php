
<?php
/**
 * API para subir documentos a un proyecto específico
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
    
    // Procesar documentos usando la función existente
    $conexion = $proyectoObj->getConexion();
    
    // Incluir la función procesarDocumentos desde cargarProyecto.php
    require_once 'cargarProyecto.php';
    
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
    responderError('Error en el servidor: ' . $e->getMessage());
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
        'mensaje' => $mensaje
    ));
    
    exit;
}
?>