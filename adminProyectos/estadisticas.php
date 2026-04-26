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

    <style>
        /* Override chart container height for all charts */
        .chart-container {
            position: relative;
            height: 300px;
            max-height: 300px;
        }

        /* KPI cards */
        .kpi-card { background: #1e293b; color: #f1f5f9; }
        .kpi-value { font-size: 2.8rem; font-weight: 700; line-height: 1; letter-spacing: -0.02em; }
        .kpi-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            opacity: 0.7;
            margin-bottom: 0.4rem;
        }
        .kpi-info-btn {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: none;
            border: none;
            color: inherit;
            opacity: 0.5;
            cursor: pointer;
            padding: 0;
            font-size: 1rem;
            line-height: 1;
        }
        .kpi-info-btn:hover { opacity: 1; }
        .kpi-sub { font-size: 0.78rem; opacity: 0.55; margin-top: 0.35rem; min-height: 1em; }

        /* Year toggle buttons */
        .year-filter-btn { min-width: 130px; }

        /* Distribution legend - single column list (sits to the right of the donut) */
        .legend-grid {
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow-y: auto;
            max-height: 300px;
        }
        .legend-grid .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
            background: none;
            padding: 0;
            border-radius: 0;
        }
        .legend-grid .legend-color {
            width: 11px;
            height: 11px;
            border-radius: 2px;
            flex-shrink: 0;
        }
        .legend-grid .legend-text {
            font-size: 0.78rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }
        .legend-grid .legend-count {
            font-size: 0.78rem;
            font-weight: 700;
            flex-shrink: 0;
            color: #212529;
            background: none;
            padding: 0;
            border-radius: 0;
            margin-left: 0;
        }
    </style>
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
                        <a class="nav-link" href="index.php">Proyectos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="estadisticas.php">Estadísticas</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">

        <!-- Page title -->
        <div class="row mb-2">
            <div class="col-12">
                <h5 class="text-secondary mb-0"><i class="fas fa-chart-pie me-2"></i>Dashboard de Estadísticas</h5>
            </div>
        </div>

        <!-- 1. Year quick filter -->
        <div class="row mb-3">
            <div class="col-12 d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted small fw-semibold me-1">Período:</span>
                <button id="btnAnoActual" class="btn btn-primary btn-sm year-filter-btn" data-year="current">
                    <i class="fas fa-calendar-check me-1"></i>Año Actual
                </button>
                <button id="btnAnoAnterior" class="btn btn-outline-secondary btn-sm year-filter-btn" data-year="previous">
                    <i class="fas fa-calendar me-1"></i>Año Anterior
                </button>
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

        <!-- Alert container -->
        <div id="alertContainer"></div>

        <!-- 2 + 3. Unified KPI row: 4 summary cards + 2 KPI cards -->
        <div class="row mb-4">

            <!-- Card 1: Total Proyectos -->
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card text-center bg-primary text-white h-100 position-relative">
                    <button class="kpi-info-btn"
                        data-bs-toggle="popover"
                        data-bs-placement="top"
                        data-bs-trigger="hover focus"
                        title="Total Proyectos"
                        data-bs-content="Total de proyectos cuya fecha de creación corresponde al año seleccionado, considerando los filtros activos.">
                        <i class="fas fa-circle-info fa-lg"></i>
                    </button>
                    <div class="card-body">
                        <h2 class="card-title" id="totalProyectos">0</h2>
                        <p class="card-text">Total Proyectos</p>
                        <i class="fas fa-project-diagram fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- Card 2: Terminados -->
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card text-center bg-success text-white h-100 position-relative">
                    <button class="kpi-info-btn"
                        data-bs-toggle="popover"
                        data-bs-placement="top"
                        data-bs-trigger="hover focus"
                        title="Terminados"
                        data-bs-content="Proyectos con estado 'Terminado' dentro del año seleccionado.">
                        <i class="fas fa-circle-info fa-lg"></i>
                    </button>
                    <div class="card-body">
                        <h2 class="card-title" id="proyectosTerminados">0</h2>
                        <p class="card-text">Terminados</p>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- Card 3: En Proceso -->
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card text-center bg-warning text-dark h-100 position-relative">
                    <button class="kpi-info-btn"
                        data-bs-toggle="popover"
                        data-bs-placement="top"
                        data-bs-trigger="hover focus"
                        title="En Proceso"
                        data-bs-content="Proyectos con estado 'En Proceso' dentro del año seleccionado.">
                        <i class="fas fa-circle-info fa-lg" style="color:rgba(0,0,0,0.5)"></i>
                    </button>
                    <div class="card-body">
                        <h2 class="card-title" id="proyectosProceso">0</h2>
                        <p class="card-text">En Proceso</p>
                        <i class="fas fa-cog fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- Card 4: Pendientes -->
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card text-center bg-info text-white h-100 position-relative">
                    <button class="kpi-info-btn"
                        data-bs-toggle="popover"
                        data-bs-placement="top"
                        data-bs-trigger="hover focus"
                        title="Pendientes"
                        data-bs-content="Proyectos con estado 'Pendiente' dentro del año seleccionado.">
                        <i class="fas fa-circle-info fa-lg"></i>
                    </button>
                    <div class="card-body">
                        <h2 class="card-title" id="proyectosPendientes">0</h2>
                        <p class="card-text">Pendientes</p>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- Card 5: Completados vs Plan % -->
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card kpi-card h-100 position-relative">
                    <div class="card-body p-3">
                        <button class="kpi-info-btn"
                            data-bs-toggle="popover"
                            data-bs-placement="top"
                            data-bs-trigger="hover focus"
                            title="¿Cómo se calcula?"
                            data-bs-content="Porcentaje de proyectos con fecha límite en el año seleccionado que fueron formalmente cerrados (Estado = Terminado con Finalización Real registrada).">
                            <i class="fas fa-circle-info fa-lg"></i>
                        </button>
                        <p class="kpi-label">Completados vs Plan</p>
                        <div class="d-flex align-items-baseline gap-1 mb-2">
                            <span class="kpi-value" id="kpiPctCompletados">—</span>
                            <span class="fs-5 opacity-50">%</span>
                        </div>
                        <div class="progress mb-1" style="height:5px;background:rgba(255,255,255,0.15)">
                            <div class="progress-bar bg-info" id="kpiBarCompletados" role="progressbar" style="width:0%;transition:width .8s ease"></div>
                        </div>
                        <p class="kpi-sub" id="kpiTextCompletados">&nbsp;</p>
                    </div>
                </div>
            </div>

            <!-- Card 6: % Cumplimiento SLA -->
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card kpi-card h-100 position-relative">
                    <div class="card-body p-3">
                        <button class="kpi-info-btn"
                            data-bs-toggle="popover"
                            data-bs-placement="top"
                            data-bs-trigger="hover focus"
                            title="¿Cómo se calcula?"
                            data-bs-content="Porcentaje de proyectos terminados cuya Finalización Real fue igual o anterior a la Fecha Límite acordada. Objetivo: 85%.">
                            <i class="fas fa-circle-info fa-lg"></i>
                        </button>
                        <p class="kpi-label">% Cumplimiento SLA</p>
                        <div class="d-flex align-items-baseline gap-1 mb-2">
                            <span class="kpi-value" id="kpiPctSLA">—</span>
                            <span class="fs-5 opacity-50">%</span>
                        </div>
                        <div class="progress mb-1" style="height:5px;background:rgba(255,255,255,0.15)">
                            <div class="progress-bar" id="kpiBarSLA" role="progressbar" style="width:0%;transition:width .8s ease"></div>
                        </div>
                        <p class="kpi-sub" id="kpiTextSLA">&nbsp;</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- 4. Completados vs Plan chart (full width) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Completados vs Plan por Mes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartCompletadosVsPlan"></canvas>
                        </div>
                        <div class="text-center mt-2">
                            <small class="text-muted">Barras: planificados (azul) y completados (verde) · Línea naranja: acumulado completados · Hover para ver SLA mensual</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Distribution + Finalizados por Año (side by side) -->
        <div class="row mb-4">
            <!-- Unified Distribution chart -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>Distribución
                        </h5>
                        <div class="btn-group btn-group-sm" id="toggleDistribucion" role="group">
                            <button type="button" class="btn btn-light active" data-vista="estado">Por Estado</button>
                            <button type="button" class="btn btn-outline-light" data-vista="sector">Por Sector</button>
                        </div>
                    </div>
                    <div class="card-body d-flex gap-3 align-items-center">
                        <div style="flex:0 0 55%;min-width:0">
                            <div class="chart-container">
                                <canvas id="chartDistribucion"></canvas>
                            </div>
                        </div>
                        <div id="leyendaDistribucion" class="legend-grid" style="flex:1;min-width:0;align-self:center"></div>
                    </div>
                </div>
            </div>

            <!-- Finalizados por Año -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
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
                        <div class="text-center mt-2">
                            <small class="text-muted">* Solo se muestran proyectos con fecha de finalización real</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /container-fluid -->

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JS -->
    <script src="js/estadisticas.js"></script>
</body>
</html>
