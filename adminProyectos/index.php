
<?php
// Activar visualización de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de Proyectos</title>
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
                        <a class="nav-link active" href="index.php">Proyectos</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Seguimiento de Proyectos</h1>
            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#leyendaModal">
                <i class="fas fa-info-circle me-1"></i> Leyenda
            </button>
        </div>
        
        <div id="alertContainer"></div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Filtros</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="filtroSector" class="form-label">Sector</label>
                        <select class="form-select" id="filtroSector">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="filtroEstado" class="form-label">Estado</label>
                        <select class="form-select" id="filtroEstado">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="filtroEtapa" class="form-label">Etapa</label>
                        <select class="form-select" id="filtroEtapa">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="filtroLider" class="form-label">Líder de Proyecto</label>
                        <select class="form-select" id="filtroLider">
                            <option value="">Todos</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 text-end">
                        <button id="btnResetFiltros" class="btn btn-secondary me-2">Restablecer</button>
                        <button id="btnAplicarFiltros" class="btn btn-primary">Aplicar Filtros</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de Proyectos -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Lista de Proyectos</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaProyectos" class="table table-striped table-bordered w-100">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha Creación</th>
                                <th>Desarrollo</th>
                                <th>Tipo Presentación</th>
                                <th>Solicitante</th>
                                <th>Sector</th>
                                <th>Estado</th>
                                <th>Etapa</th>
                                <th>Líder Proyecto</th>
                                <th>Prioridad</th>
                                <th>Avance</th>
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

    <!-- Modal de Leyenda -->
    <div class="modal fade" id="leyendaModal" tabindex="-1" aria-labelledby="leyendaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="leyendaModalLabel">Leyenda de Estados y Etapas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-bold">Estados:</h6>
                    <div class="mb-3 d-flex flex-wrap gap-2">
                        <span class="badge estado-pendiente">Pendiente</span>
                        <span class="badge estado-proceso">En Proceso</span>
                        <span class="badge estado-pausado">Pausado</span>
                        <span class="badge estado-terminado">Terminado</span>
                        <span class="badge estado-cancelado">Cancelado</span>
                    </div>
                    
                    <h6 class="fw-bold mt-4">Etapas y porcentaje de avance:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Pendiente
                                    <div class="circular-progress" style="--percentage: 0%; --color: #6c757d;">
                                        <span>0%</span>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Asignado
                                    <div class="circular-progress" style="--percentage: 1%; --color: #6c757d;">
                                        <span>1%</span>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Diagnóstico
                                    <div class="circular-progress" style="--percentage: 15%; --color: #ff9800;">
                                        <span>15%</span>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Diseño
                                    <div class="circular-progress" style="--percentage: 30%; --color: #2196f3;">
                                        <span>30%</span>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Desarrollo
                                    <div class="circular-progress" style="--percentage: 45%; --color: #2196f3;">
                                        <span>45%</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Prueba y evaluación
                                    <div class="circular-progress" style="--percentage: 60%; --color: #00bcd4;">
                                        <span>60%</span>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Modificaciones/correcciones
                                    <div class="circular-progress" style="--percentage: 75%; --color: #00bcd4;">
                                        <span>75%</span>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Implementación
                                    <div class="circular-progress" style="--percentage: 85%; --color: #4caf50;">
                                        <span>85%</span>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Finalizado
                                    <div class="circular-progress" style="--percentage: 100%; --color: #4caf50;">
                                        <span>100%</span>
                                    </div>
                                </li>
                            </ul>
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
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    
    <!-- Custom JS -->
    <script src="js/proyectos.js"></script>
    
    <script>
        $(document).ready(function() {
            // Inicializar gestor de proyectos
            ProyectosManager.init();
            
            // Mostrar mensaje si hay algún error en la URL
            const urlParams = new URLSearchParams(window.location.search);
            const errorMsg = urlParams.get('error');
            if (errorMsg) {
                mostrarAlerta(decodeURIComponent(errorMsg), 'error');
            }
        });
    </script>
</body>
</html>