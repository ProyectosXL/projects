
/**
 * Funcionalidad JavaScript para el formulario de carga de proyectos
 */

// Almacenar los documentos adjuntos
const documentos = [];

$(document).ready(function() {
    // Inicializar Select2
    inicializarSelect2();
    
    // Cargar solicitantes desde la API
    cargarSolicitantes();
    
    // Configurar eventos
    configurarEventos();
});

/**
 * Inicializa los componentes Select2
 */
function inicializarSelect2() {
    // Configuración general para todos los selects
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Seleccione una opción',
        allowClear: true,
        dropdownParent: $('body') // Esto asegura que el dropdown se agregue al body
    });
    
    // Configuración especial para el select de solicitante con búsqueda
    $('#solicitante').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Buscar solicitante...',
        allowClear: true,
        minimumInputLength: 2,
        dropdownParent: $('body'), // Esto asegura que el dropdown se agregue al body
        language: {
            inputTooShort: function() {
                return "Ingrese al menos 2 caracteres para buscar";
            },
            noResults: function() {
                return "No se encontraron resultados";
            },
            searching: function() {
                return "Buscando...";
            }
        }
    });

    // Establecer z-index alto para los dropdowns de Select2
    $('.select2-dropdown').css('z-index', 9999);
}

/**
 * Carga los solicitantes desde la API
 */
function cargarSolicitantes() {
    $.ajax({
        url: 'api/cargarProyecto.php',
        type: 'GET',
        data: { accion: 'obtenerSolicitantes' },
        dataType: 'json',
        beforeSend: function() {
            $('#solicitante').prop('disabled', true);
        },
        success: function(response) {
            if (response.exito) {
                // Limpiar opciones actuales
                $('#solicitante').empty().append('<option value="">Seleccione solicitante</option>');
                
                // Agregar opciones desde la respuesta
                $.each(response.solicitantes, function(index, solicitante) {
                    // Usamos el nombre formateado como valor en lugar del número de legajo
                    $('#solicitante').append(new Option(
                        solicitante.APELLIDO_Y_NOMBRE,
                        solicitante.NOMBRE_FORMATEADO
                    ));
                });
                
                // Actualizar Select2
                $('#solicitante').trigger('change');
            } else {
                mostrarError('Error al cargar solicitantes: ' + response.mensaje);
            }
        },
        error: function(xhr, status, error) {
            mostrarError('Error al cargar solicitantes: ' + error);
            console.error('Error en la petición:', xhr.responseText);
        },
        complete: function() {
            $('#solicitante').prop('disabled', false);
        }
    });
}

/**
 * Configura todos los eventos del formulario
 */
function configurarEventos() {
    // Botón para agregar documento
    $('#btnAgregarDocumento').on('click', agregarDocumento);
    
    // Manejar el envío del formulario
    $('#formProyecto').on('submit', function(e) {
        e.preventDefault();
        mostrarConfirmacion();
    });
    
    // Botón de confirmación en el modal
    $('#btnConfirmarGuardar').on('click', guardarProyecto);
}

/**
 * Agrega un documento al listado
 */
function agregarDocumento() {
    const input = document.getElementById('documentoInput');
    
    if (!input.files || input.files.length === 0) {
        mostrarError('Por favor, seleccione un archivo');
        return;
    }
    
    const file = input.files[0];
    
    // Validar tipo de archivo
    const tiposPermitidos = [
        'application/pdf', 
        'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    if (!tiposPermitidos.includes(file.type)) {
        mostrarError('Tipo de archivo no permitido. Solo se aceptan PDF, Word y Excel.');
        return;
    }
    
    // Validar tamaño (5MB máximo)
    if (file.size > 5 * 1024 * 1024) {
        mostrarError('El archivo es demasiado grande. El tamaño máximo es 5MB.');
        return;
    }
    
    // Generar un ID único para este documento
    const docId = 'doc_' + Date.now();
    
    // Almacenar datos del documento
    documentos.push({
        id: docId,
        file: file,
        nombre: file.name,
        tipo: file.type,
        tamano: formatearTamano(file.size)
    });
    
    // Actualizar vista
    actualizarVistaDocumentos();
    
    // Limpiar input
    input.value = '';
}

/**
 * Actualiza la vista de documentos
 */
function actualizarVistaDocumentos() {
    const container = document.getElementById('documentosContainer');
    container.innerHTML = '';
    
    if (documentos.length === 0) {
        return;
    }
    
    documentos.forEach(doc => {
        // Determinar icono según tipo
        let iconoClase = 'bi bi-file-earmark';
        let iconoColor = '';
        
        if (doc.tipo === 'application/pdf') {
            iconoClase = 'bi bi-file-earmark-pdf-fill';
            iconoColor = 'pdf-icon';
        } else if (doc.tipo.includes('word')) {
            iconoClase = 'bi bi-file-earmark-word-fill';
            iconoColor = 'word-icon';
        } else if (doc.tipo.includes('excel')) {
            iconoClase = 'bi bi-file-earmark-excel-fill';
            iconoColor = 'excel-icon';
        }
        
        const docElement = document.createElement('div');
        docElement.className = 'documento-item';
        docElement.innerHTML = `
            <div class="documento-icon ${iconoColor}">
                <i class="${iconoClase}"></i>
            </div>
            <div class="documento-info">
                <p class="documento-nombre">${doc.nombre}</p>
                <p class="documento-tamano">${doc.tamano}</p>
            </div>
            <div class="documento-acciones">
                <button type="button" class="btn btn-outline-danger" onclick="eliminarDocumento('${doc.id}')">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        
        container.appendChild(docElement);
    });
}

/**
 * Elimina un documento del listado
 */
function eliminarDocumento(docId) {
    const index = documentos.findIndex(doc => doc.id === docId);
    if (index !== -1) {
        documentos.splice(index, 1);
        actualizarVistaDocumentos();
    }
}

/**
 * Muestra el modal de confirmación
 */
function mostrarConfirmacion() {
    // Cerrar todos los dropdowns de Select2 antes de mostrar el modal
    $('.select2-hidden-accessible').select2('close');
    
    // Mostrar el modal con un pequeño retraso para evitar conflictos
    setTimeout(function() {
        const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
        modal.show();
    }, 100);
}

/**
 * Guarda el proyecto en la base de datos
 */
function guardarProyecto() {
    // Validar el formulario
    const form = document.getElementById('formProyecto');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Crear FormData con los datos del formulario
    const formData = new FormData(form);
    
    // Agregar documentos
    documentos.forEach((doc, index) => {
        formData.append(`documento_${index}`, doc.file);
    });
    
    // Indicar cuántos documentos se envían
    formData.append('num_documentos', documentos.length);
    
    // Enviar datos al servidor
    $.ajax({
        url: 'api/cargarProyecto.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        beforeSend: function() {
            // Mostrar indicador de carga
            $('#formProyecto').addClass('loading');
            $('#btnConfirmarGuardar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...');
        },
        success: function(response) {
            // Cerrar el modal
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            
            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                
                if (data.exito) {
                    // Mostrar mensaje de éxito
                    mostrarExito(data.mensaje || 'Proyecto guardado correctamente');
                    
                    // Limpiar formulario y recargar después de 2 segundos
                    setTimeout(() => {
                        window.location.reload(); // Recargar toda la página
                    }, 2000);
                } else {
                    mostrarError(data.mensaje || 'Error al guardar el proyecto');
                }
            } catch (e) {
                console.error('Error al procesar la respuesta:', e);
                mostrarError('Error al procesar la respuesta del servidor');
            }
        },
        error: function(xhr, status, error) {
            // Cerrar el modal
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            
            // Mostrar error
            mostrarError('Error al guardar el proyecto: ' + error);
            console.error('Error en la petición:', xhr.responseText);
        },
        complete: function() {
            // Quitar indicador de carga
            $('#formProyecto').removeClass('loading');
            $('#btnConfirmarGuardar').prop('disabled', false).html('Guardar');
        }
    });
}

/**
 * Limpia el formulario después de un envío exitoso
 */
function limpiarFormulario() {
    // Restablecer select2
    $('.select2').val(null).trigger('change');
    
    // Limpiar textarea
    $('#funcionalidad').val('');
    
    // Limpiar documentos
    documentos.length = 0;
    actualizarVistaDocumentos();
    
    // Quitar alertas
    $('.alert').alert('close');
}

/**
 * Muestra un mensaje de error
 */
function mostrarError(mensaje) {
    // Remover alertas anteriores
    $('#formProyecto').find('.alert').remove();
    
    // Crear alerta
    const alerta = $('<div class="alert alert-danger alert-dismissible fade show" role="alert"></div>');
    alerta.html(`
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `);
    
    // Insertar al inicio del formulario
    $('#formProyecto').prepend(alerta);
    
    // Auto cerrar después de 5 segundos
    setTimeout(() => {
        alerta.alert('close');
    }, 5000);
}

/**
 * Muestra un mensaje de éxito
 */
function mostrarExito(mensaje) {
    // Remover alertas anteriores
    $('#formProyecto').find('.alert').remove();
    
    // Crear alerta
    const alerta = $('<div class="alert alert-success alert-dismissible fade show" role="alert"></div>');
    alerta.html(`
        <i class="bi bi-check-circle-fill me-2"></i>
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `);
    
    // Insertar al inicio del formulario
    $('#formProyecto').prepend(alerta);
    
    // Auto cerrar después de 5 segundos
    setTimeout(() => {
        alerta.alert('close');
    }, 5000);
}

/**
 * Formatea el tamaño de un archivo
 */
function formatearTamano(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}