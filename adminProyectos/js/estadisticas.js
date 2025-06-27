
// Variables globales para los gráficos
let chartEstados = null;
let chartSectores = null;
let chartFinalizados = null;

// Configuración de colores para los gráficos
const coloresEstados = {
    'Pendiente': '#ffc107',
    'Proceso': '#17a2b8', 
    'Terminado': '#28a745',
    'Pausado': '#fd7e14',
    'Cancelado': '#dc3545',
    'Sin Estado': '#6c757d'
};

const coloresSectores = [
    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
    '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF',
    '#4BC0C0', '#FF6384', '#36A2EB', '#FFCE56'
];

// Objeto principal para manejar las estadísticas
const EstadisticasManager = {
    // Inicializar la página
    init: function() {
        console.log('Inicializando página de estadísticas...');
        this.cargarFiltros();
        this.setupEventListeners();
        this.cargarTodasLasEstadisticas();
    },

    // Configurar event listeners
    setupEventListeners: function() {
        $('#btnAplicarFiltros').on('click', () => {
            this.cargarTodasLasEstadisticas();
        });

        $('#btnResetFiltros').on('click', () => {
            $('#filtroSector').val('');
            $('#filtroLider').val('');
            $('#filtroEstado').val('');
            this.cargarTodasLasEstadisticas();
        });
    },

    // Cargar filtros disponibles
    cargarFiltros: function() {
        console.log('Cargando filtros...');
        
        fetch('api/estadisticas.php?action=filtros')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.poblarSelect('filtroSector', data.sectores || []);
                    this.poblarSelect('filtroLider', data.lideres || []);
                    this.poblarSelect('filtroEstado', data.estados || []);
                } else {
                    this.mostrarError('Error al cargar filtros: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.mostrarError('Error al cargar filtros');
            });
    },

    // Poblar un select con opciones
    poblarSelect: function(selectId, opciones) {
        const select = document.getElementById(selectId);
        const defaultOption = select.options[0];
        
        select.innerHTML = '';
        select.appendChild(defaultOption);
        
        opciones.forEach(opcion => {
            if (opcion) {
                const option = document.createElement('option');
                option.value = opcion;
                option.textContent = opcion;
                select.appendChild(option);
            }
        });
    },

    // Cargar todas las estadísticas
    cargarTodasLasEstadisticas: function() {
        const filtroSector = $('#filtroSector').val();
        const filtroLider = $('#filtroLider').val();
        const filtroEstado = $('#filtroEstado').val();

        console.log('Cargando estadísticas con filtros:', { filtroSector, filtroLider, filtroEstado });

        // Mostrar loading en todas las cards
        $('.card').addClass('loading');

        // Cargar cada tipo de estadística
        this.cargarEstadisticasEstados(filtroSector, filtroLider); // Con filtros de sector y líder (NO estado)
        this.cargarEstadisticasSectores(filtroLider, filtroEstado); // Con filtros de líder y estado
        this.cargarProyectosFinalizados(filtroSector, filtroLider, filtroEstado); // Con todos los filtros
    },

    // Cargar estadísticas de estados (SIN FILTRO DE ESTADO, CON OTROS FILTROS)
    cargarEstadisticasEstados: function(filtroSector, filtroLider) {
        // El gráfico de estados NO se filtra por estado, pero SÍ por sector y líder
        const params = new URLSearchParams({
            action: 'estados',
            sector: filtroSector || '',
            lider: filtroLider || ''
            // Nota: NO incluimos 'estado' en los parámetros
        });

        fetch(`api/estadisticas.php?${params}`)
            .then(response => response.json())
            .then(data => {
                $('.card').removeClass('loading');
                
                if (data.success) {
                    this.crearGraficoEstados(data.data, data.total);
                    this.actualizarResumenEstados(data.data);
                } else {
                    this.mostrarError('Error al cargar estadísticas de estados: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                $('.card').removeClass('loading');
                console.error('Error:', error);
                this.mostrarError('Error al cargar estadísticas de estados');
            });
    },

    // Cargar estadísticas de sectores
    cargarEstadisticasSectores: function(filtroLider, filtroEstado) {
        const params = new URLSearchParams({
            action: 'sectores',
            lider: filtroLider || '',
            estado: filtroEstado || ''
        });

        fetch(`api/estadisticas.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.crearGraficoSectores(data.data, data.total);
                } else {
                    this.mostrarError('Error al cargar estadísticas de sectores: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.mostrarError('Error al cargar estadísticas de sectores');
            });
    },

    // Cargar proyectos finalizados por año
    cargarProyectosFinalizados: function(filtroSector, filtroLider, filtroEstado) {
        const params = new URLSearchParams({
            action: 'finalizados-por-año',
            sector: filtroSector || '',
            lider: filtroLider || '',
            estado: filtroEstado || ''
        });

        fetch(`api/estadisticas.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.crearGraficoFinalizados(data.data, data.usaFechaFinalizacion, data.estadoFiltrado);
                } else {
                    this.mostrarError('Error al cargar proyectos por año: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.mostrarError('Error al cargar proyectos por año');
            });
    },

    // Actualizar el resumen en las cards superiores - FUNCIÓN AÑADIDA
    actualizarResumenEstados: function(datos) {
        let totalProyectos = 0;
        let terminados = 0;
        let proceso = 0;
        let pendientes = 0;

        datos.forEach(item => {
            totalProyectos += item.cantidad;
            
            switch(item.estado.toLowerCase()) {
                case 'terminado':
                    terminados = item.cantidad;
                    break;
                case 'proceso':
                    proceso = item.cantidad;
                    break;
                case 'pendiente':
                    pendientes = item.cantidad;
                    break;
            }
        });

        // Actualizar las cards
        document.getElementById('totalProyectos').textContent = totalProyectos;
        document.getElementById('proyectosTerminados').textContent = terminados;
        document.getElementById('proyectosProceso').textContent = proceso;
        document.getElementById('proyectosPendientes').textContent = pendientes;
    },

    crearGraficoEstados: function(datos, total) {
        const ctx = document.getElementById('chartEstados').getContext('2d');
        if (chartEstados) chartEstados.destroy();
        
        const labels = datos.map(item => item.estado);
        const values = datos.map(item => item.cantidad);
        const colors = datos.map(item => coloresEstados[item.estado] || '#6c757d');
        
        chartEstados = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderColor: colors.map(color => color + '80'),
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
                            label: function(context) {
                                const porcentaje = ((context.raw / total) * 100).toFixed(1);
                                return `${context.label}: ${context.raw} (${porcentaje}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    duration: 1000
                }
            }
        });
        this.crearLeyenda('leyendaEstados', datos, colors);
    },

    crearGraficoSectores: function(datos, total) {
        const ctx = document.getElementById('chartSectores').getContext('2d');
        if (chartSectores) chartSectores.destroy();
        
        const labels = datos.map(item => item.sector);
        const values = datos.map(item => item.cantidad);
        const colors = datos.map((item, index) => coloresSectores[index % coloresSectores.length]);
        
        chartSectores = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderColor: colors.map(color => color + '80'),
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
                            label: function(context) {
                                const porcentaje = ((context.raw / total) * 100).toFixed(1);
                                return `${context.label}: ${context.raw} (${porcentaje}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    duration: 1000
                }
            }
        });
        this.crearLeyenda('leyendaSectores', datos, colors);
    },

    crearGraficoFinalizados: function(datos, usaFechaFinalizacion, estadoFiltrado) {
        const ctx = document.getElementById('chartFinalizados').getContext('2d');
        if (chartFinalizados) chartFinalizados.destroy();
        
        const labels = datos.map(item => item.año.toString());
        const values = datos.map(item => item.cantidad);

        let titulo, color, descripcion;
        if (estadoFiltrado && estadoFiltrado !== 'Terminado') {
            titulo = `Proyectos "${estadoFiltrado}" por Año`;
            color = coloresEstados[estadoFiltrado] || 'rgba(108, 117, 125, 0.8)';
            descripcion = `* Se muestran proyectos con estado "${estadoFiltrado}" por año de creación`;
        } else {
            titulo = 'Proyectos Finalizados por Año';
            color = 'rgba(40, 167, 69, 0.8)';
            descripcion = '* Solo se muestran proyectos con fecha de finalización real';
        }

        chartFinalizados = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
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
                            title: function(context) {
                                return `Año ${context[0].label}`;
                            },
                            label: function(context) {
                                if (estadoFiltrado && estadoFiltrado !== 'Terminado') {
                                    return `Proyectos ${estadoFiltrado}: ${context.raw}`;
                                } else {
                                    return `Proyectos finalizados: ${context.raw}`;
                                }
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 },
                        grid: { color: 'rgba(0,0,0,0.1)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });

        this.actualizarTituloGraficoFinalizados(titulo, descripcion);
    },

    actualizarTituloGraficoFinalizados: function(titulo, descripcion) {
        const headerElement = document.querySelector('#chartFinalizados').closest('.card').querySelector('.card-header h5');
        if (headerElement) {
            headerElement.innerHTML = `<i class="fas fa-chart-bar me-2"></i>${titulo}`;
        }
        const footerElement = document.querySelector('#chartFinalizados').closest('.card').querySelector('.text-center small');
        if (footerElement) {
            footerElement.textContent = descripcion;
        }
    },

    crearLeyenda: function(containerId, datos, colors) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        
        datos.forEach((item, index) => {
            const legendItem = document.createElement('div');
            legendItem.className = 'legend-item';
            
            const colorBox = document.createElement('div');
            colorBox.className = 'legend-color';
            colorBox.style.backgroundColor = colors[index];
            
            const text = document.createElement('span');
            text.className = 'legend-text';
            text.textContent = item.estado || item.sector;
            
            const count = document.createElement('span');
            count.className = 'legend-count';
            count.textContent = item.cantidad;
            
            legendItem.appendChild(colorBox);
            legendItem.appendChild(text);
            legendItem.appendChild(count);
            container.appendChild(legendItem);
        });
    },

    // Función para mostrar errores - FUNCIÓN AÑADIDA
    mostrarError: function(mensaje) {
        console.error(mensaje);
        
        // Crear alerta de error
        const alertContainer = document.getElementById('alertContainer') || document.querySelector('.container-fluid');
        if (alertContainer) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-exclamation-circle me-2"></i>
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            if (document.getElementById('alertContainer')) {
                document.getElementById('alertContainer').appendChild(alert);
            } else {
                alertContainer.insertBefore(alert, alertContainer.firstChild);
            }
            
            // Auto-cerrar después de 5 segundos
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }
    }
};

// Inicializar cuando el documento esté listo
$(document).ready(function() {
    EstadisticasManager.init();
    console.log('✅ Página de estadísticas cargada correctamente');
});