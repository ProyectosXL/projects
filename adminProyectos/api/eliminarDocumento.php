<?php
/**
 * API para eliminar documentos de un proyecto
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
    // Verificar que se envió por DELETE
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        responderError('Método no permitido', 405);
        exit;
    }
    
    // Verificar que se proporcionó un ID de documento
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        responderError('ID de documento no válido');
        exit;
    }
    
    $documentoId = (int)$_GET['id'];
    
    // Crear instancia del proyecto
    $proyectoObj = new Proyecto();
    $conexion = $proyectoObj->getConexion();
    
    // Obtener información del documento antes de eliminarlo
    $sql = "SELECT ID_Proyecto, NombreArchivo FROM DocumentosProyectos WHERE ID = ?";
    $params = array($documentoId);
    $stmt = sqlsrv_query($conexion, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception('Error al consultar el documento: ' . print_r(sqlsrv_errors(), true));
    }
    
    $documento = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if (!$documento) {
        responderError('Documento no encontrado');
        exit;
    }
    
    // Eliminar archivo físico
    $rutaArchivo = __DIR__ . '/../documentos/' . $documento['ID_Proyecto'] . '/' . $documento['NombreArchivo'];
    
    if (file_exists($rutaArchivo)) {
        if (!unlink($rutaArchivo)) {
            error_log("No se pudo eliminar el archivo físico: $rutaArchivo");
            // Continuar con la eliminación de la base de datos aunque falle el archivo
        }
    }
    
    // Eliminar registro de la base de datos
    $sql = "DELETE FROM DocumentosProyectos WHERE ID = ?";
    $params = array($documentoId);
    $stmt = sqlsrv_query($conexion, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception('Error al eliminar el documento de la base de datos: ' . print_r(sqlsrv_errors(), true));
    }
    
    // Verificar que se eliminó correctamente
    $filasAfectadas = sqlsrv_rows_affected($stmt);
    
    if ($filasAfectadas === 0) {
        responderError('No se encontró el documento para eliminar');
        exit;
    }
    
    // Responder con éxito
    responderExito('Documento eliminado correctamente');
    
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