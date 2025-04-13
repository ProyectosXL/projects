
/**
 * JavaScript para utilidades de UI en detalle de proyecto
 * Proporciona funciones comunes de interfaz de usuario
 */

const DetalleProyectoUI = {
    // Muestra una notificación
    mostrarNotificacion: function(mensaje, tipo = 'info') {
        // Crear contenedor para notificaciones si no existe
        let notifContainer = document.getElementById('notificacionesContainer');
        
        if (!notifContainer) {
            notifContainer = document.createElement('div');
            notifContainer.id = 'notificacionesContainer';
            notifContainer.style.position = 'fixed';
            notifContainer.style.top = '20px';
            notifContainer.style.right = '20px';
            notifContainer.style.zIndex = '9999';
            document.body.appendChild(notifContainer);
        }
        
        // Crear notificación
        const notif = document.createElement('div');
        notif.className = `toast align-items-center text-white bg-${tipo} border-0`;
        notif.setAttribute('role', 'alert');
        notif.setAttribute('aria-live', 'assertive');
        notif.setAttribute('aria-atomic', 'true');
        
        // Iconos según tipo
        let icono = 'info-circle';
        if (tipo === 'success') icono = 'check-circle';
        if (tipo === 'warning') icono = 'exclamation-triangle';
        if (tipo === 'danger' || tipo === 'error') {
            icono = 'exclamation-circle';
            tipo = 'danger';
        }
        
        notif.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${icono} me-2"></i> ${mensaje}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
        `;
        
        // Agregar al contenedor
        notifContainer.appendChild(notif);
        
        // Mostrar notificación
        const bsToast = new bootstrap.Toast(notif, {
            delay: 5000,
            autohide: true
        });
        bsToast.show();
        
        // Eliminar después de ocultarse
        notif.addEventListener('hidden.bs.toast', () => {
            notifContainer.removeChild(notif);
        });
    },
    
    // Formatea el tamaño del archivo
    formatearTamano: function(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
};