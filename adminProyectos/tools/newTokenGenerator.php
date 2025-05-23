
<?php
// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

// Configuración con tu URL real
$clientId = '426890158878-qlk4dsktcsnc6odt9e8rbupqpskhmtsk.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = 'http://app.xl.com.ar:8080/projects/adminProyectos/tools/newTokenGenerator.php';

echo "<h1>Generador de Token para Google Drive</h1>";

$client = new Google\Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->setAccessType('offline');
$client->setPrompt('consent'); // Forzar generación de refresh token
$client->addScope(Google\Service\Drive::DRIVE);

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        echo "<h2>¡Éxito!</h2>";
        
        if (!isset($token['refresh_token'])) {
            echo "<p style='color:red'><strong>ADVERTENCIA: No se recibió refresh token.</strong></p>";
            echo "<p>Esto puede ocurrir si ya has autorizado esta aplicación antes y no has revocado el acceso.</p>";
        } else {
            echo "<p style='color:green'><strong>Se ha generado un nuevo refresh token correctamente.</strong></p>";
        }
        
        echo "<h3>Token completo (guarda esto en un lugar seguro):</h3>";
        echo "<pre style='background-color:#f8f8f8; padding:10px; border:1px solid #ddd; overflow-x:scroll;'>";
        echo json_encode($token, JSON_PRETTY_PRINT);
        echo "</pre>";
        
        if (isset($token['refresh_token'])) {
            echo "<h3>Solo refresh token:</h3>";
            echo "<code style='background-color:#f8f8f8; padding:10px; border:1px solid #ddd; display:block'>";
            echo $token['refresh_token'];
            echo "</code>";
        }
        
        echo "<h3>Implementa este código en GoogleDriveAPI.php:</h3>";
        
        echo "<pre style='background-color:#f8f8f8; padding:10px; border:1px solid #ddd; overflow-x:scroll;'>";
        echo htmlspecialchars('
/**
 * Inicializa el cliente de Google API
 */
private function initGoogleClient() {
    try {
        require_once __DIR__ . \'/../vendor/autoload.php\';
        
        // Crear cliente
        $this->client = new Google\Client();
        $this->client->setClientId($this->clientId);
        $this->client->setClientSecret($this->clientSecret);
        $this->client->setRedirectUri($this->redirectUri);
        
        // Usar el token completo generado
        $token = ' . json_encode($token) . ';
        $this->client->setAccessToken($token);
        
        // Refrescar token si es necesario
        if ($this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithRefreshToken($token[\'refresh_token\']);
        }
        
        // Crear servicio de Google Drive
        $this->service = new Google\Service\Drive($this->client);
        
    } catch (Exception $e) {
        throw new Exception(\'Error al iniciar el cliente de Google API: \' . $e->getMessage());
    }
}');
        echo "</pre>";
        
    } catch (Exception $e) {
        echo "<h2 style='color:red'>Error al obtener el token</h2>";
        echo "<p>{$e->getMessage()}</p>";
        echo "<pre>{$e->getTraceAsString()}</pre>";
    }
} else {
    $authUrl = $client->createAuthUrl();
    echo "<p>Para generar un nuevo token, sigue estos pasos:</p>";
    echo "<ol>";
    echo "<li>Primero, revoca el acceso previo a esta aplicación en <a href='https://myaccount.google.com/permissions' target='_blank'>Google Account Permissions</a></li>";
    echo "<li>Luego, haz clic en el botón a continuación para autorizar nuevamente:</li>";
    echo "</ol>";
    echo "<a href='$authUrl' style='display:inline-block; background-color:#4285f4; color:white; padding:10px 20px; text-decoration:none; border-radius:4px; font-weight:bold;'>Autorizar Acceso</a>";
}
?>