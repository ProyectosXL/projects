
<?php
// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Prueba de conexión a Google Drive</h1>";

try {
    // Verificar que el autoload existe
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new Exception("No se encontró el archivo de autoload en: $autoloadPath");
    }
    echo "<p>✓ Archivo de autoload encontrado</p>";
    
    // Verificar que las clases de Google API existen
    require_once $autoloadPath;
    
    if (!class_exists('Google\\Client')) {
        throw new Exception("La clase Google\\Client no existe. Verifica tu instalación de Google API Client.");
    }
    echo "<p>✓ Clase Google\\Client encontrada</p>";
    
    if (!class_exists('Google\\Service\\Drive')) {
        throw new Exception("La clase Google\\Service\\Drive no existe. Verifica tu instalación de Google API Client.");
    }
    echo "<p>✓ Clase Google\\Service\\Drive encontrada</p>";
    
    // Cargar clase GoogleDriveAPI
    $googleDriveApiPath = __DIR__ . '/../class/GoogleDriveAPI.php';
    if (!file_exists($googleDriveApiPath)) {
        throw new Exception("No se encontró el archivo GoogleDriveAPI.php en: $googleDriveApiPath");
    }
    echo "<p>✓ Archivo GoogleDriveAPI.php encontrado</p>";
    
    require_once $googleDriveApiPath;
    
    // Intentar crear instancia
    echo "<p>Inicializando GoogleDriveAPI...</p>";
    $googleDrive = new GoogleDriveAPI();
    echo "<p style='color:green'>✓ Instancia creada correctamente</p>";
    
    // Intentar crear estructura de carpetas
    echo "<p>Creando estructura de carpetas de prueba...</p>";
    $folderId = $googleDrive->setupFolderStructure(999); // ID temporal para prueba
    echo "<p style='color:green'>✓ Estructura creada correctamente</p>";
    echo "<p>ID de carpeta: $folderId</p>";
    
    // Intentar subir un archivo de prueba
    echo "<p>Creando archivo de prueba...</p>";
    $testFile = __DIR__ . '/test.txt';
    file_put_contents($testFile, 'Este es un archivo de prueba para Google Drive.');
    
    echo "<p>Subiendo archivo de prueba...</p>";
    $fileInfo = $googleDrive->uploadFile($testFile, 'test.txt', $folderId);
    echo "<p style='color:green'>✓ Archivo subido correctamente</p>";
    echo "<pre>" . print_r($fileInfo, true) . "</pre>";
    
    // Limpiar
    unlink($testFile);
    
    echo "<h2>Prueba completada con éxito</h2>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>Error en la prueba</h2>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Ubicación:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>";
    echo "<p><strong>Trace:</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}