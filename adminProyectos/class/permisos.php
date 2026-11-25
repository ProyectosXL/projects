<?php
/**
 * PermisosProyectos
 * Verificación de permisos del hub de usuarios (Gestionusuarios) para
 * Gestión de Proyectos. Mismo patrón que AuthCashflow (finanzas/cashflow).
 *
 * Las tablas FP_ (FP_SOF_USUARIOS, FP_ROL_PERMISO, FP_PERMISOS...) NO están en
 * la base central que usa adminProyectos, sino en la base del hub
 * (HOST_APPS / DATABASE_APPS). Para llegar a ellas se usa la conexión del
 * propio hub: Gestionusuarios/config/Database.php -> connectApps().
 *
 * DEPENDENCIA DE DESPLIEGUE: Gestionusuarios tiene que estar en la misma
 * carpeta que projects (htdocs/Gestionusuarios y htdocs/projects).
 *
 * FALLA CERRADA: si no está el hub, si falla la conexión o si falla cualquier
 * consulta, el resultado es "sin permiso" (y se deja el error en el log).
 * Nunca se concede un permiso por error.
 */

define('PERMISOS_PROYECTOS_PADRON', __DIR__ . '/../../../Gestionusuarios/config/Database.php');

if (file_exists(PERMISOS_PROYECTOS_PADRON)) {
    require_once PERMISOS_PROYECTOS_PADRON;
}

class PermisosProyectos
{
    /**
     * Clave del hub (módulo DEI, sección Gestión de Proyectos) que habilita
     * administrar Parámetros › Personas. Es el único lugar donde está escrita.
     */
    const CLAVE_PARAMETROS = 'dei.item.gestion_de_proyectos_parametros';

    /** Resultado por clave, solo durante el pedido actual (no se guarda en sesión
     *  para que quitar el permiso desde el hub tenga efecto inmediato) */
    private static $cache = [];

    /** Datos del usuario en el hub (null = sin usuario / no se pudo leer) */
    private static $usuario = null;
    private static $usuarioLeido = false;

    /**
     * ¿El usuario de la sesión puede administrar Parámetros › Personas?
     */
    public static function puedeAdministrarParametros()
    {
        return self::tienePermiso(self::CLAVE_PARAMETROS);
    }

    /**
     * Username de la sesión, o null si no hay sesión iniciada.
     */
    public static function username()
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        $username = $_SESSION['username'] ?? null;
        return ($username !== null && trim($username) !== '') ? trim($username) : null;
    }

    /**
     * Verifica una clave del hub para el usuario de la sesión:
     *   - Pasan los administradores del hub (sector Proyectos + es_admin),
     *     con el mismo criterio que AuthCashflow / AuthCentral / menú DEI.
     *   - Para el resto de los roles solo cuenta la asignación EXPLÍCITA de la
     *     clave al rol en FP_ROL_PERMISO (no se aplica el criterio de "todo
     *     activo por defecto" que usa el hub para armar el menú).
     */
    public static function tienePermiso($clave)
    {
        if (array_key_exists($clave, self::$cache)) {
            return self::$cache[$clave];
        }

        self::$cache[$clave] = self::calcularPermiso($clave);
        return self::$cache[$clave];
    }

    private static function calcularPermiso($clave)
    {
        try {
            $usuario = self::usuarioHub();
            if ($usuario === null) {
                return false;
            }

            if ($usuario['es_admin']) {
                return true;
            }

            if (empty($usuario['rol_id'])) {
                return false;
            }

            $db = \GestionUsuarios\Config\Database::getInstance()->connectApps();

            $sql = "SELECT COUNT(*) AS Cantidad
                    FROM FP_ROL_PERMISO rp
                    INNER JOIN FP_PERMISOS p ON p.id = rp.permiso_id
                    WHERE rp.rol_id = ? AND p.clave = ?";

            $stmt = sqlsrv_query($db, $sql, [(int)$usuario['rol_id'], $clave]);

            if ($stmt === false) {
                error_log('PermisosProyectos: error al verificar la clave ' . $clave . ': ' . print_r(sqlsrv_errors(), true));
                return false;
            }

            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            return $row && (int)$row['Cantidad'] > 0;
        } catch (\Throwable $th) {
            error_log('PermisosProyectos: error al verificar la clave ' . $clave . ': ' . $th->getMessage());
            return false;
        }
    }

    /**
     * Lee el usuario de la sesión en FP_SOF_USUARIOS (activo) y calcula si es
     * administrador del hub. Devuelve null ante cualquier falla.
     */
    private static function usuarioHub()
    {
        if (self::$usuarioLeido) {
            return self::$usuario;
        }

        self::$usuarioLeido = true;

        $username = self::username();
        if ($username === null) {
            return null;
        }

        if (!class_exists('\GestionUsuarios\Config\Database')) {
            error_log('PermisosProyectos: no se encontró Gestionusuarios en ' . PERMISOS_PROYECTOS_PADRON . '; se deniegan los permisos.');
            return null;
        }

        try {
            $db = \GestionUsuarios\Config\Database::getInstance()->connectApps();

            $sql = "SELECT u.id, u.username, u.rol_id, u.sector_id,
                           s.codigo AS sector_codigo, s.nombre AS sector_nombre,
                           r.codigo AS rol_codigo, r.es_admin
                    FROM FP_SOF_USUARIOS u
                    LEFT JOIN FP_SECTORES s ON s.id = u.sector_id
                    LEFT JOIN FP_ROLES_PERMISOS_MAP r ON r.id = u.rol_id
                    WHERE UPPER(TRIM(u.username)) = UPPER(TRIM(?)) AND u.activo = 1";

            $stmt = sqlsrv_query($db, $sql, [$username]);

            if ($stmt === false) {
                error_log('PermisosProyectos: error al leer el usuario ' . $username . ': ' . print_r(sqlsrv_errors(), true));
                return null;
            }

            $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if (!$user) {
                return null;
            }

            // Mismo criterio de administrador que AuthCashflow::init() y AuthCentral::user()
            $esSectorProyectos = ((int)($user['sector_id'] ?? 0) === 7)
                || (strtoupper(trim($user['sector_codigo'] ?? '')) === 'PROYECTOS')
                || (stripos($user['sector_nombre'] ?? '', 'Proyectos') !== false);

            $esAdmin = $esSectorProyectos && (
                !empty($user['es_admin'])
                || (int)($user['rol_id'] ?? 0) === 1
                || strtoupper(trim($user['rol_codigo'] ?? '')) === 'CONTROLTOTAL'
                || strtoupper(trim($user['username'] ?? '')) === 'FRANCOPERTUS'
            );

            self::$usuario = [
                'username' => $user['username'],
                'rol_id' => $user['rol_id'],
                'es_admin' => $esAdmin
            ];
        } catch (\Throwable $th) {
            error_log('PermisosProyectos: error al leer el usuario ' . $username . ': ' . $th->getMessage());
            self::$usuario = null;
        }

        return self::$usuario;
    }
}
