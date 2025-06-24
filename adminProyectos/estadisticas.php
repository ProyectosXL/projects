<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Proyectos</title>
    <link rel="icon" type="image/png" href="../images/logo-xl-project.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="css/styles.css" rel="stylesheet">
    <link href="css/estadisticas.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-chart-bar me-2"></i>
                Estadísticas de Proyectos
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-arrow-left me-1"></i> Volver a Proyectos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="estadisticas.php">Estadísticas</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="fas fa-chart-pie me-2"></i>Dashboard de Estadísticas</h1>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="filtroSector" class="form-label">Sector</label>
                        <select class="form-select" id="filtroSector">
                            <option value="">Todos los sectores</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="filtroLider" class="form-label">Líder de Proyecto</label>
                        <select class="form-select" id="filtroLider">
                            <option value="">Todos los líderes</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="filtroEstado" class="form-label">Estado</label>
                        <select class="form-select" id="filtroEstado">
                            <option value="">Todos los estados</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <button id="btnAplicarFiltros" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i> Aplicar
                        </button>
                        <button id="btnResetFiltros" class="btn btn-secondary">
                            <i class="fas fa-undo me-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenedor de alertas -->
        <div id="alertContainer"></div>

        <!-- Resumen estadístico - MOVIDO AQUÍ -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center bg-primary text-white">
                    <div class="card-body">
                        <h2 class="card-title" id="totalProyectos">0</h2>
                        <p class="card-text">Total Proyectos</p>
                        <i class="fas fa-project-diagram fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <h2 class="card-title" id="proyectosTerminados">0</h2>
                        <p class="card-text">Terminados</p>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center bg-warning text-dark">
                    <div class="card-body">
                        <h2 class="card-title" id="proyectosProceso">0</h2>
                        <p class="card-text">En Proceso</p>
                        <i class="fas fa-cog fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <h2 class="card-title" id="proyectosPendientes">0</h2>
                        <p class="card-text">Pendientes</p>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Primera fila de gráficos -->
        <div class="row mb-4">
            <!-- Gráfico de Estados -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-tasks me-2"></i>
                            Distribución por Estado
                            <small class="text-light">(Sin filtros)</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartEstados"></canvas>
                        </div>
                        <div class="chart-legend mt-3" id="leyendaEstados">
                            <!-- La leyenda se generará dinámicamente -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Sectores -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-building me-2"></i>
                            Distribución por Sector
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartSectores"></canvas>
                        </div>
                        <div class="chart-legend mt-3" id="leyendaSectores">
                            <!-- La leyenda se generará dinámicamente -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Segunda fila: Gráfico de barras -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Proyectos Finalizados por Año
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartFinalizados"></canvas>
                        </div>
                        <div class="text-center mt-3">
                            <small class="text-muted">* Solo se muestran proyectos con fecha de finalización real</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="js/estadisticas.js"></script>
</body>
</html>