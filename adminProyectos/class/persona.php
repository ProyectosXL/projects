<?php

/**
 * Persona
 * Listado único de personas de Gestión de Proyectos (tabla PersonasProyectos,
 * ver sql/PersonasProyectos.sql). Cada persona tiene tres switches que indican
 * en qué combo aparece: Solicitante, Líder y Desarrollador.
 *
 * HistorialProyectos sigue guardando el NOMBRE como texto en Solicitante,
 * LiderProyecto y Desarrollador; por eso renombrar una persona actualiza ese
 * texto en los proyectos (en la misma transacción).
 *
 * Las comparaciones de nombre se hacen en SQL Server: la columna Nombre usa
 * Modern_Spanish_CI_AI, así que no distingue mayúsculas ni acentos.
 */
class Persona
{
    /**
     * Roles: clave usada en la app => columna de PersonasProyectos, columna de
     * HistorialProyectos y texto para mensajes.
     */
    const ROLES = [
        'solicitante' => ['columna' => 'EsSolicitante', 'columnaProyecto' => 'Solicitante', 'texto' => 'Solicitante'],
        'lider' => ['columna' => 'EsLider', 'columnaProyecto' => 'LiderProyecto', 'texto' => 'Líder'],
        'desarrollador' => ['columna' => 'EsDesarrollador', 'columnaProyecto' => 'Desarrollador', 'texto' => 'Desarrollador']
    ];

    /** Campos que se pueden cambiar con un switch directo desde la tabla */
    const CAMPOS_SWITCH = ['EsSolicitante', 'EsLider', 'EsDesarrollador', 'Activo'];

    const LARGO_NOMBRE = 255;
    const LARGO_MAIL = 200;
    const LARGO_USUARIO = 100;

    private $conexion;
    private $cid_central;

    /**
     * @param resource|null $cidCentral Conexión central ya abierta para reutilizar
     *        (la pasa Proyecto). Abrir una segunda Conexion en el mismo pedido
     *        puede fallar: classEnv.php lee el .env con getenv() y, con Apache en
     *        Windows, otro pedido simultáneo que termina puede dejar esas
     *        variables vacías ("Invalid value type for option Database").
     */
    function __construct($cidCentral = null)
    {
        if ($cidCentral) {
            $this->cid_central = $cidCentral;
            return;
        }

        $rutaConexion = __DIR__ . '/../../class/conexion.php';

        if (!file_exists($rutaConexion)) {
            throw new Exception("Error crítico: El archivo conexion.php no existe en la ruta: " . $rutaConexion);
        }

        require_once $rutaConexion;
        $this->conexion = new Conexion();
        $this->cid_central = $this->conexion->conectar('central');

        if (!$this->cid_central) {
            throw new Exception("Error de conexión a la base de datos: " . print_r(sqlsrv_errors(), true));
        }
    }

    /**
     * Limpia texto para UTF-8 correcto (mismo criterio que Proyecto::limpiarTextoUTF8)
     */
    private function limpiarTextoUTF8($texto)
    {
        if (!is_string($texto)) {
            return $texto;
        }

        $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252'];
        $encoding = mb_detect_encoding($texto, $encodings, true);

        if ($encoding && $encoding !== 'UTF-8') {
            $texto = mb_convert_encoding($texto, 'UTF-8', $encoding);
        }

        $texto = trim($texto);
        $texto = str_replace(['â€œ', 'â€', 'â€™', 'Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº', 'Ã±'],
                            ['"', '"', "'", 'á', 'é', 'í', 'ó', 'ú', 'ñ'], $texto);

        return $texto;
    }

    private function aBool($valor)
    {
        return !empty($valor) && $valor !== '0' && $valor !== 'false';
    }

    private function recortarUsuario($usuario)
    {
        return mb_substr((string)$usuario, 0, self::LARGO_USUARIO);
    }

    private function columnaRol($rol)
    {
        if (!isset(self::ROLES[$rol])) {
            throw new Exception('Rol no válido: ' . $rol);
        }

        return self::ROLES[$rol]['columna'];
    }

    /**
     * Normaliza una fila de la base para que serialice limpio en JSON
     */
    private function formatearFila(&$row)
    {
        foreach (['FechaCreacion', 'FechaModificacion'] as $campo) {
            if (isset($row[$campo]) && $row[$campo] instanceof DateTime) {
                $row[$campo] = $row[$campo]->format('Y-m-d H:i:s');
            }
        }

        foreach (['ID', 'EsSolicitante', 'EsLider', 'EsDesarrollador', 'Activo'] as $campo) {
            if (array_key_exists($campo, $row)) {
                $row[$campo] = (int)$row[$campo];
            }
        }

        $row['SinMail'] = (trim((string)($row['Mail'] ?? '')) === '') ? 1 : 0;

        return $row;
    }

    /**
     * Valida los datos del modal (alta/edición). Siempre valida todo.
     */
    private function validarDatos($datos, $activo)
    {
        $nombre = $this->limpiarTextoUTF8($datos['Nombre'] ?? '');
        if ($nombre === '') {
            throw new Exception('El nombre es obligatorio');
        }
        if (mb_strlen($nombre) > self::LARGO_NOMBRE) {
            throw new Exception('El nombre no puede superar los ' . self::LARGO_NOMBRE . ' caracteres');
        }

        $mail = $this->limpiarTextoUTF8($datos['Mail'] ?? '');
        if ($mail !== '') {
            if (mb_strlen($mail) > self::LARGO_MAIL) {
                throw new Exception('El mail no puede superar los ' . self::LARGO_MAIL . ' caracteres');
            }
            if (filter_var($mail, FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception('El mail no tiene un formato válido');
            }
        }

        $esSolicitante = $this->aBool($datos['EsSolicitante'] ?? 0);
        $esLider = $this->aBool($datos['EsLider'] ?? 0);
        $esDesarrollador = $this->aBool($datos['EsDesarrollador'] ?? 0);

        // Solicitante y Líder reciben avisos automáticos por mail
        if (($esSolicitante || $esLider) && $mail === '') {
            throw new Exception('El mail es obligatorio para Solicitantes y Líderes (reciben avisos automáticos)');
        }

        if ($activo && !$esSolicitante && !$esLider && !$esDesarrollador) {
            throw new Exception('Una persona activa tiene que tener al menos un rol (Solicitante, Líder o Desarrollador)');
        }

        return [
            'Nombre' => $nombre,
            'Mail' => $mail !== '' ? $mail : null,
            'EsSolicitante' => $esSolicitante ? 1 : 0,
            'EsLider' => $esLider ? 1 : 0,
            'EsDesarrollador' => $esDesarrollador ? 1 : 0
        ];
    }

    /**
     * Lanza una Exception con mensaje claro si los errores corresponden a la
     * restricción UNIQUE sobre Nombre (códigos 2627 y 2601)
     */
    private function verificarErrorNombreDuplicado($errores)
    {
        foreach ((array)$errores as $error) {
            if (isset($error['code']) && in_array((int)$error['code'], [2627, 2601], true)) {
                throw new Exception('Ya existe una persona con ese nombre en el listado');
            }
        }
    }

    /**
     * Devuelve el ID de otra persona con el mismo nombre (sin distinguir
     * mayúsculas ni acentos), o null si no hay.
     */
    private function idConMismoNombre($nombre, $excluirId = null)
    {
        $sql = "SELECT TOP 1 ID FROM PersonasProyectos WHERE Nombre = ?";
        $params = [$nombre];

        if ($excluirId !== null) {
            $sql .= " AND ID <> ?";
            $params[] = $excluirId;
        }

        $result = sqlsrv_query($this->cid_central, $sql, $params);

        if ($result === false) {
            throw new Exception('Error al verificar el nombre: ' . print_r(sqlsrv_errors(), true));
        }

        $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
        return $row ? (int)$row['ID'] : null;
    }

    /**
     * Listado completo para la pantalla de Parámetros (activos e inactivos;
     * el filtro "Mostrar inactivos" se aplica en la tabla).
     */
    public function listar()
    {
        try {
            $sql = "SELECT ID, Nombre, Mail, EsSolicitante, EsLider, EsDesarrollador, Activo,
                           FechaCreacion, CreadoPor, FechaModificacion, ModificadoPor
                    FROM PersonasProyectos WITH (NOLOCK)
                    ORDER BY Nombre";

            $result = sqlsrv_query($this->cid_central, $sql);

            if ($result === false) {
                throw new Exception('Error al listar personas: ' . print_r(sqlsrv_errors(), true));
            }

            $personas = [];
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $this->formatearFila($row);
                $personas[] = $row;
            }

            return $personas;
        } catch (\Throwable $th) {
            error_log("Error en Persona::listar: " . $th->getMessage());
            throw $th;
        }
    }

    public function obtenerPorId($id)
    {
        try {
            $sql = "SELECT ID, Nombre, Mail, EsSolicitante, EsLider, EsDesarrollador, Activo,
                           FechaCreacion, CreadoPor, FechaModificacion, ModificadoPor
                    FROM PersonasProyectos WITH (NOLOCK)
                    WHERE ID = ?";

            $result = sqlsrv_query($this->cid_central, $sql, [$id]);

            if ($result === false) {
                throw new Exception('Error al obtener la persona: ' . print_r(sqlsrv_errors(), true));
            }

            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            $this->formatearFila($row);
            return $row;
        } catch (\Throwable $th) {
            error_log("Error en Persona::obtenerPorId: " . $th->getMessage());
            throw $th;
        }
    }

    /**
     * Personas activas con el rol indicado ('solicitante', 'lider' o
     * 'desarrollador'), en orden alfabético. Fuente de los combos.
     */
    public function listarPorRol($rol)
    {
        try {
            $columna = $this->columnaRol($rol);

            $sql = "SELECT ID, Nombre, Mail
                    FROM PersonasProyectos WITH (NOLOCK)
                    WHERE Activo = 1 AND $columna = 1
                    ORDER BY Nombre";

            $result = sqlsrv_query($this->cid_central, $sql);

            if ($result === false) {
                throw new Exception('Error al listar personas por rol: ' . print_r(sqlsrv_errors(), true));
            }

            $personas = [];
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $personas[] = [
                    'ID' => (int)$row['ID'],
                    'Nombre' => $row['Nombre'],
                    'Mail' => $row['Mail']
                ];
            }

            return $personas;
        } catch (\Throwable $th) {
            error_log("Error en Persona::listarPorRol: " . $th->getMessage());
            throw $th;
        }
    }

    /**
     * ¿El nombre corresponde a una persona activa con ese rol? Devuelve el
     * Nombre tal como está en el listado, o null si no es válido.
     */
    public function nombreActivoConRol($rol, $nombre)
    {
        $columna = $this->columnaRol($rol);
        $nombre = $this->limpiarTextoUTF8((string)$nombre);

        if ($nombre === '') {
            return null;
        }

        $sql = "SELECT TOP 1 Nombre FROM PersonasProyectos
                WHERE Nombre = ? AND Activo = 1 AND $columna = 1";

        $result = sqlsrv_query($this->cid_central, $sql, [$nombre]);

        if ($result === false) {
            throw new Exception('Error al validar la persona: ' . print_r(sqlsrv_errors(), true));
        }

        $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
        return $row ? $row['Nombre'] : null;
    }

    /**
     * Mail de una persona por nombre, sin importar si está activa ni qué roles
     * tiene (para los avisos automáticos). null si no está o no tiene mail.
     */
    public function resolverMail($nombre)
    {
        $nombre = trim((string)$nombre);
        if ($nombre === '') {
            return null;
        }

        $sql = "SELECT TOP 1 Mail FROM PersonasProyectos WITH (NOLOCK) WHERE Nombre = ?";
        $result = sqlsrv_query($this->cid_central, $sql, [$nombre]);

        if ($result === false) {
            throw new Exception('Error al resolver el mail: ' . print_r(sqlsrv_errors(), true));
        }

        $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
        $mail = $row ? trim((string)$row['Mail']) : '';

        return $mail !== '' ? $mail : null;
    }

    /**
     * Cuántos proyectos tienen el nombre como Solicitante, Líder o
     * Desarrollador (sin distinguir mayúsculas, acentos ni espacios al
     * inicio/fin, igual que el UPDATE del renombrado).
     */
    public function contarProyectosPorNombre($nombre)
    {
        $sql = "SELECT
                    SUM(CASE WHEN LTRIM(RTRIM(Solicitante)) = ? THEN 1 ELSE 0 END) AS Solicitante,
                    SUM(CASE WHEN LTRIM(RTRIM(LiderProyecto)) = ? THEN 1 ELSE 0 END) AS LiderProyecto,
                    SUM(CASE WHEN LTRIM(RTRIM(Desarrollador)) = ? THEN 1 ELSE 0 END) AS Desarrollador,
                    SUM(CASE WHEN LTRIM(RTRIM(Solicitante)) = ? OR LTRIM(RTRIM(LiderProyecto)) = ?
                              OR LTRIM(RTRIM(Desarrollador)) = ? THEN 1 ELSE 0 END) AS Total
                FROM HistorialProyectos WITH (NOLOCK)";

        $result = sqlsrv_query($this->cid_central, $sql, array_fill(0, 6, $nombre));

        if ($result === false) {
            throw new Exception('Error al contar los proyectos de la persona: ' . print_r(sqlsrv_errors(), true));
        }

        $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC) ?: [];

        return [
            'Solicitante' => (int)($row['Solicitante'] ?? 0),
            'LiderProyecto' => (int)($row['LiderProyecto'] ?? 0),
            'Desarrollador' => (int)($row['Desarrollador'] ?? 0),
            'Total' => (int)($row['Total'] ?? 0)
        ];
    }

    /**
     * Alta de una persona (queda activa)
     */
    public function crear($datos, $usuario)
    {
        try {
            $limpios = $this->validarDatos($datos, true);

            if ($this->idConMismoNombre($limpios['Nombre']) !== null) {
                throw new Exception('Ya existe una persona con ese nombre en el listado');
            }

            $sql = "INSERT INTO PersonasProyectos (Nombre, Mail, EsSolicitante, EsLider, EsDesarrollador, Activo, FechaCreacion, CreadoPor)
                    VALUES (?, ?, ?, ?, ?, 1, GETDATE(), ?);
                    SELECT SCOPE_IDENTITY() AS ID;";

            $params = [
                $limpios['Nombre'],
                $limpios['Mail'],
                $limpios['EsSolicitante'],
                $limpios['EsLider'],
                $limpios['EsDesarrollador'],
                $this->recortarUsuario($usuario)
            ];

            $stmt = sqlsrv_query($this->cid_central, $sql, $params);

            if ($stmt === false) {
                $errores = sqlsrv_errors();
                $this->verificarErrorNombreDuplicado($errores);
                throw new Exception('Error al crear la persona: ' . print_r($errores, true));
            }

            sqlsrv_next_result($stmt);
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

            return (int)$row['ID'];
        } catch (\Throwable $th) {
            error_log("Error en Persona::crear: " . $th->getMessage());
            throw $th;
        }
    }

    /**
     * Edición desde el modal. Si cambia el nombre y hay proyectos que lo usan,
     * sin $confirmarRenombre no guarda nada y devuelve el conteo para que la
     * pantalla pida confirmación. Con confirmación, actualiza la persona y el
     * texto en HistorialProyectos en una única transacción.
     *
     * @return array ['requiereConfirmacion' => bool, 'conteo' => array|null, 'proyectosActualizados' => int]
     */
    public function actualizar($id, $datos, $usuario, $confirmarRenombre = false)
    {
        try {
            $actual = $this->obtenerPorId($id);
            if (!$actual) {
                throw new Exception('Persona no encontrada');
            }

            $limpios = $this->validarDatos($datos, (int)$actual['Activo'] === 1);

            if ($this->idConMismoNombre($limpios['Nombre'], $id) !== null) {
                throw new Exception('Ya existe otra persona con ese nombre en el listado');
            }

            // Cualquier diferencia de texto cuenta como renombrado (también
            // mayúsculas o acentos), para que los proyectos queden con el nombre nuevo
            $renombra = ($limpios['Nombre'] !== $actual['Nombre']);
            $conteo = null;

            if ($renombra) {
                $conteo = $this->contarProyectosPorNombre($actual['Nombre']);

                if ($conteo['Total'] > 0 && !$confirmarRenombre) {
                    return ['requiereConfirmacion' => true, 'conteo' => $conteo, 'proyectosActualizados' => 0];
                }
            }

            if (sqlsrv_begin_transaction($this->cid_central) === false) {
                throw new Exception('No se pudo iniciar la transacción: ' . print_r(sqlsrv_errors(), true));
            }

            try {
                $sql = "UPDATE PersonasProyectos
                        SET Nombre = ?, Mail = ?, EsSolicitante = ?, EsLider = ?, EsDesarrollador = ?,
                            FechaModificacion = GETDATE(), ModificadoPor = ?
                        WHERE ID = ?";

                $params = [
                    $limpios['Nombre'],
                    $limpios['Mail'],
                    $limpios['EsSolicitante'],
                    $limpios['EsLider'],
                    $limpios['EsDesarrollador'],
                    $this->recortarUsuario($usuario),
                    $id
                ];

                $stmt = sqlsrv_query($this->cid_central, $sql, $params);

                if ($stmt === false) {
                    $errores = sqlsrv_errors();
                    $this->verificarErrorNombreDuplicado($errores);
                    throw new Exception('Error al actualizar la persona: ' . print_r($errores, true));
                }

                $proyectosActualizados = 0;

                if ($renombra && $conteo['Total'] > 0) {
                    foreach (self::ROLES as $rolInfo) {
                        $columna = $rolInfo['columnaProyecto'];
                        $sqlProyectos = "UPDATE HistorialProyectos SET $columna = ? WHERE LTRIM(RTRIM($columna)) = ?";
                        $stmtProyectos = sqlsrv_query($this->cid_central, $sqlProyectos, [$limpios['Nombre'], $actual['Nombre']]);

                        if ($stmtProyectos === false) {
                            throw new Exception("Error al actualizar $columna en los proyectos: " . print_r(sqlsrv_errors(), true));
                        }

                        $proyectosActualizados += max(0, (int)sqlsrv_rows_affected($stmtProyectos));
                    }
                }

                if (sqlsrv_commit($this->cid_central) === false) {
                    throw new Exception('No se pudo confirmar la transacción: ' . print_r(sqlsrv_errors(), true));
                }
            } catch (\Throwable $th) {
                sqlsrv_rollback($this->cid_central);
                throw $th;
            }

            return ['requiereConfirmacion' => false, 'conteo' => $conteo, 'proyectosActualizados' => $proyectosActualizados];
        } catch (\Throwable $th) {
            error_log("Error en Persona::actualizar: " . $th->getMessage());
            throw $th;
        }
    }

    /**
     * Cambio de un switch directo desde la tabla (rol o Activo), sin abrir el
     * modal. Reglas:
     *   - Prender Solicitante o Líder exige que la persona tenga mail.
     *   - Apagar roles y activar/desactivar no exigen mail.
     *   - Una persona activa tiene que quedar con al menos un rol.
     * No se borran personas: desactivar es Activo = 0.
     */
    public function cambiarSwitch($id, $campo, $valor, $usuario)
    {
        try {
            if (!in_array($campo, self::CAMPOS_SWITCH, true)) {
                throw new Exception('Campo no válido');
            }

            $actual = $this->obtenerPorId($id);
            if (!$actual) {
                throw new Exception('Persona no encontrada');
            }

            $nuevo = $this->aBool($valor) ? 1 : 0;

            $estado = [
                'EsSolicitante' => (int)$actual['EsSolicitante'],
                'EsLider' => (int)$actual['EsLider'],
                'EsDesarrollador' => (int)$actual['EsDesarrollador'],
                'Activo' => (int)$actual['Activo']
            ];
            $estado[$campo] = $nuevo;

            if ($nuevo === 1 && in_array($campo, ['EsSolicitante', 'EsLider'], true) && (int)$actual['SinMail'] === 1) {
                $rolTexto = $campo === 'EsSolicitante' ? 'Solicitante' : 'Líder';
                throw new Exception("Para marcar a {$actual['Nombre']} como $rolTexto primero cargale un mail (botón Editar): Solicitantes y Líderes reciben avisos automáticos");
            }

            if ($estado['Activo'] === 1 && !$estado['EsSolicitante'] && !$estado['EsLider'] && !$estado['EsDesarrollador']) {
                throw new Exception('Una persona activa tiene que tener al menos un rol (Solicitante, Líder o Desarrollador)');
            }

            $sql = "UPDATE PersonasProyectos SET $campo = ?, FechaModificacion = GETDATE(), ModificadoPor = ? WHERE ID = ?";
            $stmt = sqlsrv_query($this->cid_central, $sql, [$nuevo, $this->recortarUsuario($usuario), $id]);

            if ($stmt === false) {
                throw new Exception('Error al actualizar la persona: ' . print_r(sqlsrv_errors(), true));
            }

            return true;
        } catch (\Throwable $th) {
            error_log("Error en Persona::cambiarSwitch: " . $th->getMessage());
            throw $th;
        }
    }
}
