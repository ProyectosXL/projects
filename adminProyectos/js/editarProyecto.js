$(document).ready(function() {
    // Validación de fechas: Fecha Límite debe ser posterior a Inicio Programado
    $('#InicioProgramado, #FechaLimite').on('change', function() {
        const inicioProgramado = $('#InicioProgramado').val();
        const fechaLimite = $('#FechaLimite').val();
        
        if (inicioProgramado && fechaLimite && fechaLimite < inicioProgramado) {
            mostrarNotificacion('La Fecha Límite no puede ser anterior al Inicio Programado', 'warning');
            $('#FechaLimite').val('').focus();
        }
    });
    
    // Validación: Inicio Real y Finalización Real
    $('#InicioReal, #FinalizacionReal').on('change', function() {
        const inicioReal = $('#InicioReal').val();
        const finalizacionReal = $('#FinalizacionReal').val();
        
        if (inicioReal && finalizacionReal && finalizacionReal < inicioReal) {
            mostrarNotificacion('La Finalización Real no puede ser anterior al Inicio Real', 'warning');
            $('#FinalizacionReal').val('').focus();
        }
    });
    
    // Actualizar estado si se selecciona "Finalizado" como etapa
    $('#EtapaProyecto').on('change', function() {
        if ($(this).val() === 'Finalizado') {
            $('#Estado').val('Terminado');
            mostrarNotificacion('Estado actualizado automáticamente a "Terminado"', 'info');
        }
    });
    
    // Validación de comentario y autor
    $('#comentario, #autor').on('change input', function() {
        validarComentarioAutor();
    });
    
    // Validación de campos requeridos
    $('#Desarrollo').on('input', function() {
        validarCampoRequerido($(this), 'El nombre del proyecto es requerido');
    });
    
    // Validación de URL del requerimiento (NUEVO)
    $('#URLRequerimiento').on('input', function() {
        validarURL($(this));
    });
    
    // Función para validar URL (NUEVA)
    function validarURL($campo) {
        const url = $campo.val().trim();
        const fieldId = $campo.attr('id');
        
        // Si está vacío, no es error (campo opcional)
        if (!url) {
            $campo.removeClass('is-invalid is-valid');
            $('#' + fieldId + '-error-msg').remove();
            return true;
        }
        
        // Validar formato de URL
        try {
            new URL(url);
            // URL válida
            $campo.removeClass('is-invalid').addClass('is-valid');
            $('#' + fieldId + '-error-msg').remove();
            return true;
        } catch (e) {
            // URL inválida
            $campo.removeClass('is-valid').addClass('is-invalid');
            if ($('#' + fieldId + '-error-msg').length === 0) {
                $('<div id="' + fieldId + '-error-msg" class="invalid-feedback">Ingrese una URL válida (ejemplo: https://ejemplo.com)</div>')
                    .insertAfter($campo);
            }
            return false;
        }
    }
    
    // Función para validar que si hay comentario, debe haber autor
    function validarComentarioAutor() {
        const comentario = $('#comentario').val().trim();
        const autor = $('#autor').val();
        
        if (comentario && !autor) {
            $('#autor').addClass('is-invalid');
            if ($('#autor-error-msg').length === 0) {
                $('<div id="autor-error-msg" class="invalid-feedback">Debe seleccionar un responsable para el comentario</div>')
                    .insertAfter('#autor');
            }
            return false;
        } else {
            $('#autor').removeClass('is-invalid');
            $('#autor-error-msg').remove();
            return true;
        }
    }
    
    // Función para validar campos requeridos
    function validarCampoRequerido($campo, mensaje) {
        const valor = $campo.val().trim();
        const fieldId = $campo.attr('id');
        
        if (!valor) {
            $campo.addClass('is-invalid');
            if ($('#' + fieldId + '-error-msg').length === 0) {
                $('<div id="' + fieldId + '-error-msg" class="invalid-feedback">' + mensaje + '</div>')
                    .insertAfter($campo);
            }
            return false;
        } else {
            $campo.removeClass('is-invalid');
            $('#' + fieldId + '-error-msg').remove();
            return true;
        }
    }
    
    // Función para validar todas las fechas
    function validarFechas() {
        let esValido = true;
        
        // Validar Fecha Límite vs Inicio Programado
        const inicioProgramado = $('#InicioProgramado').val();
        const fechaLimite = $('#FechaLimite').val();
        
        if (inicioProgramado && fechaLimite && fechaLimite < inicioProgramado) {
            $('#FechaLimite').addClass('is-invalid');
            if ($('#FechaLimite-error-msg').length === 0) {
                $('<div id="FechaLimite-error-msg" class="invalid-feedback">La Fecha Límite no puede ser anterior al Inicio Programado</div>')
                    .insertAfter('#FechaLimite');
            }
            esValido = false;
        } else {
            $('#FechaLimite').removeClass('is-invalid');
            $('#FechaLimite-error-msg').remove();
        }
        
        // Validar Finalización Real vs Inicio Real
        const inicioReal = $('#InicioReal').val();
        const finalizacionReal = $('#FinalizacionReal').val();
        
        if (inicioReal && finalizacionReal && finalizacionReal < inicioReal) {
            $('#FinalizacionReal').addClass('is-invalid');
            if ($('#FinalizacionReal-error-msg').length === 0) {
                $('<div id="FinalizacionReal-error-msg" class="invalid-feedback">La Finalización Real no puede ser anterior al Inicio Real</div>')
                    .insertAfter('#FinalizacionReal');
            }
            esValido = false;
        } else {
            $('#FinalizacionReal').removeClass('is-invalid');
            $('#FinalizacionReal-error-msg').remove();
        }
        
        return esValido;
    }
    
    // Limpiar errores cuando el usuario cambie los valores
    $('input, select, textarea').on('change input', function() {
        if ($(this).hasClass('is-invalid')) {
            const fieldId = $(this).attr('id');
            $(this).removeClass('is-invalid');
            $('#' + fieldId + '-error-msg').remove();
        }
    });
    
    // Advertencia si el usuario intenta salir sin guardar
    let formModificado = false;
    let formularioEnviado = false;
    
    // Marcar formulario como modificado cuando cambie cualquier campo
    $('input, select, textarea').on('change input', function() {
        formModificado = true;
        // Agregar indicador visual
        if (!$('h1').hasClass('form-modified')) {
            $('h1').addClass('form-modified');
        }
    });
    
    // Advertir al usuario si intenta navegar sin guardar
    $('a[href]').on('click', function(e) {
        if (formModificado && !formularioEnviado) {
            e.preventDefault();
            const enlace = $(this).attr('href');
            
            mostrarConfirmacion(
                'Cambios sin guardar',
                'Tiene cambios sin guardar. ¿Está seguro que desea salir sin guardar?',
                'warning',
                () => {
                    // Si confirma, deshabilitar beforeunload y navegar
                    $(window).off('beforeunload');
                    formModificado = false;
                    window.location.href = enlace;
                },
                () => {
                    // Si cancela, no hacer nada
                    console.log('Navegación cancelada por el usuario');
                }
            );
        }
    });
    
    // Función para configurar el evento beforeunload
    function setupBeforeUnload() {
        $(window).on('beforeunload', function(e) {
            if (formModificado && !formularioEnviado) {
                const mensaje = 'Tiene cambios sin guardar. ¿Está seguro que desea salir?';
                e.returnValue = mensaje;
                return mensaje;
            }
        });
    }
    
    // Configurar el evento beforeunload inicialmente
    setupBeforeUnload();
    
    // Validar antes de enviar el formulario
    $('form').on('submit', function(e) {
        e.preventDefault(); // Prevenir envío inmediato
        
        let esValido = true;
        
        // Verificar campos requeridos
        if (!validarCampoRequerido($('#Desarrollo'), 'El nombre del proyecto es requerido')) {
            esValido = false;
        }
        
        // Verificar comentario y autor
        if (!validarComentarioAutor()) {
            esValido = false;
        }
        
        // Validación adicional de fechas
        if (!validarFechas()) {
            esValido = false;
        }
        
        // Validación de URL (NUEVA)
        if (!validarURL($('#URLRequerimiento'))) {
            esValido = false;
        }
        
        if (!esValido) {
            // Scroll al primer campo con error
            $('html, body').animate({
                scrollTop: $('.is-invalid').first().offset().top - 100
            }, 500);
            return false;
        }
        
        // Mostrar confirmación antes de guardar
        mostrarConfirmacion(
            'Confirmar cambios',
            '¿Está seguro que desea guardar los cambios en este proyecto?',
            'question',
            () => {
                // Si confirma, proceder con el guardado
                enviarFormulario();
            },
            () => {
                // Si cancela, no hacer nada
                console.log('Guardado cancelado por el usuario');
            }
        );
        
        return false; // Prevenir envío automático
    });
    
    // Función para validar selects (similar a la de Select2)
    function validarSelect($select, mensaje) {
        const valor = $select.val();
        const fieldId = $select.attr('id');
        
        if (!valor || valor === '') {
            $select.addClass('is-invalid');
            if ($('#' + fieldId + '-error-msg').length === 0) {
                $('<div id="' + fieldId + '-error-msg" class="invalid-feedback">' + mensaje + '</div>')
                    .insertAfter($select);
            }
            return false;
        } else {
            $select.removeClass('is-invalid');
            $('#' + fieldId + '-error-msg').remove();
            return true;
        }
    }
    
    // Función para enviar el formulario después de confirmación
    function enviarFormulario() {
        // Marcar que el formulario se está enviando
        formularioEnviado = true;
        formModificado = false;
        
        // Mostrar indicador de carga
        const $submitBtn = $('button[type="submit"]');
        const textoOriginal = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');
        
        // Agregar clase de loading al formulario
        $('form').addClass('loading');
        
        // Mostrar notificación de proceso
        mostrarNotificacion('Guardando cambios...', 'info');
        
        // Enviar formulario
        $('form')[0].submit();
        
        // Si por alguna razón el formulario tarda mucho, restaurar después de 30 segundos
        setTimeout(function() {
            $submitBtn.prop('disabled', false).html(textoOriginal);
            $('form').removeClass('loading');
            formularioEnviado = false;
        }, 30000);
    }
    
    // Función para mostrar confirmaciones modernas
    function mostrarConfirmacion(titulo, mensaje, tipo, onConfirm, onCancel) {
        // Crear modal de confirmación dinámico
        const modalId = 'confirmModal-' + Date.now();
        const iconos = {
            'question': 'fa-question-circle text-primary',
            'warning': 'fa-exclamation-triangle text-warning',
            'danger': 'fa-exclamation-circle text-danger',
            'info': 'fa-info-circle text-info'
        };
        
        const modalHTML = `
            <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas ${iconos[tipo] || iconos.question} me-2"></i>
                                ${titulo}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">${mensaje}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-primary" id="btn-confirmar-${modalId}">
                                <i class="fas fa-check me-1"></i> Confirmar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Agregar modal al DOM
        $('body').append(modalHTML);
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById(modalId));
        modal.show();
        
        // Eventos
        $(`#btn-confirmar-${modalId}`).on('click', function() {
            modal.hide();
            if (onConfirm) onConfirm();
        });
        
        $(`#${modalId}`).on('hidden.bs.modal', function() {
            // Limpiar modal del DOM
            $(this).remove();
            if (onCancel) onCancel();
        });
    }
    
    // Función para mostrar notificaciones toast mejoradas
    function mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
        // Crear contenedor para notificaciones si no existe
        if ($('#toast-container').length === 0) {
            $('body').append(`
                <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>
            `);
        }
        
        const iconos = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };
        
        const colores = {
            'success': 'text-bg-success',
            'error': 'text-bg-danger',
            'warning': 'text-bg-warning',
            'info': 'text-bg-info'
        };
        
        const toastId = 'toast-' + Date.now();
        
        const toastHTML = `
            <div id="${toastId}" class="toast ${colores[tipo]}" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header ${colores[tipo]}">
                    <i class="fas ${iconos[tipo]} me-2"></i>
                    <strong class="me-auto">
                        ${tipo.charAt(0).toUpperCase() + tipo.slice(1)}
                    </strong>
                    <small>ahora</small>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${mensaje}
                </div>
            </div>
        `;
        
        $('#toast-container').append(toastHTML);
        
        // Mostrar toast
        const toast = new bootstrap.Toast(document.getElementById(toastId), {
            delay: duracion
        });
        toast.show();
        
        // Limpiar después de ocultar
        document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
    
    // Mejorar la experiencia de usuario con los selects
    $('select.form-select').on('focus', function() {
        $(this).addClass('form-select-focused');
    }).on('blur', function() {
        $(this).removeClass('form-select-focused');
    });
    
    // Función para formatear fechas (utilidad)
    function formatearFecha(fecha) {
        if (!fecha) return '';
        const d = new Date(fecha);
        return d.toLocaleDateString('es-ES');
    }
    
    // Función para validar formato de fecha
    function esFechaValida(fecha) {
        if (!fecha) return true;
        const d = new Date(fecha);
        return d instanceof Date && !isNaN(d);
    }
    
    // Debugging: Log cuando se inicializa el script
    console.log('Script editarProyecto.js inicializado correctamente (con validación de URL)');
    
    // Función global para debug
    window.debugFormulario = function() {
        console.log('=== Estado del formulario ===');
        console.log('Form modificado:', formModificado);
        console.log('Form enviado:', formularioEnviado);
        console.log('Valores actuales:');
        $('input, select, textarea').each(function() {
            const id = $(this).attr('id');
            const value = $(this).val();
            if (id && value) {
                console.log(`${id}: ${value}`);
            }
        });
    };
});