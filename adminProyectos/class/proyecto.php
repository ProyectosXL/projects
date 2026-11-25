<?php

require_once __DIR__ . '/programa.php';
require_once __DIR__ . '/persona.php';

class Proyecto
{
    // Ventana de aceptación tácita para la etapa "Validación cliente", en días corridos
    const DIAS_VENTANA_ACEPTACION = 10;

    private $conexion;
    private $cid_central;
    
    function __construct() 
    {
        // Ruta ajustada para llegar a conexion.php basada en la estructura actual
        $rutaConexion = __DIR__ . '/../../class/conexion.php';
        
        // Verificar si el archivo existe
        if (!file_exists($rutaConexion)) {
            throw new Exception("Error crítico: El archivo conexion.php no existe en la ruta: " . $rutaConexion);
        }

        require_once $rutaConexion;
        $this->conexion = new Conexion();
        $this->cid_central = $this->conexion->conectar('central');

        if (!$this->cid_central) {
            $error = print_r(sqlsrv_errors(), true);
            throw new Exception("Error de conexión a la base de datos: " . $error);
        }
        
        // Verificar y crear tablas/campos necesarios
        $this->verificarTablaHistorialProyectos();
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
     * Convierte texto a UTF-8 para asegurar compatibilidad con SQL Server
     */
    private function asegurarUTF8($texto) {
        if (!is_string($texto)) {
            return $texto;
        }
        
        // Detectar encoding actual
        $encoding = mb_detect_encoding($texto, 'UTF-8, ISO-8859-1', true);
        
        // Convertir a UTF-8 si es necesario
        if ($encoding !== 'UTF-8') {
            return mb_convert_encoding($texto, 'UTF-8', $encoding);
        }
        
        return $texto;
    }
    
    /**
     * Instancia de Persona (listado PersonasProyectos), creada recién cuando se necesita
     */
    private $personaObj = null;

    private function persona()
    {
        if ($this->personaObj === null) {
            // Reutiliza la conexión de Proyecto (ver Persona::__construct)
            $this->personaObj = new Persona($this->cid_central);
        }

        return $this->personaObj;
    }

    /**
     * Personas activas con el rol indicado ('solicitante', 'lider' o
     * 'desarrollador'), en orden alfabético, desde PersonasProyectos
     * (se administra en Parámetros › Personas).
     *
     * Se mantienen las claves históricas (NOMBRE_APELLIDO, MAIL,
     * APELLIDO_Y_NOMBRE, NOMBRE_FORMATEADO) para no romper a quien las usa.
     */
    public function obtenerPersonasPorRol($rol)
    {
        try {
            $personas = [];

            foreach ($this->persona()->listarPorRol($rol) as $persona) {
                $personas[] = [
                    'NOMBRE_APELLIDO' => $persona['Nombre'],
                    'MAIL' => $persona['Mail'],
                    'APELLIDO_Y_NOMBRE' => $persona['Nombre'], // Texto a mostrar
                    'NOMBRE_FORMATEADO' => $persona['Nombre']  // Valor que se guarda en HistorialProyectos
                ];
            }

            return $personas;
        } catch (\Throwable $th) {
            error_log("Error en obtenerPersonasPorRol($rol): " . $th->getMessage());
            throw $th;
        }
    }

    /**
     * Solicitantes: personas activas con EsSolicitante = 1
     */
    public function obtenerSolicitantes()
    {
        return $this->obtenerPersonasPorRol('solicitante');
    }

    /**
     * Si el nombre es una persona activa con ese rol, devuelve el Nombre tal
     * como está en el listado (sin distinguir mayúsculas ni acentos); si no, null.
     */
    public function nombrePersonaActivaConRol($rol, $nombre)
    {
        return $this->persona()->nombreActivoConRol($rol, $nombre);
    }

    /**
     * Valida el valor recibido para un combo de persona. Se acepta:
     *   - una persona activa con ese rol (se devuelve el Nombre del listado), o
     *   - el valor que el proyecto ya tenía (opción "(fuera de lista)").
     * Lanza Exception si no es válido. El valor vacío lo resuelve quien llama.
     */
    public function validarPersonaParaRol($rol, $valor, $valorActual = null)
    {
        // El valor que el proyecto ya tenía se acepta tal cual (se compara antes de
        // limpiar el texto, para que un valor viejo siempre coincida consigo mismo)
        if ($valorActual !== null && trim((string)$valor) === trim((string)$valorActual)) {
            return trim((string)$valorActual);
        }

        $valor = $this->limpiarTextoUTF8((string)$valor);

        $nombre = $this->nombrePersonaActivaConRol($rol, $valor);

        if ($nombre === null) {
            $textos = ['solicitante' => 'Solicitante', 'lider' => 'Líder de Proyecto', 'desarrollador' => 'Desarrollador'];
            throw new Exception('"' . $valor . '" no es una persona activa habilitada como ' . ($textos[$rol] ?? $rol) . '. Revisá el listado en Parámetros › Personas.');
        }

        return $nombre;
    }

    /**
     * Resuelve el nombre de una persona a partir de su username de sesión,
     * consultando SOF_USUARIOS (NOMBRE -> DESCRIPCION). Si no se encuentra o
     * falla la consulta, devuelve el username tal cual para no romper al
     * llamador.
     */
    public function obtenerNombreUsuario($username)
    {
        if (empty($username)) {
            return $username;
        }

        try {
            $sql = "SELECT DESCRIPCION FROM SOF_USUARIOS WHERE NOMBRE = ?";
            $result = sqlsrv_query($this->cid_central, $sql, array($username));

            if ($result === false) {
                error_log("Error al obtener nombre de usuario: " . print_r(sqlsrv_errors(), true));
                return $username;
            }

            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            return (!empty($row['DESCRIPCION'])) ? $row['DESCRIPCION'] : $username;
        } catch (\Throwable $th) {
            error_log("Error en obtenerNombreUsuario: " . $th->getMessage());
            return $username;
        }
    }

    /**
     * Crea un nuevo proyecto en la base de datos
     * Versión con formato de fecha corregido
     */
    public function crearProyecto($datos, $documentos = [])
    {
        try {
            // Verificar si la tabla HistorialProyectos existe
            $this->verificarTablaHistorialProyectos();
            
            // Limpiar caracteres especiales y asegurar UTF-8
            $datosLimpios = [
                'TipoPresentacion' => $this->limpiarTextoUTF8($datos['TipoPresentacion']),
                'Desarrollo' => $this->limpiarTextoUTF8($datos['Desarrollo']),
                'Solicitante' => $this->limpiarTextoUTF8($datos['Solicitante']),
                'Prioridad' => $this->limpiarTextoUTF8($datos['Prioridad']),
                'Pais' => $this->limpiarTextoUTF8($datos['Pais']),
                'Sector' => $this->limpiarTextoUTF8($datos['Sector']),
                'Funcionalidad' => $this->limpiarTextoUTF8($datos['Funcionalidad'])
            ];
            
            // Log para debugging
            error_log('Datos limpios para insertar: ' . print_r($datosLimpios, true));
            
            // Mapear los campos a los nombres de la base de datos
            $datosParaDB = [
                'TipoPresentacion' => $datosLimpios['TipoPresentacion'],
                'Desarrollo' => $datosLimpios['Desarrollo'],
                'Solicitante' => $datosLimpios['Solicitante'],
                'Prioridad' => !empty($datosLimpios['Prioridad']) ? $datosLimpios['Prioridad'] : 'Sin asignar',
                'Pais' => $datosLimpios['Pais'],
                'Sector' => $datosLimpios['Sector'],
                'Funcionalidad' => $datosLimpios['Funcionalidad'],
                
                // Campos por defecto para proyectos nuevos
                'Estado' => 'Pendiente',
                'EtapaProyecto' => 'Pendiente',
                'FechaCreacion' => date('Y-m-d'),
                'PorcentajeAvance' => 0
            ];
            
            // SOLUCIÓN 1: Usar GETDATE() de SQL Server en lugar de fecha de PHP
            $sql = "INSERT INTO HistorialProyectos (
                        TipoPresentacion, 
                        Desarrollo,
                        Solicitante, 
                        Prioridad,
                        Pais, 
                        Sector, 
                        Funcionalidad, 
                        FechaCreacion, 
                        Estado, 
                        EtapaProyecto
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, ?)";
            
            // Preparar parámetros (sin fecha - usamos GETDATE())
            $params = array(
                $datosParaDB['TipoPresentacion'],
                $datosParaDB['Desarrollo'],
                $datosParaDB['Solicitante'],
                $datosParaDB['Prioridad'],
                $datosParaDB['Pais'],
                $datosParaDB['Sector'],
                $datosParaDB['Funcionalidad'],
                'Pendiente',
                'Pendiente'
            );
            
            error_log('SQL: ' . $sql);
            error_log('Parámetros: ' . print_r($params, true));
            
            // Ejecutar la consulta
            $stmt = sqlsrv_query($this->cid_central, $sql, $params);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log('Error al insertar proyecto: ' . print_r($errors, true));
                throw new Exception("Error al insertar el proyecto: " . print_r($errors, true));
            }
            
            // Verificar cuántas filas fueron afectadas
            $filasAfectadas = sqlsrv_rows_affected($stmt);
            
            // Liberar statement
            sqlsrv_free_stmt($stmt);
            
            if ($filasAfectadas > 0) {
                error_log("✅ Proyecto insertado exitosamente. Filas afectadas: $filasAfectadas");
                error_log("📅 Fecha insertada usando GETDATE() del servidor SQL");
                
                // Retornar el nombre del proyecto para el mensaje de éxito
                return $datosParaDB['Desarrollo'];
            } else {
                throw new Exception('No se insertó ningún registro');
            }
            
        } catch (Exception $e) {
            error_log("❌ Error en crearProyecto: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Limpia texto para UTF-8 correcto
     */
    private function limpiarTextoUTF8($texto) {
        if (!is_string($texto)) {
            return $texto;
        }
        
        // Convertir a UTF-8 desde diferentes encodings posibles
        $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252'];
        $encoding = mb_detect_encoding($texto, $encodings, true);
        
        if ($encoding && $encoding !== 'UTF-8') {
            $texto = mb_convert_encoding($texto, 'UTF-8', $encoding);
        }
        
        // Normalizar y limpiar caracteres problemáticos específicos
        $texto = trim($texto);
        $texto = str_replace(['â€œ', 'â€', 'â€™', 'Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº', 'Ã±'], 
                            ['"', '"', "'", 'á', 'é', 'í', 'ó', 'ú', 'ñ'], $texto);
        
        return $texto;
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
                Prioridad NVARCHAR(50) NULL,
                URLRequerimiento NVARCHAR(500) NULL,
                FechaEntregaCliente DATETIME NULL,
                FechaAprobacionCliente DATETIME NULL,
                TipoAprobacionCliente VARCHAR(10) NULL,
                UsuarioAprobacionCliente VARCHAR(100) NULL
            )";
            
            $resultCrear = sqlsrv_query($this->cid_central, $sqlCrear);
            
            if ($resultCrear === false) {
                throw new Exception('Error al crear la tabla HistorialProyectos: ' . print_r(sqlsrv_errors(), true));
            }
        } else {
            // Si la tabla existe, verificamos si necesita ser alterada para corregir tipos
            $sql = "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = 'HistorialProyectos' AND COLUMN_NAME = 'FechaCreacion'";
            $result = sqlsrv_query($this->cid_central, $sql);
            
            if ($result === false) {
                throw new Exception('Error al verificar el tipo de columna FechaCreacion: ' . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            
            // Si el tipo no es DATETIME, alteramos la tabla
            if ($row && $row['DATA_TYPE'] !== 'datetime') {
                $sqlAlter = "ALTER TABLE HistorialProyectos ALTER COLUMN FechaCreacion DATETIME NOT NULL";
                $resultAlter = sqlsrv_query($this->cid_central, $sqlAlter);
                
                if ($resultAlter === false) {
                    throw new Exception('Error al modificar la columna FechaCreacion: ' . print_r(sqlsrv_errors(), true));
                }
            }
            
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

            // Verificar si la columna URLRequerimiento existe, y si no, agregarla
            $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = 'HistorialProyectos' AND COLUMN_NAME = 'URLRequerimiento'";
            $result = sqlsrv_query($this->cid_central, $sql);
            
            if ($result === false) {
                throw new Exception('Error al verificar la columna URLRequerimiento: ' . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            
            if ($row['count'] == 0) {
                // Agregar la columna URLRequerimiento
                $sqlAlter = "ALTER TABLE HistorialProyectos ADD URLRequerimiento NVARCHAR(500)";
                $resultAlter = sqlsrv_query($this->cid_central, $sqlAlter);
                
                if ($resultAlter === false) {
                    throw new Exception('Error al agregar la columna URLRequerimiento: ' . print_r(sqlsrv_errors(), true));
                }
            }

            // Verificar si la columna Desarrollador existe, y si no, agregarla
            $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = 'HistorialProyectos' AND COLUMN_NAME = 'Desarrollador'";
            $result = sqlsrv_query($this->cid_central, $sql);
            
            if ($result === false) {
                throw new Exception('Error al verificar la columna Desarrollador: ' . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            
            if ($row['count'] == 0) {
                // Agregar la columna Desarrollador
                $sqlAlter = "ALTER TABLE HistorialProyectos ADD Desarrollador NVARCHAR(255)";
                $resultAlter = sqlsrv_query($this->cid_central, $sqlAlter);
                
                if ($resultAlter === false) {
                    throw new Exception('Error al agregar la columna Desarrollador: ' . print_r(sqlsrv_errors(), true));
                }
            }

            // La columna FinalizacionReal ya está definida en la estructura inicial de la tabla
            // pero verificamos por si acaso en instalaciones existentes
            $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = 'HistorialProyectos' AND COLUMN_NAME = 'FinalizacionReal'";
            $result = sqlsrv_query($this->cid_central, $sql);
            
            if ($result === false) {
                throw new Exception('Error al verificar la columna FinalizacionReal: ' . print_r(sqlsrv_errors(), true));
            }
            
            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            
            if ($row['count'] == 0) {
                // Agregar la columna FinalizacionReal
                $sqlAlter = "ALTER TABLE HistorialProyectos ADD FinalizacionReal DATE";
                $resultAlter = sqlsrv_query($this->cid_central, $sqlAlter);

                if ($resultAlter === false) {
                    throw new Exception('Error al agregar la columna FinalizacionReal: ' . print_r(sqlsrv_errors(), true));
                }
            }

            // Verificar si la columna InicioProgramado existe, y si no, agregarla
            $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = 'HistorialProyectos' AND COLUMN_NAME = 'InicioProgramado'";
            $result = sqlsrv_query($this->cid_central, $sql);

            if ($result === false) {
                throw new Exception('Error al verificar la columna InicioProgramado: ' . print_r(sqlsrv_errors(), true));
            }

            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

            if ($row['count'] == 0) {
                $sqlAlter = "ALTER TABLE HistorialProyectos ADD InicioProgramado DATE NULL";
                $resultAlter = sqlsrv_query($this->cid_central, $sqlAlter);

                if ($resultAlter === false) {
                    throw new Exception('Error al agregar la columna InicioProgramado: ' . print_r(sqlsrv_errors(), true));
                }
            }

            // Verificar si la columna Funcionalidad es NVARCHAR(MAX)
            $sql = "SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = 'HistorialProyectos' AND COLUMN_NAME = 'Funcionalidad'";
            $result = sqlsrv_query($this->cid_central, $sql);
            if ($result && $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                if ($row['CHARACTER_MAXIMUM_LENGTH'] != -1) {
                    $sqlAlter = "ALTER TABLE HistorialProyectos ALTER COLUMN Funcionalidad NVARCHAR(MAX)";
                    sqlsrv_query($this->cid_central, $sqlAlter);
                }
            }

            // Columnas de validación del cliente (etapa "Validación cliente")
            $columnasValidacionCliente = [
                'FechaEntregaCliente' => 'DATETIME NULL',
                'FechaAprobacionCliente' => 'DATETIME NULL',
                'TipoAprobacionCliente' => 'VARCHAR(10) NULL',
                'UsuarioAprobacionCliente' => 'VARCHAR(100) NULL'
            ];

            foreach ($columnasValidacionCliente as $columna => $tipo) {
                $sql = "SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_NAME = 'HistorialProyectos' AND COLUMN_NAME = ?";
                $result = sqlsrv_query($this->cid_central, $sql, array($columna));

                if ($result === false) {
                    throw new Exception("Error al verificar la columna $columna: " . print_r(sqlsrv_errors(), true));
                }

                $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

                if ($row['count'] == 0) {
                    $sqlAlter = "ALTER TABLE HistorialProyectos ADD $columna $tipo";
                    $resultAlter = sqlsrv_query($this->cid_central, $sqlAlter);

                    if ($resultAlter === false) {
                        throw new Exception("Error al agregar la columna $columna: " . print_r(sqlsrv_errors(), true));
                    }
                }
            }

            // Migración de datos: renombrar etapas antiguas a los nuevos nombres
            $renombresEtapas = [
                'Prueba y evaluación' => 'Validación cliente',
                'Modificaciones/correcciones' => 'Correcciones y mejoras'
            ];

            foreach ($renombresEtapas as $etapaAnterior => $etapaNueva) {
                $sql = "SELECT COUNT(*) AS count FROM HistorialProyectos WHERE EtapaProyecto = ?";
                $result = sqlsrv_query($this->cid_central, $sql, array($etapaAnterior));

                if ($result === false) {
                    throw new Exception("Error al verificar etapas pendientes de renombrar ($etapaAnterior): " . print_r(sqlsrv_errors(), true));
                }

                $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

                if ($row['count'] > 0) {
                    $sqlUpdate = "UPDATE HistorialProyectos SET EtapaProyecto = ? WHERE EtapaProyecto = ?";
                    $resultUpdate = sqlsrv_query($this->cid_central, $sqlUpdate, array($etapaNueva, $etapaAnterior));

                    if ($resultUpdate === false) {
                        throw new Exception("Error al renombrar etapa '$etapaAnterior' a '$etapaNueva': " . print_r(sqlsrv_errors(), true));
                    }
                }
            }

            // Migración de datos: renombrar sectores antiguos a los nuevos nombres
            $renombresSectores = [
                'Locales propios' => 'Sucursales'
            ];

            foreach ($renombresSectores as $sectorAnterior => $sectorNuevo) {
                $sql = "SELECT COUNT(*) AS count FROM HistorialProyectos WHERE Sector = ?";
                $result = sqlsrv_query($this->cid_central, $sql, array($sectorAnterior));

                if ($result === false) {
                    throw new Exception("Error al verificar sectores pendientes de renombrar ($sectorAnterior): " . print_r(sqlsrv_errors(), true));
                }

                $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

                if ($row['count'] > 0) {
                    $sqlUpdate = "UPDATE HistorialProyectos SET Sector = ? WHERE Sector = ?";
                    $resultUpdate = sqlsrv_query($this->cid_central, $sqlUpdate, array($sectorNuevo, $sectorAnterior));

                    if ($resultUpdate === false) {
                        throw new Exception("Error al renombrar sector '$sectorAnterior' a '$sectorNuevo': " . print_r(sqlsrv_errors(), true));
                    }
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
        $sql = "SELECT hp.*,
                       pr.Nombre AS NombrePrograma,
                       pr.Color  AS ColorPrograma
                FROM HistorialProyectos hp WITH (NOLOCK)
                LEFT JOIN ProgramasProyectos pr WITH (NOLOCK) ON pr.ID = hp.IdPrograma";

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
                
                // Asegurar que la prioridad tenga un valor por defecto
                if (empty($row['Prioridad'])) {
                    $row['Prioridad'] = 'Sin asignar';
                }
                
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
        $sql = "SELECT hp.*,
                       pr.Nombre AS NombrePrograma,
                       pr.Color  AS ColorPrograma
                FROM HistorialProyectos hp WITH (NOLOCK)
                LEFT JOIN ProgramasProyectos pr WITH (NOLOCK) ON pr.ID = hp.IdPrograma
                WHERE hp.ID = ?";

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
            
            // Asegurar que la prioridad tenga un valor por defecto
            if (empty($row['Prioridad'])) {
                $row['Prioridad'] = 'Sin asignar';
            }
            
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

            // Si la etapa cambia, leemos la etapa anterior y los datos del proyecto
            // una sola vez: sirven tanto para la regla de "Validación cliente" como
            // para notificar por mail el cambio de etapa una vez guardado.
            $etapaAnterior = null;
            $nombreProyectoActual = null;
            $solicitanteActual = null;
            $liderActual = null;

            if (array_key_exists('EtapaProyecto', $datos)) {
                $sqlEtapaActual = "SELECT EtapaProyecto, Desarrollo, Solicitante, LiderProyecto FROM HistorialProyectos WHERE ID = ?";
                $resultEtapaActual = sqlsrv_query($this->cid_central, $sqlEtapaActual, array($id));

                if ($resultEtapaActual === false) {
                    throw new Exception("Error al verificar la etapa actual del proyecto: " . print_r(sqlsrv_errors(), true));
                }

                $rowEtapaActual = sqlsrv_fetch_array($resultEtapaActual, SQLSRV_FETCH_ASSOC);
                $etapaAnterior = $rowEtapaActual['EtapaProyecto'] ?? null;
                $nombreProyectoActual = $rowEtapaActual['Desarrollo'] ?? null;
                $solicitanteActual = $rowEtapaActual['Solicitante'] ?? null;
                $liderActual = $rowEtapaActual['LiderProyecto'] ?? null;

                // Regla de negocio: al entrar a "Validación cliente" se registra la fecha
                // de entrega y se limpia cualquier aprobación previa, para que arranque
                // una ventana de aceptación tácita nueva (cubre tanto la primera entrega
                // como cada reingreso después de una corrección)
                if ($datos['EtapaProyecto'] === 'Validación cliente' && $etapaAnterior !== 'Validación cliente') {
                    $datos['FechaEntregaCliente'] = date('Y-m-d H:i:s');
                    $datos['FechaAprobacionCliente'] = null;
                    $datos['TipoAprobacionCliente'] = null;
                    $datos['UsuarioAprobacionCliente'] = null;
                }
            }

            // Preparar la consulta SQL para actualizar los campos
            $camposActualizar = [];
            $params = [];
            
            foreach ($datos as $campo => $valor) {
                // Incluir campos con valor null explícitamente o con valores no vacíos
                // Solo excluir si el valor es una cadena vacía (pero permitir null)
                if ($valor !== '' && array_key_exists($campo, $datos)) {
                    $camposActualizar[] = "$campo = ?";
                    $params[] = $valor; // Esto puede ser null, y está bien
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

            if ($etapaAnterior !== null && $etapaAnterior !== $datos['EtapaProyecto']) {
                $this->notificarCambioEtapa($id, $nombreProyectoActual, $datos['EtapaProyecto'], $solicitanteActual, $liderActual);
            }

            return true;

        } catch (\Throwable $th) {
            error_log("Error en actualizarProyecto: " . $th->getMessage());
            throw $th;
        }
    }

    /**
     * Notifica por mail (Database Mail) el cambio de etapa de un proyecto al
     * Solicitante y al LiderProyecto. Nunca propaga errores: un fallo al
     * notificar no debe afectar el cambio de etapa que ya se guardó.
     */
    private function notificarCambioEtapa($id, $nombreProyecto, $etapaNueva, $solicitanteNombre, $liderNombre)
    {
        try {
            $mailSolicitante = $this->resolverMailPorNombre($solicitanteNombre);
            $mailLider = $this->resolverMailPorNombre($liderNombre);

            if (!$mailSolicitante && !$mailLider) {
                return;
            }

            $sql = "EXEC dbo.RO_SP_NOTIF_CAMBIO_ETAPA @IdProyecto = ?, @NombreProyecto = ?, @EstadoNuevo = ?, @MailSolicitante = ?, @MailLider = ?";
            $result = sqlsrv_query($this->cid_central, $sql, array($id, $nombreProyecto, $etapaNueva, $mailSolicitante, $mailLider));

            if ($result === false) {
                error_log("Error al notificar cambio de etapa para proyecto $id: " . print_r(sqlsrv_errors(), true));
            }
        } catch (\Throwable $th) {
            error_log("Error en notificarCambioEtapa: " . $th->getMessage());
        }
    }

    /**
     * Busca el mail de una persona por su nombre en PersonasProyectos
     * (Solicitante/LiderProyecto se guardan como texto en HistorialProyectos).
     * No importa si la persona está activa ni qué roles tiene: un proyecto
     * viejo puede tener como líder a alguien que hoy está desactivado y el
     * aviso le tiene que llegar igual. La comparación la hace SQL Server sin
     * distinguir mayúsculas, acentos ni espacios al inicio/fin (mismo criterio
     * que RO_SP_NOTIF_ADMIN_PROYECTOS). null si no está o no tiene mail.
     */
    private function resolverMailPorNombre($nombre)
    {
        if (empty($nombre) || trim($nombre) === '') {
            return null;
        }

        return $this->persona()->resolverMail($nombre);
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
            
            // Limpiar caracteres especiales y asegurar UTF-8 - IGUAL QUE EN crearProyecto
            $autorLimpio = $this->limpiarTextoUTF8($autor);
            $comentarioLimpio = $this->limpiarTextoUTF8($comentario);
            
            // Log para debugging
            error_log('Comentario original: ' . $comentario);
            error_log('Comentario limpio: ' . $comentarioLimpio);
            error_log('Autor original: ' . $autor);
            error_log('Autor limpio: ' . $autorLimpio);
            
            // Preparar la consulta SQL para insertar el comentario usando GETDATE()
            $sql = "INSERT INTO ProyectosComentarios (IdProyecto, Autor, Comentario, FechaCreacion) 
                    VALUES (?, ?, ?, GETDATE())";
            
            // Convertir a tipos adecuados para SQL Server
            $idProyectoInt = intval($idProyecto);
            
            // Preparar parámetros sin tipado específico (más simple)
            $params = array($idProyectoInt, $autorLimpio, $comentarioLimpio);
            
            // Ejecutar la consulta directamente
            $stmt = sqlsrv_query($this->cid_central, $sql, $params);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log('Error SQL al insertar comentario: ' . print_r($errors, true));
                throw new Exception("Error al agregar el comentario: " . print_r($errors, true));
            }
            
            // Verificar cuántas filas fueron afectadas
            $filasAfectadas = sqlsrv_rows_affected($stmt);
            
            // Liberar statement
            sqlsrv_free_stmt($stmt);
            
            if ($filasAfectadas > 0) {
                error_log("✅ Comentario insertado exitosamente. Filas afectadas: $filasAfectadas");
                error_log("📅 Fecha insertada usando GETDATE() del servidor SQL");
                return true;
            } else {
                throw new Exception('No se insertó ningún comentario');
            }
            
        } catch (\Throwable $th) {
            error_log("❌ Error en agregarComentario: " . $th->getMessage());
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
            $sql = "SELECT Id, IdProyecto, Autor, Comentario, FechaCreacion 
                    FROM ProyectosComentarios 
                    WHERE IdProyecto = ? 
                    ORDER BY FechaCreacion DESC";
            
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
                
                // Asegurar UTF-8 en la salida también
                $row['Autor'] = $this->asegurarUTF8($row['Autor']);
                $row['Comentario'] = $this->asegurarUTF8($row['Comentario']);
                
                // Log para verificar
                error_log('Comentario leído: ' . $row['Comentario']);
                
                $comentarios[] = $row;
            }
            
            error_log('Total comentarios obtenidos: ' . count($comentarios));
            
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
        'lideres' => [],
        'desarrolladores' => [],
        'prioridades' => [],
        'programas' => []
    ];
    
    try {
        // Obtener sectores únicos (NUEVO QUERY)
        $sql = "SELECT DISTINCT(Sector) as Sector FROM HistorialProyectos WHERE Sector IS NOT NULL ORDER BY Sector";
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
        
        // Obtener desarrolladores únicos
        $sql = "SELECT DISTINCT Desarrollador FROM HistorialProyectos WHERE Desarrollador IS NOT NULL ORDER BY Desarrollador";
        $result = sqlsrv_query($this->cid_central, $sql);
        if ($result === false) {
            throw new Exception("Error al obtener desarrolladores: " . print_r(sqlsrv_errors(), true));
        }
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            $filtros['desarrolladores'][] = $row['Desarrollador'];
        }
        
        // Obtener prioridades únicas (SOLUCIONADO - sin ORDER BY personalizado)
        $sql = "SELECT DISTINCT Prioridad FROM HistorialProyectos WHERE Prioridad IS NOT NULL";
        $result = sqlsrv_query($this->cid_central, $sql);
        if ($result === false) {
            throw new Exception("Error al obtener prioridades: " . print_r(sqlsrv_errors(), true));
        }
        
        $prioridadesDB = [];
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            $prioridadesDB[] = $row['Prioridad'];
        }
        
        // Ordenar las prioridades manualmente en PHP
        $ordenPrioridades = ['Urgente', 'Alta', 'Media', 'Baja', 'Sin asignar'];
        
        // Primero agregar las prioridades en el orden deseado si existen
        foreach ($ordenPrioridades as $prioridad) {
            if (in_array($prioridad, $prioridadesDB)) {
                $filtros['prioridades'][] = $prioridad;
            }
        }
        
        // Luego agregar cualquier prioridad adicional que no esté en el orden predefinido
        foreach ($prioridadesDB as $prioridad) {
            if (!in_array($prioridad, $filtros['prioridades'])) {
                $filtros['prioridades'][] = $prioridad;
            }
        }

        // Obtener programas para el filtro (activos + inactivos con proyectos asignados)
        try {
            $programaObj = new Programa();
            $filtros['programas'] = $programaObj->listarParaFiltro();
        } catch (\Throwable $th) {
            error_log("Error al obtener programas para filtro: " . $th->getMessage());
            $filtros['programas'] = [];
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
            'FechaLimite', 'InicioReal', 'FinalizacionReal',
            'FechaEntregaCliente', 'FechaAprobacionCliente'
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
            'Validación cliente' => 0.60,
            'Correcciones y mejoras' => 0.75,
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
                7 => 'Validación cliente',
                8 => 'Correcciones y mejoras',
                9 => 'Implementación',
                10 => 'Finalizado'
            ];
            
            $etapa = isset($etapasId[$etapa]) ? $etapasId[$etapa] : $etapa;
        }
        
        return isset($avances[$etapa]) ? $avances[$etapa] : 0;
    }

}