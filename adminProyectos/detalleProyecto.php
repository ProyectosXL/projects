
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Proyecto</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
    <link href="css/detalleProyecto.css" rel="stylesheet">
    <link href="css/drive-integration.css" rel="stylesheet">
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
                        <a class="nav-link active" href="#">Detalle de Proyecto</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Volver al listado
            </a>
            <a href="editarProyecto.php" id="btnEditar" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i> Editar Proyecto
            </a>
        </div>
        
        <div id="alertContainer"></div>
        
        <div class="project-header">
            <div class="project-title">
                <span class="project-id" id="proyectoId"></span>
                <h1 id="proyectoTitulo"></h1>
            </div>
            
            <div class="project-info row">
                <div class="col-md-3 mb-3">
                    <div class="info-label">Estado:</div>
                    <span class="badge" id="proyectoEstado"></span>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Etapa:</div>
                    <div id="proyectoEtapa"></div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Prioridad:</div>
                    <span class="prioridad-badge" id="proyectoPrioridad"></span>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Avance:</div>
                    <div class="circular-progress" id="proyectoProgreso">
                        <span id="proyectoProgresoTexto"></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pestañas de información -->
        <ul class="nav nav-tabs" id="projectTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">
                    <i class="fas fa-info-circle me-2"></i>Detalles
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                    <i class="fas fa-history me-2"></i>Historial
                    <span class="badge bg-primary d-none" id="comentariosCount"></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="files-tab" data-bs-toggle="tab" data-bs-target="#files" type="button" role="tab" aria-controls="files" aria-selected="false">
                    <i class="fas fa-file-alt me-2"></i>Documentos
                    <span class="badge bg-primary d-none" id="documentosCount"></span>
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="projectTabsContent">
            <!-- Pestaña de Detalles -->
            <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3">Información General</h5>
                        <table class="table table-striped">
                            <tbody id="detallesGenerales">
                                <!-- Se cargará dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5 class="mb-3">Cronograma</h5>
                        <table class="table table-striped">
                            <tbody id="detallesCronograma">
                                <!-- Se cargará dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <h5 class="mb-3">Descripción</h5>
                        <div class="card">
                            <div class="card-body">
                                <p id="proyectoDescripcion">Cargando...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pestaña de Historial -->
            <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Historial de Comentarios</h5>
                    <a href="editarProyecto.php" id="btnAgregarComentario" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> Agregar Comentario
                    </a>
                </div>
                
                <div class="comentarios-container">
                    <!-- Se cargará dinámicamente -->
                    <div class="alert alert-info">
                        <i class="fas fa-spinner fa-spin me-2"></i>
                        Cargando comentarios...
                    </div>
                </div>
            </div>
            
            <!-- Pestaña de Documentos -->
            <div class="tab-pane fade" id="files" role="tabpanel" aria-labelledby="files-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Documentos del Proyecto</h5>
                    <button type="button" class="btn btn-sm btn-primary" id="btnSubirDocumento">
                        <i class="fas fa-upload me-1"></i> Subir Documento
                    </button>
                </div>
                
                <div id="dropzone-container" class="dropzone-container mb-4 d-none">
                    <div id="dropzone" class="dropzone">
                        <div class="dz-message">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                            <h5>Arrastra archivos aquí o haz clic para seleccionar</h5>
                            <p class="text-muted">Tipos permitidos: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, ZIP (Max. 10MB)</p>
                        </div>
                    </div>
                    <div class="text-end mt-2">
                        <button type="button" class="btn btn-secondary btn-sm" id="btnCancelarSubida">Cancelar</button>
                    </div>
                </div>
                
                <div id="documentos-lista" class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Tamaño</th>
                                <th>Fecha</th>
                                <th>Subido por</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="documentos-container">
                            <!-- Aquí se cargarán los documentos dinámicamente -->
                            <tr class="sin-documentos">
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
                                    <p>Cargando documentos...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="confirmarEliminarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar el documento "<span id="documentoNombre"></span>"?</p>
                    <p class="mb-0 text-danger">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">Eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Visualización de Documento -->
    <div class="modal fade" id="visorDocumentoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Visor de Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="visorContenido" class="visor-contenido">
                        <!-- Aquí se cargará el visor del documento -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a href="#" class="btn btn-primary" id="btnDescargarDocumento" download>
                        <i class="fas fa-download me-1"></i> Descargar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Detalles del Comentario -->
    <div class="modal fade" id="detalleComentarioModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Detalle del Comentario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="comentario-completo">
                        <div class="d-flex justify-content-between mb-2">
                            <h5 id="modalComentarioAutor" class="text-primary"></h5>
                            <span id="modalComentarioFecha" class="text-muted"></span>
                        </div>
                        <div class="border-top pt-2">
                            <p id="modalComentarioTexto"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="js/detalleProyectoUI.js"></script>
    <script src="js/detalleProyectoPestanas.js"></script>
    <script src="js/detalleProyectoComentarios.js"></script>
    <script src="js/detalleProyectoDocumentos.js"></script>
    <script src="js/detalleProyecto.js"></script>
    
    <script>
        // Inicializar el botón de editar con el ID correcto
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const proyectoId = urlParams.get('id');
            
            if (proyectoId) {
                document.getElementById('btnEditar').href = `editarProyecto.php?id=${proyectoId}`;
                document.getElementById('btnAgregarComentario').href = `editarProyecto.php?id=${proyectoId}#comentario`;
            }
        });
    </script>
</body>
</html>