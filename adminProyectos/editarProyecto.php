
<?php
// Activar visualización de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?error=' . urlencode('ID de proyecto no válido'));
    exit;
}

$idProyecto = (int)$_GET['id'];

// Intentar cargar los datos del proyecto
try {
    require_once 'class/proyecto.php';
    $proyectoObj = new Proyecto();
    
    // Esta función debe existir en la clase Proyecto
    $proyecto = $proyectoObj->obtenerProyectoPorId($idProyecto);
    
    if (!$proyecto) {
        header('Location: index.php?error=' . urlencode('Proyecto no encontrado'));
        exit;
    }
    
    // Procesar el formulario si se envió
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Recoger los datos del formulario
        $datosActualizados = [
            'InicioProgramado' => $_POST['InicioProgramado'] ?? null,
            'FechaLimite' => $_POST['FechaLimite'] ?? null,
            'InicioReal' => $_POST['InicioReal'] ?? null,
            'FinalizacionReal' => $_POST['FinalizacionReal'] ?? null,
            'Estado' => $_POST['Estado'] ?? null,
            'EtapaProyecto' => $_POST['EtapaProyecto'] ?? null,
            'LiderProyecto' => $_POST['LiderProyecto'] ?? null,
            'Desarrollador' => $_POST['Desarrollador'] ?? null,
            'Prioridad' => $_POST['Prioridad'] ?? null
        ];
        
        // Procesar comentario si se proporcionó
        $comentario = $_POST['comentario'] ?? '';
        $autor = $_POST['autor'] ?? '';
        
        if (!empty($comentario) && !empty($autor)) {
            // Esta función debe implementarse en la clase Proyecto
            $proyectoObj->agregarComentario($idProyecto, $autor, $comentario);
        }
        
        // Actualizar el proyecto
        $resultado = $proyectoObj->actualizarProyecto($idProyecto, $datosActualizados);
        
        if ($resultado) {
            header('Location: detalleProyecto.php?id=' . $idProyecto . '&mensaje=actualizado');
            exit;
        } else {
            $error = "No se pudo actualizar el proyecto";
        }
    }
    
    // Obtener historial de comentarios
    $comentarios = $proyectoObj->obtenerComentarios($idProyecto);
    
} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode('Error al cargar el proyecto: ' . $e->getMessage()));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Proyecto</title>
    <link rel="icon" type="image/png" href="../images/logo-xl-project.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
    <link href="css/editarProyecto.css" rel="stylesheet">
    
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-project-diagram me-2"></i>
                Gestión de Proyectos
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Proyectos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="detalleProyecto.php?id=<?php echo $idProyecto; ?>">Detalle</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">Editar Proyecto</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="detalleProyecto.php?id=<?php echo $idProyecto; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Volver al detalle
            </a>
            <h1 class="mb-0">Editar Proyecto</h1>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <!-- Información General (No editable) -->
            <div class="form-section">
                <h3 class="section-title">Información General</h3>
                <div class="row">
                    <div class="col-md-4">
                        <div class="field-group">
                            <div class="field-label">ID</div>
                            <div class="readonly-field"><?php echo htmlspecialchars($proyecto['ID']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="field-group">
                            <div class="field-label">Desarrollo</div>
                            <div class="readonly-field"><?php echo htmlspecialchars($proyecto['Desarrollo']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="field-group">
                            <div class="field-label">Tipo de Presentación</div>
                            <div class="readonly-field"><?php echo htmlspecialchars($proyecto['TipoPresentacion'] ?? 'No especificado'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="field-group">
                            <div class="field-label">Solicitante</div>
                            <div class="readonly-field"><?php echo htmlspecialchars($proyecto['Solicitante'] ?? 'No especificado'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="field-group">
                            <div class="field-label">País</div>
                            <div class="readonly-field"><?php echo htmlspecialchars($proyecto['Pais'] ?? 'No especificado'); ?></div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="field-group">
                            <div class="field-label">Sector</div>
                            <div class="readonly-field"><?php echo htmlspecialchars($proyecto['Sector'] ?? 'No especificado'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="field-group">
                            <div class="field-label">Funcionalidad</div>
                            <div class="readonly-field"><?php echo htmlspecialchars($proyecto['Funcionalidad'] ?? 'No especificado'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Estado y Cronograma (Editable) -->
            <div class="form-section">
                <h3 class="section-title">Estado y Propiedades</h3>
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="Estado" class="form-label">Estado</label>
                            <select class="form-select" id="Estado" name="Estado">
                                <option value="">Seleccione...</option>
                                <option value="Pendiente" <?php echo ($proyecto['Estado'] ?? '') === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="Proceso" <?php echo ($proyecto['Estado'] ?? '') === 'Proceso' ? 'selected' : ''; ?>>Proceso</option>
                                <option value="Pausado" <?php echo ($proyecto['Estado'] ?? '') === 'Pausado' ? 'selected' : ''; ?>>Pausado</option>
                                <option value="Terminado" <?php echo ($proyecto['Estado'] ?? '') === 'Terminado' ? 'selected' : ''; ?>>Terminado</option>
                                <option value="Cancelado" <?php echo ($proyecto['Estado'] ?? '') === 'Cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="EtapaProyecto" class="form-label">Etapa</label>
                            <select class="form-select" id="EtapaProyecto" name="EtapaProyecto">
                                <option value="">Seleccione...</option>
                                <option value="Pendiente" <?php echo ($proyecto['EtapaProyecto'] ?? '') === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="Asignado" <?php echo ($proyecto['EtapaProyecto'] ?? '') === 'Asignado' ? 'selected' : ''; ?>>Asignado</option>
                                <option value="Diagnostico" <?php echo ($proyecto['EtapaProyecto'] ?? '') === 'Diagnostico' ? 'selected' : ''; ?>>Diagnóstico</option>
                                <option value="Diseño" <?php echo ($proyecto['EtapaProyecto'] ?? '') === 'Diseño' ? 'selected' : ''; ?>>Diseño</option>
                                <option value="Desarrollo" <?php echo ($proyecto['EtapaProyecto'] ?? '') === 'Desarrollo' ? 'selected' : ''; ?>>Desarrollo</option>
                                <option value="Prueba y evaluación" <?php echo ($proyecto['EtapaProyecto'] ?? '') === 'Prueba y evaluación' ? 'selected' : ''; ?>>Prueba y evaluación</option>
                                <option value="Modificaciones/correcciones" <?php echo ($proyecto['EtapaProyecto'] ?? '') === 'Modificaciones/correcciones' ? 'selected' : ''; ?>>Modificaciones/correcciones</option>
                                <option value="Implementación" <?php echo ($proyecto['EtapaProyecto'] ?? '') === 'Implementación' ? 'selected' : ''; ?>>Implementación</option>
                                <option value="Finalizado" <?php echo ($proyecto['EtapaProyecto'] ?? '') === 'Finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="Prioridad" class="form-label">Prioridad</label>
                            <select class="form-select" id="Prioridad" name="Prioridad">
                                <option value="">Seleccione...</option>
                                <option value="Alta" <?php echo ($proyecto['Prioridad'] ?? '') === 'Alta' ? 'selected' : ''; ?>>Alta</option>
                                <option value="Media" <?php echo ($proyecto['Prioridad'] ?? '') === 'Media' ? 'selected' : ''; ?>>Media</option>
                                <option value="Baja" <?php echo ($proyecto['Prioridad'] ?? '') === 'Baja' ? 'selected' : ''; ?>>Baja</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="LiderProyecto" class="form-label">Líder de Proyecto</label>
                            <input type="text" class="form-control" id="LiderProyecto" name="LiderProyecto" 
                                   value="<?php echo htmlspecialchars($proyecto['LiderProyecto'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="Desarrollador" class="form-label">Desarrollador</label>
                            <input type="text" class="form-control" id="Desarrollador" name="Desarrollador" 
                                   value="<?php echo htmlspecialchars($proyecto['Desarrollador'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cronograma (Editable) -->
            <div class="form-section">
                <h3 class="section-title">Cronograma</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="InicioProgramado" class="form-label">Inicio Programado</label>
                            <input type="date" class="form-control" id="InicioProgramado" name="InicioProgramado" 
                                   value="<?php echo htmlspecialchars($proyecto['InicioProgramado'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="FechaLimite" class="form-label">Fecha Límite</label>
                            <input type="date" class="form-control" id="FechaLimite" name="FechaLimite" 
                                   value="<?php echo htmlspecialchars($proyecto['FechaLimite'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="InicioReal" class="form-label">Inicio Real</label>
                            <input type="date" class="form-control" id="InicioReal" name="InicioReal" 
                                   value="<?php echo htmlspecialchars($proyecto['InicioReal'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="FinalizacionReal" class="form-label">Finalización Real</label>
                            <input type="date" class="form-control" id="FinalizacionReal" name="FinalizacionReal" 
                                   value="<?php echo htmlspecialchars($proyecto['FinalizacionReal'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Agregar Comentario -->
            <div class="form-section">
                <h3 class="section-title">Agregar Comentario</h3>
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="autor" class="form-label">Responsable</label>
                            <select class="form-select" id="autor" name="autor">
                                <option value="">Seleccionar responsable...</option>
                                <option value="Ramiro Orozco">Ramiro Orozco</option>
                                <option value="Eduardo Berga">Eduardo Berga</option>
                                <option value="Agustina Latorre">Agustina Latorre</option>
                                <option value="Santos García">Santos García</option>
                            </select>
                            <div class="form-text text-muted">Seleccione un responsable si desea añadir un comentario</div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="comentario" class="form-label">Comentario</label>
                            <textarea class="form-control" id="comentario" name="comentario" rows="3" placeholder="Ingrese su comentario o anotación (opcional)..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Historial de Comentarios -->
            <div class="form-section">
                <h3 class="section-title">Historial de Comentarios</h3>
                <?php if (empty($comentarios)): ?>
                    <p class="text-muted">No hay comentarios registrados para este proyecto.</p>
                <?php else: ?>
                    <?php foreach ($comentarios as $comentario): ?>
                        <div class="comment-item">
                            <div class="d-flex justify-content-between">
                                <span class="comment-author"><?php echo htmlspecialchars($comentario['Autor']); ?></span>
                                <span class="comment-date"><?php echo htmlspecialchars($comentario['FechaCreacion']); ?></span>
                            </div>
                            <div class="comment-text">
                                <?php echo nl2br(htmlspecialchars($comentario['Comentario'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Botones de acción -->
            <div class="d-flex justify-content-between mb-5">
                <a href="detalleProyecto.php?id=<?php echo $idProyecto; ?>" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Guardar Cambios
                </button>
            </div>
        </form>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/editarProyecto.js"></script>

</body>
</html>