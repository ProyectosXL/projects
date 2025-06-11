
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

// Configuración de cabeceras para respuestas JSON
header('Content-Type: application/json');

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
            $datosProyecto[$campo] = trim($_POST[$campo]);
        }
        
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
        
        // Log para debugging
        error_log('Datos para crear proyecto: ' . print_r($datosParaDB, true));
        
        // Crear el proyecto
        $proyectoId = $proyectoObj->crearProyecto($datosParaDB);
        
        if ($proyectoId) {
            responderExito('Proyecto guardado correctamente', [
                'proyectoId' => $proyectoId,
                'mensaje' => 'El proyecto se ha guardado exitosamente con ID: ' . $proyectoId . '. Estado inicial: Pendiente.'
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