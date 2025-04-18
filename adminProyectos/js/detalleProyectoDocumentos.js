
/**
 * detalleProyectoDocumentos.js
 * JavaScript para manejo de documentos en detalle de proyecto
 * Controla la carga, visualización y gestión de documentos
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
        
        // Configuración de la zona de arrastrar y soltar (simulado)
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
            
            // Manejar archivos soltados (simulado)
            dropzone.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                // Simular subida
                if (files.length > 0) {
                    self.simularSubidaArchivo(files[0]);
                }
            }, false);
            
            // Manejar clic para seleccionar archivos
            dropzone.addEventListener('click', function() {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.accept = '.pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip';
                
                fileInput.addEventListener('change', function(e) {
                    if (e.target.files.length > 0) {
                        self.simularSubidaArchivo(e.target.files[0]);
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
        // Intentar obtener de la API
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
                    
                    // Notificar cantidad de documentos
                    this.notificarCantidadDocumentos();
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                DetalleProyectoUI.mostrarNotificacion('No se pudieron cargar los documentos', 'error');
                
                // SOLUCIÓN TEMPORAL: Mostrar documentos de muestra para evitar que la pestaña quede cargando
                this.documentos = [
                    {
                        id: 1,
                        nombre: "Documento de ejemplo.pdf",
                        tipo: "application/pdf",
                        tamano: "1.2 MB",
                        fecha: "2023-04-17",
                        usuario: "Admin",
                        url: "#"
                    }
                ];
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
        // Crear y disparar un evento personalizado
        const evento = new CustomEvent('documentosActualizados', {
            detail: {
                cantidad: this.documentos.length
            }
        });
        
        document.dispatchEvent(evento);
    },
    
    // Simula la subida de un archivo
    simularSubidaArchivo: function(file) {
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
        
        // Simular proceso de subida
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
        
        // Simular progreso
        let progreso = 0;
        const intervalo = setInterval(() => {
            progreso += 5;
            document.querySelector('.progress-bar').style.width = `${progreso}%`;
            
            if (progreso >= 100) {
                clearInterval(intervalo);
                
                // Simular finalización exitosa
                setTimeout(() => {
                    // Agregar documento a la lista
                    const nuevoDoc = {
                        id: this.documentos.length + 1,
                        nombre: file.name,
                        tipo: file.type,
                        tamano: DetalleProyectoUI.formatearTamano(file.size),
                        fecha: new Date().toISOString().split('T')[0],
                        usuario: 'Usuario Actual', // Aquí se usaría el usuario logueado
                        url: '#'
                    };
                    
                    this.documentos.push(nuevoDoc);
                    this.actualizarVistaDocumentos();
                    
                    // Notificar la nueva cantidad de documentos
                    this.notificarCantidadDocumentos();
                    
                    // Ocultar zona de subida y mostrar botón
                    dropzoneContainer.classList.add('d-none');
                    document.getElementById('btnSubirDocumento').classList.remove('d-none');
                    
                    // Restaurar zona de subida
                    dropzone.innerHTML = `
                        <div class="dz-message">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                            <h5>Arrastra archivos aquí o haz clic para seleccionar</h5>
                            <p class="text-muted">Tipos permitidos: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, ZIP (Max. 10MB)</p>
                        </div>
                    `;
                    
                    // Mostrar mensaje de éxito
                    DetalleProyectoUI.mostrarNotificacion('Archivo subido correctamente', 'success');
                }, 1000);
            }
        }, 100);
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
        
        // Eliminar documento de la lista
        this.documentos = this.documentos.filter(doc => doc.id !== this.documentoSeleccionado.id);
        
        // Actualizar vista
        this.actualizarVistaDocumentos();
        
        // Notificar la nueva cantidad de documentos
        this.notificarCantidadDocumentos();
        
        // Mostrar mensaje
        DetalleProyectoUI.mostrarNotificacion('Documento eliminado correctamente', 'success');
        
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
        
        // Simulamos contenido según el tipo
        if (documento.tipo.startsWith('image/')) {
            // Es una imagen
            visorContenido.innerHTML = `
                <div class="text-center py-3">
                    <img src="https://via.placeholder.com/1200x800" alt="${documento.nombre}" class="img-fluid">
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
        
        // En una implementación real, aquí se redigiría a la descarga
        // Para la simulación, mostramos un mensaje
        DetalleProyectoUI.mostrarNotificacion('Descarga iniciada...', 'info');
        
        // Simular una descarga creando un enlace temporal
        const link = document.createElement('a');
        link.href = 'https://example.com/archivo-simulado';
        link.download = documento.nombre;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
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