<?php
/**
 * API para aprobar/rechazar la etapa "Validación cliente" de un proyecto
 */

session_start();

// Configuración para mostrar errores (desactivado para no interferir con JSON)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Configuración de cabeceras para respuestas JSON
header('Content-Type: application/json; charset=UTF-8');

// Incluir clase de proyecto
require_once '../class/proyecto.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responderError('Método no permitido', 405);
        exit;
    }

    if (!isset($_SESSION['username'])) {
        responderError('Debe iniciar sesión para realizar esta acción', 401);
        exit;
    }

    if (!isset($_POST['idProyecto']) || !is_numeric($_POST['idProyecto'])) {
        responderError('ID de proyecto no válido');
        exit;
    }

    $idProyecto = (int)$_POST['idProyecto'];
    $accion = $_POST['accion'] ?? '';

    if (!in_array($accion, ['aprobar', 'rechazar'])) {
        responderError('Acción no reconocida: ' . $accion);
        exit;
    }

    $proyectoObj = new Proyecto();
    $proyecto = $proyectoObj->obtenerProyectoPorId($idProyecto);

    if (!$proyecto) {
        responderError('Proyecto no encontrado');
        exit;
    }

    if (($proyecto['EtapaProyecto'] ?? '') !== 'Validación cliente') {
        responderError('El proyecto no se encuentra en la etapa "Validación cliente"');
        exit;
    }

    $nombreUsuario = $proyectoObj->obtenerNombreUsuario($_SESSION['username']);

    if ($accion === 'aprobar') {
        $proyectoObj->actualizarProyecto($idProyecto, [
            'EtapaProyecto' => 'Implementación',
            'FechaAprobacionCliente' => date('Y-m-d H:i:s'),
            'TipoAprobacionCliente' => 'EXPLICITA',
            'UsuarioAprobacionCliente' => $nombreUsuario
        ]);

        responderExito('Proyecto aprobado correctamente');
    } else {
        $proyectoObj->actualizarProyecto($idProyecto, [
            'EtapaProyecto' => 'Correcciones y mejoras'
        ]);

        $comentario = trim($_POST['comentario'] ?? '');
        if ($comentario !== '') {
            $proyectoObj->agregarComentario($idProyecto, $nombreUsuario, '[Correcciones solicitadas] ' . $comentario);
        }

        responderExito('Proyecto enviado a "Correcciones y mejoras"');
    }

} catch (Exception $e) {
    error_log('Error en actualizarEtapaCliente: ' . $e->getMessage());
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
        'error' => $mensaje
    ));

    exit;
}
