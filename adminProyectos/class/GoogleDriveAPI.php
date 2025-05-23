
<?php
/**
 * Clase para gestionar la integración con Google Drive
 */
class GoogleDriveAPI {
    // Cliente y servicio
    private $client;
    private $service;
    
    // Credenciales
    private $clientId = '426890158878-qlk4dsktcsnc6odt9e8rbupqpskhmtsk.apps.googleusercontent.com';
    private $clientSecret = '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';
    private $redirectUri = 'http://app.xl.com.ar:8080/projects/adminProyectos/tools/configureGoogleDrive.php';
    
    // IDs de carpetas principales
    private $mainFolderId = null;
    private $projectsFolderId = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->initGoogleClient();
    }
    
    /**
     * Inicializa el cliente de Google API
     */
    private function initGoogleClient() {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            // Crear cliente
            $this->client = new Google\Client();
            $this->client->setClientId($this->clientId);
            $this->client->setClientSecret($this->clientSecret);
            $this->client->setRedirectUri($this->redirectUri);
            
            // Usar el token completo generado
            $token = json_decode('{"access_token":"ya29.a0AZYkNZg26IwEYt3-t2_u7UFj6G-NLu2-NARFLHFct5j6f2tgxwNnVNOgHTsSdKlOnO7jAC0xop4QQUNd64X9YbJTiV3IXuxEPC0E0K6FKyQTxS8YohHm7ScLdDGn_0nGfPgmSBNPrbl8mONLjdDa9VVbM5uQviOKCJN14TzZaCgYKAYMSARISFQHGX2MiKR3Gyc7N_pVpFgsl3DFkNg0175","expires_in":3599,"refresh_token":"1\/\/0hGTJZywKniwzCgYIARAAGBESNwF-L9IrZY72ICDiooqjQQOWydGo2_IUGuvqFkkUtneOHQMQIqhhoW7xZf4Qw31_XwRsdyb292s","scope":"https:\/\/www.googleapis.com\/auth\/drive","token_type":"Bearer","created":1744948668}', true);
            $this->client->setAccessToken($token);
            
            // Refrescar token si es necesario
            if ($this->client->isAccessTokenExpired()) {
                $this->client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
            }
            
            // Crear servicio de Google Drive
            $this->service = new Google\Service\Drive($this->client);
            
        } catch (Exception $e) {
            throw new Exception('Error al iniciar el cliente de Google API: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtiene o crea la estructura de carpetas principal
     * @return string ID de la carpeta del proyecto específico
     */
    public function setupFolderStructure($proyectoId) {
        // 1. Obtener o crear carpeta principal 'AdminProyectos'
        $this->mainFolderId = $this->getOrCreateFolder('AdminProyectos');
        
        // 2. Obtener o crear subcarpeta 'Proyectos'
        $this->projectsFolderId = $this->getOrCreateFolder('Proyectos', $this->mainFolderId);
        
        // 3. Obtener o crear carpeta específica para este proyecto
        $projectFolderName = 'Proyecto_' . $proyectoId;
        $projectFolderId = $this->getOrCreateFolder($projectFolderName, $this->projectsFolderId);
        
        return $projectFolderId;
    }
    
    /**
     * Sube un archivo a Google Drive en la carpeta del proyecto
     * 
     * @param string $filePath Ruta local al archivo a subir
     * @param string $fileName Nombre que tendrá el archivo en Google Drive
     * @param int $proyectoId ID del proyecto al que pertenece el archivo
     * @param string $mimeType (opcional) Tipo MIME del archivo
     * @return array Información del archivo subido
     */
    public function uploadProjectFile($filePath, $fileName, $proyectoId, $mimeType = null) {
        try {
            // Establecer estructura de carpetas y obtener el ID de la carpeta del proyecto
            $folderId = $this->setupFolderStructure($proyectoId);
            
            // Subir el archivo a la carpeta del proyecto
            return $this->uploadFile($filePath, $fileName, $folderId, $mimeType);
        } catch (Exception $e) {
            throw new Exception('Error al subir archivo del proyecto a Google Drive: ' . $e->getMessage());
        }
    }
    
    /**
     * Sube un archivo a Google Drive
     * 
     * @param string $filePath Ruta local al archivo a subir
     * @param string $fileName Nombre que tendrá el archivo en Google Drive
     * @param string $folderId (opcional) ID de la carpeta de Google Drive donde guardar el archivo
     * @param string $mimeType (opcional) Tipo MIME del archivo
     * @return array Información del archivo subido
     */
    public function uploadFile($filePath, $fileName, $folderId = null, $mimeType = null) {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("El archivo no existe: $filePath");
            }
            
            // Determinar tipo MIME si no se proporciona
            if ($mimeType === null) {
                $mimeType = mime_content_type($filePath);
            }
            
            // Crear metadatos del archivo
            $fileMetadata = new Google\Service\Drive\DriveFile([
                'name' => $fileName,
            ]);
            
            // Si se especificó una carpeta, añadirla a los metadatos
            if ($folderId) {
                $fileMetadata->setParents([$folderId]);
            }
            
            // Leer contenido del archivo
            $content = file_get_contents($filePath);
            
            // Crear opciones para la subida
            $options = [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,name,webViewLink'
            ];
            
            // Subir archivo
            $file = $this->service->files->create($fileMetadata, $options);
            
            // Configurar permisos (hacerlo accesible vía enlace)
            $permission = new Google\Service\Drive\Permission([
                'type' => 'anyone',
                'role' => 'reader'
            ]);
            
            $this->service->permissions->create($file->getId(), $permission);
            
            // Devolver información del archivo
            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'webViewLink' => $file->getWebViewLink()
            ];
            
        } catch (Exception $e) {
            throw new Exception('Error al subir archivo a Google Drive: ' . $e->getMessage());
        }
    }
    
    /**
     * Crea una carpeta en Google Drive
     * 
     * @param string $folderName Nombre de la carpeta
     * @param string $parentFolderId (opcional) ID de la carpeta padre
     * @return string ID de la carpeta creada
     */
    public function createFolder($folderName, $parentFolderId = null) {
        try {
            // Metadatos de la carpeta
            $folderMetadata = new Google\Service\Drive\DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder'
            ]);
            
            // Si se proporciona una carpeta padre, la asignamos
            if ($parentFolderId) {
                $folderMetadata->setParents([$parentFolderId]);
            }
            
            // Crear la carpeta
            $folder = $this->service->files->create($folderMetadata, [
                'fields' => 'id'
            ]);
            
            return $folder->getId();
            
        } catch (Exception $e) {
            throw new Exception('Error al crear carpeta en Google Drive: ' . $e->getMessage());
        }
    }
    
    /**
     * Busca una carpeta por nombre en Google Drive
     * 
     * @param string $folderName Nombre de la carpeta a buscar
     * @param string $parentFolderId (opcional) ID de la carpeta padre donde buscar
     * @return string|null ID de la carpeta si se encuentra, null si no
     */
    public function findFolder($folderName, $parentFolderId = null) {
        try {
            $query = "mimeType='application/vnd.google-apps.folder' and name='$folderName' and trashed=false";
            
            // Si se proporciona una carpeta padre, limitamos la búsqueda
            if ($parentFolderId) {
                $query .= " and '$parentFolderId' in parents";
            }
            
            $response = $this->service->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name)'
            ]);
            
            $files = $response->getFiles();
            
            if (count($files) > 0) {
                return $files[0]->getId();
            }
            
            return null;
            
        } catch (Exception $e) {
            throw new Exception('Error al buscar carpeta en Google Drive: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtiene o crea una carpeta en Google Drive
     * 
     * @param string $folderName Nombre de la carpeta
     * @param string $parentFolderId (opcional) ID de la carpeta padre
     * @return string ID de la carpeta
     */
    public function getOrCreateFolder($folderName, $parentFolderId = null) {
        try {
            // Primero intentamos encontrar la carpeta
            $folderId = $this->findFolder($folderName, $parentFolderId);
            
            // Si no existe, la creamos
            if ($folderId === null) {
                $folderId = $this->createFolder($folderName, $parentFolderId);
            }
            
            return $folderId;
            
        } catch (Exception $e) {
            throw new Exception('Error al obtener o crear carpeta en Google Drive: ' . $e->getMessage());
        }
    }
}