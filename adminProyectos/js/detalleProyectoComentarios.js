
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
        this.cargarComentarios();
    },
    
    cargarComentarios: function() {
        fetch(`api/detalleProyecto.php?id=${this.proyectoId}&accion=comentarios`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Error al cargar comentarios: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.exito) {
                    this.comentarios = data.comentarios;
                    this.actualizarVistaComentarios();
                    
                    // Disparar evento para actualizar el contador
                    this.notificarCantidadComentarios();
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
            })
            .catch(error => {
                console.error('Error en cargarComentarios:', error);
                DetalleProyectoUI.mostrarNotificacion('No se pudieron cargar los comentarios: ' + error.message, 'error');
                
                // SOLUCIÓN TEMPORAL: Mostrar comentarios de muestra
                this.comentarios = [
                    {
                        Id: 1,
                        Autor: "Usuario Ejemplo",
                        Comentario: "Este es un comentario de ejemplo para mostrar mientras se implementa la API.",
                        FechaCreacion: new Date().toISOString().split('T')[0]
                    }
                ];
                this.actualizarVistaComentarios();
                this.notificarCantidadComentarios();
            });
    },
    
    // Actualiza la vista de comentarios
    actualizarVistaComentarios: function() {
        const container = document.querySelector('.comentarios-container');
        
        if (!container) return;
        
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
            container.innerHTML += `
                <div class="comment-item">
                    <div class="d-flex justify-content-between">
                        <span class="comment-author">${comentario.Autor}</span>
                        <span class="comment-date">${comentario.FechaCreacion}</span>
                    </div>
                    <div class="comment-text">
                        ${comentario.Comentario.replace(/\n/g, '<br>')}
                    </div>
                </div>
            `;
        });
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
    }
};