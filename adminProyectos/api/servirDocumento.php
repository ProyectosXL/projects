
<?php
/**
 * Script para servir documentos de forma segura
 * Verifica que el usuario tenga permisos para acceder al documento
 */

// Incluir clase de proyecto para verificaciones
require_once 'class/proyecto.php';

try {
    // Verificar parámetros
    if (!isset($_GET['proyecto']) || !isset($_GET['archivo'])) {
        http_response_code(400);
        die('Parámetros faltantes');
    }
    
    $proyectoId = (int)$_GET['proyecto'];
    $nombreArchivo = basename($_GET['archivo']); // basename por seguridad
    
    // Verificar que el proyecto existe
    $proyectoObj = new Proyecto();
    $proyecto = $proyectoObj->obtenerProyectoPorId($proyectoId);
    
    if (!$proyecto) {
        http_response_code(404);
        die('Proyecto no encontrado');
    }
    
    // Verificar que el documento existe en la base de datos
    $conexion = $proyectoObj->getConexion();
    $sql = "SELECT NombreOriginal, TipoArchivo FROM DocumentosProyectos 
            WHERE ID_Proyecto = ? AND NombreArchivo = ?";
    $params = array($proyectoId, $nombreArchivo);
    $stmt = sqlsrv_query($conexion, $sql, $params);
    
    if ($stmt === false) {
        http_response_code(500);
        die('Error en la consulta');
    }
    
    $documento = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if (!$documento) {
        http_response_code(404);
        die('Documento no encontrado');
    }
    
    // Construir ruta del archivo
    $rutaArchivo = __DIR__ . '/documentos/' . $proyectoId . '/' . $nombreArchivo;
    
    // Verificar que el archivo existe físicamente
    if (!file_exists($rutaArchivo)) {
        http_response_code(404);
        die('Archivo no encontrado');
    }
    
    // Determinar tipo MIME
    $tipoMime = $documento['TipoArchivo'] ?: mime_content_type($rutaArchivo);
    
    // Configurar headers para descarga
    header('Content-Type: ' . $tipoMime);
    header('Content-Disposition: attachment; filename="' . $documento['NombreOriginal'] . '"');
    header('Content-Length: ' . filesize($rutaArchivo));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Enviar archivo
    readfile($rutaArchivo);
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error del servidor: ' . $e->getMessage());
}
?>