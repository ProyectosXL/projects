
<?php

class Proyecto
{
    private $conexion;
    private $cid_central;
    
    function __construct() 
    {
        // Ruta ajustada para llegar a conexion.php basada en la estructura actual
        $rutaConexion = __DIR__ . '/../../class/conexion.php';
        
        // Verificar si el archivo existe
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
    
    /**
     * Método para obtener la conexión a la base de datos central
     * Añadido para resolver problemas de acceso a la conexión
     */
    public function getConexion()
    {
        return $this->cid_central;
    }
    
    /**
     * Obtiene la lista de solicitantes desde la base de datos
     * Devuelve solo el primer nombre y el apellido con formato Nombre Apellido
     */
    public function obtenerSolicitantes()
    {
        try {
            // Query para obtener solicitantes usando NOMBRE y APELLIDO por separado
            $sql = "SELECT L.NRO_LEGAJO, L.NOMBRE, L.APELLIDO  
                FROM [TANGO-SUELDOS].LAKERS_CORP_SA.DBO.RO_V_LEGAJO L           
                WHERE L.DESC_DEPARTAMENTO IN ('ADMINISTRACION','COMERCIALIZACION','LOGISTICA')
                ORDER BY L.APELLIDO, L.NOMBRE";
            
            // Ejecutar consulta
            $result = sqlsrv_query($this->cid_central, $sql);
            
            if ($result === false) {
                throw new Exception('Error al ejecutar la consulta: ' . print_r(sqlsrv_errors(), true));
            }
            
            // Recopilar resultados
            $solicitantes = array();
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                // Obtener solo el primer nombre dividiendo por espacios y tomando la primera parte
                $primerNombre = explode(' ', trim($row['NOMBRE']))[0];
                
                // Convertir a formato Nombre Apellido (primera letra mayúscula, resto minúscula)
                $nombreFormateado = ucfirst(strtolower($primerNombre));
                $apellidoFormateado = ucfirst(strtolower($row['APELLIDO']));
                
                // Formar el nombre completo con el primer nombre y el apellido
                $nombreCompleto = $nombreFormateado . ' ' . $apellidoFormateado;
                
                $solicitantes[] = array(
                    'NRO_LEGAJO' => $row['NRO_LEGAJO'],
                    'APELLIDO_Y_NOMBRE' => $nombreCompleto,
                    'NOMBRE_FORMATEADO' => $nombreCompleto // Para guardar en la base de datos
                );
            }
            
            return $solicitantes;
            
        } catch (Exception $e) {
            error_log("Error en obtenerSolicitantes: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Crea un nuevo proyecto en la base de datos
     */
    public function crearProyecto($datos, $documentos = [])
    {
        try {
            // Verificar si la tabla HistorialProyectos existe
            $this->verificarTablaHistorialProyectos();
            
            // Generar fecha actual
            $fechaCreacion = date('Y-m-d H:i:s');
            
            // Preparar la consulta SQL para insertar el proyecto
            $sql = "INSERT INTO HistorialProyectos (
                        TipoPresentacion, 
                        Desarrollo,
                        Solicitante, 
                        Pais, 
                        Sector, 
                        Funcionalidad, 
                        FechaCreacion, 
                        Estado, 
                        EtapaProyecto
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = array(
                $datos['tipo'],
                $datos['desarrollo'],
                $datos['solicitante'],
                $datos['pais'],
                $datos['sector'],
                $datos['funcionalidad'],
                $fechaCreacion,
                'Pendiente', // Estado inicial
                'Pendiente'  // Etapa inicial
            );
            
            // Ejecutar la consulta
            $stmt = sqlsrv_query($this->cid_central, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception('Error al insertar el proyecto: ' . print_r(sqlsrv_errors(), true));
            }
            
            // Obtener el ID del proyecto insertado
            $sql = "SELECT SCOPE_IDENTITY() as ID";
            $stmt = sqlsrv_query($this->cid_central, $sql);
            
            if ($stmt === false) {
                throw new Exception('Error al obtener el ID del proyecto: ' . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $proyectoId = $row['ID'];
            
            return $proyectoId;
            
        } catch (Exception $e) {
            error_log("Error en crearProyecto: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verifica si la tabla HistorialProyectos existe, si no, la crea
     */
    private function verificarTablaHistorialProyectos()
    {
        // Verificar si la tabla existe
        $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'HistorialProyectos'";
        $result = sqlsrv_query($this->cid_central, $sql);
        
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
            
            $resultCrear = sqlsrv_query($this->cid_central, $sqlCrear);
            
            if ($resultCrear === false) {
                throw new Exception('Error al crear la tabla HistorialProyectos: ' . print_r(sqlsrv_errors(), true));
            }
        } else {
            // Verificar si la columna Desarrollo existe, y si no, agregarla
            $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_NAME = 'HistorialProyectos' AND COLUMN_NAME = 'Desarrollo'";
            $result = sqlsrv_query($this->cid_central, $sql);
            
            if ($result === false) {
                throw new Exception('Error al verificar la columna Desarrollo: ' . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            
            if ($row['count'] == 0) {
                // Agregar la columna Desarrollo
                $sqlAlter = "ALTER TABLE HistorialProyectos ADD Desarrollo NVARCHAR(255)";
                $resultAlter = sqlsrv_query($this->cid_central, $sqlAlter);
                
                if ($resultAlter === false) {
                    throw new Exception('Error al agregar la columna Desarrollo: ' . print_r(sqlsrv_errors(), true));
                }
            }
        }
    }
    
    /**
     * Guarda un documento para un proyecto
     */
    public function guardarDocumento($proyectoId, $documento)
    {
        try {
            // Verificar si la tabla DocumentosProyectos existe
            $this->verificarTablaDocumentosProyectos();
            
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
                $documento['nombreOriginal'],
                $documento['nombreArchivo'],
                $documento['tipoArchivo'],
                $documento['tamanoArchivo'],
                date('Y-m-d H:i:s')
            );
            
            $stmt = sqlsrv_query($this->cid_central, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception('Error al guardar información del documento: ' . print_r(sqlsrv_errors(), true));
            }
            
            // Obtener el ID del documento insertado
            $sql = "SELECT SCOPE_IDENTITY() as ID";
            $stmt = sqlsrv_query($this->cid_central, $sql);
            
            if ($stmt === false) {
                throw new Exception('Error al obtener el ID del documento: ' . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            return $row['ID'];
            
        } catch (Exception $e) {
            error_log("Error en guardarDocumento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verifica si la tabla DocumentosProyectos existe, si no, la crea
     */
    private function verificarTablaDocumentosProyectos()
    {
        // Verificar si la tabla existe
        $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'DocumentosProyectos'";
        $result = sqlsrv_query($this->cid_central, $sql);
        
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
            
            $resultCrear = sqlsrv_query($this->cid_central, $sqlCrear);
            
            if ($resultCrear === false) {
                throw new Exception('Error al crear la tabla DocumentosProyectos: ' . print_r(sqlsrv_errors(), true));
            }
        }
    }
    
    public function traerProyectos() 
    {   
        $sql = "SELECT * FROM HistorialProyectos";
                
        try {
            // Verificar que la conexión existe
            if (!$this->cid_central) {
                throw new Exception("No hay conexión a la base de datos central");
            }
            
            $result = sqlsrv_query($this->cid_central, $sql);
            
            if ($result === false) {
                throw new Exception("Error en la consulta: " . print_r(sqlsrv_errors(), true));
            }
            
            $proyectos = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                // Formatear fechas si existen
                if (isset($row['FechaCreacion']) && $row['FechaCreacion'] instanceof DateTime) {
                    $row['FechaCreacion'] = $row['FechaCreacion']->format('Y-m-d');
                }
                
                if (isset($row['FechaFinalizacion']) && $row['FechaFinalizacion'] instanceof DateTime) {
                    $row['FechaFinalizacion'] = $row['FechaFinalizacion']->format('Y-m-d');
                }
                
                // Formatear otras fechas si existen
                $this->formatearFechas($row);
                
                // Agregar porcentaje de avance según la etapa (usando EtapaProyecto)
                $row['PorcentajeAvance'] = $this->calcularPorcentajeAvance($row['EtapaProyecto'] ?? '');
                
                $proyectos[] = $row;
            }

            return $proyectos;

        } catch (\Throwable $th) {
            error_log("Error en traerProyectos: " . $th->getMessage());
            throw $th;
        }
    }
    
    public function obtenerProyectoPorId($id) 
    {
        $sql = "SELECT * FROM HistorialProyectos WHERE ID = ?";
        
        try {
            // Verificar que la conexión existe
            if (!$this->cid_central) {
                throw new Exception("No hay conexión a la base de datos central");
            }
            
            // Preparar parámetros
            $params = array($id);
            
            $result = sqlsrv_query($this->cid_central, $sql, $params);
            
            if ($result === false) {
                throw new Exception("Error en la consulta: " . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            
            if (!$row) {
                return null; // Proyecto no encontrado
            }
            
            // Formatear fechas
            $this->formatearFechas($row);
            
            // Agregar porcentaje de avance según la etapa
            $row['PorcentajeAvance'] = $this->calcularPorcentajeAvance($row['EtapaProyecto'] ?? '');
            
            return $row;
            
        } catch (\Throwable $th) {
            error_log("Error en obtenerProyectoPorId: " . $th->getMessage());
            throw $th;
        }
    }
    
    public function actualizarProyecto($id, $datos) 
    {
        // Comprobamos si la tabla existe, si no, la creamos
        $this->verificarTablasComentarios();
        
        try {
            // Verificar que la conexión existe
            if (!$this->cid_central) {
                throw new Exception("No hay conexión a la base de datos central");
            }
            
            // Preparar la consulta SQL para actualizar los campos
            $camposActualizar = [];
            $params = [];
            
            foreach ($datos as $campo => $valor) {
                if ($valor !== null && $valor !== '') {
                    $camposActualizar[] = "$campo = ?";
                    $params[] = $valor;
                }
            }
            
            // Si no hay campos para actualizar, retornamos true
            if (empty($camposActualizar)) {
                return true;
            }
            
            // Agregamos el ID al final del array de parámetros
            $params[] = $id;
            
            $sql = "UPDATE HistorialProyectos SET " . implode(", ", $camposActualizar) . " WHERE ID = ?";
            
            $result = sqlsrv_query($this->cid_central, $sql, $params);
            
            if ($result === false) {
                throw new Exception("Error al actualizar el proyecto: " . print_r(sqlsrv_errors(), true));
            }
            
            return true;
            
        } catch (\Throwable $th) {
            error_log("Error en actualizarProyecto: " . $th->getMessage());
            throw $th;
        }
    }
    
    public function agregarComentario($idProyecto, $autor, $comentario) 
    {
        // Comprobamos si la tabla existe, si no, la creamos
        $this->verificarTablasComentarios();
        
        try {
            // Verificar que la conexión existe
            if (!$this->cid_central) {
                throw new Exception("No hay conexión a la base de datos central");
            }
            
            // Obtener la fecha actual en formato ANSI SQL
            $fechaActual = date('Y-m-d H:i:s');
            
            // Preparar la consulta SQL para insertar el comentario
            $sql = "INSERT INTO ProyectosComentarios (IdProyecto, Autor, Comentario, FechaCreacion) VALUES (?, ?, ?, ?)";
            
            // Convertir a tipos adecuados para SQL Server
            $idProyectoInt = intval($idProyecto);
            
            // Crear parámetros con tipos específicos para evitar conversiones implícitas
            $params = array(
                array($idProyectoInt, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_INT),
                array($autor, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR(255)),
                array($comentario, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_NVARCHAR('max')),
                array($fechaActual, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR), SQLSRV_SQLTYPE_DATETIME)
            );
            
            // Usar sqlsrv_prepare para preparar la consulta con los parámetros tipados
            $stmt = sqlsrv_prepare($this->cid_central, $sql, $params);
            
            if (!$stmt) {
                throw new Exception("Error al preparar la consulta: " . print_r(sqlsrv_errors(), true));
            }
            
            // Ejecutar la consulta preparada
            if (!sqlsrv_execute($stmt)) {
                throw new Exception("Error al agregar el comentario: " . print_r(sqlsrv_errors(), true));
            }
            
            return true;
            
        } catch (\Throwable $th) {
            error_log("Error en agregarComentario: " . $th->getMessage());
            throw $th;
        }
    }
    
    public function obtenerComentarios($idProyecto) 
    {
        // Comprobamos si la tabla existe, si no, la creamos
        $this->verificarTablasComentarios();
        
        try {
            // Verificar que la conexión existe
            if (!$this->cid_central) {
                throw new Exception("No hay conexión a la base de datos central");
            }
            
            // Preparar la consulta SQL para obtener los comentarios
            $sql = "SELECT * FROM ProyectosComentarios WHERE IdProyecto = ? ORDER BY FechaCreacion DESC";
            
            $params = array($idProyecto);
            
            $result = sqlsrv_query($this->cid_central, $sql, $params);
            
            if ($result === false) {
                throw new Exception("Error al obtener los comentarios: " . print_r(sqlsrv_errors(), true));
            }
            
            $comentarios = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                // Formatear fecha
                if (isset($row['FechaCreacion']) && $row['FechaCreacion'] instanceof DateTime) {
                    $row['FechaCreacion'] = $row['FechaCreacion']->format('Y-m-d H:i:s');
                }
                
                $comentarios[] = $row;
            }
            
            return $comentarios;
            
        } catch (\Throwable $th) {
            error_log("Error en obtenerComentarios: " . $th->getMessage());
            throw $th;
        }
    }
    
    private function verificarTablasComentarios() 
    {
        try {
            // Verificar que la conexión existe
            if (!$this->cid_central) {
                throw new Exception("No hay conexión a la base de datos central");
            }
            
            // Verificar si la tabla de comentarios existe
            $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'ProyectosComentarios'";
            
            $result = sqlsrv_query($this->cid_central, $sql);
            
            if ($result === false) {
                throw new Exception("Error al verificar la tabla de comentarios: " . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            
            // Si la tabla no existe, la creamos
            if ($row['count'] == 0) {
                $sqlCrear = "CREATE TABLE ProyectosComentarios (
                    Id INT IDENTITY(1,1) PRIMARY KEY,
                    IdProyecto INT NOT NULL,
                    Autor NVARCHAR(255) NOT NULL,
                    Comentario NVARCHAR(MAX) NOT NULL,
                    FechaCreacion DATETIME NOT NULL
                )";
                
                $resultCrear = sqlsrv_query($this->cid_central, $sqlCrear);
                
                if ($resultCrear === false) {
                    throw new Exception("Error al crear la tabla de comentarios: " . print_r(sqlsrv_errors(), true));
                }
            } else {
                // Si la tabla existe, verificamos si necesita ser alterada para corregir tipos
                $sql = "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = 'ProyectosComentarios' AND COLUMN_NAME = 'FechaCreacion'";
                $result = sqlsrv_query($this->cid_central, $sql);
                
                if ($result === false) {
                    throw new Exception("Error al verificar el tipo de columna FechaCreacion: " . print_r(sqlsrv_errors(), true));
                }
                
                $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                
                // Si el tipo no es DATETIME, alteramos la tabla
                if ($row && $row['DATA_TYPE'] !== 'datetime') {
                    $sqlAlter = "ALTER TABLE ProyectosComentarios ALTER COLUMN FechaCreacion DATETIME NOT NULL";
                    $resultAlter = sqlsrv_query($this->cid_central, $sqlAlter);
                    
                    if ($resultAlter === false) {
                        throw new Exception("Error al modificar la columna FechaCreacion: " . print_r(sqlsrv_errors(), true));
                    }
                }
            }
            
            return true;
            
        } catch (\Throwable $th) {
            error_log("Error en verificarTablasComentarios: " . $th->getMessage());
            throw $th;
        }
    }
    
    public function obtenerFiltros() {
        $filtros = [
            'sectores' => [],
            'estados' => [],
            'etapas' => [],
            'lideres' => []
        ];
        
        try {
            // Obtener sectores únicos
            $sql = "SELECT DISTINCT Sector FROM HistorialProyectos WHERE Sector IS NOT NULL ORDER BY Sector";
            $result = sqlsrv_query($this->cid_central, $sql);
            if ($result === false) {
                throw new Exception("Error al obtener sectores: " . print_r(sqlsrv_errors(), true));
            }
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $filtros['sectores'][] = $row['Sector'];
            }
            
            // Obtener estados únicos
            $sql = "SELECT DISTINCT Estado FROM HistorialProyectos WHERE Estado IS NOT NULL ORDER BY Estado";
            $result = sqlsrv_query($this->cid_central, $sql);
            if ($result === false) {
                throw new Exception("Error al obtener estados: " . print_r(sqlsrv_errors(), true));
            }
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $filtros['estados'][] = $row['Estado'];
            }
            
            // Obtener etapas únicas
            $sql = "SELECT DISTINCT EtapaProyecto FROM HistorialProyectos WHERE EtapaProyecto IS NOT NULL ORDER BY EtapaProyecto";
            $result = sqlsrv_query($this->cid_central, $sql);
            if ($result === false) {
                throw new Exception("Error al obtener etapas: " . print_r(sqlsrv_errors(), true));
            }
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $filtros['etapas'][] = $row['EtapaProyecto'];
            }
            
            // Obtener líderes de proyecto únicos
            $sql = "SELECT DISTINCT LiderProyecto FROM HistorialProyectos WHERE LiderProyecto IS NOT NULL ORDER BY LiderProyecto";
            $result = sqlsrv_query($this->cid_central, $sql);
            if ($result === false) {
                throw new Exception("Error al obtener líderes: " . print_r(sqlsrv_errors(), true));
            }
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $filtros['lideres'][] = $row['LiderProyecto'];
            }
            
            return $filtros;
            
        } catch (\Throwable $th) {
            error_log("Error en obtenerFiltros: " . $th->getMessage());
            throw $th;
        }
    }
    
    private function formatearFechas(&$row) {
        // Formatear todas las fechas en el array
        $camposFecha = [
            'FechaCreacion', 'FechaFinalizacion', 'InicioProgramado', 
            'FechaLimite', 'InicioReal', 'FinalizacionReal'
        ];
        
        foreach ($camposFecha as $campo) {
            if (isset($row[$campo]) && $row[$campo] instanceof DateTime) {
                $row[$campo] = $row[$campo]->format('Y-m-d');
            }
        }
        
        return $row;
    }
    
    private function calcularPorcentajeAvance($etapa) {
        $avances = [
            'Pendiente' => 0.00,
            'Asignado' => 0.01,
            'Diagnostico' => 0.15,
            'Diseño' => 0.30,
            'Desarrollo' => 0.45,
            'Prueba y evaluación' => 0.60,
            'Modificaciones/correcciones' => 0.75,
            'Implementación' => 0.85,
            'Finalizado' => 1.00
        ];
        
        // Si es un ID numérico, convertir a nombre de etapa
        if (is_numeric($etapa)) {
            $etapasId = [
                1 => 'Pendiente',
                2 => 'Asignado',
                3 => 'Diagnostico',
                4 => 'Diseño',
                5 => 'Desarrollo',
                7 => 'Prueba y evaluación',
                8 => 'Modificaciones/correcciones',
                9 => 'Implementación',
                10 => 'Finalizado'
            ];
            
            $etapa = isset($etapasId[$etapa]) ? $etapasId[$etapa] : $etapa;
        }
        
        return isset($avances[$etapa]) ? $avances[$etapa] : 0;
    }

}