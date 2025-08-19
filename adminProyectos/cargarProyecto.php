
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario de Proyecto</title>
    <link rel="icon" type="image/png" href="../images/logo-xl-project.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/carga.css">
</head>
<body>
    <div class="container">
        <form class="formulario-proyecto p-4 rounded shadow" id="formProyecto">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-primary mb-0">
                    <i class="bi bi-clipboard-data me-2"></i>Formulario de Proyecto
                </h2>
                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#ayudaModal">
                    <i class="bi bi-question-circle me-1"></i>Ayuda
                </button>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="id" class="form-label">ID Proyecto</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="id" name="id" placeholder="Generado automáticamente" disabled>
                        <span class="input-group-text">
                            <i class="bi bi-info-circle" title="El ID se genera automáticamente al guardar"></i>
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="tipo" class="form-label">Tipo/Presentación</label>
                    <select class="form-select select2" id="tipo" name="tipo" required>
                        <option value="">Seleccione tipo</option>
                        <option value="Aplicación Web">Aplicación Web</option>
                        <option value="Integración">Integración</option>
                        <option value="Política">Política</option>
                        <option value="Power BI">Power BI</option>
                        <option value="Procedimiento">Procedimiento</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="desarrollo" class="form-label">Nombre del Proyecto</label>
                <input type="text" class="form-control" id="desarrollo" name="desarrollo" placeholder="Ingrese el nombre del proyecto" required>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="solicitante" class="form-label">Solicitante</label>
                    <select class="form-select select2" id="solicitante" name="solicitante" required>
                        <option value="">Seleccione solicitante</option>
                        <!-- Se cargará dinámicamente desde JS con email en tooltip -->
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="prioridad" class="form-label">Prioridad</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="prioridad" name="prioridad" value="Sin asignar" readonly>
                        <span class="input-group-text">
                            <i class="bi bi-lock-fill text-muted" title="La prioridad se establece automáticamente"></i>
                        </span>
                    </div>
                    <div class="form-text text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        La prioridad se asignará posteriormente por el gerente del área
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="pais" class="form-label">País</label>
                    <select class="form-select select2" id="pais" name="pais" required>
                        <option value="">Seleccione país</option>
                        <option value="Argentina">Argentina</option>
                        <option value="Uruguay">Uruguay</option>
                        <option value="Argentina/Uruguay">Argentina/Uruguay</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="sector" class="form-label">Sector</label>
                    <select class="form-select select2" id="sector" name="sector" required>
                        <option value="">Seleccione sector</option>
                        <!-- Se cargará dinámicamente desde JS -->
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="funcionalidad" class="form-label">Funcionalidad</label>
                <div class="input-group">
                    <textarea class="form-control" id="funcionalidad" name="funcionalidad" rows="4" placeholder="Describa brevemente la funcionalidad o el objetivo del proyecto" required></textarea>
                </div>
                <div class="form-text text-muted">
                    <i class="bi bi-lightbulb me-1"></i>
                    Incluya el problema a resolver, funcionalidades principales y beneficios esperados
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="btnGuardarProyecto">
                <i class="bi bi-save me-2"></i>Guardar Proyecto
            </button>
        </form>
    </div>

    <!-- Modal de confirmación -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">Confirmar acción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea guardar este proyecto?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmarGuardar">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="ayudaModal" tabindex="-1" aria-labelledby="ayudaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="ayudaModalLabel">
                        <i class="bi bi-question-circle me-2"></i>Ayuda para Cargar Proyectos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            <h6 class="fw-bold text-primary">
                                <i class="bi bi-1-circle me-2"></i>Información General
                            </h6>
                            <ul class="list-unstyled ms-3 mb-4">
                                <li class="mb-2">
                                    <strong>ID Proyecto:</strong> Se genera automáticamente al guardar
                                </li>
                                <li class="mb-2">
                                    <strong>Tipo/Presentación:</strong> Seleccione la categoría que mejor describa su proyecto
                                </li>
                                <li class="mb-2">
                                    <strong>Nombre del Proyecto:</strong> Use un nombre descriptivo y claro
                                </li>
                            </ul>

                            <h6 class="fw-bold text-primary">
                                <i class="bi bi-2-circle me-2"></i>Configuración del Proyecto
                            </h6>
                            <ul class="list-unstyled ms-3 mb-4">
                                <li class="mb-2">
                                    <strong>Solicitante:</strong> Persona que solicita el proyecto (se carga desde el sistema de empleados)
                                </li>
                                <li class="mb-2">
                                    <strong>Prioridad:</strong> 
                                    <span class="badge bg-secondary">Sin asignar</span>
                                    Se establece automáticamente. El gerente del área lo establecerá posteriormente
                                </li>
                                <li class="mb-2">
                                    <strong>País:</strong> Región donde se implementará el proyecto
                                </li>
                                <li class="mb-2">
                                    <strong>Sector:</strong> Área o departamento beneficiario del proyecto
                                </li>
                            </ul>

                            <h6 class="fw-bold text-primary">
                                <i class="bi bi-3-circle me-2"></i>Descripción del Proyecto
                            </h6>
                            <ul class="list-unstyled ms-3 mb-4">
                                <li class="mb-2">
                                    <strong>Funcionalidad:</strong> Describa:
                                    <ul class="mt-2">
                                        <li>El problema o necesidad a resolver</li>
                                        <li>Las funcionalidades principales del proyecto</li>
                                        <li>Los beneficios esperados</li>
                                        <li>Cualquier requisito especial</li>
                                    </ul>
                                </li>
                            </ul>

                            <div class="alert alert-info">
                                <h6 class="fw-bold">
                                    <i class="bi bi-lightbulb me-2"></i>Consejos para una buena descripción:
                                </h6>
                                <ul class="mb-0">
                                    <li>Sea específico y claro en la descripción</li>
                                    <li>Mencione el impacto esperado en el negocio</li>
                                    <li>Incluya cualquier información técnica relevante</li>
                                    <li>Si es una mejora, explique la situación actual</li>
                                </ul>
                            </div>

                            <div class="alert alert-warning">
                                <h6 class="fw-bold">
                                    <i class="bi bi-exclamation-triangle me-2"></i>Importante:
                                </h6>
                                <ul class="mb-0">
                                    <li>Todos los campos marcados son obligatorios</li>
                                    <li>Una vez guardado, el proyecto entrará en estado "Pendiente"</li>
                                    <li>El líder de proyecto será asignado posteriormente</li>
                                    <li>Podrá realizar un seguimiento del proyecto en la pantalla principal</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Custom JS -->
    <script src="js/cargarProyecto.js"></script>
</body>
</html>