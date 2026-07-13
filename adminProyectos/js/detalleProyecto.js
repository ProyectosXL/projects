
/**
 * JavaScript principal para la página de detalle del proyecto
 * Maneja la inicialización y la coordinación entre módulos
 */

// Objeto principal para gestionar la página de detalle
const DetalleProyecto = {
    // ID del proyecto actual
    proyectoId: null,
    
    // Datos del proyecto
    proyectoData: null,
    
    // Inicializa el controlador
    init: function() {
        // Obtener el ID del proyecto de la URL
        const urlParams = new URLSearchParams(window.location.search);
        this.proyectoId = urlParams.get('id');
        
        if (!this.proyectoId) {
            console.error('No se proporcionó un ID de proyecto');
            return;
        }
        
        // Configurar la navegación de pestañas
        this.setupTabNavigation();
        
        // Inicializar módulos
        DetalleProyectoPestanas.init(this.proyectoId);
        DetalleProyectoDocumentos.init(this.proyectoId);
        DetalleProyectoComentarios.init(this.proyectoId);
        
        // Establecer el dispatcher de eventos personalizado
        this.setupEventDispatcher();
        
        // Auto-cerrar alertas después de 5 segundos (solo mensajes flash, no bloques persistentes como la validación del cliente)
        setTimeout(function() {
            document.querySelectorAll('#alertContainer .alert').forEach(alert => {
                if (alert && bootstrap) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
    },
    
    // Configura la navegación entre pestañas
    setupTabNavigation: function() {
        // Si hay hash en la URL, activar la pestaña correspondiente
        if (window.location.hash) {
            const hash = window.location.hash;
            if (hash === '#history') {
                new bootstrap.Tab(document.querySelector('#history-tab')).show();
            } else if (hash === '#files') {
                new bootstrap.Tab(document.querySelector('#files-tab')).show();
            }
        }
        
        // Cambiar hash al cambiar de pestaña
        document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tabEl => {
            tabEl.addEventListener('shown.bs.tab', function (event) {
                const id = event.target.getAttribute('aria-controls');
                window.location.hash = id;
            });
        });
    },
    
    // Configura el dispatcher de eventos personalizado para comunicación entre módulos
    setupEventDispatcher: function() {
        // Eventos para actualizar los contadores de pestaña
        document.addEventListener('comentariosActualizados', (e) => {
            this.actualizarContadorComentarios(e.detail.cantidad);
        });
        
        document.addEventListener('documentosActualizados', (e) => {
            this.actualizarContadorDocumentos(e.detail.cantidad);
        });
    },
    
    // Actualiza el contador de comentarios en la pestaña
    actualizarContadorComentarios: function(cantidad) {
        const contadorElement = document.getElementById('comentariosCount');
        if (!contadorElement) return;
        
        if (cantidad > 0) {
            contadorElement.textContent = cantidad;
            contadorElement.classList.remove('d-none');
        } else {
            contadorElement.classList.add('d-none');
        }
    },
    
    // Actualiza el contador de documentos en la pestaña
    actualizarContadorDocumentos: function(cantidad) {
        const contadorElement = document.getElementById('documentosCount');
        if (!contadorElement) return;
        
        if (cantidad > 0) {
            contadorElement.textContent = cantidad;
            contadorElement.classList.remove('d-none');
        } else {
            contadorElement.classList.add('d-none');
        }
    }
};

// Inicializar cuando el documento esté listo
document.addEventListener('DOMContentLoaded', function() {
    DetalleProyecto.init();
});