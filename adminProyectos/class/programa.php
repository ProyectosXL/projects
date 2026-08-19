<?php

class Programa
{
    private $conexion;
    private $cid_central;

    function __construct()
    {
        // Ruta ajustada para llegar a conexion.php basada en la estructura actual
        $rutaConexion = __DIR__ . '/../../class/conexion.php';

        if (!file_exists($rutaConexion)) {
            die("Error crítico: El archivo conexion.php no existe en la ruta: " . $rutaConexion);
        }

        require_once $rutaConexion;
        $this->conexion = new Conexion();
        $this->cid_central = $this->conexion->conectar('central');

        if (!$this->cid_central) {
            $error = print_r(sqlsrv_errors(), true);
            die("Error de conexión a la base de datos: " . $error);
        }
    }

    public function getConexion()
    {
        return $this->cid_central;
    }

    /**
     * Limpia texto para UTF-8 correcto (mismo patrón que Proyecto::limpiarTextoUTF8)
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

    private function validarColor($color)
    {
        if (empty($color) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return null;
        }

        return $color;
    }

    /**
     * Valida y normaliza los datos de entrada para crear() y actualizar()
     */
    private function validarDatos($datos)
    {
        $nombre = $this->limpiarTextoUTF8($datos['Nombre'] ?? '');
        if ($nombre === '') {
            throw new Exception('El nombre del programa es obligatorio');
        }
        if (mb_strlen($nombre) > 150) {
            throw new Exception('El nombre no puede superar los 150 caracteres');
        }

        $descripcion = $this->limpiarTextoUTF8($datos['Descripcion'] ?? '');
        if (mb_strlen($descripcion) > 500) {
            throw new Exception('La descripción no puede superar los 500 caracteres');
        }

        $responsable = $this->limpiarTextoUTF8($datos['Responsable'] ?? '');
        if (mb_strlen($responsable) > 255) {
            throw new Exception('El responsable no puede superar los 255 caracteres');
        }

        return [
            'Nombre' => $nombre,
            'Descripcion' => $descripcion !== '' ? $descripcion : null,
            'Responsable' => $responsable !== '' ? $responsable : null,
            'Color' => $this->validarColor($datos['Color'] ?? null)
        ];
    }

    /**
     * Normaliza una fila de la base de datos para que serialice limpio en JSON
     */
    private function formatearFila(&$row)
    {
        if (isset($row['FechaCreacion']) && $row['FechaCreacion'] instanceof DateTime) {
            $row['FechaCreacion'] = $row['FechaCreacion']->format('Y-m-d H:i:s');
        }

        if (isset($row['Activo'])) {
            $row['Activo'] = (int)$row['Activo'];
        }

        if (array_key_exists('CantidadProyectos', $row)) {
            $row['CantidadProyectos'] = (int)$row['CantidadProyectos'];
        }

        if (array_key_exists('CantidadActivos', $row)) {
            $row['CantidadActivos'] = (int)$row['CantidadActivos'];
        }

        return $row;
    }

    /**
     * Lanza una Exception con mensaje claro si $errores corresponde a una
     * violación de la restricción UNIQUE sobre Nombre (códigos 2627 y 2601)
     */
    private function verificarErrorNombreDuplicado($errores)
    {
        foreach ($errores as $error) {
            if (isset($error['code']) && in_array((int)$error['code'], [2627, 2601], true)) {
                throw new Exception('Ya existe un programa con ese nombre');
            }
        }
    }

    public function listar($soloActivos = false)
    {
        try {
            $sql = "SELECT p.ID, p.Nombre, p.Descripcion, p.Responsable, p.Color, p.Activo,
                           p.FechaCreacion, p.CreadoPor,
                           (SELECT COUNT(*) FROM HistorialProyectos hp WITH (NOLOCK) WHERE hp.IdPrograma = p.ID) AS CantidadProyectos,
                           (SELECT COUNT(*) FROM HistorialProyectos hp WITH (NOLOCK) WHERE hp.IdPrograma = p.ID AND hp.Estado NOT IN ('Terminado', 'Cancelado')) AS CantidadActivos
                    FROM ProgramasProyectos p WITH (NOLOCK)";

            if ($soloActivos) {
                $sql .= " WHERE p.Activo = 1";
            }

            $sql .= " ORDER BY p.Nombre";

            $result = sqlsrv_query($this->cid_central, $sql);

            if ($result === false) {
                throw new Exception('Error al listar programas: ' . print_r(sqlsrv_errors(), true));
            }

            $programas = [];
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $this->formatearFila($row);
                $programas[] = $row;
            }

            return $programas;
        } catch (\Throwable $th) {
            error_log("Error en Programa::listar: " . $th->getMessage());
            throw $th;
        }
    }

    public function obtenerPorId($id)
    {
        try {
            $sql = "SELECT ID, Nombre, Descripcion, Responsable, Color, Activo, FechaCreacion, CreadoPor
                    FROM ProgramasProyectos WITH (NOLOCK)
                    WHERE ID = ?";

            $result = sqlsrv_query($this->cid_central, $sql, array($id));

            if ($result === false) {
                throw new Exception('Error al obtener el programa: ' . print_r(sqlsrv_errors(), true));
            }

            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            $this->formatearFila($row);
            return $row;
        } catch (\Throwable $th) {
            error_log("Error en Programa::obtenerPorId: " . $th->getMessage());
            throw $th;
        }
    }

    public function crear($datos, $usuario)
    {
        try {
            $datosLimpios = $this->validarDatos($datos);

            $sql = "INSERT INTO ProgramasProyectos (Nombre, Descripcion, Responsable, Color, Activo, FechaCreacion, CreadoPor)
                    VALUES (?, ?, ?, ?, 1, GETDATE(), ?)";

            $params = array(
                $datosLimpios['Nombre'],
                $datosLimpios['Descripcion'],
                $datosLimpios['Responsable'],
                $datosLimpios['Color'],
                $usuario
            );

            $stmt = sqlsrv_query($this->cid_central, $sql, $params);

            if ($stmt === false) {
                $errores = sqlsrv_errors();
                $this->verificarErrorNombreDuplicado($errores);
                throw new Exception('Error al crear el programa: ' . print_r($errores, true));
            }

            $sqlId = "SELECT SCOPE_IDENTITY() AS ID";
            $stmtId = sqlsrv_query($this->cid_central, $sqlId);

            if ($stmtId === false) {
                throw new Exception('Error al obtener el ID del programa creado: ' . print_r(sqlsrv_errors(), true));
            }

            $rowId = sqlsrv_fetch_array($stmtId, SQLSRV_FETCH_ASSOC);

            return (int)$rowId['ID'];
        } catch (\Throwable $th) {
            error_log("Error en Programa::crear: " . $th->getMessage());
            throw $th;
        }
    }

    public function actualizar($id, $datos)
    {
        try {
            $datosLimpios = $this->validarDatos($datos);

            $sql = "UPDATE ProgramasProyectos SET Nombre = ?, Descripcion = ?, Responsable = ?, Color = ? WHERE ID = ?";

            $params = array(
                $datosLimpios['Nombre'],
                $datosLimpios['Descripcion'],
                $datosLimpios['Responsable'],
                $datosLimpios['Color'],
                $id
            );

            $stmt = sqlsrv_query($this->cid_central, $sql, $params);

            if ($stmt === false) {
                $errores = sqlsrv_errors();
                $this->verificarErrorNombreDuplicado($errores);
                throw new Exception('Error al actualizar el programa: ' . print_r($errores, true));
            }

            return true;
        } catch (\Throwable $th) {
            error_log("Error en Programa::actualizar: " . $th->getMessage());
            throw $th;
        }
    }

    /**
     * Activa o desactiva un programa. No hay borrado físico bajo ninguna circunstancia.
     */
    public function cambiarEstado($id, $activo)
    {
        try {
            $sql = "UPDATE ProgramasProyectos SET Activo = ? WHERE ID = ?";
            $stmt = sqlsrv_query($this->cid_central, $sql, array($activo ? 1 : 0, $id));

            if ($stmt === false) {
                throw new Exception('Error al cambiar el estado del programa: ' . print_r(sqlsrv_errors(), true));
            }

            return true;
        } catch (\Throwable $th) {
            error_log("Error en Programa::cambiarEstado: " . $th->getMessage());
            throw $th;
        }
    }

    public function contarProyectosActivos($id)
    {
        try {
            $sql = "SELECT COUNT(*) AS Cantidad FROM HistorialProyectos WITH (NOLOCK)
                    WHERE IdPrograma = ? AND Estado NOT IN ('Terminado', 'Cancelado')";

            $result = sqlsrv_query($this->cid_central, $sql, array($id));

            if ($result === false) {
                throw new Exception('Error al contar proyectos activos del programa: ' . print_r(sqlsrv_errors(), true));
            }

            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            return (int)$row['Cantidad'];
        } catch (\Throwable $th) {
            error_log("Error en Programa::contarProyectosActivos: " . $th->getMessage());
            throw $th;
        }
    }

    /**
     * Programas activos más los inactivos que todavía tengan proyectos asignados,
     * para poder filtrar el histórico aunque el programa ya no esté activo.
     */
    public function listarParaFiltro()
    {
        try {
            $sql = "SELECT DISTINCT p.ID, p.Nombre, p.Color
                    FROM ProgramasProyectos p WITH (NOLOCK)
                    WHERE p.Activo = 1
                       OR EXISTS (SELECT 1 FROM HistorialProyectos hp WITH (NOLOCK) WHERE hp.IdPrograma = p.ID)
                    ORDER BY p.Nombre";

            $result = sqlsrv_query($this->cid_central, $sql);

            if ($result === false) {
                throw new Exception('Error al listar programas para filtro: ' . print_r(sqlsrv_errors(), true));
            }

            $programas = [];
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $programas[] = [
                    'id' => (int)$row['ID'],
                    'nombre' => $row['Nombre'],
                    'color' => $row['Color']
                ];
            }

            return $programas;
        } catch (\Throwable $th) {
            error_log("Error en Programa::listarParaFiltro: " . $th->getMessage());
            throw $th;
        }
    }

    public function obtenerProyectos($idPrograma, $excluirIdProyecto = null)
    {
        try {
            $sql = "SELECT ID, Desarrollo, Estado, EtapaProyecto, Prioridad
                    FROM HistorialProyectos WITH (NOLOCK)
                    WHERE IdPrograma = ?";
            $params = array($idPrograma);

            if ($excluirIdProyecto !== null) {
                $sql .= " AND ID <> ?";
                $params[] = $excluirIdProyecto;
            }

            $sql .= " ORDER BY Desarrollo";

            $result = sqlsrv_query($this->cid_central, $sql, $params);

            if ($result === false) {
                throw new Exception('Error al obtener los proyectos del programa: ' . print_r(sqlsrv_errors(), true));
            }

            $proyectos = [];
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $proyectos[] = $row;
            }

            return $proyectos;
        } catch (\Throwable $th) {
            error_log("Error en Programa::obtenerProyectos: " . $th->getMessage());
            throw $th;
        }
    }
}
