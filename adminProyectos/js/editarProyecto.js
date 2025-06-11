
$(document).ready(function() {
    // Validación de fechas: Fecha Límite debe ser posterior a Inicio Programado
    $('#InicioProgramado, #FechaLimite').on('change', function() {
        const inicioProgramado = $('#InicioProgramado').val();
        const fechaLimite = $('#FechaLimite').val();
        
        if (inicioProgramado && fechaLimite && fechaLimite < inicioProgramado) {
            alert('La Fecha Límite no puede ser anterior al Inicio Programado');
            $('#FechaLimite').val('');
        }
    });
    
    // Validación: Inicio Real y Finalización Real
    $('#InicioReal, #FinalizacionReal').on('change', function() {
        const inicioReal = $('#InicioReal').val();
        const finalizacionReal = $('#FinalizacionReal').val();
        
        if (inicioReal && finalizacionReal && finalizacionReal < inicioReal) {
            alert('La Finalización Real no puede ser anterior al Inicio Real');
            $('#FinalizacionReal').val('');
        }
    });
    
    // Actualizar estado si se selecciona "Finalizado" como etapa
    $('#EtapaProyecto').on('change', function() {
        if ($(this).val() === 'Finalizado') {
            $('#Estado').val('Terminado');
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
            const confirmacion = confirm('Tiene cambios sin guardar. ¿Está seguro que desea salir?');
            if (!confirmacion) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Advertir si cierra el navegador/pestaña
    $(window).on('beforeunload', function(e) {
        if (formModificado && !formularioEnviado) {
            const mensaje = 'Tiene cambios sin guardar. ¿Está seguro que desea salir?';
            e.returnValue = mensaje;
            return mensaje;
        }
    });
    
    // Validar antes de enviar el formulario
    $('form').on('submit', function(e) {
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
        
        if (!esValido) {
            e.preventDefault();
            // Scroll al primer campo con error
            $('html, body').animate({
                scrollTop: $('.is-invalid').first().offset().top - 100
            }, 500);
            return false;
        }
        
        // Mostrar confirmación antes de guardar
        const confirmacion = confirm('¿Está seguro que desea guardar los cambios en este proyecto?');
        if (!confirmacion) {
            e.preventDefault();
            return false;
        }
        
        // Marcar que el formulario se está enviando
        formularioEnviado = true;
        formModificado = false;
        
        // Mostrar indicador de carga
        const $submitBtn = $('button[type="submit"]');
        const textoOriginal = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Guardando...');
        
        // Agregar clase de loading al formulario
        $('form').addClass('loading');
        
        // Si por alguna razón el formulario tarda mucho, restaurar después de 30 segundos
        setTimeout(function() {
            $submitBtn.prop('disabled', false).html(textoOriginal);
            $('form').removeClass('loading');
            formularioEnviado = false;
        }, 30000);
        
        return true;
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
    
    // Función para mostrar notificaciones toast
    function mostrarNotificacion(mensaje, tipo = 'info') {
        // Crear contenedor para notificaciones si no existe
        if ($('#notificaciones-container').length === 0) {
            $('body').append('<div id="notificaciones-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>');
        }
        
        const iconos = {
            'success': 'check-circle',
            'error': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        
        const colores = {
            'success': 'success',
            'error': 'danger',
            'warning': 'warning',
            'info': 'info'
        };
        
        const notificacion = $(`
            <div class="alert alert-${colores[tipo]} alert-dismissible fade show" role="alert">
                <i class="fas fa-${iconos[tipo]} me-2"></i>
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('#notificaciones-container').append(notificacion);
        
        // Auto-cerrar después de 5 segundos
        setTimeout(function() {
            notificacion.alert('close');
        }, 5000);
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
    console.log('Script editarProyecto.js inicializado correctamente (versión simplificada)');
    
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