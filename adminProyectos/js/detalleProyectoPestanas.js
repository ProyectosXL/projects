/**
 * JavaScript para manejo de pestañas en detalle de proyecto
 * Controla la carga de datos en las diferentes pestañas
 */

const DetalleProyectoPestanas = {
    // ID del proyecto actual
    proyectoId: null,
    
    // Datos del proyecto
    proyectoData: null,
    
    // Inicializa el módulo
    init: function(proyectoId) {
        this.proyectoId = proyectoId;
        console.log('Inicializando pestañas para proyecto:', proyectoId);
        this.cargarDatosProyecto();
    },
    
    // Carga los datos del proyecto desde la API
    cargarDatosProyecto: function() {
        console.log('Cargando datos del proyecto:', this.proyectoId);
        
        fetch(`api/detalleProyecto.php?id=${this.proyectoId}&accion=obtener`)
            .then(response => {
                console.log('Respuesta recibida:', response);
                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Datos recibidos:', data);
                if (data.exito) {
                    this.proyectoData = data.proyecto;
                    this.actualizarInterfaz();
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
            })
            .catch(error => {
                console.error('Error en cargarDatosProyecto:', error);
                if (typeof DetalleProyectoUI !== 'undefined') {
                    DetalleProyectoUI.mostrarNotificacion('No se pudieron cargar los datos del proyecto: ' + error.message, 'error');
                } else {
                    // Fallback si DetalleProyectoUI no está disponible
                    alert('Error al cargar datos del proyecto: ' + error.message);
                }
            });
    },
    
    // Actualiza la interfaz con los datos cargados
    actualizarInterfaz: function() {
        if (!this.proyectoData) {
            console.error('No hay datos del proyecto para mostrar');
            return;
        }
        
        console.log('Actualizando interfaz con datos:', this.proyectoData);
        
        // Actualizar información general
        this.actualizarElemento('proyectoId', this.proyectoData.ID);
        this.actualizarElemento('proyectoTitulo', this.proyectoData.Desarrollo);
        
        // Actualizar estado
        const estadoElement = document.getElementById('proyectoEstado');
        if (estadoElement) {
            estadoElement.textContent = this.proyectoData.Estado || 'No especificado';
            estadoElement.className = `badge estado-${(this.proyectoData.Estado || 'pendiente').toLowerCase()}`;
        }
        
        // Actualizar etapa
        this.actualizarElemento('proyectoEtapa', this.proyectoData.EtapaProyecto || 'No especificado');
        
        // Actualizar prioridad
        const prioridadElement = document.getElementById('proyectoPrioridad');
        if (prioridadElement) {
            prioridadElement.textContent = this.proyectoData.Prioridad || 'No especificado';
            prioridadElement.className = `prioridad-badge ${this.proyectoData.clasePrioridad || ''}`;
        }
        
        // Actualizar progreso circular
        this.actualizarProgreso();
        
        // Actualizar campos de información
        this.actualizarTablaInformacion('detallesGenerales', {
            'Fecha de Creación': this.proyectoData.FechaCreacion || 'No especificado',
            'Tipo de Presentación': this.proyectoData.TipoPresentacion || 'No especificado',
            'Solicitante': this.proyectoData.Solicitante || 'No especificado',
            'Sector': this.proyectoData.Sector || 'No especificado',
            'País': this.proyectoData.Pais || 'No especificado',
            'Líder de Proyecto': this.proyectoData.LiderProyecto || 'No especificado',
            'Desarrollador': this.proyectoData.Desarrollador || 'No especificado',
            'URL Requerimiento': this.proyectoData.URLRequerimiento ? 
                `<a href="${this.proyectoData.URLRequerimiento}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-external-link-alt me-1"></i> Ver Requerimiento
                 </a>` : 'No especificado'
        });
        
        this.actualizarTablaInformacion('detallesCronograma', {
            'Inicio Programado': this.proyectoData.InicioProgramado || 'No especificado',
            'Fecha Límite': this.proyectoData.FechaLimite || 'No especificado',
            'Inicio Real': this.proyectoData.InicioReal || 'No especificado',
            'Finalización Real': this.proyectoData.FinalizacionReal || 'En curso',
            'Etapa Actual': this.proyectoData.EtapaProyecto || 'No especificado'
        });
        
        // Actualizar descripción
        this.actualizarElemento('proyectoDescripcion', 
            this.proyectoData.Funcionalidad ? 
            this.proyectoData.Funcionalidad.replace(/\n/g, '<br>') : 
            'Sin descripción', true);
        
        console.log('Interfaz actualizada correctamente');
    },
    
    // Función auxiliar para actualizar elementos
    actualizarElemento: function(id, contenido, esHTML = false) {
        const elemento = document.getElementById(id);
        if (elemento) {
            if (esHTML) {
                elemento.innerHTML = contenido;
            } else {
                elemento.textContent = contenido;
            }
        } else {
            console.warn(`Elemento con ID '${id}' no encontrado`);
        }
    },
    
    // Actualizar progreso circular
    actualizarProgreso: function() {
        const porcentaje = parseFloat(this.proyectoData.PorcentajeAvance || 0) * 100;
        const progresoElement = document.getElementById('proyectoProgreso');
        const progresoTexto = document.getElementById('proyectoProgresoTexto');
        
        if (progresoElement && progresoTexto) {
            let color = '#6c757d'; // Gris por defecto
            
            if (porcentaje >= 85) {
                color = '#4caf50'; // Verde
            } else if (porcentaje >= 60) {
                color = '#00bcd4'; // Azul claro
            } else if (porcentaje >= 30) {
                color = '#2196f3'; // Azul
            } else if (porcentaje >= 15) {
                color = '#ff9800'; // Naranja
            } else if (porcentaje > 0) {
                color = '#ff5722'; // Rojo
            }
            
            progresoElement.style.setProperty('--percentage', `${porcentaje}%`);
            progresoElement.style.setProperty('--color', color);
            progresoTexto.textContent = `${Math.round(porcentaje)}%`;
        }
    },
    
    // Actualiza una tabla de información
    actualizarTablaInformacion: function(tableId, datos) {
        const tabla = document.getElementById(tableId);
        if (!tabla) {
            console.warn(`Tabla con ID '${tableId}' no encontrada`);
            return;
        }
        
        let html = '';
        for (const [clave, valor] of Object.entries(datos)) {
            html += `
                <tr>
                    <th style="width: 40%;">${clave}</th>
                    <td>${valor}</td>
                </tr>
            `;
        }
        
        tabla.innerHTML = html;
    }
};