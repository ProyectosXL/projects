<?php
header('Content-Type: application/json; charset=UTF-8');

// Activar visualización de errores para depuración
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

try {
    // Incluir la clase Proyecto
    require_once '../class/proyecto.php';
    
    // Crear una instancia de la clase Proyecto
    $proyectoObj = new Proyecto();
    
    $action = isset($_GET['action']) ? $_GET['action'] : 'listar';
    
    switch ($action) {
        case 'listar':
            // Obtener proyectos
            $proyectos = $proyectoObj->traerProyectos();
            echo json_encode([
                'data' => $proyectos
            ]);
            break;
            
        case 'filtros':
            // Obtener datos para filtros
            $filtros = $proyectoObj->obtenerFiltros();
            echo json_encode($filtros);
            break;
            
        default:
            echo json_encode([
                'error' => 'Acción no válida'
            ]);
    }
    
} catch (Exception $e) {
    // Log del error para depuración
    error_log("Error en API: " . $e->getMessage());
    
    // Devolver respuesta de error en formato JSON
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}