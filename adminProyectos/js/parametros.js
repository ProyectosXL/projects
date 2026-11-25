
/**
 * Parámetros › Personas (alta, edición, switches de rol y activo)
 * Reutiliza mostrarAlerta() y escapeHtml() definidas en js/proyectos.js
 *
 * Las validaciones de acá son solo para avisar rápido: el servidor
 * (api/parametros.php + class/persona.php) valida todo de nuevo.
 */

const ParametrosManager = {
    dataTable: null,
    modalPersona: null,

    // Textos para los mensajes de los switches
    nombresCampo: {
        EsSolicitante: 'Solicitante',
        EsLider: 'Líder',
        EsDesarrollador: 'Desarrollador',
        Activo: 'Activo'
    },

    init: function() {
        this.registrarFiltroInactivos();
        this.inicializarTabla();
        this.setupEventListeners();

        const modalEl = document.getElementById('modalPersona');
        if (modalEl) {
            this.modalPersona = new bootstrap.Modal(modalEl);
        }
    },

    /**
     * Filtro "Mostrar inactivos": por defecto se ven solo los activos
     */
    registrarFiltroInactivos: function() {
        $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex, rowData) {
            if (settings.nTable.id !== 'tablaPersonas') return true;
            if ($('#filtroMostrarInactivos').is(':checked')) return true;
            return rowData && rowData.Activo == 1;
        });
    },

    renderSwitch: function(campo) {
        return function(data, type, row) {
            if (type !== 'display') return data;
            const checked = data == 1 ? 'checked' : '';
            const titulo = ParametrosManager.nombresCampo[campo];
            return `<div class="form-check form-switch d-flex justify-content-center mb-0">
                        <input class="form-check-input switch-persona" type="checkbox" role="switch"
                               data-id="${row.ID}" data-campo="${campo}" ${checked}
                               title="${titulo}" aria-label="${titulo}">
                    </div>`;
        };
    },

    inicializarTabla: function() {
        this.dataTable = $('#tablaPersonas').DataTable({
            ajax: {
                url: 'api/parametros.php?accion=listar',
                dataSrc: 'data',
                error: function(xhr, textStatus, error) {
                    console.error('Error en la solicitud AJAX:', textStatus, error);
                    let mensaje = 'Error al cargar las personas. Consulte la consola para más detalles.';
                    try {
                        const respuesta = JSON.parse(xhr.responseText);
                        if (respuesta.mensaje) mensaje = escapeHtml(respuesta.mensaje);
                    } catch (e) { /* respuesta no JSON */ }
                    mostrarAlerta(mensaje, 'error');
                }
            },
            columns: [
                {
                    data: 'Nombre',
                    render: function(data, type, row) {
                        if (type !== 'display') return data || '';
                        let html = escapeHtml(data);
                        if (row.Activo != 1) {
                            html += ' <span class="badge bg-secondary ms-1">Inactivo</span>';
                        }
                        return html;
                    }
                },
                {
                    data: 'Mail',
                    render: function(data, type, row) {
                        if (type !== 'display') return data || '';
                        if (data) return escapeHtml(data);
                        // La etiqueta se muestra solo donde el mail hace falta (Solicitante o Líder)
                        if (row.EsSolicitante == 1 || row.EsLider == 1) {
                            return '<span class="badge bg-warning text-dark" title="Solicitantes y Líderes reciben avisos automáticos: cargale un mail con Editar"><i class="fas fa-exclamation-triangle me-1"></i>Sin mail</span>';
                        }
                        return '<span class="text-muted">-</span>';
                    }
                },
                { data: 'EsSolicitante', className: 'text-center', render: this.renderSwitch('EsSolicitante') },
                { data: 'EsLider', className: 'text-center', render: this.renderSwitch('EsLider') },
                { data: 'EsDesarrollador', className: 'text-center', render: this.renderSwitch('EsDesarrollador') },
                { data: 'Activo', className: 'text-center', render: this.renderSwitch('Activo') },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        return `<button class="btn btn-sm btn-outline-primary btn-editar-persona" data-id="${row.ID}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>`;
                    }
                }
            ],
            order: [[0, 'asc']],
            responsive: true,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            dom: '<"top"lf>rt<"bottom"ip><"clear">',
            createdRow: function(row, data) {
                if (data.Activo != 1) {
                    $(row).addClass('text-muted');
                }
            }
        });
    },

    setupEventListeners: function() {
        $('#btnNuevaPersona').on('click', () => this.abrirModalNuevo());
        $('#btnGuardarPersona').on('click', () => this.guardarPersona(false));

        $('#filtroMostrarInactivos').on('change', () => this.dataTable.draw());

        // Delegados en el tbody: sobreviven a los redibujos de DataTables
        $('#tablaPersonas tbody').on('click', '.btn-editar-persona', function() {
            ParametrosManager.abrirModalEditar($(this).data('id'));
        });

        $('#tablaPersonas tbody').on('change', '.switch-persona', function() {
            ParametrosManager.cambiarSwitch(this);
        });

        $('#personaNombre').on('input', () => {
            const original = $('#personaNombreOriginal').val();
            const cambio = original !== '' && $('#personaNombre').val().trim() !== original;
            $('#avisoRenombre').toggleClass('d-none', !cambio);
        });
    },

    /**
     * Guarda un switch al tocarlo. Si el servidor rechaza el cambio, el switch
     * vuelve a su estado anterior.
     */
    cambiarSwitch: function(input) {
        const $input = $(input);
        const id = $input.data('id');
        const campo = $input.data('campo');
        const valorNuevo = input.checked;
        const $fila = $input.closest('tr');

        $input.prop('disabled', true);

        const formData = new FormData();
        formData.append('accion', 'cambiarSwitch');
        formData.append('id', id);
        formData.append('campo', campo);
        formData.append('valor', valorNuevo ? '1' : '0');

        fetch('api/parametros.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (!data.exito) {
                    input.checked = !valorNuevo;
                    mostrarAlerta(escapeHtml(data.mensaje || 'No se pudo guardar el cambio'), 'error');
                    return;
                }

                const nombre = data.persona ? data.persona.Nombre : '';
                const accion = valorNuevo ? 'prendido' : 'apagado';
                mostrarAlerta(`${this.nombresCampo[campo]} ${accion} para ${escapeHtml(nombre)}`, 'success');

                // Actualizar la fila con lo que quedó en la base (etiqueta "Sin mail",
                // estilo de inactivo y filtro de inactivos)
                if (data.persona) {
                    const fila = this.dataTable.row($fila.hasClass('child') ? $fila.prev() : $fila);
                    fila.data(data.persona);
                    $(fila.node()).toggleClass('text-muted', data.persona.Activo != 1);
                    this.dataTable.draw(false);
                }
            })
            .catch(error => {
                input.checked = !valorNuevo;
                console.error('Error al cambiar el switch:', error);
                mostrarAlerta(escapeHtml('Error al guardar el cambio: ' + error.message), 'error');
            })
            .finally(() => {
                $input.prop('disabled', false);
            });
    },

    abrirModalNuevo: function() {
        document.getElementById('formPersona').reset();
        $('#personaId').val('');
        $('#personaActivo').val('1');
        $('#personaNombreOriginal').val('');
        $('#avisoRenombre').addClass('d-none');
        $('#modalPersonaTitulo').text('Nueva persona');
        this.ocultarErrorModal();
        this.modalPersona.show();
    },

    abrirModalEditar: function(id) {
        fetch(`api/parametros.php?accion=obtener&id=${encodeURIComponent(id)}`)
            .then(response => response.json())
            .then(data => {
                if (!data.exito) {
                    mostrarAlerta(escapeHtml(data.mensaje || 'No se pudo obtener la persona'), 'error');
                    return;
                }

                const persona = data.persona;

                document.getElementById('formPersona').reset();
                $('#personaId').val(persona.ID);
                $('#personaActivo').val(persona.Activo);
                $('#personaNombreOriginal').val(persona.Nombre || '');
                $('#personaNombre').val(persona.Nombre || '');
                $('#personaMail').val(persona.Mail || '');
                $('#personaEsSolicitante').prop('checked', persona.EsSolicitante == 1);
                $('#personaEsLider').prop('checked', persona.EsLider == 1);
                $('#personaEsDesarrollador').prop('checked', persona.EsDesarrollador == 1);
                $('#avisoRenombre').addClass('d-none');

                $('#modalPersonaTitulo').text('Editar persona');
                this.ocultarErrorModal();
                this.modalPersona.show();
            })
            .catch(error => {
                console.error('Error al obtener la persona:', error);
                mostrarAlerta(escapeHtml('No se pudo obtener la persona: ' + error.message), 'error');
            });
    },

    /**
     * Validación rápida del modal (el servidor valida lo mismo)
     */
    validarModal: function() {
        const nombre = $('#personaNombre').val().trim();
        const mail = $('#personaMail').val().trim();
        const esSolicitante = $('#personaEsSolicitante').is(':checked');
        const esLider = $('#personaEsLider').is(':checked');
        const esDesarrollador = $('#personaEsDesarrollador').is(':checked');
        const activo = $('#personaActivo').val() === '1';

        if (nombre === '') return 'El nombre es obligatorio';
        if (mail !== '' && !document.getElementById('personaMail').checkValidity()) return 'El mail no tiene un formato válido';
        if ((esSolicitante || esLider) && mail === '') return 'El mail es obligatorio para Solicitantes y Líderes (reciben avisos automáticos)';
        if (activo && !esSolicitante && !esLider && !esDesarrollador) {
            return 'Una persona activa tiene que tener al menos un rol (Solicitante, Líder o Desarrollador)';
        }

        return null;
    },

    guardarPersona: function(confirmarRenombre) {
        const error = this.validarModal();
        if (error) {
            this.mostrarErrorModal(error);
            return;
        }

        const nombre = $('#personaNombre').val().trim();

        const formData = new FormData();
        formData.append('accion', 'guardar');
        formData.append('id', $('#personaId').val());
        formData.append('Nombre', nombre);
        formData.append('Mail', $('#personaMail').val().trim());
        formData.append('EsSolicitante', $('#personaEsSolicitante').is(':checked') ? '1' : '0');
        formData.append('EsLider', $('#personaEsLider').is(':checked') ? '1' : '0');
        formData.append('EsDesarrollador', $('#personaEsDesarrollador').is(':checked') ? '1' : '0');
        if (confirmarRenombre) {
            formData.append('confirmarRenombre', '1');
        }

        const $btn = $('#btnGuardarPersona');
        $btn.prop('disabled', true);

        fetch('api/parametros.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.requiereConfirmacion) {
                    const c = data.conteo || {};
                    const original = $('#personaNombreOriginal').val();
                    const mensaje =
                        `Cambiar el nombre de "${original}" a "${nombre}" lo actualiza en ${c.Total} proyecto(s):\n\n` +
                        `  • como Solicitante: ${c.Solicitante}\n` +
                        `  • como Líder: ${c.LiderProyecto}\n` +
                        `  • como Desarrollador: ${c.Desarrollador}\n\n` +
                        '¿Confirmás el cambio?';

                    if (confirm(mensaje)) {
                        this.guardarPersona(true);
                    }
                    return;
                }

                if (!data.exito) {
                    this.mostrarErrorModal(data.mensaje || 'No se pudo guardar la persona');
                    return;
                }

                this.modalPersona.hide();
                mostrarAlerta(escapeHtml(data.mensaje), 'success');
                this.dataTable.ajax.reload(null, false);
            })
            .catch(error => {
                console.error('Error al guardar la persona:', error);
                this.mostrarErrorModal('Error al guardar la persona: ' + error.message);
            })
            .finally(() => {
                $btn.prop('disabled', false);
            });
    },

    mostrarErrorModal: function(mensaje) {
        const errorEl = document.getElementById('modalPersonaError');
        if (errorEl) {
            errorEl.textContent = mensaje;
            errorEl.classList.remove('d-none');
        }
    },

    ocultarErrorModal: function() {
        const errorEl = document.getElementById('modalPersonaError');
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.add('d-none');
        }
    }
};
