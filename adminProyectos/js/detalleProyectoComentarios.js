
/**
 * JavaScript para manejo de comentarios en detalle de proyecto
 * Controla la carga y visualización de comentarios
 */

const DetalleProyectoComentarios = {
    // ID del proyecto actual
    proyectoId: null,
    
    // Lista de comentarios
    comentarios: [],
    
    // Inicializa el módulo
    init: function(proyectoId) {
        this.proyectoId = proyectoId;
        console.log('Inicializando comentarios para proyecto:', proyectoId);
        this.cargarComentarios();
    },
    
    cargarComentarios: function() {
        console.log('Cargando comentarios del proyecto:', this.proyectoId);
        
        fetch(`api/detalleProyecto.php?id=${this.proyectoId}&accion=comentarios`)
            .then(response => {
                console.log('Respuesta de comentarios:', response);
                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Datos de comentarios recibidos:', data);
                if (data.exito) {
                    this.comentarios = data.comentarios || [];
                    this.actualizarVistaComentarios();
                    this.notificarCantidadComentarios();
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
            })
            .catch(error => {
                console.error('Error en cargarComentarios:', error);
                if (typeof DetalleProyectoUI !== 'undefined') {
                    DetalleProyectoUI.mostrarNotificacion('No se pudieron cargar los comentarios: ' + error.message, 'error');
                }
                
                // Mostrar mensaje de error en la interfaz
                this.comentarios = [];
                this.actualizarVistaComentarios();
                this.notificarCantidadComentarios();
            });
    },
    
    // Actualiza la vista de comentarios
    actualizarVistaComentarios: function() {
        const container = document.querySelector('.comentarios-container');
        
        if (!container) {
            console.warn('Contenedor de comentarios no encontrado');
            return;
        }
        
        // Si no hay comentarios, mostrar mensaje
        if (this.comentarios.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No hay comentarios registrados para este proyecto.
                </div>
            `;
            return;
        }
        
        // Limpiar contenedor
        container.innerHTML = '';
        
        // Agregar comentarios
        this.comentarios.forEach(comentario => {
            const comentarioHTML = `
                <div class="comment-item">
                    <div class="d-flex justify-content-between">
                        <span class="comment-author">${comentario.Autor || 'Usuario'}</span>
                        <span class="comment-date">${comentario.FechaCreacion || 'Fecha desconocida'}</span>
                    </div>
                    <div class="comment-text">
                        ${(comentario.Comentario || 'Sin comentario').replace(/\n/g, '<br>')}
                    </div>
                </div>
            `;
            container.innerHTML += comentarioHTML;
        });
        
        console.log('Vista de comentarios actualizada con', this.comentarios.length, 'comentarios');
    },
    
    // Notifica la cantidad de comentarios disponibles
    notificarCantidadComentarios: function() {
        // Crear y disparar un evento personalizado
        const evento = new CustomEvent('comentariosActualizados', {
            detail: {
                cantidad: this.comentarios.length
            }
        });
        
        document.dispatchEvent(evento);
        console.log('Evento comentariosActualizados disparado:', this.comentarios.length);
    }
};