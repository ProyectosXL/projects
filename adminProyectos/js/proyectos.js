
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
                this.poblarSelectFiltros('filtroPrioridad', data.prioridades); // NUEVO
                
                // Restaurar filtros si vienen de otra página
                this.restaurarFiltrosGuardados();
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
                        let acciones = `<button class="btn btn-info btn-sm btn-detalle" data-id="${row.ID}">
                                        <i class="fas fa-info-circle"></i>
                                    </button>`;
                        
                        // Agregar ícono para URL Requerimiento si existe
                        if (row.URLRequerimiento && row.URLRequerimiento.trim() !== '') {
                            acciones += ` <a href="${row.URLRequerimiento}" target="_blank" rel="noopener noreferrer" 
                                            class="btn btn-sm btn-outline-danger" title="Ver Requerimiento">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>`;
                        }
                        
                        return acciones;
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
    
    aplicarFiltros: function() {
        const sectores = Array.from(document.getElementById('filtroSector')?.selectedOptions || []).map(opt => opt.value);
        const estados = Array.from(document.getElementById('filtroEstado')?.selectedOptions || []).map(opt => opt.value);
        const etapas = Array.from(document.getElementById('filtroEtapa')?.selectedOptions || []).map(opt => opt.value);
        const lideres = Array.from(document.getElementById('filtroLider')?.selectedOptions || []).map(opt => opt.value);
        const prioridades = Array.from(document.getElementById('filtroPrioridad')?.selectedOptions || []).map(opt => opt.value); // NUEVO
        
        console.log('Aplicando filtros múltiples:', { sectores, estados, etapas, lideres, prioridades });
        
        // Guardar filtros
        this.guardarFiltros();
        
        // Limpiar filtros previos
        $.fn.dataTable.ext.search.length = 0;
        
        // Crear filtros personalizados para DataTables
        $.fn.dataTable.ext.search.push(
            (settings, data, dataIndex) => {
                const filaSector = data[5]; // Columna Sector
                const filaEstado = data[6]; // Columna Estado
                const filaEtapa = data[7];  // Columna EtapaProyecto
                const filaLider = data[8];  // Columna LiderProyecto
                const filaPrioridad = data[9]; // Columna Prioridad (NUEVO)
                
                // Verificar cada filtro
                if (sectores.length > 0 && !sectores.some(sector => filaSector.includes(sector))) return false;
                if (estados.length > 0 && !estados.some(estado => filaEstado.includes(estado))) return false;
                if (etapas.length > 0 && !etapas.some(etapa => filaEtapa.includes(etapa))) return false;
                if (lideres.length > 0 && !lideres.some(lider => filaLider.includes(lider))) return false;
                if (prioridades.length > 0 && !prioridades.some(prioridad => filaPrioridad.includes(prioridad))) return false; // NUEVO
                
                return true;
            }
        );
        
        // Redibujar la tabla con los filtros aplicados
        this.dataTable.draw();
        
        // Actualizar indicadores visuales
        this.actualizarIndicadoresFiltros();
        this.actualizarTextoFiltrosActivos();
    },
    
    resetearFiltros: function() {
        const selectores = ['filtroSector', 'filtroEstado', 'filtroEtapa', 'filtroLider', 'filtroPrioridad']; // Agregar filtroPrioridad
        
        selectores.forEach(id => {
            const elemento = document.getElementById(id);
            if (elemento) {
                Array.from(elemento.options).forEach(option => {
                    option.selected = false;
                });
                elemento.classList.remove('filtro-activo');
            }
        });
        
        // Limpiar sessionStorage
        sessionStorage.removeItem('filtrosProyectos');
        
        // Limpiar filtros de DataTables
        $.fn.dataTable.ext.search.length = 0;
        this.dataTable.search('').columns().search('').draw();
        
        this.actualizarIndicadoresFiltros();
        this.actualizarTextoFiltrosActivos();
        
        console.log('Filtros reseteados');
    },
    
    actualizarIndicadoresFiltros: function() {
        const selectores = ['filtroSector', 'filtroEstado', 'filtroEtapa', 'filtroLider', 'filtroPrioridad']; // Agregar filtroPrioridad
        
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
    
    actualizarTextoFiltrosActivos: function() {
        const textoElement = document.getElementById('filtrosActivosTexto');
        if (!textoElement) return;
        
        const filtrosActivos = [];
        
        // Obtener filtros seleccionados
        const sectores = Array.from(document.getElementById('filtroSector')?.selectedOptions || []).map(opt => opt.value);
        const estados = Array.from(document.getElementById('filtroEstado')?.selectedOptions || []).map(opt => opt.value);
        const etapas = Array.from(document.getElementById('filtroEtapa')?.selectedOptions || []).map(opt => opt.value);
        const lideres = Array.from(document.getElementById('filtroLider')?.selectedOptions || []).map(opt => opt.value);
        const prioridades = Array.from(document.getElementById('filtroPrioridad')?.selectedOptions || []).map(opt => opt.value); // NUEVO
        
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
        if (prioridades.length > 0) { // NUEVO
            filtrosActivos.push(`Prioridades: ${prioridades.length === 1 ? prioridades[0] : `${prioridades.length} seleccionados`}`);
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
}, // ← AGREGAR ESTA COMA

// Guardar filtros en sessionStorage
guardarFiltros: function() {
    const filtros = {
        sectores: Array.from(document.getElementById('filtroSector')?.selectedOptions || []).map(opt => opt.value),
        estados: Array.from(document.getElementById('filtroEstado')?.selectedOptions || []).map(opt => opt.value),
        etapas: Array.from(document.getElementById('filtroEtapa')?.selectedOptions || []).map(opt => opt.value),
        lideres: Array.from(document.getElementById('filtroLider')?.selectedOptions || []).map(opt => opt.value),
        prioridades: Array.from(document.getElementById('filtroPrioridad')?.selectedOptions || []).map(opt => opt.value)
    };
    
    sessionStorage.setItem('filtrosProyectos', JSON.stringify(filtros));
},

// Restaurar filtros guardados
restaurarFiltrosGuardados: function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('aplicar_filtros') === 'true') {
        const filtrosGuardados = sessionStorage.getItem('filtrosProyectos');
        if (filtrosGuardados) {
            const filtros = JSON.parse(filtrosGuardados);
            
            // Aplicar filtros guardados a los selects
            this.aplicarFiltrosGuardados('filtroSector', filtros.sectores);
            this.aplicarFiltrosGuardados('filtroEstado', filtros.estados);
            this.aplicarFiltrosGuardados('filtroEtapa', filtros.etapas);
            this.aplicarFiltrosGuardados('filtroLider', filtros.lideres);
            this.aplicarFiltrosGuardados('filtroPrioridad', filtros.prioridades);
            
            // Aplicar filtros automáticamente
            setTimeout(() => this.aplicarFiltros(), 500);
        }
    }
},

// Aplicar filtros guardados a un select específico
aplicarFiltrosGuardados: function(selectId, valores) {
    const select = document.getElementById(selectId);
    if (select && valores && valores.length > 0) {
        Array.from(select.options).forEach(option => {
            option.selected = valores.includes(option.value);
        });
    }
}

}; // ← Este es el cierre del objeto ProyectosManager

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