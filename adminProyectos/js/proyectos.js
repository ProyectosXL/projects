
/**
 * Funciones específicas para la gestión de proyectos
 */

// Objeto para gestionar los proyectos
const ProyectosManager = {
    // Referencia a la tabla DataTable
    dataTable: null,
    
    // Datos originales de proyectos
    proyectosData: [],
    
    // Datos de filtros
    filtros: {
        sectores: [],
        estados: [],
        etapas: [],
        lideres: []
    },
    
    // Inicializa la gestión de proyectos
    init: function() {
        this.cargarFiltros();
        this.inicializarTabla();
        this.setupEventListeners();
    },
    
    // Carga las opciones de filtros desde el servidor
    cargarFiltros: function() {
        console.log('Cargando filtros...');
        
        fetch('api/proyectos.php?action=filtros')
            .then(response => {
                console.log('Respuesta recibida:', response);
                if (!response.ok) {
                    throw new Error('Error al cargar filtros: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Datos de filtros:', data);
                this.filtros = data;
                this.poblarSelectFiltros('filtroSector', data.sectores);
                this.poblarSelectFiltros('filtroEstado', data.estados);
                this.poblarSelectFiltros('filtroEtapa', data.etapas);
                this.poblarSelectFiltros('filtroLider', data.lideres);
            })
            .catch(error => {
                console.error('Error en cargarFiltros:', error);
                mostrarAlerta('No se pudieron cargar los filtros: ' + error.message, 'error');
            });
    },
    
    // Rellena un select con opciones (ACTUALIZADO para filtros múltiples)
    poblarSelectFiltros: function(selectId, opciones) {
        const select = document.getElementById(selectId);
        if (!select) {
            console.error(`No se encontró el select con ID: ${selectId}`);
            return;
        }
        
        // Limpiar opciones existentes
        select.innerHTML = '';
        
        // Agregar nuevas opciones
        if (Array.isArray(opciones)) {
            opciones.forEach(opcion => {
                if (opcion) { // Verificar que no sea null o vacío
                    const option = document.createElement('option');
                    option.value = opcion;
                    option.textContent = opcion;
                    select.appendChild(option);
                }
            });
        } else {
            console.warn(`Las opciones para ${selectId} no son un array:`, opciones);
        }
        
        // Agregar evento para actualizar indicadores visuales
        select.addEventListener('change', () => {
            this.actualizarIndicadoresFiltros();
        });
    },
    
    // Inicializa la tabla DataTable
    inicializarTabla: function() {
        console.log('Inicializando tabla...');
        
        this.dataTable = $('#tablaProyectos').DataTable({
            ajax: {
                url: 'api/proyectos.php?action=listar',
                dataSrc: 'data',
                error: function(xhr, textStatus, error) {
                    console.error('Error en la solicitud AJAX:', textStatus, error);
                    console.log('Respuesta del servidor:', xhr.responseText);
                    mostrarAlerta('Error al cargar los datos. Consulte la consola para más detalles.', 'error');
                }
            },
            columns: [
                { data: 'ID' },
                { data: 'FechaCreacion' },
                { data: 'Desarrollo' },
                { data: 'TipoPresentacion' },
                { data: 'Solicitante' },
                { data: 'Sector' },
                { 
                    data: 'Estado',
                    render: function(data) {
                        const claseEstado = 'estado-' + (data ? data.toLowerCase() : 'desconocido');
                        return `<span class="badge rounded-pill ${claseEstado}">${data || 'N/A'}</span>`;
                    }
                },
                { data: 'EtapaProyecto' },
                { data: 'LiderProyecto' },
                { 
                    data: 'Prioridad',
                    render: function(data) {
                        let prioridadLower = (data || '').toLowerCase();
                        let clase = '';
                        
                        if (prioridadLower.includes('urgente')) {
                            clase = 'prioridad-urgente';
                        } else if (prioridadLower.includes('alta')) {
                            clase = 'prioridad-alta';
                        } else if (prioridadLower.includes('media')) {
                            clase = 'prioridad-media';
                        } else if (prioridadLower.includes('baja')) {
                            clase = 'prioridad-baja';
                        } else if (prioridadLower.includes('sin asignar') || !data || data.trim() === '') {
                            clase = 'prioridad-sin-asignar';
                            data = data || 'Sin asignar';
                        }
                        
                        return `<span class="prioridad-badge ${clase}">${data}</span>`;
                    }
                },
                { 
                    data: 'PorcentajeAvance',
                    render: function(data) {
                        const porcentaje = parseFloat(data) * 100;
                        let color = '#ff5722'; // Valor predeterminado
                        
                        if (porcentaje >= 85) {
                            color = '#4caf50'; // Verde para alto progreso
                        } else if (porcentaje >= 60) {
                            color = '#00bcd4'; // Azul claro para progreso medio-alto
                        } else if (porcentaje >= 30) {
                            color = '#2196f3'; // Azul para progreso medio
                        } else if (porcentaje >= 15) {
                            color = '#ff9800'; // Naranja para progreso bajo-medio
                        }
                        
                        return `<div class="progress-circle" style="--percentage: ${porcentaje}%; --color: ${color}">
                                    <span>${porcentaje.toFixed(0)}%</span>
                                </div>`;
                    }
                },
                { 
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        // Corrección: Usar atributo data-id para el ID del proyecto
                        return `<button class="btn btn-info btn-sm btn-detalle" data-id="${row.ID}">
                                    <i class="fas fa-info-circle"></i>
                                </button>`;
                    }
                }
            ],
            order: [[0, 'desc']],
            responsive: true,
            fixedHeader: true,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            pageLength: 50,
            dom: '<"top"lf>rt<"bottom"ip><"clear">',
            createdRow: function(row, data, index) {
                // Ya no necesitamos aplicar clases a nivel de fila para prioridad
                // ya que ahora estamos utilizando badges en la celda de prioridad
            },
            // Usar drawCallback para añadir eventos a botones generados dinámicamente
            drawCallback: function() {
                $('.btn-detalle').off('click').on('click', function() {
                    const idProyecto = $(this).data('id');
                    console.log('Ver detalle del proyecto ID:', idProyecto);
                    ProyectosManager.verDetalleProyecto(idProyecto);
                });
            }
        });
    },
    
    // Configura los listeners de eventos
    setupEventListeners: function() {
        // Botón para aplicar filtros
        const btnAplicar = document.getElementById('btnAplicarFiltros');
        if (btnAplicar) {
            btnAplicar.addEventListener('click', () => {
                this.aplicarFiltros();
            });
        } else {
            console.warn('No se encontró el botón de aplicar filtros');
        }
        
        // Botón para resetear filtros
        const btnReset = document.getElementById('btnResetFiltros');
        if (btnReset) {
            btnReset.addEventListener('click', () => {
                this.resetearFiltros();
            });
        } else {
            console.warn('No se encontró el botón de resetear filtros');
        }
    },
    
    // Aplica los filtros seleccionados (ACTUALIZADO para filtros múltiples)
    aplicarFiltros: function() {
        const sectores = Array.from(document.getElementById('filtroSector')?.selectedOptions || []).map(opt => opt.value);
        const estados = Array.from(document.getElementById('filtroEstado')?.selectedOptions || []).map(opt => opt.value);
        const etapas = Array.from(document.getElementById('filtroEtapa')?.selectedOptions || []).map(opt => opt.value);
        const lideres = Array.from(document.getElementById('filtroLider')?.selectedOptions || []).map(opt => opt.value);
        
        console.log('Aplicando filtros múltiples:', { sectores, estados, etapas, lideres });
        
        // Limpiar filtros previos
        $.fn.dataTable.ext.search.length = 0;
        
        // Crear filtros personalizados para DataTables
        $.fn.dataTable.ext.search.push(
            (settings, data, dataIndex) => {
                // Los índices deben coincidir con el orden de las columnas en la tabla HTML
                const filaSector = data[5]; // Columna Sector
                const filaEstado = data[6]; // Columna Estado (texto sin HTML)
                const filaEtapa = data[7];  // Columna EtapaProyecto
                const filaLider = data[8];  // Columna LiderProyecto
                
                // Verificar cada filtro (si hay selecciones, debe coincidir al menos una)
                if (sectores.length > 0 && !sectores.some(sector => filaSector.includes(sector))) return false;
                if (estados.length > 0 && !estados.some(estado => filaEstado.includes(estado))) return false;
                if (etapas.length > 0 && !etapas.some(etapa => filaEtapa.includes(etapa))) return false;
                if (lideres.length > 0 && !lideres.some(lider => filaLider.includes(lider))) return false;
                
                return true;
            }
        );
        
        // Redibujar la tabla con los filtros aplicados
        this.dataTable.draw();
        
        // Actualizar indicadores visuales
        this.actualizarIndicadoresFiltros();
        this.actualizarTextoFiltrosActivos();
    },
    
    // Resetea todos los filtros (ACTUALIZADO para filtros múltiples)
    resetearFiltros: function() {
        const selectores = ['filtroSector', 'filtroEstado', 'filtroEtapa', 'filtroLider'];
        
        selectores.forEach(id => {
            const elemento = document.getElementById(id);
            if (elemento) {
                // Para selects múltiples, deseleccionar todas las opciones
                Array.from(elemento.options).forEach(option => {
                    option.selected = false;
                });
                // Quitar clase de filtro activo
                elemento.classList.remove('filtro-activo');
            }
        });
        
        // Limpiar filtros de DataTables
        $.fn.dataTable.ext.search.length = 0;
        
        // Redibujar la tabla sin filtros
        this.dataTable.search('').columns().search('').draw();
        
        // Actualizar indicadores visuales
        this.actualizarIndicadoresFiltros();
        this.actualizarTextoFiltrosActivos();
        
        console.log('Filtros reseteados');
    },
    
    // Actualiza los indicadores visuales de filtros activos (NUEVA FUNCIÓN)
    actualizarIndicadoresFiltros: function() {
        const selectores = ['filtroSector', 'filtroEstado', 'filtroEtapa', 'filtroLider'];
        
        selectores.forEach(id => {
            const elemento = document.getElementById(id);
            if (elemento) {
                const seleccionados = Array.from(elemento.selectedOptions);
                
                if (seleccionados.length > 0) {
                    elemento.classList.add('filtro-activo');
                } else {
                    elemento.classList.remove('filtro-activo');
                }
            }
        });
    },
    
    // Actualiza el texto que muestra los filtros activos (NUEVA FUNCIÓN)
    actualizarTextoFiltrosActivos: function() {
        const textoElement = document.getElementById('filtrosActivosTexto');
        if (!textoElement) return;
        
        const filtrosActivos = [];
        
        // Obtener filtros seleccionados
        const sectores = Array.from(document.getElementById('filtroSector')?.selectedOptions || []).map(opt => opt.value);
        const estados = Array.from(document.getElementById('filtroEstado')?.selectedOptions || []).map(opt => opt.value);
        const etapas = Array.from(document.getElementById('filtroEtapa')?.selectedOptions || []).map(opt => opt.value);
        const lideres = Array.from(document.getElementById('filtroLider')?.selectedOptions || []).map(opt => opt.value);
        
        // Construir texto de filtros activos
        if (sectores.length > 0) {
            filtrosActivos.push(`Sectores: ${sectores.length === 1 ? sectores[0] : `${sectores.length} seleccionados`}`);
        }
        if (estados.length > 0) {
            filtrosActivos.push(`Estados: ${estados.length === 1 ? estados[0] : `${estados.length} seleccionados`}`);
        }
        if (etapas.length > 0) {
            filtrosActivos.push(`Etapas: ${etapas.length === 1 ? etapas[0] : `${etapas.length} seleccionados`}`);
        }
        if (lideres.length > 0) {
            filtrosActivos.push(`Líderes: ${lideres.length === 1 ? lideres[0] : `${lideres.length} seleccionados`}`);
        }
        
        // Actualizar texto
        if (filtrosActivos.length > 0) {
            textoElement.textContent = `Filtros activos: ${filtrosActivos.join(' | ')}`;
            textoElement.style.color = '#0d6efd';
            textoElement.style.fontWeight = '500';
        } else {
            textoElement.textContent = 'Sin filtros aplicados';
            textoElement.style.color = '#6c757d';
            textoElement.style.fontWeight = 'normal';
        }
    },
    
    // Ver detalle de un proyecto
    verDetalleProyecto: function(idProyecto) {
        console.log('Navegando al detalle del proyecto ID:', idProyecto);
        
        if (!idProyecto) {
            console.error('ID de proyecto no válido');
            mostrarAlerta('No se pudo abrir el detalle: ID de proyecto no válido', 'error');
            return;
        }
        
        // Redirigir a la página de detalle
        window.location.href = `detalleProyecto.php?id=${idProyecto}`;
    }
};

// Función para mostrar alertas
function mostrarAlerta(mensaje, tipo = 'info') {
    console.log(`Alerta (${tipo}): ${mensaje}`);
    
    // Si existe toastr, usarlo para mostrar notificaciones
    if (typeof toastr !== 'undefined') {
        toastr[tipo](mensaje);
        return;
    }
    
    // Crear contenedor para alertas si no existe
    let alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'alertContainer';
        
        const container = document.querySelector('.container-fluid') || document.querySelector('.container');
        if (container) {
            container.insertBefore(alertContainer, container.firstChild);
        } else {
            document.body.insertBefore(alertContainer, document.body.firstChild);
        }
    }
    
    // Alternativa: crear manualmente un elemento de alerta
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${tipo === 'error' ? 'danger' : tipo} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    
    // Iconos para diferentes tipos de alertas
    let icono = 'info-circle';
    if (tipo === 'success') icono = 'check-circle';
    if (tipo === 'warning') icono = 'exclamation-triangle';
    if (tipo === 'error' || tipo === 'danger') {
        icono = 'exclamation-circle';
    }
    
    alertDiv.innerHTML = `
        <i class="fas fa-${icono} me-2"></i>
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Agregar alerta al contenedor
    alertContainer.appendChild(alertDiv);
    
    // Auto-cerrar después de 5 segundos
    setTimeout(() => {
        if (alertDiv && alertDiv.parentNode) {
            if (typeof bootstrap !== 'undefined') {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            } else {
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.remove(), 300);
            }
        }
    }, 5000);
}