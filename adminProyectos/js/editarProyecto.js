
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
    
    // Validar antes de enviar el formulario
    $('form').on('submit', function(e) {
        // Verificar comentario y autor
        if (!validarComentarioAutor()) {
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    
    // Función para validar que si hay comentario, debe haber autor
    function validarComentarioAutor() {
        const comentario = $('#comentario').val().trim();
        const autor = $('#autor').val();
        
        if (comentario && !autor) {
            $('#autor').addClass('is-invalid');
            // Si no existe ya un mensaje de error, lo añadimos
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
});