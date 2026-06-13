
// ─── Global chart instances ───────────────────────────────────────────────────
let chartDistribucion    = null;
let chartFinalizados     = null;
let chartCompletadosVsPlan = null;

const MESES      = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
const MESES_FULL = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function colorChipDesarrollador(nombre) {
    if (!nombre) return { bg: '#e9ecef', color: '#6c757d' };
    let hash = 0;
    for (let i = 0; i < nombre.length; i++) {
        hash = nombre.charCodeAt(i) + ((hash << 5) - hash);
        hash |= 0;
    }
    const hue = Math.abs(hash) % 360;
    return { bg: `hsl(${hue},55%,88%)`, color: `hsl(${hue},55%,28%)` };
}

const coloresEstados = {
    'Pendiente':  '#ffc107',
    'Proceso':    '#17a2b8',
    'En Proceso': '#17a2b8',
    'Terminado':  '#28a745',
    'Pausado':    '#fd7e14',
    'Cancelado':  '#dc3545',
    'Sin Estado': '#6c757d'
};

const coloresSectores = [
    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
    '#9966FF', '#FF9F40', '#C9CBCF', '#7BC8A4',
    '#E8C99A', '#A8D8EA', '#AA96DA', '#FCBAD3'
];

// ─── Main manager ─────────────────────────────────────────────────────────────
const EstadisticasManager = {

    anoSeleccionado:      new Date().getFullYear(),
    mesSeleccionado:      0,
    kpiDetalleTipoActivo: null,
    vistaDistribucion:    'estado',
    datosEstados:         { data: [], total: 0 },
    datosSectores:        { data: [], total: 0 },

    // ── Init ──────────────────────────────────────────────────────────────────
    init: function() {
        this.cargarFiltros();
        this.setupYearFilters();
        this.actualizarPeriodoBadge();
        try {
            this.setupEventListeners();
        } catch(e) {
            console.error('setupEventListeners error:', e);
        }
        this.cargarTodasLasEstadisticas();
    },

    // ── Year quick filters ────────────────────────────────────────────────────
    setupYearFilters: function() {
        document.querySelectorAll('[data-year]').forEach(btn => {
            btn.addEventListener('click', () => {
                const now = new Date().getFullYear();
                this.anoSeleccionado = btn.dataset.year === 'current' ? now : now - 1;

                document.querySelectorAll('[data-year]').forEach(b => {
                    b.classList.replace('btn-primary', 'btn-outline-secondary');
                });
                btn.classList.replace('btn-outline-secondary', 'btn-primary');

                this.actualizarPeriodoBadge();
                this.cargarTodasLasEstadisticas();
            });
        });
    },

    // ── Event listeners ───────────────────────────────────────────────────────
    setupEventListeners: function() {
        $('#btnAplicarFiltros').on('click', () => this.cargarTodasLasEstadisticas());

        $('#btnResetFiltros').on('click', () => {
            $('#filtroSector, #filtroLider, #filtroEstado').val('');
            $('#filtroMes').val('');
            this.mesSeleccionado = 0;
            this.actualizarPeriodoBadge();
            this.cargarTodasLasEstadisticas();
        });

        $('#filtroMes').on('change', () => {
            this.mesSeleccionado = parseInt($('#filtroMes').val()) || 0;
            this.actualizarPeriodoBadge();
            this.cargarKPIResumen();
            const modalEl = document.getElementById('modalKpiDetalle');
            if (modalEl && modalEl.classList.contains('show') && this.kpiDetalleTipoActivo) {
                this.cargarKPIDetalle(this.kpiDetalleTipoActivo);
            }
        });

        $(document).on('click', '.btn-ver-proyectos', (e) => {
            const tipo = e.currentTarget.dataset.tipo;
            this.kpiDetalleTipoActivo = tipo;
            this.cargarKPIDetalle(tipo);
        });

        // Distribution toggle
        document.querySelectorAll('#toggleDistribucion button').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#toggleDistribucion button').forEach(b => {
                    b.classList.remove('active', 'btn-light');
                    b.classList.add('btn-outline-light');
                });
                btn.classList.remove('btn-outline-light');
                btn.classList.add('active', 'btn-light');

                this.vistaDistribucion = btn.dataset.vista;
                if (this.vistaDistribucion === 'estado') {
                    this.crearGraficoDistribucion(this.datosEstados.data, this.datosEstados.total, 'estado');
                } else {
                    this.crearGraficoDistribucion(this.datosSectores.data, this.datosSectores.total, 'sector');
                }
            });
        });

        // Bootstrap popovers for KPI info buttons
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
            try {
                new bootstrap.Popover(el, { html: false, container: 'body' });
            } catch(e) {
                console.warn('Popover init failed for element:', el, e);
            }
        });
    },

    // ── Filter selects ────────────────────────────────────────────────────────
    cargarFiltros: function() {
        fetch('api/estadisticas.php?action=filtros')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.poblarSelect('filtroSector', data.sectores || []);
                    this.poblarSelect('filtroLider',  data.lideres  || []);
                    this.poblarSelect('filtroEstado', data.estados  || []);
                } else {
                    this.mostrarError('Error al cargar filtros: ' + (data.error || ''));
                }
            })
            .catch(() => this.mostrarError('Error al cargar filtros'));
    },

    poblarSelect: function(selectId, opciones) {
        const select = document.getElementById(selectId);
        const def = select.options[0];
        select.innerHTML = '';
        select.appendChild(def);
        opciones.forEach(o => {
            if (o) {
                const opt = document.createElement('option');
                opt.value = opt.textContent = o;
                select.appendChild(opt);
            }
        });
    },

    // ── Load all ──────────────────────────────────────────────────────────────
    cargarTodasLasEstadisticas: function() {
        const sector = $('#filtroSector').val() || '';
        const lider  = $('#filtroLider').val()  || '';
        const estado = $('#filtroEstado').val() || '';
        const year   = this.anoSeleccionado;

        $('.card').addClass('loading');

        this.cargarEstadisticasEstados(sector, lider, year);
        this.cargarEstadisticasSectores(lider, estado, year);
        this.cargarProyectosFinalizados(sector, lider, estado);   // historical – no year filter
        this.cargarCompletadosVsPlan(sector, lider, year);
        this.cargarKPIResumen();
    },

    // ── Loaders ───────────────────────────────────────────────────────────────
    cargarEstadisticasEstados: function(sector, lider, year) {
        const params = new URLSearchParams({ action: 'estados', sector, lider, year });
        fetch(`api/estadisticas.php?${params}`)
            .then(r => r.json())
            .then(data => {
                $('.card').removeClass('loading');
                if (!data.success) { this.mostrarError('Error estados: ' + (data.error || '')); return; }
                this.datosEstados = { data: data.data, total: data.total };
                this.actualizarResumenEstados(data.data);
                if (this.vistaDistribucion === 'estado') {
                    this.crearGraficoDistribucion(data.data, data.total, 'estado');
                }
            })
            .catch(() => { $('.card').removeClass('loading'); this.mostrarError('Error al cargar estados'); });
    },

    cargarEstadisticasSectores: function(lider, estado, year) {
        const params = new URLSearchParams({ action: 'sectores', lider, estado, year });
        fetch(`api/estadisticas.php?${params}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) { this.mostrarError('Error sectores: ' + (data.error || '')); return; }
                this.datosSectores = { data: data.data, total: data.total };
                if (this.vistaDistribucion === 'sector') {
                    this.crearGraficoDistribucion(data.data, data.total, 'sector');
                }
            })
            .catch(() => this.mostrarError('Error al cargar sectores'));
    },

    cargarProyectosFinalizados: function(sector, lider, estado) {
        // Historical view — intentionally no year filter
        const params = new URLSearchParams({ action: 'finalizados-por-año', sector, lider, estado });
        fetch(`api/estadisticas.php?${params}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) { this.mostrarError('Error finalizados: ' + (data.error || '')); return; }
                this.crearGraficoFinalizados(data.data, data.usaFechaFinalizacion, data.estadoFiltrado);
            })
            .catch(() => this.mostrarError('Error al cargar finalizados'));
    },

    cargarCompletadosVsPlan: function(sector, lider, year) {
        const params = new URLSearchParams({ action: 'completados-vs-plan', sector, lider, year });
        fetch(`api/estadisticas.php?${params}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) { this.mostrarError('Error completados vs plan: ' + (data.error || '')); return; }
                this.crearGraficoCompletadosVsPlan(data.data);
            })
            .catch(() => this.mostrarError('Error al cargar completados vs plan'));
    },

    cargarKPIResumen: function() {
        const sector = $('#filtroSector').val() || '';
        const lider  = $('#filtroLider').val()  || '';
        const year   = this.anoSeleccionado;
        const params = new URLSearchParams({ action: 'kpi-resumen', sector, lider, year, month: this.mesSeleccionado });
        fetch(`api/estadisticas.php?${params}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) { this.mostrarError('Error KPI: ' + (data.error || '')); return; }
                this.actualizarKPICards(data);
            })
            .catch(() => this.mostrarError('Error al cargar KPIs'));
    },

    // ── Summary cards update ──────────────────────────────────────────────────
    actualizarResumenEstados: function(datos) {
        let total = 0, terminados = 0, proceso = 0, pendientes = 0;
        datos.forEach(item => {
            total += item.cantidad;
            const e = (item.estado || '').toLowerCase();
            if (e === 'terminado')               terminados = item.cantidad;
            else if (e === 'proceso' || e === 'en proceso') proceso = item.cantidad;
            else if (e === 'pendiente')          pendientes = item.cantidad;
        });
        document.getElementById('totalProyectos').textContent       = total;
        document.getElementById('proyectosTerminados').textContent  = terminados;
        document.getElementById('proyectosProceso').textContent     = proceso;
        document.getElementById('proyectosPendientes').textContent  = pendientes;
    },

    // ── KPI cards update ──────────────────────────────────────────────────────
    actualizarKPICards: function(data) {
        const pctComp = data.pct_completados;
        const pctSLA  = data.pct_sla;

        // KPI 1 — Completados vs Plan
        document.getElementById('kpiPctCompletados').textContent =
            pctComp !== null ? pctComp.toFixed(1) : '—';
        document.getElementById('kpiBarCompletados').style.width = (pctComp || 0) + '%';
        document.getElementById('kpiTextCompletados').textContent =
            data.total_plan > 0
                ? `${data.completados} de ${data.total_plan} proyectos cerrados`
                : 'Sin proyectos con fecha límite en el período';

        // KPI 2 — SLA
        document.getElementById('kpiPctSLA').textContent =
            pctSLA !== null ? pctSLA.toFixed(1) : '—';
        const barSLA = document.getElementById('kpiBarSLA');
        barSLA.style.width = (pctSLA || 0) + '%';
        barSLA.className = 'progress-bar ' + (
            pctSLA === null ? 'bg-secondary' :
            pctSLA >= 85    ? 'bg-success'   :
            pctSLA >= 60    ? 'bg-warning'   : 'bg-danger'
        );
        document.getElementById('kpiTextSLA').textContent =
            data.completados > 0
                ? `${data.a_tiempo} de ${data.completados} cerrados a tiempo · objetivo 85%`
                : 'Sin proyectos terminados en el período';
    },

    // ── Charts ────────────────────────────────────────────────────────────────
    crearGraficoDistribucion: function(datos, total, tipo) {
        const ctx = document.getElementById('chartDistribucion').getContext('2d');
        if (chartDistribucion) chartDistribucion.destroy();

        const labels = datos.map(d => tipo === 'estado' ? d.estado : d.sector);
        const values = datos.map(d => d.cantidad);
        const colors = tipo === 'estado'
            ? datos.map(d => coloresEstados[d.estado] || '#6c757d')
            : datos.map((_, i) => coloresSectores[i % coloresSectores.length]);

        chartDistribucion = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderColor: colors.map(c => c + '80'),
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                                return `${ctx.label}: ${ctx.raw} (${pct}%)`;
                            }
                        }
                    }
                },
                animation: { animateScale: true, duration: 800 }
            }
        });

        this.crearLeyenda('leyendaDistribucion', datos, colors, tipo);
    },

    crearLeyenda: function(containerId, datos, colors, tipo) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '';
        datos.forEach((item, i) => {
            const el = document.createElement('div');
            el.className = 'legend-item';
            el.innerHTML = `
                <div class="legend-color" style="background:${colors[i]}"></div>
                <span class="legend-text">${tipo === 'sector' ? item.sector : item.estado}</span>
                <span class="legend-count">${item.cantidad}</span>`;
            container.appendChild(el);
        });
    },

    crearGraficoFinalizados: function(datos, usaFechaFinalizacion, estadoFiltrado) {
        const ctx = document.getElementById('chartFinalizados').getContext('2d');
        if (chartFinalizados) chartFinalizados.destroy();

        const labels = datos.map(d => d.año.toString());
        const values = datos.map(d => d.cantidad);
        let titulo, color, nota;

        if (estadoFiltrado && estadoFiltrado !== 'Terminado') {
            titulo = `Proyectos "${estadoFiltrado}" por Año`;
            color  = coloresEstados[estadoFiltrado] || 'rgba(108,117,125,0.8)';
            nota   = `Proyectos "${estadoFiltrado}" por año de creación`;
        } else {
            titulo = 'Proyectos Finalizados por Año';
            color  = 'rgba(40,167,69,0.8)';
            nota   = 'Solo proyectos con fecha de finalización real';
        }

        chartFinalizados = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: titulo,
                    data: values,
                    backgroundColor: color,
                    borderColor: color.replace('0.8', '1'),
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: ctx => `Año ${ctx[0].label}`,
                            label: ctx => estadoFiltrado && estadoFiltrado !== 'Terminado'
                                ? `Proyectos ${estadoFiltrado}: ${ctx.raw}`
                                : `Proyectos finalizados: ${ctx.raw}`
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.08)' } },
                    x: { grid: { display: false } }
                },
                animation: { duration: 1000, easing: 'easeOutQuart' }
            }
        });

        const hdr = document.querySelector('#chartFinalizados').closest('.card').querySelector('.card-header h5');
        if (hdr) hdr.innerHTML = `<i class="fas fa-chart-bar me-2"></i>${titulo}`;
        const ftr = document.querySelector('#chartFinalizados').closest('.card').querySelector('.text-center small');
        if (ftr) ftr.textContent = '* ' + nota;
    },

    crearGraficoCompletadosVsPlan: function(datos) {
        const ctx = document.getElementById('chartCompletadosVsPlan').getContext('2d');
        if (chartCompletadosVsPlan) chartCompletadosVsPlan.destroy();

        chartCompletadosVsPlan = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: MESES,
                datasets: [
                    {
                        label: 'Planificados (Fecha Límite)',
                        data: datos.map(d => d.planificados),
                        backgroundColor: 'rgba(54,162,235,0.7)',
                        borderColor:     'rgba(54,162,235,1)',
                        borderWidth: 2,
                        borderRadius: 4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Completados (Terminado)',
                        data: datos.map(d => d.completados),
                        backgroundColor: 'rgba(75,192,100,0.7)',
                        borderColor:     'rgba(75,192,100,1)',
                        borderWidth: 2,
                        borderRadius: 4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Acumulado completados',
                        data: datos.map(d => d.acumulado),
                        type: 'line',
                        borderColor:     'rgba(255,159,64,1)',
                        backgroundColor: 'rgba(255,159,64,0.08)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
                        tension: 0.3,
                        yAxisID: 'y2'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const d = datos[context.dataIndex];
                                if (context.datasetIndex === 0) return `Planificados ese mes: ${d.planificados}`;
                                if (context.datasetIndex === 1) return `Completados ese mes: ${d.completados}`;
                                if (context.datasetIndex === 2) return `Acumulado completados: ${d.acumulado}`;
                            },
                            afterBody: function(contexts) {
                                if (!contexts.length) return [];
                                const d = datos[contexts[0].dataIndex];
                                const sla     = d.pct_sla          !== null ? d.pct_sla.toFixed(1)          + '%' : 'Sin datos';
                                const slaAcum = d.pct_sla_acumulado !== null ? d.pct_sla_acumulado.toFixed(1) + '%' : 'Sin datos';
                                return [
                                    `Acumulado planificados: ${d.acumulado_plan}`,
                                    `% SLA ese mes: ${sla}`,
                                    `% SLA acumulado: ${slaAcum}`
                                ];
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        ticks: { stepSize: 1 },
                        title: { display: true, text: 'Cantidad mensual' },
                        grid: { color: 'rgba(0,0,0,0.08)' }
                    },
                    y2: {
                        beginAtZero: true,
                        position: 'right',
                        ticks: { stepSize: 1 },
                        title: { display: true, text: 'Acumulado' },
                        grid: { drawOnChartArea: false }
                    },
                    x: { grid: { display: false } }
                },
                animation: { duration: 1000, easing: 'easeOutQuart' }
            }
        });
    },

    // ── Period badge ──────────────────────────────────────────────────────────
    actualizarPeriodoBadge: function() {
        const badge = document.getElementById('periodoActivo');
        if (badge) badge.textContent = 'Mostrando: ' + this.textoPeriodo();
        const anoLabel = document.getElementById('anoResumenLabel');
        if (anoLabel) anoLabel.textContent = this.anoSeleccionado;
    },

    textoPeriodo: function() {
        if (this.mesSeleccionado > 0) {
            return MESES_FULL[this.mesSeleccionado - 1] + ' ' + this.anoSeleccionado;
        }
        return 'Año ' + this.anoSeleccionado;
    },

    // ── KPI Detail modal ──────────────────────────────────────────────────────
    cargarKPIDetalle: function(tipo) {
        const sector  = $('#filtroSector').val() || '';
        const lider   = $('#filtroLider').val()  || '';
        const year    = this.anoSeleccionado;
        const month   = this.mesSeleccionado;
        const periodo = this.textoPeriodo();

        const titulo = tipo === 'completados'
            ? 'Proyectos del plan — Completados vs Plan'
            : 'Proyectos evaluados — SLA';
        document.getElementById('kpiDetalleTitulo').textContent = titulo;

        const filtrosActivos = [];
        if (sector) filtrosActivos.push('sector: ' + sector);
        if (lider)  filtrosActivos.push('líder: ' + lider);
        const sufijo = filtrosActivos.length > 0 ? ' · Filtros: ' + filtrosActivos.join(', ') : '';
        let nota = 'Proyectos cuya Fecha Límite cae en ' + periodo + sufijo + '.';
        if (tipo === 'completados') nota += ' Se excluyen los proyectos pausados.';
        document.getElementById('kpiDetalleNota').textContent = nota;
        document.getElementById('kpiDetalleResumen').textContent = '';
        document.getElementById('kpiDetalleTabla').innerHTML =
            '<tr><td colspan="8" class="text-center text-muted py-3">' +
            '<i class="fas fa-spinner fa-spin me-2"></i>Cargando...</td></tr>';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalKpiDetalle')).show();

        const params = new URLSearchParams({ action: 'kpi-detalle', tipo, sector, lider, year, month });
        fetch('api/estadisticas.php?' + params)
            .then(r => r.json())
            .then(data => {
                if (!data.success) { this.mostrarError('Error al cargar detalle: ' + (data.error || '')); return; }
                this.pintarTablaKPIDetalle(data);
            })
            .catch(() => this.mostrarError('Error al cargar detalle KPI'));
    },

    pintarTablaKPIDetalle: function(data) {
        const Y = data.total;
        const X = data.cumplen;
        const pct = Y > 0 ? (X / Y * 100).toFixed(1) : null;
        const pctDisplay = pct !== null ? pct + '%' : '—';

        // Color del badge de porcentaje según tipo y umbral
        let badgePctClass;
        if (data.tipo === 'sla') {
            badgePctClass = pct === null        ? 'bg-secondary'
                : parseFloat(pct) >= 85         ? 'bg-success'
                : parseFloat(pct) >= 60         ? 'bg-warning text-dark'
                :                                 'bg-danger';
        } else {
            badgePctClass = (pct !== null && parseFloat(pct) === 100) ? 'bg-success' : 'bg-secondary';
        }
        const label = data.tipo === 'completados' ? 'completados' : 'a tiempo';

        // Franja de resumen con métrica destacada
        document.getElementById('kpiDetalleResumen').innerHTML =
            '<div class="d-flex align-items-center gap-4 flex-wrap">' +
                '<div>' +
                    '<div class="kpi-detalle-count">' + X +
                        '<span class="text-muted fw-normal" style="font-size:.65em"> / ' + Y + '</span>' +
                    '</div>' +
                    '<div class="small text-muted">' + label + '</div>' +
                '</div>' +
                '<span class="badge rounded-pill ' + badgePctClass + ' kpi-detalle-pct-badge">' + pctDisplay + '</span>' +
            '</div>';

        const tbody = document.getElementById('kpiDetalleTabla');
        if (!data.proyectos || data.proyectos.length === 0) {
            tbody.innerHTML =
                '<tr><td colspan="8" class="text-center py-4">' +
                '<i class="fas fa-inbox fa-2x text-muted d-block mb-2"></i>' +
                '<span class="text-muted">No hay proyectos para el período y filtros seleccionados.</span>' +
                '</td></tr>';
            return;
        }
        tbody.innerHTML = data.proyectos.map(p => {
            const badgeClass = (p.clasificacion === 'Completado' || p.clasificacion === 'A tiempo')
                ? 'bg-success'
                : (p.clasificacion === 'No completado' ? 'bg-secondary' : 'bg-danger');
            const devNombre = p.desarrollador || '';
            const devDisplay = devNombre || 'Sin asignar';
            const chip = colorChipDesarrollador(devNombre || null);
            const devChip = '<span style="background:' + chip.bg + ';color:' + chip.color +
                ';font-size:0.78rem;padding:0.2em 0.6em;border-radius:999px;white-space:nowrap;font-weight:500">' +
                escapeHtml(devDisplay) + '</span>';
            return '<tr>' +
                '<td class="text-nowrap ps-3">' + p.id + '</td>' +
                '<td>' + escapeHtml(p.desarrollo || '') + '</td>' +
                '<td>' + escapeHtml(p.sector || '') + '</td>' +
                '<td class="text-nowrap">' + devChip + '</td>' +
                '<td class="text-nowrap">' + p.fecha_limite + '</td>' +
                '<td class="text-nowrap">' + p.finalizacion_real + '</td>' +
                '<td class="text-nowrap"><span class="badge rounded-pill badge-estado">' + escapeHtml(p.estado || '') + '</span></td>' +
                '<td class="text-nowrap"><span class="badge rounded-pill badge-clasificacion ' + badgeClass + '">' + escapeHtml(p.clasificacion) + '</span></td>' +
                '</tr>';
        }).join('');
    },

    // ── Error helper ─────────────────────────────────────────────────────────
    mostrarError: function(mensaje) {
        console.error(mensaje);
        const container = document.getElementById('alertContainer');
        if (!container) return;
        const div = document.createElement('div');
        div.className = 'alert alert-danger alert-dismissible fade show';
        div.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        container.appendChild(div);
        setTimeout(() => { if (div.parentNode) div.remove(); }, 5000);
    }
};

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$(document).ready(function() {
    EstadisticasManager.init();
});
