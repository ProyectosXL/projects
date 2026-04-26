
<?php
require_once __DIR__ . '/proyecto.php';
$p = new Proyecto();
$conn = $p->getConexion();
$sql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'HistorialProyectos' 
        AND COLUMN_NAME = 'Funcionalidad'";
$stmt = sqlsrv_query($conn, $sql);
while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    print_r($row);
}
