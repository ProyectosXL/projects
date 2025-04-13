
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
    
    // Confirmar antes de enviar el formulario
    $('form').on('submit', function(e) {
        // Validar que al menos se haya modificado un campo
        // Aquí se podría agregar esa lógica si es necesario
        
        // Si se agrega un comentario, verificar que se seleccionó un autor
        const comentario = $('#comentario').val().trim();
        const autor = $('#autor').val();
        
        if (comentario && !autor) {
            e.preventDefault();
            alert('Debe seleccionar un responsable para el comentario');
            return false;
        }
        
        return true;
    });
});