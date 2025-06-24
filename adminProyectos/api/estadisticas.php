<?php
/**
 * API para estadísticas de proyectos
 */

header('Content-Type: application/json; charset=UTF-8');

// Activar visualización de errores para depuración
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // Incluir la clase Proyecto
    require_once '../class/proyecto.php';
    
    // Crear una instancia de la clase Proyecto
    $proyectoObj = new Proyecto();
    
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $filtroSector = isset($_GET['sector']) ? $_GET['sector'] : '';
    $filtroLider = isset($_GET['lider']) ? $_GET['lider'] : '';
    $filtroEstado = isset($_GET['estado']) ? $_GET['estado'] : '';
    
    switch ($action) {
        case 'estados':
            // Estados NUNCA se filtran - siempre sin parámetros
            obtenerEstadisticasEstados($proyectoObj);
            break;
            
        case 'finalizados-por-año':
            obtenerProyectosFinalizadosPorAño($proyectoObj, $filtroSector, $filtroLider, $filtroEstado);
            break;
            
        case 'sectores':
            obtenerEstadisticasSectores($proyectoObj, $filtroLider, $filtroEstado);
            break;
            
        case 'filtros':
            obtenerFiltrosEstadisticas($proyectoObj);
            break;
            
        default:
            echo json_encode([
                'error' => 'Acción no válida'
            ]);
    }
    
} catch (Exception $e) {
    // Log del error para depuración
    error_log("Error en API estadísticas: " . $e->getMessage());
    
    // Devolver respuesta de error en formato JSON
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

/**
 * Obtiene estadísticas de estados de proyectos
 * NUNCA se filtra - siempre muestra todos los estados
 */
function obtenerEstadisticasEstados($proyectoObj) {
    try {
        $conexion = $proyectoObj->getConexion();
        
        $sql = "SELECT Estado, COUNT(*) as cantidad 
                FROM HistorialProyectos 
                GROUP BY Estado 
                ORDER BY cantidad DESC";
        
        $stmt = sqlsrv_query($conexion, $sql);
        
        if ($stmt === false) {
            throw new Exception('Error al obtener estadísticas de estados: ' . print_r(sqlsrv_errors(), true));
        }
        
        $estadisticas = array();
        $total = 0;
        
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $estado = $row['Estado'] ?: 'Sin Estado';
            $cantidad = intval($row['cantidad']);
            $total += $cantidad;
            
            $estadisticas[] = array(
                'estado' => $estado,
                'cantidad' => $cantidad
            );
        }
        
        // Calcular porcentajes
        foreach ($estadisticas as &$item) {
            $item['porcentaje'] = $total > 0 ? round(($item['cantidad'] / $total) * 100, 1) : 0;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $estadisticas,
            'total' => $total
        ]);
        
    } catch (Exception $e) {
        error_log('Error en obtenerEstadisticasEstados: ' . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Obtiene proyectos finalizados por año
 */
function obtenerProyectosFinalizadosPorAño($proyectoObj, $filtroSector = '', $filtroLider = '', $filtroEstado = '') {
    try {
        $conexion = $proyectoObj->getConexion();
        
        // Si no se especifica estado, usar 'Terminado' por defecto
        // Si se especifica estado y NO es 'Terminado', mostrar años de creación en lugar de finalización
        $usarFechaFinalizacion = true;
        
        if (!empty($filtroEstado) && $filtroEstado !== 'Terminado') {
            // Para estados que no son "Terminado", usar fecha de creación
            $sql = "SELECT YEAR(FechaCreacion) as año, COUNT(*) as cantidad 
                    FROM HistorialProyectos 
                    WHERE Estado = ?";
            $params = array($filtroEstado);
            $usarFechaFinalizacion = false;
        } else {
            // Para estado "Terminado" o sin filtro de estado, usar fecha de finalización
            $sql = "SELECT YEAR(FinalizacionReal) as año, COUNT(*) as cantidad 
                    FROM HistorialProyectos 
                    WHERE Estado = 'Terminado' AND FinalizacionReal IS NOT NULL";
            $params = array();
        }
        
        if (!empty($filtroSector)) {
            $sql .= " AND Sector = ?";
            $params[] = $filtroSector;
        }
        
        if (!empty($filtroLider)) {
            $sql .= " AND LiderProyecto = ?";
            $params[] = $filtroLider;
        }
        
        $sql .= $usarFechaFinalizacion ? 
                " GROUP BY YEAR(FinalizacionReal) ORDER BY año ASC" : 
                " GROUP BY YEAR(FechaCreacion) ORDER BY año ASC";
        
        $stmt = sqlsrv_query($conexion, $sql, $params);
        
        if ($stmt === false) {
            throw new Exception('Error al obtener proyectos por año: ' . print_r(sqlsrv_errors(), true));
        }
        
        $estadisticas = array();
        
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $año = intval($row['año']);
            $cantidad = intval($row['cantidad']);
            
            $estadisticas[] = array(
                'año' => $año,
                'cantidad' => $cantidad
            );
        }
        
        echo json_encode([
            'success' => true,
            'data' => $estadisticas,
            'usaFechaFinalizacion' => $usarFechaFinalizacion,
            'estadoFiltrado' => $filtroEstado
        ]);
        
    } catch (Exception $e) {
        error_log('Error en obtenerProyectosFinalizadosPorAño: ' . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Obtiene estadísticas de sectores
 */
function obtenerEstadisticasSectores($proyectoObj, $filtroLider = '', $filtroEstado = '') {
    try {
        $conexion = $proyectoObj->getConexion();
        
        $sql = "SELECT Sector, COUNT(*) as cantidad 
                FROM HistorialProyectos 
                WHERE 1=1";
        
        $params = array();
        
        if (!empty($filtroLider)) {
            $sql .= " AND LiderProyecto = ?";
            $params[] = $filtroLider;
        }
        
        if (!empty($filtroEstado)) {
            $sql .= " AND Estado = ?";
            $params[] = $filtroEstado;
        }
        
        $sql .= " GROUP BY Sector ORDER BY cantidad DESC";
        
        $stmt = sqlsrv_query($conexion, $sql, $params);
        
        if ($stmt === false) {
            throw new Exception('Error al obtener estadísticas de sectores: ' . print_r(sqlsrv_errors(), true));
        }
        
        $estadisticas = array();
        $total = 0;
        
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $sector = $row['Sector'] ?: 'Sin Sector';
            $cantidad = intval($row['cantidad']);
            $total += $cantidad;
            
            $estadisticas[] = array(
                'sector' => $sector,
                'cantidad' => $cantidad
            );
        }
        
        // Calcular porcentajes
        foreach ($estadisticas as &$item) {
            $item['porcentaje'] = $total > 0 ? round(($item['cantidad'] / $total) * 100, 1) : 0;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $estadisticas,
            'total' => $total
        ]);
        
    } catch (Exception $e) {
        error_log('Error en obtenerEstadisticasSectores: ' . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Obtiene filtros disponibles para estadísticas
 */
function obtenerFiltrosEstadisticas($proyectoObj) {
    try {
        $conexion = $proyectoObj->getConexion();
        
        // Obtener sectores únicos
        $sqlSectores = "SELECT DISTINCT Sector FROM HistorialProyectos WHERE Sector IS NOT NULL ORDER BY Sector";
        $resultSectores = sqlsrv_query($conexion, $sqlSectores);
        
        $sectores = array();
        if ($resultSectores !== false) {
            while ($row = sqlsrv_fetch_array($resultSectores, SQLSRV_FETCH_ASSOC)) {
                $sectores[] = $row['Sector'];
            }
        }
        
        // Obtener líderes únicos
        $sqlLideres = "SELECT DISTINCT LiderProyecto FROM HistorialProyectos WHERE LiderProyecto IS NOT NULL ORDER BY LiderProyecto";
        $resultLideres = sqlsrv_query($conexion, $sqlLideres);
        
        $lideres = array();
        if ($resultLideres !== false) {
            while ($row = sqlsrv_fetch_array($resultLideres, SQLSRV_FETCH_ASSOC)) {
                $lideres[] = $row['LiderProyecto'];
            }
        }
        
        // Obtener estados únicos
        $sqlEstados = "SELECT DISTINCT Estado FROM HistorialProyectos WHERE Estado IS NOT NULL ORDER BY Estado";
        $resultEstados = sqlsrv_query($conexion, $sqlEstados);
        
        $estados = array();
        if ($resultEstados !== false) {
            while ($row = sqlsrv_fetch_array($resultEstados, SQLSRV_FETCH_ASSOC)) {
                $estados[] = $row['Estado'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'sectores' => $sectores,
            'lideres' => $lideres,
            'estados' => $estados
        ]);
        
    } catch (Exception $e) {
        error_log('Error en obtenerFiltrosEstadisticas: ' . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>