
/**
 * Gestión de Programas de Proyectos (alta, edición, activar/desactivar)
 * Reutiliza mostrarAlerta() y escapeHtml() definidas en js/proyectos.js
 */

const ProgramasManager = {
    dataTable: null,
    modalPrograma: null,

    init: function() {
        this.inicializarTabla();
        this.setupEventListeners();

        const modalEl = document.getElementById('modalPrograma');
        if (modalEl) {
            this.modalPrograma = new bootstrap.Modal(modalEl);
        }
    },

    inicializarTabla: function() {
        this.dataTable = $('#tablaProgramas').DataTable({
            ajax: {
                url: 'api/programas.php?accion=listar',
                dataSrc: 'data',
                error: function(xhr, textStatus, error) {
                    console.error('Error en la solicitud AJAX:', textStatus, error);
                    mostrarAlerta('Error al cargar los programas. Consulte la consola para más detalles.', 'error');
                }
            },
            columns: [
                {
                    data: 'Nombre',
                    render: function(data, type, row) {
                        if (type !== 'display') return data || '';
                        const chip = row.Color
                            ? `<span class="programa-color-chip" style="background-color:${row.Color}"></span>`
                            : '';
                        return `${chip}${escapeHtml(data)}`;
                    }
                },
                {
                    data: 'Descripcion',
                    render: function(data, type) {
                        if (type !== 'display') return data || '';
                        return data ? escapeHtml(data) : '<span class="text-muted">-</span>';
                    }
                },
                {
                    data: 'Responsable',
                    render: function(data, type) {
                        if (type !== 'display') return data || '';
                        return data ? escapeHtml(data) : '<span class="text-muted">-</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        return `<span class="badge bg-secondary">${row.CantidadActivos} / ${row.CantidadProyectos}</span>`;
                    }
                },
                {
                    data: 'Activo',
                    render: function(data) {
                        return (data == 1)
                            ? '<span class="badge bg-success">Activo</span>'
                            : '<span class="badge bg-secondary">Inactivo</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        let botones = `<button class="btn btn-sm btn-outline-primary btn-editar-programa" data-id="${row.ID}" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button> `;

                        if (row.Activo == 1) {
                            botones += `<button class="btn btn-sm btn-outline-warning btn-cambiar-estado-programa" data-id="${row.ID}" data-activo="1" title="Desactivar">
                                        <i class="fas fa-ban"></i>
                                    </button>`;
                        } else {
                            botones += `<button class="btn btn-sm btn-outline-success btn-cambiar-estado-programa" data-id="${row.ID}" data-activo="0" title="Activar">
                                        <i class="fas fa-check"></i>
                                    </button>`;
                        }

                        return botones;
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
            },
            drawCallback: function() {
                $('.btn-editar-programa').off('click').on('click', function() {
                    ProgramasManager.abrirModalEditar($(this).data('id'));
                });

                $('.btn-cambiar-estado-programa').off('click').on('click', function() {
                    const id = $(this).data('id');
                    const activoActual = $(this).data('activo') == 1;
                    ProgramasManager.cambiarEstado(id, activoActual);
                });
            }
        });
    },

    setupEventListeners: function() {
        const btnNuevo = document.getElementById('btnNuevoPrograma');
        if (btnNuevo) {
            btnNuevo.addEventListener('click', () => this.abrirModalNuevo());
        }

        const btnGuardar = document.getElementById('btnGuardarPrograma');
        if (btnGuardar) {
            btnGuardar.addEventListener('click', () => this.guardarPrograma());
        }

        const btnSinColor = document.getElementById('btnSinColor');
        if (btnSinColor) {
            btnSinColor.addEventListener('click', () => {
                const inputColor = document.getElementById('programaColor');
                inputColor.value = '#6c757d';
                inputColor.dataset.sinColor = '1';
                this.actualizarPreviewColor();
            });
        }

        const inputColor = document.getElementById('programaColor');
        if (inputColor) {
            inputColor.addEventListener('input', () => {
                inputColor.dataset.sinColor = '0';
                this.actualizarPreviewColor();
            });
        }

        const inputNombre = document.getElementById('programaNombre');
        if (inputNombre) {
            inputNombre.addEventListener('input', () => this.actualizarPreviewColor());
        }
    },

    actualizarPreviewColor: function() {
        const preview = document.getElementById('previewColorPrograma');
        const inputColor = document.getElementById('programaColor');
        const inputNombre = document.getElementById('programaNombre');
        if (!preview || !inputColor) return;

        const sinColor = inputColor.dataset.sinColor === '1';
        preview.style.backgroundColor = sinColor ? '#6c757d' : inputColor.value;
        preview.style.color = '#fff';
        preview.textContent = (inputNombre && inputNombre.value.trim() !== '') ? inputNombre.value.trim() : 'Vista previa';
    },

    abrirModalNuevo: function() {
        document.getElementById('formPrograma').reset();
        document.getElementById('programaId').value = '';
        document.getElementById('modalProgramaTitulo').textContent = 'Nuevo programa';

        const inputColor = document.getElementById('programaColor');
        inputColor.value = '#0d6efd';
        inputColor.dataset.sinColor = '0';

        this.ocultarErrorModal();
        this.actualizarPreviewColor();
        this.modalPrograma.show();
    },

    abrirModalEditar: function(id) {
        fetch(`api/programas.php?accion=obtener&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (!data.exito) {
                    mostrarAlerta(data.mensaje || 'No se pudo obtener el programa', 'error');
                    return;
                }

                const programa = data.programa;

                document.getElementById('formPrograma').reset();
                document.getElementById('programaId').value = programa.ID;
                document.getElementById('programaNombre').value = programa.Nombre || '';
                document.getElementById('programaDescripcion').value = programa.Descripcion || '';
                document.getElementById('programaResponsable').value = programa.Responsable || '';

                const inputColor = document.getElementById('programaColor');
                if (programa.Color) {
                    inputColor.value = programa.Color;
                    inputColor.dataset.sinColor = '0';
                } else {
                    inputColor.value = '#6c757d';
                    inputColor.dataset.sinColor = '1';
                }

                document.getElementById('modalProgramaTitulo').textContent = 'Editar programa';
                this.ocultarErrorModal();
                this.actualizarPreviewColor();
                this.modalPrograma.show();
            })
            .catch(error => {
                console.error('Error al obtener programa:', error);
                mostrarAlerta('No se pudo obtener el programa: ' + error.message, 'error');
            });
    },

    guardarPrograma: function() {
        const nombre = document.getElementById('programaNombre').value.trim();

        if (nombre === '') {
            this.mostrarErrorModal('El nombre es obligatorio');
            return;
        }

        const inputColor = document.getElementById('programaColor');
        const sinColor = inputColor.dataset.sinColor === '1';

        const formData = new FormData();
        formData.append('accion', 'guardar');
        formData.append('id', document.getElementById('programaId').value);
        formData.append('Nombre', nombre);
        formData.append('Descripcion', document.getElementById('programaDescripcion').value.trim());
        formData.append('Responsable', document.getElementById('programaResponsable').value.trim());
        formData.append('Color', sinColor ? '' : inputColor.value);

        fetch('api/programas.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (!data.exito) {
                    this.mostrarErrorModal(data.mensaje || 'No se pudo guardar el programa');
                    return;
                }

                this.modalPrograma.hide();
                mostrarAlerta(data.mensaje, 'success');
                this.dataTable.ajax.reload(null, false);
            })
            .catch(error => {
                console.error('Error al guardar programa:', error);
                this.mostrarErrorModal('Error al guardar el programa: ' + error.message);
            });
    },

    cambiarEstado: function(id, activoActual) {
        const accion = activoActual ? 'desactivar' : 'activar';
        if (!confirm(`¿Está seguro que desea ${accion} este programa?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('accion', 'cambiarEstado');
        formData.append('id', id);
        formData.append('activo', activoActual ? '0' : '1');

        fetch('api/programas.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (!data.exito) {
                    mostrarAlerta(data.mensaje || 'No se pudo cambiar el estado del programa', 'error');
                    return;
                }

                mostrarAlerta(data.mensaje, 'success');
                this.dataTable.ajax.reload(null, false);
            })
            .catch(error => {
                console.error('Error al cambiar estado del programa:', error);
                mostrarAlerta('Error al cambiar el estado del programa: ' + error.message, 'error');
            });
    },

    mostrarErrorModal: function(mensaje) {
        const errorEl = document.getElementById('modalProgramaError');
        if (errorEl) {
            errorEl.textContent = mensaje;
            errorEl.classList.remove('d-none');
        }
    },

    ocultarErrorModal: function() {
        const errorEl = document.getElementById('modalProgramaError');
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.add('d-none');
        }
    }
};
