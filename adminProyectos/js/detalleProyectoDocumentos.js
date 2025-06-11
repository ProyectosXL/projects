
/**
 * detalleProyectoDocumentos.js
 * JavaScript para manejo de documentos en detalle de proyecto
 * Controla la carga, visualización y gestión de documentos (solo almacenamiento local)
 */

const DetalleProyectoDocumentos = {
    // ID del proyecto actual
    proyectoId: null,
    
    // Lista de documentos
    documentos: [],
    
    // Documento seleccionado actualmente para eliminar
    documentoSeleccionado: null,
    
    // Inicializa el módulo
    init: function(proyectoId) {
        this.proyectoId = proyectoId;
        this.setupEventListeners();
        this.setupModales();
        this.cargarDocumentos();
    },
    
    // Configura los event listeners
    setupEventListeners: function() {
        const self = this;
        
        // Botón para mostrar zona de subida
        const btnSubirDocumento = document.getElementById('btnSubirDocumento');
        if (btnSubirDocumento) {
            btnSubirDocumento.addEventListener('click', function() {
                document.getElementById('dropzone-container').classList.remove('d-none');
                this.classList.add('d-none');
            });
        }
        
        // Botón para cancelar subida
        const btnCancelarSubida = document.getElementById('btnCancelarSubida');
        if (btnCancelarSubida) {
            btnCancelarSubida.addEventListener('click', function() {
                document.getElementById('dropzone-container').classList.add('d-none');
                btnSubirDocumento.classList.remove('d-none');
            });
        }
        
        // Configuración de la zona de arrastrar y soltar
        const dropzone = document.getElementById('dropzone');
        if (dropzone) {
            // Eventos para simular la funcionalidad de arrastrar y soltar
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            // Cambiar estilo al arrastrar sobre la zona
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, function() {
                    dropzone.classList.add('border-primary');
                    dropzone.style.backgroundColor = '#e9ecef';
                }, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, function() {
                    dropzone.classList.remove('border-primary');
                    dropzone.style.backgroundColor = '#f8f9fa';
                }, false);
            });
            
            // Manejar archivos soltados
            dropzone.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length > 0) {
                    self.procesarArchivo(files[0]);
                }
            }, false);
            
            // Manejar clic para seleccionar archivos
            dropzone.addEventListener('click', function() {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.accept = '.pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip';
                
                fileInput.addEventListener('change', function(e) {
                    if (e.target.files.length > 0) {
                        self.procesarArchivo(e.target.files[0]);
                    }
                });
                
                fileInput.click();
            });
        }
    },
    
    // Configura los modales
    setupModales: function() {
        const self = this;
        
        // Modal de confirmación para eliminar documento
        const confirmarEliminarModal = document.getElementById('confirmarEliminarModal');
        if (confirmarEliminarModal) {
            // Botón para confirmar eliminación
            const btnConfirmarEliminar = document.getElementById('btnConfirmarEliminar');
            btnConfirmarEliminar.addEventListener('click', function() {
                self.eliminarDocumento();
                bootstrap.Modal.getInstance(confirmarEliminarModal).hide();
            });
        }
    },
    
    cargarDocumentos: function() {
        // Obtener de la API
        fetch(`api/detalleProyecto.php?id=${this.proyectoId}&accion=documentos`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Error al cargar documentos: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.exito) {
                    this.documentos = data.documentos;
                    this.actualizarVistaDocumentos();
                    this.notificarCantidadDocumentos();
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                DetalleProyectoUI.mostrarNotificacion('No se pudieron cargar los documentos', 'error');
                
                // Mostrar tabla vacía
                this.documentos = [];
                this.actualizarVistaDocumentos();
                this.notificarCantidadDocumentos();
            });
    },
    
    // Actualiza la vista de documentos en la tabla
    actualizarVistaDocumentos: function() {
        const container = document.getElementById('documentos-container');
        
        if (!container) return;
        
        // Limpiar contenedor
        container.innerHTML = '';
        
        // Si no hay documentos, mostrar mensaje
        if (this.documentos.length === 0) {
            container.innerHTML = `
                <tr class="sin-documentos">
                    <td colspan="6" class="text-center py-4">
                        <i class="fas fa-file-alt fa-3x text-muted mb-3 d-block"></i>
                        <p>No hay documentos asociados a este proyecto</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        // Agregar documentos a la tabla
        this.documentos.forEach(doc => {
            const iconoClase = this.obtenerIconoDocumento(doc.tipo);
            
            container.innerHTML += `
                <tr data-id="${doc.id}">
                    <td>
                        <i class="${iconoClase} me-2"></i>
                        ${doc.nombre}
                    </td>
                    <td>${this.obtenerTipoDocumento(doc.tipo)}</td>
                    <td>${doc.tamano}</td>
                    <td>${doc.fecha}</td>
                    <td>${doc.usuario}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-ver" onclick="DetalleProyectoDocumentos.verDocumento(${doc.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-descargar" onclick="DetalleProyectoDocumentos.descargarDocumento(${doc.id})">
                            <i class="fas fa-download"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-eliminar" onclick="DetalleProyectoDocumentos.confirmarEliminar(${doc.id})">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    },
    
    // Notifica la cantidad de documentos disponibles
    notificarCantidadDocumentos: function() {
        const evento = new CustomEvent('documentosActualizados', {
            detail: {
                cantidad: this.documentos.length
            }
        });
        
        document.dispatchEvent(evento);
    },
    
    // Procesa un archivo para subirlo al servidor
    procesarArchivo: function(file) {
        // Verificar tamaño máximo (10MB)
        if (file.size > 10 * 1024 * 1024) {
            DetalleProyectoUI.mostrarNotificacion('El archivo es demasiado grande. El tamaño máximo permitido es 10MB.', 'warning');
            return;
        }
        
        // Verificar tipo de archivo
        const tiposPermitidos = [
            'application/pdf', 
            'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/png',
            'image/jpeg',
            'application/zip'
        ];
        
        if (!tiposPermitidos.includes(file.type)) {
            DetalleProyectoUI.mostrarNotificacion('Tipo de archivo no permitido. Por favor, suba archivos PDF, DOC, DOCX, XLS, XLSX, PNG, JPG o ZIP.', 'warning');
            return;
        }
        
        this.subirArchivo(file);
    },
    
    // Sube el archivo al servidor
    subirArchivo: function(file) {
        const dropzoneContainer = document.getElementById('dropzone-container');
        const dropzone = document.getElementById('dropzone');
        
        // Mostrar progreso
        dropzone.innerHTML = `
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
                <h5>Subiendo archivo...</h5>
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                </div>
                <p class="text-muted">${file.name} (${DetalleProyectoUI.formatearTamano(file.size)})</p>
            </div>
        `;
        
        // Crear FormData para enviar el archivo
        const formData = new FormData();
        formData.append('documento_0', file);
        formData.append('num_documentos', 1);
        formData.append('proyecto_id', this.proyectoId);
        
        // Hacer petición AJAX
        fetch('api/subirDocumento.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.exito) {
                // Recargar lista de documentos
                this.cargarDocumentos();
                
                // Ocultar zona de subida y mostrar botón
                dropzoneContainer.classList.add('d-none');
                document.getElementById('btnSubirDocumento').classList.remove('d-none');
                
                // Restaurar zona de subida
                this.restaurarDropzone();
                
                DetalleProyectoUI.mostrarNotificacion('Archivo subido correctamente', 'success');
            } else {
                throw new Error(data.mensaje || 'Error al subir archivo');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            DetalleProyectoUI.mostrarNotificacion('Error al subir el archivo: ' + error.message, 'error');
            
            // Restaurar zona de subida en caso de error
            this.restaurarDropzone();
        });
    },
    
    // Restaura la zona de dropzone a su estado original
    restaurarDropzone: function() {
        const dropzone = document.getElementById('dropzone');
        dropzone.innerHTML = `
            <div class="dz-message">
                <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                <h5>Arrastra archivos aquí o haz clic para seleccionar</h5>
                <p class="text-muted">Tipos permitidos: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, ZIP (Max. 10MB)</p>
            </div>
        `;
    },
    
    // Mostrar confirmación para eliminar documento
    confirmarEliminar: function(documentoId) {
        // Encontrar documento
        const documento = this.documentos.find(doc => doc.id === documentoId);
        
        if (!documento) return;
        
        // Guardar referencia al documento
        this.documentoSeleccionado = documento;
        
        // Actualizar modal
        document.getElementById('documentoNombre').textContent = documento.nombre;
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('confirmarEliminarModal'));
        modal.show();
    },
    
    // Eliminar documento
    eliminarDocumento: function() {
        if (!this.documentoSeleccionado) return;
        
        // Hacer petición para eliminar del servidor
        fetch(`api/eliminarDocumento.php?id=${this.documentoSeleccionado.id}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.exito) {
                // Recargar lista de documentos
                this.cargarDocumentos();
                DetalleProyectoUI.mostrarNotificacion('Documento eliminado correctamente', 'success');
            } else {
                throw new Error(data.mensaje || 'Error al eliminar documento');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            DetalleProyectoUI.mostrarNotificacion('Error al eliminar el documento: ' + error.message, 'error');
        });
        
        // Limpiar referencia
        this.documentoSeleccionado = null;
    },
    
    // Ver documento
    verDocumento: function(documentoId) {
        // Encontrar documento
        const documento = this.documentos.find(doc => doc.id === documentoId);
        
        if (!documento) return;
        
        // Configurar modal
        const visorContenido = document.getElementById('visorContenido');
        const btnDescargar = document.getElementById('btnDescargarDocumento');
        
        // Simular contenido según el tipo
        if (documento.tipo.startsWith('image/')) {
            // Es una imagen
            visorContenido.innerHTML = `
                <div class="text-center py-3">
                    <img src="${documento.url}" alt="${documento.nombre}" class="img-fluid" 
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div style="display:none;" class="py-5 bg-light">
                        <i class="fas fa-image fa-5x text-info mb-3"></i>
                        <h4>No se puede mostrar la imagen</h4>
                        <p>Descargue el archivo para visualizarlo</p>
                    </div>
                </div>
            `;
        } else if (documento.tipo === 'application/pdf') {
            // Es un PDF
            visorContenido.innerHTML = `
                <div class="text-center py-5 bg-light">
                    <i class="fas fa-file-pdf fa-5x text-danger mb-3"></i>
                    <h4>Vista previa no disponible</h4>
                    <p>Descargue el archivo para visualizarlo</p>
                </div>
            `;
        } else {
            // Otro tipo de documento
            visorContenido.innerHTML = `
                <div class="text-center py-5 bg-light">
                    <i class="fas fa-file fa-5x text-secondary mb-3"></i>
                    <h4>Vista previa no disponible</h4>
                    <p>Este tipo de archivo no se puede previsualizar</p>
                </div>
            `;
        }
        
        // Configurar botón de descarga
        btnDescargar.setAttribute('href', documento.url);
        btnDescargar.setAttribute('download', documento.nombre);
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('visorDocumentoModal'));
        modal.show();
    },
    
    // Descargar documento
    descargarDocumento: function(documentoId) {
        // Encontrar documento
        const documento = this.documentos.find(doc => doc.id === documentoId);
        
        if (!documento) return;
        
        // Crear enlace temporal para descarga
        const link = document.createElement('a');
        link.href = documento.url;
        link.download = documento.nombre;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        DetalleProyectoUI.mostrarNotificacion('Descarga iniciada...', 'info');
    },
    
    // Devuelve el tipo de documento formateado
    obtenerTipoDocumento: function(mimeType) {
        const tipos = {
            'application/pdf': 'PDF',
            'application/msword': 'DOC',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'DOCX',
            'application/vnd.ms-excel': 'XLS',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'XLSX',
            'image/png': 'PNG',
            'image/jpeg': 'JPG',
            'application/zip': 'ZIP'
        };
        
        return tipos[mimeType] || 'Desconocido';
    },
    
    // Devuelve la clase de icono según el tipo de documento
    obtenerIconoDocumento: function(mimeType) {
        if (mimeType === 'application/pdf') {
            return 'fas fa-file-pdf text-danger';
        } else if (mimeType === 'application/msword' || mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return 'fas fa-file-word text-primary';
        } else if (mimeType === 'application/vnd.ms-excel' || mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            return 'fas fa-file-excel text-success';
        } else if (mimeType.startsWith('image/')) {
            return 'fas fa-file-image text-info';
        } else if (mimeType === 'application/zip') {
            return 'fas fa-file-archive text-warning';
        } else {
            return 'fas fa-file text-secondary';
        }
    }
};