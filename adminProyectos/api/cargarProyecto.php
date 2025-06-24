
<?php
/**
 * API para cargar proyectos
 * Maneja tanto la obtención de solicitantes como el guardado de proyectos
 * Los proyectos nuevos entran con Estado y EtapaProyecto = "Pendiente"
 */

// Configuración para mostrar errores (solo para desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuración de cabeceras para respuestas JSON con UTF-8
header('Content-Type: application/json; charset=UTF-8');

// Incluir clase de proyecto
require_once '../class/proyecto.php';

try {
    // Crear instancia del proyecto
    $proyectoObj = new Proyecto();
    
    // Determinar la acción a realizar
    $accion = $_REQUEST['accion'] ?? '';
    
    switch ($accion) {
        case 'obtenerSolicitantes':
            obtenerSolicitantes($proyectoObj);
            break;
            
        case 'guardarProyecto':
            guardarProyecto($proyectoObj);
            break;
            
        default:
            responderError('Acción no especificada o no válida');
            break;
    }
    
} catch (Exception $e) {
    error_log('Error en cargarProyecto.php: ' . $e->getMessage());
    responderError('Error interno del servidor: ' . $e->getMessage());
}

/**
 * Obtiene la lista de solicitantes
 */
function obtenerSolicitantes($proyectoObj) {
    try {
        $solicitantes = $proyectoObj->obtenerSolicitantes();
        
        if ($solicitantes && is_array($solicitantes)) {
            responderExito('Solicitantes obtenidos correctamente', [
                'solicitantes' => $solicitantes
            ]);
        } else {
            responderError('No se pudieron obtener los solicitantes');
        }
        
    } catch (Exception $e) {
        error_log('Error en obtenerSolicitantes: ' . $e->getMessage());
        responderError('Error al obtener solicitantes: ' . $e->getMessage());
    }
}

/**
 * Función para limpiar texto y asegurar UTF-8
 */
function limpiarTextoUTF8($texto) {
    if (!is_string($texto)) {
        return $texto;
    }
    
    // Detectar encoding y convertir si es necesario
    $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252'];
    $encoding = mb_detect_encoding($texto, $encodings, true);
    
    if ($encoding && $encoding !== 'UTF-8') {
        $texto = mb_convert_encoding($texto, 'UTF-8', $encoding);
    }
    
    // Limpiar caracteres problemáticos específicos
    $texto = str_replace(['â€œ', 'â€', 'â€™', 'Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº', 'Ã±'], 
                        ['"', '"', "'", 'á', 'é', 'í', 'ó', 'ú', 'ñ'], $texto);
    
    return trim($texto);
}

/**
 * Guarda un nuevo proyecto
 */
function guardarProyecto($proyectoObj) {
    try {
        // Validar datos requeridos
        $camposRequeridos = ['tipo', 'desarrollo', 'solicitante', 'prioridad', 'pais', 'sector', 'funcionalidad'];
        $datosProyecto = [];
        
        foreach ($camposRequeridos as $campo) {
            if (empty($_POST[$campo])) {
                responderError("El campo '$campo' es requerido");
                return;
            }
            // Limpiar y asegurar UTF-8
            $datosProyecto[$campo] = limpiarTextoUTF8($_POST[$campo]);
        }
        
        // Log para debugging
        error_log('Datos procesados con UTF-8: ' . print_r($datosProyecto, true));
        
        // Mapear los campos a los nombres de la base de datos
        $datosParaDB = [
            'TipoPresentacion' => $datosProyecto['tipo'],
            'Desarrollo' => $datosProyecto['desarrollo'],
            'Solicitante' => $datosProyecto['solicitante'],
            'Prioridad' => $datosProyecto['prioridad'],
            'Pais' => $datosProyecto['pais'],
            'Sector' => $datosProyecto['sector'],
            'Funcionalidad' => $datosProyecto['funcionalidad'],
            
            // Campos por defecto para proyectos nuevos
            'Estado' => 'Pendiente',
            'EtapaProyecto' => 'Pendiente',
            'FechaCreacion' => date('Y-m-d'),
            'PorcentajeAvance' => 0
        ];
        
        // Crear el proyecto - ahora retorna el nombre del proyecto
        $nombreProyecto = $proyectoObj->crearProyecto($datosParaDB);
        
        if ($nombreProyecto) {
            // Obtener un número de proyecto aproximado (últimos registros)
            $numeroProyecto = obtenerNumeroProyectoAproximado($proyectoObj, $nombreProyecto);
            
            $mensajeExito = "Se ha cargado exitosamente el proyecto \"$nombreProyecto\"";
            if ($numeroProyecto) {
                $mensajeExito .= " con el número $numeroProyecto";
            }
            
            responderExito($mensajeExito, [
                'nombreProyecto' => $nombreProyecto,
                'numeroProyecto' => $numeroProyecto
            ]);
        } else {
            responderError('No se pudo guardar el proyecto');
        }
        
    } catch (Exception $e) {
        error_log('Error en guardarProyecto: ' . $e->getMessage());
        responderError('Error al guardar el proyecto: ' . $e->getMessage());
    }
}

/**
 * Obtiene un número de proyecto aproximado para el mensaje
 */
function obtenerNumeroProyectoAproximado($proyectoObj, $nombreProyecto) {
    try {
        $conexion = $proyectoObj->getConexion();
        
        // Buscar el proyecto recién insertado
        $sql = "SELECT TOP 1 ID FROM HistorialProyectos 
                WHERE Desarrollo = ? 
                ORDER BY ID DESC";
        
        $params = array($nombreProyecto);
        $stmt = sqlsrv_query($conexion, $sql, $params);
        
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            sqlsrv_free_stmt($stmt);
            return $row['ID'];
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log('Error al obtener número de proyecto: ' . $e->getMessage());
        return null;
    }
}

/**
 * Genera una respuesta de éxito en formato JSON
 */
function responderExito($mensaje, $datos = []) {
    $respuesta = [
        'exito' => true,
        'mensaje' => $mensaje
    ];
    
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
    
    echo json_encode([
        'exito' => false,
        'mensaje' => $mensaje
    ], JSON_UNESCAPED_UNICODE);
    
    exit;
}
?>