
<?php
/**
 * API para gestionar la carga de proyectos
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
    $accion = isset($_GET['accion']) ? $_GET['accion'] : (isset($_POST['accion']) ? $_POST['accion'] : '');
    
    // Ejecutar acción correspondiente
    switch ($accion) {
        case 'obtenerSolicitantes':
            obtenerSolicitantes($proyectoObj);
            break;
            
        case '':
            // Si no se especifica acción, asumimos que es crear proyecto (POST)
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                crearProyecto($proyectoObj);
            } else {
                responderError('No se especificó una acción válida');
            }
            break;
            
        default:
            responderError('Acción no reconocida: ' . $accion);
            break;
    }
    
} catch (Exception $e) {
    responderError('Error en el servidor: ' . $e->getMessage());
}

/**
 * Obtiene la lista de solicitantes desde la base de datos
 */
function obtenerSolicitantes($proyectoObj) {
    try {
        // Ahora usamos el método que agregamos a la clase Proyecto
        $solicitantes = $proyectoObj->obtenerSolicitantes();
        
        // Responder con los solicitantes
        responderExito('Solicitantes obtenidos correctamente', array(
            'solicitantes' => $solicitantes
        ));
        
    } catch (Exception $e) {
        responderError('Error al obtener solicitantes: ' . $e->getMessage());
    }
}

/**
 * Crea un nuevo proyecto en la base de datos
 */
function crearProyecto($proyectoObj) {
    try {
        // Validar datos obligatorios
        $camposRequeridos = array('tipo', 'desarrollo', 'solicitante', 'pais', 'sector', 'funcionalidad');
        foreach ($camposRequeridos as $campo) {
            if (!isset($_POST[$campo]) || empty($_POST[$campo])) {
                responderError('El campo ' . $campo . ' es obligatorio');
                return;
            }
        }
        
        // Obtener datos del formulario
        $datosProyecto = array(
            'tipo' => $_POST['tipo'],
            'desarrollo' => $_POST['desarrollo'],
            'solicitante' => $_POST['solicitante'],
            'pais' => $_POST['pais'],
            'sector' => $_POST['sector'],
            'funcionalidad' => $_POST['funcionalidad']
        );
        
        // Crear el proyecto utilizando el método de la clase Proyecto
        $proyectoId = $proyectoObj->crearProyecto($datosProyecto);
        
        // Procesar documentos adjuntos si existen
        $documentosGuardados = procesarDocumentos($proyectoObj->getConexion(), $proyectoId);
        
        // Responder con éxito
        responderExito('Proyecto creado correctamente', array(
            'id' => $proyectoId,
            'documentos' => $documentosGuardados
        ));
        
    } catch (Exception $e) {
        responderError('Error al crear el proyecto: ' . $e->getMessage());
    }
}

/**
 * Verifica si la tabla HistorialProyectos existe, si no, la crea
 */
function verificarTablaHistorialProyectos($conexion) {
    // Verificar si la tabla existe
    $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'HistorialProyectos'";
    $result = sqlsrv_query($conexion, $sql);
    
    if ($result === false) {
        throw new Exception('Error al verificar la tabla HistorialProyectos: ' . print_r(sqlsrv_errors(), true));
    }
    
    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
    
    if ($row['count'] == 0) {
        // Crear la tabla si no existe
        $sqlCrear = "CREATE TABLE HistorialProyectos (
            ID INT IDENTITY(1,1) PRIMARY KEY,
            TipoPresentacion NVARCHAR(255),
            Desarrollo NVARCHAR(255),
            Solicitante NVARCHAR(255),
            Pais NVARCHAR(255),
            Sector NVARCHAR(255),
            Funcionalidad NVARCHAR(MAX),
            FechaCreacion DATETIME,
            FechaFinalizacion DATETIME NULL,
            Estado NVARCHAR(255),
            EtapaProyecto NVARCHAR(255),
            LiderProyecto NVARCHAR(255) NULL,
            InicioProgramado DATE NULL,
            FechaLimite DATE NULL,
            InicioReal DATE NULL,
            FinalizacionReal DATE NULL,
            Prioridad NVARCHAR(50) NULL
        )";
        
        $resultCrear = sqlsrv_query($conexion, $sqlCrear);
        
        if ($resultCrear === false) {
            throw new Exception('Error al crear la tabla HistorialProyectos: ' . print_r(sqlsrv_errors(), true));
        }
    } else {
        // Verificar si la columna Desarrollo existe, y si no, agregarla
        $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'HistorialProyectos' AND COLUMN_NAME = 'Desarrollo'";
        $result = sqlsrv_query($conexion, $sql);
        
        if ($result === false) {
            throw new Exception('Error al verificar la columna Desarrollo: ' . print_r(sqlsrv_errors(), true));
        }
        
        $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
        
        if ($row['count'] == 0) {
            // Agregar la columna Desarrollo
            $sqlAlter = "ALTER TABLE HistorialProyectos ADD Desarrollo NVARCHAR(255)";
            $resultAlter = sqlsrv_query($conexion, $sqlAlter);
            
            if ($resultAlter === false) {
                throw new Exception('Error al agregar la columna Desarrollo: ' . print_r(sqlsrv_errors(), true));
            }
        }
    }
}

/**
 * Verifica si la tabla DocumentosProyectos existe, si no, la crea
 */
function verificarTablaDocumentosProyectos($conexion) {
    // Verificar si la tabla existe
    $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'DocumentosProyectos'";
    $result = sqlsrv_query($conexion, $sql);
    
    if ($result === false) {
        throw new Exception('Error al verificar la tabla DocumentosProyectos: ' . print_r(sqlsrv_errors(), true));
    }
    
    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
    
    if ($row['count'] == 0) {
        // Crear la tabla si no existe
        $sqlCrear = "CREATE TABLE DocumentosProyectos (
            ID INT IDENTITY(1,1) PRIMARY KEY,
            ID_Proyecto INT NOT NULL,
            NombreOriginal NVARCHAR(255) NOT NULL,
            NombreArchivo NVARCHAR(255) NOT NULL,
            TipoArchivo NVARCHAR(100),
            TamanoArchivo BIGINT,
            FechaSubida DATETIME,
            FOREIGN KEY (ID_Proyecto) REFERENCES HistorialProyectos(ID)
        )";
        
        $resultCrear = sqlsrv_query($conexion, $sqlCrear);
        
        if ($resultCrear === false) {
            throw new Exception('Error al crear la tabla DocumentosProyectos: ' . print_r(sqlsrv_errors(), true));
        }
    }
}

/**
 * Procesa y guarda los documentos adjuntos
 */
function procesarDocumentos($conexion, $proyectoId) {
    $documentosGuardados = array();
    
    // Verificar si hay documentos para procesar
    $numDocumentos = isset($_POST['num_documentos']) ? intval($_POST['num_documentos']) : 0;
    
    if ($numDocumentos <= 0) {
        return $documentosGuardados;
    }
    
    // Crear directorio para almacenar documentos si no existe
    $directorioBase = '../documentos/';
    $directorioProyecto = $directorioBase . $proyectoId . '/';
    
    if (!file_exists($directorioBase)) {
        mkdir($directorioBase, 0755, true);
    }
    
    if (!file_exists($directorioProyecto)) {
        mkdir($directorioProyecto, 0755, true);
    }
    
    // Procesar cada documento
    for ($i = 0; $i < $numDocumentos; $i++) {
        $docKey = 'documento_' . $i;
        
        if (!isset($_FILES[$docKey]) || $_FILES[$docKey]['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        $archivo = $_FILES[$docKey];
        $nombreOriginal = $archivo['name'];
        $tipoArchivo = $archivo['type'];
        $tamanoArchivo = $archivo['size'];
        $rutaTemporal = $archivo['tmp_name'];
        
        // Generar nombre único para el archivo
        $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
        $nombreArchivo = uniqid('doc_') . '.' . $extension;
        $rutaDestino = $directorioProyecto . $nombreArchivo;
        
        // Mover el archivo al directorio de destino
        if (move_uploaded_file($rutaTemporal, $rutaDestino)) {
            // Guardar información del documento en la base de datos
            $sql = "INSERT INTO DocumentosProyectos (
                        ID_Proyecto,
                        NombreOriginal,
                        NombreArchivo,
                        TipoArchivo,
                        TamanoArchivo,
                        FechaSubida
                    ) VALUES (?, ?, ?, ?, ?, ?)";
            
            $params = array(
                $proyectoId,
                $nombreOriginal,
                $nombreArchivo,
                $tipoArchivo,
                $tamanoArchivo,
                date('Y-m-d H:i:s')
            );
            
            $stmt = sqlsrv_query($conexion, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception('Error al guardar información del documento: ' . print_r(sqlsrv_errors(), true));
            }
            
            // Obtener el ID del documento insertado
            $sql = "SELECT SCOPE_IDENTITY() as ID";
            $stmt = sqlsrv_query($conexion, $sql);
            
            if ($stmt === false) {
                throw new Exception('Error al obtener el ID del documento: ' . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $documentoId = $row['ID'];
            
            // Agregar a la lista de documentos guardados
            $documentosGuardados[] = array(
                'id' => $documentoId,
                'nombre' => $nombreOriginal,
                'ruta' => 'documentos/' . $proyectoId . '/' . $nombreArchivo,
                'tipo' => $tipoArchivo,
                'tamano' => $tamanoArchivo
            );
        }
    }
    
    return $documentosGuardados;
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
        'mensaje' => $mensaje
    ));
    
    exit;
}