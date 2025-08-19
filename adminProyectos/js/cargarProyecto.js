
/**
 * Funcionalidad JavaScript para el formulario de carga de proyectos
 * Con Select2 y prioridad automática
 */

$(document).ready(function() {
    // Inicializar Select2
    inicializarSelect2();
    
    // Cargar solicitantes desde la API
    cargarSolicitantes();
    cargarSectores();
    
    // Configurar eventos
    configurarEventos();
    
    console.log('Formulario de carga inicializado con prioridad automática');
});

/**
 * Inicializa Select2 en todos los selects (excepto prioridad que ahora es readonly)
 */
function inicializarSelect2() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: function() {
            return $(this).data('placeholder') || 'Seleccione una opción';
        },
        allowClear: true,
        language: {
            noResults: function() {
                return "No se encontraron resultados";
            },
            searching: function() {
                return "Buscando...";
            }
        }
    });

    // Configuración específica para el campo de solicitante con búsqueda
    $('#solicitante').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Buscar solicitante...',
        allowClear: true,
        minimumInputLength: 0,
        language: {
            noResults: function() {
                return "No se encontraron resultados";
            },
            searching: function() {
                return "Buscando...";
            }
        }
    });
}

/**
 * Carga los solicitantes desde la API
 */
function cargarSolicitantes() {
    console.log('Cargando solicitantes...');
    
    $.ajax({
        url: 'api/cargarProyecto.php',
        type: 'GET',
        data: { accion: 'obtenerSolicitantes' },
        dataType: 'json',
        beforeSend: function() {
            $('#solicitante').prop('disabled', true);
            // Destruir Select2 temporalmente para cambiar las opciones
            $('#solicitante').select2('destroy');
            $('#solicitante').html('<option value="">Cargando solicitantes...</option>');
            $('#solicitante').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Cargando...',
                allowClear: true
            });
        },
        success: function(response) {
            console.log('Respuesta recibida:', response);
            
            if (response.exito) {
                // Destruir Select2 para actualizar opciones
                $('#solicitante').select2('destroy');
                
                // Limpiar opciones actuales
                $('#solicitante').empty().append('<option value="">Seleccione solicitante</option>');
                
                // Agregar opciones desde la respuesta
                if (response.solicitantes && Array.isArray(response.solicitantes)) {
                    $.each(response.solicitantes, function(index, solicitante) {
                        $('#solicitante').append(new Option(
                            solicitante.APELLIDO_Y_NOMBRE,
                            solicitante.NOMBRE_FORMATEADO
                        ));
                    });
                    console.log('Solicitantes cargados:', response.solicitantes.length);
                } else {
                    console.warn('No se encontraron solicitantes en la respuesta');
                    $('#solicitante').append('<option value="">No hay solicitantes disponibles</option>');
                }
                                
                // Reinicializar Select2
                $('#solicitante').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: 'Buscar solicitante...',
                    allowClear: true,
                    minimumInputLength: 0
                });
                
            } else {
                mostrarError('Error al cargar solicitantes: ' + (response.mensaje || 'Error desconocido'));
                $('#solicitante').select2('destroy');
                $('#solicitante').html('<option value="">Error al cargar solicitantes</option>');
                $('#solicitante').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: 'Error...',
                    allowClear: true
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            
            mostrarError('Error al cargar solicitantes: ' + error);
            $('#solicitante').select2('destroy');
            $('#solicitante').html('<option value="">Error al cargar solicitantes</option>');
            $('#solicitante').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Error...',
                allowClear: true
            });
        },
        complete: function() {
            $('#solicitante').prop('disabled', false);
        }
    });
}

/**
 * Carga los sectores desde la API
 */
function cargarSectores() {
    console.log('Cargando sectores...');
    
    $.ajax({
        url: 'api/cargarProyecto.php',
        type: 'GET',
        data: { accion: 'obtenerSectores' },
        dataType: 'json',
        beforeSend: function() {
            $('#sector').prop('disabled', true);
            // Destruir Select2 temporalmente para cambiar las opciones
            $('#sector').select2('destroy');
            $('#sector').html('<option value="">Cargando sectores...</option>');
            $('#sector').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Cargando...',
                allowClear: true
            });
        },
        success: function(response) {
            console.log('Respuesta de sectores recibida:', response);
            
            if (response.exito) {
                // Destruir Select2 para actualizar opciones
                $('#sector').select2('destroy');
                
                // Limpiar opciones actuales
                $('#sector').empty().append('<option value="">Seleccione sector</option>');
                
                // Agregar opciones desde la respuesta
                if (response.sectores && Array.isArray(response.sectores)) {
                    $.each(response.sectores, function(index, sector) {
                        $('#sector').append(new Option(sector, sector));
                    });
                    console.log('Sectores cargados:', response.sectores.length);
                } else {
                    console.warn('No se encontraron sectores en la respuesta');
                    $('#sector').append('<option value="">No hay sectores disponibles</option>');
                }
                                
                // Reinicializar Select2
                $('#sector').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: 'Seleccione sector...',
                    allowClear: true
                });
                
            } else {
                mostrarError('Error al cargar sectores: ' + (response.mensaje || 'Error desconocido'));
                $('#sector').select2('destroy');
                $('#sector').html('<option value="">Error al cargar sectores</option>');
                $('#sector').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: 'Error...',
                    allowClear: true
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX en sectores:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            
            mostrarError('Error al cargar sectores: ' + error);
            $('#sector').select2('destroy');
            $('#sector').html('<option value="">Error al cargar sectores</option>');
            $('#sector').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Error...',
                allowClear: true
            });
        },
        complete: function() {
            $('#sector').prop('disabled', false);
        }
    });
}

/**
 * Configura todos los eventos del formulario
 */
function configurarEventos() {
    // Manejar el envío del formulario
    $('#formProyecto').on('submit', function(e) {
        e.preventDefault();
        mostrarConfirmacion();
    });
    
    // Botón de confirmación en el modal
    $('#btnConfirmarGuardar').on('click', guardarProyecto);
    
    // Validaciones en tiempo real
    $('#desarrollo').on('input', function() {
        validarCampoRequerido($(this), 'El nombre del proyecto es requerido');
    });
    
    $('#tipo').on('select2:select select2:unselect', function() {
        validarSelect2($(this), 'Debe seleccionar un tipo de presentación');
    });
    
    $('#solicitante').on('select2:select select2:unselect', function() {
        validarSelect2($(this), 'Debe seleccionar un solicitante');
    });
    
    $('#pais').on('select2:select select2:unselect', function() {
        validarSelect2($(this), 'Debe seleccionar un país');
    });
    
    $('#sector').on('select2:select select2:unselect', function() {
        validarSelect2($(this), 'Debe seleccionar un sector');
    });
    
    $('#funcionalidad').on('input', function() {
        validarCampoRequerido($(this), 'La funcionalidad es requerida');
    });
}

/**
 * Función para validar campos requeridos
 */
function validarCampoRequerido($campo, mensaje) {
    const valor = $campo.val() ? $campo.val().trim() : '';
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

/**
 * Función para validar campos Select2
 */
function validarSelect2($campo, mensaje) {
    const valor = $campo.val();
    const fieldId = $campo.attr('id');
    
    if (!valor || valor === '') {
        $campo.addClass('is-invalid');
        $campo.next('.select2').find('.select2-selection').addClass('is-invalid');
        
        if ($('#' + fieldId + '-error-msg').length === 0) {
            $('<div id="' + fieldId + '-error-msg" class="invalid-feedback">' + mensaje + '</div>')
                .insertAfter($campo.next('.select2'));
        }
        return false;
    } else {
        $campo.removeClass('is-invalid');
        $campo.next('.select2').find('.select2-selection').removeClass('is-invalid');
        $('#' + fieldId + '-error-msg').remove();
        return true;
    }
}

/**
 * Valida todo el formulario (sin validar prioridad ya que es automática)
 */
function validarFormulario() {
    let esValido = true;
    
    // Validar cada campo requerido
    if (!validarCampoRequerido($('#desarrollo'), 'El nombre del proyecto es requerido')) {
        esValido = false;
    }
    
    if (!validarSelect2($('#tipo'), 'Debe seleccionar un tipo de presentación')) {
        esValido = false;
    }
    
    if (!validarSelect2($('#solicitante'), 'Debe seleccionar un solicitante')) {
        esValido = false;
    }
    
    if (!validarSelect2($('#pais'), 'Debe seleccionar un país')) {
        esValido = false;
    }
    
    if (!validarSelect2($('#sector'), 'Debe seleccionar un sector')) {
        esValido = false;
    }
    
    if (!validarCampoRequerido($('#funcionalidad'), 'La funcionalidad es requerida')) {
        esValido = false;
    }
    
    return esValido;
}

/**
 * Muestra el modal de confirmación
 */
function mostrarConfirmacion() {
    // Validar formulario antes de mostrar confirmación
    if (!validarFormulario()) {
        // Scroll al primer campo con error
        $('html, body').animate({
            scrollTop: $('.is-invalid').first().offset().top - 100
        }, 500);
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
}

/**
 * Guarda el proyecto en la base de datos
 * Versión con prioridad automática
 */
function guardarProyecto() {
    // Validar el formulario una vez más
    if (!validarFormulario()) {
        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
        return;
    }
    
    // Recopilar datos del formulario (prioridad siempre será "Sin asignar")
    const datosProyecto = {
        tipo: $('#tipo').val(),
        desarrollo: $('#desarrollo').val(),
        solicitante: $('#solicitante').val(),
        prioridad: 'Sin asignar', // Valor fijo
        pais: $('#pais').val(),
        sector: $('#sector').val(),
        funcionalidad: $('#funcionalidad').val()
    };
    
    console.log('Enviando datos:', datosProyecto);
    
    // Enviar datos al servidor
    $.ajax({
        url: 'api/cargarProyecto.php',
        type: 'POST',
        data: {
            accion: 'guardarProyecto',
            ...datosProyecto
        },
        dataType: 'json',
        beforeSend: function() {
            // Mostrar indicador de carga
            $('#btnConfirmarGuardar')
                .prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...');
        },
        success: function(response) {
            console.log('Respuesta del servidor:', response);
            
            // Cerrar el modal
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            
            if (response.exito) {
                // Mostrar mensaje de éxito personalizado
                mostrarExito(response.mensaje || 'Proyecto guardado correctamente');
                
                // Log adicional si tenemos datos del proyecto
                if (response.nombreProyecto) {
                    console.log('✅ Proyecto creado:', response.nombreProyecto);
                    if (response.numeroProyecto) {
                        console.log('📝 Número de proyecto:', response.numeroProyecto);
                    }
                }
                
                // Limpiar formulario después de 3 segundos para que el usuario pueda leer el mensaje
                setTimeout(() => {
                    limpiarFormulario();
                }, 3000);
            } else {
                mostrarError(response.mensaje || 'Error al guardar el proyecto');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            
            // Cerrar el modal
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            
            // Mostrar error
            let mensajeError = 'Error al guardar el proyecto';
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                mensajeError = errorResponse.mensaje || mensajeError;
            } catch (e) {
                mensajeError += ': ' + error;
            }
            
            mostrarError(mensajeError);
        },
        complete: function() {
            // Quitar indicador de carga
            $('#btnConfirmarGuardar')
                .prop('disabled', false)
                .html('Guardar');
        }
    });
}

/**
 * Limpia el formulario después de un envío exitoso
 */
function limpiarFormulario() {
    // Limpiar todos los campos
    $('#formProyecto')[0].reset();
    
    // Limpiar Select2
    $('.select2').val(null).trigger('change');
    
    // Restaurar prioridad a "Sin asignar" (campo readonly)
    $('#prioridad').val('Sin asignar');
    
    // Remover clases de validación
    $('.form-control, .form-select').removeClass('is-invalid is-valid');
    $('.select2-selection').removeClass('is-invalid is-valid');
    $('.invalid-feedback').remove();
    
    // Recargar datos dinámicos
    cargarSolicitantes();
    cargarSectores(); // NUEVA LÍNEA
    
    console.log('Formulario limpiado');
}

/**
 * Muestra un mensaje de error
 */
function mostrarError(mensaje) {
    mostrarNotificacion(mensaje, 'danger');
}

/**
 * Muestra un mensaje de éxito
 */
function mostrarExito(mensaje) {
    mostrarNotificacion(mensaje, 'success');
}

/**
 * Función genérica para mostrar notificaciones
 */
function mostrarNotificacion(mensaje, tipo) {
    // Remover alertas anteriores
    $('.alert').remove();
    
    // Crear alerta
    const iconos = {
        'success': 'bi-check-circle-fill',
        'danger': 'bi-exclamation-triangle-fill',
        'warning': 'bi-exclamation-triangle-fill',
        'info': 'bi-info-circle-fill'
    };
    
    const alerta = $(`
        <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            <i class="${iconos[tipo] || iconos.info} me-2"></i>
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `);
    
    // Insertar al inicio del contenedor
    $('.container').prepend(alerta);
    
    // Auto cerrar después de 5 segundos
    setTimeout(() => {
        alerta.alert('close');
    }, 5000);
    
    // Scroll hacia arriba para ver la notificación
    $('html, body').animate({ scrollTop: 0 }, 300);
}