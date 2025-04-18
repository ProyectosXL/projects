
<?php
/**
 * Script para configurar las credenciales de Google Drive
 * 
 * Este script se debe usar una sola vez para configurar el acceso a Google Drive.
 * Procede en dos pasos:
 * 1. Genera una URL para autorizar el acceso (el admin debe hacer clic y autorizar)
 * 2. Recibe el código de autorización y genera el token de actualización (refresh token)
 */

// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar autoload de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Configuración (copiar del proyecto de Google Cloud Console)
$clientId = '426890158878-qlk4dsktcsnc6odt9e8rbupqpskhmtsk.apps.googleusercontent.com';     // Ingresar Client ID de Google Cloud Console
$clientSecret = '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'; // Ingresar Client Secret de Google Cloud Console
$redirectUri = 'http://app.xl.com.ar:8080/projects/adminProyectos/tools/configureGoogleDrive.php';  // URL de redirección autorizada en Google Cloud Console

// Verificar si tenemos las credenciales
if (empty($clientId) || empty($clientSecret) || empty($redirectUri)) {
    die('Por favor, configure las credenciales en el archivo configureGoogleDrive.php');
}

// Crear cliente de Google API
$client = new Google_Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->setAccessType('offline');
$client->setApprovalPrompt('force'); // Forzar la generación de refresh token
$client->setScopes(['https://www.googleapis.com/auth/drive']);

// Verificar si recibimos un código de autorización
if (isset($_GET['code'])) {
    // Intercambiar el código de autorización por un token de acceso
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (isset($token['refresh_token'])) {
        echo '<h1>Configuración completada</h1>';
        echo '<p>El token de actualización (refresh token) se ha generado correctamente.</p>';
        echo '<p><strong>Refresh Token:</strong> ' . $token['refresh_token'] . '</p>';
        echo '<p>Guarde este token en la configuración de la clase GoogleDriveAPI.</p>';
    } else {
        echo '<h1>Error</h1>';
        echo '<p>No se ha podido obtener el token de actualización. Por favor, intente de nuevo.</p>';
        echo '<pre>' . print_r($token, true) . '</pre>';
    }
} else {
    // Generar URL de autorización
    $authUrl = $client->createAuthUrl();
    
    echo '<h1>Configuración de Google Drive API</h1>';
    echo '<p>Para configurar el acceso a Google Drive, siga estos pasos:</p>';
    echo '<ol>';
    echo '<li>Haga clic en el siguiente enlace para autorizar el acceso a Google Drive: ';
    echo '<a href="' . $authUrl . '" target="_blank">Autorizar acceso</a></li>';
    echo '<li>Inicie sesión con la cuenta de Google que desee utilizar</li>';
    echo '<li>Conceda los permisos solicitados</li>';
    echo '<li>Será redirigido de vuelta a esta página, donde se mostrará el token de actualización</li>';
    echo '</ol>';
}