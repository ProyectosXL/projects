
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
        this.cargarDatosProyecto();
    },
    
    // Carga los datos del proyecto desde la API
    cargarDatosProyecto: function() {
        fetch(`api/detalleProyecto.php?id=${this.proyectoId}&accion=obtener`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Error al cargar datos: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.exito) {
                    this.proyectoData = data.proyecto;
                    this.actualizarInterfaz();
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                DetalleProyectoUI.mostrarNotificacion('No se pudieron cargar los datos del proyecto', 'error');
            });
    },
    
    // Actualiza la interfaz con los datos cargados
    actualizarInterfaz: function() {
        if (!this.proyectoData) return;
        
        // Actualizar información general
        document.getElementById('proyectoId').textContent = this.proyectoData.ID;
        document.getElementById('proyectoTitulo').textContent = this.proyectoData.Desarrollo;
        
        // Actualizar estado
        const estadoElement = document.getElementById('proyectoEstado');
        estadoElement.textContent = this.proyectoData.Estado || 'No especificado';
        estadoElement.className = `badge estado-${(this.proyectoData.Estado || 'pendiente').toLowerCase()}`;
        
        // Actualizar etapa
        document.getElementById('proyectoEtapa').textContent = this.proyectoData.EtapaProyecto || 'No especificado';
        
        // Actualizar prioridad
        const prioridadElement = document.getElementById('proyectoPrioridad');
        prioridadElement.textContent = this.proyectoData.Prioridad || 'No especificado';
        prioridadElement.className = `prioridad-badge ${this.proyectoData.clasePrioridad}`;
        
        // Actualizar progreso circular
        const porcentaje = parseFloat(this.proyectoData.PorcentajeAvance) * 100;
        const progresoElement = document.getElementById('proyectoProgreso');
        const progresoTexto = document.getElementById('proyectoProgresoTexto');
        
        let color = '#ff5722';
        if (porcentaje >= 85) {
            color = '#4caf50';
        } else if (porcentaje >= 60) {
            color = '#00bcd4';
        } else if (porcentaje >= 30) {
            color = '#2196f3';
        } else if (porcentaje >= 15) {
            color = '#ff9800';
        }
        
        progresoElement.style.setProperty('--percentage', `${porcentaje}%`);
        progresoElement.style.setProperty('--color', color);
        progresoTexto.textContent = `${Math.round(porcentaje)}%`;
        
        // Actualizar campos de información
        this.actualizarTablaInformacion('detallesGenerales', {
            'Fecha de Creación': this.proyectoData.FechaCreacion || 'No especificado',
            'Tipo de Presentación': this.proyectoData.TipoPresentacion || 'No especificado',
            'Solicitante': this.proyectoData.Solicitante || 'No especificado',
            'Sector': this.proyectoData.Sector || 'No especificado',
            'País': this.proyectoData.Pais || 'No especificado',
            'Líder de Proyecto': this.proyectoData.LiderProyecto || 'No especificado',
            'Desarrollador': this.proyectoData.Desarrollador || 'No especificado'
        });
        
        this.actualizarTablaInformacion('detallesCronograma', {
            'Inicio Programado': this.proyectoData.InicioProgramado || 'No especificado',
            'Fecha Límite': this.proyectoData.FechaLimite || 'No especificado',
            'Inicio Real': this.proyectoData.InicioReal || 'No especificado',
            'Finalización Real': this.proyectoData.FinalizacionReal || 'En curso',
            'Etapa Actual': this.proyectoData.EtapaProyecto || 'No especificado'
        });
        
        // Actualizar descripción
        document.getElementById('proyectoDescripcion').innerHTML = 
            this.proyectoData.Funcionalidad ? 
            this.proyectoData.Funcionalidad.replace(/\n/g, '<br>') : 
            'Sin descripción';
    },
    
    // Actualiza una tabla de información
    actualizarTablaInformacion: function(tableId, datos) {
        const tabla = document.getElementById(tableId);
        if (!tabla) return;
        
        let html = '';
        for (const [clave, valor] of Object.entries(datos)) {
            html += `
                <tr>
                    <th>${clave}</th>
                    <td>${valor}</td>
                </tr>
            `;
        }
        
        tabla.innerHTML = html;
    }
};