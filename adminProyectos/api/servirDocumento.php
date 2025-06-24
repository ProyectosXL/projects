
<?php
/**
 * Script para servir documentos de forma segura
 * Verifica que el usuario tenga permisos para acceder al documento
 */

// Incluir clase de proyecto para verificaciones
require_once __DIR__ . '/../class/proyecto.php';

try {
    // Verificar parámetros
    if (!isset($_GET['proyecto']) || !isset($_GET['archivo'])) {
        http_response_code(400);
        die('Parámetros faltantes');
    }
    
    $proyectoId = (int)$_GET['proyecto'];
    $nombreArchivo = basename($_GET['archivo']); // basename por seguridad
    
    // Log para depuración
    error_log("Intentando servir archivo: $nombreArchivo para proyecto: $proyectoId");
    
    // Verificar que el proyecto existe
    $proyectoObj = new Proyecto();
    $proyecto = $proyectoObj->obtenerProyectoPorId($proyectoId);
    
    if (!$proyecto) {
        error_log("Proyecto no encontrado: $proyectoId");
        http_response_code(404);
        die('Proyecto no encontrado');
    }
    
    // Verificar que el documento existe en la base de datos
    $conexion = $proyectoObj->getConexion();
    $sql = "SELECT NombreOriginal, TipoArchivo, TamanoArchivo FROM DocumentosProyectos 
            WHERE ID_Proyecto = ? AND NombreArchivo = ?";
    $params = array($proyectoId, $nombreArchivo);
    $stmt = sqlsrv_query($conexion, $sql, $params);
    
    if ($stmt === false) {
        error_log("Error en la consulta: " . print_r(sqlsrv_errors(), true));
        http_response_code(500);
        die('Error en la consulta');
    }
    
    $documento = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if (!$documento) {
        error_log("Documento no encontrado en BD: $nombreArchivo");
        http_response_code(404);
        die('Documento no encontrado');
    }
    
    // Construir ruta del archivo
    $rutaArchivo = __DIR__ . '/../documentos/' . $proyectoId . '/' . $nombreArchivo;
    
    error_log("Ruta del archivo: $rutaArchivo");
    
    // Verificar que el archivo existe físicamente
    if (!file_exists($rutaArchivo)) {
        error_log("Archivo físico no encontrado: $rutaArchivo");
        http_response_code(404);
        die('Archivo no encontrado');
    }
    
    // Verificar que el archivo es legible
    if (!is_readable($rutaArchivo)) {
        error_log("Archivo no legible: $rutaArchivo");
        http_response_code(403);
        die('No se puede leer el archivo');
    }
    
    // Determinar tipo MIME
    $tipoMime = $documento['TipoArchivo'];
    if (!$tipoMime) {
        $tipoMime = mime_content_type($rutaArchivo);
    }
    if (!$tipoMime) {
        $tipoMime = 'application/octet-stream';
    }
    
    // Limpiar cualquier salida previa
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Configurar headers para descarga
    header('Content-Type: ' . $tipoMime);
    header('Content-Disposition: attachment; filename="' . $documento['NombreOriginal'] . '"');
    header('Content-Length: ' . filesize($rutaArchivo));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Leer y enviar archivo en chunks para archivos grandes
    $handle = fopen($rutaArchivo, 'rb');
    if ($handle === false) {
        error_log("No se pudo abrir el archivo: $rutaArchivo");
        http_response_code(500);
        die('Error al abrir el archivo');
    }
    
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    
    fclose($handle);
    
    error_log("Archivo servido exitosamente: $nombreArchivo");
    
} catch (Exception $e) {
    error_log('Error en servirDocumento.php: ' . $e->getMessage());
    http_response_code(500);
    die('Error del servidor: ' . $e->getMessage());
}
?>