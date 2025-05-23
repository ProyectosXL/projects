
<?php
// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar autoload de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Configuración (usar los mismos valores que en GoogleDriveAPI.php)
$clientId = '426890158878-qlk4dsktcsnc6odt9e8rbupqpskhmtsk.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = 'http://app.xl.com.ar:8080/projects/adminProyectos/tools/refreshGoogleToken.php';

// Crear cliente de Google API
$client = new Google_Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->setAccessType('offline');
$client->setPrompt('consent');  // Esto forzará la generación de un nuevo refresh token
$client->setIncludeGrantedScopes(true);
$client->setScopes(['https://www.googleapis.com/auth/drive']);

// Verificar si recibimos un código de autorización
if (isset($_GET['code'])) {
    try {
        // Intercambiar el código de autorización por un token de acceso
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);
        
        echo '<h1>Resultado</h1>';
        
        // Verificar si obtuvimos un refresh token
        if (isset($token['refresh_token'])) {
            echo '<div style="background-color: #dff0d8; padding: 15px; border-radius: 4px; margin-bottom: 20px;">';
            echo '<h2 style="color: #3c763d;">Éxito: Token generado correctamente</h2>';
            echo '<p><strong>Refresh Token:</strong> <code>' . $token['refresh_token'] . '</code></p>';
            echo '<p>Copia este token y actualiza el valor en la clase GoogleDriveAPI.php</p>';
            echo '</div>';
        } else {
            echo '<div style="background-color: #f2dede; padding: 15px; border-radius: 4px; margin-bottom: 20px;">';
            echo '<h2 style="color: #a94442;">Error: No se obtuvo refresh token</h2>';
            echo '<p>El token de acceso se obtuvo correctamente pero no incluye un refresh token.</p>';
            echo '<p>Esto puede ocurrir si ya has autorizado esta aplicación antes. Prueba a revocar el acceso en tu cuenta de Google y vuelve a intentarlo.</p>';
            echo '</div>';
            
            // Mostrar los detalles del token para depuración
            echo '<h3>Detalles del token obtenido:</h3>';
            echo '<pre>' . json_encode($token, JSON_PRETTY_PRINT) . '</pre>';
        }
    } catch (Exception $e) {
        echo '<div style="background-color: #f2dede; padding: 15px; border-radius: 4px; margin-bottom: 20px;">';
        echo '<h2 style="color: #a94442;">Error: ' . htmlspecialchars($e->getMessage()) . '</h2>';
        echo '</div>';
        
        echo '<h3>Detalles del error:</h3>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
} else {
    // Generar URL de autorización
    $authUrl = $client->createAuthUrl();
    
    echo '<h1>Regeneración de Token de Google Drive</h1>';
    echo '<p>Para regenerar el token de acceso a Google Drive, siga estos pasos:</p>';
    echo '<ol>';
    echo '<li>Haga clic en el siguiente enlace para autorizar el acceso: ';
    echo '<a href="' . $authUrl . '" style="color: #337ab7; text-decoration: none; font-weight: bold;">Autorizar Acceso</a></li>';
    echo '<li>Inicie sesión con la cuenta de Google que desee utilizar.</li>';
    echo '<li>Será redirigido de vuelta a esta página, donde se mostrará el nuevo token.</li>';
    echo '</ol>';
}
?>