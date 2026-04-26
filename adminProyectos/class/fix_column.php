
<?php
require_once __DIR__ . '/proyecto.php';
$p = new Proyecto();
$conn = $p->getConexion();
$sql = "ALTER TABLE HistorialProyectos ALTER COLUMN Funcionalidad NVARCHAR(MAX)";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    print_r(sqlsrv_errors());
} else {
    echo "SUCCESS: Column Funcionalidad is now NVARCHAR(MAX)\n";
}
