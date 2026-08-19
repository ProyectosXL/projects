<?php
/**
 * API para gestionar Programas de Proyectos
 */

session_start();

// Configuración para mostrar errores (desactivado para no interferir con JSON)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Configuración de cabeceras para respuestas JSON con UTF-8
header('Content-Type: application/json; charset=UTF-8');

// Incluir clase de programa
require_once '../class/programa.php';

try {
    $programaObj = new Programa();

    $accion = $_REQUEST['accion'] ?? '';

    switch ($accion) {
        case 'listar':
            accionListar($programaObj);
            break;

        case 'obtener':
            accionObtener($programaObj);
            break;

        case 'guardar':
            accionGuardar($programaObj);
            break;

        case 'cambiarEstado':
            accionCambiarEstado($programaObj);
            break;

        case 'listarParaFiltro':
            accionListarParaFiltro($programaObj);
            break;

        default:
            responderError('Acción no especificada o no válida');
            break;
    }

} catch (\Throwable $th) {
    error_log('Error en programas.php: ' . $th->getMessage());
    responderError('Error interno del servidor: ' . $th->getMessage());
}

/**
 * Devuelve todos los programas en el formato que espera DataTables
 */
function accionListar($programaObj) {
    try {
        $programas = $programaObj->listar();
        echo json_encode(['data' => $programas], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (\Throwable $th) {
        error_log('Error en accionListar: ' . $th->getMessage());
        responderError('Error al listar programas: ' . $th->getMessage());
    }
}

/**
 * Devuelve un programa puntual por ID
 */
function accionObtener($programaObj) {
    if (!isset($_REQUEST['id']) || !is_numeric($_REQUEST['id'])) {
        responderError('ID de programa no válido');
        return;
    }

    try {
        $programa = $programaObj->obtenerPorId((int)$_REQUEST['id']);

        if (!$programa) {
            responderError('Programa no encontrado');
            return;
        }

        responderExito('Programa obtenido correctamente', ['programa' => $programa]);
    } catch (\Throwable $th) {
        error_log('Error en accionObtener: ' . $th->getMessage());
        responderError('Error al obtener el programa: ' . $th->getMessage());
    }
}

/**
 * Da de alta o edita un programa según venga o no un ID
 */
function accionGuardar($programaObj) {
    if (!isset($_SESSION['username'])) {
        responderError('Sesión no válida', 401);
        return;
    }

    try {
        $datos = [
            'Nombre' => $_POST['Nombre'] ?? '',
            'Descripcion' => $_POST['Descripcion'] ?? '',
            'Responsable' => $_POST['Responsable'] ?? '',
            'Color' => $_POST['Color'] ?? ''
        ];

        $id = trim($_POST['id'] ?? '');

        if ($id === '') {
            $nuevoId = $programaObj->crear($datos, $_SESSION['username']);
            $programa = $programaObj->obtenerPorId($nuevoId);
            responderExito('Programa creado correctamente', ['programa' => $programa]);
        } else {
            $programaObj->actualizar((int)$id, $datos);
            $programa = $programaObj->obtenerPorId((int)$id);
            responderExito('Programa actualizado correctamente', ['programa' => $programa]);
        }
    } catch (\Throwable $th) {
        error_log('Error en accionGuardar: ' . $th->getMessage());
        responderError($th->getMessage());
    }
}

/**
 * Activa o desactiva un programa. Antes de desactivar valida que no tenga
 * proyectos activos asignados.
 */
function accionCambiarEstado($programaObj) {
    if (!isset($_SESSION['username'])) {
        responderError('Sesión no válida', 401);
        return;
    }

    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        responderError('ID de programa no válido');
        return;
    }

    $id = (int)$_POST['id'];
    $activo = !empty($_POST['activo']) && $_POST['activo'] !== '0';

    try {
        if (!$activo) {
            $cantidad = $programaObj->contarProyectosActivos($id);
            if ($cantidad > 0) {
                responderError("No se puede desactivar: el programa tiene $cantidad proyectos activos asignados. Reasignalos o cerralos primero.");
                return;
            }
        }

        $programaObj->cambiarEstado($id, $activo);
        $programa = $programaObj->obtenerPorId($id);
        responderExito($activo ? 'Programa activado correctamente' : 'Programa desactivado correctamente', ['programa' => $programa]);
    } catch (\Throwable $th) {
        error_log('Error en accionCambiarEstado: ' . $th->getMessage());
        responderError('Error al cambiar el estado del programa: ' . $th->getMessage());
    }
}

/**
 * Devuelve los programas para poblar el select de filtro del listado
 */
function accionListarParaFiltro($programaObj) {
    try {
        $programas = $programaObj->listarParaFiltro();
        responderExito('Programas obtenidos correctamente', ['programas' => $programas]);
    } catch (\Throwable $th) {
        error_log('Error en accionListarParaFiltro: ' . $th->getMessage());
        responderError('Error al obtener programas: ' . $th->getMessage());
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
