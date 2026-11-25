<?php
/**
 * Pestaña "Parámetros" de la barra de navegación.
 * Solo se muestra a quien tiene el permiso del hub (PermisosProyectos::CLAVE_PARAMETROS).
 * Ocultarla es cosmético: parametros.php y api/parametros.php validan el
 * permiso en el servidor.
 *
 * Requiere que la página haya llamado a session_start() antes de emitir salida.
 * Opcional: $navParametrosActivo = true para marcarla como activa.
 */
require_once __DIR__ . '/../class/permisos.php';

if (PermisosProyectos::puedeAdministrarParametros()): ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo !empty($navParametrosActivo) ? ' active' : ''; ?>" href="parametros.php">Parámetros</a>
                    </li>
<?php endif; ?>
