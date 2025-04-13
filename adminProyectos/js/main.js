
/**
 * Script principal para la aplicación
 */

// Función para mostrar alertas estilizadas
function mostrarAlerta(mensaje, tipo = 'info') {
    // Crear elemento de alerta
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${tipo} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    
    // Icono según el tipo de alerta
    let icono = 'info-circle';
    if (tipo === 'success') icono = 'check-circle';
    if (tipo === 'warning') icono = 'exclamation-triangle';
    if (tipo === 'error' || tipo === 'danger') {
        icono = 'exclamation-circle';
        tipo = 'danger'; // Asegurar que usamos la clase correcta de Bootstrap
    }
    
    // Contenido de la alerta
    alertDiv.innerHTML = `
        <i class="fas fa-${icono} me-2"></i>
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Agregar al DOM
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-cerrar después de 5 segundos
    setTimeout(() => {
        const alertNode = document.querySelector('.alert');
        if (alertNode) {
            const bsAlert = new bootstrap.Alert(alertNode);
            bsAlert.close();
        }
    }, 5000);
}

// Cuando el documento esté listo
$(document).ready(function() {
    // Inicializar gestor de proyectos
    ProyectosManager.init();
    
    // Detectar errores en llamadas AJAX
    $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
        console.error("Error AJAX:", thrownError);
        mostrarAlerta('Ha ocurrido un error al comunicarse con el servidor', 'error');
    });
});