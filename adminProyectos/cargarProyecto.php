
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario de Proyecto</title>
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
            <h2 class="mb-4 text-primary">
                <i class="bi bi-clipboard-data me-2"></i>Formulario de Proyecto
            </h2>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="id" class="form-label">ID Proyecto</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="id" name="id" placeholder="Generado automáticamente" disabled>
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

            <div class="mb-3">
                <label for="solicitante" class="form-label">Solicitante</label>
                <select class="form-select select2" id="solicitante" name="solicitante" required>
                    <option value="">Seleccione solicitante</option>
                    <!-- Se cargará dinámicamente desde JS -->
                </select>
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
                        <option value="Abastecimiento">Abastecimiento</option>
                        <option value="Administración">Administración</option>
                        <option value="Casa central">Casa central</option>
                        <option value="Comercial">Comercial</option>
                        <option value="Control de stock">Control de stock</option>
                        <option value="Dirección">Dirección</option>
                        <option value="Ecommerce">Ecommerce</option>
                        <option value="Gerencia">Gerencia</option>
                        <option value="Locales propios">Locales propios</option>
                        <option value="Logística">Logística</option>
                        <option value="Proyectos">Proyectos</option>
                        <option value="Recursos humanos">Recursos humanos</option>
                        <option value="Sistemas">Sistemas</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="funcionalidad" class="form-label">Funcionalidad</label>
                <div class="input-group">
                    <textarea class="form-control" id="funcionalidad" name="funcionalidad" rows="4" placeholder="Ingrese una breve descripción" required></textarea>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Documentos adjuntos</label>
                <div id="documentosContainer">
                    <!-- Aquí se mostrarán los documentos adjuntos -->
                </div>
                <div class="input-group mb-3">
                    <input type="file" class="form-control" id="documentoInput" accept=".pdf,.doc,.docx,.xls,.xlsx">
                    <button class="btn btn-primary" type="button" id="btnAgregarDocumento">
                        <i class="bi bi-plus-lg"></i> Agregar
                    </button>
                </div>
                <div class="form-text">
                    Formatos permitidos: PDF, Word, Excel. Máximo 5 MB por archivo.
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