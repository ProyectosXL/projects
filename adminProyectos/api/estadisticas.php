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
    
    $action       = isset($_GET['action']) ? $_GET['action'] : '';
    $filtroSector = isset($_GET['sector']) ? $_GET['sector'] : '';
    $filtroLider  = isset($_GET['lider'])  ? $_GET['lider']  : '';
    $filtroEstado = isset($_GET['estado']) ? $_GET['estado'] : '';
    $filtroAno    = isset($_GET['year'])   ? intval($_GET['year'])  : intval(date('Y'));
    $filtroMes    = isset($_GET['month'])  ? intval($_GET['month']) : 0;

    switch ($action) {
        case 'estados':
            obtenerEstadisticasEstados($proyectoObj, $filtroSector, $filtroLider, $filtroAno);
            break;

        case 'finalizados-por-año':
            // Historical chart — intentionally no year filter
            obtenerProyectosFinalizadosPorAño($proyectoObj, $filtroSector, $filtroLider, $filtroEstado);
            break;

        case 'sectores':
            obtenerEstadisticasSectores($proyectoObj, $filtroLider, $filtroEstado, $filtroAno);
            break;

        case 'completados-vs-plan':
            obtenerCompletadosVsPlan($proyectoObj, $filtroSector, $filtroLider, $filtroAno);
            break;

        case 'kpi-resumen':
            obtenerKPIResumen($proyectoObj, $filtroSector, $filtroLider, $filtroAno, $filtroMes);
            break;

        case 'kpi-detalle':
            $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
            obtenerKPIDetalle($proyectoObj, $tipo, $filtroSector, $filtroLider, $filtroAno, $filtroMes);
            break;

        case 'filtros':
            obtenerFiltrosEstadisticas($proyectoObj);
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
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
 * Obtiene estadísticas de estados de proyectos.
 * Filtrado por año de creación, sector y líder (nunca por estado).
 */
function obtenerEstadisticasEstados($proyectoObj, $filtroSector = '', $filtroLider = '', $filtroAno = 0) {
    try {
        $conexion = $proyectoObj->getConexion();
        $ano = $filtroAno > 0 ? $filtroAno : intval(date('Y'));

        $sql = "SELECT Estado, COUNT(*) as cantidad
                FROM HistorialProyectos
                WHERE YEAR(FechaCreacion) = ?";

        $params = array($ano);

        if (!empty($filtroSector)) {
            $sql .= " AND Sector = ?";
            $params[] = $filtroSector;
        }

        if (!empty($filtroLider)) {
            $sql .= " AND LiderProyecto = ?";
            $params[] = $filtroLider;
        }

        $sql .= " GROUP BY Estado ORDER BY cantidad DESC";
        
        $stmt = sqlsrv_query($conexion, $sql, $params);
        
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
 * Obtiene estadísticas de sectores.
 * Filtrado por año de creación, líder y estado.
 */
function obtenerEstadisticasSectores($proyectoObj, $filtroLider = '', $filtroEstado = '', $filtroAno = 0) {
    try {
        $conexion = $proyectoObj->getConexion();
        $ano = $filtroAno > 0 ? $filtroAno : intval(date('Y'));

        $sql = "SELECT Sector, COUNT(*) as cantidad
                FROM HistorialProyectos
                WHERE YEAR(FechaCreacion) = ?";

        $params = array($ano);

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
 * Completados vs Plan por mes del año seleccionado.
 * Devuelve 12 filas con: planificados, completados, acumulado, acumulado_plan, pct_sla.
 */
function obtenerCompletadosVsPlan($proyectoObj, $filtroSector = '', $filtroLider = '', $filtroAno = 0) {
    try {
        $conexion = $proyectoObj->getConexion();
        $ano = $filtroAno > 0 ? $filtroAno : intval(date('Y'));

        // Proyectos planificados: FechaLimite en el año seleccionado
        $sqlPlan = "SELECT MONTH(FechaLimite) AS mes, COUNT(*) AS cantidad
                    FROM HistorialProyectos
                    WHERE FechaLimite IS NOT NULL AND YEAR(FechaLimite) = ?";
        $paramsPlan = array($ano);
        if (!empty($filtroSector)) { $sqlPlan .= " AND Sector = ?";        $paramsPlan[] = $filtroSector; }
        if (!empty($filtroLider))  { $sqlPlan .= " AND LiderProyecto = ?"; $paramsPlan[] = $filtroLider; }
        $sqlPlan .= " GROUP BY MONTH(FechaLimite)";

        // Proyectos completados: FinalizacionReal en el año seleccionado y Estado = Terminado
        $sqlComp = "SELECT MONTH(FinalizacionReal) AS mes, COUNT(*) AS cantidad
                    FROM HistorialProyectos
                    WHERE FinalizacionReal IS NOT NULL AND Estado = 'Terminado' AND YEAR(FinalizacionReal) = ?";
        $paramsComp = array($ano);
        if (!empty($filtroSector)) { $sqlComp .= " AND Sector = ?";        $paramsComp[] = $filtroSector; }
        if (!empty($filtroLider))  { $sqlComp .= " AND LiderProyecto = ?"; $paramsComp[] = $filtroLider; }
        $sqlComp .= " GROUP BY MONTH(FinalizacionReal)";

        // SLA mensual: de los proyectos con FechaLimite en el mes/año, cuántos cerraron a tiempo
        $sqlSLA = "SELECT MONTH(FechaLimite) AS mes,
                          COUNT(*) AS total_fl,
                          SUM(CASE WHEN Estado = 'Terminado' AND FinalizacionReal IS NOT NULL
                                        AND FinalizacionReal <= FechaLimite THEN 1 ELSE 0 END) AS a_tiempo
                   FROM HistorialProyectos
                   WHERE FechaLimite IS NOT NULL AND YEAR(FechaLimite) = ?";
        $paramsSLA = array($ano);
        if (!empty($filtroSector)) { $sqlSLA .= " AND Sector = ?";        $paramsSLA[] = $filtroSector; }
        if (!empty($filtroLider))  { $sqlSLA .= " AND LiderProyecto = ?"; $paramsSLA[] = $filtroLider; }
        $sqlSLA .= " GROUP BY MONTH(FechaLimite)";

        $planificados = array_fill(1, 12, 0);
        $completados  = array_fill(1, 12, 0);
        $slaData      = array_fill(1, 12, array('total' => 0, 'a_tiempo' => 0));

        $stmtPlan = sqlsrv_query($conexion, $sqlPlan, $paramsPlan);
        if ($stmtPlan === false) throw new Exception('Error planificados: ' . print_r(sqlsrv_errors(), true));
        while ($row = sqlsrv_fetch_array($stmtPlan, SQLSRV_FETCH_ASSOC)) {
            $planificados[intval($row['mes'])] = intval($row['cantidad']);
        }

        $stmtComp = sqlsrv_query($conexion, $sqlComp, $paramsComp);
        if ($stmtComp === false) throw new Exception('Error completados: ' . print_r(sqlsrv_errors(), true));
        while ($row = sqlsrv_fetch_array($stmtComp, SQLSRV_FETCH_ASSOC)) {
            $completados[intval($row['mes'])] = intval($row['cantidad']);
        }

        $stmtSLA = sqlsrv_query($conexion, $sqlSLA, $paramsSLA);
        if ($stmtSLA === false) throw new Exception('Error SLA mensual: ' . print_r(sqlsrv_errors(), true));
        while ($row = sqlsrv_fetch_array($stmtSLA, SQLSRV_FETCH_ASSOC)) {
            $slaData[intval($row['mes'])] = array(
                'total'    => intval($row['total_fl']),
                'a_tiempo' => intval($row['a_tiempo'])
            );
        }

        $result          = array();
        $acumComp        = 0;
        $acumPlan        = 0;
        $acumSLATotal    = 0;
        $acumSLAaTiempo  = 0;
        for ($i = 1; $i <= 12; $i++) {
            $acumComp       += $completados[$i];
            $acumPlan       += $planificados[$i];
            $sla             = $slaData[$i];
            $acumSLATotal   += $sla['total'];
            $acumSLAaTiempo += $sla['a_tiempo'];
            $pctSLA          = $sla['total']    > 0 ? round(($sla['a_tiempo']    / $sla['total'])    * 100, 1) : null;
            $pctSLAacum      = $acumSLATotal    > 0 ? round(($acumSLAaTiempo    / $acumSLATotal)     * 100, 1) : null;
            $result[] = array(
                'mes'              => $i,
                'planificados'     => $planificados[$i],
                'completados'      => $completados[$i],
                'acumulado'        => $acumComp,
                'acumulado_plan'   => $acumPlan,
                'pct_sla'          => $pctSLA,
                'pct_sla_acumulado'=> $pctSLAacum
            );
        }

        echo json_encode(array('success' => true, 'data' => $result));

    } catch (Exception $e) {
        error_log('Error en obtenerCompletadosVsPlan: ' . $e->getMessage());
        echo json_encode(array('error' => $e->getMessage()));
    }
}

/**
 * KPIs globales del año seleccionado:
 *   - pct_completados : % proyectos con FechaLimite en el año que fueron Terminados
 *   - pct_sla         : % de esos terminados cuya FinalizacionReal <= FechaLimite
 */
function obtenerKPIResumen($proyectoObj, $filtroSector = '', $filtroLider = '', $filtroAno = 0, $filtroMes = 0) {
    try {
        $conexion = $proyectoObj->getConexion();
        $ano = $filtroAno > 0 ? $filtroAno : intval(date('Y'));

        $sql = "SELECT
                    COUNT(*) AS total_plan,
                    SUM(CASE WHEN Estado = 'Terminado' AND FinalizacionReal IS NOT NULL
                             THEN 1 ELSE 0 END) AS completados,
                    SUM(CASE WHEN Estado = 'Terminado' AND FinalizacionReal IS NOT NULL
                                  AND FinalizacionReal <= FechaLimite
                             THEN 1 ELSE 0 END) AS a_tiempo
                FROM HistorialProyectos
                WHERE FechaLimite IS NOT NULL AND YEAR(FechaLimite) = ?";
        $params = array($ano);
        if ($filtroMes > 0)        { $sql .= " AND MONTH(FechaLimite) = ?"; $params[] = $filtroMes; }
        if (!empty($filtroSector)) { $sql .= " AND Sector = ?";             $params[] = $filtroSector; }
        if (!empty($filtroLider))  { $sql .= " AND LiderProyecto = ?";      $params[] = $filtroLider; }
        $sql .= " AND (Estado IS NULL OR Estado <> 'Pausado')";

        $stmt = sqlsrv_query($conexion, $sql, $params);
        if ($stmt === false) throw new Exception('Error KPI: ' . print_r(sqlsrv_errors(), true));

        $row        = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $totalPlan  = intval($row['total_plan']);
        $completados = intval($row['completados']);
        $aTiempo    = intval($row['a_tiempo']);

        $pctCompletados = $totalPlan  > 0 ? round(($completados / $totalPlan)  * 100, 1) : null;
        $pctSLA         = $completados > 0 ? round(($aTiempo    / $completados) * 100, 1) : null;

        echo json_encode(array(
            'success'         => true,
            'total_plan'      => $totalPlan,
            'completados'     => $completados,
            'a_tiempo'        => $aTiempo,
            'pct_completados' => $pctCompletados,
            'pct_sla'         => $pctSLA
        ));

    } catch (Exception $e) {
        error_log('Error en obtenerKPIResumen: ' . $e->getMessage());
        echo json_encode(array('error' => $e->getMessage()));
    }
}

/**
 * Detalle de proyectos para un KPI.
 * tipo='completados': universo completo del período, clasificado como Completado / No completado.
 * tipo='sla': solo los completados del período, clasificado como A tiempo / Tarde.
 */
function obtenerKPIDetalle($proyectoObj, $tipo, $filtroSector = '', $filtroLider = '', $filtroAno = 0, $filtroMes = 0) {
    try {
        $conexion = $proyectoObj->getConexion();
        $ano = $filtroAno > 0 ? $filtroAno : intval(date('Y'));

        $sql = "SELECT
                    ID,
                    Desarrollo,
                    Sector,
                    Desarrollador,
                    CONVERT(varchar(10), FechaLimite, 23)      AS FechaLimiteStr,
                    CONVERT(varchar(10), FinalizacionReal, 23) AS FinalizacionRealStr,
                    Estado,
                    CASE WHEN Estado = 'Terminado' AND FinalizacionReal IS NOT NULL
                              AND FinalizacionReal <= FechaLimite THEN 1 ELSE 0 END AS aTiempo
                FROM HistorialProyectos
                WHERE FechaLimite IS NOT NULL AND YEAR(FechaLimite) = ?";
        $params = array($ano);

        if ($filtroMes > 0)        { $sql .= " AND MONTH(FechaLimite) = ?"; $params[] = $filtroMes; }
        if (!empty($filtroSector)) { $sql .= " AND Sector = ?";             $params[] = $filtroSector; }
        if (!empty($filtroLider))  { $sql .= " AND LiderProyecto = ?";      $params[] = $filtroLider; }

        if ($tipo === 'completados') {
            $sql .= " AND (Estado IS NULL OR Estado <> 'Pausado')";
        }

        if ($tipo === 'sla') {
            $sql .= " AND Estado = 'Terminado' AND FinalizacionReal IS NOT NULL";
        }

        $sql .= " ORDER BY FechaLimite ASC";

        $stmt = sqlsrv_query($conexion, $sql, $params);
        if ($stmt === false) throw new Exception('Error KPI detalle: ' . print_r(sqlsrv_errors(), true));

        $proyectos = array();
        $cumplen   = 0;

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $fechaLim = $row['FechaLimiteStr']     ?: '-';
            $finReal  = $row['FinalizacionRealStr'] !== null ? $row['FinalizacionRealStr'] : '-';

            if ($tipo === 'completados') {
                $esComp        = ($row['Estado'] === 'Terminado' && $row['FinalizacionRealStr'] !== null && $row['FinalizacionRealStr'] !== '');
                $clasificacion = $esComp ? 'Completado' : 'No completado';
            } else {
                $clasificacion = intval($row['aTiempo']) ? 'A tiempo' : 'Tarde';
            }

            if ($clasificacion === 'Completado' || $clasificacion === 'A tiempo') $cumplen++;

            $proyectos[] = array(
                'id'                => intval($row['ID']),
                'desarrollo'        => $row['Desarrollo'],
                'sector'            => $row['Sector'],
                'desarrollador'     => $row['Desarrollador'],
                'fecha_limite'      => $fechaLim,
                'finalizacion_real' => $finReal,
                'estado'            => $row['Estado'],
                'clasificacion'     => $clasificacion
            );
        }

        echo json_encode(array(
            'success'   => true,
            'tipo'      => $tipo,
            'proyectos' => $proyectos,
            'total'     => count($proyectos),
            'cumplen'   => $cumplen
        ));

    } catch (Exception $e) {
        error_log('Error en obtenerKPIDetalle: ' . $e->getMessage());
        echo json_encode(array('error' => $e->getMessage()));
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