<?php
/**
 * API de Parámetros › Personas (tabla PersonasProyectos)
 *
 * TODAS las acciones (lectura y escritura) exigen el permiso del hub
 * PermisosProyectos::CLAVE_PARAMETROS. El chequeo está acá, en el servidor;
 * ocultar la pestaña en la pantalla es solo cosmético.
 */

session_start();

// Configuración para mostrar errores (desactivado para no interferir con JSON)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Configuración de cabeceras para respuestas JSON con UTF-8
header('Content-Type: application/json; charset=UTF-8');

require_once '../class/permisos.php';
require_once '../class/persona.php';

try {
    if (PermisosProyectos::username() === null) {
        responderError('Sesión no válida. Volvé a iniciar sesión.', 401);
    }

    if (!PermisosProyectos::puedeAdministrarParametros()) {
        responderError('No tenés permiso para administrar los parámetros de Gestión de Proyectos.', 403);
    }

    $personaObj = new Persona();

    $accion = $_REQUEST['accion'] ?? '';

    switch ($accion) {
        case 'listar':
            accionListar($personaObj);
            break;

        case 'obtener':
            accionObtener($personaObj);
            break;

        case 'guardar':
            exigirPost();
            accionGuardar($personaObj);
            break;

        case 'cambiarSwitch':
            exigirPost();
            accionCambiarSwitch($personaObj);
            break;

        default:
            responderError('Acción no especificada o no válida');
            break;
    }

} catch (\Throwable $th) {
    error_log('Error en parametros.php: ' . $th->getMessage());
    responderError('Error interno del servidor: ' . $th->getMessage(), 500);
}

/**
 * Las escrituras solo se aceptan por POST
 */
function exigirPost() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        responderError('Método no permitido', 405);
    }
}

/**
 * Devuelve todas las personas en el formato que espera DataTables
 */
function accionListar($personaObj) {
    try {
        $personas = $personaObj->listar();
        echo json_encode(['data' => $personas], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (\Throwable $th) {
        error_log('Error en accionListar: ' . $th->getMessage());
        responderError('Error al listar personas: ' . $th->getMessage());
    }
}

/**
 * Devuelve una persona por ID
 */
function accionObtener($personaObj) {
    if (!isset($_REQUEST['id']) || !is_numeric($_REQUEST['id'])) {
        responderError('ID de persona no válido');
    }

    try {
        $persona = $personaObj->obtenerPorId((int)$_REQUEST['id']);

        if (!$persona) {
            responderError('Persona no encontrada', 404);
        }

        responderExito('Persona obtenida correctamente', ['persona' => $persona]);
    } catch (\Throwable $th) {
        error_log('Error en accionObtener: ' . $th->getMessage());
        responderError('Error al obtener la persona: ' . $th->getMessage());
    }
}

/**
 * Alta o edición según venga o no un ID. Si la edición renombra a alguien que
 * figura en proyectos y no viene confirmarRenombre=1, no guarda y devuelve
 * requiereConfirmacion con el conteo de proyectos.
 */
function accionGuardar($personaObj) {
    try {
        $datos = [
            'Nombre' => $_POST['Nombre'] ?? '',
            'Mail' => $_POST['Mail'] ?? '',
            'EsSolicitante' => $_POST['EsSolicitante'] ?? 0,
            'EsLider' => $_POST['EsLider'] ?? 0,
            'EsDesarrollador' => $_POST['EsDesarrollador'] ?? 0
        ];

        $usuario = PermisosProyectos::username();
        $id = trim($_POST['id'] ?? '');

        if ($id === '') {
            $nuevoId = $personaObj->crear($datos, $usuario);
            $persona = $personaObj->obtenerPorId($nuevoId);
            responderExito('Persona creada correctamente', ['persona' => $persona]);
        }

        if (!is_numeric($id)) {
            responderError('ID de persona no válido');
        }

        $confirmar = ($_POST['confirmarRenombre'] ?? '') === '1';
        $resultado = $personaObj->actualizar((int)$id, $datos, $usuario, $confirmar);

        if ($resultado['requiereConfirmacion']) {
            responderJson([
                'exito' => false,
                'requiereConfirmacion' => true,
                'conteo' => $resultado['conteo'],
                'mensaje' => 'El cambio de nombre afecta proyectos existentes y requiere confirmación'
            ]);
        }

        $mensaje = 'Persona actualizada correctamente';
        if ($resultado['proyectosActualizados'] > 0) {
            $mensaje .= '. Se actualizó el nombre en ' . $resultado['conteo']['Total'] . ' proyecto(s)';
        }

        $persona = $personaObj->obtenerPorId((int)$id);
        responderExito($mensaje, ['persona' => $persona]);
    } catch (\Throwable $th) {
        error_log('Error en accionGuardar: ' . $th->getMessage());
        responderError($th->getMessage());
    }
}

/**
 * Cambia un switch (EsSolicitante, EsLider, EsDesarrollador o Activo) sin
 * abrir el modal
 */
function accionCambiarSwitch($personaObj) {
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        responderError('ID de persona no válido');
    }

    $campo = $_POST['campo'] ?? '';
    $valor = $_POST['valor'] ?? '0';

    try {
        $personaObj->cambiarSwitch((int)$_POST['id'], $campo, $valor, PermisosProyectos::username());
        $persona = $personaObj->obtenerPorId((int)$_POST['id']);
        responderExito('Cambio guardado', ['persona' => $persona]);
    } catch (\Throwable $th) {
        error_log('Error en accionCambiarSwitch: ' . $th->getMessage());
        responderError($th->getMessage());
    }
}

/**
 * Respuesta JSON genérica
 */
function responderJson($respuesta, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
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
