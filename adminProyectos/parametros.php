<?php
/**
 * Parámetros › Personas
 * Listado único de personas para los combos Solicitante, Líder de Proyecto y
 * Desarrollador. Solo para quien tiene el permiso del hub
 * PermisosProyectos::CLAVE_PARAMETROS (validado acá y en api/parametros.php).
 */
session_start();

require_once 'class/permisos.php';

$haySesion = PermisosProyectos::username() !== null;
$tienePermiso = $haySesion && PermisosProyectos::puedeAdministrarParametros();

if (!$tienePermiso) {
    http_response_code($haySesion ? 403 : 401);
}

$navParametrosActivo = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parámetros</title>
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
    <link href="css/parametros.css" rel="stylesheet">
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
                        <a class="nav-link" href="programas.php">Programas</a>
                    </li>
<?php include __DIR__ . '/includes/navParametros.php'; ?>
                </ul>
            </div>
        </div>
    </nav>

<?php if (!$tienePermiso): ?>
    <div class="container mt-5">
        <div class="alert alert-warning d-flex align-items-start" role="alert">
            <i class="fas fa-lock fa-2x me-3"></i>
            <div>
                <h4 class="alert-heading">Acceso restringido</h4>
                <?php if (!$haySesion): ?>
                    <p class="mb-0">Tenés que iniciar sesión para entrar a Parámetros.</p>
                <?php else: ?>
                    <p>No tenés permiso para administrar los parámetros de Gestión de Proyectos.</p>
                    <p class="mb-0">Si lo necesitás, pedile al administrador del hub de usuarios que le asigne a tu rol el permiso <strong>Parámetros</strong> (Gestión de Proyectos).</p>
                <?php endif; ?>
            </div>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a Proyectos
        </a>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php else: ?>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Parámetros</h1>
            <button type="button" class="btn btn-primary" id="btnNuevaPersona">
                <i class="fas fa-plus me-1"></i> Nueva persona
            </button>
        </div>

        <div id="alertContainer"></div>

        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Personas</h5>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="filtroMostrarInactivos">
                    <label class="form-check-label" for="filtroMostrarInactivos">Mostrar inactivos</label>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Cada switch define en qué combo aparece la persona: <strong>Solicitante</strong> (Cargar y Editar Proyecto),
                    <strong>Líder</strong> y <strong>Desarrollador</strong> (Editar Proyecto). Los cambios de los switches se guardan al tocarlos.
                    Solicitantes y Líderes necesitan mail porque reciben avisos automáticos.
                </p>
                <div class="table-responsive">
                    <table id="tablaPersonas" class="table table-striped table-bordered w-100">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Mail</th>
                                <th class="text-center">Solicitante</th>
                                <th class="text-center">Líder</th>
                                <th class="text-center">Desarrollador</th>
                                <th class="text-center">Activo</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Los datos se cargan dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de alta/edición de persona -->
    <div class="modal fade" id="modalPersona" tabindex="-1" aria-labelledby="modalPersonaTitulo" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalPersonaTitulo">Nueva persona</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="modalPersonaError" class="alert alert-danger d-none"></div>
                    <form id="formPersona" novalidate>
                        <input type="hidden" id="personaId" name="id">
                        <input type="hidden" id="personaActivo" value="1">
                        <input type="hidden" id="personaNombreOriginal" value="">
                        <div class="mb-3">
                            <label for="personaNombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="personaNombre" name="Nombre" maxlength="255" required>
                            <div class="form-text d-none" id="avisoRenombre">
                                <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                Al cambiar el nombre también se actualiza en los proyectos donde figura (te vamos a pedir confirmación).
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="personaMail" class="form-label">Mail</label>
                            <input type="email" class="form-control" id="personaMail" name="Mail" maxlength="200">
                            <div class="form-text">Obligatorio si es Solicitante o Líder (reciben avisos automáticos). Opcional si solo es Desarrollador.</div>
                        </div>
                        <div class="mb-1 fw-semibold">Aparece en los combos</div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="personaEsSolicitante">
                            <label class="form-check-label" for="personaEsSolicitante">Solicitante</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="personaEsLider">
                            <label class="form-check-label" for="personaEsLider">Líder de Proyecto</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="personaEsDesarrollador">
                            <label class="form-check-label" for="personaEsDesarrollador">Desarrollador</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarPersona">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- Custom JS (se reutiliza mostrarAlerta() y escapeHtml() de proyectos.js, igual que Programas) -->
    <script src="js/proyectos.js"></script>
    <script src="js/parametros.js"></script>

    <script>
        $(document).ready(function() {
            ParametrosManager.init();
        });
    </script>
<?php endif; ?>
</body>
</html>
