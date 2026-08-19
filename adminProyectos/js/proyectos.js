
/**
 * Funciones específicas para la gestión de proyectos
 */

// Objeto para gestionar los proyectos
const ProyectosManager = {
    // Referencia a la tabla DataTable
    dataTable: null,

    // Mapa único de índices de columna de la tabla (evita números mágicos
    // dispersos que se desalinean en silencio si se agrega/mueve una columna)
    COL: {
        id: 0, desarrollo: 1, programa: 2, tipoPresentacion: 3, solicitante: 4,
        sector: 5, inicioProgramado: 6, fechaLimite: 7, estado: 8, etapa: 9,
        finalizacionReal: 10, lider: 11, desarrollador: 12, prioridad: 13,
        avance: 14, acciones: 15
    },

    // Datos originales de proyectos
    proyectosData: [],

    // Datos de filtros
    filtros: {
        sectores: [],
        estados: [],
        etapas: [],
        lideres: [],
        desarrolladores: [],
        prioridades: [],
        programas: []
    },

    // Filtros activos de multi-select (usados por la función de búsqueda global)
    filtrosActuales: {
        sectores: [], estados: [], etapas: [], lideres: [], desarrolladores: [], prioridades: [], programas: []
    },

    // Chips de fecha activos
    activeChips: new Set(),

    // Estado del ordenamiento por prioridad
    sortByPriority: false,

    // Inicializa la gestión de proyectos
    init: function() {
        this.cargarFiltros();
        this.inicializarTabla();
        this.inicializarFiltroGlobal();
        this.setupEventListeners();
        this.setupChipFilters();
        this.setupPanelFiltros();
        this.actualizarTextoFiltrosActivos();
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
                this.poblarSelectFiltros('filtroDesarrollador', data.desarrolladores);
                this.poblarSelectFiltros('filtroPrioridad', data.prioridades);
                this.poblarSelectFiltros('filtroPrograma', data.programas || []);

                // Restaurar filtros si vienen de otra página
                this.restaurarFiltrosGuardados();
            })
            .catch(error => {
                console.error('Error en cargarFiltros:', error);
                mostrarAlerta('No se pudieron cargar los filtros: ' + error.message, 'error');
            });
    },

    // Rellena un select con opciones. Acepta tanto un array de strings planos
    // (comportamiento original) como un array de objetos {id, nombre, color}
    // (usado por el filtro de Programa, que además necesita la opción fija
    // "(Sin programa)" para poder aislar los proyectos sueltos).
    poblarSelectFiltros: function(selectId, opciones) {
        const select = document.getElementById(selectId);
        if (!select) {
            console.error(`No se encontró el select con ID: ${selectId}`);
            return;
        }

        // Limpiar opciones existentes
        select.innerHTML = '';

        if (Array.isArray(opciones)) {
            const esPrograma = selectId === 'filtroPrograma';
            const esObjetos = esPrograma || (opciones.length > 0 && typeof opciones[0] === 'object' && opciones[0] !== null);

            if (esObjetos) {
                if (esPrograma) {
                    const optionSinPrograma = document.createElement('option');
                    optionSinPrograma.value = '0';
                    optionSinPrograma.textContent = '(Sin programa)';
                    select.appendChild(optionSinPrograma);
                }

                opciones.forEach(opcion => {
                    if (opcion && opcion.id !== undefined && opcion.id !== null) {
                        const option = document.createElement('option');
                        option.value = String(opcion.id);
                        option.textContent = opcion.nombre;
                        select.appendChild(option);
                    }
                });
            } else {
                opciones.forEach(opcion => {
                    if (opcion) { // Verificar que no sea null o vacío
                        const option = document.createElement('option');
                        option.value = opcion;
                        option.textContent = opcion;
                        select.appendChild(option);
                    }
                });
            }
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
                {
                    data: 'ID',
                    render: function(data, type, row) {
                        if (type !== 'display') return data;
                        const fechaCreacion = row.FechaCreacion
                            ? new Date(row.FechaCreacion).toLocaleDateString('es-ES')
                            : 'Sin fecha';
                        return `<span data-bs-toggle="tooltip" data-bs-placement="right" title="Creado: ${fechaCreacion}">${data}</span>`;
                    }
                },
                { data: 'Desarrollo' },
                {
                    data: 'NombrePrograma',
                    render: function(data, type, row) {
                        if (type !== 'display') return data || '';
                        if (!data) return '<span class="text-muted">-</span>';
                        const color = row.ColorPrograma || '#6c757d';
                        return `<span class="badge" style="background-color:${color}">${escapeHtml(data)}</span>`;
                    }
                },
                { data: 'TipoPresentacion' },
                { data: 'Solicitante' },
                { data: 'Sector' },
                {
                    data: 'InicioProgramado',
                    render: function(data, type, row) {
                        if (!data || data === '1900-01-01' || data === '') {
                            return '<span class="text-muted">Sin definir</span>';
                        }

                        const fechaInicio = new Date(data);
                        const fechaFormateada = fechaInicio.toLocaleDateString('es-ES');

                        const estado = row.Estado ? row.Estado.toLowerCase() : '';
                        const mostrarSemaforo = estado === 'proceso' || estado === 'pendiente' || estado === 'en proceso';

                        if (!mostrarSemaforo) {
                            return `<span class="text-muted">${fechaFormateada}</span>`;
                        }

                        const hoy = new Date();
                        const diferenciaDias = Math.ceil((fechaInicio - hoy) / (1000 * 60 * 60 * 24));

                        let icono, tooltip, color;

                        if (diferenciaDias < 0) {
                            icono = 'fas fa-circle text-secondary';
                            tooltip = `Inició hace ${Math.abs(diferenciaDias)} días`;
                            color = 'text-secondary';
                        } else if (diferenciaDias <= 2) {
                            icono = 'fas fa-circle text-warning';
                            tooltip = diferenciaDias === 0 ? 'Inicia hoy' : `Inicia en ${diferenciaDias} días`;
                            color = 'text-warning';
                        } else {
                            icono = 'fas fa-circle text-success';
                            tooltip = `Inicia en ${diferenciaDias} días`;
                            color = 'text-success';
                        }

                        return `<span class="d-flex align-items-center">
                                   <i class="${icono} me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="${tooltip}"></i>
                                   <span class="${color}">${fechaFormateada}</span>
                               </span>`;
                    }
                },
                {
                    data: 'FechaLimite',
                    render: function(data, type, row) {
                        if (!data || data === '1900-01-01' || data === '') {
                            return '<span class="text-muted">Sin definir</span>';
                        }

                        const fechaLimite = new Date(data);
                        const fechaFormateada = fechaLimite.toLocaleDateString('es-ES');

                        // Solo mostrar semáforo para estados PROCESO y PENDIENTE
                        const estado = row.Estado ? row.Estado.toLowerCase() : '';
                        const mostrarSemaforo = estado === 'proceso' || estado === 'pendiente' || estado === 'en proceso';

                        if (!mostrarSemaforo) {
                            return `<span class="text-muted">${fechaFormateada}</span>`;
                        }

                        const hoy = new Date();
                        const diferenciaDias = Math.ceil((fechaLimite - hoy) / (1000 * 60 * 60 * 24));

                        let icono = '';
                        let tooltip = '';
                        let color = '';

                        if (diferenciaDias < 0) {
                            // Vencido - Rojo
                            icono = 'fas fa-circle text-danger';
                            tooltip = `Vencido hace ${Math.abs(diferenciaDias)} días`;
                            color = 'text-danger';
                        } else if (diferenciaDias <= 2) {
                            // Faltan 2 días o menos - Amarillo
                            icono = 'fas fa-circle text-warning';
                            tooltip = diferenciaDias === 0 ? 'Vence hoy' : `Faltan ${diferenciaDias} días para el vencimiento`;
                            color = 'text-warning';
                        } else {
                            // Dentro del plazo - Verde
                            icono = 'fas fa-circle text-success';
                            tooltip = `Faltan ${diferenciaDias} días`;
                            color = 'text-success';
                        }

                        return `<span class="d-flex align-items-center">
                                   <i class="${icono} me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="${tooltip}"></i>
                                   <span class="${color}">${fechaFormateada}</span>
                               </span>`;
                    }
                },
                {
                    data: 'Estado',
                    render: function(data) {
                        const claseEstado = 'estado-' + (data ? data.toLowerCase() : 'desconocido');
                        return `<span class="badge rounded-pill ${claseEstado}">${data || 'N/A'}</span>`;
                    }
                },
                { data: 'EtapaProyecto' },
                {
                    data: 'FinalizacionReal',
                    render: function(data) {
                        if (!data || data === '1900-01-01' || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        const fecha = new Date(data);
                        return fecha.toLocaleDateString('es-ES');
                    }
                },
                { data: 'LiderProyecto' },
                { data: 'Desarrollador' },
                {
                    data: 'Prioridad',
                    type: 'prioridad',
                    render: function(data, type) {
                        if (type === 'sort' || type === 'type') {
                            const p = (data || '').toLowerCase();
                            if (p.includes('urgente'))     return 1;
                            if (p.includes('alta'))        return 2;
                            if (p.includes('media'))       return 3;
                            if (p.includes('baja'))        return 4;
                            return 5;
                        }
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

                // Activar tooltips de Bootstrap para los semáforos de fecha límite
                $('[data-bs-toggle="tooltip"]').tooltip();
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

        // Botón ordenar por prioridad
        const btnOrdenar = document.getElementById('btnOrdenarPrioridad');
        if (btnOrdenar) {
            btnOrdenar.addEventListener('click', () => {
                this.sortByPriority = !this.sortByPriority;
                if (this.sortByPriority) {
                    btnOrdenar.classList.remove('btn-outline-secondary');
                    btnOrdenar.classList.add('btn-secondary', 'active');
                    this.dataTable.order([this.COL.prioridad, 'asc']).draw();
                } else {
                    btnOrdenar.classList.remove('btn-secondary', 'active');
                    btnOrdenar.classList.add('btn-outline-secondary');
                    this.dataTable.order([0, 'desc']).draw();
                }
            });
        }
    },

    aplicarFiltros: function() {
        this.filtrosActuales = {
            sectores:       Array.from(document.getElementById('filtroSector')?.selectedOptions || []).map(opt => opt.value),
            estados:        Array.from(document.getElementById('filtroEstado')?.selectedOptions || []).map(opt => opt.value),
            etapas:         Array.from(document.getElementById('filtroEtapa')?.selectedOptions || []).map(opt => opt.value),
            lideres:        Array.from(document.getElementById('filtroLider')?.selectedOptions || []).map(opt => opt.value),
            desarrolladores: Array.from(document.getElementById('filtroDesarrollador')?.selectedOptions || []).map(opt => opt.value),
            prioridades:    Array.from(document.getElementById('filtroPrioridad')?.selectedOptions || []).map(opt => opt.value),
            programas:      Array.from(document.getElementById('filtroPrograma')?.selectedOptions || []).map(opt => opt.value)
        };

        console.log('Aplicando filtros múltiples:', this.filtrosActuales);

        this.guardarFiltros();
        this.dataTable.draw();
        this.actualizarIndicadoresFiltros();
        this.actualizarTextoFiltrosActivos();

        // Cerrar el panel: aplicar filtros es la señal de "terminé de elegir",
        // y es lo que le devuelve el espacio a la tabla
        const panel = document.getElementById('panelFiltros');
        if (panel) {
            bootstrap.Collapse.getOrCreateInstance(panel).hide();
        }
    },

    resetearFiltros: function() {
        const selectores = ['filtroSector', 'filtroEstado', 'filtroEtapa', 'filtroLider', 'filtroDesarrollador', 'filtroPrioridad', 'filtroPrograma'];

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

        // Limpiar filtros activos
        this.filtrosActuales = { sectores: [], estados: [], etapas: [], lideres: [], desarrolladores: [], prioridades: [], programas: [] };
        this.dataTable.search('').draw();

        this.actualizarIndicadoresFiltros();
        this.actualizarTextoFiltrosActivos();

        console.log('Filtros reseteados');
    },

    actualizarIndicadoresFiltros: function() {
        const selectores = ['filtroSector', 'filtroEstado', 'filtroEtapa', 'filtroLider', 'filtroDesarrollador', 'filtroPrioridad', 'filtroPrograma'];

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
        const badgeElement = document.getElementById('badgeFiltrosActivos');

        // Obtener filtros seleccionados
        const sectores = Array.from(document.getElementById('filtroSector')?.selectedOptions || []).map(opt => opt.value);
        const estados = Array.from(document.getElementById('filtroEstado')?.selectedOptions || []).map(opt => opt.value);
        const etapas = Array.from(document.getElementById('filtroEtapa')?.selectedOptions || []).map(opt => opt.value);
        const lideres = Array.from(document.getElementById('filtroLider')?.selectedOptions || []).map(opt => opt.value);
        const desarrolladores = Array.from(document.getElementById('filtroDesarrollador')?.selectedOptions || []).map(opt => opt.value);
        const prioridades = Array.from(document.getElementById('filtroPrioridad')?.selectedOptions || []).map(opt => opt.value);
        const programas = Array.from(document.getElementById('filtroPrograma')?.selectedOptions || []).map(opt => opt.value);

        // Construir texto de filtros activos (sin prefijo: el badge y el
        // título "Filtros" ya dicen de qué se trata)
        const filtrosActivos = [];

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
        if (desarrolladores.length > 0) {
            filtrosActivos.push(`Desarrolladores: ${desarrolladores.length === 1 ? desarrolladores[0] : `${desarrolladores.length} seleccionados`}`);
        }
        if (prioridades.length > 0) {
            filtrosActivos.push(`Prioridades: ${prioridades.length === 1 ? prioridades[0] : `${prioridades.length} seleccionados`}`);
        }
        if (programas.length > 0) {
            let etiquetaPrograma;
            if (programas.length === 1) {
                if (programas[0] === '0') {
                    etiquetaPrograma = '(Sin programa)';
                } else {
                    const programaEncontrado = (this.filtros.programas || []).find(p => String(p.id) === programas[0]);
                    etiquetaPrograma = programaEncontrado ? programaEncontrado.nombre : programas[0];
                }
            } else {
                etiquetaPrograma = `${programas.length} seleccionados`;
            }
            filtrosActivos.push(`Programa: ${etiquetaPrograma}`);
        }

        // El badge cuenta GRUPOS de filtro con al menos un valor elegido, no
        // la suma de valores (dos sectores + un estado = 2, no 3)
        const gruposActivos = [sectores, estados, etapas, lideres, desarrolladores, prioridades, programas]
            .filter(grupo => grupo.length > 0).length;

        // Actualizar texto del header. Nada de estilos inline: sobre el fondo
        // azul del header, texto blanco atenuado sin filtros y blanco pleno
        // con filtros (un color como '#0d6efd' encima de ese mismo azul
        // quedaría invisible)
        if (textoElement) {
            if (filtrosActivos.length > 0) {
                textoElement.textContent = filtrosActivos.join(' | ');
                textoElement.classList.remove('text-white-50');
                textoElement.classList.add('text-white', 'fw-semibold');
            } else {
                textoElement.textContent = 'Sin filtros aplicados';
                textoElement.classList.remove('text-white', 'fw-semibold');
                textoElement.classList.add('text-white-50');
            }
        }

        if (badgeElement) {
            if (gruposActivos > 0) {
                badgeElement.textContent = gruposActivos;
                badgeElement.classList.remove('d-none');
            } else {
                badgeElement.classList.add('d-none');
            }
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

    // Guardar filtros en sessionStorage
    guardarFiltros: function() {
        const filtros = {
            sectores: Array.from(document.getElementById('filtroSector')?.selectedOptions || []).map(opt => opt.value),
            estados: Array.from(document.getElementById('filtroEstado')?.selectedOptions || []).map(opt => opt.value),
            etapas: Array.from(document.getElementById('filtroEtapa')?.selectedOptions || []).map(opt => opt.value),
            lideres: Array.from(document.getElementById('filtroLider')?.selectedOptions || []).map(opt => opt.value),
            desarrolladores: Array.from(document.getElementById('filtroDesarrollador')?.selectedOptions || []).map(opt => opt.value),
            prioridades: Array.from(document.getElementById('filtroPrioridad')?.selectedOptions || []).map(opt => opt.value),
            programas: Array.from(document.getElementById('filtroPrograma')?.selectedOptions || []).map(opt => opt.value)
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
                this.aplicarFiltrosGuardados('filtroDesarrollador', filtros.desarrolladores);
                this.aplicarFiltrosGuardados('filtroPrioridad', filtros.prioridades);
                this.aplicarFiltrosGuardados('filtroPrograma', filtros.programas);

                // Reflejar el resumen y el badge de una sin esperar los 500ms
                // de abajo, para no mostrar "Sin filtros aplicados" de arranque
                this.actualizarTextoFiltrosActivos();

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
    },

    // Registra UNA función de búsqueda global que combina multi-select y chips
    // Debe llamarse una sola vez, después de inicializarTabla()
    inicializarFiltroGlobal: function() {
        const self = this;

        // Los índices de columna se leen desde self.COL (definido al principio
        // del objeto) para no desalinearse en silencio si se agrega una columna
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex, rowData) {
            const f = self.filtrosActuales;

            // --- Multi-select filter ---
            const filaSector        = data[self.COL.sector];
            const filaEstado        = data[self.COL.estado];
            const filaEtapa         = data[self.COL.etapa];
            const filaLider         = data[self.COL.lider];
            const filaDesarrollador = data[self.COL.desarrollador];
            const filaPrioridad     = data[self.COL.prioridad];

            if (f.sectores.length > 0       && !f.sectores.some(v => filaSector.includes(v)))                          return false;
            if (f.estados.length > 0        && !f.estados.some(v => filaEstado.includes(v)))                           return false;
            if (f.etapas.length > 0         && !f.etapas.some(v => filaEtapa.includes(v)))                             return false;
            if (f.lideres.length > 0        && !f.lideres.some(v => filaLider.includes(v)))                            return false;
            if (f.desarrolladores.length > 0 && !f.desarrolladores.some(v => filaDesarrollador && filaDesarrollador.includes(v))) return false;
            if (f.prioridades.length > 0    && !f.prioridades.some(v => filaPrioridad.includes(v)))                    return false;

            // Filtro de Programa: match exacto por ID (no por texto renderizado),
            // porque comparar por .includes() sobre el nombre da falsos positivos
            // por prefijo (ej: "Personal" matchearía "Gestión de Personal")
            if (f.programas.length > 0) {
                const idFila = rowData.IdPrograma ? String(rowData.IdPrograma) : '0';
                if (!f.programas.includes(idFila)) return false;
            }

            // --- Chip date filter (OR logic: row passes if it matches ANY active chip) ---
            if (self.activeChips.size > 0) {
                const hoy = new Date(); hoy.setHours(0, 0, 0, 0);

                // Inicio y fin del mes actual
                const inicioMesActual = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                const finMesActual    = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
                finMesActual.setHours(23, 59, 59, 999);

                // Inicio y fin del mes siguiente
                const inicioMesSig = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 1);
                const finMesSig    = new Date(hoy.getFullYear(), hoy.getMonth() + 2, 0);
                finMesSig.setHours(23, 59, 59, 999);


                const estadoRaw = (rowData.Estado || '').toLowerCase();

                const parseDate = (val) => {
                    if (!val || val === '1900-01-01') return null;
                    const d = new Date(val); d.setHours(0, 0, 0, 0);
                    return isNaN(d) ? null : d;
                };
                const fechaLimite = parseDate(rowData.FechaLimite);

                let chipMatch = false;
                for (const filtro of self.activeChips) {
                    if (filtro === 'vencen-mes'          && fechaLimite && fechaLimite >= inicioMesActual && fechaLimite <= finMesActual) { chipMatch = true; break; }
                    if (filtro === 'vencen-mes-siguiente' && fechaLimite && fechaLimite >= inicioMesSig    && fechaLimite <= finMesSig)    { chipMatch = true; break; }
                    if (filtro === 'atrasados'            && fechaLimite && fechaLimite < hoy
                        && estadoRaw !== 'finalizado' && estadoRaw !== 'cancelado' && estadoRaw !== 'terminado' && estadoRaw !== 'pausado') { chipMatch = true; break; }
                    if (filtro === 'proximos-meses'      && fechaLimite && fechaLimite >= inicioMesSig) { chipMatch = true; break; }
                }
                if (!chipMatch) return false;
            }

            return true;
        });
    },

    // Vincula los chips de fecha a la función de búsqueda global
    setupChipFilters: function() {
        document.querySelectorAll('.chip-fecha').forEach(chip => {
            chip.addEventListener('click', () => {
                const filtro = chip.dataset.filtro;
                const yaActivo = this.activeChips.has(filtro);

                // Limpiar todos los chips
                this.activeChips.clear();
                document.querySelectorAll('.chip-fecha').forEach(c => c.classList.remove('active'));

                // Si no estaba activo, activar el clickeado; si ya estaba, queda deseleccionado
                if (!yaActivo) {
                    this.activeChips.add(filtro);
                    chip.classList.add('active');
                }

                this.dataTable.draw();
            });
        });
    },

    // Panel colapsable de filtros: recuerda si estaba abierto o cerrado entre
    // navegaciones (clave propia en sessionStorage, separada de 'filtrosProyectos')
    // y sincroniza el chevron con el estado real del collapse de Bootstrap.
    setupPanelFiltros: function() {
        const panel = document.getElementById('panelFiltros');
        const header = document.getElementById('headerFiltros');
        const chevron = document.getElementById('chevronFiltros');
        if (!panel) return;

        // Restaurar estado abierto/cerrado antes de que Bootstrap inicialice el
        // collapse: agregar 'show' directamente evita el "salto" de la animación
        const abiertoGuardado = sessionStorage.getItem('panelFiltrosAbierto') === 'true';
        if (abiertoGuardado) {
            panel.classList.add('show');
            if (chevron) chevron.classList.add('abierto');
            if (header) header.setAttribute('aria-expanded', 'true');
        }

        // Una sola instancia reutilizable; toggle:false para que no vuelva a
        // alternar el estado que ya dejamos configurado arriba
        bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false });

        panel.addEventListener('shown.bs.collapse', () => {
            if (chevron) chevron.classList.add('abierto');
            if (header) header.setAttribute('aria-expanded', 'true');
            sessionStorage.setItem('panelFiltrosAbierto', 'true');
        });

        panel.addEventListener('hidden.bs.collapse', () => {
            if (chevron) chevron.classList.remove('abierto');
            if (header) header.setAttribute('aria-expanded', 'false');
            sessionStorage.setItem('panelFiltrosAbierto', 'false');
        });
    }

}; // ← Este es el cierre del objeto ProyectosManager

// Escapa HTML para insertar de forma segura texto ingresado por el usuario
// (ej. nombres de programa) dentro de celdas renderizadas con innerHTML
function escapeHtml(texto) {
    if (texto === null || texto === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(texto);
    return div.innerHTML;
}

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
