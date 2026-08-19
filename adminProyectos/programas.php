
<?php
// Activar visualización de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programas</title>
    <link rel="icon" type="image/png" href="../images/logo-xl-project.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
    <link href="css/datatables.css" rel="stylesheet">
    <link href="css/programas.css" rel="stylesheet">
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
                        <a class="nav-link" href="estadisticas.php">Estadísticas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="programas.php">Programas</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Programas</h1>
            <?php if (isset($_SESSION['username'])): ?>
                <button type="button" class="btn btn-primary" id="btnNuevoPrograma">
                    <i class="fas fa-plus me-1"></i> Nuevo programa
                </button>
            <?php endif; ?>
        </div>

        <div id="alertContainer"></div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Lista de Programas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaProgramas" class="table table-striped table-bordered w-100">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Responsable</th>
                                <th>Proyectos</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Los datos se cargarán dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de alta/edición de programa -->
    <?php if (isset($_SESSION['username'])): ?>
    <div class="modal fade" id="modalPrograma" tabindex="-1" aria-labelledby="modalProgramaTitulo" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalProgramaTitulo">Nuevo programa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modalProgramaError" class="alert alert-danger d-none"></div>
                    <form id="formPrograma">
                        <input type="hidden" id="programaId" name="id">
                        <div class="mb-3">
                            <label for="programaNombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="programaNombre" name="Nombre" maxlength="150" required>
                        </div>
                        <div class="mb-3">
                            <label for="programaDescripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="programaDescripcion" name="Descripcion" rows="3" maxlength="500"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="programaResponsable" class="form-label">Responsable</label>
                            <input type="text" class="form-control" id="programaResponsable" name="Responsable" maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label for="programaColor" class="form-label">Color</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color" id="programaColor" name="Color" value="#0d6efd" data-sin-color="0">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSinColor">Sin color</button>
                                <span class="badge" id="previewColorPrograma">Vista previa</span>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarPrograma">Guardar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- Custom JS (se reutiliza mostrarAlerta() y escapeHtml() de proyectos.js) -->
    <script src="js/proyectos.js"></script>
    <script src="js/programas.js"></script>

    <script>
        $(document).ready(function() {
            ProgramasManager.init();
        });
    </script>
</body>
</html>
