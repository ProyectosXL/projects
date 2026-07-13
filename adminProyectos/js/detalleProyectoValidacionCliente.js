
/**
 * JavaScript para el bloque de validación del cliente (etapa "Validación cliente")
 * Muestra el bloque de aprobación/rechazo y gestiona las acciones del cliente
 */

const DetalleProyectoValidacionCliente = {
    proyectoId: null,
    proyectoData: null,
    eventosConectados: false,

    // Actualiza el bloque según los datos del proyecto
    actualizar: function(proyectoData) {
        this.proyectoData = proyectoData;
        this.proyectoId = proyectoData.ID;

        const bloque = document.getElementById('bloqueValidacionCliente');
        if (!bloque) return;

        if (proyectoData.EtapaProyecto === 'Validación cliente') {
            bloque.classList.remove('d-none');
            this.actualizarTextoVencimiento();
            this.wireEventos();
        } else {
            bloque.classList.add('d-none');
        }
    },

    // Muestra la fecha de vencimiento de la ventana de aceptación tácita
    actualizarTextoVencimiento: function() {
        const texto = document.getElementById('textoVentanaAceptacion');
        if (!texto) return;

        const fechaEntrega = this.proyectoData.FechaEntregaCliente ? new Date(this.proyectoData.FechaEntregaCliente) : null;

        if (!fechaEntrega || isNaN(fechaEntrega.getTime())) {
            texto.textContent = '';
            return;
        }

        const fechaVencimiento = new Date(fechaEntrega);
        fechaVencimiento.setDate(fechaVencimiento.getDate() + DIAS_VENTANA_ACEPTACION);

        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        const vencimientoSinHora = new Date(fechaVencimiento);
        vencimientoSinHora.setHours(0, 0, 0, 0);
        const diasRestantes = Math.round((vencimientoSinHora - hoy) / (1000 * 60 * 60 * 24));

        let textoDiasRestantes;
        if (diasRestantes > 0) {
            textoDiasRestantes = `Quedan ${diasRestantes} día${diasRestantes === 1 ? '' : 's'} para la aceptación tácita.`;
        } else if (diasRestantes === 0) {
            textoDiasRestantes = 'Hoy vence el plazo para la aceptación tácita.';
        } else {
            textoDiasRestantes = 'El plazo para la aceptación tácita ya venció.';
        }

        texto.textContent = `Entregado al cliente el ${fechaEntrega.toLocaleDateString('es-AR')}. Ventana de aceptación vence el ${fechaVencimiento.toLocaleDateString('es-AR')}. ${textoDiasRestantes}`;
    },

    // Conecta los eventos de los botones (una sola vez, los elementos son estáticos en el DOM)
    wireEventos: function() {
        if (this.eventosConectados || typeof usuarioLogueado === 'undefined' || !usuarioLogueado) return;
        this.eventosConectados = true;

        const self = this;

        $('#btnValidarAprobar').on('click', function() {
            self.confirmarAccion('aprobar');
        });

        $('#btnRequiereCorrecciones').on('click', function() {
            $('#textoCorrecciones').val('');
            new bootstrap.Modal(document.getElementById('modalCorrecciones')).show();
        });

        $('#btnConfirmarCorrecciones').on('click', function() {
            const comentario = $('#textoCorrecciones').val();
            bootstrap.Modal.getInstance(document.getElementById('modalCorrecciones')).hide();
            self.confirmarAccion('rechazar', comentario);
        });
    },

    // Envía la acción de aprobación/rechazo al servidor
    confirmarAccion: function(accion, comentario) {
        $.ajax({
            url: 'api/actualizarEtapaCliente.php',
            type: 'POST',
            data: {
                idProyecto: this.proyectoId,
                accion: accion,
                comentario: comentario || ''
            },
            dataType: 'json',
            success: function(response) {
                if (response.exito) {
                    DetalleProyectoUI.mostrarNotificacion(response.mensaje, 'success');

                    if (typeof DetalleProyectoPestanas !== 'undefined') {
                        DetalleProyectoPestanas.cargarDatosProyecto();
                    }
                    if (typeof DetalleProyectoComentarios !== 'undefined') {
                        DetalleProyectoComentarios.cargarComentarios();
                    }
                } else {
                    DetalleProyectoUI.mostrarNotificacion(response.error || 'No se pudo completar la acción', 'error');
                }
            },
            error: function(xhr) {
                const mensaje = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error al comunicarse con el servidor';
                DetalleProyectoUI.mostrarNotificacion(mensaje, 'error');
            }
        });
    }
};
